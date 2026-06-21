<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class JemViewSpecialday extends JemView
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

        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->form = $this->get('Form');
        $this->return_page = $this->get('ReturnPage');
        $this->params = $this->state->get('params');
        $this->dayTypes = JemHelper::calendarSpecialDayTypes();

        if (!$this->item || !$this->form) {
            $app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
            return;
        }

        HTMLHelper::_('behavior.formvalidator');
        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();

        $title = empty($this->item->id) ? Text::_('COM_JEM_SPECIAL_DAY_ADD') : Text::_('COM_JEM_SPECIAL_DAY_EDIT');
        $this->params->def('page_title', $title);
        $this->params->def('page_heading', $title);
        $app->getDocument()->setTitle($title);

        parent::display($tpl);
    }
}
