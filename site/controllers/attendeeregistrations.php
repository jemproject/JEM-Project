<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Global attendee registrations controller.
 */
class JemControllerAttendeeregistrations extends BaseController
{
    private function triggerRegistrationStatusMail($dispatcher, $attendee, int $registrationId, ?int $status = null, bool $userOnly = false): void
    {
        $status = $status ?? (int) ($attendee->status ?? 0);

        if ($status === 1 && (int) ($attendee->waiting ?? 0) === 1) {
            $status = 2;
        }

        switch ($status) {
            case -1:
                $dispatcher->triggerEvent('onEventUserUnregistered', array($attendee->event, false, $registrationId));
                break;
            case 2:
                $dispatcher->triggerEvent('onUserOnOffWaitinglist', array($registrationId));
                break;
            default:
                $dispatcher->triggerEvent('onEventUserRegistered', array($registrationId, $attendee->places, $userOnly));
                break;
        }
    }

    private function assertCanManageAttendeeRegistrations()
    {
        $app = Factory::getApplication();
        $user = JemFactory::getUser();

        if (!$user->get('id')) {
            $uri = Uri::getInstance();
            $app->enqueueMessage(Text::_('COM_JEM_ATTENDEE_REGISTRATIONS_LOGIN_REQUIRED'), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));
            $app->close();
        }

        if (!$user->authorise('core.manage', 'com_jem')) {
            $app->enqueueMessage(Text::_('COM_JEM_ATTENDEE_REGISTRATIONS_NO_ACCESS'), 'warning');
            $app->redirect(Route::_('index.php', false));
            $app->close();
        }
    }

    public function setstatus()
    {
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->input;

        $this->assertCanManageAttendeeRegistrations();

        $id = $input->getInt('registration_id', 0);
        $status = $input->getInt('registration_status', 0);
        $itemId = $input->getInt('Itemid', 0);

        $model = $this->getModel('attendee');
        $model->setId($id);
        $attendee = $model->getData();

        if (empty($attendee->id)) {
            throw new Exception(Text::_('COM_JEM_MISSING_ATTENDEE_ID'), 404);
        }

        if ($model->setRegistrationStatus($status)) {
            PluginHelper::importPlugin('jem');
            $dispatcher = JemFactory::getDispatcher();

            $this->triggerRegistrationStatusMail($dispatcher, $attendee, $id, $status);

            $msg = Text::_('COM_JEM_REGISTERED_USERS_CHANGED');
            $type = 'message';
        } else {
            $msg = $model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED');
            $type = 'error';
        }

        $url = 'index.php?option=com_jem&view=attendeeregistrations';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        $this->setRedirect(Route::_($url, false), $msg, $type);
    }

    public function renotify()
    {
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->input;

        $this->assertCanManageAttendeeRegistrations();

        $ids = $input->get('registration_ids', array(), 'array');
        $id = $input->getInt('registration_id', 0);
        $itemId = $input->getInt('Itemid', 0);

        if ($id > 0) {
            $ids[] = $id;
        }

        $ids = array_unique(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            $this->setRedirect(Route::_('index.php?option=com_jem&view=attendeeregistrations' . ($itemId ? '&Itemid=' . $itemId : ''), false), Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
            return;
        }

        $model = $this->getModel('attendee');
        PluginHelper::importPlugin('jem');
        $dispatcher = JemFactory::getDispatcher();
        $sent = 0;

        foreach ($ids as $id) {
            $model->setId($id);
            $attendee = $model->getData();

            if (empty($attendee->id)) {
                continue;
            }

            $this->triggerRegistrationStatusMail($dispatcher, $attendee, $id, null, true);
            ++$sent;
        }

        if (!PluginHelper::isEnabled('jem', 'mailer')) {
            $app->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
        }

        $url = 'index.php?option=com_jem&view=attendeeregistrations';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        $this->setRedirect(Route::_($url, false), Text::plural('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFIED_N', $sent));
    }

    public function batch()
    {
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->input;
        $itemId = $input->getInt('Itemid', 0);
        $action = trim((string) $input->getString('batch_action', ''));
        $ids = array_unique(array_filter(array_map('intval', $input->get('registration_ids', array(), 'array'))));
        $url = 'index.php?option=com_jem&view=attendeeregistrations';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        $this->assertCanManageAttendeeRegistrations();

        if (!$ids) {
            $this->setRedirect(Route::_($url, false), Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
            return;
        }

        if ($action === 'renotify') {
            $input->set('registration_id', 0);
            $this->renotify();
            return;
        }

        if (!preg_match('/^status:(-1|0|1|2)$/', $action)) {
            $this->setRedirect(Route::_($url, false), Text::_('COM_JEM_ATTENDEE_REGISTRATION_BATCH_INVALID_ACTION'), 'warning');
            return;
        }

        $status = (int) substr($action, 7);

        if (!in_array($status, array(-1, 0, 1, 2), true)) {
            $this->setRedirect(Route::_($url, false), Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'), 'warning');
            return;
        }

        $model = $this->getModel('attendee');
        PluginHelper::importPlugin('jem');
        $dispatcher = JemFactory::getDispatcher();
        $changed = 0;

        foreach ($ids as $id) {
            $model->setId($id);
            $attendee = $model->getData();

            if (empty($attendee->id)) {
                continue;
            }

            if ($model->setRegistrationStatus($status)) {
                ++$changed;

                $this->triggerRegistrationStatusMail($dispatcher, $attendee, $id, $status);
            }
        }

        if (!PluginHelper::isEnabled('jem', 'mailer')) {
            $app->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
        }

        $this->setRedirect(Route::_($url, false), Text::plural('COM_JEM_ATTENDEE_REGISTRATION_BATCH_CHANGED_N', $changed));
    }
}
?>
