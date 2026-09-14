<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Uri\Uri;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * Event Controller
 */
class JemControllerMailto extends JemControllerForm
{
    // protected $view_item = 'editevent';
    // protected $view_list = 'eventslist';
    protected $_id = 0;


    public function getModel($name = 'mailto', $prefix = '', $config = array('ignore_request' => true)) {
        $model = parent::getModel($name, $prefix, $config);

        return $model;
    }

    public function save($key = NULL, $urlVar = NULL)
    {
        JemHelper::requirePostToken();

        $app = Factory::getApplication();
        JemHelper::setNoStoreHeaders();
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $input = $app->getInput();
        $post_link = trim($input->post->getString('link', ''));
        $returnUrl = 'index.php?option=com_jem&tmpl=component&view=mailto&link=' . rawurlencode($post_link);
        $currentUri = Route::_($returnUrl, false);

        $user = $app->getIdentity();

        if (!JemMailtoHelper::canCurrentUserSend($app)) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $link = JemMailtoHelper::validateHash($post_link);

        if (!$link || !Uri::isInternal($link)) {
            return $this->rejectSubmission($app, $currentUri);
        }

        $model = $this->getModel('mailto');
        $data = $model->getData();
        // The authenticated Joomla account is authoritative for sender identity.
        $data['sender'] = (string) $user->name;
        $data['emailfrom'] = (string) $user->email;
        $form = $model->getForm();

        if (!$form) {
            $app->enqueueMessage($model->getError(), 'error');

            return false;
        }

        if (!$model->validate($form, $data)) {
            $errors = $model->getErrors();

            foreach ($errors as $error) {
                $errorMessage = $error;

                if ($error instanceof Exception) {
                    $errorMessage = $error->getMessage();
                }

                $app->enqueueMessage($errorMessage, 'error');
            }

            $this->setRedirect($currentUri);
            return false;
        }

        if (JemMailtoHelper::containsForbiddenHeaderData($data)) {
            return $this->rejectSubmission($app, $currentUri);
        }

        foreach (array('emailto', 'emailfrom', 'sender', 'subject') as $field) {
            $data[$field] = trim((string) $data[$field]);
        }

        $siteName = (string) $app->get('sitename');
        $subject_default = Text::sprintf('COM_JEM_MAILTO_SENT_BY', $data['sender']);
        $subject         = $data['subject'] !== '' ? $data['subject'] : $subject_default;

        $replyAddress = MailHelper::cleanAddress(PunycodeHelper::emailToPunycode($data['emailfrom']));
        $recipient = MailHelper::cleanAddress(PunycodeHelper::emailToPunycode($data['emailto']));
        $senderAddress = MailHelper::cleanAddress(
            PunycodeHelper::emailToPunycode(trim((string) $app->get('mailfrom', '')))
        );

        if (!MailHelper::isEmailAddress($replyAddress)
            || !MailHelper::isEmailAddress($recipient)
            || !MailHelper::isEmailAddress($senderAddress)) {
            $this->logMailFailure('MAILTO_CONFIGURATION_OR_ADDRESS_INVALID');

            return $this->rejectSubmission($app, $currentUri);
        }

        $allowed = JemMailtoHelper::consumeSubmissionLimits(
            JPATH_CACHE . '/com_jem/mailto',
            $input->server->getString('REMOTE_ADDR', ''),
            (string) $app->getSession()->getId(),
            (int) $app->getIdentity()->id,
            (string) $app->get('secret', '')
        );

        if (!$allowed) {
            return $this->rejectSubmission($app, $currentUri, 'COM_JEM_MAILTO_LIMIT_REACHED');
        }

        $senderName = MailHelper::cleanLine(trim((string) $app->get('fromname', $siteName)));
        $replyName = MailHelper::cleanLine($data['sender']);
        $msg = Text::_('COM_JEM_MAILTO_EMAIL_MSG');
        $body = sprintf(
            $msg,
            htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['sender'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['emailfrom'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($link, ENT_QUOTES, 'UTF-8')
        );
        $subject = MailHelper::cleanSubject($subject);
        $body = MailHelper::cleanBody($body);

        try {
            $mailer = Factory::getMailer();

            if ($mailer->setSender(array($senderAddress, $senderName)) === false
                || $mailer->addReplyTo($replyAddress, $replyName) === false
                || $mailer->addRecipient($recipient) === false) {
                throw new \RuntimeException('Mail envelope rejected.');
            }

            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml();

            if (!$mailer->send()) {
                throw new \RuntimeException('Mail transport rejected the message.');
            }
        } catch (\Throwable $e) {
            $this->logMailFailure('MAILTO_DELIVERY_FAILED', $e);

            return $this->rejectSubmission($app, $currentUri);
        }

        $this->triggerActionLog(JemMailtoHelper::getLinkContext($post_link));
        $app->setUserState('jem.mailto.form.data', null);
        $this->setRedirect(Route::_($returnUrl . '&layout=sent', false));
    }

    /**
     * Return a generic public failure without exposing mail or policy details.
     *
     * @param   object  $app         Joomla application.
     * @param   string  $currentUri  Safe return URI.
     * @param   string  $messageKey  Public language key.
     *
     * @return  boolean
     */
    private function rejectSubmission(
        $app,
        string $currentUri,
        string $messageKey = 'COM_JEM_MAILTO_EMAIL_NOT_SENT'
    ): bool
    {
        $app->setUserState('jem.mailto.form.data', null);
        $app->enqueueMessage(Text::_($messageKey), 'error');
        $this->setRedirect($currentUri);

        return false;
    }

    /**
     * Record a mail failure without addresses, recipients or transport text.
     *
     * @param   string          $code       Stable internal failure code.
     * @param   \Throwable|null  $exception  Optional failure type.
     *
     * @return  void
     */
    private function logMailFailure(string $code, ?\Throwable $exception = null): void
    {
        $message = $code . ' | Mail-to-friend request failed.';

        if ($exception !== null) {
            $message .= ' Type: ' . get_class($exception) . '.';
        }

        JemHelper::addFileLogger();
        Log::add($message, Log::ERROR, 'JEM');
    }

    /**
     * Record a successful share through Joomla User Actions Log when enabled.
     *
     * Recipient data is deliberately excluded from the event payload.
     *
     * @param   array  $context  Trusted JEM view and item identifier.
     *
     * @return  void
     */
    private function triggerActionLog(array $context): void
    {
        if (!$context) {
            return;
        }

        try {
            PluginHelper::importPlugin('actionlog', 'jem');
            JemFactory::getDispatcher()->triggerEvent('onJemMailtoSent', array($context));
        } catch (\Throwable $e) {
            $this->logMailFailure('MAILTO_ACTION_LOG_FAILED', $e);
        }
    }
}
