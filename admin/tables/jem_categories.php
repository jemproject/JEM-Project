<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

jimport('joomla.database.tablenested');

/**
 * JEM categories Model class
 *
 * @package JEM
 *
 */
class jem_categories extends JTableNested
{
	/**
	 * Primary Key
	 * @var int
	 */
	public $id = null;
	/** @var int */
	public $parent_id = 0;
	/** @var string */
	public $catname = '';
	/** @var string */
	public $alias = '';
	/** @var string */
	public $description = null;
	/** @var string */
	public $meta_description = '';
	/** @var string */
	public $meta_keywords = '';
	/** @var string */
	public $image = '';
	/** @var string */
	public $color = '';
	/** @var int */
	public $published = null;
	/** @var int */
	public $checked_out = null;
	/** @var date */
	public $checked_out_time = null;
	/** @var int */
	public $access = 0;
	/** @var int */
	public $groupid = 0;
	/** @var string */
	public $maintainers = null;
	/** @var int */
	public $ordering = null;


	/**
	 * @param  database A database connector object
	 */
	public function __construct(& $db)
	{
		parent::__construct('#__jem_categories', 'id', $db);
	}

	/**
	 * Overloaded check function
	 *
	 * @access public
	 * @return boolean
	 *
	 */
	public function check()
	{
		// Not typed in a category name?
		if (trim($this->catname) == '') {
			$this->_error = Text::_('COM_JEM_ADD_NAME_CATEGORY');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->catname);

		if (empty($this->alias) || $this->alias === $alias) {
			$this->alias = $alias;
		}

		/** check for existing name */
		/* in fact, it can happen for subcategories
		$query = 'SELECT id FROM #__jem_categories WHERE catname = '.$this->_db->Quote($this->catname);
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_JEM_CATEGORY_NAME_ALREADY_EXIST', $this->catname), 'warning');
			return false;
		}
		*/

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
	function insertIgnore($updateNulls = false)
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
