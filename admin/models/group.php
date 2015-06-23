<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/admin.php';

/**
 * JEM Component Group Model
 *
 */
class JEMModelGroup extends JemModelAdmin
{
	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to delete the record. Defaults to the permission set in the component.
	 *
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id))
		{
			if ($record->published != -2) {
				return ;
			}

			$user = JemFactory::getUser();

			if (!empty($record->catid)) {
				return $user->authorise('core.delete', 'com_jem.category.'.(int) $record->catid);
			} else {
				return $user->authorise('core.delete', 'com_jem');
			}
		}
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to change the state of the record. Defaults to the permission set in the component.
	 *
	 */
	protected function canEditState($record)
	{
		$user = JemFactory::getUser();

		if (!empty($record->catid)) {
			return $user->authorise('core.edit.state', 'com_jem.category.'.(int) $record->catid);
		} else {
			return $user->authorise('core.edit.state', 'com_jem');
		}
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	JTable	A database object
	 *
	 */
	public function getTable($type = 'Group', $prefix = 'JemTable', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 *
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_jem.group', 'group', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param	integer	The id of the primary key.
	 *
	 * @return	mixed	Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		return $item;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 */
	protected function loadFormData()
	{

		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_jem.edit.group.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * With $table you can call a table name
	 *
	 */
	protected function _prepareTable($table)
	{
		$db  = JFactory::getDbo();
		$app = JFactory::getApplication();

		// Bind the form fields to the table
// 		if (!$table->bind($app->input->getArray($_POST))) {
// 			return JError::raiseWarning(500, $table->getError());
// 		}

		// Make sure the data is valid
		if (!$table->check()) {
			$this->setError($table->getError());
			return false;
		}

		// Store data
		if (!$table->store(true)) {
			JError::raiseError(500, $table->getError());
		}

		$members = $app->input->get('maintainers', array(), 'array');

		// Updating group references
		$query = $db->getQuery(true);
		$query->delete($db->quoteName('#__jem_groupmembers'));
		$query->where('group_id = '.(int)$table->id);

		$db->setQuery($query);
		$db->execute();

		foreach($members as $member)
		{
			$member = intval($member);

			$query = $db->getQuery(true);
			$columns = array('group_id', 'member');
			$values = array((int)$table->id, $member);

			$query
				->insert($db->quoteName('#__jem_groupmembers'))
				->columns($db->quoteName($columns))
				->values(implode(',', $values));

			$db->setQuery($query);
			$db->execute();
		}
	}


	/**
	 * Method to get the members data
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function &getMembers()
	{
		$members = $this->_members();

		$users = array();

		if (!empty($members)) {
			$query = 'SELECT id AS value, username, name'
					. ' FROM #__users'
					. ' WHERE id IN ('.$members.')'
					. ' ORDER BY name ASC'
					;

			$this->_db->setQuery($query);

			$users = $this->_db->loadObjectList();

			for($i=0; $i < count($users); $i++) {
			$item = $users[$i];

			$item->text = $item->name.' ('.$item->username.')';
			}

		}
		return $users;
	}


	/**
	 * Method to get the selected members
	 *
	 * @access	public
	 * @return	string
	 *
	 */
	protected function _members()
	{
		$item = parent::getItem();

		//get selected members
		if ($item->id == null) {
			$this->_members = null;
		} else {
			if ($item->id) {
				$query = 'SELECT member'
						. ' FROM #__jem_groupmembers'
						. ' WHERE group_id = '.(int)$item->id;

				$this->_db->setQuery ($query);

				$member_ids = $this->_db->loadColumn();

				if (is_array($member_ids)) {
					$this->_members = implode(',', $member_ids);
				}
			}
		}

		return $this->_members;
	}


	/**
	 * Method to get the available users
	 *
	 * @access	public
	 * @return	mixed
	 *
	 */
	function &getAvailable()
	{
		$members = $this->_members();

		// get non selected members
		$query = 'SELECT id AS value, username, name FROM #__users';
		$query .= ' WHERE block = 0' ;

		if ($members) {
			$query .= ' AND id NOT IN ('.$members.')' ;
		}

		$query .= ' ORDER BY name ASC';

		$this->_db->setQuery($query);

		$this->_available = $this->_db->loadObjectList();

		for($i=0, $n=count($this->_available); $i < $n; $i++) {
			$item = $this->_available[$i];

			$item->text = $item->name.' ('.$item->username.')';
		}

		return $this->_available;
	}
}
