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
use Joomla\CMS\Language\Text;

/**
 * JEM events Model class
 *
 * @package JEM
 *
 */
class jem_events extends Table
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
	public $checked_out = null;
	/** @var date */
	public $checked_out_time = null;


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
			$this->_error = Text::_('WRONGSTARTTIMEFORMAT'.': '.$this->times);
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}
		if (empty($this->times) || preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->times)) {
			$this->times = NULL;
		}
		if (preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)) {
			$this->_error = Text::_('WRONGENDTIMEFORMAT'.': '.$this->endtimes);
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}
		if (empty($this->endtimes) || empty($this->times) || preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)
		    || preg_match("/^:[0-5][0-9](:[0-5][0-9])?$/", $this->times))
		{
			$this->endtimes = NULL;
		}

		$this->title = strip_tags(trim($this->title));
		$titlelength = \Joomla\String\StringHelper::strlen($this->title);

		if ($this->title == '') {
			$this->_error = Text::_('COM_JEM_ADD_TITLE');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		if ($titlelength > 100) {
			$this->_error = Text::_('COM_JEM_ERROR_TITLE_LONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->title);

		if (empty($this->alias) || $this->alias === $alias) {
			$this->alias = $alias;
		}

		if ($this->dates && !preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->dates)) {
			$this->_error = Text::_('COM_JEM_DATE_WRONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		if (isset($this->enddates)) {
			if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->enddates)) {
				$this->_error = Text::_('COM_JEM_ENDDATE_WRONG_FORMAT');
				Factory::getApplication()->enqueueMessage($this->_error, 'warning');
				return false;
			}
		}

/*		if (isset($this->recurrence_limit_date)) {
			if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->recurrence_limit_date)) {
	 				$this->_error = Text::_('COM_JEM_WRONGRECURRENCEDATEFORMAT');
	 				Factory::getApplication()->enqueueMessage($this->_error, 'warning');
	 				return false;
			}
		}
		*/

		if (isset($this->times) && $this->times) {
			if (!preg_match("/^[0-2][0-9]:[0-5][0-9](:[0-5][0-9])?$/", $this->times)) {
				$this->_error = Text::_('WRONGSTARTTIMEFORMAT'.': '.$this->times);
				Factory::getApplication()->enqueueMessage($this->_error, 'warning');
				return false;
			}
		}

		if (isset($this->endtimes) && $this->endtimes) {
			if (!preg_match("/^[0-2][0-9]:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)) {
				$this->_error = Text::_('COM_JEM_WRONGENDTIMEFORMAT');
				Factory::getApplication()->enqueueMessage($this->_error, 'warning');
				return false;
			}
		}

		//No venue or category choosen?
		//if ($this->locid == '') {
		//	$this->_error = Text::_('COM_JEM_VENUE_EMPTY');
		//	Factory::getApplication()->enqueueMessage($this->_error, 'warning');
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
