<?php
/**
 * @version development 
 * @package Joomla
 * @subpackage JEM
 * @copyright JEM (C) 2013 Joomlaeventmanager.net / EventList (C) 2005 - 2008 Christoph Lukes
 *
 * @license GNU/GPL, see LICENSE.php
 * JEM is based on EventList made by Christoph Lukes from schlu.net
 *
 * JEM can be downloaded from www.joomlaeventmanager.net
 * You can visit the site for support & downloads
 * 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with redEVENT; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * EventList Component Details Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelDetails extends JModelLegacy
{
	/**
	 * Details data in details array
	 *
	 * @var array
	 */
	var $_details = null;


	/**
	 * registeres in array
	 *
	 * @var array
	 */
	var $_registers = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$id = JRequest::getInt('id');
		$this->setId((int)$id);
	}

	/**
	 * Method to set the details id
	 *
	 * @access	public
	 * @param	int	details ID number
	 */

	function setId($id)
	{
		// Set new details ID and wipe data
		$this->_id			= $id;
	}

	/**
	 * Method to get event data for the Detailsview
	 *
	 * @access public
	 * @return array
	 * @since 0.9
	 */
	function &getDetails( )
	{
		/*
		 * Load the Category data
		 */
		if ($this->_loadDetails())
		{
			$user	=  JFactory::getUser();
			
		  if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
          }
            
			// Is the category published?
			if (!$this->_details->published && $this->_details->catid)
			{
				JError::raiseError( 404, JText::_("COM_JEM_CATEGORY_NOT_PUBLISHED") );
			}

			// Do we have access to the category?
			if (($this->_details->cataccess > $gid) && $this->_details->catid)
			{
				return JError::raiseError(403, JText::_('JERROR_ALERTNOAUTHOR'));
			}
			
			
		
		//check session if uservisit already recorded
		$session 	= JFactory::getSession();
		$hitcheck = false;
		if ($session->has('hit', 'eventlist')) {
			$hitcheck 	= $session->get('hit', 0, 'eventlist');
			$hitcheck 	= in_array($this->_details->did, $hitcheck);
		}
		if (!$hitcheck) {
			//record hit
			$this->hit();

			$stamp = array();
			$stamp[] = $this->_details->did;
			$session->set('hit', $stamp, 'eventlist');
		}

		return $this->_details;
	
		
	}
	
	
	}

	/**
	 * Method to load required data
	 *
	 * @access	private
	 * @return	array
	 * @since	0.9
	 */
	function _loadDetails()
	{
		if (empty($this->_details))
		{
			
			// Get the WHERE clause
			$where	= $this->_buildDetailsWhere();

			$query = 'SELECT a.id AS did, a. published, a.dates, a.enddates, a.title, a.times, a.endtimes, '
			    . ' a.datdescription, a.meta_keywords, a.meta_description, a.unregistra, a.locid, a.created_by, '
			    . ' a.datimage, a.registra, a.maxplaces, a.waitinglist, '
					. ' l.id AS locid, l.venue, l.city, l.state, l.url, l.locdescription, l.locimage, l.city, l.plz, l.street, l.country, ct.name AS countryname, l.map, l.created_by AS venueowner, l.latitude, l.longitude,'
					. ' c.access AS cataccess, c.id AS catid, c.published AS catpublished,'
					. ' u.name AS creator_name, u.username AS creator_username,'
					. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
					. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
//					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
					. ' FROM #__jem_events AS a'
					. ' LEFT JOIN #__jem_venues AS l ON a.locid = l.id'
					. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
					. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
					. ' LEFT JOIN #__users AS u ON u.id = a.created_by'
					. ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
					. $where
					. ' GROUP BY a.id '
					;
    		$this->_db->setQuery($query);
			$this->_details = $this->_db->loadObject();
						
			
			$user	=  JFactory::getUser();
			
		  if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
		}
		
			$this->_details->attachments = ELAttach::getAttachments('event'.$this->_details->did, $gid);
			
			return (boolean) $this->_details;
		}
		return true;
	}

	/**
	 * Method to build the WHERE clause of the query to select the details
	 *
	 * @access	private
	 * @return	string	WHERE clause
	 * @since	0.9
	 */
	function _buildDetailsWhere()
	{
		$where = ' WHERE a.id = '.$this->_id;

		return $where;
	}
	
	/**
	 * Method to get the categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.1
	 */
	function getCategories()
	{
		$query = 'SELECT DISTINCT c.id, c.catname,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
		. ' FROM #__jem_categories AS c'
		. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
		. ' WHERE rel.itemid = '.$this->_id
		;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}
	
	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.1
	 */
	function hit()
	{
		if ($this->_id)
		{
			$item =  JTable::getInstance('jem_events', '');
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
	 * @since	1.1
	 */
	function getUserIsRegistered()
	{
		// Initialize variables
		$user 		=  JFactory::getUser();
		$userid		= (int) $user->get('id', 0);

		//usercheck
		$query = 'SELECT waiting+1' // 1 if user is registered, 2 if on waiting list
				. ' FROM #__jem_register'
				. ' WHERE uid = '.$userid
				. ' AND event = '.$this->_id
				;
		$this->_db->setQuery( $query );
		return $this->_db->loadResult();
	}

	/**
	 * Method to get the registered users
	 *
	 * @access	public
	 * @return	object
	 * @since	0.9
	 */
	function getRegisters()
	{
		//avatars should be displayed
		$elsettings = & ELHelper::config();

		$avatar	= '';
		$join	= '';

		if ($elsettings->comunoption == 1 && $elsettings->comunsolution == 1) {
			$avatar = ', c.avatar';
			$join	= ' LEFT JOIN #__comprofiler as c ON c.user_id = r.uid';
		}

		$name = $elsettings->regname ? 'u.name' : 'u.username';

		//Get registered users
		$query = 'SELECT '.$name.' AS name, r.uid'
				. $avatar
				. ' FROM #__jem_register AS r'
				. ' LEFT JOIN #__users AS u ON u.id = r.uid'
				. $join
				. ' WHERE event = '.$this->_id
				. '   AND waiting = 0 '
				;
		$this->_db->setQuery( $query );

		$this->_registers = $this->_db->loadObjectList();

		return $this->_registers;
	}

	/**
	 * Saves the registration to the database
	 *
	 * @access public
	 * @return int register id on success, else false
	 * @since 0.7
	 */
	function userregister()
	{
		$app =  JFactory::getApplication();

		$user 		= & JFactory::getUser();
		$elsettings = & ELHelper::config();
		$tzoffset	= $app->getCfg('offset');

		$event 		= (int) $this->_id;
		$uid 		= (int) $user->get('id');
		$onwaiting = 0;
	
		// Must be logged in
		if ($uid < 1) {
			JError::raiseError( 403, JText::_('COM_JEM_ALERTNOTAUTH') );
			return;
		}
		
		$model = $this->setId($event);
		
		$details = $this->getDetails();
		
		if ($details->maxplaces > 0) // there is a max
		{
			// check if the user should go on waiting list
			$attendees = $this->getRegisters();
			if (count($attendees) >= $details->maxplaces) 
			{
				if (!$details->waitinglist) {
					$this->setError(JText::_('COM_JEM_ERROR_REGISTER_EVENT_IS_FULL'));
					return false;
				}
				$onwaiting = 1;
			}
		}		

		//IP
		$uip 		= $elsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';

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
	 * @since 0.7
	 */
	function delreguser()
	{
		$user 	= & JFactory::getUser();

		$event 	= (int) $this->_id;
		$userid = $user->get('id');

		// Must be logged in
		if ($userid < 1) {
			JError::raiseError( 403, JText::_('COM_JEM_ALERTNOTAUTH') );
			return;
		}

		$query = 'DELETE FROM #__jem_register WHERE event = '.$event.' AND uid= '.$userid;
		$this->_db->SetQuery( $query );

		if (!$this->_db->query()) {
				JError::raiseError( 500, $this->_db->getErrorMsg() );
		}

		return true;
	}
}
?>