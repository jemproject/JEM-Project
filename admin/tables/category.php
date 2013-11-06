<?php
/**
 * @version     1.9.5
 * @package     JEM
 * @copyright   Copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// No direct access
defined('_JEXEC') or die;

jimport('joomla.database.tablenested');

/**
 * Category Table
 *
 */
class JEMTableCategory extends JTableNested
{

	function __construct(&$db)
	{
		parent::__construct('#__jem_categories', 'id', $db);

		$rootId = self::getRootId();

		if ($rootId === false) {
   		 $rootId = self::addRoot();
		}


	}


	/**
	 * Method to delete a node and, optionally, its child nodes from the table.
	 *
	 * @param   integer  $pk        The primary key of the node to delete.
	 * @param   boolean  $children  True to delete child nodes, false to move them up a level.
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     http://docs.joomla.org/JTableNested/delete
	 */
	public function delete($pk = null, $children = false)
	{
		return parent::delete($pk, $children);
	}


	/**
	 * Add the root node to an empty table.
	 *
	 * @return    integer  The id of the new root node.
	 */
	public function addRoot()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery();

		// Insert columns.
		$columns = array('parent_id','lft','rgt','level','catname','alias','access','path');

		// Insert values.
		$values = array(0,0,1,0, $db->quote('root'),$db->quote('root'),1,$db->quote(''));

		// Prepare the insert query.
		$query
		->insert($db->quoteName('#__jem_categories'))
		->columns($db->quoteName($columns))
		->values(implode(',', $values));

		$db->setQuery($query);
		$db->query();

		return $db->insertid();
	}


	/**
	 * Gets the ID of the root item in the tree
	 *
	 * @return  mixed  The ID of the root row, or false and the internal error is set.
	 *
	 */
	public function getRootId()
	{
		// Get the root item.
		$k = $this->_tbl_key;

		// Test for a unique record with parent_id = 0
		$query = $this->_db->getQuery(true);
		$query->select($k);
		$query->from($this->_tbl);
		$query->where('parent_id = 0');
		$this->_db->setQuery($query);

		$result = $this->_db->loadColumn();

		if ($this->_db->getErrorNum())
		{
			$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_GETROOTID_FAILED', get_class($this), $this->_db->getErrorMsg()));
			$this->setError($e);
			return false;
		}

		if (count($result) == 1)
		{
			$parentId = $result[0];
		}
		else
		{
			// Test for a unique record with lft = 0
			$query = $this->_db->getQuery(true);
			$query->select($k);
			$query->from($this->_tbl);
			$query->where('lft = 0');
			$this->_db->setQuery($query);

			$result = $this->_db->loadColumn();
			if ($this->_db->getErrorNum())
			{
				$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_GETROOTID_FAILED', get_class($this), $this->_db->getErrorMsg()));
				$this->setError($e);
				return false;
			}

			if (count($result) == 1)
			{
				$parentId = $result[0];
			}
			elseif (property_exists($this, 'alias'))
			{
				// Test for a unique record alias = root
				$query = $this->_db->getQuery(true);
				$query->select($k);
				$query->from($this->_tbl);
				$query->where('alias = ' . $this->_db->quote('root'));
				$this->_db->setQuery($query);

				$result = $this->_db->loadColumn();
				if ($this->_db->getErrorNum())
				{
					$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_GETROOTID_FAILED', get_class($this), $this->_db->getErrorMsg()));
					$this->setError($e);
					return false;
				}

				if (count($result) == 1)
				{
					$parentId = $result[0];
				}
				else
				{
					$e = new JException(JText::_('JLIB_DATABASE_ERROR_ROOT_NODE_NOT_FOUND'));
					$this->setError($e);
					return false;
				}
			}
			else
			{
				$e = new JException(JText::_('JLIB_DATABASE_ERROR_ROOT_NODE_NOT_FOUND'));
				$this->setError($e);
				return false;
			}
		}

		return $parentId;
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
	function _insertIgnoreObject($table, &$object, $keyName = NULL)
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
			$values[] = $this->_db->isQuoted($k) ? $this->_db->quote($v) : (int) $v;
		}
		$this->_db->setQuery(sprintf($fmtsql, implode(",", $fields), implode(",", $values)));
		if (!$this->_db->query()) {
			return false;
		}
		$id = $this->_db->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}
		return $this->_db->getAffectedRows();
	}


}

