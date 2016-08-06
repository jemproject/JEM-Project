<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Model: Attendee
 */
class JemModelAttendee extends JModelLegacy
{
	/**
	 * attendee id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Category data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$jinput = JFactory::getApplication()->input;
		$array = $jinput->get('cid',  0, 'array');

		$this->setId((int)$array[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int category identifier
	 */
	function setId($id)
	{
		// Set category id and wipe data
		$this->_id = $id;
		$this->_data = null;
	}

	/**
	 * Method to get data
	 *
	 * @access	public
	 * @return	array
	 */
	function &getData()
	{
		if (!$this->_loadData()) {
			$this->_initData();
		}

		return $this->_data;
	}

	/**
	 * Method to load data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 */
	protected function _loadData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$db = JFactory::getDbo();

			$query = $db->getQuery(true);
			$query->select(array('r.*','u.name AS username', 'a.title AS eventtitle', 'a.waitinglist'));
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
	 * @access	private
	 * @return	boolean	True on success
	 */
	protected function _initData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$data = JTable::getInstance('jem_register', '');
			$data->username = null;
			if (empty($data->eventtitle)) {
				$jinput = JFactory::getApplication()->input;
				$eventid = $jinput->getInt('event', 0);
				$table = $this->getTable('Event', 'JemTable');
				$table->load($eventid);
				if (!empty($table->title)) {
					$data->eventtitle = $table->title;
				}
				$data->waitinglist = isset($table->waitinglist) ? $table->waitinglist : 0;
			}
			$this->_data = $data;
		}
		return true;
	}

	function toggle()
	{
		$attendee = $this->getData();

		if (!$attendee->id) {
			$this->setError(JText::_('COM_JEM_MISSING_ATTENDEE_ID'));
			return false;
		}

		$row = JTable::getInstance('jem_register', '');
		$row->bind($attendee);
		$row->waiting = ($attendee->waiting || ($attending->status == 2)) ? 0 : 1;
		if ($row->status == 2) {
			$row->status = 1;
		}
		return $row->store();
	}


	/**
	 * Method to store the attendee
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function store($data)
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

		$row = $this->getTable('jem_register', '');

		if ($id > 0) {
			$row->load($id);
			$old_data = clone $row;
		}

		// bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->getErrorMsg());
			return false;
		}

		// sanitise id field
		$row->id = (int) $row->id;
		$db = JFactory::getDbo();

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
			JError::raiseWarning(0, JText::_('COM_JEM_ERROR_USER_ALREADY_REGISTERED'));
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
						JError::raiseWarning(0, JText::_('COM_JEM_ERROR_REGISTER_EVENT_IS_FULL'));
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
			JError::raiseError(500, $this->_db->getErrorMsg());
			return false;
		}

		return $row;
	}


	/**
	 * Method to set status of registered
	 *
	 * @param  int $pks   ID of the attendee record
	 * @param  int $value Status value: -1 - "not attending", 0 - "invited", 1 - "attending", 2 - "on waiting list"
	 * @return boolean	True on success.
	 */
	public function setStatus($pks, $value = 1)
	{
		// Sanitize the ids.
		$pks = (array) $pks;
		JArrayHelper::toInteger($pks);

		if (empty($pks)) {
			$this->setError(JText::_('JERROR_NO_ITEMS_SELECTED'));
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
			$db = $this->getDbo();

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
