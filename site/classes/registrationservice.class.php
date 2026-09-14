<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/registrationquantity.class.php';

/**
 * Transactional writer for capacity-sensitive registrations.
 */
final class JemRegistrationService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * Store one registration while holding the corresponding event lock.
     */
    public function save($data, array $options = array())
    {
        $row = (object) (array) $data;
        $eventId = $this->resolveEventId($row);

        if ($eventId < 1) {
            throw new InvalidArgumentException('A registration requires a valid event.');
        }

        $row->event = $eventId;
        $this->db->transactionStart();

        try {
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
     * Store a registration series as one all-or-nothing operation.
     *
     * Event locks are acquired in numeric order to prevent deadlocks between
     * concurrent series submissions. Capacity is recalculated under the locks.
     */
    public function saveMany(array $rows, array $options = array())
    {
        if (!$rows) {
            return array();
        }

        $normalisedRows = array_map(static function ($row) {
            return (object) (array) $row;
        }, array_values($rows));
        $eventIds = array();

        foreach ($normalisedRows as $row) {
            $eventId = $this->resolveEventId($row);
            if ($eventId < 1) {
                throw new InvalidArgumentException('Every registration in a batch requires a valid event.');
            }
            $row->event = $eventId;
            $eventIds[] = $eventId;
        }

        $eventIds = array_values(array_unique($eventIds));
        sort($eventIds, SORT_NUMERIC);
        $this->db->transactionStart();

        try {
            foreach ($eventIds as $eventId) {
                $this->lockEvent($eventId);
            }

            $results = array();
            foreach ($normalisedRows as $row) {
                $before = $this->loadForUpdate($row);
                $results[] = $this->saveLocked($before, $row, $options);
            }

            $this->db->transactionCommit();

            return $results;
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Persist one row while the caller owns the transaction and event lock.
     */
    private function saveLocked($before, $data, array $options)
    {
        if (is_object($before) && !empty($options['requireNew'])) {
            throw new RuntimeException('The user is already registered for this event.', 1062);
        }
        if (!is_object($before) && !empty($options['requireExisting'])) {
            throw new RuntimeException('The registration no longer exists.');
        }

        $after = $this->normaliseRow($before, $data);

        if (is_object($before)
            && ((int) $before->event !== (int) $after->event
                || (int) $before->uid !== (int) $after->uid)) {
            throw new RuntimeException('A registration cannot be reassigned.');
        }

        $this->assertQuantityPolicy($after);
        $after = $this->applyCapacityPolicy($before, $after, $options);
        $stored = (object) array(
            'event'    => (int) $after->event,
            'uid'      => (int) $after->uid,
            'uregdate' => (string) $after->uregdate,
            'uip'      => (string) $after->uip,
            'waiting'  => (int) $after->waiting,
            'status'   => (int) $after->status,
            'places'   => (int) $after->places,
            'comment'  => (string) $after->comment,
        );

        if (is_object($before)) {
            $stored->id = (int) $before->id;
            $this->db->updateObject('#__jem_register', $stored, 'id');
        } else {
            $this->db->insertObject('#__jem_register', $stored);
            $stored->id = (int) $this->db->insertid();
        }

        $after->id = (int) $stored->id;

        return (object) array(
            'before' => is_object($before) ? clone $before : null,
            'after'  => $after,
        );
    }

    private function normaliseRow($before, $data)
    {
        $input = (object) (array) $data;
        $after = is_object($before) ? clone $before : new stdClass();

        foreach (array('event', 'uid', 'uregdate', 'uip', 'waiting', 'status', 'places', 'comment') as $field) {
            if (property_exists($input, $field)) {
                $after->$field = $input->$field;
            }
        }

        $after->event = (int) ($after->event ?? 0);
        $after->uid = (int) ($after->uid ?? 0);
        $after->uregdate = (string) ($after->uregdate ?? gmdate('Y-m-d H:i:s'));
        $after->uip = (string) ($after->uip ?? '');
        $after->waiting = !empty($after->waiting) ? 1 : 0;
        $after->status = (int) ($after->status ?? JemRegistrationTransition::ATTENDING);
        $after->places = JemRegistrationQuantity::parse($after->places ?? 0);
        $after->comment = (string) ($after->comment ?? '');

        if ($after->status === JemRegistrationTransition::WAITING_LIST) {
            JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::WAITING_LIST);
        }

        if ($after->event < 1 || $after->uid < 1
            || !JemRegistrationTransition::isValidStatus(
                JemRegistrationTransition::logicalStatus($after)
            )) {
            throw new InvalidArgumentException('Invalid registration data.');
        }

        return $after;
    }

    /**
     * Recalculate capacity without trusting values loaded before the lock.
     */
    private function applyCapacityPolicy($before, $after, array $options)
    {
        if (empty($options['respectPlaces'])) {
            return $after;
        }

        $query = $this->db->getQuery(true)
            ->select(array('maxplaces', 'waitinglist', 'reservedplaces'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $after->event);
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
            ->where($this->db->quoteName('event') . ' = ' . (int) $after->event)
            ->where($this->db->quoteName('status') . ' = 1')
            ->where($this->db->quoteName('waiting') . ' = 0');

        if (is_object($before) && !empty($before->id)) {
            $query->where($this->db->quoteName('id') . ' <> ' . (int) $before->id);
        }

        $this->db->setQuery($query);
        $usedPlaces = max(0, (int) $event->reservedplaces)
            + max(0, (int) $this->db->loadResult());
        $requestedPlaces = max(1, (int) $after->places);

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
     * Enforce per-user quantity rules while the event row is locked.
     */
    private function assertQuantityPolicy($registration)
    {
        $query = $this->db->getQuery(true)
            ->select(array('minbookeduser', 'maxbookeduser'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $registration->event);
        $this->db->setQuery($query);
        $event = $this->db->loadObject();

        if (!$event) {
            throw new RuntimeException('Registration event does not exist.');
        }

        JemRegistrationQuantity::assertStoredRow($registration, $event);
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

    private function resolveEventId($row)
    {
        $eventId = (int) ($row->event ?? 0);

        if ($eventId < 1 && !empty($row->id)) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('event'))
                ->from($this->db->quoteName('#__jem_register'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $row->id);
            $this->db->setQuery($query);
            $eventId = (int) $this->db->loadResult();
        }

        return $eventId;
    }

    private function lockEvent($eventId)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $eventId);
        $this->db->setQuery((string) $query . ' FOR UPDATE');

        if ((int) $this->db->loadResult() < 1) {
            throw new RuntimeException('Registration event does not exist.');
        }
    }
}
