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
            $query->select(array('r.*','u.name AS username', 'a.title AS eventtitle', 'a.waitinglist', 'a.maxbookeduser', 'a.minbookeduser', 'a.recurrence_type', 'a.series_id', 'a.series_order', 'a.seriesbooking'));
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
                }
                $data->waitinglist = $table->waitinglist ?? 0;
            }
            $this->_data = $data;
        }
        return true;
    }

    public function toggle()
    {
        $attendee = $this->getData();

        if (!$attendee->id) {
            $this->setError(Text::_('COM_JEM_MISSING_ATTENDEE_ID'));
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
        $db = Factory::getContainer()->get('DatabaseDriver');

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
