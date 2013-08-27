<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Categories Model
 *
 * @package JEM
 *
 */
class JEMModelCategories extends JModelLegacy
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

		$jinput = JFactory::getApplication()->input;
		$array = $jinput->get('cid',  0, 'array');

		/* @todo Cleanup
		 *
		 *  $array = JRequest::getVar('cid', 0, '', 'array'); */
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
		$this->_id = $id;
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		$app = JFactory::getApplication();

		static $items;

		if (isset($items)) {
			return $items;
		}

		$limit				= $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart 		= $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.categories.filter_order', 'filter_order', 'c.ordering', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.categories.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.categories.filter_state', 'filter_state', '', 'string' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.categories.filter_search', 'filter_search', '', 'string' );
		$search 			= $this->_db->escape( trim(JString::strtolower( $search ) ) );

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		$orderby = ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', c.ordering';

		$where = array();

		// Filter by published state
		$published = $filter_state;
		if (is_numeric($published)) {
			$where[] = 'c.published = '.(int) $published;
		} elseif ($published === '') {
			$where[] = '(c.published = 0 OR c.published = 1)';
		}

		$where = ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		//select the records
		//note, since this is a tree we have to do the limits code-side
		if ($search) {
			$query = 'SELECT c.id'
					. ' FROM #__jem_categories AS c'
					. $where
					. ($where == '' ? ' WHERE ' : ' AND ')
					. ' LOWER(c.catname) LIKE '.$this->_db->Quote( '%'.$this->_db->escape( $search, true ).'%', false )
					;
			$this->_db->setQuery( $query );
			$search_rows = $this->_db->loadColumn();
		}

		$query = 'SELECT c.*, c.catname AS name, c.parent_id AS parent, u.name AS editor, g.title AS groupname, gr.name AS catgroup'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__viewlevels AS g ON g.id = c.access'
				. ' LEFT JOIN #__users AS u ON u.id = c.checked_out'
				. ' LEFT JOIN #__jem_groups AS gr ON gr.id = c.groupid'
				. $where
				. $orderby
				;
		$this->_db->setQuery( $query );
		$rows = $this->_db->loadObjectList();

		//establish the hierarchy of the categories
		$children = array();

		//set depth limit
		$levellimit = 10;

		//first pass - collect children
		if (is_array($rows))
		{
			foreach ($rows as $child) {
				$parent = $child->parent_id;
				$list 	= @$children[$parent] ? $children[$parent] : array();
				array_push($list, $child);
				$children[$parent] = $list;
			}
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

		foreach ($list as $category) {
			$category->assignedevents = $this->_countcatevents( $category->id );
		}

		return $list;
	}



	function &getPagination()
	{
		if ($this->_pagination == null) {
			$this->getItems();
		}
		return $this->_pagination;
	}

	/**
	 * Method to (un)publish a category
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user 	= JFactory::getUser();

		if (count( $cid ))
		{
			if (!$publish) {
				// Add all children to the list
				foreach ($cid as $id)
				{
					$this->_addCategories($id, $cid);
				}
			} else {
				// Add all parents to the list
				foreach ($cid as $id)
				{
					$this->_addCategories($id, $cid, 'parents');
				}
			}

			$cids = implode( ',', $cid );

			$query = 'UPDATE #__jem_categories'
				. ' SET published = ' . (int) $publish
				. ' WHERE id IN ('. $cids .')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return true;
	}

	/**
	 * Method to move a category
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function move($direction)
	{
		$row = JTable::getInstance('jem_categories', '');

		if (!$row->load( $this->_id ) ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		if (!$row->move( $direction, 'parent_id = '.$row->parent_id )) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		return true;
	}

	/**
	 * Method to order categories
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function saveorder($cid = array(), $order)
	{
		$row = JTable::getInstance('jem_categories', '');

		$groupings = array();

		// update ordering values
		for( $i=0; $i < count($cid); $i++ )
		{
			$row->load( (int) $cid[$i] );

			// track categories
			$groupings[] = $row->parent_id;

			if ($row->ordering != $order[$i])
			{
				$row->ordering = $order[$i];
				if (!$row->store()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
			}
		}

		// execute updateOrder for each parent group
		$groupings = array_unique( $groupings );
		foreach ($groupings as $group){
			$row->reorder('parent_id = '.$group);
		}

		return true;
	}

	/**
	 * Method to count the nr of assigned events to the category
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function _countcatevents($id)
	{
		$query = 'SELECT COUNT(DISTINCT e.id )'
				.' FROM #__jem_events AS e'
				.' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = e.id'
				.' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
				.' WHERE rel.catid = ' . (int)$id
				;

		$this->_db->setQuery($query);
		$number = $this->_db->loadResult();

		return $number;
	}


	/**
	 * Method to remove a category
	 *
	 * @access	public
	 * @return	string $msg
	 *
	 */
	function delete($cids)
	{
		// Add all children to the list
		foreach ($cids as $id)
		{
			$this->_addCategories($id, $cids);
		}

		$cids = implode( ',', $cids );

		$query = 'SELECT c.id, c.catname, COUNT( e.catid ) AS numcat'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS e ON e.catid = c.id'
				. ' WHERE c.id IN ('. $cids .')'
				. ' GROUP BY c.id'
				;
		$this->_db->setQuery( $query );

		if (!($rows = $this->_db->loadObjectList())) {
			JError::raiseError( 500, $this->_db->stderr() );
			return false;
		}

		$err = array();
		$cid = array();

		//TODO: Categories and its childs without assigned items will not be deleted if another tree has any item entry
		foreach ($rows as $row) {
			if ($row->numcat == 0) {
				$cid[] = $row->id;
			} else {
				$err[] = $row->catname;
			}
		}

		if (count( $cid ) && count($err) == 0)
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__jem_categories'
					. ' WHERE id IN ('. $cids .')';

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		if (count( $err )) {
			$cids 	= implode( ', ', $err );
			$msg 	= JText::sprintf( 'COM_JEM_EVENT_ASSIGNED_CATEGORY', $cids );
			return $msg;
		} else {
			$total 	= count( $cid );
			$msg 	= $total.' '.JText::_('COM_JEM_CATEGORIES_DELETED');
			return $msg;
		}
	}

	/**
	 * Method to set the access level of the category
	 *
	 * @access	public
	 * @param integer id of the category
	 * @param integer access level
	 * @return	boolean	True on success
	 *
	 */
	function access($id, $access)
	{
		$category = $this->getTable('jem_categories', '');

		//handle childs
		$cids = array();
		$cids[] = $id;
		$this->_addCategories($id, $cids);

		foreach ($cids as $cid) {

			$category->load( (int)$cid );

			if ($category->access < $access) {
				$category->access = $access;
			} else {
				$category->load( $id );
				$category->access = $access;
			}

			if ( !$category->check() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			if ( !$category->store() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

		}

		//handle parents
		$pcids = array();
		$this->_addCategories($id, $pcids, 'parents');

		foreach ($pcids as $pcid) {

			if($pcid == 0 || $pcid == $id) {
				continue;
			}

			$category->load( (int)$pcid );

			if ($category->access > $access) {

				$category->access = $access;

				if ( !$category->check() ) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
				if ( !$category->store() ) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}

			}
		}
		return true;
	}

	/**
	 * Method to add children/parents to a specific category
	 *
	 * @param int $id
	 * @param array $list
	 * @param string $type
	 * @return oject
	 *
	 *
	 */
	function _addCategories($id, &$list, $type = 'children')
	{
		// Initialize variables
		$return = true;

		if ($type == 'children') {
			$get = 'id';
			$source = 'parent_id';
		} else {
			$get = 'parent_id';
			$source = 'id';
		}

		// Get all rows with parent of $id
		$query = 'SELECT '.$get.
				' FROM #__jem_categories' .
				' WHERE '.$source.' = '.(int) $id;
		$this->_db->setQuery( $query );
		$rows = $this->_db->loadObjectList();

		// Make sure there aren't any errors
		if ($this->_db->getErrorNum()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		// Recursively iterate through all children
		foreach ($rows as $row)
		{
			$found = false;
			foreach ($list as $idx)
			{
				if ($idx == $row->$get) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$list[] = $row->$get;
			}
			$return = $this->_addCategories($row->$get, $list, $type);
		}
		return $return;
	}
}
?>