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
 * EventList Component Events Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelEvents extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();

		$limit		= $app->getUserStateFromRequest( 'com_eventlist.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_eventlist.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

	}

	/**
	 * Method to get event item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			if ($this->_data)
			{
				$this->_data = $this->_additionals($this->_data);
				$this->_data = $this->_getAttendeesNumbers($this->_data);
				
				$k = 0;
				$count = count($this->_data);
				for($i = 0; $i < $count; $i++)
				{
					$item =& $this->_data[$i];
					$item->categories = $this->getCategories($item->id);
					
					$k = 1 - $k;
				}
			}
		}

		return $this->_data;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();

		$query = 'SELECT a.*, loc.venue, loc.city, loc.checked_out AS vchecked_out, u.email, u.name AS author'
					. ' FROM #__eventlist_events AS a'
					. ' LEFT JOIN #__eventlist_venues AS loc ON loc.id = a.locid'
					. ' LEFT JOIN #__users AS u ON u.id = a.created_by'
					. $where
					. $orderby
					;

		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentOrderBy()
	{
		$app =  JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest( 'com_eventlist.events.filter_order', 'filter_order', 'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_eventlist.events.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		
		if ($filter_order != '')
		{
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		}
		else
		{
			$orderby = ' ORDER BY a.dates, a.times ';
		}

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentWhere()
	{
		$app =  JFactory::getApplication();

		$filter_state 		= $app->getUserStateFromRequest( 'com_eventlist.filter_state', 'filter_state', '', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_eventlist.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_eventlist.search', 'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );

		$where = array();

		if ($filter_state) {
			if ($filter_state == 'P') {
				$where[] = 'a.published = 1';
			} else if ($filter_state == 'U') {
				$where[] = 'a.published = 0';
			} else {
				$where[] = 'a.published >= 0';
			}
		} else {
			$where[] = 'a.published >= 0';
		}

		if ($search && $filter == 1) {
			$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
		}

		if ($search && $filter == 2) {
			$where[] = ' LOWER(loc.venue) LIKE \'%'.$search.'%\' ';
		}

		if ($search && $filter == 3) {
			$where[] = ' LOWER(loc.city) LIKE \'%'.$search.'%\' ';
		}
/*
		if ($search && $filter == 4) {
			$where[] = ' LOWER(cat.catname) LIKE \'%'.$search.'%\' ';
		}
*/
		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}

	/**
	 * Get the editor name and the nr of attendees
	 *
	 * @access private
	 * @param array $rows
	 * @return array
	 */
	function _additionals($rows)
	{
		for ($i=0, $n=count($rows); $i < $n; $i++) {

			// Get editor name
			$query = 'SELECT name'
					. ' FROM #__users'
					. ' WHERE id = '.$rows[$i]->modified_by
					;
			$this->_db->SetQuery( $query );

			$rows[$i]->editor = $this->_db->loadResult();
		}

		return $rows;
	}

	/**
	 * Method to (un)publish a event
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user 	=& JFactory::getUser();
		$userid = (int) $user->get('id');

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__eventlist_events'
				. ' SET published = '. (int) $publish
				. ' WHERE id IN ('. $cids .')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' .$userid. ' ) )'
			;

			$this->_db->setQuery( $query );

			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
	}

	/**
	 * Method to remove a event
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function delete($cid = array())
	{
		//$result = false;

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__eventlist_events'
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			//remove assigned category references
			$query = 'DELETE FROM #__eventlist_cats_event_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		return true;
	}
	
	function getCategories($id)
	{
		$query = 'SELECT DISTINCT c.id, c.catname, c.checked_out AS cchecked_out'
				. ' FROM #__eventlist_categories AS c'
				. ' LEFT JOIN #__eventlist_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				;
	
		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();
		
		$k = 0;
		$count = count($this->_cats);
		for($i = 0; $i < $count; $i++)
		{
			$item =& $this->_cats[$i];
			$cats = new eventlist_cats($item->id);
			$item->parentcats = $cats->getParentlist();
				
			$k = 1 - $k;
		}
		
		return $this->_cats;
	}
	
	function clearrecurrences()
	{
		$query = ' UPDATE #__eventlist_events '
		       . ' SET recurrence_number = 0,'
		       . ' recurrence_type = 0,'
		       . ' recurrence_counter = 0,'
		       . ' recurrence_limit = 0,'
		       . ' recurrence_limit_date = NULL,'
		       . ' recurrence_byday = ""'
		            ;
		$this->_db->setQuery($query);
		return ($this->_db->query());
	}
	
	/**
	 * adds attendees numbers to rows
	 * 
	 * @param $data reference to event rows
	 * @return bool true on success
	 */
	function _getAttendeesNumbers(& $data)
	{
		if (!is_array($data) || !count($data)) {
			return true;
		}
		// get the ids of events
		$ids = array();
		foreach ($data as $event) {
			$ids[] = $event->id;
		}
		$ids = implode(",", $ids);
		
		$query = ' SELECT COUNT(id) as total, SUM(waiting) as waitinglist, event ' 
		       . ' FROM #__eventlist_register ' 
		       . ' WHERE event IN (' . $ids .')'
		       . ' GROUP BY event '
		       ;
		$this->_db->setQuery($query);
		$res = $this->_db->loadObjectList('event');
		
		foreach ($data as $k => $event) 
		{
			if (isset($res[$event->id]))
			{
				$data[$k]->waiting   = $res[$event->id]->waitinglist;
				$data[$k]->regCount  = $res[$event->id]->total - $res[$event->id]->waitinglist;
			}
			else
			{
				$data[$k]->waiting   = 0;
				$data[$k]->regCount  = 0;
			}
			$data[$k]->available = $data[$k]->maxplaces - $data[$k]->regCount;
		}
		return $data;
	}
}
?>