<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Settings Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelSettings extends JModelLegacy
{
	/**
	 * Settings data
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Logic for the settings screen
	 *
	 */
	function &getData()
	{
		$query = 'SELECT * FROM #__jem_settings WHERE id = 1';

		$this->_db->setQuery($query);
		$this->_data = $this->_db->loadObject();

		return $this->_data;
	}

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function checkin()
	{
		$item = $this->getTable('jem_settings', '');
		if(! $item->checkin(1)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		return false;
	}

	/**
	 * Method to checkout/lock the item
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	0.9
	 */
	function checkout($uid = null)
	{
		// Make sure we have a user id to checkout the article with
		if (is_null($uid)) {
			$user	= JFactory::getUser();
			$uid	= $user->get('id');
		}
		// Lets get to it and checkout the thing...
		$item =  $this->getTable('jem_settings', '');
		if(!$item->checkout($uid, 1)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		return true;
	}

	/**
	 * Tests if the event is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	0.9
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->getData())
		{
			if ($uid) {
				return ($this->_data->checked_out && $this->_data->checked_out != $uid);
			} else {
				return $this->_data->checked_out;
			}
		}
	}

	/**
	 * Saves the settings
	 *
	 */
	function store($post)
	{
		$settings 	= JTable::getInstance('jem_settings', '');

		// Bind the form fields to the table
		if (!$settings->bind($post)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		$meta_key="";
		foreach ($settings->meta_keywords as $meta_keyword) {
			if ($meta_key != "") {
				$meta_key .= ", ";
			}
			$meta_key .= $meta_keyword;
		}
		$settings->meta_keywords = $meta_key;
		$settings->id = 1;

		if (!$settings->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

    	return true;
	}
}
?>