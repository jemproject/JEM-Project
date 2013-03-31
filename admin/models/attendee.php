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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * EventList Component attendee Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		1.1
 */
class EventListModelAttendee extends JModel
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
			$query = 'SELECT r.*, u.username '
					. ' FROM #__eventlist_register AS r '
					. ' LEFT JOIN #__users AS u ON u.id = r.uid '
					. ' WHERE r.id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_data = $this->_db->loadObject();

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
			$data = JTable::getInstance('eventlist_register', '');
			$this->_data = $data;			
		}
		return true;
	}

	function toggle()
	{
		$attendee = $this->getData();
		
		if (!$attendee->id) {
			$this->setError('COM_EVENTLIST_MISSING_ATTENDEE_ID');
			return false;
		}
		
		$row = JTable::getInstance('eventlist_register', '');
		$row->bind($attendee);
		$row->waiting = $attendee->waiting ? 0 : 1;
		return $row->store();		
	}
	
/**
	 * Method to store the attendee
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.1
	 */
	function store($data)
	{
		$user		= & JFactory::getUser();
		$config 	= & JFactory::getConfig();
		$eventid = $data['event']; 

		$row  =& $this->getTable('eventlist_register', '');

		// bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}

		// sanitise id field
		$row->id = (int) $row->id;

		$nullDate	= $this->_db->getNullDate();

		// Are we saving from an item edit?
		if ($row->id) {
			
		} else {
			$row->uregdate 		= gmdate('Y-m-d H:i:s');
			
			$query = ' SELECT e.maxplaces, e.waitinglist, COUNT(r.id) as booked ' 
			       . ' FROM #__eventlist_events AS e '
			       . ' INNER JOIN #__eventlist_register AS r ON r.event = e.id ' 
			       . ' WHERE e.id = ' . $this->_db->Quote($eventid)
			       . '   AND r.waiting = 0 '
			       . ' GROUP BY e.id ';
			$this->_db->setQuery($query);
			$details = $this->_db->loadObject();
			
			// put on waiting list ?
			if ($details->maxplaces > 0) // there is a max
			{
				// check if the user should go on waiting list
				if ($details->booked >= $details->maxplaces) 
				{
					if (!$details->waitinglist) {
						JError::raiseWarning(0, JText::_('COM_EVENTLIST_ERROR_REGISTER_EVENT_IS_FULL'));
						return false;
					}
					$row->waiting = 1;
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
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}
		
		return $row;
	}
}
?>