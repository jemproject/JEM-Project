<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;

class JemControllerTypes extends AdminController
{
    protected $text_prefix = 'COM_JEM_TYPES';

    public function getModel($name = 'Type', $prefix = 'JemModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function saveOrderAjax()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.edit.state', 'com_jem') && !$user->authorise('core.admin', 'com_jem')) {
            echo '0';
            $app->close();
        }

        $cid = $app->input->get('cid', array(), 'array');
        $order = $app->input->get('order', array(), 'array');
        ArrayHelper::toInteger($cid);
        ArrayHelper::toInteger($order);

        if (empty($cid) || count($cid) !== count($order)) {
            echo '0';
            $app->close();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        foreach ($cid as $index => $id) {
            if ($id <= 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_types'))
                ->set($db->quoteName('ordering') . ' = ' . (int) ($order[$index] ?? ($index + 1)))
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $db->execute();
        }

        echo '1';
        $app->close();
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

        $model = $this->getModel('type');

        if ($model->delete($cid)) {
            $app->enqueueMessage(Text::plural('COM_JEM_TYPES_N_ITEMS_DELETED', count($cid)));
        } else {
            $app->enqueueMessage($model->getError(), 'error');
        }

        $this->setRedirect('index.php?option=com_jem&view=types');
    }
}
