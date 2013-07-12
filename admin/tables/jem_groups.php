<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM groups Model class
 *
 * @package JEM
 * @since 0.9
 */
class jem_groups extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $name				= '';
	/** @var string */
	var $description 		= '';
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time	= 0;

	function jem_groups(& $db) {
		parent::__construct('#__jem_groups', 'id', $db);
	}

	// overloaded check function
	function check()
	{
		// Not typed in a category name?
		if (trim( $this->name ) == '') {
			$this->_error = JText::_('COM_JEM_ADD_GROUP_NAME');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error);
			return false;
		}

		/** check for existing name */
		$query = 'SELECT id FROM #__jem_groups WHERE name = '.$this->_db->Quote($this->name);
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('COM_JEM_GROUP_NAME_ALREADY_EXIST', $this->name));
			return false;
		}

		return true;
	}
}
?>