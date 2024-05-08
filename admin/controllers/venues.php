<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Controller: Venues
 */
class JemControllerVenues extends AdminController
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 */
	protected $text_prefix = 'COM_JEM_VENUES';


	/**
	 * Proxy for getModel.
	 */
	public function getModel($name = 'Venue', $prefix = 'JemModel', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);
		return $model;
	}

	/**
	 * logic for remove venues
	 *
	 * @access public
	 */
	public function remove()
	{
		// Check for token
		Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));
		
		$app = Factory::getApplication();
		$user = Factory::getApplication()->getIdentity();
		$jinput = $app->input;
		$cid = $jinput->get('cid',array(),'array');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			throw new Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
		} else {
			$model = $this->getModel('venue');

			ArrayHelper::toInteger($cid);

			// trigger delete function in the model
			$result = $model->delete($cid);
			if($result['removed'])
			{
				$app->enqueueMessage(Text::plural($this->text_prefix.'_N_ITEMS_DELETED',$result['removedCount']));
			}
			if($result['error'])
			{
				$app->enqueueMessage(Text::_('COM_JEM_VENUES_UNABLETODELETE'),'warning');

				foreach ($result['error'] AS $error)
				{
					$html = array();
					$html[] = '<span class="label label-info">'.$error[0].'</span>';
					$html[] = '<br>';
					unset($error[0]);
					$html[] = implode('<br>', $error);
					$app->enqueueMessage(implode("\n",$html),'warning');
				}
			}

            $this->postDeleteHook($model,$cid);
		}

		$cache = Factory::getCache('com_jem');
		$cache->clean();

		$this->setRedirect( 'index.php?option=com_jem&view=venues');
	}
}
