<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM venues Model class
 *
 * @package JEM
 *
 */
class jem_venues extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var string */
	var $venue 				= '';
	/** @var string */
	var $alias	 			= '';
	/** @var string */
	var $url 				= '';
	/** @var string */
	var $street 			= '';
	/** @var string */
	var $postalCode			= '';
	/** @var string */
	var $city 				= '';
	/** @var string */
	var $state				= '';
	/** @var string */
	var $country			= '';
	/** @var float */
	var $latitude			= null;
	/** @var float */
	var $longitude			= null;
	/** @var string */
	var $locdescription 	= null;
	/** @var string */
	var $meta_description 	= '';
	/** @var string */
	var $meta_keywords		= '';
	/** @var string */
	var $locimage 			= '';
	/** @var int */
	var $map		 		= null;
	/** @var int */
	var $created_by			= null;
	/** @var string */
	var $author_ip	 		= null;
	/** @var date */
	var $created		 	= null;
	/** @var date */
	var $modified 			= 0;
	/** @var int */
	var $modified_by 		= null;
	/** @var int */
	var $version	 		= null;
	/** @var int */
	var $published	 		= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time 	= 0;
	/** @var int */
	var $ordering 			= null;

	public function __construct(& $db) {
		parent::__construct('#__jem_venues', 'id', $db);
	}

	// overloaded check function
	//function check($jemsettings)
	function check()
	{
		// not typed in a venue name
		if(!trim($this->venue)) {
			$this->_error = JText::_('COM_JEM_ADD_VENUE');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->venue);

		if(empty($this->alias) || $this->alias === $alias) {
			$this->alias = $alias;
		}

		if ($this->map) {
			if (!trim($this->street) || !trim($this->city) || !trim($this->country) || !trim($this->postalCode)) {
				if ((!trim($this->latitude) && !trim($this->longitude))) {
					$this->_error = JText::_('COM_JEM_ERROR_ADDRESS');
					JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
					return false;
				}
			}
		}

		if (JFilterInput::checkAttribute(array ('href', $this->url))) {
			$this->_error = JText::_('COM_JEM_ERROR_URL_WRONG_FORMAT');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		if (trim($this->url)) {
			$this->url = strip_tags($this->url);

			if (strlen($this->url) > 199) {
				$this->_error = JText::_('COM_JEM_ERROR_URL_LONG');
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
				return false;
			}
			if (!preg_match('/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}'
			.'((:[0-9]{1,5})?\/.*)?$/i' , $this->url)) {
				$this->_error = JText::_('COM_JEM_ERROR_URL_WRONG_FORMAT');
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
				return false;
			}
		}

		$this->street = strip_tags($this->street);
		if (JString::strlen($this->street) > 50) {
			$this->_error = JText::_('COM_JEM_ERROR_STREET_LONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		$this->postalCode = strip_tags($this->postalCode);
		if (JString::strlen($this->postalCode) > 10) {
			$this->_error = JText::_('COM_JEM_ERROR_ZIP_LONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		$this->city = strip_tags($this->city);
		if (JString::strlen($this->city) > 50) {
			$this->_error = JText::_('COM_JEM_ERROR_CITY_LONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		$this->state = strip_tags($this->state);
		if (JString::strlen($this->state) > 50) {
			$this->_error = JText::_('COM_JEM_ERROR_STATE_LONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		$this->country = strip_tags($this->country);
		if (JString::strlen($this->country) > 2) {
			$this->_error = JText::_('COM_JEM_ERROR_COUNTRY_LONG');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		/** check for existing name */
		/*
		$query = 'SELECT id FROM #__jem_venues WHERE venue = '.$this->_db->Quote($this->venue);
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('COM_JEM_VENUE_NAME_ALREADY_EXIST', $this->venue));
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