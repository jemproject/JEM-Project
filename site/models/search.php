<?php
/**
 * @version 1.9 $Id$
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
 * JEM Component search Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelSearch extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * the query
	 */
	var $_query = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();
		$jemsettings =  JEMHelper::config();

		// Get the paramaters of the active menu item
		$params 	=  $app->getParams('com_jem');

		//get the number of events from database
		$limit       	= $app->getUserStateFromRequest('com_jem.search.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart		= JRequest::getVar('limitstart', 0, '', 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Get the filter request variables
		$this->setState('filter_order', JRequest::getCmd('filter_order', 'a.dates'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
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
			$query = $this->_buildQuery();

			if ($pop) {
				$this->_data = $this->_getList( $query );
			} else {
				$pagination = $this->getPagination();
				$this->_data = $this->_getList( $query, $pagination->limitstart, $pagination->limit );
			}

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
		if (empty($this->_query))
		{
			// Get the WHERE and ORDER BY clauses for the query
			$where		= $this->_buildWhere();
			$orderby	= $this->_buildOrderBy();

			//Get Events from Database
			$this->_query = 'SELECT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription,'
					. ' l.venue, l.city, l.state, l.url,'
					. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
					. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
					. ' FROM #__jem_events AS a'
	        . ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
					. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
          . ' LEFT JOIN #__jem_countries AS c ON c.iso2 = l.country'
					. $where
					. ' GROUP BY a.id '
					. $orderby
					;
		}
		return $this->_query;
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

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir.', a.dates, a.times';

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

		$top_category = $params->get('top_category', 0);

		$task 		= JRequest::getWord('task');

		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where = ' WHERE a.published = -1';
		} else {
			$where = ' WHERE a.published = 1';
		}

		$filter            = JRequest::getString('filter', '', 'request');
		$filter_type       = JRequest::getWord('filter_type', '', 'request');
		$filter_continent  = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');
		$filter_country    = $app->getUserStateFromRequest('com_jem.search.filter_country', 'filter_country', '', 'string');
		$filter_city       = $app->getUserStateFromRequest('com_jem.search.filter_city', 'filter_city', '', 'string');
		$filter_date_from  = $app->getUserStateFromRequest('com_jem.search.filter_date_from', 'filter_date_from', '', 'string');
		$filter_date_to    = $app->getUserStateFromRequest('com_jem.search.filter_date_to', 'filter_date_to', '', 'string');
		$filter_category   = $app->getUserStateFromRequest('com_jem.search.filter_category', 'filter_category', 0, 'int');
		$filter_category = ($filter_category ? $filter_category : $top_category);

		// no result if no filter:
		if ( !($filter || $filter_continent || $filter_country || $filter_city || $filter_date_from || $filter_date_to || $filter_category != $top_category) ) {
			return ' WHERE 0 ';
		}

		if ($filter)
		{
			// clean filter variables
			$filter 		= JString::strtolower($filter);
			$filter			= $this->_db->Quote( '%'.$this->_db->escape( $filter, true ).'%', false );
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
			}
		}

		// filter date
		$nulldate = '0000-00-00';
		if ($params->get('date_filter_type', 0) == 1) // match on all events dates (between start and end)
		{
			if ($filter_date_from && strtotime($filter_date_from))
			{
				$filter_date_from = $this->_db->Quote(strftime('%Y-%m-%d', strtotime($filter_date_from)));
				$where .= ' AND DATEDIFF(IF (a.enddates IS NOT NULL AND a.enddates <> '. $this->_db->Quote($nulldate) .', a.enddates, a.dates), '. $filter_date_from .') >= 0';
			}
			if ($filter_date_to && strtotime($filter_date_to))
			{
				$filter_date_to = $this->_db->Quote(strftime('%Y-%m-%d', strtotime($filter_date_to)));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
			}
		}
		else // match only on start date
		{
			if ($filter_date_from && strtotime($filter_date_from))
			{
				$filter_date_from = $this->_db->Quote(strftime('%Y-%m-%d', strtotime($filter_date_from)));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_from .') >= 0';
			}
			if ($filter_date_to && strtotime($filter_date_to))
			{
				$filter_date_to = $this->_db->Quote(strftime('%Y-%m-%d', strtotime($filter_date_to)));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
			}
		}
		// filter country
		if ($filter_continent) {
			$where .= ' AND c.continent = ' . $this->_db->Quote($filter_continent);
		}
		// filter country
		if ($filter_country) {
			$where .= ' AND l.country = ' . $this->_db->Quote($filter_country);
		}
		// filter city
		if ($filter_country && $filter_city) {
			$where .= ' AND l.city = ' . $this->_db->Quote($filter_city);
		}
		// filter category
		if ($filter_category) {
			$cats = JEMCategories::getChilds((int) $filter_category);
			$where .= ' AND rel.catid IN (' . implode(', ', $cats) .')';
		}

		return $where;
	}

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

	function getCategories($id)
	{
		$user = JFactory::getUser();
		$gid = JEMHelper::getGID($user);

		$query = 'SELECT c.id, c.catname, c.access, c.ordering, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access  <= '.$gid;
				;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}

  	function getCountryOptions()
  	{
    	$app =  JFactory::getApplication();

  		$filter_continent = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');

			$query = ' SELECT c.iso2 as value, c.name as text '
         		  . ' FROM #__jem_events AS a'
         		  . ' INNER JOIN #__jem_venues AS l ON l.id = a.locid'
         		  . ' INNER JOIN #__jem_countries as c ON c.iso2 = l.country '
          		;

    	if ($filter_continent) {
      		$query .= ' WHERE c.continent = ' . $this->_db->Quote($filter_continent);
    	}
    	$query .= ' GROUP BY c.iso2 ';
    	$query .= ' ORDER BY c.name ';
    	$this->_db->setQuery($query);

    	return $this->_db->loadObjectList();
  	}

	function getCityOptions()
	{
		if (!$country = JRequest::getString('filter_country', '', 'request')) {
			return array();
		}
		$query = ' SELECT DISTINCT l.city as value, l.city as text '
		       . ' FROM #__jem_events AS a'
           . ' INNER JOIN #__jem_venues AS l ON l.id = a.locid'
           . ' INNER JOIN #__jem_countries as c ON c.iso2 = l.country '
           . ' WHERE l.country = ' . $this->_db->Quote($country)
           . ' ORDER BY l.city ';

			$this->_db->setQuery($query);
    		return $this->_db->loadObjectList();
	}


  	/**
  	 * logic to get the categories
  	 *
 	  * @access public
 	  * @return void
 	  */
  	function getCategoryTree()
  	{
		$app =  JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params 	=  $app->getParams('com_jem');
		$top_id = $params->get('top_category', 0);

		$user = JFactory::getUser();
		$jemsettings = JEMHelper::config();
		$userid = (int) $user->get('id');
		$gid = JEMHelper::getGID($user);


    	$superuser  = JEMUser::superuser();

	    $where = ' WHERE c.published = 1 AND c.access <= '.$gid;

    	//get the maintained categories and the categories whithout any group
    	//or just get all if somebody have edit rights
    	$query = 'SELECT c.*'
    	    . ' FROM #__jem_categories AS c'
    	    . $where
        	. ' ORDER BY c.ordering'
        	;
    	$this->_db->setQuery( $query );
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
    	return JEMCategories::treerecurse($top_id, '', array(), $children, true, max(0, $levellimit-1));
  	}
}
?>