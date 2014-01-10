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
 * JEM Component Categories Model
 *
 * @package JEM
 *
 */
class JEMModelCategories extends JModelLegacy
{
	/**
	 * Top category id
	 *
	 * @var int
	 */
	var $_id = 0;

	/**
	 * Event data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Categories total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Categories data array
	 *
	 * @var array
	 */
	var $_categories = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params = $app->getParams('com_jem');

		//get	the number of events from database
		$limit 		= JRequest::getInt('limit', $params->get('cat_num'));
		$limitstart = JRequest::getInt('limitstart');

		$id = $params->get('catid',0);
		$this->_id = $id;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Categories
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
		$app = JFactory::getApplication();
		$params = $app->getParams();

		// Lets load the content if it doesn't already exist
		if (empty($this->_categories))
		{
			$parentCategory = $this->_getList($this->_buildQueryParentCategory());
			$query = $this->_buildQuerySubCategories();
			$pagination = $this->getPagination();
			$this->_categories = $this->_getList($query, $pagination->limitstart,  $pagination->limit);

			// Include parent category itself
			$this->_categories = array_merge($parentCategory, $this->_categories);

			foreach($this->_categories as $category)
			{
				
				if ($params->get('usecat',1)) {
					//child categories
					$query = $this->_buildQuerySubCategories($category->id);
					$this->_db->setQuery($query);
					$category->subcats = $this->_db->loadObjectList();
				} else {
					$category->subcats = array();
				}

				//Generate description
				if (empty ($category->description)) {
					$category->description = JText::_('COM_JEM_NO_DESCRIPTION');
				} else {
					//execute plugins
					$category->text 	= $category->description;
					$category->title 	= $category->catname;
					JPluginHelper::importPlugin('content');
					$app->triggerEvent('onContentPrepare', array('com_jem.categories', &$category, &$params, 0));
					$category->description = $category->text;
				}

				//create target link
				$task = JRequest::getWord('task');

				$category->linktext = $task == 'archive' ? JText::_('COM_JEM_SHOW_ARCHIVE') : JText::_('COM_JEM_SHOW_EVENTS');
				
				if ($task == 'archive') {
					$category->linktarget = JRoute::_(JEMHelperRoute::getCategoryRoute($category->slug.'&task=archive'));
				} else {
					$category->linktarget = JRoute::_(JEMHelperRoute::getCategoryRoute($category->slug));
				}
			}
		}

		return $this->_categories;
	}

	/**
	 * Total nr of Categories
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQueryTotal();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get the subcategories query
	 * @param string $parent_id Parent ID of the subcategories
	 * @return string The query string
	 */
	function _buildQuerySubCategories($parent_id = null) {
		return $this->_buildQuery($parent_id);
	}

	/**
	 * Method to get the parent category query
	 * @param string $parent_id ID of the parent category
	 * @return string The query string
	 */
	function _buildQueryParentCategory($parent_id = null) {
		return $this->_buildQuery($parent_id, true);
	}


	/**
	 * Method get the categories query
	 * @param string $parent_id
	 * @param string $parentCategory
	 * @return string The query string
	 */
	function _buildQuery($parent_id = null, $parentCategory = false)
	{
		$app = JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params = $app->getParams('com_jem');

		if (is_null($parent_id)) {
			$parent_id = $this->_id;
		}

		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$ordering = 'c.ordering ASC';

		// build where clause
		$where_sub = ' WHERE cc.published = 1';
		if($parentCategory) {
			$where_sub .= ' AND cc.id = '.(int) $parent_id;
		} else {
			$where_sub .= ' AND cc.parent_id = '.(int) $parent_id;
		}
		$where_sub .= ' AND cc.access IN (' . implode(',', $levels) . ')';

		// check archive task and ensure that only categories get selected
		// if they contain a published/archived event
		$task = JRequest::getWord('task');
		if($task == 'archive') {
			$where_sub .= ' AND i.published = 2';
		} else {
			$where_sub .= ' AND i.published = 1';
		}
		$where_sub .= ' AND c.id = cc.id';

		// show/hide empty categories
		$empty = $params->get('empty_cat') ? '' : ' HAVING assignedevents > 0';

		// Parent category itself or its sub categories
		$parentCategoryQuery = $parentCategory ? 'c.id='.(int)$parent_id : 'c.parent_id='.(int)$parent_id;

		$query = 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS slug,'
					. ' ('
					. ' SELECT COUNT(DISTINCT i.id)'
					. ' FROM #__jem_events AS i'
					. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__jem_categories AS cc ON cc.id = rel.catid'
					. $where_sub
					. ' GROUP BY cc.id'
					. ')'
					. ' AS assignedevents'
				. ' FROM #__jem_categories AS c'
				. ' WHERE c.published = 1'
				. ' AND '.$parentCategoryQuery
				. ' AND c.access IN (' . implode(',', $levels) . ')'
				. ' GROUP BY c.id '.$empty
				. ' ORDER BY '.$ordering
				;

		return $query;
	}


	/**
	 * Method to build the Categories query without subselect
	 * That's enough to get the total value.
	 *
	 * @access private
	 * @return string
	 */
	function _buildQueryTotal()
	{
		$app = JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params = $app->getParams('com_jem');

		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT DISTINCT c.id'
			. ' FROM #__jem_categories AS c';

		if (!$params->get('empty_cat', 1))
		{
			$query .= ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id '
					. ' INNER JOIN #__jem_events AS e ON e.id = rel.itemid ';
		}
		$query .= ' WHERE c.published = 1'
			. ' AND c.parent_id = ' . (int) $this->_id
			. ' AND c.access IN (' . implode(',', $levels) . ')'
			;
		if (!$params->get('empty_cat', 1))
		{
			$task = JRequest::getWord('task');
			if($task == 'archive') {
				$query .= ' AND e.published = 2';
			} else {
				$query .= ' AND e.published = 1';
			}
		}

		return $query;
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
			$this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}
		return $this->_pagination;
	}
}
?>