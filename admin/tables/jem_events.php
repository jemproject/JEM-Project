<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM events Model class
 *
 * @package JEM
 *
 */
class jem_events extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	public $id = null;
	/** @var int */
	public $locid = null;
	/** @var date */
	public $dates = null;
	/** @var date */
	public $enddates = null;
	/** @var date */
	public $times = null;
	/** @var date */
	public $endtimes = null;
	/** @var string */
	public $title = '';
	/** @var string */
	public $alias = '';
	/** @var date */
	public $created = null;
	/** @var int */
	public $created_by = null;
	/** @var int */
	public $modified = 0;
	/** @var int */
	public $modified_by = null;
	/** @var int */
	public $version = 0;
	/** @var string */
	public $meta_description = '';
	/** @var string */
	public $meta_keywords = '';
	/**
	 * repetition intervall
	 *
	 * @var int
	 */
	public $recurrence_number = 0;
	/**
	 * type of recurrence (daily, weekly, monthly)
	 *
	 * @var int
	 */
	public $recurrence_type = 0;
	/**
	 * occurence counter
	 *
	 * @var int
	 */
	public $recurrence_counter = 0;
	/**
	* limit counter for repetition
	*
	* @var string
	*/
	public $recurrence_limit = 0;
	/**
	* limit date for repetition
	*
	* @var string
	*/
	public $recurrence_limit_date = null;
	/**
	* list of day the event occurs on (2 letters, separated by comma)
	*
	* @var string
	*/
	public $recurrence_byday = '';
	/** @var int id of first event for recurrence events*/
	public $recurrence_first_id = 0;
	/** @var string */
	public $datimage = '';
	/** @var string */
	public $author_ip = null;
	/** @var int */
	public $published = null;
	/** @var int */
	public $registra = null;
	/** @var int */
	public $unregistra = null;
	/** @var int */
	public $maxplaces = 0;
	/** @var int */
	public $waitinglist = 0;
	/** @var int */
	public $hits = 0;
	/** @var int */
	public $checked_out = 0;
	/** @var date */
	public $checked_out_time = 0;


	public function __construct(& $db)
	{
		parent::__construct('#__jem_events', 'id', $db);
	}

	/** overloaded check function
	 *
	 * @return boolean
	 */
	public function check($jemsettings = null)
	{
		// Check fields
		if (empty($this->enddates)) {
			$this->enddates = NULL;
		}

		if (preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->times)) {
			$this->_error = JText::_('WRONGSTARTTIMEFORMAT'.': '.$this->times);
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}
		if (empty($this->times) || preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->times)) {
			$this->times = NULL;
		}
		if (preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)) {
			$this->_error = JText::_('WRONGENDTIMEFORMAT'.': '.$this->endtimes);
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}
		if (empty($this->endtimes) || empty($this->times) || preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)
		    || preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->times))
		{
			$this->endtimes = NULL;
		}

		$this->title = strip_tags(trim($this->title));
		$titlelength = JString::strlen($this->title);

		if ($this->title == '') {
			$this->_error = JText::_('COM_JEM_ADD_TITLE');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		if ($titlelength > 100) {
			$this->_error = JText::_('COM_JEM_ERROR_TITLE_LONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->title);

		if (empty($this->alias) || $this->alias === $alias) {
			$this->alias = $alias;
		}

		if ($this->dates && !preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->dates)) {
			$this->_error = JText::_('COM_JEM_DATE_WRONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		if (isset($this->enddates)) {
			if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->enddates)) {
				$this->_error = JText::_('COM_JEM_ENDDATE_WRONG_FORMAT');
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
				return false;
			}
		}

/*		if (isset($this->recurrence_limit_date)) {
			if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->recurrence_limit_date)) {
	 				$this->_error = JText::_('COM_JEM_WRONGRECURRENCEDATEFORMAT');
	 				JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
	 				return false;
			}
		}
		*/

		if (isset($this->times) && $this->times) {
			if (!preg_match("/^[0-2][0-9]:[0-5][0-9](:[0-5][0-9])?$/", $this->times)) {
				$this->_error = JText::_('WRONGSTARTTIMEFORMAT'.': '.$this->times);
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
				return false;
			}
		}

		if (isset($this->endtimes) && $this->endtimes) {
			if (!preg_match("/^[0-2][0-9]:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)) {
				$this->_error = JText::_('COM_JEM_WRONGENDTIMEFORMAT');
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
				return false;
			}
		}

		//No venue or category choosen?
		//if ($this->locid == '') {
		//	$this->_error = JText::_('COM_JEM_VENUE_EMPTY');
		//	JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
		//	return false;
		//}

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
		$ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);
		if (!$ret) {
			$this->setError(get_class($this).'::store failed - '.$this->_db->getErrorMsg());
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