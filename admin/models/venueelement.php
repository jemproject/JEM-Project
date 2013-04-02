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
 * EventList Component Venueelement Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelVenueelement extends JModelLegacy
{
	/**
	 * Category data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Category total
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
	 * Categorie id
	 *
	 * @var int
	 */
	var $_id = null;

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
		$limitstart = $app->getUserStateFromRequest( 'com_eventlist.limitstart', 'limitstart', 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get categories item data
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
		}

		return $this->_data;
	}

	/**
	 * Total nr of venues
	 *
	 * @access public
	 * @return integer
	 * @since 0.9
	 */
	function getTotal()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the venues
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
	 * Method to build the query for the venues
	 *
	 * @access private
	 * @return string
	 * @since 0.9
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();

		$query = 'SELECT l.*'
				. ' FROM #__eventlist_venues AS l'
				. $where
				. $orderby
				;

		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the venues
	 *
	 * @access private
	 * @return string
	 * @since 0.9
	 */
	function _buildContentOrderBy()
	{
		$app =  JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter_order', 'filter_order', 'l.ordering', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		
		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', l.ordering';

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the venues
	 *
	 * @access private
	 * @return string
	 * @since 0.9
	 */
	function _buildContentWhere()
	{
		$app =  JFactory::getApplication();

		$filter 			= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter', 'filter', '', 'int' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter_state', 'filter_state', '', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_eventlist.venueelement.search', 'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );

		$where = array();

		/*
		* Filter state
		*/
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'l.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'l.published = 0';
			}
		}

		/*
		* Search venues
		*/
		if ($search && $filter == 1) {
			$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
		}

		/*
		* Search city
		*/
		if ($search && $filter == 2) {
			$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}
}
?>