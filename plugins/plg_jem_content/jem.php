<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * 
 * Plugin based on the Joomla! content plugin
 */
defined('_JEXEC') or die();

/**
 * JEM content
 */
class plgContentJem extends JPlugin
{

	public function onContentBeforeSave($context, &$event, $isNew)
	{
		
		// Check we are handling the backend event form.
		if ($context != 'com_jem.event') {
			return true;
		}
		
		$user = JFactory::getUser();
		
		/*
		 * // Check if this function is enabled. if
		 * (!$this->params->def('function', 1)) { return true; } // Check this
		 * is a new event. if (!$isNew) { return true; }
		 */
		
		return true;
	}

	/**
	 * Example after save content method
	 * Event is passed by reference, but after the save, so no changes will be
	 * saved.
	 * Method is called right after the content is saved
	 *
	 * @param string		The context of the content passed to the plugin (added in
	 *        	1.6)
	 * @param object		A JTableContent object
	 * @param bool		If the content is just about to be created
	 * @since 1.6
	 */
	public function onContentAfterSave($context, &$event, $isNew)
	{
		
		// Check we are handling the backend event form.
		if ($context != 'com_jem.event') {
			return true;
		}
		
		$user = JFactory::getUser();
		
		/*
		 * // Check if this function is enabled. if
		 * (!$this->params->def('function', 1)) { return true; } // Check this
		 * is a new event. if (!$isNew) { return true; }
		 */
		
		return true;
	}

	/**
	 * Don't allow categories to be deleted if they contain items or
	 * subcategories with items
	 *
	 * @todo : change code
	 * When selecting category's to delete it can happen that category's
	 * without events are not being deleted. 
	 * Like cat3:1 event), cat2:(0 event), try to delete both.
	 *      
	 * @param string	The context for the content passed to the plugin.
	 * @param object	The data relating to the content that was deleted.
	 * @return boolean
	 */
	public function onContentBeforeDelete($context, $data)
	{
		
		// Skip plugin if we are deleting something other than categories
		if ($context != 'com_jem.category') {
			return true;
		}
		
		// Check if this function is enabled.
		if (!$this->params->def('check_categories', 1)) {
			return true;
		}
		
		// $extension = JRequest::getString('extension');
		$extension = 'com_jem';
		
		// Default to true if not a core extension
		$result = true;
		
		$tableInfo = array(
				'com_jem' => array(
						'table_name' => '#__jem_events'
				)
		);
		
		// Now check to see if this is a known core extension
		if (isset($tableInfo[$extension])) {
			// Get table name for known core extensions
			$table = $tableInfo[$extension]['table_name'];
			// See if this category has any content items
			$count = $this->_countItemsInCategory($table, $data->get('id'));
			// Return false if db error
			if ($count === false) {
				$result = false;
			}
			else {
				// Show error if items are found in the category
				if ($count > 0) {
					$msg = JText::sprintf('COM_JEM_CATEGORIES_DELETE_NOT_ALLOWED', $data->get('catname')) . JText::plural('COM_JEM_CATEGORIES_N_ITEMS_ASSIGNED', $count);
					JError::raiseWarning(403, $msg);
					$result = false;
				}
				// Check for items in any child categories (if it is a leaf,
				// there are no child categories)
				if (!$data->isLeaf()) {
					$count = $this->_countItemsInChildren($table, $data->get('id'), $data);
					if ($count === false) {
						$result = false;
					}
					elseif ($count) {
						$combined = array();
						
						foreach ($count as $row) {
							$combined[] = $row->catname;
						}
						
						$result = array_unique($combined);
						
						$cids = implode(', ', $result);
						
						$msg =
						 JText::sprintf('COM_JEM_CATEGORIES_HAS_SUBCATEGORY_ITEMS', $cids);
						JError::raiseWarning(403, $msg);
						$result = false;
					}
				}
			}
			
			return $result;
		}
	}

	/**
	 * Get count of items in a category
	 *
	 * @param string	table name of component table (column is catid)
	 * @param int		id of the category to check
	 * @return mixed of items found or false if db error
	 * 
	 */
	private function _countItemsInCategory($table, $catid)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		// Count the items in this category
		$query->select('COUNT(e.catid)');
		$query->from('#__jem_categories AS c');
		$query->join('LEFT', '#__jem_cats_event_relations AS e ON e.catid = c.id');
		
		$query->where('c.id = ' . $catid);
		$db->setQuery($query);
		$count = $db->loadResult();
		
		// Check for DB error.
		if ($error = $db->getErrorMsg()) {
			JError::raiseWarning(500, $error);
			return false;
		}
		else {
			return $count;
		}
	}

	/**
	 * Get count of items in a category's child categories
	 *
	 * @param string	table name of component table (column is catid)
	 * @param int		id of the category to check
	 * @return mixed of items found or false if db error
	 * 
	 */
	private function _countItemsInChildren($table, $catid, $data)
	{
		$db = JFactory::getDbo();
		// Create subquery for list of child categories
		$childCategoryTree = $data->getTree();
		// First element in tree is the current category, so we can skip that
		// one
		unset($childCategoryTree[0]);
		$childCategoryIds = array();
		foreach ($childCategoryTree as $node) {
			$childCategoryIds[] = $node->id;
		}
		
		// Make sure we only do the query if we have some categories to look in
		if (count($childCategoryIds)) {
			// Count the items in this category
			$query = $db->getQuery(true);
			$query->select('c.catname,c.id,e.itemid');
			$query->from('#__jem_categories AS c');
			$query->join('LEFT', '#__jem_cats_event_relations AS e ON e.catid = c.id');
			$query->where('e.catid IN (' . implode(',', $childCategoryIds) . ')');
			
			$db->setQuery($query);
			$count = $db->loadObjectList();
			
			// Check for DB error.
			if ($error = $db->getErrorMsg()) {
				JError::raiseWarning(500, $error);
				return false;
			}
			else {
				return $count;
			}
		}
		else		// If we didn't have any categories to check, return 0
		{
			return 0;
		}
	}
}
