<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

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
		$array = $jinput->get('id',  0, 'array');

		if(is_array($array))
		{
			$this->setId((int)$array[0]);
		}
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
			$query->select(array('r.*','u.name AS username', 'a.title AS eventtitle', 'a.waitinglist', 'a.maxbookeduser', 'a.minbookeduser'));
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

			return (boolean) $this->_data;
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
				}
				$data->waitinglist = isset($table->waitinglist) ? $table->waitinglist : 0;
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

		$row = Table::getInstance('jem_register', '');
		$row->bind($attendee);
		$row->waiting = ($attendee->waiting || ($attendee->status == 2)) ? 0 : 1;
		if ($row->status == 2) {
			$row->status = 1;
		}
		return $row->store();
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
		$eventid = $data['event'];
		$userid  = $data['uid'];
		$id      = !empty($data['id']) ? (int)$data['id'] : 0;
		$status  = isset($data['status']) ? $data['status'] : false;

		// Split status and waiting
		if ($status !== false) {
			if ($status == 2) {
				$data['status'] = 1;
				$data['waiting'] = 1;
			} elseif ($status == 1) {
				$data['waiting'] = 0;
			}
		}

		// $row = $this->getTable('jem_register', '');
		$row = Table::getInstance('jem_register', '');

		if ($id > 0) {
			$row->load($id);
			$old_data = clone $row;
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

		} else {
			if ($row->status === 0) {
				// todo: add "invited" field to store such timestamps?
			} else { // except status "invited"
				$row->uregdate = gmdate('Y-m-d H:i:s');
			}

			// Get event
			$query = $db->getQuery(true);
			$query->select(array('maxplaces','waitinglist'));
			$query->from('#__jem_events');
			$query->where('id= '.$db->quote($eventid));

			$db->setQuery($query);
			$event = $db->loadObject();

			// Get register information of the event
			$query = $db->getQuery(true);
			$query->select(array('COUNT(id) AS registered', 'COALESCE(SUM(waiting), 0) AS waiting'));
			$query->from('#__jem_register');
			$query->where('status = 1 AND event = '.$db->quote($eventid));

			$db->setQuery($query);
			$register = $db->loadObject();

			// If no one is registered yet, $register is null!
			if (is_null($register)) {
				$register = new stdClass;
				$register->registered = 0;
				$register->waiting = 0;
				$register->booked = 0;
			} else {
				$register->booked = $register->registered - $register->waiting;
			}

			// put on waiting list ?
			if (($event->maxplaces > 0) && ($status == 1)) // there is a max and user will attend
			{
				// check if the user should go on waiting list
				if ($register->booked >= $event->maxplaces)
				{
					if (!$event->waitinglist) {
						Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_REGISTER_EVENT_IS_FULL'), 'warning');
						return false;
					} else {
						$row->waiting = 1;
					}
				}
			}
		}

		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}

		// Store it in the db
		if (!$row->store()) {
			Factory::getApplication()->enqueueMessage($row->getError(), 'error');
			return false;
		}

		return $row;
	}

	/**
	 * Method to set status of registered
	 *
	 * @param  array $pks   IDs of the attendee records
	 * @param  int   $value Status value: -1 - "not attending", 0 - "invited", 1 - "attending", 2 - "on waiting list"
	 * @return boolean      True on success.
	 */
	public function setStatus($pks, $value = 1)
	{
		// Sanitize the ids.
		$pks = (array)$pks;
		\Joomla\Utilities\ArrayHelper::toInteger($pks);

		if (empty($pks)) {
			$this->setError(Text::_('JERROR_NO_ITEMS_SELECTED'));
			return false;
		}

		// Split status and waiting
		if ($value == 2) {
			$status = 1;
			$waiting = 1;
		} else {
			$status = (int)$value;
			$waiting = 0;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			$db->setQuery(
					'UPDATE #__jem_register' .
					' SET status = '.$status.', waiting = '.$waiting.
					' WHERE id IN ('.implode(',', $pks).')'
					);
			if ($db->execute() === false) {
				throw new Exception($db->getErrorMsg());
			}

		} catch (Exception $e) {
			JemHelper::addLogEntry($e->getMessage(), __METHOD__, JLog::ERROR);
			$this->setError($e->getMessage());
			return false;
		}

	//	JemHelper::addLogEntry("Registration status of record(s) ".implode(', ', $pks)." set to $value", __METHOD__, JLog::DEBUG);
		return true;
	}
}
