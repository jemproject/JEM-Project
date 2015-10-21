<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.controllerform');

/*
 * Abstraction layer to handle incompatibilities between Joomla 2.5 and 3.x
 */

if (version_compare(JVERSION, '3.0', 'lt')) {

	# on Joomla 2.5 first param must be a reference to a JModel object...

	abstract class JemControllerForm extends JControllerForm
	{
		/**
		 * Function that allows child controller access to model data
		 * after the data has been saved.
		 *
		 * @see    JemControllerForm::postSaveHook()
		 *
		 * @since  JEM 2.1.5
		 */
		protected function _postSaveHook($model, $validData = array())
		{
			// Derived class will provide its own implementation if required.
		}

		/**
		 * Function that allows child controller access to model data
		 * after the data has been saved.
		 *
		 * @param   JModel  &$model     The data model object.
		 * @param   array   $validData  The validated data.
		 *
		 * @return  void
		 *
		 * @since   11.1
		 */
		protected function postSaveHook(JModel &$model, $validData = array())
		{
			$this->_postSaveHook($model, $validData);
		}
	}

} else {

	# ...on Joomla 3.x a JModelLegacy object is expected.

	abstract class JemControllerForm extends JControllerForm
	{
		/**
		 * Function that allows child controller access to model data
		 * after the data has been saved.
		 *
		 * @see    JemControllerForm::postSaveHook()
		 *
		 * @since  JEM 2.1.5
		 */
		protected function _postSaveHook($model, $validData = array())
		{
			// Derived class will provide its own implementation if required.
		}

		/**
		 * Function that allows child controller access to model data
		 * after the data has been saved. - wrapper
		 *
		 * @param   JModelLegacy  $model      The data model object.
		 * @param   array         $validData  The validated data.
		 *
		 * @return  void
		 *
		 * @since   12.2
		 */
		protected function postSaveHook(JModelLegacy $model, $validData = array())
		{
			$this->_postSaveHook($model, $validData);
		}
	}
}

?>