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
    public function resend()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $registrationId = $app->input->getInt('registration_id');
        $notificationId = $app->input->getInt('notification_id');
        $redirect = Route::_('index.php?option=com_jem&view=registration&id=' . $registrationId, false);
        $service = new JemNotificationService();
        $registration = $service->getOwnedRegistration($registrationId, (int) $user->id);
        $notification = $service->getById($notificationId);
        $policy = $service->canUserResend($registrationId, (int) $user->id);

        if (!$registration || !$notification || !$policy->allowed
            || $notification->state !== JemNotificationService::STATE_SENT
            || (int) $notification->registration_id !== $registrationId
            || $notification->recipient_type !== 'user'
            || (int) $notification->recipient_user_id !== (int) $user->id) {
            $app->enqueueMessage(Text::_('COM_JEM_NOTIFICATION_RESEND_NOT_ALLOWED'), 'warning');
            $this->setRedirect($redirect);
            return false;
        }

        PluginHelper::importPlugin('jem');
        $results = $app->triggerEvent('onJemNotificationAction', array($notificationId, 'resend', (int) $user->id, 'user'));
        $success = in_array(true, $results, true);
        $app->enqueueMessage(Text::_($success ? 'COM_JEM_NOTIFICATION_RESEND_SUCCESS' : 'COM_JEM_NOTIFICATION_ACTION_FAILED'), $success ? 'message' : 'warning');
        $this->setRedirect($redirect);
        return $success;
    }
}
