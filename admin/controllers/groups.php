<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Language\Text;

/**
 * JEM Component Groups Controller
 *
 */
class JemControllerGroups extends AdminController
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_GROUPS';


	/**
	 * Proxy for getModel.
	 *
	 */
	public function getModel($name = 'Group', $prefix = 'JemModel', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);
		return $model;
	}

	/**
	 * logic to remove a group
	 *
	 * @access public
	 * @return void
	 *
	 */
	public function remove()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit('Invalid Token');

		$jinput = Factory::getApplication()->input;
		$cid = $jinput->get('cid',  0, 'array');

		if (!is_array($cid) || count($cid) < 1) {
			throw new Exception(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 500);
		}

		$total = count($cid);

		$model = $this->getModel('groups');

		if(!$model->delete($cid)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$msg = $total.' '.Text::_('COM_JEM_GROUPS_DELETED');

		$this->setRedirect('index.php?option=com_jem&view=groups', $msg);
	}
}
?>
