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
 * JEM Settings Table
 *
 */
class JEMTableSettings extends JTable
{
	function __construct(&$db)
	{
		parent::__construct('#__jem_settings', 'id', $db);
	}


	/*
	 * Validators
	 */
	function check()
	{
		
		return true;
	}


	/**
	 * Overloaded the store method
	 */
	public function store($updateNulls = false)
	{
		return parent::store($updateNulls);
	}


	public function bind($array, $ignore = '')
	{

		if (isset($array['globalattribs']) && is_array($array['globalattribs']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['globalattribs']);
			$array['globalattribs'] = (string) $registry;
		}
		
		//don't override without calling base class
		return parent::bind($array, $ignore);
	}

}
?>