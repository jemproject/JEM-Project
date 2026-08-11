<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Authoritative transactional writer for JEM registrations.
 */
final class JemRegistrationService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * Store one registration and its history atomically.
     *
     * @param   array|object  $data     Registration fields.
     * @param   array         $options  actorId, source, action, reasonCode,
     *                                  forced, expectedRevision and operationReference.
     *
     * @return object Result with before, after, transition and operationReference.
     */
    public function save($data, array $options = array())
    {
        $this->assertSchemaReady();
        $this->db->transactionStart();

        try {
            $row = (object) (array) $data;
            $eventId = (int) ($row->event ?? 0);

            if ($eventId < 1 && !empty($row->id)) {
                $query = $this->db->getQuery(true)
                    ->select($this->db->quoteName('event'))
                    ->from($this->db->quoteName('#__jem_register'))
                    ->where($this->db->quoteName('id') . ' = ' . (int) $row->id);
                $this->db->setQuery($query);
                $eventId = (int) $this->db->loadResult();
            }

            if ($eventId < 1) {
                throw new InvalidArgumentException('A registration requires a valid event.');
            }

            $this->lockEvent($eventId);
            $before = $this->loadForUpdate($row);
            $result = $this->saveLocked($before, $row, $options);
            $this->db->transactionCommit();

            return $result;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Store several registrations as one all-or-nothing operation.
     *
     * Event locks are acquired in numeric order so series bookings cannot
     * deadlock each other. Capacity is recalculated while those locks are held.
     *
     * @return array<object>
     */
    public function saveMany(array $rows, array $options = array())
    {
        if (!$rows) {
            return array();
        }

        $this->assertSchemaReady();
        $normalisedRows = array_map(static function ($row) {
            return (object) (array) $row;
        }, array_values($rows));
        $eventIds = array();

        foreach ($normalisedRows as $row) {
            $eventId = (int) ($row->event ?? 0);
            if ($eventId < 1) {
                throw new InvalidArgumentException('Every registration in a batch requires a valid event.');
            }
            $eventIds[] = $eventId;
        }

        $eventIds = array_values(array_unique($eventIds));
        sort($eventIds, SORT_NUMERIC);
        $this->db->transactionStart();

        try {
            foreach ($eventIds as $eventId) {
                $this->lockEvent($eventId);
            }

            $operationReference = (string) ($options['operationReference'] ?? '');
            if ($operationReference === '') {
                $operationReference = JemRegistrationIdentity::generateOperationReference();
            }
            $results = array();

            foreach ($normalisedRows as $row) {
                $before = $this->loadForUpdate($row);
                $rowOptions = $options;
                $rowOptions['operationReference'] = $operationReference;
                $results[] = $this->saveLocked($before, $row, $rowOptions);
            }

            $this->db->transactionCommit();

            return $results;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Persist a row while the caller owns the database transaction and locks.
     *
     * This method exists for capacity-sensitive batch and waiting-list code.
     */
    public function saveLocked($before, $data, array $options = array())
    {
        $operationReference = (string) ($options['operationReference'] ?? '');
        if ($operationReference === '') {
            $operationReference = JemRegistrationIdentity::generateOperationReference();
        }
        if (!JemRegistrationIdentity::isOperationReference($operationReference)) {
            throw new InvalidArgumentException('Invalid registration operation reference.');
        }

        if (is_object($before)
            && $this->operationAlreadyApplied($operationReference, (int) $before->id)) {
            return $this->result($before, $before, null, $operationReference, false);
        }

        if (!empty($options['requireNew']) && is_object($before)) {
            throw new RuntimeException('The user is already registered for this event.', 1062);
        }
        if (!empty($options['requireExisting']) && !is_object($before)) {
            throw new RuntimeException('The registration no longer exists.');
        }

        $after = $this->normaliseRow($before, $data);
        if (is_object($before) && (int) ($before->event ?? 0) !== (int) $after->event) {
            throw new RuntimeException('A registration cannot be moved to another event.');
        }
        $after = $this->applyCapacityPolicy($before, $after, $options);
        $oldStatus = is_object($before) ? JemRegistrationTransition::logicalStatus($before) : null;
        $newStatus = JemRegistrationTransition::logicalStatus($after);
        $requiresActiveUser = !is_object($before)
            || (int) ($before->uid ?? 0) !== (int) $after->uid
            || ($oldStatus === JemRegistrationTransition::NOT_ATTENDING
                && $newStatus !== JemRegistrationTransition::NOT_ATTENDING);

        if ($requiresActiveUser) {
            $this->assertActiveUser((int) $after->uid);
        }

        $expectedRevision = array_key_exists('expectedRevision', $options)
            ? (int) $options['expectedRevision']
            : null;
        if (is_object($before) && $expectedRevision !== null
            && $expectedRevision !== (int) ($before->revision ?? 1)) {
            throw new RuntimeException('The registration was modified by another operation.');
        }

        $changedFields = self::changedFields($before, $after);
        if (is_object($before) && !$changedFields) {
            return $this->result($before, $before, null, $operationReference, false);
        }

        $now = gmdate('Y-m-d H:i:s');
        if (is_object($before)) {
            $after->id = (int) $before->id;
            $after->reference = (string) $before->reference;
            $after->created = $before->created ?? null;
            $after->modified = $now;
            $after->revision = max(1, (int) ($before->revision ?? 1)) + 1;
            $this->db->updateObject('#__jem_register', $after, 'id');
        } else {
            $after->reference = $this->createUniqueReference();
            $after->created = $now;
            $after->modified = $now;
            $after->revision = 1;
            if (empty($after->uregdate)) {
                $after->uregdate = $now;
            }
            $this->db->insertObject('#__jem_register', $after);
            $after->id = (int) $this->db->insertid();
        }

        $action = (string) ($options['action'] ?? self::inferAction($before, $after));
        $history = $this->createHistoryRow(
            $before,
            $after,
            $operationReference,
            $action,
            $changedFields,
            $options,
            $now
        );
        $this->db->insertObject('#__jem_registration_history', $history);

        $transition = JemRegistrationTransition::create(
            $before,
            $after,
            (int) ($options['actorId'] ?? 0),
            (string) ($options['source'] ?? 'unknown')
        );
        $transition->forced = !empty($options['forced']);
        $transition->operationReference = $operationReference;
        $transition->registrationReference = $after->reference;
        $transition->revision = (int) $after->revision;
        $transition->changedFields = $changedFields;

        return $this->result($before, $after, $transition, $operationReference, true);
    }

    /**
     * Convert registrations to the compatible logical cancelled state.
     */
    public function cancelByIds(array $ids, $eventId, array $options = array())
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        $eventId = (int) $eventId;
        if (!$ids || $eventId < 1) {
            return array();
        }

        $this->assertSchemaReady();
        $this->db->transactionStart();

        try {
            $this->lockEvent($eventId);
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jem_register'))
                ->where($this->db->quoteName('event') . ' = ' . $eventId)
                ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
                ->order($this->db->quoteName('id') . ' ASC');
            $this->db->setQuery((string) $query . ' FOR UPDATE');
            $rows = (array) $this->db->loadObjectList();
            $operationReference = $options['operationReference']
                ?? JemRegistrationIdentity::generateOperationReference();
            $results = array();

            foreach ($rows as $before) {
                $after = clone $before;
                $after->status = JemRegistrationTransition::NOT_ATTENDING;
                $after->waiting = 0;
                $rowOptions = $options;
                $rowOptions['operationReference'] = $operationReference;
                if (empty($rowOptions['action'])) {
                    $rowOptions['action'] = 'cancelled';
                }
                $results[] = $this->saveLocked($before, $after, $rowOptions);
            }

            $this->db->transactionCommit();

            return $results;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Apply one logical status to several registrations in one transaction.
     */
    public function setLogicalStatusByIds(array $ids, $eventId, $status, array $options = array())
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        $eventId = (int) $eventId;
        $status = (int) $status;

        if (!$ids || $eventId < 1 || !JemRegistrationTransition::isValidStatus($status)) {
            return array();
        }

        $this->assertSchemaReady();
        $this->db->transactionStart();

        try {
            $this->lockEvent($eventId);
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jem_register'))
                ->where($this->db->quoteName('event') . ' = ' . $eventId)
                ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
                ->order($this->db->quoteName('id') . ' ASC');
            $this->db->setQuery((string) $query . ' FOR UPDATE');
            $rows = (array) $this->db->loadObjectList();
            $operationReference = $options['operationReference']
                ?? JemRegistrationIdentity::generateOperationReference();
            $results = array();

            foreach ($rows as $before) {
                $after = clone $before;
                JemRegistrationTransition::applyLogicalStatus($after, $status);
                $rowOptions = $options;
                $rowOptions['operationReference'] = $operationReference;
                $results[] = $this->saveLocked($before, $after, $rowOptions);
            }

            $this->db->transactionCommit();

            return $results;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Apply one status atomically to registrations that may span events.
     */
    public function setLogicalStatusAcrossEvents(array $ids, $status, array $options = array())
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        $status = (int) $status;
        if (!$ids || !JemRegistrationTransition::isValidStatus($status)) {
            return array();
        }

        $this->assertSchemaReady();
        $query = $this->db->getQuery(true)
            ->select(array('id', 'event'))
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        $this->db->setQuery($query);
        $identityRows = (array) $this->db->loadObjectList();

        if (count($identityRows) !== count($ids)) {
            throw new RuntimeException('One or more registrations no longer exist.');
        }

        $eventIds = array_values(array_unique(array_map(static function ($row) {
            return (int) $row->event;
        }, $identityRows)));
        sort($eventIds, SORT_NUMERIC);
        sort($ids, SORT_NUMERIC);
        $this->db->transactionStart();

        try {
            foreach ($eventIds as $eventId) {
                $this->lockEvent($eventId);
            }

            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jem_register'))
                ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
                ->order($this->db->quoteName('event') . ' ASC, ' . $this->db->quoteName('id') . ' ASC');
            $this->db->setQuery((string) $query . ' FOR UPDATE');
            $rows = (array) $this->db->loadObjectList();
            $operationReference = $options['operationReference']
                ?? JemRegistrationIdentity::generateOperationReference();
            $results = array();

            foreach ($rows as $before) {
                $after = clone $before;
                JemRegistrationTransition::applyLogicalStatus($after, $status);
                $rowOptions = $options;
                $rowOptions['operationReference'] = $operationReference;
                $results[] = $this->saveLocked($before, $after, $rowOptions);
            }

            $this->db->transactionCommit();

            return $results;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Record terminal history before authorised physical event cleanup.
     */
    public function purgeForEvent($eventId, array $options = array())
    {
        $eventId = (int) $eventId;
        if ($eventId < 1) {
            return 0;
        }

        $this->assertSchemaReady();
        $this->db->transactionStart();

        try {
            $count = $this->purgeForEventLocked($eventId, $options);
            $this->db->transactionCommit();

            return $count;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Purge event registrations while the caller owns the transaction.
     */
    public function purgeForEventLocked($eventId, array $options = array())
    {
        $eventId = (int) $eventId;
        $this->lockEvent($eventId, false);
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('event') . ' = ' . $eventId)
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery((string) $query . ' FOR UPDATE');
        $rows = (array) $this->db->loadObjectList();
        $operationReference = $options['operationReference']
            ?? JemRegistrationIdentity::generateOperationReference();
        $now = gmdate('Y-m-d H:i:s');

        foreach ($rows as $before) {
            $revision = max(1, (int) ($before->revision ?? 1)) + 1;
            $history = $this->createHistoryRow(
                $before,
                null,
                $operationReference,
                'purged',
                array('deleted'),
                $options,
                $now,
                $revision
            );
            $this->db->insertObject('#__jem_registration_history', $history);
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('event') . ' = ' . $eventId);
        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    public static function changedFields($before, $after)
    {
        $fields = array('event', 'uid', 'places', 'status', 'waiting', 'comment');
        if (!is_object($before)) {
            return $fields;
        }

        return array_values(array_filter($fields, static function ($field) use ($before, $after) {
            return (string) ($before->$field ?? '') !== (string) ($after->$field ?? '');
        }));
    }

    public static function inferAction($before, $after)
    {
        if (!is_object($before)) {
            return JemRegistrationTransition::logicalStatus($after) === JemRegistrationTransition::INVITED
                ? 'invited'
                : 'created';
        }

        $oldStatus = JemRegistrationTransition::logicalStatus($before);
        $newStatus = JemRegistrationTransition::logicalStatus($after);
        if ($newStatus === JemRegistrationTransition::NOT_ATTENDING
            && $oldStatus !== JemRegistrationTransition::NOT_ATTENDING) {
            return 'cancelled';
        }
        if ($oldStatus === JemRegistrationTransition::WAITING_LIST
            && $newStatus === JemRegistrationTransition::ATTENDING) {
            return 'promoted';
        }
        if ($oldStatus !== $newStatus) {
            return 'status_changed';
        }
        if ((int) ($before->places ?? 1) !== (int) ($after->places ?? 1)) {
            return 'places_changed';
        }

        return 'updated';
    }

    private function normaliseRow($before, $data)
    {
        $input = (object) (array) $data;
        $after = is_object($before) ? clone $before : new stdClass();

        foreach (array('event', 'uid', 'places', 'uregdate', 'uip', 'waiting', 'status', 'comment') as $field) {
            if (property_exists($input, $field)) {
                $after->$field = $input->$field;
            }
        }

        $after->event = (int) ($after->event ?? 0);
        $after->uid = (int) ($after->uid ?? 0);
        $after->places = max(0, (int) ($after->places ?? 1));
        $after->status = (int) ($after->status ?? JemRegistrationTransition::ATTENDING);
        $after->waiting = !empty($after->waiting) ? 1 : 0;
        $after->uregdate = (string) ($after->uregdate ?? '');
        $after->uip = (string) ($after->uip ?? '');
        $after->comment = (string) ($after->comment ?? '');

        if ($after->status === JemRegistrationTransition::WAITING_LIST) {
            $after->status = JemRegistrationTransition::ATTENDING;
            $after->waiting = 1;
        }
        if (!JemRegistrationTransition::isValidStatus(
            $after->status === JemRegistrationTransition::ATTENDING && $after->waiting
                ? JemRegistrationTransition::WAITING_LIST
                : $after->status
        )) {
            throw new InvalidArgumentException('Invalid registration status.');
        }

        return $after;
    }

    private function loadForUpdate($row)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_register'));

        if (!empty($row->id)) {
            $query->where($this->db->quoteName('id') . ' = ' . (int) $row->id);
        } elseif (!empty($row->event) && !empty($row->uid)) {
            $query->where($this->db->quoteName('event') . ' = ' . (int) $row->event)
                ->where($this->db->quoteName('uid') . ' = ' . (int) $row->uid);
        } else {
            return null;
        }

        $this->db->setQuery((string) $query . ' FOR UPDATE');

        return $this->db->loadObject() ?: null;
    }

    /**
     * Recalculate capacity under the event lock instead of trusting a value
     * calculated by a controller before the transaction began.
     */
    private function applyCapacityPolicy($before, $after, array $options)
    {
        if (empty($options['respectPlaces'])) {
            return $after;
        }

        $eventId = (int) ($after->event ?? 0);
        $query = $this->db->getQuery(true)
            ->select(array('maxplaces', 'waitinglist', 'reservedplaces'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);
        $this->db->setQuery($query);
        $event = $this->db->loadObject();

        if (!$event) {
            throw new RuntimeException('Registration event does not exist.');
        }

        $logicalStatus = JemRegistrationTransition::logicalStatus($after);
        if ($logicalStatus === JemRegistrationTransition::WAITING_LIST) {
            if (!(int) $event->waitinglist) {
                throw new RuntimeException('The event does not have a waiting list.');
            }

            return $after;
        }

        if ($logicalStatus !== JemRegistrationTransition::ATTENDING || (int) $event->maxplaces < 1) {
            return $after;
        }

        $query = $this->db->getQuery(true)
            ->select('COALESCE(SUM(GREATEST(' . $this->db->quoteName('places') . ', 1)), 0)')
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('event') . ' = ' . $eventId)
            ->where($this->db->quoteName('status') . ' = 1')
            ->where($this->db->quoteName('waiting') . ' = 0');

        if (is_object($before) && !empty($before->id)) {
            $query->where($this->db->quoteName('id') . ' <> ' . (int) $before->id);
        }

        $this->db->setQuery($query);
        $usedPlaces = max(0, (int) $event->reservedplaces) + max(0, (int) $this->db->loadResult());
        $requestedPlaces = max(1, (int) ($after->places ?? 1));

        if ($usedPlaces + $requestedPlaces <= (int) $event->maxplaces) {
            return $after;
        }

        if (!empty($options['allowWaiting']) && (int) $event->waitinglist) {
            JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::WAITING_LIST);

            return $after;
        }

        throw new RuntimeException('Event capacity would be exceeded.');
    }

    private function lockEvent($eventId, $required = true)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $eventId);
        $this->db->setQuery((string) $query . ' FOR UPDATE');
        $found = (int) $this->db->loadResult();

        if ($required && $found < 1) {
            throw new RuntimeException('Registration event does not exist.');
        }
    }

    private function assertActiveUser($userId)
    {
        if ($userId < 1) {
            throw new RuntimeException('A registration requires a Joomla user.');
        }

        $query = $this->db->getQuery(true)
            ->select(array('id', 'block', 'activation'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $userId);
        $this->db->setQuery($query);
        $user = $this->db->loadObject();

        if (!$user || !empty($user->block) || self::activationRequiresVerification($user->activation)) {
            throw new RuntimeException('The Joomla booking-holder account is not active and verified.');
        }
    }

    /**
     * Joomla normally clears the activation token after verification, while
     * legacy/migrated active accounts may store the equivalent string "0".
     */
    private static function activationRequiresVerification($activation)
    {
        $activation = trim((string) $activation);

        return $activation !== '' && $activation !== '0';
    }

    private function assertSchemaReady()
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('registration_schema_ready'));
        $this->db->setQuery($query);

        if ((string) $this->db->loadResult() !== '1') {
            throw new RuntimeException('JEM registration schema migration is not complete.');
        }
    }

    private function createUniqueReference()
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = JemRegistrationIdentity::generateRegistrationReference();
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__jem_register'))
                ->where($this->db->quoteName('reference') . ' = ' . $this->db->quote($reference));
            $this->db->setQuery($query);
            if ((int) $this->db->loadResult() === 0) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique registration reference.');
    }

    private function operationAlreadyApplied($operationReference, $registrationId)
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jem_registration_history'))
            ->where($this->db->quoteName('operation_reference') . ' = ' . $this->db->quote($operationReference))
            ->where($this->db->quoteName('registration_id') . ' = ' . (int) $registrationId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    private function createHistoryRow($before, $after, $operationReference, $action, array $changedFields, array $options, $now, $revision = null)
    {
        $current = is_object($after) ? $after : $before;
        $eventTitle = $this->loadEventTitle((int) ($current->event ?? 0));

        return (object) array(
            'operation_reference'    => $operationReference,
            'registration_id'        => (int) ($current->id ?? 0),
            'registration_reference' => (string) ($current->reference ?? ''),
            'revision'               => $revision ?? (int) ($current->revision ?? 1),
            'event_id'               => (int) ($current->event ?? 0),
            'event_title'            => $eventTitle,
            'action'                 => preg_replace('/[^a-z0-9_.-]/i', '', $action),
            'old_status'             => is_object($before) ? JemRegistrationTransition::logicalStatus($before) : null,
            'new_status'             => is_object($after) ? JemRegistrationTransition::logicalStatus($after) : null,
            'old_places'             => is_object($before) ? max(0, (int) ($before->places ?? 1)) : null,
            'new_places'             => is_object($after) ? max(0, (int) ($after->places ?? 1)) : null,
            'old_user_id'            => is_object($before) ? (int) ($before->uid ?? 0) : null,
            'new_user_id'            => is_object($after) ? (int) ($after->uid ?? 0) : null,
            'actor_user_id'          => (int) ($options['actorId'] ?? 0),
            'source'                 => preg_replace('/[^a-z0-9_.-]/i', '', (string) ($options['source'] ?? 'unknown')),
            'reason_code'            => isset($options['reasonCode'])
                ? preg_replace('/[^a-z0-9_.-]/i', '', (string) $options['reasonCode'])
                : null,
            'forced'                 => !empty($options['forced']) ? 1 : 0,
            'changed_fields'         => json_encode(array_values($changedFields), JSON_UNESCAPED_SLASHES),
            'occurred'               => $now,
        );
    }

    private function loadEventTitle($eventId)
    {
        if ($eventId < 1) {
            return '';
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('title'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $eventId);
        $this->db->setQuery($query);

        return (string) $this->db->loadResult();
    }

    private function result($before, $after, $transition, $operationReference, $changed)
    {
        return (object) array(
            'before'             => $before,
            'after'              => $after,
            'transition'         => $transition,
            'operationReference' => $operationReference,
            'changed'            => (bool) $changed,
        );
    }
}
