<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Event Model
 *
 * @package JEM
 *
 */
class JEMModelEvent extends JModelLegacy
{
	/**
	 * Event data in event array
	 *
	 * @var array
	 */
	var $_event = null;

	/**
	 * registeres in array
	 *
	 * @var array
	 */
	var $_registers = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$id = JRequest::getInt('id');
		$this->setId((int)$id);
	}

	/**
	 * Method to set the event id
	 *
	 * @access	public
	 * @param	int	event ID number
	 */

	function setId($id)
	{
		// Set new event ID and wipe data
		$this->_id = $id;
	}

	/**
	 * Method to get event data for the Eventview
	 *
	 * @access public
	 * @return array
	 *
	 */
	function &getEvent()
	{
		/*
		 * Load the Category data
		 */
		if ($this->_loadEvent()) {
			$user = JFactory::getUser();
			$gid = JEMHelper::getGID($user);

			// Is the category published?
			if (!$this->_event->catpublished && $this->_event->catid) {
				throw new Exception(JText::_("COM_JEM_CATEGORY_NOT_PUBLISHED"),403 );
			}

			// Do we have access to the category?
			if (($this->_event->cataccess > $gid) && $this->_event->catid) {
				 throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			}

			//check session if uservisit already recorded
			$session 	= JFactory::getSession();
			$hitcheck = false;
			if ($session->has('hit', 'jem')) {
				$hitcheck 	= $session->get('hit', 0, 'jem');
				$hitcheck 	= in_array($this->_event->did, $hitcheck);
			}
			if (!$hitcheck) {
				//record hit
				$this->hit();

				$stamp = array();
				$stamp[] = $this->_event->did;
				$session->set('hit', $stamp, 'jem');
			}

			return $this->_event;
		}
	}

	/**
	 * Method to load required data
	 *
	 * @access	private
	 * @return	array
	 *
	 */
	function _loadEvent()
	{
		if (empty($this->_event)) {
			$db = JFactory::getDbo();

			// Get the WHERE clause
			$where	= $this->_buildEventWhere();

			$query = 'SELECT a.id AS did, a.published, a.contactid, a.dates, a.enddates, a.title, a.times, a.endtimes, '
					. ' a.datdescription, a.meta_keywords, a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, a.meta_description, a.unregistra, a.locid, a.created_by, '
					. ' a.datimage, a.registra, a.maxplaces, a.waitinglist, '
					. ' l.id AS locid, l.venue, l.city, l.state, l.url, l.locdescription, l.locimage, l.city, l.postalCode, l.street, l.country, ct.name AS countryname, l.map, l.created_by AS venueowner, l.latitude, l.longitude,'
					. ' c.access AS cataccess, c.id AS catid, c.published AS catpublished,'
					. ' u.name AS creator_name, u.username AS creator_username, con.id AS conid, con.name AS conname, con.telephone AS contelephone, con.email_to AS conemail,'
					. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
					. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
//					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
					. ' FROM #__jem_events AS a'
					. ' LEFT JOIN #__jem_venues AS l ON a.locid = l.id'
					. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
					. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
					. ' LEFT JOIN #__users AS u ON u.id = a.created_by'
					. ' LEFT JOIN #__contact_details AS con ON con.id = a.contactid'
					. ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
					. $where
					. ' GROUP BY a.id '
					;
			$this->_db->setQuery($query);
			$this->_event = $this->_db->loadObject();

			// Define Attachments
			$user = JFactory::getUser();
			$gid = JEMHelper::getGID($user);

			$this->_event->attachments = JEMAttachment::getAttachments('event'.$this->_event->did, $gid);

			// Define Booked
			$query = $db->getQuery(true);
			$query->select(array('COUNT(*)'));
			$query->from('#__jem_register');
			$query->where(array('event= '.$db->quote($this->_event->did), 'waiting= 0'));
			$db->setQuery($query);
			$res = $db->loadResult();
			$this->_event->booked = $res;

			return (boolean) $this->_event;
		}
		return true;
	}

	/**
	 * Method to build the WHERE clause of the query to select the event
	 *
	 * @access	private
	 * @return	string	WHERE clause
	 *
	 */
	function _buildEventWhere()
	{
		$where = ' WHERE a.id = '.$this->_id;

		return $where;
	}

	/**
	 * Method to get the categories
	 *
	 * @access	public
	 * @return	object
	 *
	 */
	function getCategories()
	{
		$user = JFactory::getUser();
		$gid = JEMHelper::getGID($user);


		$query = 'SELECT DISTINCT c.id, c.catname,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
		. ' FROM #__jem_categories AS c'
		. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
		. ' WHERE rel.itemid = '.$this->_id
		. ' AND c.published = 1'
		. ' AND c.access  <= '.$gid
		;

		$this->_db->setQuery($query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}

	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function hit()
	{
		if ($this->_id)
		{
			$item = JTable::getInstance('jem_events', '');
			$item->hit($this->_id);
			return true;
		}
		return false;
	}


	/**
	 * Method to check if the user is already registered
	 * return false if not registered, 1 for registerd, 2 for waiting list
	 *
	 * @access	public
	 * @return	mixed false if not registered, 1 for registerd, 2 for waiting list
	 *
	 */
	function getUserIsRegistered()
	{
		// Initialize variables
		$user 		= JFactory::getUser();
		$userid		= (int) $user->get('id', 0);

		//usercheck
		$query = 'SELECT waiting+1' // 1 if user is registered, 2 if on waiting list
				. ' FROM #__jem_register'
				. ' WHERE uid = '.$userid
				. ' AND event = '.$this->_id
				;
		$this->_db->setQuery($query );
		return $this->_db->loadResult();
	}

	/**
	 * Method to get the registered users
	 *
	 * @access	public
	 * @return	object
	 *
	 */
	function getRegisters()
	{
		//avatars should be displayed
		$jemsettings = JEMHelper::config();

		$avatar	= '';
		$join	= '';

		if ($jemsettings->comunoption == 1 && $jemsettings->comunsolution == 1) {
			$avatar = ', c.avatar';
			$join	= ' LEFT JOIN #__comprofiler as c ON c.user_id = r.uid';
		}

		$name = $jemsettings->regname ? 'u.name' : 'u.username';

		//Get registered users
		$query = 'SELECT '.$name.' AS name, r.uid'
				. $avatar
				. ' FROM #__jem_register AS r'
				. ' LEFT JOIN #__users AS u ON u.id = r.uid'
				. $join
				. ' WHERE event = '.$this->_id
				. '   AND waiting = 0 '
				;
		$this->_db->setQuery($query );

		$this->_registers = $this->_db->loadObjectList();

		return $this->_registers;
	}

	/**
	 * Saves the registration to the database
	 *
	 * @access public
	 * @return int register id on success, else false
	 *
	 */
	function userregister()
	{
		$user 		= JFactory::getUser();
		$jemsettings = JEMHelper::config();

		$event 		= (int) $this->_id;
		$uid 		= (int) $user->get('id');
		$onwaiting = 0;

		// Must be logged in
		if ($uid < 1) {
			JError::raiseError(403, JText::_('COM_JEM_ALERTNOTAUTH') );
			return;
		}

		$this->setId($event);

		$event2 = $this->getEvent();

		if ($event2->maxplaces > 0) // there is a max
		{
			// check if the user should go on waiting list
			$attendees = $this->getRegisters();
			if (count($attendees) >= $event2->maxplaces) {
				if (!$event2->waitinglist) {
					$this->setError(JText::_('COM_JEM_ERROR_REGISTER_EVENT_IS_FULL'));
					return false;
				}
				$onwaiting = 1;
			}
		}

		//IP
		$uip = $jemsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';

		$obj = new stdClass();
		$obj->event 	= (int)$event;
		$obj->waiting = $onwaiting;
		$obj->uid   	= (int)$uid;
		$obj->uregdate 	= gmdate('Y-m-d H:i:s');
		$obj->uip   	= $uip;
		$this->_db->insertObject('#__jem_register', $obj);

		return $this->_db->insertid();
	}

	/**
	 * Deletes a registered user
	 *
	 * @access public
	 * @return true on success
	 *
	 */
	function delreguser()
	{
		$user 	= JFactory::getUser();

		$event 	= (int) $this->_id;
		$userid = $user->get('id');

		// Must be logged in
		if ($userid < 1) {
			JError::raiseError(403, JText::_('COM_JEM_ALERTNOTAUTH') );
			return;
		}

		$query = 'DELETE FROM #__jem_register WHERE event = '.$event.' AND uid= '.$userid;
		$this->_db->SetQuery($query );

		if (!$this->_db->query()) {
				JError::raiseError(500, $this->_db->getErrorMsg() );
		}

		return true;
	}
}
?>