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
 * EventList Component Category Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelCategory extends JModelLegacy
{
	/**
	 * Category id
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
	 * Groups data array
	 *
	 * @var array
	 */
	var $_groups = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  0, '', 'array');
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
		$this->_id	    = $id;
		$this->_data	= null;
	}

	/**
	 * Method to get content category data
	 *
	 * @access	public
	 * @return	array
	 * @since	0.9
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
	 * Method to get the group data
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function &getGroups()
	{
		$query = 'SELECT id AS value, name AS text'
			. ' FROM #__jem_groups'
			. ' ORDER BY name'
			;
		$this->_db->setQuery( $query );

		$this->_groups = $this->_db->loadObjectList();

		return $this->_groups;
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
			$query = 'SELECT *'
					. ' FROM #__jem_categories'
					. ' WHERE id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_data = $this->_db->loadObject();
			
			if ($this->_data) {
				$files = ELAttach::getAttachments('category'.$this->_data->id);
				$this->_data->attachments = $files;
			}

			return (boolean) $this->_data;
		}
		return true;
	}

	/**
	 * Method to initialise the category data
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
			//sticky forms
			$session = JFactory::getSession();
			if ($session->has('categoryform', 'com_jem')) {
				
				$categoryform 	= $session->get('categoryform', 0, 'com_jem');
				$category 		= & JTable::getInstance('jem_categories', '');
				
				if (!$category->bind($categoryform)) {
					JError::raiseError( 500, $this->_db->stderr() );
					return false;
				}
				$this->_data				= $category;
				return (boolean) $this->_data;
				
			} else {
				$category = new stdClass();
				$category->id					= 0;
				$category->parent_id			= 0;
				$category->groupid				= 0;
				$category->catname				= null;
				$category->alias				= null;
				$category->catdescription		= null;
				$category->meta_description		= null;
				$category->meta_keywords		= null;
				$category->published			= 1;
				$category->image				= JText::_('COM_JEM_SELECTIMAGE');
      			$category->color        		= '';
				$category->access				= 0;
				$category->attachments	= array();
				$this->_data					= $category;
				return (boolean) $this->_data;
			}
		}
		return true;
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
			$category = & JTable::getInstance('jem_categories', '');
			return $category->checkin($this->_id);
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
			// Make sure we have a user id to checkout the group with
			if (is_null($uid)) {
				$user	= JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$category =  JTable::getInstance('jem_categories', '');
			return $category->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Tests if the category is checked out
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
			JError::raiseWarning( 0, 'Unable to Load Data');
			return false;
		}
	}

	/**
	 * Method to store the category
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function store($data)
	{
		$row  = $this->getTable('jem_categories', '');

		// bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}

		
		// Check if image was selected
		jimport('joomla.filesystem.file');
		$format 	= JFile::getExt(JPATH_SITE.'/images/eventlist/categories/'.$row->image);

		$allowable 	= array ('gif', 'jpg', 'png');
		if (in_array($format, $allowable)) {
			$row->image = $row->image;
		} else {
			$row->image = '';
		}
		
		
		if (!$row->id) {
			$row->ordering = $row->getNextOrder();
		}

		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}

		// Store it in the db
		if (!$row->store()) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}
	
		// attachments
		// new ones first
		$attachments = JRequest::getVar( 'attach', array(), 'files', 'array' );
		$attachments['customname'] = JRequest::getVar( 'attach-name', array(), 'post', 'array' );
		$attachments['description'] = JRequest::getVar( 'attach-desc', array(), 'post', 'array' );
		$attachments['access'] = JRequest::getVar( 'attach-access', array(), 'post', 'array' );
		ELAttach::postUpload($attachments, 'category'.$row->id);
		
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