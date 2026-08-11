<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class JemViewRegistration extends JemView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if ((int) $user->id < 1) {
            $return = base64_encode(Uri::getInstance()->toString());
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
            return;
        }

        $this->item = $this->get('Item');
        if (!$this->item) {
            throw new Exception(Text::_('COM_JEM_REGISTRATION_NOT_FOUND'), 404);
        }
        $service = new JemNotificationService();
        $all = $service->getRegistrationNotifications((int) $this->item->id, 50);
        $this->notifications = array_values(array_filter($all, static function ($notification) use ($user) {
            return $notification->recipient_type === 'user'
                && (int) $notification->recipient_user_id === (int) $user->id;
        }));
        $this->latestSent = null;
        foreach ($this->notifications as $notification) {
            if ($notification->state === JemNotificationService::STATE_SENT) {
                $this->latestSent = $notification;
                break;
            }
        }
        $this->resendPolicy = $service->canUserResend((int) $this->item->id, (int) $user->id);
        $this->document->setTitle(Text::_('COM_JEM_REGISTRATION_DETAILS'));
        JemHelper::loadCss('jem');
        parent::display($tpl);
    }
}
