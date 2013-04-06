<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * EventList Component Editevent Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelEditevent extends JModelLegacy
{
	/**
	 * Event data in Event array
	 *
	 * @var array
	 */
	var $_event = null;

	/**
	 * Category data in category array
	 *
	 * @var array
	 */
	var $_categories = null;

	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct()
	{
		parent::__construct();

		$id = JRequest::getInt('id');
		$this->setId($id);
	}

	/**
	 * Method to set the event id
	 *
	 * @access	public Event
	 */
	function setId($id)
	{
		// Set new event ID
		$this->_id = $id;
	}

	/**
	 * logic to get the event
	 *
	 * @access public
	 * @since	0.9
	 * 
	 * @return object
	 */
	function &getEvent(  )
	{
		$app =  JFactory::getApplication();

		// Initialize variables
		$user		=  JFactory::getUser();
		$elsettings =  ELHelper::config();

		$view		= JRequest::getWord('view');

		/*
		* If Id exists we will do the edit stuff
		*/
		if ($this->_id) {

			/*
			* Load the Event data
			*/
			$this->_loadEvent();

			/*
			* Error if allready checked out otherwise check event out
			*/
			if ($this->isCheckedOut( $user->get('id') )) {
				$app->redirect( 'index.php?view='.$view, JText::_( 'COM_JEM_THE_EVENT' ).': '.$this->_event->title.' '.JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ) );
			} else {
				$this->checkout( $user->get('id') );
			}

			/*
			* access check
			*/
			$editaccess	= ELUser::editaccess($elsettings->eventowner, $this->_event->created_by, $elsettings->eventeditrec, $elsettings->eventedit);			
			$maintainer = ELUser::ismaintainer();

			if ($maintainer || $editaccess ) $allowedtoeditevent = 1;

			if ($allowedtoeditevent == 0) {

				JError::raiseError( 403, JText::_( 'COM_JEM_NO_ACCESS' ) );

			}

			/*
			* If no Id exists we will do the add event stuff
			*/
		} else {

			//Check if the user has access to the form
			$maintainer = ELUser::ismaintainer();
			$genaccess 	= ELUser::validate_user( $elsettings->evdelrec, $elsettings->delivereventsyes );

			if ( !($maintainer || $genaccess )) {
				JError::raiseError( 403, JText::_( 'COM_JEM_NO_ACCESS' ) );
			}
			
			//sticky forms
			$session = JFactory::getSession();
			if ($session->has('eventform', 'com_jem')) {
				
				$eventform 		= $session->get('eventform', 0, 'com_jem');
				$this->_event 	=  JTable::getInstance('jem_events', '');
								
				if (!$this->_event->bind($eventform)) {
					JError::raiseError( 500, $this->_db->stderr() );
					return false;
				}
				
				$query = 'SELECT venue'
					. ' FROM #__jem_venues'
					. ' WHERE id = '.(int)$eventform['locid']
					;
				$this->_db->setQuery($query);
				$this->_event->venue = $this->_db->loadResult();
								
			} else {

				//prepare output
				$this->_event = new stdClass();
				$this->_event->id					= 0;
				$this->_event->locid				= '';
				$this->_event->dates				= '';
				$this->_event->enddates				= null;
				$this->_event->title				= '';
				$this->_event->times				= null;
				$this->_event->endtimes				= null;
				$this->_event->created				= null;
				$this->_event->author_ip			= null;
				$this->_event->created_by			= null;
				$this->_event->datdescription		= '';
				$this->_event->registra				= 0;
				$this->_event->unregistra			= 0;
				$this->_event->recurrence_number	= 0;
				$this->_event->recurrence_type		= 0;
				$this->_event->recurrence_limit_date= '0000-00-00';
      			$this->_event->recurrence_limit  	= 0;
      			$this->_event->recurrence_byday 	= '';
				$this->_event->sendername			= '';
				$this->_event->sendermail			= '';
				$this->_event->datimage				= '';
				$this->_event->hits					= 0;
				$this->_event->version				= 0;
				$this->_event->ownedvenuesonly				= 0;
				$this->_event->attachments		= array();
				$this->_event->maxplaces		= 0;
				$this->_event->waitinglist	= 0;
				$this->_event->venue				= JText::_('COM_JEM_SELECTVENUE');
			
			}

		}

		return $this->_event;

	}

	/**
	 * logic to get the event
	 *
	 * @access private
	 * @return object
	 */
	function _loadEvent(  )
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_event))
		{
			$query = 'SELECT e.*, v.venue'
					. ' FROM #__jem_events AS e'
					. ' LEFT JOIN #__jem_venues AS v ON v.id = e.locid'
					. ' WHERE e.id = '.(int)$this->_id
					;
			$this->_db->setQuery($query);
			$this->_event = $this->_db->loadObject();
			$this->_event->attachments = ELAttach::getAttachments('event'.$this->_event->id);

			return (boolean) $this->_event;
		}
		return true;
	}

	/**
	 * logic to get the categories
	 *
	 * @access public
	 * @return void
	 */
	function getCategories( )
	{
		$user		=  JFactory::getUser();
		$elsettings =  ELHelper::config();
		$userid		= (int) $user->get('id');
		$superuser	= ELUser::superuser();
		
		if (JFactory::getUser()->authorise('core.manage')) {
           $gid = (int) 3;      //viewlevel Special
           } else {
               if($user->get('id')) {
                   $gid = (int) 2;    //viewlevel Registered
               } else {
                   $gid = (int) 1;    //viewlevel Public
               }
           }
		
		
		
        
		$where = ' WHERE c.published = 1 AND c.access <= '.$gid;
		
		
		//only check for maintainers if we don't have an edit action
		if(!$this->_id) {
			//get the ids of the categories the user maintaines
			$query = 'SELECT g.group_id'
					. ' FROM #__jem_groupmembers AS g'
					. ' WHERE g.member = '.$userid
					;
			$this->_db->setQuery( $query );
			$catids = $this->_db->loadResultArray();

			$categories = implode(' OR c.groupid = ', $catids);

			//build ids query
			if ($categories) {
				//check if user is allowed to submit events in general, if yes allow to submit into categories
				//which aren't assigned to a group. Otherwise restrict submission into maintained categories only 
				if (ELUser::validate_user($elsettings->evdelrec, $elsettings->delivereventsyes)) {
					$where .= ' AND c.groupid = 0 OR c.groupid = '.$categories;
				} else {
					$where .= ' AND c.groupid = '.$categories;
				}
			} else {
				$where .= ' AND c.groupid = 0';
			}

		}

		//administrators or superadministrators have access to all categories, also maintained ones
		if($superuser) {
			$where = ' WHERE c.published = 1';
		}

		//get the maintained categories and the categories whithout any group
		//or just get all if somebody have edit rights
		$query = 'SELECT c.*'
				. ' FROM #__jem_categories AS c'
				. $where
				. ' ORDER BY c.ordering'
				;
		$this->_db->setQuery( $query );

	//	$this->_category = array();
	//	$this->_category[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_SELECT_CATEGORY' ) );
	//	$this->_categories = array_merge( $this->_category, $this->_db->loadObjectList() );
	
		$rows = $this->_db->loadObjectList();
		
		//set depth limit
		$levellimit = 10;
		
		//get children
    	$children = array();  	
    	foreach ($rows as $child) {
        	$parent = $child->parent_id;
       		$list = @$children[$parent] ? $children[$parent] : array();
        	array_push($list, $child);
        	$children[$parent] = $list;
    	}
    	//get list of the items
    	$this->_categories = eventlist_cats::treerecurse(0, '', array(), $children, true, max(0, $levellimit-1));

		return $this->_categories;
	}
	
	/**
	 * Method to get the categories an item is assigned to
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getCatsselected()
	{
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = ' . (int)$this->_id;
		$this->_db->setQuery($query);
		$used = $this->_db->loadResultArray();
		return $used;
	}

	/**
	 * logic to get the venueslist
	 *
	 * @access public
	 * @return array
	 */
	function getVenues( )
	{
		$app =  JFactory::getApplication();
		
		$params 	=  $app->getParams();
		
		$where		= $this->_buildVenuesWhere(  );
		$orderby	= $this->_buildVenuesOrderBy(  );

		$limit			= $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $params->def('display_num', 0), 'int');
		$limitstart		= JRequest::getInt('limitstart');

		$query = 'SELECT l.id, l.venue, l.city, l.country, l.published'
				.' FROM #__jem_venues AS l'
				. $where
				. $orderby
				;

		$this->_db->setQuery( $query, $limitstart, $limit );
		$rows = $this->_db->loadObjectList();

		return $rows;
	}
	
	/**
	 * logic to get the venueslist but limited to the user owned venues
	 *
	 * @access public
	 * @return array
	 */
	function getUserVenues()
	{
        $user = & JFactory::getUser();
        $userid = $user->get('id');

    	$query = 'SELECT id AS value, venue AS text'
        		. ' FROM #__jem_venues'
                . ' WHERE created_by = '. (int)$userid
                . ' AND published = 1'
        		. ' ORDER BY venue'
        ;
        
    	$this->_db->setQuery( $query );

    	$this->_venues = $this->_db->loadObjectList();

    return $this->_venues;
}
	

	/**
	 * Method to build the ordering
	 *
	 * @access private
	 * @return array
	 */
	function _buildVenuesOrderBy( )
	{

		$filter_order		= JRequest::getCmd('filter_order');
		$filter_order_Dir	= JRequest::getCmd('filter_order_Dir');

		$orderby = ' ORDER BY ';

		if ($filter_order && $filter_order_Dir)
		{
			$orderby .= $filter_order.' '.$filter_order_Dir.', ';
		}

		$orderby .= 'l.ordering';

		return $orderby;
	}

	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildVenuesWhere(  )
	{
		$elsettings = & ELHelper::config();
		$filter_type		= JRequest::getInt('filter_type');
		$filter 			= JRequest::getString('filter');
		$filter 			= $this->_db->getEscaped( trim(JString::strtolower( $filter ) ) );

		$where = array();
		
		$where[] = 'l.published = 1';

		if ($filter && $filter_type == 1) {
			$where[] = 'LOWER(l.venue) LIKE "%'.$filter.'%"';
		}

		if ($filter && $filter_type == 2) {
			$where[] = 'LOWER(l.city) LIKE "%'.$filter.'%"';
		}
		
		if ($elsettings->ownedvenuesonly) 
		{
			$user = & JFactory::getUser();
			$userid = $user->get('id');
			$where[] = ' created_by = '. (int)$userid;			
		}

		$where = ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '');

		return $where;
	}

	/**
	 * Method to get the total number
	 *
	 * @access public
	 * @return integer
	 */
	function getCountitems ()
	{
		// Initialize variables
		$where		= $this->_buildVenuesWhere(  );

		$query = 'SELECT count(*)'
				. ' FROM #__jem_venues AS l'
				. $where
				;
		$this->_db->SetQuery($query);

  		return $this->_db->loadResult();
	}

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$item = & $this->getTable('jem_events', '');
			if(! $item->checkin($this->_id)) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return false;
	}

		/**
	 * Method to checkout/lock the item
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function checkout($uid = null)
	{
		if ($this->_id)
		{
			// Make sure we have a user id to checkout the article with
			if (is_null($uid)) {
				$user	= JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$item =  $this->getTable('jem_events', '');
			if(!$item->checkout($uid, $this->_id)) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			return true;
		}
		return false;
	}

	/**
	 * Tests if the event is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	0.9
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadEvent())
		{
			if ($uid) {
				return ($this->_event->checked_out && $this->_event->checked_out != $uid);
			} else {
				return $this->_event->checked_out;
			}
		}
	}

	/**
	 * Method to store the event
	 *
	 * @access	public
	 * @return	id
	 * @since	0.9
	 */
	function store($data, $file)
	{
		$app =  JFactory::getApplication();

		$user 		=  JFactory::getUser();
		$elsettings =  ELHelper::config();
	
		$cats 		= JRequest::getVar( 'cid', array(), 'post', 'array');
		$row 		=  JTable::getInstance('jem_events', '');

		//Sanitize
		$data['datdescription'] = JRequest::getVar( 'datdescription', '', 'post','string', JREQUEST_ALLOWRAW );

		//include the metatags
	/*	if (strlen($data['meta_description']) > 255) {
			$data['meta_description'] = substr($data['meta_description'],0,254);
		}

		if (strlen($data['meta_keywords']) > 200) {
			$data['meta_keywords'] = substr($data['meta_keywords'],0,199);
		}
		*/
		
		//include the metatags
		//$data['meta_description'] = addslashes(htmlspecialchars(trim($elsettings->meta_description)));
		if (strlen($data['meta_description']) > 255) {
			$data['meta_description'] = substr($data['meta_description'],0,254);
		}
		//$data['meta_keywords'] = addslashes(htmlspecialchars(trim($elsettings->meta_keywords)));
		if (strlen($data['meta_keywords']) > 200) {
			$data['meta_keywords'] = substr($data['meta_keywords'],0,199);
		}
		
		

		$curimage = JRequest::getVar( 'curimage', '', 'post','string' );
		
		//bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError( 500, $this->_db->stderr() );
			return false;
		}
		
		//get values from time selectlist and concatenate them accordingly
		$starthours		= JRequest::getCmd( 'starthours');
		$startminutes	= JRequest::getCmd( 'startminutes');
		$endhours		= JRequest::getCmd( 'endhours');
		$endminutes		= JRequest::getCmd( 'endminutes');
		
		$row->times		= $starthours.':'.$startminutes;
		$row->endtimes	= $endhours.':'.$endminutes;
			
		//Are we saving from an item edit?
		if ($row->id) {

			//check if user is allowed to edit events
			$editaccess	= ELUser::editaccess($elsettings->eventowner, $row->created_by, $elsettings->eventeditrec, $elsettings->eventedit);
			$maintainer = ELUser::ismaintainer();

			if ($maintainer || $editaccess ) $allowedtoeditevent = 1;

			if ($allowedtoeditevent == 0) {
				JError::raiseError( 403, JText::_( 'COM_JEM_NO_ACCESS' ) );
			}

			$row->modified 		= gmdate('Y-m-d H:i:s');
			$row->modified_by 	= $user->get('id');

			/*
			* Is editor the owner of the event
			* This extra Check is needed to make it possible
			* that the venue is published after an edit from an owner
			*/
			if ($elsettings->venueowner == 1 && $row->created_by == $user->get('id')) {
				$owneredit = 1;
			} else {
				$owneredit = 0;
			}

		} else {

			//check if user is allowed to submit new events
			$maintainer = ELUser::ismaintainer();
			$genaccess 	= ELUser::validate_user( $elsettings->evdelrec, $elsettings->delivereventsyes );

			if ( !($maintainer || $genaccess) ){
				JError::raiseError( 403, JText::_( 'COM_JEM_NO_ACCESS' ) );
			}

			//get IP, time and userid
			$row->created 		= gmdate('Y-m-d H:i:s');

			$row->author_ip 	= $elsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';
			$row->created_by 	= $user->get('id');

			//Set owneredit to false
			$owneredit = 0;
		}

		/*
		* Autopublish
		* check if the user has the required rank for autopublish
		*/
		$autopubev = ELUser::validate_user( $elsettings->evpubrec, $elsettings->autopubl );
		if ($autopubev || $owneredit) {
				$row->published = 1 ;
			} else {
				$row->published = 0 ;
		}

		//Image upload

		//If image upload is required we will stop here if no file was attached
		if ( empty($file['name']) && $elsettings->imageenabled == 2 ) {

			$this->setError( JText::_( 'COM_JEM_IMAGE_EMPTY' ) );
			return false;
		}
		
		

		if ( ( $elsettings->imageenabled == 2 || $elsettings->imageenabled == 1 ) && ( !empty($file['name'])  ) )  {

			jimport('joomla.filesystem.file');

			$base_Dir 		= JPATH_SITE.'/images/eventlist/events/';

			//check the image
			$check = ELImage::check($file, $elsettings);

			if ($check === false) {
				$app->redirect($_SERVER['HTTP_REFERER']);
			}

			//sanitize the image filename
			$filename = ELImage::sanitize($base_Dir, $file['name']);
			$filepath = $base_Dir . $filename;

			if (!JFile::upload($file['tmp_name'], $filepath)) {
				$this->setError( JText::_( 'COM_JEM_UPLOAD_FAILED' ) );
				return false;
			} else {
				$row->datimage = $filename;
			}
		} else {
			//keep image if edited and left blank
			$row->datimage = $curimage;
		}//end image if

		$editoruser = ELUser::editoruser();

		if (!$editoruser) {
			//check datdescription --> wipe out code
			$row->datdescription = strip_tags($row->datdescription, '<br><br/>');

			//convert the linux \n (Mac \r, Win \r\n) to <br /> linebreaks
			$row->datdescription = str_replace(array("\r\n", "\r", "\n"), "<br />", $row->datdescription);

			// cut too long words
			$row->datdescription = wordwrap($row->datdescription, 75, ' ', 1);

			//check length
			$length = JString::strlen($row->datdescription);
			if ($length > $elsettings->datdesclimit) {
				//too long then shorten datdescription
				$row->datdescription = JString::substr($row->datdescription, 0, $elsettings->datdesclimit);
				//add ...
				$row->datdescription = $row->datdescription.'...';
			}
		}

		//set registration regarding the el settings
		switch ($elsettings->showfroregistra) {
			case 0:
				$row->registra = 0;
			break;

			case 1:
				$row->registra = 1;
			break;

			case 2:
				$row->registra =  $row->registra ;
			break;
		}

		switch ($elsettings->showfrounregistra) {
			case 0:
				$row->unregistra = 0;
			break;

			case 1:
				$row->unregistra = 1;
			break;

			case 2:
				if ($elsettings->showfroregistra >= 1) {
					$row->unregistra = $row->unregistra;
				} else {
					$row->unregistra = 0;
				}
			break;
		}

		//Make sure the table is valid
		if (!$row->check($elsettings)) {
			$this->setError($row->getError());
			return false;
		}
		
		$row->version++;

		//store it in the db
		if (!$row->store(true)) {
			JError::raiseError( 500, $this->_db->stderr() );
			return false;
		}
		
		//store cat relation
		$query = 'DELETE FROM #__jem_cats_event_relations WHERE itemid = '.$row->id;
		$this->_db->setQuery($query);
		$this->_db->query();
			
		foreach($cats as $cat)
		{
			$query = 'INSERT INTO #__jem_cats_event_relations (`catid`, `itemid`) VALUES(' . $cat . ',' . $row->id . ')';
			$this->_db->setQuery($query);
			$this->_db->query();
		}
	
		// attachments
		// new ones first
		$attachments = JRequest::getVar( 'attach', array(), 'files', 'array' );
		$attachments['customname'] = JRequest::getVar( 'attach-name', array(), 'post', 'array' );
		$attachments['description'] = JRequest::getVar( 'attach-desc', array(), 'post', 'array' );
		$attachments['access'] = JRequest::getVar( 'attach-access', array(), 'post', 'array' );
		ELAttach::postUpload($attachments, 'event'.$row->id);
		
		// and update old ones
		$attachments = array();
		$old['id'] = JRequest::getVar( 'attached-id', array(), 'post', 'array' );
		$old['name'] = JRequest::getVar( 'attached-name', array(), 'post', 'array' );
		$old['description'] = JRequest::getVar( 'attached-desc', array(), 'post', 'array' );
		$old['access'] = JRequest::getVar( 'attached-access', array(), 'post', 'array' );
		foreach ($old['id'] as $k => $id)
		{
			$attach = array();
			$attach['id'] = $id;
			$attach['name'] = $old['name'][$k];
			$attach['description'] = $old['description'][$k];
			$attach['access'] = $old['access'][$k];
			ELAttach::update($attach);
		}
		
		return $row->id;
	}
}
?>