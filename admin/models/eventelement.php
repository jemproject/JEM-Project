<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Eventelement Model
 *
 * @package JEM
 *
 */
class JEMModelEventelement extends JModelLegacy
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
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();

		$limit		= $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );

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
			if ($this->_data)
			{
				$count = count($this->_data);
				for($i = 0; $i < $count; $i++)
				{
					$item = $this->_data[$i];
					$item->categories = $this->getCategories($item->id);
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
		// Lets load the content if it doesn't already exist
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

		$query = 'SELECT a.*, loc.venue, loc.city'
					. ' FROM #__jem_events AS a'
					. ' LEFT JOIN #__jem_venues AS loc ON loc.id = a.locid'
				//	. ' LEFT JOIN #__jem_categories AS cat ON cat.id = a.catsid'
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

		$filter_order		= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_order', 'filter_order', 'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', a.dates';

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

		$filter_state 	= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_state', 'filter_state', '', 'word' );
		$filter 		= $app->getUserStateFromRequest( 'com_jem.eventelement.filter', 'filter', '', 'int' );
		$search 		= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_search', 'filter_search', '', 'string' );
		$search 		= $this->_db->escape( trim(JString::strtolower( $search ) ) );

		$where = array();

		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'a.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'a.published = 0';
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


	function getCategories($id)
	{
		$query = 'SELECT DISTINCT c.id, c.catname, c.checked_out AS cchecked_out'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		$count = count($this->_cats);
		for($i = 0; $i < $count; $i++)
		{
			$item = $this->_cats[$i];
			$cats = new JEMCategories($item->id);
			$item->parentcats = $cats->getParentlist();
		}

		return $this->_cats;
	}
}
?>