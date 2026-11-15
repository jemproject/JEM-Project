<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/helpers/attendeeform.php';
require_once dirname(__DIR__) . '/classes/attendeeeditoraccess.class.php';
require_once dirname(__DIR__) . '/classes/registrationrelocation.class.php';

/** Existing-reservation editing for event-scoped frontend managers. */
class JemControllerEditattendee extends BaseController
{
    /** @return void Save permitted fields and notify after the authoritative writer commits. */
    public function save(): void
    {
        $app = Factory::getApplication();
        $this->requirePost();
        $id = $app->input->post->getInt('id');
        $model = $this->getModel('editattendee');
        $model->setId($id);
        $before = clone $model->getData();
        $post = $app->input->post->getArray();
        $post['comment'] = $app->input->post->get('comment', $before->comment, 'raw');
        $notify = $app->input->post->getBool('sendemail');
        $promotion = (int) $before->status === 2 && (string) ($post['status'] ?? '') === '1';
        if ($promotion) {
            $post['status'] = 2;
        }
        $messages = $app->getMessageQueue();
        $row = $model->store($post);
        if (!$row) {
            JemAttendeeFormHelper::remember($post, (int) $before->event, $id);
            JemAttendeeFormHelper::reportError($model->getError() ?: Text::_('COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE'), $messages);
            $this->setRedirect($this->editUrl($id));
            return;
        }
        JemAttendeeFormHelper::clear((int) $before->event, $id);
        $transition = JemRegistrationTransition::create($before, $row, (int) JemFactory::getUser()->id, 'site.attendee.edit');
        if ((int) $row->revision !== (int) $before->revision) {
            try {
                PluginHelper::importPlugin('actionlog', 'jem');
                JemRegistrationTransition::dispatchAudit(JemFactory::getDispatcher(), [$transition]);
                if ($notify && !$promotion) {
                    PluginHelper::importPlugin('jem');
                    JemRegistrationTransition::dispatchStatusMail(JemFactory::getDispatcher(), $row, $transition, false, true);
                    if (!PluginHelper::isEnabled('jem', 'mailer')) {
                        $app->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'warning');
                    }
                }
                if (JemRegistrationTransition::releasesCapacity($before, $row)) {
                    JemHelper::reconcileWaitingList((int) $row->event, ['source' => 'site.attendee.edit',
                        'excludeIds' => JemRegistrationTransition::logicalStatus($row) === 2 ? [$id] : []]);
                }
            } catch (Throwable $error) {
                $app->enqueueMessage(Text::_('COM_JEM_FE_RESERVATION_FOLLOWUP'), 'warning');
            }
        }
        if ($promotion) {
            $result = JemWaitingListPromotion::promote((int) $row->event, [
                'mode' => JemWaitingListPromotion::MODE_MANUAL, 'registrationIds' => [$id],
                'notify' => $notify, 'actorId' => (int) JemFactory::getUser()->id, 'source' => 'site.attendee.manual']);
            if (!$result->success || $result->reason === 'notification_failed') {
                $app->enqueueMessage(Text::_('COM_JEM_WAITINGLIST_PROMOTION_FAILED'), 'warning');
            }
        }
        try { Factory::getCache('com_jem')->clean(); } catch (Throwable $error) { /* The save is already committed. */ }
        $this->setRedirect($this->editUrl($id), Text::_('COM_JEM_ATTENDEE_SAVED'));
    }

    /** @return void Build a server-retained relocation review. */
    public function relocationPreview(): void
    {
        $this->relocation(false);
    }

    /** @return void Commit only the exact authorised and confirmed review. */
    public function relocationConfirm(): void
    {
        $this->relocation(true);
    }

    /** @param bool $commit Whether to commit the previous review. @return void Private JSON response. */
    private function relocation(bool $commit): void
    {
        $app = Factory::getApplication();
        JemHelper::setNoStoreHeaders();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->sendHeaders();
        try {
            $this->requirePost();
            $db = Factory::getContainer()->get('DatabaseDriver');
            $actor = JemFactory::getUser();
            $id = $app->input->post->getInt('id');
            $eventId = JemAttendeeEditorAccess::assertReservation($db, $actor, $id);
            $service = new JemRegistrationRelocation($db, 'site.attendee.relocation');
            $key = 'com_jem.site.relocation.' . $id;
            if (!$commit) {
                $input = ['areas' => $app->input->post->get('capacity_areas', [], 'array'),
                    'admissions' => $app->input->post->get('admissions', [], 'array'),
                    'reason' => $app->input->post->getString('relocation_reason'),
                    'choice' => $app->input->post->getCmd('relocation_choice', 'standard'),
                    'accepted' => $app->input->post->getBool('relocation_accepted')];
                $review = $service->preview($id, $input, $actor);
                $token = bin2hex(random_bytes(24));
                $app->getSession()->set($key, ['input' => $input, 'review' => $review, 'token' => $token,
                    'actor' => (int) $actor->id, 'event' => $eventId, 'expires' => time() + 900,
                    'operation' => JemRegistrationIdentity::generateOperationReference()]);
                echo new JsonResponse(['review' => $review, 'token' => $token]);
            } else {
                $saved = $app->getSession()->get($key);
                if (!is_array($saved) || $saved['expires'] < time() || $saved['actor'] !== (int) $actor->id
                    || $saved['event'] !== $eventId || !hash_equals($saved['token'], $app->input->post->getString('relocation_token'))) {
                    throw new DomainException('COM_JEM_RELOCATION_REVIEW_EXPIRED');
                }
                $service->confirm($id, $saved['input'], $actor, $saved['review']['fingerprint'], $saved['operation']);
                try {
                    JemHelper::reconcileWaitingList($eventId, ['source' => 'site.attendee.relocation']);
                    PluginHelper::importPlugin('jem');
                    $notified = PluginHelper::isEnabled('jem', 'mailer')
                        && in_array(true, (array) JemFactory::getDispatcher()->triggerEvent('onJemRegistrationRelocated', [$id]), true);
                    if (!$notified) { $app->enqueueMessage(Text::_('COM_JEM_RELOCATION_NOTIFICATION_FAILED'), 'warning'); }
                } catch (Throwable $error) {
                    $app->enqueueMessage(Text::_('COM_JEM_FE_RESERVATION_FOLLOWUP'), 'warning');
                }
                $app->enqueueMessage(Text::_('COM_JEM_RELOCATION_SAVED'), 'message');
                try { Factory::getCache('com_jem')->clean(); } catch (Throwable $error) { /* Already committed. */ }
                echo new JsonResponse(['saved' => true]);
            }
        } catch (Throwable $error) {
            $message = $error instanceof DomainException || $error->getCode() === 403
                ? Text::_($error->getMessage()) : Text::_('COM_JEM_RELOCATION_FAILED');
            echo new JsonResponse(null, $message, true);
        }
        $app->close();
    }

    /** @return void Reject unauthenticated, non-POST or CSRF-invalid operations. */
    private function requirePost(): void
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_jem', JPATH_ADMINISTRATOR, 'en-GB', true);
        $app->getLanguage()->load('com_jem', JPATH_ADMINISTRATOR, null, true);
        if ($app->input->server->getString('REQUEST_METHOD') !== 'POST'
            || !JemFactory::getUser()->id || !Session::checkToken('post')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /** @param int $id Registration ID. @return string Safe internal frontend editor route. */
    private function editUrl(int $id): string
    {
        return Route::_('index.php?option=com_jem&view=editattendee&layout=default&id=' . $id, false);
    }
}
