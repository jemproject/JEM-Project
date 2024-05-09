<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Date\Date;

/**
 * Category Model
 */
class JemModelCategory extends AdminModel
{
	/**
	 * The prefix to use with controller messages.
	 * @var string
	 */
	protected $text_prefix = 'COM_JEM_CATEGORIES';


	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param  object $record A record object.
	 *
	 * @return boolean True if allowed to delete the record. Defaults to the
	 *         permission set in the component.
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id)) {
			if ($record->published != -2) {
				return;
			}
			$user = JemFactory::getUser();

			return $user->authorise('core.delete', 'com_jem');
		}
	}

	/**
	 * Method to test whether a record can have its state changed.
	 *
	 * @param  object $record A record object.
	 *
	 * @return boolean True if allowed to change the state of the record.
	 *         Defaults to the permission set in the component.
	 */
	protected function canEditState($record)
	{
		$user = JemFactory::getUser();

		// Check for existing category.
		if (!empty($record->id)) {
			return $user->authorise('core.edit.state', 'com_jem' . '.category.' . (int) $record->id);
		}
		// New category, so check against the parent.
		elseif (!empty($record->parent_id)) {
			return $user->authorise('core.edit.state', 'com_jem' . '.category.' . (int) $record->parent_id);
		}
		// Default to component settings if neither category nor parent known.
		else {
			return $user->authorise('core.edit.state', 'com_jem');
		}
	}

	/**
	 * Auto-populate the model state.
	 *
	 * @Note Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = Factory::getApplication('administrator');

		$parentId = $app->input->getInt('parent_id', 0);
		$this->setState('category.parent_id', $parentId);

		// Load the User state.
		$pk = (int) $app->input->getInt('id', 0);
		$this->setState($this->getName() . '.id', $pk);

		// Load the parameters.
		$params = ComponentHelper::getParams('com_jem');
		$this->setState('params', $params);
	}

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param  string $type   The table name. Optional.
	 * @param  string $prefix The class prefix. Optional.
	 * @param  array  $config Configuration array for model. Optional.
	 *
	 * @return Table A Table object
	 */
	public function getTable($type = 'Category', $prefix = 'JemTable', $config = array())
	{
		return Table::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get a category.
	 *
	 * @param  integer $pk An optional id of the object to get, otherwise the id
	 *                     from the model state is used.
	 *
	 * @return mixed Category data object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		if ($result = parent::getItem($pk))
		{
			// Prime required properties.
			if (empty($result->id)) {
				$result->parent_id = $this->getState('category.parent_id');
			}

			// Convert the metadata field to an array.
			$registry = new Registry();
			$registry->loadString($result->metadata ?? '{}');
			$result->metadata = $registry->toArray();

			// Convert the created and modified dates to local user time for
			// display in the form.
			jimport('joomla.utilities.date');
			$tz = new DateTimeZone(Factory::getApplication()->getCfg('offset'));

			if (intval($result->created_time)) {
				$date = new Date($result->created_time);
				$date->setTimezone($tz);
				$result->created_time = $date->toSql(true);
			}
			else {
				$result->created_time = null;
			}

			if (intval($result->modified_time)) {
				$date = new Date($result->modified_time);
				$date->setTimezone($tz);
				$result->modified_time = $date->toSql(true);
			}
			else {
				$result->modified_time = null;
			}
		}

		return $result;
	}

	/**
	 * Method to get the row form.
	 *
	 * @param  array   $data     Data for the form.
	 * @param  boolean $loadData True if the form is to load its own data
	 *                           (default case), false if not.
	 *
	 * @return mixed A JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_jem.category', 'category',
		                        array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form)) {
			return false;
		}

		return $form;
	}

	/**
	 * A protected method to get the where clause for the reorder
	 * This ensures that the row will be moved relative to a row with the same
	 * extension
	 *
	 * @param  JCategoryTable $table Current table instance
	 *
	 * @return array An array of conditions to add to add to ordering queries.
	 */
	protected function getReorderConditionsDISABLED($table)
	{
		return 'extension = ' . $this->_db->Quote($table->extension);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return mixed The data for the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_jem.edit.category.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param  array $data The form data.
	 *
	 * @return boolean True on success.
	 *
	 * @since 1.6
	 */
	public function save($data)
	{
		// Initialise variables;
		$dispatcher = JemFactory::getDispatcher();
		$table = $this->getTable();
		$jinput = Factory::getApplication()->input;

		$pk = (!empty($data['id'])) ? $data['id'] : (int) $this->getState($this->getName() . '.id');
		$isNew = true;

		// Include the content plugins for the on save events.
		PluginHelper::importPlugin('content');

		// Load the row if saving an existing category.
		if ($pk > 0) {
			$table->load($pk);
			$isNew = false;
		}

		// Set the new parent id if parent id not matched OR while New/Save as
		// Copy .
		if ($table->parent_id != $data['parent_id'] || $data['id'] == 0) {
			$table->setLocation($data['parent_id'], 'last-child');
		}
		$data['title'] = isset($data['title']) ? $data['title']  : '';
		$data['note'] = isset($data['note']) ? $data['note']  : '';
		$data['language'] = isset($data['language']) ? $data['language']  : '';
		$data['path'] = isset($data['path']) ? $data['path']  : '';
		$data['metadata'] = isset($data['metadata']) ? $data['metadata']  : '';
		
		// Alter the title for save as copy
		if ($jinput->get('task', '') == 'save2copy') {
			list ($title, $alias) = $this->generateNewTitle($data['parent_id'], $data['alias'], $data['title']);
			$data['title'] = $title;
			$data['alias'] = $alias;
			
			// also reset creation date, modification fields, hit counter, version
			unset($data['created_time']);
			unset($data['modified_time']);
			unset($data['modified_user_id']);
		}

		$groupid = $jinput->get('groupid', '', 'int');
		$table->groupid = $groupid;

		$color = $jinput->get('color', '', 'html');

		if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
			$color = '';
		}
		$table->color = $color;
		
		// Bind the data.
		if (!$table->bind($data)) {
			$this->setError($table->getError());
			return false;
		}

		// Bind the rules.
		if (isset($data['rules'])) {
			$rules = new JAccessRules($data['rules']);
			$table->setRules($rules);
		}

		// Check the data.
		if (!$table->check()) {
			$this->setError($table->getError());
			return false;
		}

		// Trigger the onContentBeforeSave event.
		// $result = $dispatcher->triggerEvent($this->event_before_save, array($this->option . '.' . $this->name, &$table, $isNew));
		$result = $dispatcher->triggerEvent($this->event_before_save, array($this->option . '.' . $this->name, &$table, $isNew,''));
		
		if (in_array(false, $result, true)) {
			$this->setError($table->getError());
			return false;
		}

		// Store the data.
		if (!$table->store()) {
			
			$this->setError($table->getError());
			return false;
		}
		
		// Trigger the onContentAfterSave event.
		$dispatcher->triggerEvent($this->event_after_save, array($this->option . '.' . $this->name, &$table, $isNew));

		// Rebuild the path for the category:
		if (!$table->rebuildPath($table->id)) {
			$this->setError($table->getError());
			return false;
		}

		// Rebuild the paths of the category's children:
		if (!$table->rebuild($table->id, $table->lft, $table->level, $table->path)) {
			$this->setError($table->getError());
			return false;
		}

		$this->setState($this->getName() . '.id', $table->id);

		// Clear the cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Method to change the published state of one or more records.
	 *
	 * @param  array   &$pks  A list of the primary keys to change.
	 * @param  integer $value The value of the published state.
	 *
	 * @return boolean True on success.
	 */
	public function publish(&$pks, $value = 1)
	{
		if (parent::publish($pks, $value)) {
			// Initialise variables.
			$dispatcher = JemFactory::getDispatcher();
			$extension = Factory::getApplication()->input->getCmd('extension', '');

			// Include the content plugins for the change of category state
			// event.
			PluginHelper::importPlugin('content');

			// Trigger the onCategoryChangeState event.
			$dispatcher->triggerEvent('onCategoryChangeState', array($extension, $pks, $value));

			return true;
		}
	}

	/**
	 * Method rebuild the entire nested set tree.
	 *
	 * @return boolean False on failure or error, true otherwise.
	 */
	public function rebuild()
	{
		// Get an instance of the table object.
		$table = $this->getTable();

		if (!$table->rebuild()) {
			$this->setError($table->getError());
			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Method to save the reordered nested set tree.
	 * First we save the new order values in the lft values of the changed ids.
	 * Then we invoke the table rebuild to implement the new ordering.
	 *
	 * @param  array   $idArray   An array of primary key ids.
	 * @param  integer $lft_array The lft value
	 *
	 * @return boolean False on failure or error, True otherwise
	 */
	public function saveorder($idArray = null, $lft_array = null)
	{
		// Get an instance of the table object.
		$table = $this->getTable();

		if (!$table->saveorder($idArray, $lft_array)) {
			$this->setError($table->getError());
			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Batch copy categories to a new category.
	 *
	 * @param  integer $value    The new category.
	 * @param  array   $pks      An array of row IDs.
	 * @param  array   $contexts An array of item contexts.
	 *
	 * @return mixed An array of new IDs on success, boolean false on failure.
	 */
	protected function batchCopy($value, $pks, $contexts)
	{
		// $value comes as {parent_id}.{extension}
		$parts = explode('.', $value);
		$parentId = (int) \Joomla\Utilities\ArrayHelper::getValue($parts, 0, 1);

		$table = $this->getTable();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$user = JemFactory::getUser();
		$extension = Factory::getApplication()->input->get('extension', '', 'word');
		$i = 0;

		// Check that the parent exists
		if ($parentId) {
			if (!$table->load($parentId)) {
				if ($error = $table->getError()) {
					// Fatal error
					$this->setError($error);
					return false;
				}
				else {
					// Non-fatal error
					$this->setError(Text::_('JGLOBAL_BATCH_MOVE_PARENT_NOT_FOUND'));
					$parentId = 0;
				}
			}
			// Check that user has create permission for parent category
			$canCreate = ($parentId == $table->getRootId()) ? $user->authorise('core.create', $extension) : $user->authorise('core.create', $extension . '.category.' . $parentId);
			if (!$canCreate) {
				// Error since user cannot create in parent category
				$this->setError(Text::_('COM_CATEGORIES_BATCH_CANNOT_CREATE'));
				return false;
			}
		}

		// If the parent is 0, set it to the ID of the root item in the tree
		if (empty($parentId)) {
			if (!$parentId = $table->getRootId()) {
				$this->setError($parentId->getError());
				return false;
			}
			// Make sure we can create in root
			elseif (!$user->authorise('core.create', $extension)) {
				$this->setError(Text::_('COM_CATEGORIES_BATCH_CANNOT_CREATE'));
				return false;
			}
		}

		// We need to log the parent ID
		$parents = array();

		// Calculate the emergency stop count as a precaution against a runaway
		// loop bug
		$query = $db->getQuery(true);
		$query->select('COUNT(id)');
		$query->from($db->quoteName('#__categories'));
		$db->setQuery($query);
		$count = $db->loadResult();

		if ($error = $count->getError()) {
			$this->setError($error);
			return false;
		}

		// Parent exists so we let's proceed
		while (!empty($pks) && $count > 0)
		{
			// Pop the first id off the stack
			$pk = array_shift($pks);

			$table->reset();

			// Check that the row actually exists
			if (!$table->load($pk)) {
				if ($error = $table->getError()) {
					// Fatal error
					$this->setError($error);
					return false;
				}
				else {
					// Not fatal error
					$this->setError(Text::sprintf('JGLOBAL_BATCH_MOVE_ROW_NOT_FOUND', $pk));
					continue;
				}
			}

			// Copy is a bit tricky, because we also need to copy the children
			$query->clear();
			$query->select('id');
			$query->from($db->quoteName('#__categories'));
			$query->where('lft > ' . (int) $table->lft);
			$query->where('rgt < ' . (int) $table->rgt);
			$db->setQuery($query);
			$childIds = $db->loadColumn();

			// Add child ID's to the array only if they aren't already there.
			foreach ($childIds as $childId) {
				if (!in_array($childId, $pks)) {
					array_push($pks, $childId);
				}
			}

			// Make a copy of the old ID and Parent ID
			$oldId = $table->id;
			$oldParentId = $table->parent_id;

			// Reset the id because we are making a copy.
			$table->id = 0;

			// If we a copying children, the Old ID will turn up in the parents
			// list
			// otherwise it's a new top level item
			$table->parent_id = isset($parents[$oldParentId]) ? $parents[$oldParentId] : $parentId;

			// Set the new location in the tree for the node.
			$table->setLocation($table->parent_id, 'last-child');

			// TODO: Deal with ordering?
			// $table->ordering = 1;
			$table->level = null;
			$table->asset_id = null;
			$table->lft = null;
			$table->rgt = null;

			// Alter the title & alias
			list ($title, $alias) = $this->generateNewTitle($table->parent_id, $table->alias, $table->catname);
			$table->title = $title;
			$table->alias = $alias;

			// Store the row.
			if (!$table->store()) {
				$this->setError($table->getError());
				return false;
			}

			// Get the new item ID
			$newId = $table->get('id');

			// Add the new ID to the array
			$newIds[$i] = $newId;
			$i++;

			// Now we log the old 'parent' to the new 'parent'
			$parents[$oldId] = $table->id;
			$count--;
		}

		// Rebuild the hierarchy.
		if (!$table->rebuild()) {
			$this->setError($table->getError());
			return false;
		}

		// Rebuild the tree path.
		if (!$table->rebuildPath($table->id)) {
			$this->setError($table->getError());
			return false;
		}

		return $newIds;
	}

	/**
	 * Batch move categories to a new category.
	 *
	 * @param  integer $value    The new category ID.
	 * @param  array   $pks      An array of row IDs.
	 * @param  array   $contexts An array of item contexts.
	 *
	 * @return boolean True on success.
	 */
	protected function batchMove($value, $pks, $contexts)
	{
		$parentId = (int) $value;

		$table = $this->getTable();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$user = JemFactory::getUser();
		$extension = Factory::getApplication()->input->get('extension', '', 'word');

		// Check that the parent exists.
		if ($parentId) {
			if (!$table->load($parentId)) {
				if ($error = $table->getError()) {
					// Fatal error
					$this->setError($error);

					return false;
				}
				else {
					// Non-fatal error
					$this->setError(Text::_('JGLOBAL_BATCH_MOVE_PARENT_NOT_FOUND'));
					$parentId = 0;
				}
			}
			// Check that user has create permission for parent category
			$canCreate = ($parentId == $table->getRootId()) ? $user->authorise('core.create', $extension) : $user->authorise('core.create', $extension . '.category.' . $parentId);
			if (!$canCreate) {
				// Error since user cannot create in parent category
				$this->setError(Text::_('COM_CATEGORIES_BATCH_CANNOT_CREATE'));
				return false;
			}

			// Check that user has edit permission for every category being
			// moved
			// Note that the entire batch operation fails if any category lacks
			// edit permission
			foreach ($pks as $pk) {
				if (!$user->authorise('core.edit', $extension . '.category.' . $pk)) {
					// Error since user cannot edit this category
					$this->setError(Text::_('COM_CATEGORIES_BATCH_CANNOT_EDIT'));
					return false;
				}
			}
		}

		// We are going to store all the children and just move the category
		$children = array();

		// Parent exists so we let's proceed
		foreach ($pks as $pk)
		{
			// Check that the row actually exists
			if (!$table->load($pk)) {
				if ($error = $table->getError()) {
					// Fatal error
					$this->setError($error);
					return false;
				}
				else {
					// Not fatal error
					$this->setError(Text::sprintf('JGLOBAL_BATCH_MOVE_ROW_NOT_FOUND', $pk));
					continue;
				}
			}

			// Set the new location in the tree for the node.
			$table->setLocation($parentId, 'last-child');

			// Check if we are moving to a different parent
			if ($parentId != $table->parent_id) {
				// Add the child node ids to the children array.
				$query->clear();
				$query->select('id');
				$query->from($db->quoteName('#__categories'));
				$query->where($db->quoteName('lft') . ' BETWEEN ' . (int) $table->lft . ' AND ' . (int) $table->rgt);
				$db->setQuery($query);
				$children = array_merge($children, (array) $db->loadColumn());
			}

			// Store the row.
			if (!$table->store()) {
				$this->setError($table->getError());
				return false;
			}

			// Rebuild the tree path.
			if (!$table->rebuildPath()) {
				$this->setError($table->getError());
				return false;
			}
		}

		// Process the child rows
		if (!empty($children)) {
			// Remove any duplicates and sanitize ids.
			$children = array_unique($children);
			\Joomla\Utilities\ArrayHelper::toInteger($children);

			// Check for a database error.
			// if ($db->getErrorNum()) {
			// 	$this->setError($db->getErrorMsg());
			// 	return false;
			// }
		}

		return true;
	}

	/**
	 * Custom clean the cache of com_content and content modules
	 *
	 * TODO: Should this clean caches of JEM, e.g. com_jem and mod_jem* ?
	 */
	protected function cleanCache($group = null, $client_id = 0)
	{
		$extension = Factory::getApplication()->input->getCmd('extension', '');
		switch ($extension)
		{
			case 'com_content':
				parent::cleanCache('com_content');
				parent::cleanCache('mod_articles_archive');
				parent::cleanCache('mod_articles_categories');
				parent::cleanCache('mod_articles_category');
				parent::cleanCache('mod_articles_latest');
				parent::cleanCache('mod_articles_news');
				parent::cleanCache('mod_articles_popular');
				break;
			default:
				parent::cleanCache($extension);
				break;
		}
	}

	/**
	 * Method to change the title & alias.
	 *
	 * @param  integer $parent_id The id of the parent.
	 * @param  string  $alias     The alias.
	 * @param  string  $title     The title.
	 *
	 * @return array Contains the modified title and alias.
	 */
	protected function generateNewTitle($parent_id, $alias, $title)
	{
		// Alter the title & alias
		$table = $this->getTable();
		while ($table->load(array('alias' => $alias, 'parent_id' => $parent_id))) {
			$title = \Joomla\String\StringHelper::increment($title);
			$alias = \Joomla\String\StringHelper::increment($alias, 'dash');
		}

		return array($title, $alias);
	}

	/**
	 * Method to get the group data
	 *
	 * @access public
	 * @return boolean on success
	 */
	public function getGroups()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = 'SELECT id AS value, name AS text'
		       . ' FROM #__jem_groups'
		       . ' ORDER BY name';
		$db->setQuery($query);

		$groups = $db->loadObjectList('value');

		return $groups;
	}

	/**
	 * Method to remove a category
	 *
	 * @todo: check if finder-plugin is being triggered
	 * move to Candelete function
	 *
	 * @access public
	 * @return string $msg
	 */
	public function delete(&$cids)
	{
		\Joomla\Utilities\ArrayHelper::toInteger($cids);

		// Add all children to the list
		foreach ($cids as $id) {
			$this->_addCategories($id, $cids);
		}

		$cids = implode(',', $cids);

		if (strlen($cids) == 0) {
			Factory::getApplication()->enqueueMessage($this->_db->stderr(), 'error');
			return false;
		}

		$query = 'SELECT c.id, c.catname, COUNT( e.catid ) AS numcat'
		       . ' FROM #__jem_categories AS c'
		       . ' LEFT JOIN #__jem_cats_event_relations AS e ON e.catid = c.id'
		       . ' WHERE c.id IN (' . $cids .')' . ' GROUP BY c.id';
		$this->_db->setQuery($query);

		if (!($rows = $this->_db->loadObjectList())) {
			Factory::getApplication()->enqueueMessage($this->_db->stderr(), 'error');
			return false;
		}

		$err = array();
		$cid = array();

		// TODO: Categories and its childs without assigned items will not be
		// deleted if another tree has any item entry
		foreach ($rows as $row) {
			if ($row->numcat == 0) {
				$cid[] = $row->id;
			}
			else {
				$err[] = $row->catname;
			}
		}

		if (count($cid) && count($err) == 0) {
			$cids = implode(',', $cid);
			$query = 'DELETE FROM #__jem_categories'
			       . ' WHERE id IN (' . $cids . ')';

			$this->_db->setQuery($query);

			// TODO: use exception handling
			if ($this->_db->execute() === false) {
				$this->setError($this->_db->getError());
				return false;
			}
		}

		if (count($err)) {
			$cids = implode(', ', $err);
			$msg = Text::sprintf('COM_JEM_EVENT_ASSIGNED_CATEGORY', $cids);
			return $msg;
		}
		else {
			$total = count($cid);
			$msg = Text::plural('COM_JEM_CATEGORIES_N_ITEMS_DELETED', $total);
			return $msg;
		}
	}

	/**
	 * Method to add children/parents to a specific category
	 *
	 * @param  int    $id
	 * @param  array  $list
	 * @param  string $type
	 * @return object
	 */
	protected function _addCategories($id, &$list, $type = 'children')
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
		$query = 'SELECT ' . $get
		       . ' FROM #__jem_categories'
		       . ' WHERE ' . $source . ' = ' . (int)$id;
		$this->_db->setQuery( $query );
		

		// Make sure there aren't any errors
		// if ($this->_db->getErrorNum()) {
		// 	$this->setError($this->_db->getErrorMsg());
		// 	return false;
		// }
		try 
		{
			$rows = $this->_db->loadObjectList();
		} 
		catch (\InvalidArgumentException $e)
		{
			$this->setError($e->getMessage());			
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
