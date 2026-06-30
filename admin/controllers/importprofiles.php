<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

class JemControllerImportprofiles extends AdminController
{
    protected $text_prefix = 'COM_JEM_IMPORT_PROFILES';

    public function getModel($name = 'Importprofile', $prefix = 'JemModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function remove()
    {
        Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.delete', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $cid = $app->input->get('cid', array(), 'array');

        if (!is_array($cid) || count($cid) < 1) {
            throw new \Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
        }

        ArrayHelper::toInteger($cid);
        $cid = array_filter($cid);

        if (empty($cid)) {
            throw new \Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
        }

        $model = $this->getModel('importprofile');

        if ($model->delete($cid)) {
            $app->enqueueMessage(Text::plural('COM_JEM_IMPORT_PROFILES_N_ITEMS_DELETED', count($cid)));
        } else {
            $app->enqueueMessage($model->getError(), 'error');
        }

        $this->setRedirect('index.php?option=com_jem&view=importprofiles');
    }
}
