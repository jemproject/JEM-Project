<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

abstract class JemControllerForm extends FormController
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
	 * @param   BaseDatabaseModel   $model      The data model object.
	 * @param   array         		$validData  The validated data.
	 *
	 * @return  void
	 *
	 * @since   12.2
	 */
	protected function postSaveHook(BaseDatabaseModel $model, $validData = array())
	{
		$this->_postSaveHook($model, $validData);
	}
}

?>
