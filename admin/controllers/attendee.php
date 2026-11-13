<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Response\JsonResponse;

/**
 * Controller: Attendee
 */
class JemControllerAttendee extends BaseController
{
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        // Register Extra task
        $this->registerTask('add',       'edit');
        $this->registerTask('apply',     'save');
        $this->registerTask('save2new',  'save');
        $this->registerTask('save2copy', 'save');
    }

    /** @return void Build and retain an authorised relocation review; never trust browser totals. */
    public function relocationPreview(): void
    {
        $this->relocationResponse(false);
    }

    /** @return void Confirm the retained review, then queue its notification after commit. */
    public function relocationConfirm(): void
    {
        $this->relocationResponse(true);
    }

    /** @param bool $confirm Whether to commit the reviewed change. @return void JSON response for the attendee editor. */
    private function relocationResponse(bool $confirm): void
    {
        $app = Factory::getApplication();
        try {
            if (!Session::checkToken('post')) {
                throw new DomainException('JINVALID_TOKEN');
            }
            $this->assertCanManageAttendees();
            require_once JPATH_SITE . '/components/com_jem/classes/registrationrelocation.class.php';
            $service = new JemRegistrationRelocation(Factory::getContainer()->get('DatabaseDriver'));
            $id = $app->input->post->getInt('id');
            $actor = $app->getIdentity();
            $session = $app->getSession();
            $key = 'com_jem.relocation.' . $id;
            if (!$confirm) {
                $input = ['areas' => $app->input->post->get('capacity_areas', [], 'array'),
                    'admissions' => $app->input->post->get('admissions', [], 'array'),
                    'reason' => $app->input->post->getString('relocation_reason'),
                    'choice' => $app->input->post->getCmd('relocation_choice', 'standard'),
                    'accepted' => $app->input->post->getBool('relocation_accepted')];
                $review = $service->preview($id, $input, $actor);
                $token = bin2hex(random_bytes(24));
                $session->set($key, ['input' => $input, 'review' => $review, 'token' => $token,
                    'actor' => (int) $actor->id, 'expires' => time() + 900,
                    'operation' => JemRegistrationIdentity::generateOperationReference()]);
                echo new JsonResponse(['review' => $review, 'token' => $token]);
            } else {
                $saved = $session->get($key);
                if (!is_array($saved) || $saved['expires'] < time() || $saved['actor'] !== (int) $actor->id
                    || !hash_equals($saved['token'], $app->input->post->getString('relocation_token'))) {
                    throw new DomainException('COM_JEM_RELOCATION_REVIEW_EXPIRED');
                }
                $result = $service->confirm($id, $saved['input'], $actor, $saved['review']['fingerprint'], $saved['operation']);
                // Released areas may allow a queued reservation to be promoted by the existing policy.
                try {
                    JemHelper::reconcileWaitingList((int) $result->after->event, ['source' => 'administrator.attendee.relocation']);
                } catch (Throwable $waitingError) {
                    $app->enqueueMessage(Text::_('COM_JEM_RELOCATION_WAITING_REVIEW'), 'warning');
                }
                $notified = false;
                try {
                    PluginHelper::importPlugin('jem');
                    if (PluginHelper::isEnabled('jem', 'mailer')) {
                        $responses = JemFactory::getDispatcher()->triggerEvent('onJemRegistrationRelocated', [$id]);
                        $notified = in_array(true, (array) $responses, true);
                    }
                } catch (Throwable $notificationError) {
                    // The reservation has committed. A delivery problem must not repeat or reverse it.
                    $notified = false;
                }
                $app->enqueueMessage(Text::_('COM_JEM_RELOCATION_SAVED'), 'message');
                $app->enqueueMessage(Text::_($notified ? 'COM_JEM_RELOCATION_QUEUED' : 'COM_JEM_RELOCATION_NOTIFICATION_FAILED'),
                    $notified ? 'message' : 'warning');
                try {
                    Factory::getCache('com_jem')->clean();
                } catch (Throwable $cacheError) {
                    // A cache failure must not report the committed relocation as failed.
                }
                echo new JsonResponse(['saved' => true, 'notified' => $notified]);
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage() === 'registration_change_unavailable'
                ? 'COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE' : $exception->getMessage();
            echo new JsonResponse(null, Text::_($message), true);
        }
        $app->close();
    }

    /** @return void Retry queuing the latest saved relocation notice without moving any places. */
    public function relocationNotify(): void
    {
        Session::checkToken('post') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();
        if (!JemHelperBackend::canManage('jem.notifications.resend')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $app = Factory::getApplication();
        $id = $app->input->post->getInt('id');
        $queued = false;
        try {
            PluginHelper::importPlugin('jem');
            if (PluginHelper::isEnabled('jem', 'mailer')) {
                $queued = in_array(true, (array) JemFactory::getDispatcher()->triggerEvent('onJemRegistrationRelocated', [$id]), true);
            }
        } catch (Throwable $error) {
            $queued = false;
        }
        $app->enqueueMessage(Text::_($queued ? 'COM_JEM_RELOCATION_QUEUED' : 'COM_JEM_RELOCATION_NOTIFICATION_FAILED'), $queued ? 'message' : 'warning');
        $this->setRedirect('index.php?option=com_jem&view=attendee&id=' . $id);
    }

    /** @return void Enforce backend attendee management permission. */
    private function assertCanManageAttendees()
    {
        if (!JemHelperBackend::canManage('jem.attendees.manage')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * redirect to events page
     */
    public function back() {
        $this->setRedirect('index.php?option=com_jem&view=attendees&eventid='. Factory::getApplication()->input->getInt('event', 0));
    }

    /**
     * logic for cancel an action
     *
     * @access public
     * @return void
     */
    public function cancel() {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        $attendee = Table::getInstance('jem_register', '');
        $attendee->bind(Factory::getApplication()->input->post->getArray(/*get them all*/));
        $attendee->checkin();

        $this->setRedirect('index.php?option=com_jem&view=attendees&eventid='. Factory::getApplication()->input->getInt('event', 0));
    }

    /**
     * saves the attendee in the database
     *
     * @access public
     * @return void
     */
    public function save() {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        // Defining JInput
        $jinput = Factory::getApplication()->input;

        // retrieving task "apply"
        $task = $jinput->getCmd('task');

        // Retrieving $post
        $post = $jinput->post->getArray(/*get them all*/);
        // This field is plain text; the model validates it before any write.
        if ($jinput->post->exists('comment')) {
            $post['comment'] = $jinput->post->get('comment', '', 'raw');
        }

        // Retrieving email-setting
        $sendemail = $jinput->getInt('sendemail','0');

        // Retrieving event-id
        $eventid = $jinput->getInt('event');

        // the id in case of edit
        $id = (!empty($post['id']) ? $post['id'] : 0);

        $model = $this->getModel('attendee');

        // Handle task 'save2copy' - reset id to store as new record, then like 'apply'.
        if ($task == 'save2copy') {
            $post['id'] = 0;
            $id = 0;
            $task = 'apply';
        }

        // handle changing the user - must also trigger onEventUserUnregistered
        $uid = (!empty($post['uid']) ? $post['uid'] : 0);
        $old_data = null;
        if ($uid && $id) {
            $model->setId($id);
            $old_data = $model->getData();
        }
        $old_uid = !empty($old_data->uid) ? (int) $old_data->uid : 0;
        $manualPromotion = $old_data
            && JemRegistrationTransition::logicalStatus($old_data) === JemRegistrationTransition::WAITING_LIST
            && (int) ($post['status'] ?? 0) === JemRegistrationTransition::ATTENDING;

        // Keep the row waiting while saving other edits. The central service
        // performs the capacity-locked promotion after the save succeeds.
        if ($manualPromotion) {
            $post['status'] = JemRegistrationTransition::WAITING_LIST;
        }

        if ($row = $model->store($post)) {
            $transition = JemRegistrationTransition::create(
                $old_data,
                $row,
                (int) Factory::getApplication()->getIdentity()->id,
                'administrator.attendee.edit'
            );

            if ($sendemail == 1) {
                PluginHelper::importPlugin('jem');
                $dispatcher = JemFactory::getDispatcher();
                $eventChanged = $old_data && (int) $old_data->event !== (int) $row->event;
                $registrationChanged = !$old_data
                    || $eventChanged
                    || $old_uid !== (int) $row->uid
                    || JemRegistrationTransition::logicalStatus($old_data) !== JemRegistrationTransition::logicalStatus($row)
                    || (int) ($old_data->places ?? 0) !== (int) ($row->places ?? 0)
                    || (int) ($old_data->revision ?? 0) !== (int) ($row->revision ?? 0)
                    || (string) ($old_data->comment ?? '') !== (string) ($row->comment ?? '');

                // there was a user and it's overwritten by a new user -> send unregister mails
                if ($old_uid && (($old_uid != $uid) || $eventChanged)) {
                    JemRegistrationTransition::dispatchDeletionMail($dispatcher, $old_data);
                }
                // Notify a new user or an existing user whose registration data changed.
                if ($uid && $registrationChanged && !$manualPromotion) {
                    JemRegistrationTransition::dispatchStatusMail($dispatcher, $row, $transition, false, true);
                }
                // but show warning if mailer is disabled
                if (!PluginHelper::isEnabled('jem', 'mailer')) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
                }
            }

            PluginHelper::importPlugin('actionlog', 'jem');
            $dispatcher = JemFactory::getDispatcher();
            $dispatcher->triggerEvent('onJemAfterAttendeeSave', array($row, empty($id)));

            if ($old_data) {
                JemRegistrationTransition::dispatchAudit($dispatcher, array($transition));

                if (JemRegistrationTransition::releasesCapacity($old_data, $row)) {
                    JemHelper::reconcileWaitingList((int) $old_data->event, array(
                        'source' => 'administrator.attendee.edit',
                        'excludeIds' => JemRegistrationTransition::logicalStatus($row) === JemRegistrationTransition::WAITING_LIST
                            ? array((int) $row->id)
                            : array(),
                    ));
                }
            }

            if ($manualPromotion) {
                $promotion = JemWaitingListPromotion::promote((int) $row->event, array(
                    'mode' => JemWaitingListPromotion::MODE_MANUAL,
                    'registrationIds' => array((int) $row->id),
                    'notify' => $sendemail === 1,
                    'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                    'source' => 'administrator.attendee.manual',
                ));

                if (!$promotion->success) {
                    $reason = $promotion->reason === 'capacity_exceeded'
                        ? 'COM_JEM_WAITINGLIST_PROMOTION_CAPACITY_EXCEEDED'
                        : 'COM_JEM_WAITINGLIST_PROMOTION_FAILED';
                    $this->setRedirect(
                        'index.php?option=com_jem&view=attendee&hidemainmenu=1&id=' . (int) $row->id . '&eventid=' . (int) $row->event,
                        Text::_($reason),
                        'error'
                    );
                    return;
                }

                if ($promotion->reason === 'notification_failed') {
                    Factory::getApplication()->enqueueMessage(
                        Text::_('COM_JEM_WAITINGLIST_PROMOTION_NOTIFICATION_FAILED'),
                        'warning'
                    );
                }
            }

            switch ($task) {
            case 'apply':
                // Redirect back to the edit screen.
                $link = 'index.php?option=com_jem&view=attendee&hidemainmenu=1&id='.$row->id.'&eventid='.$row->event;
                break;

            case 'save2new':
                // Redirect back to the edit screen for new record.
                $link = 'index.php?option=com_jem&view=attendee&hidemainmenu=1&eventid='.$row->event;
                break;

            default:
                // Redirect to the list screen.
                $link = 'index.php?option=com_jem&view=attendees&eventid='.$row->event;
                break;
            }
            $msg = Text::_('COM_JEM_ATTENDEE_SAVED');

            $cache = Factory::getCache('com_jem');
            $cache->clean();
        } else {
            $msg     = '';
            $link     = 'index.php?option=com_jem&view=attendees&eventid='.$eventid;
        }
        $this->setRedirect($link, $msg);
    }

    public function selectUser() {
        $this->assertCanManageAttendees();

        $jinput = Factory::getApplication()->input;
        $jinput->set('view', 'userelement');
        parent::display();
    }

    /**
     * Return the admission catalogue and current inventory for the selected
     * event, booking holder and optional existing registration.
     */
    public function pricingOptions()
    {
        $this->assertCanManageAttendees();
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
            throw new Exception(Text::_('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY'), 403);
        }
        $app = Factory::getApplication();
        JemHelper::setNoStoreHeaders();
        $app->sendHeaders();
        $eventId = $app->input->getInt('event', 0);
        $userId = $app->input->getInt('uid', 0);
        $registrationId = $app->input->getInt('id', 0);

        try {
            $data = $this->getModel('attendee')->getPricingData($eventId, $userId, $registrationId);
            echo new JsonResponse($data);
        } catch (Throwable $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }
}
