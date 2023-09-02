<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

/**
 * JEM venues Model class
 *
 * @package JEM
 *
 */
class jem_venues extends Table
{
	/**
	 * Primary Key
	 * @var int
	 */
	public $id = null;
	/** @var string */
	public $venue = '';
	/** @var string */
	public $alias = '';
	/** @var string */
	public $url = '';
	/** @var string */
	public $street = '';
	/** @var string */
	public $postalCode = '';
	/** @var string */
	public $city = '';
	/** @var string */
	public $state = '';
	/** @var string */
	public $country = '';
	/** @var float */
	public $latitude = null;
	/** @var float */
	public $longitude = null;
	/** @var string */
	public $locdescription = null;
	/** @var string */
	public $meta_description = '';
	/** @var string */
	public $meta_keywords = '';
	/** @var string */
	public $locimage = '';
	/** @var int */
	public $map = null;
	/** @var int */
	public $created_by = null;
	/** @var string */
	public $author_ip = null;
	/** @var date */
	public $created = null;
	/** @var date */
	public $modified = 0;
	/** @var int */
	public $modified_by = null;
	/** @var int */
	public $version = null;
	/** @var int */
	public $published = null;
	/** @var int */
	public $checked_out = null;
	/** @var date */
	public $checked_out_time = null;
	/** @var int */
	public $ordering = null;


	public function __construct(& $db)
	{
		parent::__construct('#__jem_venues', 'id', $db);
	}

	/** overloaded check function
	 *
	 * @return boolean
	 */
	public function check()
	{
		// not typed in a venue name
		if (!trim($this->venue)) {
			$this->_error = Text::_('COM_JEM_ADD_VENUE');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->venue);

		if (empty($this->alias) || $this->alias === $alias) {
			$this->alias = $alias;
		}

		if ($this->map) {
			if (!trim($this->street) || !trim($this->city) || !trim($this->country) || !trim($this->postalCode)) {
				if ((!trim($this->latitude) && !trim($this->longitude))) {
					$this->_error = Text::_('COM_JEM_ERROR_ADDRESS');
					Factory::getApplication()->enqueueMessage($this->_error, 'warning');
					return false;
				}
			}
		}

		if (JFilterInput::checkAttribute(array ('href', $this->url))) {
			$this->_error = Text::_('COM_JEM_ERROR_URL_WRONG_FORMAT');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		if (trim($this->url)) {
			$this->url = strip_tags($this->url);

			if (strlen($this->url) > 199) {
				$this->_error = Text::_('COM_JEM_ERROR_URL_LONG');
				Factory::getApplication()->enqueueMessage($this->_error, 'warning');
				return false;
			}
			if (!preg_match('/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}'
			.'((:[0-9]{1,5})?\/.*)?$/i' , $this->url)) {
				$this->_error = Text::_('COM_JEM_ERROR_URL_WRONG_FORMAT');
				Factory::getApplication()->enqueueMessage($this->_error, 'warning');
				return false;
			}
		}

		$this->street = strip_tags($this->street);
		if (\Joomla\String\StringHelper::strlen($this->street) > 50) {
			$this->_error = Text::_('COM_JEM_ERROR_STREET_LONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$this->postalCode = strip_tags($this->postalCode);
		if (\Joomla\String\StringHelper::strlen($this->postalCode) > 10) {
			$this->_error = Text::_('COM_JEM_ERROR_ZIP_LONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$this->city = strip_tags($this->city);
		if (\Joomla\String\StringHelper::strlen($this->city) > 50) {
			$this->_error = Text::_('COM_JEM_ERROR_CITY_LONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$this->state = strip_tags($this->state);
		if (\Joomla\String\StringHelper::strlen($this->state) > 50) {
			$this->_error = Text::_('COM_JEM_ERROR_STATE_LONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		$this->country = strip_tags($this->country);
		if (\Joomla\String\StringHelper::strlen($this->country) > 2) {
			$this->_error = Text::_('COM_JEM_ERROR_COUNTRY_LONG');
			Factory::getApplication()->enqueueMessage($this->_error, 'warning');
			return false;
		}

		/** check for existing name */
		/*
		$query = 'SELECT id FROM #__jem_venues WHERE venue = '.$this->_db->Quote($this->venue);
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_JEM_VENUE_NAME_ALREADY_EXIST', $this->venue), 'warning');
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
