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

class JemViewTypevenues extends JemView
{
    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->addCommonTemplatePath();
    }

    public function display($tpl = null)
    {
        $app         = Factory::getApplication();
        $document    = $app->getDocument();
        $jemsettings = JemHelper::config();
        $settings    = JemHelper::globalattribs();
        $user        = JemFactory::getUser();
        $params      = $app->getParams();
        $uri         = Uri::getInstance();
        $pathway     = $app->getPathWay();
        $print       = $app->input->getBool('print', false);

        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        if ($print) {
            JemHelper::loadCss('print');
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        $typeObj = $this->get('Type');

        if (!$typeObj) {
            $requestedTypeId = $app->input->getInt('id', 0) ?: (int) $params->get('id', 0);
            $pagetitle = Text::_('COM_JEM_TYPEVENUES_VIEW_DEFAULT_TITLE');
            $pathway->addItem($pagetitle);
            $params->set('page_heading', $pagetitle);

            if ($app->get('sitename_pagetitles', 0) == 1) {
                $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
            } elseif ($app->get('sitename_pagetitles', 0) == 2) {
                $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
            }

            $document->setTitle($pagetitle);
            $document->setMetaData('title', $pagetitle);

            $permissions = new stdClass();
            $permissions->canAddVenue = $user->can('add', 'venue');
            $permissions->canEditPublishVenue = $user->can(array('edit', 'publish'), 'venue');

            $this->type          = null;
            $this->missingTypeId = $requestedTypeId;
            $this->rows          = array();
            $this->novenues      = 1;
            $this->pagination    = null;
            $this->lists         = array('filter' => '', 'search' => '', 'order_Dir' => '', 'order' => 'a.city');
            $this->params        = $params;
            $this->action        = $uri->toString();
            $this->jemsettings   = $jemsettings;
            $this->settings      = $settings;
            $this->permissions   = $permissions;
            $this->pagetitle     = $pagetitle;
            $this->pageclass_sfx = '';
            $this->print         = $print;
            $this->print_link    = $uri->toString() . '?print=1&tmpl=component';
            $this->pdf_link      = Route::_('index.php?option=com_jem&view=typevenues&id=' . (int) $requestedTypeId . '&format=raw&layout=pdf');
            $this->task          = '';
            $this->show_status   = false;

            parent::display($tpl);
            return;
        }

        if (empty($typeObj->user_has_access_type)) {
            if ($user->get('guest') || !$user->get('id')) {
                $app->enqueueMessage(Text::_('COM_JEM_LOGIN_TO_ACCESS'), 'warning');
                $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));

                return;
            }

            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $rows       = $this->get('Items');
        $pagination = $this->get('Pagination');
        $novenues   = !$rows ? 1 : 0;

        $filter_order     = $app->getUserStateFromRequest('com_jem.venueslist.filter_order', 'filter_order', 'a.city', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest('com_jem.venueslist.filter_order_Dir', 'filter_order_Dir', '', 'word');
        $filter           = $app->getUserStateFromRequest('com_jem.venueslist.filter_type', 'filter_type', '', 'int');
        $search           = $app->getUserStateFromRequest('com_jem.venueslist.filter_search', 'filter_search', '', 'string');

        $filters = array(
            HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE')),
            HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY')),
            HTMLHelper::_('select.option', '6', Text::_('COM_JEM_COUNTRY')),
            HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE')),
        );

        $lists                = array();
        $lists['filter']      = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size' => '1', 'class' => 'form-select'), 'value', 'text', $filter);
        $lists['search']      = $search;
        $lists['order_Dir']   = $filter_order_Dir;
        $lists['order']       = $filter_order;

        $typeName  = htmlspecialchars($typeObj->name, ENT_QUOTES, 'UTF-8');
        $pagetitle = Text::sprintf('COM_JEM_TYPEVENUES_TITLE', $typeName);

        $pathway->addItem($pagetitle);
        $params->set('page_heading', $pagetitle);

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        $permissions = new stdClass();
        $permissions->canAddVenue = $user->can('add', 'venue');
        $permissions->canEditPublishVenue = $user->can(array('edit', 'publish'), 'venue');

        $this->type          = $typeObj;
        $this->rows          = $rows;
        $this->novenues      = $novenues;
        $this->pagination    = $pagination;
        $this->lists         = $lists;
        $this->params        = $params;
        $this->action        = $uri->toString();
        $this->jemsettings   = $jemsettings;
        $this->settings      = $settings;
        $this->permissions   = $permissions;
        $this->pagetitle     = $pagetitle;
        $this->pageclass_sfx = '';
        $this->print         = $print;
        $this->print_link    = $uri->toString() . '?print=1&tmpl=component';
        $this->pdf_link      = Route::_('index.php?option=com_jem&view=typevenues&id=' . (int) $typeObj->id . '&format=raw&layout=pdf');
        $this->task          = '';
        $this->show_status   = $permissions->canEditPublishVenue;

        parent::display($tpl);
    }
}
