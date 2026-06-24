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

/**
 * Attendee registrations list view.
 */
class JemViewAttendeeregistrations extends JemView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = JemFactory::getUser();
        $uri = Uri::getInstance();

        if (!$user->get('id')) {
            $app->enqueueMessage(Text::_('COM_JEM_LOGIN_TO_ACCESS'), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));

            return;
        }

        if (!$user->authorise('core.manage', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $document = $app->getDocument();
        $params = $app->getParams();
        $menu = $app->getMenu();
        $menuitem = $menu->getActive();
        $settings = JemHelper::globalattribs();
        $jemsettings = JemHelper::config();

        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        $rows = $this->get('Data');
        $pagination = $this->get('Pagination');
        $filter = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter', 'filter', 0, 'int');
        $search = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_search', 'filter_search', '', 'string');
        $filterStatus = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_status', 'filter_status', -2, 'int');
        $filterOrder = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_order', 'filter_order', 'r.uregdate', 'cmd');
        $filterOrderDir = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');

        $filters = array(
            HTMLHelper::_('select.option', 0, Text::_('JALL')),
            HTMLHelper::_('select.option', 1, Text::_('COM_JEM_NAME')),
            HTMLHelper::_('select.option', 2, Text::_('COM_JEM_USERNAME')),
            HTMLHelper::_('select.option', 3, Text::_('COM_JEM_EVENT')),
        );
        $statuses = array(
            HTMLHelper::_('select.option', -2, Text::_('COM_JEM_ATT_FILTER_ALL')),
            HTMLHelper::_('select.option', 0, Text::_('COM_JEM_ATT_FILTER_INVITED')),
            HTMLHelper::_('select.option', -1, Text::_('COM_JEM_ATT_FILTER_NOT_ATTENDING')),
            HTMLHelper::_('select.option', 1, Text::_('COM_JEM_ATT_FILTER_ATTENDING')),
            HTMLHelper::_('select.option', 2, Text::_('COM_JEM_ATT_FILTER_WAITING')),
        );

        $lists = array(
            'filter' => HTMLHelper::_('select.genericlist', $filters, 'filter', array('size' => '1', 'class' => 'form-select'), 'value', 'text', $filter),
            'status' => HTMLHelper::_('select.genericlist', $statuses, 'filter_status', array('class' => 'form-select', 'onChange' => 'this.form.submit();'), 'value', 'text', $filterStatus),
            'search' => $search,
            'order' => $filterOrder,
            'order_Dir' => $filterOrderDir,
        );

        $pagetitle = Text::_('COM_JEM_ATTENDEE_REGISTRATIONS');
        $pageheading = $pagetitle;

        if ($menuitem && ($menuitem->query['option'] ?? '') === 'com_jem' && ($menuitem->query['view'] ?? '') === 'attendeeregistrations') {
            $params->def('page_title', $menuitem->title);
            $pagetitle = $params->get('page_title', $pagetitle);
            $pageheading = $params->get('page_heading', $pagetitle);
        }

        $params->set('page_heading', $pageheading);

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        $document->setTitle($pagetitle);

        $permissions = new stdClass();
        $permissions->canManageAttendees = $user->authorise('core.manage', 'com_jem');
        $this->action = $uri->toString();
        $this->rows = $rows;
        $this->pagination = $pagination;
        $this->params = $params;
        $this->settings = $settings;
        $this->jemsettings = $jemsettings;
        $this->lists = $lists;
        $this->permissions = $permissions;
        $this->itemid = $menuitem ? (int) $menuitem->id : (int) $app->input->getInt('Itemid', 0);
        $filterQuery = array(
            'option' => 'com_jem',
            'view' => 'attendeeregistrations',
            'format' => 'raw',
            'layout' => 'pdf',
            'filter' => $filter,
            'filter_status' => $filterStatus,
            'filter_search' => $search,
            'filter_order' => $filterOrder,
            'filter_order_Dir' => $filterOrderDir,
        );

        if ($this->itemid) {
            $filterQuery['Itemid'] = $this->itemid;
        }

        $this->print_link = Route::_('index.php?option=com_jem&view=attendeeregistrations&print=1&tmpl=component');
        $this->pdf_link = Route::_('index.php?' . http_build_query($filterQuery), false);
        $this->pageclass_sfx = htmlspecialchars((string) $params->get('pageclass_sfx', ''), ENT_COMPAT, 'UTF-8');
        $this->columns = $this->getColumns($params);

        parent::display($tpl);
    }

    protected function getColumns($params): array
    {
        $columns = array('registration_id', 'name', 'username', 'places', 'uregdate', 'event', 'event_date', 'status');
        $selected = (array) $params->get('registration_columns', $columns);
        $allowed = array('registration_id', 'name', 'username', 'user_id', 'email', 'places', 'uregdate', 'event', 'event_date', 'venue', 'status', 'comment');
        $selected = array_values(array_intersect($selected, $allowed));

        return $selected ?: $columns;
    }
}
?>
