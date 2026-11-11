<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

require_once JPATH_SITE . '/components/com_jem/classes/invitationservice.class.php';

/** Composer and capability response endpoints; all mutations require POST and CSRF. */
class JemControllerInvitations extends BaseController
{
    /** @return void Preview the exact recipient snapshot without committing inventory. */
    public function preview(): void
    {
        $this->compose(false);
    }

    /** @return void Commit the reviewed invitations and queue personalised notifications. */
    public function send(): void
    {
        $this->compose(true);
    }

    /** @param bool $send Whether to commit. @return void */
    private function compose(bool $send): void
    {
        $this->requirePost(true);
        $app = Factory::getApplication();
        $input = $app->input->post->get('invitation', [], 'array');
        $session = $app->getSession();
        $key = 'com_jem.invitation.' . $app->getName();
        $form = array_filter($input, static fn ($value, $field) => in_array($field, ['users', 'groups'], true)
            ? is_array($value) && !array_filter($value, static fn ($id) => !is_scalar($id))
            : is_scalar($value), ARRAY_FILTER_USE_BOTH);
        $session->set($key . '.form', $form);
        try {
            if ($send) {
                // The fingerprint is server-owned; browser edits cannot authorise a new audience.
                $input['fingerprint'] = (string) $session->get($key . '.fingerprint', '');
            }
            $report = $this->service()->compose($input, $app->getIdentity(), $send);
            $session->set($key . '.report', $report);
            $session->set($key . '.fingerprint', $send ? '' : $report['fingerprint']);
            $app->enqueueMessage(Text::_($send ? 'COM_JEM_INV_QUEUED' : 'COM_JEM_INV_REVIEW'));
        } catch (Throwable $error) {
            $session->set($key . '.fingerprint', '');
            $session->set($key . '.report', null);
            $this->error($error);
        }
        $this->setRedirect('index.php?option=com_jem&view=invitations&event_id=' . (int) ($input['event_id'] ?? 0));
    }

    /** @return void Rotate and queue a pending invitation link. */
    public function resend(): void
    {
        $this->manage(false);
    }

    /** @return void Withdraw pending holds; confirmed reservations are not silently cancelled. */
    public function revoke(): void
    {
        $this->manage(true);
    }

    /** @param bool $revoke Revoke instead of resend. @return void */
    private function manage(bool $revoke): void
    {
        $this->requirePost(true);
        $app = Factory::getApplication();
        try {
            $this->service()->manage($app->input->post->getInt('id', 0), $app->getIdentity(), $revoke);
            $app->enqueueMessage(Text::_('COM_JEM_INV_UPDATED'));
        } catch (Throwable $error) {
            $this->error($error);
        }
        $this->setRedirect('index.php?option=com_jem&view=invitations&event_id=' . $app->input->post->getInt('event_id', 0));
    }

    /** @return void Explicitly accept/decline; GET never consumes a capability or places. */
    public function respond(): void
    {
        $this->requirePost(false);
        $app = Factory::getApplication();
        $redirect = 'index.php?option=com_jem&view=invitation';
        try {
            $answer = $app->input->post->getCmd('answer');
            if (!in_array($answer, ['accept', 'decline'], true)) {
                throw new DomainException('COM_JEM_INV_INVALID_LINK');
            }
            $token = $app->input->post->getString('invitation_token', '');
            $id = $app->input->post->getInt('invitation_id', 0);
            $proof = $token !== '' ? JemInvitationPolicy::digest($token) : 'owner:' . (int) $app->getIdentity()->id . ':' . $id;
            $review = $app->getSession()->get('com_jem.invitation.response', []);
            if ($answer === 'accept' && (!isset($review['digest'], $review['fingerprint'])
                || !hash_equals($review['digest'], $proof))) {
                throw new DomainException('COM_JEM_INV_PREVIEW_AGAIN');
            }
            $expected = (string) ($review['fingerprint'] ?? '');
            $rows = $token !== ''
                ? $this->service()->respond($token, $answer === 'accept', false, $expected)
                : $this->service()->respondOwned($id, $app->getIdentity(), $answer === 'accept', false, $expected);
            $app->getSession()->set('com_jem.invitation.response', null);
            $app->getSession()->set('com_jem.invitation.holder', [
                'until' => time() + 1800, 'ids' => array_map(static fn ($row) => (int) $row->id, $rows),
            ]);
            $app->enqueueMessage(Text::_($answer === 'accept' ? 'COM_JEM_INV_ACCEPTED' : 'COM_JEM_INV_DECLINED'));
        } catch (Throwable $error) {
            $this->error($error);
            if (!empty($token) && preg_match('/^[a-f0-9]{64}$/D', $token)) {
                $redirect .= '&invitation_token=' . rawurlencode($token);
            } elseif (!empty($id)) {
                $redirect .= '&invitation_id=' . (int) $id;
            }
        }
        $this->setRedirect($redirect);
    }

    /** @return void Download only a confirmed reservation owned by the restricted holder session. */
    public function download(): void
    {
        $this->requirePost(false);
        $app = Factory::getApplication();
        $holder = $app->getSession()->get('com_jem.invitation.holder', []);
        $id = $app->input->post->getInt('id', 0);
        if (($holder['until'] ?? 0) < time() || !in_array($id, $holder['ids'] ?? [], true)) {
            throw new RuntimeException(Text::_('COM_JEM_INV_INVALID_LINK'), 403);
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $row = $db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $id)->loadObject();
        require_once JPATH_SITE . '/components/com_jem/classes/ticketservice.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/ticketpdf.class.php';
        $ticket = (new JemTicketService($db))->invitationDocument($row);
        $bytes = JemTicketPdf::render($ticket);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $app->setHeader('Content-Type', 'application/pdf', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="reservation-' . $row->reference . '.pdf"', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->sendHeaders();
        echo $bytes;
        $app->close();
    }

    /** @param bool $manager Require Joomla identity for organiser operations. @return void */
    private function requirePost(bool $manager): void
    {
        $app = Factory::getApplication();
        if ($app->input->getMethod() !== 'POST' || !Session::checkToken('post')
            || ($manager && empty($app->getIdentity()->id))) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /** @return JemInvitationService Shared transactional service. */
    private function service(): JemInvitationService
    {
        return new JemInvitationService(Factory::getContainer()->get('DatabaseDriver'));
    }

    /** @param Throwable $error Failure; no capability or email is logged. @return void */
    private function error(Throwable $error): void
    {
        $message = $error instanceof DomainException || $error instanceof InvalidArgumentException
            ? Text::_($error->getMessage()) : Text::_('COM_JEM_INV_ERROR');
        Factory::getApplication()->enqueueMessage($message, 'error');
        if (!($error instanceof DomainException)) {
            JemHelper::addLogEntry('Invitation operation failed: ' . get_class($error), __METHOD__, \Joomla\CMS\Log\Log::ERROR);
        }
    }
}
