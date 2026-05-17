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
use Joomla\CMS\Uri\Uri;

class JemViewTypeevents extends JemView
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
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $rows       = $this->get('Items');
        $noevents   = !$rows ? 1 : 0;
        $pagination = $this->get('Pagination');

        $filter_order     = $app->getUserStateFromRequest('com_jem.typeevents.filter_order',     'filter_order',     'a.dates', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest('com_jem.typeevents.filter_order_Dir', 'filter_order_Dir', 'ASC',     'word');

        $lists                = array();
        $lists['order_Dir']   = $filter_order_Dir;
        $lists['order']       = $filter_order;

        $filters = array();
        if ($jemsettings->showtitle == 1) {
            $filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_TITLE'));
        }
        if ($jemsettings->showlocate == 1) {
            $filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));
        }
        if ($jemsettings->showcity == 1) {
            $filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
        }
        if ($jemsettings->showcat == 1) {
            $filters[] = HTMLHelper::_('select.option', '4', Text::_('COM_JEM_CATEGORY'));
        }

        $filter_type = $app->getUserStateFromRequest('com_jem.typeevents.filter_type', 'filter_type', 0, 'int');
        $search      = $app->getUserStateFromRequest('com_jem.typeevents.filter_search', 'filter_search', '', 'string');

        $lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type',
            array('size' => '1', 'class' => 'form-select'), 'value', 'text', $filter_type);
        $lists['search'] = $search;

        $typeName  = htmlspecialchars($typeObj->name, ENT_QUOTES, 'UTF-8');
        $pagetitle = Text::sprintf('COM_JEM_TYPEEVENTS_TITLE', $typeName);

        $pathway->addItem($pagetitle);

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        $permissions = new stdClass();
        $permissions->canAddEvent = $user->can('add', 'event');
        $permissions->canAddVenue = $user->can('add', 'venue');

        $this->type          = $typeObj;
        $this->rows          = $rows;
        $this->noevents      = $noevents;
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
        $this->archive_link  = $uri->toString();
        $this->task          = '';

        parent::display($tpl);
    }
}
