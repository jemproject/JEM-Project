<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Categoryelement Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelCategoryelement extends JModelLegacy
{	/**
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

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the category identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id)
	{
		// Set id
		$this->_id	 = $id;
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		$app =  JFactory::getApplication();
		
		static $items;

		if (isset($items)) {
			return $items;
		}
		
		$limit				= $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart 		= $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.categoryelement.filter_order', 		'filter_order', 	'c.ordering', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.categoryelement.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.categoryelement.filter_state', 'filter_state', '', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.categoryelement.search', 'search', '', 'string' );
		$search 			= $this->_db->escape( trim(JString::strtolower( $search ) ) );
		
		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', c.ordering';
		
		$where = array();
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'c.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'c.published = 0';
			}
		}
		
		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );
		
		//select the records
		//note, since this is a tree we have to do the limits code-side
		if ($search) {			
			
			
			$query = 'SELECT c.id'
					. ' FROM #__jem_categories AS c'
					. ' WHERE LOWER(c.catname) LIKE '.$this->_db->Quote( '%'.$this->_db->escape( $search, true ).'%', false )
					. $where
					;
			$this->_db->setQuery( $query );
			$search_rows = $this->_db->loadColumn();
		}
		
		$query = 'SELECT c.*, u.name AS editor, g.title AS groupname, gr.name AS catgroup'
					. ' FROM #__jem_categories AS c'
				//	. ' LEFT JOIN #__groups AS g ON g.id = c.access'
				    . ' LEFT JOIN #__viewlevels AS g ON g.id = c.access'
					. ' LEFT JOIN #__users AS u ON u.id = c.checked_out'
					. ' LEFT JOIN #__jem_groups AS gr ON gr.id = c.groupid'
					. $where
					. $orderby
					;
		$this->_db->setQuery( $query );
		$rows = $this->_db->loadObjectList();
		
	// for debugging	
	//	print_r($query);
		
		//establish the hierarchy of the categories
		$children = array();
		
		//first pass - collect children
		//set depth limit
		$levellimit = 10;

    	foreach ($rows as $child) {
        	$parent = $child->parent_id;
       		$list 	= @$children[$parent] ? $children[$parent] : array();
        	array_push($list, $child);
        	$children[$parent] = $list;
    	}
    	
    	//second pass - get an indent list of the items
    	$list = JEMCategories::treerecurse(0, '', array(), $children, false, max(0, $levellimit-1));
    	
    	//eventually only pick out the searched items.
		if ($search) {
			$list1 = array();

			foreach ($search_rows as $sid )
			{
				foreach ($list as $item)
				{
					if ($item->id == $sid) {
						$list1[] = $item;
					}
				}
			}
			// replace full list with found items
			$list = $list1;
		}
		
    	$total = count( $list );

		jimport('joomla.html.pagination');
		$this->_pagination = new JPagination( $total, $limitstart, $limit );

		// slice out elements based on limits
		$list = array_slice( $list, $this->_pagination->limitstart, $this->_pagination->limit );

		return $list;
	}
	
	function &getPagination()
	{
		if ($this->_pagination == null) {
			$this->getItems();
		}
		return $this->_pagination;
	}
}
?>