<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modeladmin');

/*
 * Abstraction layer to handle incompatibilities between Joomla 2.5 and 3.x
 */

if (version_compare(JVERSION, '3.0', 'lt')) {
	// on Joomla 2.5 there's a reference...
	abstract class JemModelAdmin extends JModelAdmin
	{
		protected function _prepareTable($table)
		{
			// Derived class will provide its own implementation if required.
		}
		protected function prepareTable(&$table)
		{
			$this->_prepareTable($table);
		}
	}
} else {
	// ...on Joomla 3.x not.
	abstract class JemModelAdmin extends JModelAdmin
	{
		protected function _prepareTable($table)
		{
			// Derived class will provide its own implementation if required.
		}
		protected function prepareTable($table)
		{
			$this->_prepareTable($table);
		}
	}
}
