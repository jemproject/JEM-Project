<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';

/**
 * JEM Component Group Model
 *
 */
class JemModelGroup extends JemModelAdmin
{
	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param  object  A record object.
	 * @return boolean True if allowed to delete the record. Defaults to the permission set in the component.
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id) && ($record->published == -2))
		{
			$user = JemFactory::getUser();

			if (!empty($record->catid)) {
				return $user->authorise('core.delete', 'com_jem.category.'.(int) $record->catid);
			} else {
				return $user->authorise('core.delete', 'com_jem');
			}
		}

		return false;
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param  object  A record object.
	 * @return boolean True if allowed to change the state of the record. Defaults to the permission set in the component.
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
	 * @param  type   The table type to instantiate. Optional.
	 * @param  string A prefix for the table class name. Optional.
	 * @param  array  Configuration data for model. Optional.
	 * @return Table A database object
	 */
	public function getTable($type = 'Group', $prefix = 'JemTable', $config = array())
	{
		return Table::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param  array   $data     Data for the form. Optional.
	 * @param  boolean $loadData True if the form is to load its own data (default case), false if not. Optional.
	 * @return mixed   A JForm object on success, false on failure
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
	 * @param  integer The id of the primary key.
	 *
	 * @return mixed   Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		return $item;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_jem.edit.group.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param Table The table object to prepare.
	 *
	 */
	protected function _prepareTable($table)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();

		// Make sure the data is valid
		if (!$table->check()) {
			$this->setError($table->getError());
			return;
		}

		// Store data
		if (!$table->store(true)) {
			throw new Exception($table->getError(), 500);
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

			$query->insert($db->quoteName('#__jem_groupmembers'))
			      ->columns($db->quoteName($columns))
			      ->values(implode(',', $values));

			$db->setQuery($query);
			$db->execute();
		}
	}

	/**
	 * Method to get the members data
	 *
	 * @access public
	 * @return array List of members
	 *
	 */
	public function getMembers()
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

			foreach ($users as &$user) {
				$user->text = $user->name . ' (' . $user->username . ')';
			}
		}

		return $users;
	}

	/**
	 * Method to get the selected members.
	 *
	 * @access protected
	 * @return string
	 *
	 */
	protected function _members()
	{
		$item = parent::getItem();

		$members = null;

		//get selected members
		if ($item->id) {
			$query = 'SELECT member'
			       . ' FROM #__jem_groupmembers'
			       . ' WHERE group_id = '.(int)$item->id;

			$this->_db->setQuery ($query);
			$member_ids = $this->_db->loadColumn();

			if (is_array($member_ids)) {
				$members = implode(',', $member_ids);
			}
		}

		return $members;
	}

	/**
	 * Method to get the available users.
	 *
	 * @access public
	 * @return mixed
	 *
	 */
	public function getAvailable()
	{
		$members = $this->_members();

		// get non selected members
		$query  = 'SELECT id AS value, username, name FROM #__users';
		$query .= ' WHERE block = 0' ;

		if ($members) {
			$query .= ' AND id NOT IN ('.$members.')' ;
		}

		$query .= ' ORDER BY name ASC';

		$this->_db->setQuery($query);
		$available = $this->_db->loadObjectList();

		foreach ($available as &$item) {
			$item->text = $item->name . ' (' . $item->username . ')';
		}

		return $available;
	}
}
