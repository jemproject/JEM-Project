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
        $status = $status ?? JemRegistrationTransition::logicalStatus($attendee);
        $after = clone $attendee;
        JemRegistrationTransition::applyLogicalStatus($after, $status);
        $transition = JemRegistrationTransition::create(
            $attendee,
            $after,
            (int) Factory::getApplication()->getIdentity()->id,
            'site.attendeeregistrations.renotify'
        );

        JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition, $userOnly, true);
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

        $fullControl = $user->authorise('core.admin', 'com_jem');
        $canManage = $user->authorise('core.manage', 'com_jem')
            && $user->authorise('jem.events.access', 'com_jem')
            && $user->authorise('jem.attendees.manage', 'com_jem');

        if (!$fullControl && !$canManage) {
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

        if (!JemRegistrationTransition::isValidStatus($status)) {
            throw new Exception(Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'), 400);
        }

        $after = clone $attendee;
        JemRegistrationTransition::applyLogicalStatus($after, $status);
        $transition = JemRegistrationTransition::create(
            $attendee,
            $after,
            (int) $app->getIdentity()->id,
            'site.attendeeregistrations.edit'
        );

        if ($transition->oldStatus === JemRegistrationTransition::WAITING_LIST
            && $transition->newStatus === JemRegistrationTransition::ATTENDING) {
            $url = 'index.php?option=com_jem&view=attendeeregistrations';

            if ($itemId) {
                $url .= '&Itemid=' . $itemId;
            }

            $this->setRedirect(
                Route::_($url, false),
                Text::_('COM_JEM_WAITINGLIST_USE_PROMOTION_ACTION'),
                'warning'
            );
            return;
        }

        if (!$transition->changed) {
            $msg = Text::_('COM_JEM_REGISTERED_USERS_CHANGED');
            $type = 'message';
        } elseif ($model->setRegistrationStatus($status)) {
            PluginHelper::importPlugin('jem');
            PluginHelper::importPlugin('actionlog', 'jem');
            $dispatcher = JemFactory::getDispatcher();

            JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition);
            JemRegistrationTransition::dispatchAudit($dispatcher, array($transition));

            if (JemRegistrationTransition::releasesCapacity($attendee, $after)) {
                JemHelper::reconcileWaitingList((int) $attendee->event, array(
                    'source' => 'site.attendeeregistrations.edit',
                    'excludeIds' => $status === JemRegistrationTransition::WAITING_LIST
                        ? array((int) $attendee->id)
                        : array(),
                ));
            }

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
        $attendees = array();

        foreach ($ids as $id) {
            $model->setId($id);
            $attendee = $model->getData();

            if (empty($attendee->id)) {
                throw new Exception(Text::_('COM_JEM_MISSING_ATTENDEE_ID'), 404);
            }

            $attendees[(int) $id] = clone $attendee;
        }

        foreach ($attendees as $id => $attendee) {
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

        if ($action === 'promote') {
            $force = $input->getBool('waitinglist_force', false);

            if ($force && !$app->getIdentity()->authorise('core.admin', 'com_jem')) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $model = $this->getModel('attendee');
            $idsByEvent = array();

            foreach ($ids as $id) {
                $model->setId($id);
                $attendee = $model->getData();

                if (empty($attendee->id)) {
                    throw new Exception(Text::_('COM_JEM_MISSING_ATTENDEE_ID'), 404);
                }

                $idsByEvent[(int) $attendee->event][] = (int) $attendee->id;
            }

            if (count($idsByEvent) !== 1) {
                $this->setRedirect(
                    Route::_($url, false),
                    Text::_('COM_JEM_WAITINGLIST_PROMOTION_ONE_EVENT_ONLY'),
                    'warning'
                );
                return;
            }

            $promoted = 0;

            foreach ($idsByEvent as $eventId => $eventRegistrationIds) {
                $result = JemWaitingListPromotion::promote($eventId, array(
                    'mode' => JemWaitingListPromotion::MODE_MANUAL,
                    'registrationIds' => $eventRegistrationIds,
                    'notify' => $input->getBool('waitinglist_notify', true),
                    'force' => $force,
                    'actorId' => (int) $app->getIdentity()->id,
                    'source' => $force ? 'site.attendeeregistrations.force' : 'site.attendeeregistrations.manual',
                ));

                if (!$result->success) {
                    $key = $result->reason === 'capacity_exceeded'
                        ? 'COM_JEM_WAITINGLIST_PROMOTION_CAPACITY_EXCEEDED'
                        : 'COM_JEM_WAITINGLIST_PROMOTION_FAILED';
                    $this->setRedirect(Route::_($url, false), Text::_($key), 'error');
                    return;
                }

                $promoted += count($result->promotedIds);

                if ($result->reason === 'notification_failed') {
                    $app->enqueueMessage(Text::_('COM_JEM_WAITINGLIST_PROMOTION_NOTIFICATION_FAILED'), 'warning');
                }
            }

            $this->setRedirect(
                Route::_($url, false),
                Text::plural('COM_JEM_WAITINGLIST_PROMOTED_N', $promoted)
            );
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
        $transitions = array();
        $changedRows = array();

        // Validate every record before starting the transaction.
        foreach ($ids as $id) {
            $model->setId($id);
            $attendee = $model->getData();

            if (empty($attendee->id)) {
                throw new Exception(Text::_('COM_JEM_MISSING_ATTENDEE_ID'), 404);
            }

            $after = clone $attendee;
            JemRegistrationTransition::applyLogicalStatus($after, $status);
            $transition = JemRegistrationTransition::create(
                $attendee,
                $after,
                (int) $app->getIdentity()->id,
                'site.attendeeregistrations.batch'
            );

            if ($transition->changed) {
                $transitions[] = $transition;
                $changedRows[(int) $id] = $after;
            }
        }

        if ($status === JemRegistrationTransition::ATTENDING
            && array_filter($transitions, static function ($transition) {
                return $transition->oldStatus === JemRegistrationTransition::WAITING_LIST;
            })) {
            $this->setRedirect(
                Route::_($url, false),
                Text::_('COM_JEM_WAITINGLIST_USE_PROMOTION_ACTION'),
                'warning'
            );
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();

            foreach ($transitions as $transition) {
                $model->setId((int) $transition->registrationId);

                if (!$model->setRegistrationStatus((int) $transition->newStatus)) {
                    throw new RuntimeException($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'));
                }
            }

            $db->transactionCommit();
        } catch (Throwable $e) {
            $db->transactionRollback();
            $this->setRedirect(Route::_($url, false), $e->getMessage(), 'error');
            return;
        }

        PluginHelper::importPlugin('jem');
        PluginHelper::importPlugin('actionlog', 'jem');
        $dispatcher = JemFactory::getDispatcher();

        foreach ($transitions as $transition) {
            JemRegistrationTransition::dispatchStatusMail(
                $dispatcher,
                $changedRows[(int) $transition->registrationId],
                $transition
            );
        }

        JemRegistrationTransition::dispatchAudit($dispatcher, $transitions);

        $releasedEvents = array();
        $excludedByEvent = array();

        foreach ($transitions as $transition) {
            if ($transition->oldStatus === JemRegistrationTransition::ATTENDING
                && $transition->newStatus !== JemRegistrationTransition::ATTENDING) {
                $releasedEvents[(int) $transition->eventId] = true;

                if ($transition->newStatus === JemRegistrationTransition::WAITING_LIST) {
                    $excludedByEvent[(int) $transition->eventId][] = (int) $transition->registrationId;
                }
            }
        }

        foreach (array_keys($releasedEvents) as $releasedEventId) {
            JemHelper::reconcileWaitingList($releasedEventId, array(
                'source' => 'site.attendeeregistrations.batch',
                'excludeIds' => $excludedByEvent[$releasedEventId] ?? array(),
            ));
        }

        $changed = count($transitions);

        if (!PluginHelper::isEnabled('jem', 'mailer')) {
            $app->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
        }

        $this->setRedirect(Route::_($url, false), Text::plural('COM_JEM_ATTENDEE_REGISTRATION_BATCH_CHANGED_N', $changed));
    }
}
?>
