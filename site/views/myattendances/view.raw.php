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
 * Raw: Myattendances
 */
class JemViewMyattendances extends HtmlView
{
    /**
     * Creates the PDF output for the My Attendances view.
     */
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
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));

            return;
        }

        $model = $this->getModel();
        $model->setState('limitstart', 0);
        $model->setState('limit', 0);

        JemPdfView::renderEventList(Text::_('COM_JEM_MY_ATTENDANCES'), (array) $model->getAttending(), 'jem-my-attendances.pdf');
    }
}
