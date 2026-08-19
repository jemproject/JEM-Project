<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

/**
 * Durable reservation-notification journal and delivery-attempt service.
 *
 * A notification row is an immutable rendered snapshot. Delivery attempts are
 * append-only. Retrying uses the same snapshot; resending creates a linked row.
 */
final class JemNotificationService
{
    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_QUEUED = 'queued';
    public const STATE_PROCESSING = 'processing';
    public const STATE_SENT = 'sent';
    public const STATE_FAILED = 'failed';
    public const STATE_CANCELLED = 'cancelled';

    /** @var object */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * Resolve the requested and effective language for one recipient.
     *
     * The Joomla user profile is authoritative. The site default and en-GB are
     * deterministic fallbacks when that language has no JEM Mailer pack.
     */
    public function resolveRecipientLanguage($userId = 0, $email = '')
    {
        $user = $this->findUser((int) $userId, (string) $email);
        $requested = '';
        $source = 'site_default';

        if ($user) {
            $params = new Registry((string) ($user->params ?? ''));
            $requested = trim((string) $params->get('language', ''));
            if ($requested === '') {
                $requested = trim((string) $params->get('site_language', ''));
            }
            if ($requested !== '') {
                $source = 'user_profile';
            }
        }

        $siteDefault = trim((string) ComponentHelper::getParams('com_languages')->get('site', ''));
        if ($siteDefault === '') {
            $siteDefault = Factory::getApplication()->getLanguage()->getTag();
        }

        $candidates = array();
        if ($requested !== '') {
            $candidates[] = array($requested, $source, '');
        }
        $candidates[] = array($siteDefault, 'site_default', $requested === '' ? '' : 'requested_language_unavailable');
        $candidates[] = array('en-GB', 'english_fallback', 'jem_site_language_unavailable');

        foreach ($candidates as $candidate) {
            if (JemNotificationTemplateService::hasMailerLanguage($candidate[0])) {
                return (object) array(
                    'user' => $user,
                    'requested' => $requested,
                    'resolved' => $candidate[0],
                    'source' => $candidate[1],
                    'fallback_reason' => $candidate[2],
                );
            }
        }

        return (object) array(
            'user' => $user,
            'requested' => $requested,
            'resolved' => 'en-GB',
            'source' => 'english_fallback',
            'fallback_reason' => 'jem_language_pack_unavailable',
        );
    }

    /**
     * Insert an immutable notification intent, or return its existing row when
     * the same business transition already created it.
     */
    public function create(array $data)
    {
        $email = filter_var(trim((string) ($data['recipient_email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new InvalidArgumentException('A valid notification recipient is required.');
        }

        $now = Factory::getDate()->toSql();
        $payload = $data['payload'] ?? array();
        $payloadJson = self::encodeJson($payload);
        $attachmentsJson = self::encodeJson($data['attachments'] ?? array());
        $content = array(
            (string) ($data['subject'] ?? ''),
            (string) ($data['body'] ?? ''),
            (string) ($data['htmlbody'] ?? ''),
            $payloadJson,
            $attachmentsJson,
        );
        $contentHash = hash('sha256', implode("\x1f", $content));
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = hash('sha256', implode('|', array(
                (int) ($data['registration_id'] ?? 0),
                (int) ($data['revision'] ?? 0),
                (string) ($data['template_id'] ?? ''),
                strtolower($email),
                (string) ($data['recipient_type'] ?? 'user'),
                $contentHash,
            )));
        } elseif (!preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)) {
            $idempotencyKey = hash('sha256', $idempotencyKey);
        }

        $existing = $this->getByIdempotencyKey($idempotencyKey);
        if ($existing) {
            $existing->_created = false;
            return $existing;
        }

        $row = (object) array(
            'notification_uuid' => self::uuidV4(),
            'registration_id' => (int) ($data['registration_id'] ?? 0),
            'registration_reference' => substr((string) ($data['registration_reference'] ?? ''), 0, 28),
            'registration_revision' => (int) ($data['revision'] ?? 0),
            'event_id' => (int) ($data['event_id'] ?? 0),
            'reminder_definition_id' => !empty($data['reminder_definition_id']) ? (int) $data['reminder_definition_id'] : null,
            'ticket_id' => !empty($data['ticket_id']) ? (int) $data['ticket_id'] : null,
            'notification_type' => substr((string) ($data['notification_type'] ?? 'registration'), 0, 64),
            'recipient_type' => substr((string) ($data['recipient_type'] ?? 'user'), 0, 32),
            'recipient_user_id' => (int) ($data['recipient_user_id'] ?? 0),
            'recipient_name' => substr((string) ($data['recipient_name'] ?? ''), 0, 255),
            'recipient_email' => $email,
            'requested_language' => substr((string) ($data['requested_language'] ?? ''), 0, 12),
            'resolved_language' => substr((string) ($data['resolved_language'] ?? 'en-GB'), 0, 12),
            'language_source' => substr((string) ($data['language_source'] ?? 'fallback'), 0, 32),
            'fallback_reason' => substr((string) ($data['fallback_reason'] ?? ''), 0, 255),
            'template_id' => substr((string) ($data['template_id'] ?? ''), 0, 255),
            'subject' => (string) ($data['subject'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'htmlbody' => (string) ($data['htmlbody'] ?? ''),
            'payload_json' => $payloadJson,
            'attachments_json' => $attachmentsJson,
            'content_hash' => $contentHash,
            'state' => !empty($data['scheduled_at']) ? self::STATE_SCHEDULED : self::STATE_QUEUED,
            'scheduled_at' => !empty($data['scheduled_at']) ? (string) $data['scheduled_at'] : null,
            'queued_at' => !empty($data['scheduled_at']) ? null : $now,
            'processing_started_at' => null,
            'sent_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'next_attempt_at' => null,
            'attempt_count' => 0,
            'max_attempts' => max(1, (int) ($data['max_attempts'] ?? $this->configInt('notification_max_attempts', 4))),
            'idempotency_key' => $idempotencyKey,
            'resend_of' => !empty($data['resend_of']) ? (int) $data['resend_of'] : null,
            'source' => substr((string) ($data['source'] ?? 'automatic'), 0, 80),
            'created_by' => (int) ($data['created_by'] ?? 0),
            'created' => $now,
            'modified' => $now,
        );

        try {
            $this->db->insertObject('#__jem_notifications', $row, 'id');
        } catch (Throwable $e) {
            $existing = $this->getByIdempotencyKey($idempotencyKey);
            if (!$existing) {
                throw $e;
            }
            $existing->_created = false;
            return $existing;
        }

        $row->_created = true;
        JemHelper::addLogEntry(
            'Notification #' . (int) $row->id . ' ' . $row->state . ' for registration #' . (int) $row->registration_id,
            __METHOD__,
            Log::INFO
        );

        return $row;
    }

    public function getById($id)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $id);
        $this->db->setQuery($query);
        return $this->db->loadObject();
    }

    public function getByIdempotencyKey($key)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('idempotency_key') . ' = ' . $this->db->quote((string) $key));
        $this->db->setQuery($query);
        return $this->db->loadObject();
    }

    /**
     * Atomically claim a queued/failed snapshot and append its attempt row.
     */
    public function beginAttempt($notificationId, $source = 'automatic', $actorId = 0)
    {
        $notification = $this->getById((int) $notificationId);
        if (!$notification
            || !in_array($notification->state, array(self::STATE_QUEUED, self::STATE_FAILED), true)
            || (int) $notification->attempt_count >= (int) $notification->max_attempts) {
            return false;
        }

        $attemptNumber = (int) $notification->attempt_count + 1;
        $now = Factory::getDate()->toSql();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_PROCESSING))
            ->set($this->db->quoteName('processing_started_at') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('attempt_count') . ' = ' . $attemptNumber)
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('id') . ' = ' . (int) $notificationId)
            ->where($this->db->quoteName('state') . ' IN (' . $this->db->quote(self::STATE_QUEUED) . ',' . $this->db->quote(self::STATE_FAILED) . ')')
            ->where($this->db->quoteName('attempt_count') . ' = ' . (int) $notification->attempt_count);
        $this->db->setQuery($query);
        $this->db->execute();
        if ((int) $this->db->getAffectedRows() !== 1) {
            return false;
        }

        $attempt = (object) array(
            'notification_id' => (int) $notificationId,
            'attempt_number' => $attemptNumber,
            'transport' => 'mail',
            'source' => substr((string) $source, 0, 32),
            'requested_by_user_id' => (int) $actorId,
            'started_at' => $now,
            'finished_at' => null,
            'result' => 'processing',
            'transport_message_id' => '',
            'error_code' => '',
            'error_message' => '',
        );
        $this->db->insertObject('#__jem_notifications_attempts', $attempt, 'id');

        return $attempt;
    }

    public function finishAttempt($notificationId, $attemptId, $success, $errorMessage = '', $messageId = '')
    {
        // Factory caches the literal "now" date for the lifetime of a long
        // request. Anchor retries to the actual completion time instead.
        $completedAt = Factory::getDate(time());
        $now = $completedAt->toSql();
        $result = $success ? 'sent' : 'failed';
        $error = substr(preg_replace('/\s+/', ' ', trim((string) $errorMessage)), 0, 1000);
        $notification = $this->getById((int) $notificationId);
        $nextAttempt = null;

        if (!$success && $notification && (int) $notification->attempt_count < (int) $notification->max_attempts) {
            $delays = $this->retryDelays();
            $delayIndex = max(0, (int) $notification->attempt_count - 1);
            $delayMinutes = (int) ($delays[$delayIndex] ?? end($delays));
            $nextAttempt = Factory::getDate($completedAt->toUnix() + ($delayMinutes * 60))->toSql();
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications_attempts'))
            ->set($this->db->quoteName('finished_at') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('result') . ' = ' . $this->db->quote($result))
            ->set($this->db->quoteName('transport_message_id') . ' = ' . $this->db->quote(substr((string) $messageId, 0, 255)))
            ->set($this->db->quoteName('error_message') . ' = ' . $this->db->quote($error))
            ->where($this->db->quoteName('id') . ' = ' . (int) $attemptId)
            ->where($this->db->quoteName('notification_id') . ' = ' . (int) $notificationId);
        $this->db->setQuery($query);
        $this->db->execute();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote($success ? self::STATE_SENT : self::STATE_FAILED))
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('processing_started_at') . ' = NULL')
            ->set($this->db->quoteName('next_attempt_at') . ' = ' . ($nextAttempt === null ? 'NULL' : $this->db->quote($nextAttempt)))
            ->set($this->db->quoteName($success ? 'sent_at' : 'failed_at') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('id') . ' = ' . (int) $notificationId)
            ->where($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_PROCESSING));
        $this->db->setQuery($query);
        $this->db->execute();

        JemHelper::addLogEntry(
            'Notification #' . (int) $notificationId . ' delivery ' . $result . ($error === '' ? '' : ': ' . $error),
            __METHOD__,
            $success ? Log::INFO : Log::WARNING
        );

        return (bool) $success;
    }

    /**
     * Promote due scheduled snapshots and return all due queue/retry ids.
     */
    public function getDueNotificationIds($limit = 100)
    {
        $limit = max(1, min(500, (int) $limit));
        $now = Factory::getDate()->toSql();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_QUEUED))
            ->set($this->db->quoteName('queued_at') . ' = COALESCE(' . $this->db->quoteName('queued_at') . ', ' . $this->db->quote($now) . ')')
            ->set($this->db->quoteName('next_attempt_at') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_SCHEDULED))
            ->where($this->db->quoteName('scheduled_at') . ' <= ' . $this->db->quote($now));
        $this->db->setQuery($query);
        $this->db->execute();

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('state') . ' IN (' . $this->db->quote(self::STATE_QUEUED) . ',' . $this->db->quote(self::STATE_FAILED) . ')')
            ->where($this->db->quoteName('attempt_count') . ' < ' . $this->db->quoteName('max_attempts'))
            ->where('(' . $this->db->quoteName('next_attempt_at') . ' IS NULL OR ' . $this->db->quoteName('next_attempt_at') . ' <= ' . $this->db->quote($now) . ')')
            ->order(array($this->db->quoteName('scheduled_at') . ' ASC', $this->db->quoteName('id') . ' ASC'));
        $this->db->setQuery($query, 0, $limit);

        return array_map('intval', (array) $this->db->loadColumn());
    }

    /**
     * Recover task processes abandoned without a delivery result.
     */
    public function recoverStaleProcessing($minutes = 30)
    {
        $threshold = Factory::getDate(Factory::getDate()->toUnix() - (max(5, (int) $minutes) * 60))->toSql();
        $now = Factory::getDate()->toSql();
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_PROCESSING))
            ->where($this->db->quoteName('processing_started_at') . ' < ' . $this->db->quote($threshold));
        $this->db->setQuery($query);
        $ids = array_map('intval', (array) $this->db->loadColumn());
        if (!$ids) {
            return 0;
        }
        $idList = implode(',', $ids);
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications_attempts'))
            ->set($this->db->quoteName('finished_at') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('result') . ' = ' . $this->db->quote('failed'))
            ->set($this->db->quoteName('error_code') . ' = ' . $this->db->quote('stale_processing'))
            ->set($this->db->quoteName('error_message') . ' = ' . $this->db->quote('Recovered after an interrupted scheduler process.'))
            ->where($this->db->quoteName('notification_id') . ' IN (' . $idList . ')')
            ->where($this->db->quoteName('result') . ' = ' . $this->db->quote('processing'));
        $this->db->setQuery($query)->execute();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_FAILED))
            ->set($this->db->quoteName('processing_started_at') . ' = NULL')
            ->set($this->db->quoteName('failed_at') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('next_attempt_at') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('id') . ' IN (' . $idList . ')')
            ->where($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_PROCESSING));
        $this->db->setQuery($query);
        $this->db->execute();

        return count($ids);
    }

    /**
     * Cancel unsent reminder snapshots while preserving their audit rows.
     */
    public function cancelPendingReminders($registrationId = 0, $eventId = 0, $definitionId = 0, array $exceptIds = array())
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__jem_notifications'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_CANCELLED))
            ->set($this->db->quoteName('cancelled_at') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
            ->set($this->db->quoteName('next_attempt_at') . ' = NULL')
            ->where($this->db->quoteName('notification_type') . ' = ' . $this->db->quote('reminder'))
            ->where($this->db->quoteName('state') . ' IN (' . $this->db->quote(self::STATE_SCHEDULED) . ',' . $this->db->quote(self::STATE_QUEUED) . ',' . $this->db->quote(self::STATE_FAILED) . ')');
        if ((int) $registrationId > 0) {
            $query->where($this->db->quoteName('registration_id') . ' = ' . (int) $registrationId);
        }
        if ((int) $eventId > 0) {
            $query->where($this->db->quoteName('event_id') . ' = ' . (int) $eventId);
        }
        if ((int) $definitionId > 0) {
            $query->where($this->db->quoteName('reminder_definition_id') . ' = ' . (int) $definitionId);
        }
        $exceptIds = array_values(array_filter(array_unique(array_map('intval', $exceptIds))));
        if ($exceptIds) {
            $query->where($this->db->quoteName('id') . ' NOT IN (' . implode(',', $exceptIds) . ')');
        }
        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    /**
     * Remove terminal notification history after the configured retention.
     */
    public function purgeExpired($days = 0)
    {
        $days = (int) $days;
        if ($days <= 0) {
            return 0;
        }

        $threshold = Factory::getDate(Factory::getDate()->toUnix() - ($days * 86400))->toSql();
        $terminal = implode(',', array_map(array($this->db, 'quote'), array(self::STATE_SENT, self::STATE_CANCELLED)));
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where('(' . $this->db->quoteName('state') . ' IN (' . $terminal . ') OR ('
                . $this->db->quoteName('state') . ' = ' . $this->db->quote(self::STATE_FAILED)
                . ' AND (' . $this->db->quoteName('next_attempt_at') . ' IS NULL OR '
                . $this->db->quoteName('attempt_count') . ' >= ' . $this->db->quoteName('max_attempts') . ')))')
            ->where($this->db->quoteName('created') . ' < ' . $this->db->quote($threshold));
        $this->db->setQuery($query);
        $ids = array_map('intval', (array) $this->db->loadColumn());
        if (!$ids) {
            return 0;
        }

        $idList = implode(',', $ids);
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__jem_notifications_attempts'))
            ->where($this->db->quoteName('notification_id') . ' IN (' . $idList . ')');
        $this->db->setQuery($query)->execute();
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('id') . ' IN (' . $idList . ')');
        $this->db->setQuery($query)->execute();

        return count($ids);
    }

    /**
     * Deliver the exact stored snapshot as a controlled retry.
     */
    public function retryStored($notificationId, callable $sender, $actorId = 0, $source = 'admin_retry')
    {
        $notification = $this->getById((int) $notificationId);
        if (!$notification || !in_array($notification->state, array(self::STATE_QUEUED, self::STATE_FAILED), true)) {
            return false;
        }

        $attempt = $this->beginAttempt($notification->id, $source, $actorId);
        if (!$attempt) {
            return false;
        }

        $error = '';
        try {
            $success = (bool) $sender($notification, $error);
        } catch (Throwable $e) {
            $success = false;
            $error = $e->getMessage();
        }

        return $this->finishAttempt($notification->id, $attempt->id, $success, $error);
    }

    /**
     * Enforce the self-service resend window and cooldown.
     */
    public function canUserResend($registrationId, $userId)
    {
        $limit = max(1, $this->configInt('notification_user_resend_limit', 2));
        $hours = max(1, $this->configInt('notification_user_resend_window_hours', 24));
        $cooldown = max(1, $this->configInt('notification_user_resend_cooldown_minutes', 10));
        $now = Factory::getDate();
        $windowStart = Factory::getDate($now->toUnix() - ($hours * 3600))->toSql();
        $cooldownStart = Factory::getDate($now->toUnix() - ($cooldown * 60))->toSql();

        $query = $this->db->getQuery(true)
            ->select(array(
                'COUNT(*) AS total',
                'MAX(' . $this->db->quoteName('created') . ') AS latest',
            ))
            ->from($this->db->quoteName('#__jem_notifications'))
            ->where($this->db->quoteName('registration_id') . ' = ' . (int) $registrationId)
            ->where($this->db->quoteName('recipient_user_id') . ' = ' . (int) $userId)
            ->where($this->db->quoteName('source') . ' = ' . $this->db->quote('user_resend'))
            ->where($this->db->quoteName('created') . ' >= ' . $this->db->quote($windowStart));
        $this->db->setQuery($query);
        $usage = $this->db->loadObject();

        if ((int) ($usage->total ?? 0) >= $limit) {
            return (object) array('allowed' => false, 'reason' => 'limit', 'limit' => $limit, 'cooldown' => $cooldown);
        }
        if (!empty($usage->latest) && $usage->latest > $cooldownStart) {
            return (object) array('allowed' => false, 'reason' => 'cooldown', 'limit' => $limit, 'cooldown' => $cooldown);
        }

        return (object) array('allowed' => true, 'reason' => '', 'limit' => $limit, 'cooldown' => $cooldown);
    }

    /**
     * Return an active booking owned by an unblocked Joomla user.
     */
    public function getOwnedRegistration($registrationId, $userId)
    {
        $query = $this->db->getQuery(true)
            ->select(array('r.*', 'u.name AS user_name', 'u.email AS user_email', 'u.block AS user_block', 'e.title AS event_title'))
            ->from($this->db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $this->db->quoteName('#__users', 'u') . ' ON u.id = r.uid')
            ->join('INNER', $this->db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
            ->where('r.id = ' . (int) $registrationId)
            ->where('r.uid = ' . (int) $userId)
            ->where('u.block = 0');
        $this->db->setQuery($query);
        return $this->db->loadObject();
    }

    public function getRegistrationNotifications($registrationId, $limit = 100)
    {
        $query = $this->db->getQuery(true)
            ->select('n.*')
            ->select('(SELECT COUNT(*) FROM ' . $this->db->quoteName('#__jem_notifications_attempts', 'na') . ' WHERE na.notification_id = n.id) AS attempts_total')
            ->select('(SELECT na2.error_message FROM ' . $this->db->quoteName('#__jem_notifications_attempts', 'na2') . ' WHERE na2.notification_id = n.id ORDER BY na2.attempt_number DESC LIMIT 1) AS last_error')
            ->from($this->db->quoteName('#__jem_notifications', 'n'))
            ->where('n.registration_id = ' . (int) $registrationId)
            ->order('n.created DESC, n.id DESC');
        $this->db->setQuery($query, 0, max(1, (int) $limit));
        return (array) $this->db->loadObjectList();
    }

    private function findUser($userId, $email)
    {
        if ($userId < 1 && trim($email) === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select(array('id', 'name', 'username', 'email', 'params', 'block'))
            ->from($this->db->quoteName('#__users'));
        if ($userId > 0) {
            $query->where($this->db->quoteName('id') . ' = ' . $userId);
        } else {
            $query->where('LOWER(' . $this->db->quoteName('email') . ') = ' . $this->db->quote(strtolower(trim($email))));
        }
        $this->db->setQuery($query, 0, 1);
        return $this->db->loadObject();
    }

    private function configInt($key, $default)
    {
        if (!class_exists('JemConfig')) {
            return (int) $default;
        }
        return (int) JemConfig::getInstance()->toRegistry()->get($key, $default);
    }

    private function retryDelays()
    {
        $raw = '10,30,120';
        if (class_exists('JemConfig')) {
            $raw = (string) JemConfig::getInstance()->toRegistry()->get('notification_retry_delays_minutes', $raw);
        }
        $delays = array_values(array_filter(array_map('intval', explode(',', $raw)), static function ($value) {
            return $value > 0;
        }));

        return $delays ?: array(10, 30, 120);
    }

    private static function encodeJson($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json === false ? '{}' : $json;
    }

    private static function uuidV4()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
