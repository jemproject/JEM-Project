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
        $capacity = $this->prepareCapacityAllocations($before, $after, $options);
        $oldStatus = is_object($before) ? JemRegistrationTransition::logicalStatus($before) : null;
        $newStatus = JemRegistrationTransition::logicalStatus($after);
        $hasCommercialLines = array_key_exists('commercialLines', $options);
        if (is_object($before) && self::isPricedRegistration($before) && !$hasCommercialLines) {
            if ((int) ($before->places ?? 0) !== (int) ($after->places ?? 0)) {
                throw new RuntimeException('Priced registration quantities require a commercial revision.');
            }
            if ($oldStatus === JemRegistrationTransition::NOT_ATTENDING
                && $newStatus !== JemRegistrationTransition::NOT_ATTENDING) {
                throw new RuntimeException('A cancelled priced registration must be reactivated from a current quote.');
            }
        }
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
        if (!empty($capacity['changed'])) {
            $changedFields[] = 'capacity_allocations';
        }
        if (!empty($options['forceRevision']) && !in_array('commercial_items', $changedFields, true)) {
            $changedFields[] = 'commercial_items';
        }
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

        $this->persistCommercialRevision($before, $after, $options, $now);
        $this->persistCapacityAllocations($after, (array) ($capacity['rows'] ?? array()), $now);

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
        $this->db->insertObject('#__jem_register_history', $history);

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
            $this->db->insertObject('#__jem_register_history', $history);
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
        $fields = array(
            'event', 'uid', 'places', 'status', 'waiting', 'comment',
            'pricing_mode', 'currency', 'subtotal_net', 'discount_total', 'tax_total',
            'management_fee_net', 'management_fee_tax', 'management_fee_gross',
            'grand_total', 'payment_state', 'price_locked_at', 'external_payment_reference',
        );
        if (!is_object($before)) {
            return array_values(array_filter($fields, static function ($field) use ($after) {
                return in_array($field, array('event', 'uid', 'places', 'status', 'waiting', 'comment'), true)
                    || property_exists($after, $field);
            }));
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

        foreach (array(
            'event', 'uid', 'places', 'uregdate', 'uip', 'waiting', 'status', 'comment',
            'pricing_mode', 'currency', 'subtotal_net', 'discount_total', 'tax_total',
            'management_fee_net', 'management_fee_tax', 'management_fee_gross',
            'grand_total', 'payment_state', 'price_locked_at', 'external_payment_reference',
        ) as $field) {
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

    /**
     * Return the immutable item snapshot for one registration revision.
     */
    public function commercialItems($registerId, $revision, $lock = false)
    {
        $registerId = (int) $registerId;
        $revision = (int) $revision;
        if ($registerId < 1 || $revision < 1) {
            return array();
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_register_items'))
            ->where($this->db->quoteName('register_id') . ' = ' . $registerId)
            ->where($this->db->quoteName('registration_revision') . ' = ' . $revision)
            ->order($this->db->quoteName('line_number') . ' ASC');
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        return (array) $this->db->loadObjectList();
    }

    private function persistCommercialRevision($before, $after, array $options, $now)
    {
        if (!self::isPricedRegistration($after)) {
            return;
        }

        if (array_key_exists('commercialLines', $options)) {
            $lines = (array) $options['commercialLines'];
        } elseif (is_object($before) && self::isPricedRegistration($before)) {
            $lines = $this->commercialItems(
                (int) $before->id,
                max(1, (int) ($before->revision ?? 1)),
                true
            );
        } else {
            throw new RuntimeException('A new priced registration requires admission lines.');
        }

        if (!$lines) {
            throw new RuntimeException('A priced registration revision cannot have an empty commercial snapshot.');
        }

        $admissionQuantity = 0;
        foreach (array_values($lines) as $offset => $source) {
            $source = (object) (array) $source;
            $kind = preg_replace('/[^a-z0-9_.-]/i', '', (string) ($source->line_kind ?? ''));
            $quantity = (int) ($source->quantity ?? 0);
            if ($kind === '' || $quantity < 1) {
                throw new RuntimeException('Commercial lines require a valid kind and positive quantity.');
            }
            if ($kind === 'admission') {
                $admissionQuantity += $quantity;
            }

            $item = (object) array(
                'register_id' => (int) $after->id,
                'registration_revision' => (int) $after->revision,
                'line_number' => $offset + 1,
                'line_kind' => $kind,
                'event_price_id' => !empty($source->event_price_id) ? (int) $source->event_price_id : null,
                'capacity_pool_id' => !empty($source->capacity_pool_id) ? (int) $source->capacity_pool_id : null,
                'item_code' => (string) ($source->item_code ?? ''),
                'item_name' => (string) ($source->item_name ?? ''),
                'item_description' => isset($source->item_description) ? (string) $source->item_description : null,
                'quantity' => $quantity,
                'currency' => (string) ($source->currency ?? $after->currency ?? ''),
                'price_includes_tax' => !empty($source->price_includes_tax) ? 1 : 0,
                'unit_net' => (string) ($source->unit_net ?? '0.00'),
                'unit_tax' => (string) ($source->unit_tax ?? '0.00'),
                'unit_gross' => (string) ($source->unit_gross ?? '0.00'),
                'line_net' => (string) ($source->line_net ?? '0.00'),
                'line_tax' => (string) ($source->line_tax ?? '0.00'),
                'line_gross' => (string) ($source->line_gross ?? '0.00'),
                'tax_code' => (string) ($source->tax_code ?? ''),
                'tax_name' => (string) ($source->tax_name ?? ''),
                'tax_type' => (string) ($source->tax_type ?? ''),
                'tax_rate' => (string) ($source->tax_rate ?? '0.00'),
                'calculation_mode' => (string) ($source->calculation_mode ?? ''),
                'calculation_value' => isset($source->calculation_value) ? (string) $source->calculation_value : null,
                'calculation_basis' => (string) ($source->calculation_basis ?? ''),
                'condition_snapshot' => isset($source->condition_snapshot)
                    ? (string) $source->condition_snapshot
                    : null,
                'created' => $now,
            );
            $this->db->insertObject('#__jem_register_items', $item);
        }

        if ($admissionQuantity !== (int) $after->places) {
            throw new RuntimeException('Registration places must equal the current admission quantities.');
        }
    }

    private static function isPricedRegistration($row)
    {
        return is_object($row)
            && in_array((string) ($row->pricing_mode ?? 'classic'), array('single', 'multiple', 'priced'), true);
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

    /**
     * Return the immutable capacity catalogue and live availability used by
     * both site and administrator registration forms.
     */
    public function capacityOptions($eventId, $registerId = 0)
    {
        $eventId = (int) $eventId;
        $registerId = (int) $registerId;
        $event = $this->loadCapacityEvent($eventId);
        $result = (object) array(
            'enabled' => false,
            'capacity_mode' => $event ? (string) ($event->capacity_mode ?? 'classic') : 'classic',
            'event_capacity' => $event ? (int) ($event->maxplaces ?? 0) : 0,
            'options' => array(),
            'current' => array(),
        );

        if (!$event || (string) $event->capacity_mode !== 'areas') {
            return $result;
        }

        $catalogue = $this->capacityCatalogue($event);
        $current = $registerId > 0
            ? $this->loadCapacityAllocationRevision($registerId, 0, false)
            : array();
        $used = $this->loadUsedCapacity($eventId, $registerId);
        foreach ($catalogue as $key => $option) {
            $usedQuantity = (int) ($used[$key] ?? 0);
            $currentQuantity = (int) ($current[$key]['quantity'] ?? 0);
            $option['used'] = $usedQuantity;
            $option['remaining'] = max(0, (int) $option['capacity'] - $usedQuantity);
            $option['current_quantity'] = $currentQuantity;
            $result->options[] = $option;
            if ($currentQuantity > 0) {
                $result->current[$key] = $currentQuantity;
            }
        }
        $result->enabled = !empty($result->options);

        return $result;
    }

    /**
     * Validate one capacity-only booking against the event snapshot while the
     * event row is locked. Registration revisions retain their allocation
     * snapshot even after cancellation or waiting-list changes.
     */
    private function prepareCapacityAllocations($before, $after, array $options)
    {
        $event = $this->loadCapacityEvent((int) $after->event);
        if (!$event || (string) ($event->capacity_mode ?? 'classic') !== 'areas') {
            return array('rows' => array(), 'changed' => false);
        }

        $catalogue = $this->capacityCatalogue($event);
        if (!$catalogue) {
            throw new RuntimeException('This event does not have reservable capacity areas.');
        }

        $previous = is_object($before)
            ? $this->loadCapacityAllocationRevision(
                (int) $before->id,
                max(1, (int) ($before->revision ?? 1)),
                true
            )
            : array();
        $submitted = array_key_exists('capacityAllocations', $options);
        $quantities = $submitted
            ? $this->normaliseCapacityQuantities($options['capacityAllocations'], $catalogue)
            : array_map(static function ($row) {
                return (int) $row['quantity'];
            }, $previous);

        $logicalStatus = JemRegistrationTransition::logicalStatus($after);
        $requiresSelection = in_array($logicalStatus, array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true);
        $selectedTotal = array_sum($quantities);
        if ($requiresSelection && ($selectedTotal < 1 || $selectedTotal !== (int) $after->places)) {
            throw new RuntimeException('The selected capacity areas must equal the number of places.');
        }

        $rows = array();
        foreach ($quantities as $key => $quantity) {
            if ($quantity < 1) {
                continue;
            }
            $row = $catalogue[$key];
            $row['quantity'] = $quantity;
            $rows[$key] = $row;
        }

        if ($logicalStatus === JemRegistrationTransition::ATTENDING) {
            $used = $this->loadUsedCapacity((int) $after->event, (int) ($before->id ?? 0));
            $unavailable = false;
            foreach ($rows as $key => $row) {
                if ((int) ($used[$key] ?? 0) + (int) $row['quantity'] > (int) $row['capacity']) {
                    $unavailable = true;
                    break;
                }
            }
            if ($unavailable && !empty($options['allowWaiting']) && !empty($event->waitinglist)) {
                JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::WAITING_LIST);
            } elseif ($unavailable) {
                throw new RuntimeException('The requested capacity area no longer has enough available places.');
            }
        }

        return array(
            'rows' => array_values($rows),
            'changed' => $this->capacityFingerprint($previous) !== $this->capacityFingerprint($rows),
        );
    }

    private function loadCapacityEvent($eventId)
    {
        if ((int) $eventId < 1) {
            return null;
        }
        $query = $this->db->getQuery(true)
            ->select(array('id', 'capacity_mode', 'venue_snapshot', 'maxplaces', 'waitinglist'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $eventId);
        $this->db->setQuery($query);

        return $this->db->loadObject() ?: null;
    }

    private function capacityCatalogue($event)
    {
        $snapshot = json_decode((string) ($event->venue_snapshot ?? ''), true);
        if (!is_array($snapshot) || ($snapshot['schema'] ?? '') !== 'jem-venue-capacity/v1') {
            return array();
        }

        $catalogue = array();
        foreach ((array) ($snapshot['spaces'] ?? array()) as $space) {
            $layout = (array) ($space['layout'] ?? array());
            $areas = array_values(array_filter(
                (array) ($space['capacity_areas'] ?? array()),
                static function ($area) {
                    return !empty($area['published']) && (int) ($area['capacity'] ?? 0) > 0;
                }
            ));
            if (!$areas && (int) ($layout['capacity'] ?? 0) > 0) {
                $areas[] = array(
                    'id' => null,
                    'code' => 'general',
                    'name' => (string) ($space['name'] ?? ''),
                    'capacity' => (int) $layout['capacity'],
                );
            }
            foreach ($areas as $area) {
                $areaId = (int) ($area['id'] ?? 0);
                $layoutId = (int) ($layout['id'] ?? 0);
                $key = $areaId > 0 ? 'area:' . $areaId : 'layout:' . $layoutId;
                if ($layoutId < 1 || isset($catalogue[$key])) {
                    continue;
                }
                $catalogue[$key] = array(
                    'key' => $key,
                    'venue_capacity_area_id' => $areaId > 0 ? $areaId : null,
                    'venue_layout_id' => $layoutId,
                    'venue_layout_revision' => (int) ($layout['revision'] ?? 0),
                    'area_code' => (string) ($area['code'] ?? 'general'),
                    'area_name' => (string) ($area['name'] ?? $space['name'] ?? ''),
                    'space_code' => (string) ($space['code'] ?? ''),
                    'space_name' => (string) ($space['name'] ?? ''),
                    'capacity' => (int) ($area['capacity'] ?? 0),
                );
            }
        }

        return $catalogue;
    }

    private function normaliseCapacityQuantities($input, array $catalogue)
    {
        $quantities = array();
        foreach ((array) $input as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $source = (array) $value;
                $key = (string) ($source['key'] ?? $source['allocation_key'] ?? $key);
                $value = $source['quantity'] ?? 0;
            }
            $key = (string) $key;
            if (ctype_digit($key)) {
                $key = 'area:' . $key;
            }
            if (!isset($catalogue[$key])) {
                if ((int) $value > 0) {
                    throw new RuntimeException('An invalid capacity area was selected.');
                }
                continue;
            }
            $quantity = max(0, (int) $value);
            if ($quantity > (int) $catalogue[$key]['capacity']) {
                throw new RuntimeException('A capacity area quantity exceeds its configured limit.');
            }
            $quantities[$key] = $quantity;
        }

        return $quantities;
    }

    private function loadCapacityAllocationRevision($registerId, $revision = 0, $lock = false)
    {
        if ((int) $registerId < 1) {
            return array();
        }
        if ((int) $revision < 1) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('revision'))
                ->from($this->db->quoteName('#__jem_register'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $registerId);
            $this->db->setQuery($query);
            $revision = (int) $this->db->loadResult();
        }
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_register_capacity_allocations'))
            ->where($this->db->quoteName('register_id') . ' = ' . (int) $registerId)
            ->where($this->db->quoteName('registration_revision') . ' = ' . (int) $revision)
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));
        $rows = array();
        foreach ((array) $this->db->loadAssocList() as $row) {
            $key = !empty($row['venue_capacity_area_id'])
                ? 'area:' . (int) $row['venue_capacity_area_id']
                : 'layout:' . (int) $row['venue_layout_id'];
            $rows[$key] = $row;
        }

        return $rows;
    }

    private function loadUsedCapacity($eventId, $excludedRegisterId = 0)
    {
        $query = $this->db->getQuery(true)
            ->select(array(
                'a.venue_capacity_area_id',
                'a.venue_layout_id',
                'SUM(a.quantity) AS quantity',
            ))
            ->from($this->db->quoteName('#__jem_register_capacity_allocations', 'a'))
            ->join('INNER', $this->db->quoteName('#__jem_register', 'r')
                . ' ON r.id = a.register_id AND r.revision = a.registration_revision')
            ->where('r.event = ' . (int) $eventId)
            ->where('r.status = 1')
            ->where('r.waiting = 0')
            ->group(array('a.venue_capacity_area_id', 'a.venue_layout_id'));
        if ((int) $excludedRegisterId > 0) {
            $query->where('r.id <> ' . (int) $excludedRegisterId);
        }
        $this->db->setQuery($query);
        $used = array();
        foreach ((array) $this->db->loadObjectList() as $row) {
            $key = !empty($row->venue_capacity_area_id)
                ? 'area:' . (int) $row->venue_capacity_area_id
                : 'layout:' . (int) $row->venue_layout_id;
            $used[$key] = (int) $row->quantity;
        }

        return $used;
    }

    private function persistCapacityAllocations($after, array $rows, $now)
    {
        foreach ($rows as $source) {
            $source = (array) $source;
            $row = (object) array(
                'register_id' => (int) $after->id,
                'registration_revision' => (int) $after->revision,
                'event_id' => (int) $after->event,
                'venue_capacity_area_id' => !empty($source['venue_capacity_area_id'])
                    ? (int) $source['venue_capacity_area_id']
                    : null,
                'venue_layout_id' => (int) $source['venue_layout_id'],
                'venue_layout_revision' => (int) $source['venue_layout_revision'],
                'area_code' => (string) $source['area_code'],
                'area_name' => (string) $source['area_name'],
                'space_code' => (string) $source['space_code'],
                'space_name' => (string) $source['space_name'],
                'quantity' => (int) $source['quantity'],
                'created' => $now,
            );
            $this->db->insertObject('#__jem_register_capacity_allocations', $row);
        }
    }

    private function capacityFingerprint(array $rows)
    {
        $fingerprint = array();
        foreach ($rows as $key => $row) {
            $row = (array) $row;
            $resolvedKey = is_string($key) && strpos($key, ':') !== false
                ? $key
                : (!empty($row['venue_capacity_area_id'])
                    ? 'area:' . (int) $row['venue_capacity_area_id']
                    : 'layout:' . (int) ($row['venue_layout_id'] ?? 0));
            $fingerprint[$resolvedKey] = (int) ($row['quantity'] ?? 0);
        }
        ksort($fingerprint, SORT_STRING);

        return json_encode($fingerprint);
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
            ->from($this->db->quoteName('#__jem_register_history'))
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
