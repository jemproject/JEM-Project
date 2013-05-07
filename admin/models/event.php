<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Event Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelEvent extends JModelLegacy
{
	/**
	 * Event id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Event data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$cid = JRequest::getVar( 'cid', array(0), '', 'array' );
		JArrayHelper::toInteger($cid, array(0));
		$this->setId($cid[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int event identifier
	 */
	function setId($id)
	{
		// Set event id and wipe data
		$this->_id	    = $id;
		$this->_data	= null;
	}

	/**
	 * Logic for the event edit screen
	 *
	 */
	function &getData()
	{
		if ($this->_loadData())
		{

		}
		else  $this->_initData();

		return $this->_data;
	}

	/**
	 * Method to load content event data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function _loadData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = 'SELECT e.*, v.venue, con.name AS contactname'
					. ' FROM #__jem_events AS e'
					. ' LEFT JOIN #__jem_venues AS v ON v.id = e.locid'
					. ' LEFT JOIN #__contact_details AS con ON con.id = e.contactid'
					. ' WHERE e.id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_data = $this->_db->loadObject();

			if ($this->_data)
			{
				$this->_getAttachments();
				$this->_getBooked();
			}
			return (boolean) $this->_data;
		}
		return true;
	}

	/**
	 * Method to initialise the event data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function _initData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
            $createdate =  JFactory::getDate();
            $user =  JFactory::getUser();

            $event = new stdClass();
            $event->id					= 0;
            $event->locid				= 0;
            //$event->categories			= '';
            $event->dates				= null;
            $event->enddates			= null;
            $event->times				= null;
            $event->endtimes			= null;
            $event->title				= '';
            $event->alias				= '';
            $event->created				= $createdate->toUnix();
            $event->author_ip			= '';
            $event->created_by			= $user->get('id');
            $event->published			= 1;
            $event->registra			= 0;
            $event->unregistra			= 0;
            $event->maxplaces			= 0;
            $event->booked				= 0;
            $event->waitinglist			= 0;
            $event->datdescription		= '';
            $event->meta_keywords		= '';
            $event->meta_description	= '';
            $event->recurrence_number	= 0;
            $event->recurrence_type		= 0;
            $event->recurrence_limit_date	= '0000-00-00';
            $event->recurrence_limit 	= 0;
            $event->recurrence_counter 	= 0;
            $event->recurrence_byday 	= '';
            $event->datimage			= '';
            $event->venue				= JText::_('COM_JEM_SELECTVENUE');
            $event->hits				= 0;
            $event->contactname			= null;
            $event->contactid			= null;
            $event->version				= 0;
            $event->modified			= $this->_db->getNullDate();
            $event->attachments			= array();
            $this->_data				= $event;
            return (boolean) $this->_data;
		}
		return true;
	}

	/**
	 * load attachements to $_data
	 * requires _data !
	 */
	function _getAttachments()
	{
		$files = JEMAttachment::getAttachments('event'.$this->_data->id);
		$this->_data->attachments = $files;
	}

	function _getBooked()
	{
		$query = ' SELECT count(id) '
		       . ' FROM #__jem_register '
		       . ' WHERE event = ' . $this->_db->Quote($this->_data->id)
		       . '   AND waiting = 0 '
		       ;
		$this->_db->setQuery($query);
		$res = $this->_db->loadResult();
		$this->_data->booked = $res;
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
			$event =  JTable::getInstance('jem_events', '');
			return $event->checkin($this->_id);
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
			// Make sure we have a user id to checkout the event with
			if (is_null($uid)) {
				$user	= JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$event =  JTable::getInstance('jem_events', '');
			return $event->checkout($uid, $this->_id);
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
		if ($this->_loadData())
		{
			if ($uid) {
				return ($this->_data->checked_out && $this->_data->checked_out != $uid);
			} else {
				return $this->_data->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, JText::_('COM_JEM_UNABLE_TO_LOAD_DATA'));
			return false;
		}
	}

	/**
	 * Method to store the event
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function store($data)
	{
		//$app = JFactory::getApplication();

		$jemsettings = JEMAdmin::config();
		$user		=  JFactory::getUser();

		$cats 		= JRequest::getVar( 'cid', array(), 'post', 'array');

		$row = JTable::getInstance('jem_events', '');

		// Bind the form fields to the table
		if (!$row->bind($data)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		//get values from time selectlist and concatenate them accordingly
		$starthours		= JRequest::getCmd( 'starthours');
		$startminutes	= JRequest::getCmd( 'startminutes');
		$endhours		= JRequest::getCmd( 'endhours');
		$endminutes		= JRequest::getCmd( 'endminutes');

		$row->times		= $starthours.':'.$startminutes;
		$row->endtimes	= $endhours.':'.$endminutes;

		// Check the metatags
		if (JString::strlen($row->meta_description) > 255) {
			$row->meta_description = JString::substr($row->meta_description, 0, 254);
		}

		if (JString::strlen($row->meta_keywords) > 200) {
			$row->meta_keywords = JString::substr($row->meta_keywords, 0, 199);
		}

		//Check if image was selected
		jimport('joomla.filesystem.file');
		$format 	= JFile::getExt('JPATH_SITE/images/jem/events/'.$row->datimage);

		$allowable 	= array ('gif', 'jpg', 'png');
		if (in_array($format, $allowable)) {
			$row->datimage = $row->datimage;
		} else {
			$row->datimage = '';
		}

		// sanitise id field
		$row->id = (int) $row->id;

		$nullDate	= $this->_db->getNullDate();

		// Are we saving from an item edit?
		if ($row->id) {
			$row->modified 		= gmdate('Y-m-d H:i:s');
			$row->modified_by 	= $user->get('id');
		} else {
			$row->modified 		= $nullDate;
			$row->modified_by 	= '';

			//get IP, time and userid
			$row->created 			= gmdate('Y-m-d H:i:s');

			$row->author_ip 		= $jemsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';
		}

		$row->version++;

		// Make sure the data is valid
		if (!$row->check($jemsettings)) {
			$this->setError($row->getError());
			return false;
		}

		// Store the table to the database
		if (!$row->store(true)) {
			$this->setError($this->_db->getErrorMsg());
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
		JEMAttachment::postUpload($attachments, 'event'.$row->id);

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
			JEMAttachment::update($attach);
		}

		// check for recurrence, when filled it will perform the cleanup function
		if ($row->recurrence_number > 0)
		{
			JEMHelper::cleanup(1);
		}
		
		
		return $row->id;
	}

	/**
	 * Fetch event hits
	 *
	 * @param int $id
	 * @return int
	 */
	function gethits($id)
	{
		$query = 'SELECT hits FROM #__jem_events WHERE id = '.(int)$id;
		$this->_db->setQuery($query);
		$hits = $this->_db->loadResult();

		return $hits;
	}

	/**
	 * Reset hitcount
	 *
	 * @param int $id
	 * @return int
	 */
	function resetHits($id)
	{
		$row  = $this->getTable('jem_events', '');
		$row->load($id);
		$row->hits = 0;
		$row->store();
		$row->checkin();
		return $row->id;
	}

	/**
	 * Get assigned cats
	 *
	 * @return array
	 */
	function getCatsselected()
	{
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = ' . (int)$this->_id;
		$this->_db->setQuery($query);
		$used = $this->_db->loadColumn();
		return $used;
	}
}
?>