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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Save and reset language-specific notification templates.
 */
class JemControllerNotificationtemplate extends BaseController
{
    public function save()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        return $this->store(false);
    }

    public function apply()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        return $this->store(true);
    }

    public function reset()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertAccess();
        $app = Factory::getApplication();
        $templateId = $app->input->post->getString('template_id');
        $language = $app->input->post->getString('language');

        try {
            $this->getModel('Notificationtemplate')->reset($templateId, $language);
            $app->enqueueMessage(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_RESET_SUCCESS'));
        } catch (Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($this->editUrl($templateId, $language));

        return true;
    }

    public function cancel()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        Factory::getApplication()->setUserState('com_jem.edit.notificationtemplate.data', null);
        $this->setRedirect(Route::_('index.php?option=com_jem&view=notifications', false));

        return true;
    }

    private function store($apply)
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertAccess();
        $app = Factory::getApplication();
        $data = (array) $app->input->post->get('jform', array(), 'array');
        $templateId = (string) ($data['template_id'] ?? '');
        $language = (string) ($data['language'] ?? '');

        try {
            $warnings = $this->getModel('Notificationtemplate')->save($data);
            foreach ($warnings as $warning) {
                $app->enqueueMessage($this->warningText($warning), 'warning');
            }
            $app->enqueueMessage(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SAVE_SUCCESS'));
            $url = $apply
                ? $this->editUrl($templateId, $language)
                : Route::_('index.php?option=com_jem&view=notifications&filter_language=' . rawurlencode($language), false);
        } catch (Throwable $e) {
            $app->setUserState('com_jem.edit.notificationtemplate.data', $data);
            $app->enqueueMessage($e->getMessage(), 'error');
            $url = $this->editUrl($templateId, $language);
        }

        $this->setRedirect($url);

        return true;
    }

    private function warningText($warning)
    {
        if (preg_match('/^[a-z]+:missing_recommended:([a-z][a-z0-9_]*)$/', $warning, $matches)) {
            return Text::sprintf('COM_JEM_NOTIFICATION_TEMPLATE_RECOMMENDED_MISSING', '{' . $matches[1] . '}');
        }

        return $warning;
    }

    private function assertAccess()
    {
        if (!JemHelperBackend::canManage('jem.notifications.templates')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function editUrl($templateId, $language)
    {
        return Route::_(
            'index.php?option=com_jem&view=notificationtemplate&template_id=' . rawurlencode((string) $templateId)
            . '&language=' . rawurlencode((string) $language),
            false
        );
    }
}
