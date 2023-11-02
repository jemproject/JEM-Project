<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

jimport('joomla.database.tablenested');

/**
 * Category Table
 */
class JemTableCategory extends JTableNested
{
	public function __construct(&$db)
	{
		parent::__construct('#__jem_categories', 'id', $db);

		if (self::addRoot() !== false) {
			return;
		}
	}

	/**
	 * Method to delete a node and, optionally, its child nodes from the table.
	 *
	 * @param  integer  $pk        The primary key of the node to delete.
	 * @param  boolean  $children  True to delete child nodes, false to move them up a level.
	 *
	 * @return boolean  True on success.
	 *
	 * @link   https://docs.joomla.org/JTableNested/delete
	 */
	public function delete($pk = null, $children = false)
	{
		return parent::delete($pk, $children);
	}

	/**
	 * Add the root node to an empty table.
	 *
	 * @return integer  The id of the new root node.
	 */
	public function addRoot()
	{
		if (self::getRootId() !== false) {
			return;
		}

        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		// Insert columns.
		$columns = array('parent_id', 'lft','rgt', 'level', 'catname', 'alias', 'access','title','published');

		// Insert values.
		$values = array(0, 0, 1, 0, $db->quote('root'), $db->quote('root'),1, $db->quote('root'),1);

		// Prepare the insert query.
		$query
		->insert($db->quoteName('#__jem_categories'))
		->columns($db->quoteName($columns))
		->values(implode(',', $values));
		$db->setQuery($query);
		
		$db->execute();

		return $db->insertid();
	}

	/**
	 * try to insert first, update if fails
	 *
	 * Can be overloaded/supplemented by the child class
	 *
	 * @access public
	 * @param  boolean If false, null object variables are not updated
	 * @return null|string null if successful otherwise returns and error message
	 */
	public function insertIgnore($updateNulls = false)
	{
		$ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);
		if ($ret < 0) {
			$this->setError(get_class($this).'::store failed - '.$this->_db->getError());
		}
		return $ret;
	}

	/**
	 * Inserts a row into a table based on an objects properties, ignore if already exists
	 *
	 * @access protected
	 * @param  string  The name of the table
	 * @param  object  An object whose properties match table fields
	 * @param  string  The name of the primary key. If provided the object property is updated.
	 * @return int number of affected row
	 */
	protected function _insertIgnoreObject($table, &$object, $keyName = NULL)
	{
		$fmtsql = 'INSERT IGNORE INTO '.$this->_db->quoteName($table).' (%s) VALUES (%s) ';
		$fields = array();
		foreach (get_object_vars($object) as $k => $v) {
			if (is_array($v) or is_object($v) or $v === NULL) {
				continue;
			}
			if ($k[0] == '_') { // internal field
				continue;
			}
			$fields[] = $this->_db->quoteName($k);
			$values[] = $this->_db->quote($v);
		}
		$this->_db->setQuery(sprintf($fmtsql, implode(",", $fields), implode(",", $values)));
		$results = $this->_db->execute();
        if ($results === false){
			return -1;
		}
		$id = $this->_db->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}
		return $this->_db->getAffectedRows();
	}

	/**
	 * Overloaded check function
	 *
	 * @return boolean
	 *
	 * @see    Table::check
	 * @since  11.1
	 */
	public function check()
	{
		// Check for a title.
		if (trim($this->catname) == '') {
			$this->setError(Text::_('JLIB_DATABASE_ERROR_MUSTCONTAIN_A_TITLE_CATEGORY'));
			return false;
		}
		$this->alias = trim($this->alias);
		if (empty($this->alias)) {
			$this->alias = $this->catname;
		}

		$this->alias = JemHelper::stringURLSafe($this->alias);
		if (trim(str_replace('-', '', $this->alias)) == '') {
			$this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
		}

		return true;
	}

	/**
	 * Overloaded bind function.
	 *
	 * @param  array   $array   named array
	 * @param  string  $ignore  An optional array or space separated list of properties
	 *                          to ignore while binding.
	 *
	 * @return mixed   Null if operation was satisfactory, otherwise returns an error
	 *
	 * @see    Table::bind
	 * @since  11.1
	 */
	public function bind($array, $ignore = '')
	{
		if (isset($array['params']) && is_array($array['params'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['params']);
			$array['params'] = (string) $registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string) $registry;
		}

		if (isset($array['rules']) && is_array($array['rules'])) {
			$rules = new JAccessRules($array['rules']);
			$this->setRules($rules);
		}

		return parent::bind($array, $ignore);
	}

	/**
	 * Overloaded Table::store to set created/modified and user id.
	 *
	 * @param  boolean  $updateNulls  True to update fields even if they are null.
	 * @return boolean  True on success.
	 */
	public function store($updateNulls = false)
	{
		$date = Factory::getDate();
		$user = JemFactory::getUser();
		if ($this->id) {
			// Existing category
			$this->modified_time = $date->toSql();
			$this->modified_user_id = $user->get('id');
		} else {
			// New category
			$this->created_time = $date->toSql();
			$this->created_user_id = $user->get('id');
		}
		// Verify that the alias is unique
		$table = Table::getInstance('Category', 'JEMTable', array('dbo' => Factory::getContainer()->get('DatabaseDriver')));

		if ($table->load(array('alias' => $this->alias, 'parent_id' => $this->parent_id))
		    && ($table->id != $this->id || $this->id == 0)) {
			
			$this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_UNIQUE_ALIAS'));
			return false;
		}
	
		return parent::store($updateNulls);
	}

	/**
	 * Check Csv Import
	 * @Todo   add validation
	 */
	public function checkCsvImport()
	{
        foreach (get_object_vars($this) as $k => $v) {
            if (is_array($v) or is_object($v) or $v === NULL) {
                continue;
            }
            if ($k[0] == '_') { // internal field
                continue;
            }
            //Change datetime to null when its value is '000-00-00' (support J4 & J5)
            if(strpos($v, "0000-00-00")!== FALSE){
                $this->$k = null;
            }
        }
		return true;
	}

	/**
	 * Store Csv Import
	 */
	public function storeCsvImport($updateNulls = false)
	{
		// Initialise variables.
		$k = $this->_tbl_key;

		// If a primary key exists update the object, otherwise insert it.
		if ($this->$k) {
			$stored = $this->_db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		} else {
			$stored = $this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}

		// If the store failed return false.
		if (!$stored) {
			$e = Text::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $stored->getError());
			$this->setError($e);
			return false;
		}

		if ($this->_locked) {
			$this->_unlock();
		}

		return true;
	}
}
