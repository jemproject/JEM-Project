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

class JemControllerNotificationcontent extends BaseController
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
        $section = $app->input->post->getCmd('section', 'footer');
        $language = $app->input->post->getString('language');

        try {
            $this->getModel('Notificationcontent')->reset($section, $language);
            $app->enqueueMessage(Text::_('COM_JEM_NOTIFICATION_CONTENT_RESET_SUCCESS'));
        } catch (Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($this->editUrl($section, $language));

        return true;
    }

    public function cancel()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        Factory::getApplication()->setUserState('com_jem.edit.notificationcontent.data', null);
        $this->setRedirect(Route::_('index.php?option=com_jem&view=notifications', false));

        return true;
    }

    private function store($apply)
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertAccess();
        $app = Factory::getApplication();
        $data = (array) $app->input->post->get('jform', array(), 'array');
        $section = (string) ($data['section'] ?? 'footer');
        $language = (string) ($data['language'] ?? '');

        try {
            $this->getModel('Notificationcontent')->save($data);
            $app->enqueueMessage(Text::_('COM_JEM_NOTIFICATION_CONTENT_SAVE_SUCCESS'));
            $url = $apply
                ? $this->editUrl($section, $language)
                : Route::_('index.php?option=com_jem&view=notifications', false);
        } catch (Throwable $e) {
            $app->setUserState('com_jem.edit.notificationcontent.data', $data);
            $app->enqueueMessage($e->getMessage(), 'error');
            $url = $this->editUrl($section, $language);
        }

        $this->setRedirect($url);

        return true;
    }

    private function assertAccess()
    {
        if (!JemHelperBackend::canManage('jem.notifications.templates')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function editUrl($section, $language)
    {
        return Route::_(
            'index.php?option=com_jem&view=notificationcontent&section=' . rawurlencode((string) $section)
            . '&language=' . rawurlencode((string) $language),
            false
        );
    }
}
