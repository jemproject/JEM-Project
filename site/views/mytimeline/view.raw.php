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
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Raw: My Timeline
 */
class JemViewMytimeline extends HtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $user = JemFactory::getUser();

        if (!$user->get('id')) {
            $uri = Uri::getInstance();
            $app->enqueueMessage(Text::_('COM_JEM_LOGIN_TO_ACCESS'), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));

            return;
        }

        $params = $app->getParams();
        $title = trim((string) $params->get('page_heading', $params->get('page_title', Text::_('COM_JEM_MY_TIMELINE'))));

        if ($title === '') {
            $title = Text::_('COM_JEM_MY_TIMELINE');
        }

        if ($app->input->getCmd('task', '') === 'archive') {
            $title .= ' - ' . Text::_('COM_JEM_ARCHIVE');
        }

        JemPdfView::renderLinkedEventList($title, (array) $this->getModel()->getItems(), 'jem-my-timeline.pdf');
    }
}
