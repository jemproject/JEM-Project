<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;

/**
 * JEM Component Cssmanager Controller
 */
class JemControllerCssmanager extends AdminController
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('setlinenumber', 		'linenumber');
		$this->registerTask('disablelinenumber', 	'linenumber');
	}


	/**
	 * Proxy for getModel.
	 */
	public function getModel($name = 'Cssmanager', $prefix = 'JemModel', $config = array())
	{
		$model = parent::getModel($name, $prefix, array('ignore_request' => true));
		return $model;
	}

	/**
	 *
	 */
	public function cancel()
	{
		$this->setRedirect('index.php?option=com_jem&view=main');
	}

	public function back()
	{
		$this->setRedirect('index.php?option=com_jem&view=main');
	}
	/**
	 *
	 */
	public function linenumber()
	{
		$task  = Factory::getApplication()->input->get('task', '');
		$model = $this->getModel();

		switch ($task)
		{
			case 'setlinenumber' :
				$model->setStatusLinenumber(1);
				break;

			default :
				$model->setStatusLinenumber(0);
				break;
		}

		$this->setRedirect('index.php?option=com_jem&view=cssmanager');
	}

}
