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
 * JEM Component Contactelement Model
 *
 * @package JEM
 *
 */
class JEMModelContactelement extends JModelLegacy
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
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();

		$limit		= $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get contactelement item data
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
	 * Total nr of contactelement
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
	 * Method to get a pagination object for the contactelement
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
	 * Method to build the query for the contactelement
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();

		$query = 'SELECT con.*'
				. ' FROM #__contact_details AS con'
				. $where
				. $orderby
				;
		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the contactelement
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentOrderBy()
	{
		$app =  JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest( 'com_jem.contactelement.filter_order', 'filter_order', 'con.ordering', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.contactelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		if ($filter_order != '') {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		} else {
			$orderby = ' ORDER BY con.name ';
		}

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the contactelement
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentWhere()
	{
		$app =  JFactory::getApplication();

		$filter 			= $app->getUserStateFromRequest( 'com_jem.contactelement.filter', 'filter', '', 'int' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.contactelement.filter_state', 'filter_state', '', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.contactelement.filter_search', 'filter_search', '', 'string' );
		$search 			= $this->_db->escape( trim(JString::strtolower( $search ) ) );

		$where = array();

		/*
		* Filter state
		*/
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'con.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'con.published = 0';
			}
		}

		/*
		* Search names
		*/
		if ($search && $filter == 1) {
			$where[] = ' LOWER(con.name) LIKE \'%'.$search.'%\' ';
		}

		/*
		* Search address
		*/
		if ($search && $filter == 2) {
			$where[] = ' LOWER(con.address) LIKE \'%'.$search.'%\' ';
		}

		/*
		 * Search city
		*/
		if ($search && $filter == 3) {
			$where[] = ' LOWER(con.suburb) LIKE \'%'.$search.'%\' ';
		}

		/*
		 * Search state
		*/
		if ($search && $filter == 4) {
			$where[] = ' LOWER(con.state) LIKE \'%'.$search.'%\' ';
		}

		$where = ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}
}
?>