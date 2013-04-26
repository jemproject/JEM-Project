<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
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

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component JEM Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelEventslist extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

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

		// Get the paramaters of the active menu item
		$params 	=  $app->getParams('com_jem');

		//get the number of events from database
		$limit       	= $app->getUserStateFromRequest('com_jem.jem.limit', 'limit', $params->def('display_num', 0), 'int');
		$limitstart		= JRequest::getVar('limitstart', 0, '', 'int');
			
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Get the filter request variables
		$this->setState('filter_order', JRequest::getCmd('filter_order', 'a.dates'));
		$this->setState('filter_order_dir', JRequest::getWord('filter_order_Dir', 'ASC'));
	}

	/**
	 * set limit
	 * @param int value
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}
	
	/**
	 * Method to get the Events
	 *
	 * @access public
	 * @return array
	 */
	function &getData( )
	{
		$pop	= JRequest::getBool('pop');

		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query 		= $this->_buildQuery();
			$pagination = $this->getPagination();

			if ($pop) {
				$this->_data = $this->_getList( $query );
			} else {
				$this->_data = $this->_getList( $query, $pagination->limitstart, $pagination->limit );
			}

			$this->_data = $this->_getAttendeesNumbers($this->_data);
			
			$k = 0;
			$count = count($this->_data);
			for($i = 0; $i < $count; $i++)
			{
				$item = $this->_data[$i];
				$item->categories = $this->getCategories($item->id);
				
				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_data[$i]);
				} 
				
				$k = 1 - $k;
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
		$where		= $this->_buildWhere();
		$orderby	= $this->_buildOrderBy();

		// Get Events from Database ...
		$query = ' SELECT a.id, a.dates, a.datimage, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription, a.maxplaces, a.waitinglist, '
		       . ' l.venue, l.city, l.state, l.url, l.street, ct.name AS countryname, '
		      		 . ' c.catname, c.id AS catid,'
		       . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
		       . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
		       . ' FROM #__jem_events AS a'
		       . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
		       . ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
		       		. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
					. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
		       . $where
		       . ' GROUP BY a.id'
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
	
	   function _buildOrderBy()
	{
		$filter_order		= $this->getState('filter_order');
		$filter_order_dir	= $this->getState('filter_order_dir');
		
		
		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_dir	= JFilterInput::getinstance()->clean($filter_order_dir, 'word');
		
		
		
		if ($filter_order != '')
		{
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_dir;
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
	function _buildWhere()
	{
		$app =  JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params 	=  $app->getParams();
        $jemsettings =  JEMHelper::config();
		
        $task 		= JRequest::getWord('task');
		
		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where = ' WHERE a.published = -1';
		} else {
			$where = ' WHERE a.published = 1';
		}

		
		// get excluded categories
		$excluded_cats = trim( $params->get( 'excluded_cats', '' ) );
		
		if ($excluded_cats != '' )  {
			$cats_excluded    = explode( ',', $excluded_cats );
			$where .= ' AND ( c.id!=' . implode( ' AND c.id!=', $cats_excluded ) . ')';
		}
		// === END Exlucded categories add === //
		
		
		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ($jemsettings->filter)
		{
			$filter 		= JRequest::getString('filter', '', 'request');
			$filter_type 	= JRequest::getWord('filter_type', '', 'request');

			if ($filter)
			{
				// clean filter variables
				$filter 		= JString::strtolower($filter);
				$filter			= $this->_db->Quote( '%'.$this->_db->getEscaped( $filter, true ).'%', false );
				$filter_type 	= JString::strtolower($filter_type);

				switch ($filter_type)
				{
					case 'title' :
						$where .= ' AND LOWER( a.title ) LIKE '.$filter;
						break;

					case 'venue' :
						$where .= ' AND LOWER( l.venue ) LIKE '.$filter;
						break;

					case 'city' :
						$where .= ' AND LOWER( l.city ) LIKE '.$filter;
						break;
						
					case 'type':
                        $where .= ' AND LOWER( c.catname ) LIKE '.$filter;
                        break;
                        
                    case 'state':
                        $where .= ' AND LOWER( l.state ) LIKE '.$filter;
                        break;    
                        
                        
				}
			}
		}
		return $where;
		
	}
	
	/**
	 * adds attendees numbers to rows
	 * 
	 * @param $data reference to event rows
	 * @return bool true on success
	 */
	function _getAttendeesNumbers(& $data)
	{
		if (!is_array($data)) {
			return true;
		}
		// get the ids of events
		$ids = array();
		foreach ($data as $event) {
			$ids[] = $event->id;
		}
		$ids = implode(",", $ids);
		
		$query = ' SELECT COUNT(id) as total, SUM(waiting) as waitinglist, event ' 
		       . ' FROM #__jem_register ' 
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
				$data[$k]->attendees  = $res[$event->id]->total - $res[$event->id]->waitinglist;
			}
			else
			{
				$data[$k]->waiting   = 0;
				$data[$k]->attendees  = 0;
			}
			$data[$k]->available = $data[$k]->maxplaces - $data[$k]->attendees;
		}
		return $data;
	}
	
	
	
	function getCategories($id)
	{
		$user		=  JFactory::getUser();
		
		$where		= $this->_buildWhere2();
		
		if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
          }
		
		
		
		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access  <= '.$gid
				. $where
				;

			
		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}
	
	
	
	function _buildWhere2()
	{
		$app =  JFactory::getApplication();
	
		// Get the paramaters of the active menu item
		$params 	=  $app->getParams();
		$jemsettings =  JEMHelper::config();
		
		
	
	// get excluded categories
	$excluded_cats = trim( $params->get( 'excluded_cats', '' ) );
	
	if ($excluded_cats != '' )  {
		$cats_excluded    = explode( ',', $excluded_cats );
		$where = ' AND ( c.id!=' . implode( ' AND c.id!=', $cats_excluded ) . ')';
	}
	// === END Exlucded categories add === //
	else
	{
		$where = '';	
	}
	
	
	return $where;
	
	}
	
	
	
	
	
	
	
	
}
?>