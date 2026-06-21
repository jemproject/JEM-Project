<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class JemViewSpecialdays extends JemView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = JemFactory::getUser();
        $uri = Uri::getInstance();

        if (!$user->authorise('core.manage', 'com_jem')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));
            return;
        }

        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->params = $this->state->get('params');
        $this->dayTypes = JemHelper::calendarSpecialDayTypes();
        $this->years = $this->get('AvailableYears');
        $selectedYear = (int) $this->state->get('filter.year', (int) date('Y'));
        $this->years[$selectedYear] = $selectedYear;
        ksort($this->years);
        $this->action = Route::_('index.php?option=com_jem&view=specialdays');
        $this->newLink = Route::_('index.php?option=com_jem&view=specialday&layout=edit&return=' . base64_encode($uri->toString()));
        $this->printLink = Route::_('index.php?option=com_jem&view=specialdays&print=1&tmpl=component');

        $menuitem = $app->getMenu()->getActive();
        $pagetitle = $menuitem ? $menuitem->title : Text::_('COM_JEM_SPECIAL_DAYS');
        $this->params->def('page_title', $pagetitle);
        $this->params->def('page_heading', $pagetitle);

        $app->getDocument()->setTitle($pagetitle);

        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();

        parent::display($tpl);
    }
}
