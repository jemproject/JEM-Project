<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

/**
 * JEM table class
 *
 * @package JEM
 *
 */
class jem_cats_event_relations extends Table
{
	/**
	 * Primary Key
	 * @var int
	 */
	public $id = null;
	/**
	 * Category ID
	 * @var int
	 */
	public $catid = null;
	/**
	 * Event ID
	 * @var int
	 */
	public $itemid = null;
	/**
	 * Ordering
	 * @var int
	 * @todo implement
	 */
	public $ordering = null;

	public function __construct(& $db)
	{
		parent::__construct('#__jem_cats_event_relations', 'id', $db);
	}

	/**
	 * overloaded check function
	 */
	public function check()
	{
		return true;
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
		try {
			$ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);
		} catch (RuntimeException $e){
			$this->setError(get_class($this).'::store failed - '.$e->getMessage());
			return false;
		}
		return true;
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
		if ($this->_db->execute() === false) {
			return false;
		}
		$id = $this->_db->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}
		return $this->_db->getAffectedRows();
	}
}
?>
