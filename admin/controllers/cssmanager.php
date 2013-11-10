<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */
defined('_JEXEC') or die();

jimport('joomla.application.component.controlleradmin');

/**
 * JEM Component Cssmanager Controller
 */
class JEMControllerCssmanager extends JControllerAdmin
{

	/**
	 * Proxy for getModel.
	 */
	public function getModel($name = 'Cssmanager', $prefix = 'JEMModel', $config = array())
	{
		$model = parent::getModel($name, $prefix, array(
				'ignore_request' => true
		));
		return $model;
	}

	/**
	 *
	 */
	public function cancel()
	{
		$this->setRedirect('index.php?option=com_jem&view=jem');
	}
}
