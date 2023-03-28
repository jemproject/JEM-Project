<?php
/**
 * @version 2.3.12
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

jimport('joomla.application.component.controlleradmin');

/**
 * Controller: Venues
 */
class JemControllerVenues extends JControllerAdmin
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
		JSession::checkToken() or jexit(JText::_('COM_JEM_GLOBAL_INVALID_TOKEN'));
		
		$app = Factory::getApplication();
		$user = Factory::getApplication()->getIdentity();
		$jinput = $app->input;
		$cid = $jinput->get('cid',array(),'array');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			throw new Exception(JText::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
		} else {
			$model = $this->getModel('venue');

			jimport('joomla.utilities.arrayhelper');
			\Joomla\Utilities\ArrayHelper::toInteger($cid);

			// trigger delete function in the model
			$result = $model->delete($cid);
			if($result['removed'])
			{
				$app->enqueueMessage(JText::plural($this->text_prefix.'_N_ITEMS_DELETED',$result['removedCount']));
			}
			if($result['error'])
			{
				$app->enqueueMessage(JText::_('COM_JEM_VENUES_UNABLETODELETE'),'warning');

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

			if (version_compare(JVERSION, '3.0', 'lt')) {
				# postDeleteHook doesn't exists in Joomla 2.x
			} else {
				$this->postDeleteHook($model,$cid);
			}
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$this->setRedirect( 'index.php?option=com_jem&view=venues');
	}
}
