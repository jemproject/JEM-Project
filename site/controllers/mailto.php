<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * Event Controller
 */
class JemControllerMailto extends JemControllerForm
{
	// protected $view_item = 'editevent';
	// protected $view_list = 'eventslist';
	protected $_id = 0;

	
	public function getModel($name = 'mailto', $prefix = '', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);

		return $model;
	}
	
	public function save($key = NULL, $urlVar = NULL){
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$app     = Factory::getApplication();
		$model   = $this->getModel('mailto');
		$data    = $model->getData();
		$uri= Uri::getInstance();
		$form = $model->getForm();
		$post_link = $this->input->post->get('link', '', 'post');
		$currentUri = $uri->toString() . '&link='.$post_link;
		
		if (!$form)
		{
			$app->enqueueMessage($model->getError(), 'error');

			return false;
		}

		if (!$model->validate($form, $data))
		{
			$errors = $model->getErrors();

			foreach ($errors as $error)
			{
				$errorMessage = $error;

				if ($error instanceof Exception)
				{
					$errorMessage = $error->getMessage();
				}

				$app->enqueueMessage($errorMessage, 'error');
			}

			$this->setRedirect($currentUri);
		}

		$headers = array (
			'Content-Type:',
			'MIME-Version:',
			'Content-Transfer-Encoding:',
			'bcc:',
			'cc:'
		);
		foreach ($data as $key => $value)
		{
			foreach ($headers as $header)
			{
				if (is_string($value) && strpos($value, $header) !== false)
				{
					$app->enqueueMessage(403, 'error');
				}
			}
		}

		unset($headers, $fields);

		$siteName = $app->get('sitename');
		$link     = JemMailtoHelper::validateHash($this->input->post->get('link', '', 'post'));
		
		// Verify that this is a local link
		if (!$link || !Uri::isInternal($link))
		{
			// Non-local url...
			$app->enqueueMessage( Text::_('COM_JEM_MAILTO_EMAIL_NOT_SENT'), 'error');
			$this->setRedirect($currentUri);
		}

		$subject_default = Text::sprintf('COM_JEM_MAILTO_SENT_BY', $data['sender']);
		$subject         = $data['subject'] !== '' ? $data['subject'] : $subject_default;
		$error = false;

		if (!$data['emailto'] || !JMailHelper::isEmailAddress($data['emailto']))
		{
			$error = Text::sprintf('COM_JEM_MAILTO_EMAIL_INVALID', $data['emailto']);

			$app->enqueueMessage( $error, 'error');
		}

		// Check for a valid from address
		if (!$data['emailfrom'] || !JMailHelper::isEmailAddress($data['emailfrom']))
		{
			$error = Text::sprintf('COM_JEM_MAILTO_EMAIL_INVALID', $data['emailfrom']);

			$app->enqueueMessage( $error, 'error');
		}

		if ($error)
		{
			return $this->setRedirect($currentUri);
			return false;
		}
		$msg  = Text::_('COM_JEM_MAILTO_EMAIL_MSG');
		$body = sprintf($msg, $siteName, $data['sender'], $data['emailfrom'], $link);

		// To send we need to use punycode.
		$data['emailfrom'] = JStringPunycode::emailToPunycode($data['emailfrom']);
		$data['emailfrom'] = JMailHelper::cleanAddress($data['emailfrom']);
		$data['emailto']   = JStringPunycode::emailToPunycode($data['emailto']);
		$from = array($data['emailfrom'], $data['sender']);

		// Clean the email data
		$subject = JMailHelper::cleanSubject($subject);
		$body    = JMailHelper::cleanBody($body);

		//--------------start new code ------------
		$mailer = Factory::getMailer();
		$mailer->setSender($from);
		$mailer->addRecipient($data['emailto']);
		$mailer->setSubject($subject);
		$mailer->setBody($body);
		$mailer->isHTML();
		try{
			if (!$mailer->send())
			{
				$app->enqueueMessage( Text::_('COM_JEM_MAILTO_EMAIL_NOT_SENT'), 'error');
				$this->setRedirect($currentUri);
				return false;
			}
		}catch(Exception $e){
			$app->enqueueMessage($e->getMessage(), 'notice');			
			$this->setRedirect($currentUri);
			return false;
		}
		$currentUri .= '&layout=sent';
		$this->setRedirect($currentUri);
		//--------------end new code ------------

    }

}
