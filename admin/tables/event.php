<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM Event Table
 *
 */
class JEMTableEvent extends JTable
{
	function __construct(&$db)
	{
		parent::__construct('#__jem_events', 'id', $db);
	}

	/**
	 * overloaded check function
	 */
	function check()
	{
		$jinput = JFactory::getApplication()->input;

		//get values from time selectlist and concatenate them accordingly
		$starthours		= $jinput->get('starthours','','cmd');
		$startminutes	= $jinput->get('startminutes','','cmd');
		$endhours		= $jinput->get('endhours','','cmd');
		$endminutes		= $jinput->get('endminutes','','cmd');

		// StartTime
		if ($starthours != '' && $startminutes != '') {
			$this->times = $starthours.':'.$startminutes;
		} else if ($starthours != '' && $startminutes == '') {
			$startminutes = "00";
			$this->times = $starthours.':'.$startminutes;
		} else if ($starthours == '' && $startminutes != '') {
			$starthours = "00";
			$this->times = $starthours.':'.$startminutes;
		} else {
			$this->times = NULL;
		}

		// EndTime
		if ($endhours != '' && $endminutes != '') {
			$this->endtimes = $endhours.':'.$endminutes;
		} else if ($endhours != '' && $endminutes == '') {
			$endminutes = "00";
			$this->endtimes = $endhours.':'.$endminutes;
		} else if ($endhours == '' && $endminutes != '') {
			$endhours = "00";
			$this->endtimes = $endhours.':'.$endminutes;
		} else {
			$this->endtimes = NULL;
		}

		// Dates
		if (empty($this->enddates)) {
			$this->enddates = NULL;
		}

		if (empty($this->dates)) {
			$this->dates = NULL;
		}

		// check startDate
		if ($this->dates == NULL) {
			$this->times = NULL;
			$this->enddates = NULL;
			$this->endtimes = NULL;
		}

		// Check begin date is before end date

		// Check if end date is set
		if($this->enddates == null) {
			// Check if end time is set
			if($this->endtimes == null) {
				$date1 = new DateTime('00:00');
				$date2 = new DateTime('00:00');
			} else {
				$date1 = new DateTime($this->times);
				$date2 = new DateTime($this->endtimes);
			}
		} else {
			// Check if end time is set
			if($this->endtimes == null) {
				$date1 = new DateTime($this->dates);
				$date2 = new DateTime($this->enddates);
			} else {
				$date1 = new DateTime($this->dates.' '.$this->times);
				$date2 = new DateTime($this->enddates.' '.$this->endtimes);
			}
		}

		if($date1 > $date2) {
			$this->setError(JText::_('COM_JEM_ERROR_END_BEFORE_START'));
			return false;
		}

		// Set alias
		$this->alias = JApplication::stringURLSafe($this->alias);
		if (empty($this->alias)) {
			$this->alias = JApplication::stringURLSafe($this->title);
		}

		return true;
	}

	/**
	 * Overloaded store method for the Event table.
	 */
	public function store($updateNulls = false)
	{
		return parent::store($updateNulls);
	}

	/**
	 * Overloaded bind method for the Event table.
	 */
	public function bind($array, $ignore = '')
	{
		// in here we are checking for the empty value of the checkbox

		if (!isset($array['registra'])) {
			$array['registra'] = 0 ;
		}

		if (!isset($array['unregistra'])) {
			$array['unregistra'] = 0 ;
		}

		if (!isset($array['waitinglist'])) {
			$array['waitinglist'] = 0 ;
		}

		// Search for the {readmore} tag and split the text up accordingly.
		if (isset($array['articletext'])) {
			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos = preg_match($pattern, $array['articletext']);

			if ($tagPos == 0) {
				$this->introtext = $array['articletext'];
				$this->fulltext = '';
			} else {
				list ($this->introtext, $this->fulltext) = preg_split($pattern, $array['articletext'], 2);
			}
		}

		if (isset($array['attribs']) && is_array($array['attribs'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['attribs']);
			$array['attribs'] = (string) $registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string) $registry;
		}

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

	/**
	 * Method to set the publishing state for a row or list of rows in the database
	 * table. The method respects checked out rows by other users and will attempt
	 * to checkin rows that it can after adjustments are made.
	 *
	 * @param   mixed    $pks     An array of primary key values to update.  If not
	 *                            set the instance property value is used. [optional]
	 * @param   integer  $state   The publishing state. eg. [0 = unpublished, 1 = published] [optional]
	 * @param   integer  $userId  The user id of the user performing the operation. [optional]
	 *
	 * @return  boolean  True on success.
	 */
	function publish($pks = null, $state = 1, $userId = 0)
	{
		// Initialise variables.
		$k = $this->_tbl_key;

		// Sanitize input.
		JArrayHelper::toInteger($pks);
		$userId = (int) $userId;
		$state = (int) $state;

		// If there are no primary keys set check to see if the instance key is set.
		if (empty($pks)) {
			if ($this->$k) {
				$pks = array($this->$k);
			} else {
				// Nothing to set publishing state on, return false.
				$this->setError(JText::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
				return false;
			}
		}

		// Build the WHERE clause for the primary keys.
		$where = $k . '=' . implode(' OR ' . $k . '=', $pks);

		// Determine if there is checkin support for the table.
		if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time')) {
			$checkin = '';
		} else {
			$checkin = ' AND (checked_out = 0 OR checked_out = ' . (int) $userId . ')';
		}

		// Update the publishing state for rows with the given primary keys.
		$query = $this->_db->getQuery(true);
		$query->update($this->_db->quoteName($this->_tbl));
		$query->set($this->_db->quoteName('published') . ' = ' . (int) $state);
		$query->where($where);
		$this->_db->setQuery($query . $checkin);
		$this->_db->query();

		// Check for a database error.
		if ($this->_db->getErrorNum()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		// If checkin is supported and all rows were adjusted, check them in.
		if ($checkin && (count($pks) == $this->_db->getAffectedRows())) {
			// Checkin the rows.
			foreach ($pks as $pk) {
				$this->checkin($pk);
			}
		}

		// If the JTable instance value is in the list of primary keys that were set, set the instance.
		if (in_array($this->$k, $pks)) {
			$this->published = $state;
		}

		$this->setError('');

		return true;
	}
}
?>