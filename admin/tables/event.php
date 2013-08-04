<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM Venue Model
 * 
 */
class JEMTableEvent extends JTable
{


	function __construct(&$db)
	{
		parent::__construct('#__jem_events', 'id', $db);
	}


	
	// overloaded check function
	function check()
	{
		
		if (empty($this->enddates)) {
			$this->enddates = NULL;
		}
		
		if (empty($this->dates)) {
			$this->dates = NULL;
		}

		return true;
	}
	
	
	/**
	 * Overload the store method for the Venue table.
	 *
	 */
	public function store($updateNulls = false)
	{
		
			// Verify that the alias is unique
			$table = JTable::getInstance('Event', 'JEMTable');
					/*if ($table->load(array('alias'=>$this->alias, 'catid'=>$this->catid)) && ($table->id != $this->id || $this->id==0)) {*/
			//if ($table->load(array('alias'=>$this->alias)) && ($table->id != $this->id || $this->id==0)) {
			//
			//
				//	$this->setError(JText::_('COM_JEM_ERROR_UNIQUE_ALIAS'));
				//	return false;
				//	}
					// Attempt to store the user data.
					return parent::store($updateNulls);
		}
	
		
		public function bind($array, $ignore = '')
		{
			
			// in here we are checking for the empty value of the checkbox
			
			
			if (!isset($array['registra']))
				$array['registra'] = 0 ;
			
			if (!isset($array['unregistra']))
				$array['unregistra'] = 0 ;
			
			if (!isset($array['waitinglist']))
				$array['waitinglist'] = 0 ;
			
			
		
			//don't override without calling base class
			return parent::bind($array, $ignore);
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
	 *
	 * 
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
		if (empty($pks))
		{
			if ($this->$k)
			{
				$pks = array($this->$k);
			}
			// Nothing to set publishing state on, return false.
			else
			{
				$this->setError(JText::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
				return false;
			}
		}

		// Build the WHERE clause for the primary keys.
		$where = $k . '=' . implode(' OR ' . $k . '=', $pks);

		// Determine if there is checkin support for the table.
		if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time'))
		{
			$checkin = '';
		}
		else
		{
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
		if ($this->_db->getErrorNum())
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		// If checkin is supported and all rows were adjusted, check them in.
		if ($checkin && (count($pks) == $this->_db->getAffectedRows()))
		{
			// Checkin the rows.
			foreach ($pks as $pk)
			{
				$this->checkin($pk);
			}
		}

		// If the JTable instance value is in the list of primary keys that were set, set the instance.
		if (in_array($this->$k, $pks))
		{
			$this->published = $state;
		}

		$this->setError('');

		return true;
	}

	
}
?>