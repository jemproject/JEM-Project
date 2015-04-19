<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * Table: Groups
 */
class JEMTableGroups extends JTable
{
    public function __construct(&$db)
    {
		parent::__construct('#__jem_groups', 'id', $db);
    }

	// overloaded check function
	public function check()
	{
		// Not typed in a category name?
		if (trim($this->name ) == '') {
			$this->setError(JText::_('COM_JEM_ADD_GROUP_NAME'));
			return false;
		}

		// Set alias
		//$this->alias = JApplication::stringURLSafe($this->alias);
		//if (empty($this->alias)) {
		//	$this->alias = JApplication::stringURLSafe($this->title);
		//}

		return true;
	}

	/**
	 * Store.
	 */
	public function store($updateNulls = false)
	{
		return parent::store($updateNulls);
	}

	public function bind($array, $ignore = '')
	{
		// in here we are checking for the empty value of the checkbox

		//don't override without calling base class
		return parent::bind($array, $ignore);
	}

	/**
	 * try to insert first, update if fails
	 *
	 * Can be overloaded/supplemented by the child class
	 *
	 * @access public
	 * @param boolean If false, null object variables are not updated
	 * @return null|string null if successful otherwise returns and error message
	 */
	function insertIgnore($updateNulls=false)
	{
		$ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);
		if(!$ret) {
			$this->setError(get_class($this).'::store failed - '.$this->_db->getErrorMsg());
			return false;
		}
		return true;
	}

	/**
	 * Inserts a row into a table based on an objects properties, ignore if already exists
	 *
	 * @access protected
	 * @param string  The name of the table
	 * @param object  An object whose properties match table fields
	 * @param string  The name of the primary key. If provided the object property is updated.
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
		if (!$this->_db->execute()) {
			return false;
		}
		$id = $this->_db->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}

		return $this->_db->getAffectedRows();
	}
}
