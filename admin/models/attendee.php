<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Log\Log;
use Joomla\Utilities\ArrayHelper;

/**
 * Model: Attendee
 */
class JemModelAttendee extends BaseDatabaseModel
{
    /**
     * attendee id
     *
     * @var int
     */
    protected $_id = null;

    /**
     * Category data array
     *
     * @var array
     */
    protected $_data = null;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->input;
        $this->setId($jinput->getInt('id', 0));
    }

    /**
     * Method to set the identifier
     *
     * @access public
     * @param  int  category identifier
     */
    public function setId($id)
    {
        // Set category id and wipe data
        $this->_id = $id;
        $this->_data = null;
    }

    /**
     * Method to get data
     *
     * @access public
     * @return array
     */
    public function getData()
    {
        if (!$this->_loadData()) {
            $this->_initData();
        }

        return $this->_data;
    }

    /**
     * Method to load data
     *
     * @access protected
     * @return boolean  True on success
     */
    protected function _loadData()
    {
        // Lets load the content if it doesn't already exist
        if (empty($this->_data))
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);
            $query->select(array(
                'r.*', 'u.name AS username', 'a.title AS eventtitle', 'a.waitinglist',
                'a.maxbookeduser', 'a.minbookeduser', 'a.recurrence_type', 'a.series_id',
                'a.series_order', 'a.seriesbooking', 'a.pricing_mode AS event_pricing_mode',
                'a.pricing_revision AS event_pricing_revision', 'a.currency AS event_currency',
                'a.prices_include_tax', 'a.capacity_mode AS event_capacity_mode',
            ));
            $query->from('#__jem_register as r');
            $query->join('LEFT', '#__users AS u ON (u.id = r.uid)');
            $query->join('LEFT', '#__jem_events AS a ON (a.id = r.event)');
            $query->where(array('r.id= '.$db->quote($this->_id)));

            $this->_db->setQuery($query);
            $this->_data = $this->_db->loadObject();

            // Merge status and waiting
            if (!empty($this->_data) && !empty($this->_data->waiting) && ($this->_data->status == 1)) {
                $this->_data->status = 2;
            }

            return (bool) $this->_data;
        }
        return true;
    }

    /**
     * Method to initialise the data
     *
     * @access protected
     * @return boolean  True on success
     */
    protected function _initData()
    {
        // Lets load the content if it doesn't already exist
        if (empty($this->_data))
        {
            $data = Table::getInstance('jem_register', '');
            $data->username = null;
            if (empty($data->eventtitle)) {
                $jinput = Factory::getApplication()->input;
                $eventid = $jinput->getInt('eventid', 0);
                $table = $this->getTable('Event', 'JemTable');
                $table->load($eventid);
                if (!empty($table->title)) {
                    $data->eventtitle = $table->title;
                    $data->event = $table->id;
                    $data->maxbookeduser = $table->maxbookeduser;
                    $data->minbookeduser = $table->minbookeduser;
                    $data->recurrence_type = $table->recurrence_type;
                    $data->seriesbooking = $table->seriesbooking;
                    $data->event_pricing_mode = $table->pricing_mode;
                    $data->event_pricing_revision = $table->pricing_revision;
                    $data->event_currency = $table->currency;
                    $data->prices_include_tax = $table->prices_include_tax;
                    $data->event_capacity_mode = $table->capacity_mode;
                }
                $data->waitinglist = $table->waitinglist ?? 0;
            }
            $this->_data = $data;
        }
        return true;
    }

    /**
     * Build the administrator admission selector from current inventory and
     * the selected booking holder's access/group eligibility.
     */
    public function getPricingData($eventId = 0, $userId = 0, $registrationId = 0)
    {
        $eventId = (int) $eventId;
        $userId = (int) $userId;
        $registrationId = (int) $registrationId;
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'id', 'pricing_mode', 'pricing_revision', 'currency', 'prices_include_tax',
                'maxplaces', 'reservedplaces', 'waitinglist',
            ))
            ->from($db->quoteName('#__jem_events'))
            ->where('id = ' . $eventId);
        $db->setQuery($query);
        $event = $db->loadObject();
        $priced = $event && in_array((string) $event->pricing_mode, array('single', 'multiple', 'priced'), true);
        $result = (object) array(
            'is_priced' => $priced,
            'pricing_mode' => $event ? (string) $event->pricing_mode : 'classic',
            'pricing_revision' => $event ? (int) $event->pricing_revision : 0,
            'currency' => $event ? (string) $event->currency : '',
            'event_capacity' => $event ? (int) $event->maxplaces : 0,
            'event_used' => 0,
            'event_remaining' => 0,
            'waitinglist' => $event ? (int) $event->waitinglist : 0,
            'options' => array(),
            'pools' => array(),
        );
        if (!$priced) {
            return $result;
        }

        $currentItems = array();
        $currentStatus = null;
        if ($registrationId > 0) {
            $query = $db->getQuery(true)
                ->select(array('status', 'waiting'))
                ->from($db->quoteName('#__jem_register'))
                ->where('id = ' . $registrationId)
                ->where('event = ' . $eventId);
            if ($userId > 0) {
                $query->where('uid = ' . $userId);
            }
            $db->setQuery($query);
            $currentRegistration = $db->loadObject();
            $currentStatus = $currentRegistration
                ? JemRegistrationTransition::logicalStatus($currentRegistration)
                : null;
            if ($currentRegistration) {
                $query = $db->getQuery(true)
                    ->select('i.*')
                    ->from($db->quoteName('#__jem_register_items', 'i'))
                    ->join('INNER', $db->quoteName('#__jem_register', 'r')
                        . ' ON r.id = i.register_id AND r.revision = i.registration_revision')
                    ->where('r.id = ' . $registrationId)
                    ->where('r.event = ' . $eventId)
                    ->where("i.line_kind = 'admission'")
                    ->order('i.line_number ASC');
                $db->setQuery($query);
                foreach ((array) $db->loadObjectList() as $item) {
                    $currentItems[(int) $item->event_price_id] = $item;
                }
            }
        }

        $query = $db->getQuery(true)
            ->select(array(
                'p.*', 'cp.name AS pool_name', 'cp.capacity AS pool_capacity',
                't.code AS tax_code', 't.name AS tax_name', 't.tax_type', 't.rate AS tax_rate',
            ))
            ->from($db->quoteName('#__jem_event_prices', 'p'))
            ->join('LEFT', $db->quoteName('#__jem_capacity_pools', 'cp') . ' ON cp.id = p.capacity_pool_id')
            ->join('LEFT', $db->quoteName('#__jem_tax_rates', 't') . ' ON t.id = p.tax_rate_id')
            ->where('p.event_id = ' . $eventId)
            ->order('p.ordering ASC, p.id ASC');
        $db->setQuery($query);
        $prices = (array) $db->loadObjectList();

        $active = 'r.status = 1 AND r.waiting = 0';
        $query = $db->getQuery(true)
            ->select('COALESCE(SUM(GREATEST(r.places, 1)), 0)')
            ->from($db->quoteName('#__jem_register', 'r'))
            ->where('r.event = ' . $eventId)
            ->where($active);
        if ($registrationId > 0) {
            $query->where('r.id <> ' . $registrationId);
        }
        $db->setQuery($query);
        $result->event_used = max(0, (int) $event->reservedplaces) + (int) $db->loadResult();
        $eventRemaining = (int) $event->maxplaces > 0
            ? max(0, (int) $event->maxplaces - $result->event_used)
            : PHP_INT_MAX;
        $result->event_remaining = $eventRemaining === PHP_INT_MAX ? null : $eventRemaining;

        $base = $db->getQuery(true)
            ->select(array('i.capacity_pool_id', 'i.event_price_id', 'i.quantity'))
            ->from($db->quoteName('#__jem_register_items', 'i'))
            ->join('INNER', $db->quoteName('#__jem_register', 'r')
                . ' ON r.id = i.register_id AND r.revision = i.registration_revision')
            ->where('r.event = ' . $eventId)
            ->where($active)
            ->where("i.line_kind = 'admission'");
        if ($registrationId > 0) {
            $base->where('r.id <> ' . $registrationId);
        }
        $db->setQuery($base);
        $poolUsed = array();
        $priceUsed = array();
        foreach ((array) $db->loadObjectList() as $used) {
            if ((int) $used->capacity_pool_id > 0) {
                $poolUsed[(int) $used->capacity_pool_id] = ($poolUsed[(int) $used->capacity_pool_id] ?? 0)
                    + (int) $used->quantity;
            }
            if ((int) $used->event_price_id > 0) {
                $priceUsed[(int) $used->event_price_id] = ($priceUsed[(int) $used->event_price_id] ?? 0)
                    + (int) $used->quantity;
            }
        }

        $levels = array();
        $groups = array();
        if ($userId > 0) {
            $bookingHolder = JemFactory::getUser($userId);
            $levels = array_flip(array_map('intval', $bookingHolder->getAuthorisedViewLevels()));
            $groups = array_flip(array_map('intval', $bookingHolder->getAuthorisedGroups()));
        }
        $now = gmdate('Y-m-d H:i:s');
        foreach ($prices as $price) {
            $priceId = (int) $price->id;
            $current = $currentItems[$priceId] ?? null;
            $lockedCurrent = $current
                && $currentStatus !== JemRegistrationTransition::NOT_ATTENDING;
            $eligible = $userId > 0
                && ((int) $price->published === 1 || $lockedCurrent)
                && (empty($price->available_from) || (string) $price->available_from <= $now || $lockedCurrent)
                && (empty($price->available_until) || (string) $price->available_until >= $now || $lockedCurrent)
                && (empty($price->access_level_id) || isset($levels[(int) $price->access_level_id]))
                && (empty($price->user_group_id) || isset($groups[(int) $price->user_group_id]));
            $poolId = (int) ($price->capacity_pool_id ?? 0);
            $poolRemaining = $poolId > 0
                ? max(0, (int) $price->pool_capacity - (int) ($poolUsed[$poolId] ?? 0))
                : $eventRemaining;
            $quotaRemaining = $price->quota === null
                ? PHP_INT_MAX
                : max(0, (int) $price->quota - (int) ($priceUsed[$priceId] ?? 0));
            $available = min($eventRemaining, $poolRemaining, $quotaRemaining);
            $unitGross = $lockedCurrent ? (string) $current->unit_gross : (string) $price->amount;
            if (!$lockedCurrent && !empty($price->tax_type)) {
                try {
                    $policy = new JemTaxPolicy(
                        (string) $price->tax_type,
                        (string) $price->tax_rate,
                        (int) $event->prices_include_tax === 1
                    );
                    $calculation = JemTaxCalculator::calculate(
                        JemMoney::fromDecimal((string) $price->amount, (string) $event->currency),
                        $policy,
                        1
                    );
                    $unitGross = $calculation->unitGross->decimal();
                } catch (Throwable $ignored) {
                    // The authoritative save path will reject an invalid tax configuration.
                }
            }

            $result->options[] = (object) array(
                'id' => $priceId,
                'code' => (string) $price->code,
                'name' => $lockedCurrent ? (string) $current->item_name : (string) $price->name,
                'pool_id' => $poolId ?: null,
                'pool_name' => (string) ($price->pool_name ?? ''),
                'available' => $available === PHP_INT_MAX ? null : $available,
                'unit_gross' => $unitGross,
                'quantity' => $current ? (int) $current->quantity : 0,
                'min_quantity' => max(1, (int) $price->min_quantity),
                'max_quantity' => $price->max_quantity === null ? null : (int) $price->max_quantity,
                'eligible' => $eligible,
                'locked' => (bool) $lockedCurrent,
            );
            if ($poolId > 0 && !isset($result->pools[$poolId])) {
                $result->pools[$poolId] = (object) array(
                    'id' => $poolId,
                    'name' => (string) $price->pool_name,
                    'capacity' => (int) $price->pool_capacity,
                    'used' => (int) ($poolUsed[$poolId] ?? 0),
                    'remaining' => $poolRemaining,
                );
            }
        }
        $result->pools = array_values($result->pools);

        return $result;
    }

    public function getCapacityData($eventId = 0, $registrationId = 0)
    {
        return (new JemRegistrationService())->capacityOptions(
            (int) $eventId,
            (int) $registrationId
        );
    }

    public function toggle()
    {
        $attendee = $this->getData();

        if (!$attendee->id) {
            $this->setError(Text::_('COM_JEM_MISSING_ATTENDEE_ID'));
            return false;
        }

        if (in_array((string) ($attendee->event_pricing_mode ?? 'classic'), array('single', 'multiple', 'priced'), true)) {
            $this->setError(Text::_('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED'));
            return false;
        }

        if (!in_array(JemRegistrationTransition::logicalStatus($attendee), array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true)) {
            $this->setError(Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'));
            return false;
        }

        $after = clone $attendee;
        $after->waiting = ($attendee->waiting || ($attendee->status == 2)) ? 0 : 1;
        if ($after->status == 2) {
            $after->status = 1;
        }

        try {
            $result = (new JemRegistrationService())->save($after, array(
                'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                'source'  => 'administrator.attendee.toggle',
                'respectPlaces' => true,
            ));
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $this->_data = $result->after;
        return true;
    }

    /**
     * Method to store the attendee
     *
     * @access public
     * @return boolean  True on success
     *
     */
    public function store($data)
    {
        $eventid = (int)($data['event'] ?? 0);
        $userid  = (int)($data['uid'] ?? 0);
        $id      = !empty($data['id']) ? (int)$data['id'] : 0;
        $status = $data['status'] ?? false;

        if ($eventid < 1 || $userid < 1) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_USER_ALREADY_REGISTERED'), 'warning');
            return false;
        }

        if ($status !== false && !JemRegistrationTransition::isValidStatus($status)) {
            $this->setError(Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'));
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array('id', 'pricing_mode', 'pricing_revision', 'capacity_mode', 'waitinglist', 'recurrence_type', 'series_id'))
            ->from($db->quoteName('#__jem_events'))
            ->where('id = ' . $eventid);
        $db->setQuery($query);
        $pricedEvent = $db->loadObject();
        $isPriced = $pricedEvent && in_array(
            (string) $pricedEvent->pricing_mode,
            array('single', 'multiple', 'priced'),
            true
        );
        if ($isPriced && !JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
            $this->setError(Text::_('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY'));

            return false;
        }
        $isAreaCapacity = $pricedEvent && (string) ($pricedEvent->capacity_mode ?? 'classic') === 'areas';
        $capacityAllocations = (array) ($data['capacity_areas'] ?? array());
        if ($isAreaCapacity && in_array((int) $status, array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true)) {
            $data['places'] = array_sum(array_map('intval', $capacityAllocations));
        }

        if ($isPriced && in_array((int) $status, array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true)) {
            if (!empty($data['seriesbooking']) || !empty($pricedEvent->recurrence_type) || !empty($pricedEvent->series_id)) {
                $this->setError(Text::_('COM_JEM_PRICED_REGISTRATION_SERIES_UNAVAILABLE'));
                return false;
            }
            if ((int) $status === JemRegistrationTransition::WAITING_LIST && empty($pricedEvent->waitinglist)) {
                $this->setError(Text::_('COM_JEM_NO_WAITINGLIST'));
                return false;
            }

            $before = null;
            if ($id > 0) {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__jem_register'))
                    ->where('id = ' . $id);
                $db->setQuery($query);
                $before = $db->loadObject();
                if (!$before || (int) $before->event !== $eventid) {
                    $this->setError(Text::_('COM_JEM_MISSING_ATTENDEE_ID'));
                    return false;
                }
                if ((int) $before->uid !== $userid) {
                    $this->setError(Text::_('COM_JEM_PRICED_REGISTRATION_USER_LOCKED'));
                    return false;
                }
            }

            $selections = array();
            foreach ((array) ($data['admissions'] ?? array()) as $priceId => $quantity) {
                $priceId = (int) $priceId;
                $quantity = (int) $quantity;
                if ($priceId > 0 && $quantity > 0) {
                    $selections[] = array('event_price_id' => $priceId, 'quantity' => $quantity);
                }
            }
            if (!$selections) {
                $this->setError(Text::_('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED'));
                return false;
            }

            try {
                $bookingHolder = JemFactory::getUser($userid);
                $context = JemPricingQuoteContext::fromIdentity(
                    $bookingHolder,
                    max(1, (int) $pricedEvent->pricing_revision),
                    $id
                );
                $service = new JemPricedRegistrationService($db);
                $quote = $service->quote($eventid, $selections, $context);
                $options = array(
                    'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                    'source' => $id > 0 ? 'administrator.attendee.order_edit' : 'administrator.attendee.order_add',
                    'requestedStatus' => (int) $status,
                );
                if ($before) {
                    $options['expectedRevision'] = max(1, (int) ($before->revision ?? 1));
                }
                $result = $service->confirm(
                    $eventid,
                    $selections,
                    $context,
                    $quote['quote_fingerprint'],
                    JemRegistrationIdentity::generateOperationReference(),
                    array(
                        'comment' => (string) ($data['comment'] ?? ($before->comment ?? '')),
                        'uip' => (string) ($data['uip'] ?? ($before->uip ?? '')),
                    ),
                    $options
                );
                $this->_data = $result->after;

                return $result->after;
            } catch (Throwable $e) {
                $this->setError($e->getMessage());
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
                return false;
            }
        }

        if ($isPriced && (int) $status === JemRegistrationTransition::INVITED) {
            $data['places'] = 0;
        }

        // Split status and waiting
        if ($status !== false) {
            if ($status == 2) {
                $data['status'] = 1;
                $data['waiting'] = 1;
            } else {
                $data['status'] = (int) $status;
                $data['waiting'] = 0;
            }
        }

        // $row = $this->getTable('jem_register', '');
        $row = Table::getInstance('jem_register', '');

        if ($id > 0) {
            if (!$row->load($id)) {
                Factory::getApplication()->enqueueMessage($row->getError(), 'error');
                return false;
            }
        }

        // bind it to the table
        if (!$row->bind($data)) {
            Factory::getApplication()->enqueueMessage($row->getError(), 'error');
            return false;
        }

        // sanitise id field
        $row->id = (int)$row->id;
        // Check if user is already registered to this event
        $query = $db->getQuery(true);
        $query->select(array('COUNT(id) AS count'));
        $query->from('#__jem_register');
        $query->where('event = '.$db->quote($eventid));
        $query->where('uid = '.$db->quote($userid));
        if ($row->id) {
            $query->where('id != '.$db->quote($row->id));
        }
        $db->setQuery($query);
        $cnt = $db->loadResult();

        if ($cnt > 0) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_USER_ALREADY_REGISTERED'), 'warning');
            return false;
        }

        // Are we saving from an item edit?
        if ($row->id) {
            if (!$row->check()) {
                Factory::getApplication()->enqueueMessage($row->getError(), 'error');
                return false;
            }

            try {
                $result = (new JemRegistrationService($db))->save($row, array(
                    'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                    'source'  => 'administrator.attendee.edit',
                    'respectPlaces' => true,
                    'requireExisting' => true,
                    'capacityAllocations' => $capacityAllocations,
                ));
            } catch (Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
                return false;
            }

            $this->_data = $result->after;
            return $result->after;
        } else {
            if ($row->status === 0) {
                // todo: add "invited" field to store such timestamps?
            } else { // except status "invited"
                $row->uregdate = gmdate('Y-m-d H:i:s');
            }

            // Get event
            $query = $db->getQuery(true);
            $query->select(array('id','maxplaces','waitinglist','recurrence_first_id','recurrence_type','series_id','series_order','seriesbooking','singlebooking'));
            $query->from('#__jem_events');
            $query->where('id= '.$db->quote($eventid));

            $db->setQuery($query);
            $event = $db->loadObject();

            // If recurrence event, save series event
            $events = array();
            if($event->recurrence_type || !empty($event->series_id)){
                // Retrieving seriesbooking
                $seriesbooking = $data["seriesbooking"];
                $singlebooking = $data["singlebooking"];

                // If event has 'seriesbooking' active
                if($event->seriesbooking && $seriesbooking && !$singlebooking){
                    //GEt date and time now
                    $nowTimestamp = time();
                    $dateFrom = gmdate('Y-m-d', $nowTimestamp);
                    $timeFrom = gmdate('H:i:s', $nowTimestamp);
                    $utcFrom = gmdate('Y-m-d H:i:s', $nowTimestamp);

                    // Get the all recurrence events of serie from now
                    $query = $db->getQuery(true);
                    $query->select(array('id','recurrence_first_id','series_id','series_order','maxplaces','waitinglist','recurrence_type','seriesbooking','singlebooking'));
                    $query->from('#__jem_events as a');
                    if (!empty($event->series_id)) {
                        $query->where('a.series_id = ' . (int) $event->series_id);
                    } else {
                        $query->where('((a.recurrence_first_id = 0 AND a.id = ' . (int)($event->recurrence_first_id?$event->recurrence_first_id:$event->id) . ') OR a.recurrence_first_id = ' . (int)($event->recurrence_first_id?$event->recurrence_first_id:$event->id) . ")");
                    }
                    $query->where(
                        '((a.start_utc IS NOT NULL AND a.start_utc >= ' . $db->quote($utcFrom) . ')' .
                        ' OR (a.start_utc IS NULL AND (a.dates > ' . $db->quote($dateFrom) .
                        ' OR (a.dates = ' . $db->quote($dateFrom) . ' AND (a.times IS NULL OR a.times >= ' . $db->quote($timeFrom) . ')))))'
                    );
                    $db->setQuery($query);
                    $events = $db->loadObjectList();
                }
            }

            if (!isset($events) || !count ($events)){
                $events [] = clone $event;
            }

            $pendingRows = array();
            foreach ($events as $e) {

                // Check if user is registered to each series event
                $query = $db->getQuery(true);
                $query->select(array('COUNT(id) AS count'));
                $query->from('#__jem_register');
                $query->where('event = '.$db->quote($e->id));
                $query->where('uid = '.$db->quote($userid));
                $db->setQuery($query);
                $cnt = $db->loadResult();

                if ($cnt > 0) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_USER_ALREADY_REGISTERED') . '[id: ' . $e->id . ']', 'warning');
                    return false;
                }

                $row_aux= clone $row;
                $row_aux->event = $e->id;

                // Make sure the data is valid
                if (!$row_aux->check()) {
                    $this->setError($row->getError());
                    return false;
                }

                $pendingRows[] = $row_aux;
            }

            try {
                $stored = (new JemRegistrationService($db))->saveMany($pendingRows, array(
                    'actorId'      => (int) Factory::getApplication()->getIdentity()->id,
                    'source'       => 'administrator.attendee.add',
                    'respectPlaces'=> true,
                    'allowWaiting' => true,
                    'requireNew'   => true,
                    'capacityAllocations' => $capacityAllocations,
                ));
            } catch (Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
                return false;
            }

            $storedRow = null;
            foreach ($stored as $result) {
                if ($storedRow === null || (int) $result->after->event === $eventid) {
                    $storedRow = $result->after;
                }
            }

            return $storedRow ?: false;
        }
    }

    /**
     * Method to set status of registered
     *
     * @param  array $pks   IDs of the attendee records
     * @param  int   $value Status value: -1 - "not attending", 0 - "invited", 1 - "attending", 2 - "on waiting list"
     * @return boolean      True on success.
     */
    public function setStatus($pks, $value = 1, $eventId = 0)
    {
        if (!JemRegistrationTransition::isValidStatus($value) || (int) $eventId < 1) {
            $this->setError(Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'));
            return false;
        }

        if (in_array((int) $value, array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true)) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('pricing_mode')
                ->from($db->quoteName('#__jem_events'))
                ->where('id = ' . (int) $eventId);
            $db->setQuery($query);
            if (in_array((string) $db->loadResult(), array('single', 'multiple', 'priced'), true)) {
                $this->setError(Text::_('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED'));
                return false;
            }
        }

        // Sanitize the ids.
        $pks = (array)$pks;
        ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            $this->setError(Text::_('JERROR_NO_ITEMS_SELECTED'));
            return false;
        }

        try {
            (new JemRegistrationService())->setLogicalStatusByIds(
                $pks,
                (int) $eventId,
                (int) $value,
                array(
                    'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                    'source'  => 'administrator.attendees.batch',
                    'respectPlaces' => true,
                )
            );
        } catch (Throwable $e) {
            JemHelper::addLogEntry($e->getMessage(), __METHOD__, Log::ERROR);
            $this->setError($e->getMessage());
            return false;
        }

    //    JemHelper::addLogEntry("Registration status of record(s) ".implode(', ', $pks)." set to $value", __METHOD__, Log::DEBUG);
        return true;
    }
}
