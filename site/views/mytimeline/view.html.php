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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class JemViewMytimeline extends JemView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $jemsettings = JemHelper::config();
        $settings = JemHelper::globalattribs();
        $menuitem = $app->getMenu()->getActive();
        $params = $app->getParams();
        $uri = Uri::getInstance();
        $user = JemFactory::getUser();
        $task = $app->input->getCmd('task', '');
        $print = $app->input->getBool('print', false);

        $this->needLoginFirst = 0;
        $this->action = $uri->toString();
        $this->items = array();
        $this->task = $task;
        $this->print = $print;
        $this->params = $params;
        $this->jemsettings = $jemsettings;
        $this->settings = $settings;
        $this->permissions = new stdClass();
        $this->pagetitle = Text::_('COM_JEM_MY_TIMELINE');
        $this->print_link = '';
        $this->archive_link = '';
        $this->pageclass_sfx = '';

        if (!$user->get('id')) {
            $app->enqueueMessage(Text::_('COM_JEM_LOGIN_TO_ACCESS'), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));
            $this->needLoginFirst = 1;
            return;
        } else {
            JemHelper::loadCss('jem');
            JemHelper::loadCustomCss();
            JemHelper::loadCustomTag();

            if ($print) {
                JemHelper::loadCss('print');
                $document->setMetaData('robots', 'noindex, nofollow');
            }

            $useMenuItemParams = ($menuitem && $menuitem->query['option'] === 'com_jem'
                && $menuitem->query['view'] === 'mytimeline');

            $pagetitle = Text::_('COM_JEM_MY_TIMELINE');
            $pageheading = $pagetitle;
            $pageclass_sfx = '';

            if ($useMenuItemParams) {
                $params->def('page_title', $menuitem->title);
                $pagetitle = $params->get('page_title', Text::_('COM_JEM_MY_TIMELINE'));
                $pageheading = $params->get('page_heading', $pagetitle);
                $pageclass_sfx = $params->get('pageclass_sfx');
            }

            if ($task === 'archive') {
                $pagetitle .= ' - ' . Text::_('COM_JEM_ARCHIVE');
                $pageheading .= ' - ' . Text::_('COM_JEM_ARCHIVE');
                $print_link = Route::_('index.php?option=com_jem&view=mytimeline&task=archive&print=1&tmpl=component');
                $archive_link = Route::_('index.php?option=com_jem&view=mytimeline');
            } else {
                $print_link = Route::_('index.php?option=com_jem&view=mytimeline&print=1&tmpl=component');
                $archive_link = Route::_('index.php?option=com_jem&view=mytimeline&task=archive');
            }

            $params->set('page_heading', $pageheading);

            if ($app->get('sitename_pagetitles', 0) == 1) {
                $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
            } elseif ($app->get('sitename_pagetitles', 0) == 2) {
                $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
            }

            $document->setTitle($pagetitle);
            $document->setMetaData('title', $pagetitle);

            $this->items = $this->get('Items');
            $this->pagetitle = $pagetitle;
            $this->print_link = $print_link;
            $this->archive_link = $archive_link;
            $this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
        }

        parent::display($tpl);
    }
}
