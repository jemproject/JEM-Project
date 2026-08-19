<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Event reminder definitions, event selection and scheduled notification work.
 */
final class JemReminderService
{
    private $db;
    private $notifications;

    public function __construct($db = null)
    {
        $this->db = $db ?: Factory::getContainer()->get('DatabaseDriver');
        $this->notifications = new JemNotificationService($this->db);
    }

    public function getDefinitions($publishedOnly = false)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_reminders'))
            ->where($this->db->quoteName('event_id') . ' = 0')
            ->order(array($this->db->quoteName('ordering') . ' ASC', $this->db->quoteName('minutes') . ' DESC'));
        if ($publishedOnly) {
            $query->where($this->db->quoteName('published') . ' = 1');
        }
        $this->db->setQuery($query);

        return (array) $this->db->loadObjectList();
    }

    public function getEventDefinitionIds($eventId)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_reminders'))
            ->where($this->db->quoteName('event_id') . ' = ' . (int) $eventId);
        $this->db->setQuery($query);

        return array_map('intval', (array) $this->db->loadColumn());
    }

    public function getDefaultDefinitionIds()
    {
        if (!$this->isEnabled()) {
            return array();
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_reminders'))
            ->where($this->db->quoteName('event_id') . ' = 0')
            ->where($this->db->quoteName('published') . ' = 1')
            ->where($this->db->quoteName('default_new_event') . ' = 1')
            ->order($this->db->quoteName('ordering') . ' ASC');
        $this->db->setQuery($query);

        return array_map('intval', (array) $this->db->loadColumn());
    }

    /**
     * Replace the selected definitions and optionally propagate them to an
     * explicitly selected recurrence/custom-date series.
     */
    public function storeEventDefinitions($eventId, array $definitionIds, $actorId = 0, $applyToSeries = false)
    {
        $eventIds = $applyToSeries ? $this->getSeriesEventIds((int) $eventId) : array((int) $eventId);
        $eventIds = array_values(array_filter(array_unique(array_map('intval', $eventIds))));
        if (!$eventIds) {
            return false;
        }

        $definitions = $this->loadSelectionDefinitions($definitionIds, (int) $eventId);

        $now = Factory::getDate()->toSql();
        $this->db->transactionStart();
        try {
            foreach ($eventIds as $selectedEventId) {
                $query = $this->db->getQuery(true)
                    ->select('*')
                    ->from($this->db->quoteName('#__jem_reminders'))
                    ->where($this->db->quoteName('event_id') . ' = ' . $selectedEventId);
                $this->db->setQuery($query);
                $existing = (array) $this->db->loadObjectList();
                $existingByKey = array();
                foreach ($existing as $row) {
                    $existingByKey[$this->selectionKey($row)] = $row;
                }

                $keepIds = array();
                foreach ($definitions as $definition) {
                    $key = $this->selectionKey($definition);
                    if (isset($existingByKey[$key])) {
                        $keepIds[] = (int) $existingByKey[$key]->id;
                        continue;
                    }

                    $row = (object) array(
                        'event_id' => $selectedEventId,
                        'source_id' => (int) $definition->event_id === 0
                            ? (int) $definition->id
                            : ((int) $definition->source_id ?: null),
                        'code' => (string) $definition->code,
                        'title' => (string) $definition->title,
                        'minutes' => max(1, (int) $definition->minutes),
                        'published' => (int) (bool) $definition->published,
                        'default_new_event' => 0,
                        'ordering' => (int) $definition->ordering,
                        'created' => $now,
                        'modified' => $now,
                        'created_by' => (int) $actorId,
                    );
                    $this->db->insertObject('#__jem_reminders', $row, 'id');
                    $keepIds[] = (int) $row->id;
                }

                $deleteIds = array_values(array_diff(
                    array_map(static function ($row) { return (int) $row->id; }, $existing),
                    $keepIds
                ));
                foreach ($deleteIds as $deleteId) {
                    $this->notifications->cancelPendingReminders(0, 0, $deleteId);
                }
                if ($deleteIds) {
                    $query = $this->db->getQuery(true)
                        ->delete($this->db->quoteName('#__jem_reminders'))
                        ->where($this->db->quoteName('event_id') . ' = ' . $selectedEventId)
                        ->where($this->db->quoteName('id') . ' IN (' . implode(',', $deleteIds) . ')');
                    $this->db->setQuery($query)->execute();
                }
            }
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }

        foreach ($eventIds as $selectedEventId) {
            $this->syncEvent($selectedEventId, true);
        }

        return true;
    }

    /**
     * Copy the root selection into generated occurrences without changing an
     * existing series unless the caller explicitly requested propagation.
     */
    public function copyDefinitionsToGeneratedSeries($eventId, $actorId = 0)
    {
        $definitionIds = $this->getEventDefinitionIds((int) $eventId);
        if (!$definitionIds) {
            return true;
        }

        return $this->storeEventDefinitions((int) $eventId, $definitionIds, (int) $actorId, true);
    }

    public function syncEvent($eventId, $force = false)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('event') . ' = ' . (int) $eventId);
        $this->db->setQuery($query);
        $count = 0;
        foreach ((array) $this->db->loadColumn() as $registrationId) {
            $count += $this->syncRegistration((int) $registrationId, (bool) $force);
        }

        return $count;
    }

    /**
     * Reconcile the future reminder snapshots for one registration.
     */
    public function syncRegistration($registrationId, $force = false)
    {
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)) {
            return 0;
        }

        $registration = $this->loadRegistration((int) $registrationId);
        if (!$registration || !$this->isEligible($registration) || !$this->isEnabled()) {
            $this->notifications->cancelPendingReminders((int) $registrationId);
            return 0;
        }

        $definitions = $this->getSelectedDefinitions((int) $registration->event_id);
        if (!$definitions) {
            $this->notifications->cancelPendingReminders((int) $registrationId);
            return 0;
        }

        $now = Factory::getDate()->toUnix();
        $keptIds = array();
        $created = 0;
        foreach ($definitions as $definition) {
            $scheduledAt = $this->calculateDueUtc($registration, $definition);
            if ($scheduledAt === null || Factory::getDate($scheduledAt)->toUnix() <= $now) {
                continue;
            }

            $existing = $this->findActiveReminder(
                (int) $registration->registration_id,
                (int) $definition->id,
                $scheduledAt
            );
            if ($existing && !$force) {
                $keptIds[] = (int) $existing->id;
                continue;
            }
            if ($existing) {
                $this->notifications->cancelPendingReminders(
                    (int) $registration->registration_id,
                    0,
                    (int) $definition->id
                );
            }

            $notification = $this->createReminder($registration, $definition, $scheduledAt, $force);
            if ($notification) {
                $keptIds[] = (int) $notification->id;
                $created++;
            }
        }

        $this->notifications->cancelPendingReminders((int) $registrationId, 0, 0, $keptIds);

        return $created;
    }

    /**
     * Schedule future eligible registrations after global activation.
     */
    public function syncAllFuture($limit = 5000)
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select('r.id')
            ->from($this->db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $this->db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
            ->where('r.status = 1')
            ->where('r.waiting = 0')
            ->where('e.dates IS NOT NULL')
            ->where("COALESCE(e.event_status, 'scheduled') <> " . $this->db->quote('cancelled'))
            ->order('r.id ASC');
        $this->db->setQuery($query, 0, max(1, (int) $limit));
        $created = 0;
        foreach ((array) $this->db->loadColumn() as $registrationId) {
            $created += $this->syncRegistration((int) $registrationId);
        }

        return $created;
    }

    /**
     * Run one scheduler batch. The sender receives an immutable notification id.
     */
    public function processDue(callable $sender, $limit = 100)
    {
        $result = (object) array('due' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'recovered' => 0, 'purged' => 0);
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)) {
            return $result;
        }

        $result->recovered = $this->notifications->recoverStaleProcessing(30);
        foreach ($this->notifications->getDueNotificationIds($limit) as $notificationId) {
            $notification = $this->notifications->getById($notificationId);
            if (!$notification) {
                continue;
            }
            $result->due++;
            if ($notification->notification_type === 'reminder' && !$this->notificationStillEligible($notification)) {
                $result->cancelled += $this->notifications->cancelPendingReminders(
                    (int) $notification->registration_id,
                    0,
                    (int) $notification->reminder_definition_id
                );
                continue;
            }

            if ((bool) $sender((int) $notificationId)) {
                $result->sent++;
            } else {
                $result->failed++;
            }
        }
        $result->purged = $this->purgeIfDue();

        return $result;
    }

    public function calculateDueUtc($event, $definition)
    {
        if (empty($event->dates)) {
            return null;
        }

        try {
            if (!empty($event->times) && !empty($event->start_utc)) {
                $start = new DateTimeImmutable((string) $event->start_utc, new DateTimeZone('UTC'));
            } else {
                $time = !empty($event->times) ? substr((string) $event->times, 0, 8) : $this->allDayTime() . ':00';
                $timeZone = new DateTimeZone(JemHelper::getEventTimeZoneName($event, $event->venue_timezone ?? null));
                $start = (new DateTimeImmutable((string) $event->dates . ' ' . $time, $timeZone))
                    ->setTimezone(new DateTimeZone('UTC'));
            }
            $minutes = max(1, (int) $definition->minutes);

            return $start->sub(new DateInterval('PT' . $minutes . 'M'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function createReminder($registration, $definition, $scheduledAt, $force)
    {
        $language = $this->notifications->resolveRecipientLanguage(
            (int) $registration->user_id,
            (string) $registration->user_email
        );
        $definitionTitle = strpos((string) $definition->title, 'COM_JEM_') === 0
            ? JemNotificationTemplateService::translateWithEnglishFallback((string) $definition->title, $language->resolved)
            : (string) $definition->title;
        $values = array(
            'user_name' => (string) $registration->user_name,
            'event_title' => (string) $registration->event_title,
            'event_date' => class_exists('JemOutput') ? (string) JemOutput::formatdate($registration->dates) : (string) $registration->dates,
            'event_time' => class_exists('JemOutput') ? (string) JemOutput::formattime($registration->times) : (string) $registration->times,
            'venue' => (string) $registration->venue,
            'venue_configuration' => JemVenueSnapshot::summary($registration),
            'city' => (string) $registration->city,
            'places' => (int) $registration->places,
            'event_description' => trim(strip_tags((string) $registration->event_description)),
            'event_url' => rtrim(Uri::root(), '/') . '/index.php?option=com_jem&view=event&id=' . (int) $registration->event_id,
            'reservation_url' => rtrim(Uri::root(), '/') . '/index.php?option=com_jem&view=registration&id=' . (int) $registration->registration_id,
            'event_image_url' => $this->imageUrl($registration->datimage ?? '', 'event', $registration->image_path ?? ''),
            'venue_image_url' => $this->imageUrl($registration->locimage ?? '', 'venue', $registration->venue_image_path ?? ''),
            'site_name' => (string) Factory::getApplication()->get('sitename'),
            'reminder_interval' => $definitionTitle,
        );
        $message = JemNotificationTemplateService::renderByLanguageKeys(
            'PLG_JEM_MAILER_USER_REMINDER_SUBJECT',
            'PLG_JEM_MAILER_USER_REMINDER_BODY',
            $values,
            $language->resolved
        );
        if ($values['venue_configuration'] !== '') {
            $label = JemNotificationTemplateService::translateWithEnglishFallback(
                'COM_JEM_EVENT_VENUE_CONFIGURATION',
                $language->resolved
            );
            if (strpos((string) $message->body, $values['venue_configuration']) === false) {
                $message->body = rtrim((string) $message->body) . "\n\n" . $label . ': ' . $values['venue_configuration'];
            }
            if (strpos((string) $message->htmlbody, htmlspecialchars($values['venue_configuration'], ENT_QUOTES, 'UTF-8')) === false) {
                $message->htmlbody = rtrim((string) $message->htmlbody)
                    . '<p><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong> '
                    . htmlspecialchars($values['venue_configuration'], ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        $material = implode('|', array(
            'jem-reminder-v1',
            (int) $registration->registration_id,
            (int) $definition->id,
            $scheduledAt,
            strtolower((string) $registration->user_email),
            (string) $registration->modified,
        ));
        $existing = $this->notifications->getByIdempotencyKey(hash('sha256', $material));
        if ($force || ($existing && $existing->state === JemNotificationService::STATE_CANCELLED)) {
            $material .= '|' . Factory::getDate()->format('YmdHis') . '|' . bin2hex(random_bytes(6));
        }

        return $this->notifications->create(array(
            'registration_id' => (int) $registration->registration_id,
            'registration_reference' => (string) $registration->registration_reference,
            'revision' => (int) $registration->registration_revision,
            'event_id' => (int) $registration->event_id,
            'reminder_definition_id' => (int) $definition->id,
            'notification_type' => 'reminder',
            'recipient_type' => 'user',
            'recipient_user_id' => (int) $registration->user_id,
            'recipient_name' => (string) $registration->user_name,
            'recipient_email' => (string) $registration->user_email,
            'requested_language' => (string) $language->requested,
            'resolved_language' => (string) $language->resolved,
            'language_source' => (string) $language->source,
            'fallback_reason' => trim((string) $language->fallback_reason . ' ' . (string) $message->fallback_reason),
            'template_id' => (string) $message->template_id,
            'subject' => (string) $message->subject,
            'body' => (string) $message->body,
            'htmlbody' => (string) $message->htmlbody,
            'payload' => array(
                'reminder_definition_id' => (int) $definition->id,
                'reminder_code' => (string) $definition->code,
                'reminder_interval' => $definitionTitle,
                'values' => $values,
            ),
            'scheduled_at' => $scheduledAt,
            'idempotency_key' => hash('sha256', $material),
            'source' => 'scheduled_reminder',
        ));
    }

    private function loadRegistration($registrationId)
    {
        $query = $this->db->getQuery(true)
            ->select(array(
                'r.id AS registration_id', 'r.reference AS registration_reference', 'r.revision AS registration_revision',
                'r.status', 'r.waiting', 'r.places', 'r.modified', 'r.uid AS user_id',
                'u.name AS user_name', 'u.email AS user_email', 'u.block AS user_block', 'u.activation AS user_activation',
                'e.id AS event_id', 'e.title AS event_title', 'e.dates', 'e.times', 'e.start_utc',
                'e.timezone_mode', 'e.timezone', 'e.locid', 'e.event_status', 'e.introtext AS event_description', 'e.venue_snapshot',
                'e.datimage', 'e.image_path', 'v.venue', 'v.city', 'v.timezone AS venue_timezone', 'v.locimage', 'v.image_path AS venue_image_path',
            ))
            ->from($this->db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $this->db->quoteName('#__users', 'u') . ' ON u.id = r.uid')
            ->join('INNER', $this->db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
            ->join('LEFT', $this->db->quoteName('#__jem_venues', 'v') . ' ON v.id = e.locid')
            ->where('r.id = ' . (int) $registrationId);
        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    private function isEligible($registration)
    {
        return $registration
            && (int) $registration->status === 1
            && (int) $registration->waiting === 0
            && (int) $registration->user_block === 0
            && in_array(trim((string) $registration->user_activation), array('', '0'), true)
            && filter_var((string) $registration->user_email, FILTER_VALIDATE_EMAIL)
            && !empty($registration->dates)
            && (string) $registration->event_status !== 'cancelled';
    }

    private function notificationStillEligible($notification)
    {
        $registration = $this->loadRegistration((int) $notification->registration_id);
        if (!$this->isEnabled() || !$this->isEligible($registration)) {
            return false;
        }
        $selected = $this->getEventDefinitionIds((int) $notification->event_id);

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jem_reminders'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $notification->reminder_definition_id)
            ->where($this->db->quoteName('event_id') . ' = ' . (int) $notification->event_id)
            ->where($this->db->quoteName('published') . ' = 1');
        $this->db->setQuery($query);

        return in_array((int) $notification->reminder_definition_id, $selected, true)
            && (int) $this->db->loadResult() === 1;
    }

    private function getSelectedDefinitions($eventId)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_reminders'))
            ->where($this->db->quoteName('event_id') . ' = ' . (int) $eventId)
            ->where($this->db->quoteName('published') . ' = 1')
            ->order($this->db->quoteName('ordering') . ' ASC');
        $this->db->setQuery($query);

        return (array) $this->db->loadObjectList();
    }

    private function findActiveReminder($registrationId, $definitionId, $scheduledAt)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('registration_id') . ' = ' . (int) $registrationId)
            ->where($this->db->quoteName('reminder_definition_id') . ' = ' . (int) $definitionId)
            ->where($this->db->quoteName('scheduled_at') . ' = ' . $this->db->quote((string) $scheduledAt))
            ->where($this->db->quoteName('state') . ' IN (' . implode(',', array_map(array($this->db, 'quote'), array(
                JemNotificationService::STATE_SCHEDULED,
                JemNotificationService::STATE_QUEUED,
                JemNotificationService::STATE_PROCESSING,
                JemNotificationService::STATE_FAILED,
            ))) . ')')
            ->order($this->db->quoteName('id') . ' DESC');
        $this->db->setQuery($query, 0, 1);

        return $this->db->loadObject();
    }

    private function loadSelectionDefinitions(array $ids, $eventId)
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if (!$ids) {
            return array();
        }
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_reminders'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
            ->where('(' . $this->db->quoteName('event_id') . ' = 0 OR '
                . $this->db->quoteName('event_id') . ' = ' . (int) $eventId . ')')
            ->order($this->db->quoteName('event_id') . ' ASC');
        $this->db->setQuery($query);

        $definitions = array();
        foreach ((array) $this->db->loadObjectList() as $definition) {
            $definitions[$this->selectionKey($definition)] = $definition;
        }

        return array_values($definitions);
    }

    private function selectionKey($definition)
    {
        return 'code:' . (string) $definition->code;
    }

    private function getSeriesEventIds($eventId)
    {
        $query = $this->db->getQuery(true)
            ->select(array('id', 'series_id', 'recurrence_first_id'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $eventId);
        $this->db->setQuery($query);
        $event = $this->db->loadObject();
        if (!$event) {
            return array();
        }

        $query = $this->db->getQuery(true)->select($this->db->quoteName('id'))->from($this->db->quoteName('#__jem_events'));
        if ((int) $event->series_id > 0) {
            $query->where($this->db->quoteName('series_id') . ' = ' . (int) $event->series_id);
        } else {
            $rootId = (int) $event->recurrence_first_id ?: (int) $event->id;
            $query->where('(' . $this->db->quoteName('id') . ' = ' . $rootId
                . ' OR ' . $this->db->quoteName('recurrence_first_id') . ' = ' . $rootId . ')');
        }
        $this->db->setQuery($query);

        return array_map('intval', (array) $this->db->loadColumn());
    }

    private function isEnabled()
    {
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)) {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('reminders_enabled'));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() === 1;
    }

    private function allDayTime()
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('reminder_all_day_time'));
        $this->db->setQuery($query);
        $value = (string) ($this->db->loadResult() ?: '09:00');

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value) ? $value : '09:00';
    }

    /**
     * Build a public URL only for an existing image inside a JEM image folder.
     */
    private function imageUrl($image, $type, $folderPath = '')
    {
        $folders = array('event' => 'events', 'venue' => 'venues');
        $image = trim(str_replace('\\', '/', (string) $image));
        if ($image === '' || !isset($folders[$type])) {
            return '';
        }

        $folderPath = trim(str_replace('\\', '/', (string) $folderPath), '/');
        if ($folderPath !== '' && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $folderPath)) {
            return '';
        }

        $relative = strpos($image, '/') === false
            ? 'images/jem/' . $folders[$type] . '/' . ($folderPath !== '' ? $folderPath . '/' : '') . $image
            : ltrim($image, '/');
        if ($relative === '' || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $relative)) {
            return '';
        }
        if (!is_file(JPATH_SITE . '/' . $relative)) {
            return '';
        }

        $urlPath = implode('/', array_map('rawurlencode', explode('/', $relative)));

        return rtrim(Uri::root(), '/') . '/' . $urlPath;
    }

    private function purgeIfDue()
    {
        $days = $this->retentionDays();
        if ($days <= 0) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('notification_last_purge'));
        $this->db->setQuery($query);
        $last = (string) $this->db->loadResult();
        $now = Factory::getDate();
        if ($last !== '' && $now->toUnix() - Factory::getDate($last)->toUnix() < 86400) {
            return 0;
        }

        $purged = $this->notifications->purgeExpired($days);
        $sql = 'INSERT INTO ' . $this->db->quoteName('#__jem_config')
            . ' (' . $this->db->quoteName('keyname') . ', ' . $this->db->quoteName('value') . ') VALUES ('
            . $this->db->quote('notification_last_purge') . ', ' . $this->db->quote($now->toSql()) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $this->db->quoteName('value') . ' = VALUES(' . $this->db->quoteName('value') . ')';
        $this->db->setQuery($sql)->execute();

        return $purged;
    }

    /**
     * Resolve the day-based policy while still understanding the former
     * development-only years setting if the new key has not been installed.
     */
    private function retentionDays()
    {
        if (!class_exists('JemConfig')) {
            return 0;
        }

        $config = JemConfig::getInstance()->toRegistry();
        $days = $config->get('notification_retention_days', null);
        if ($days !== null && $days !== '') {
            return max(0, (int) $days);
        }

        return max(0, (int) $config->get('notification_retention_years', 0)) * 365;
    }
}
