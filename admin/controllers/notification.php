<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class JemControllerNotification extends BaseController
{
    public function retry()
    {
        return $this->perform('retry');
    }

    public function resend()
    {
        return $this->perform('resend');
    }

    private function perform($mode)
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        if (!JemHelperBackend::canManage('jem.notifications.resend')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $app = Factory::getApplication();
        $id = $app->input->getInt('notification_id');
        $service = new JemNotificationService();
        $item = $service->getById($id);
        $valid = $item && (($mode === 'retry' && in_array($item->state, array('queued', 'failed'), true)) || ($mode === 'resend' && $item->state === 'sent'));
        $success = false;
        if ($valid) {
            PluginHelper::importPlugin('jem');
            $results = $app->triggerEvent('onJemNotificationAction', array($id, $mode, (int) $app->getIdentity()->id, 'admin'));
            $success = in_array(true, $results, true);
        }
        $app->enqueueMessage(Text::_($success ? 'COM_JEM_NOTIFICATION_ACTION_SUCCESS' : 'COM_JEM_NOTIFICATION_ACTION_FAILED'), $success ? 'message' : 'warning');
        $returnView = $app->input->getCmd('return_view', 'notificationhistory');
        if ($returnView === 'attendee') {
            $redirect = 'index.php?option=com_jem&view=attendee&id=' . $app->input->getInt('registration_id')
                . '&eventid=' . $app->input->getInt('event_id');
        } else {
            $redirect = 'index.php?option=com_jem&view=notificationhistory';
        }
        $this->setRedirect(Route::_($redirect, false));
        return $success;
    }
}
