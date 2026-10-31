<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
/**
 * Search-View
 */
class JemViewSearch extends JemView
{
    /**
     * Creates the Simple List View
     */
    public function display($tpl = null)
    {
        // initialize variables
        $app          = Factory::getApplication();
        $document     = $app->getDocument();
        $jemsettings  = JemHelper::config();
        $settings     = JemHelper::globalattribs();
        $menu         = $app->getMenu();
        $menuitem     = $menu->getActive();
        $params       = $app->getParams();
        $uri          = Uri::getInstance();
        $pathway      = $app->getPathWay();
        $url           = Uri::root();
        $model        = $this->getModel('search');
     // $user         = JemFactory::getUser();

        // Decide which parameters should take priority
        $useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
            && $menuitem->query['view'] == 'search');

        // Load css
        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        // Load Script
        // HTMLHelper::_('script', 'com_jem/search.js', false, true);
        $document->addScript($url.'media/com_jem/js/search.js');

        $filter_continent = $model->getSearchFilter('filter_continent', '', 'string');
        $filter_country   = $model->getSearchFilter('filter_country', '', 'string');
        $filter_city      = $model->getSearchFilter('filter_city', '', 'string');
        $filter_date_from = $model->getSearchFilter('filter_date_from', '', 'string');
        $filter_date_to   = $model->getSearchFilter('filter_date_to', '', 'string');
        $filter_category  = $model->getSearchFilter('filter_category', 0, 'int');
        $filter_type_id   = $model->getSearchFilter('filter_type_id', 0, 'int');
        $filter_venue_id  = $model->getSearchFilter('filter_venue_id', 0, 'int');
        $task             = $model->getEventSearchScope() === 'archived' ? 'archive' : '';
        $uri->delVar('task');


        // get data from model
        $rows = $this->get('Data');

        // are events available?
        $noevents = (!$rows) ? 1 : 0;

        // Check to see which parameters should take priority
        if ($useMenuItemParams) {
            // Menu item params take priority
            $pagetitle = $params->def('page_title', $menuitem ? $menuitem->title : Text::_('COM_JEM_SEARCH'));
            $pageheading = $params->def('page_heading', $pagetitle);
            $pathwayKeys = array_keys($pathway->getPathway());
            $lastPathwayEntryIndex = end($pathwayKeys);
            $pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
            //$pathway->setItemName(1, $menuitem->title);
        } else {
            $pagetitle = Text::_('COM_JEM_SEARCH');
            $pageheading = $pagetitle;
            $params->set('introtext', ''); // there is no introtext in that case
            $params->set('showintrotext', 0);
            $pathway->addItem(1, $pagetitle);
        }
        $pageclass_sfx = $params->get('pageclass_sfx');

        if ($task == 'archive') {
            $archiveLink = 'index.php?option=com_jem&view=search'
                . ($useMenuItemParams ? '&Itemid=' . (int) $menuitem->id : '');
            $pathway->addItem(Text::_('COM_JEM_ARCHIVE'), Route::_($archiveLink));
            $pagetitle   .= ' - ' . Text::_('COM_JEM_ARCHIVE');
            $pageheading .= ' - ' . Text::_('COM_JEM_ARCHIVE');
        }
        $pageclass_sfx = $params->get('pageclass_sfx');

        $params->set('page_heading', $pageheading);

        // Add site name to title if param is set
        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        }
        elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        // Set Page title
        $document->setTitle($pagetitle);
        $document->setMetadata('title' , $pagetitle);

        // No permissions required/useful on this view
        $permissions = new stdClass();

        // create select lists
        $lists    = $this->_buildSortLists();

        if ($lists['filter']) {
            //$uri->setVar('filter', $app->input->getString('filter', ''));
            //$filter        = $app->getUserStateFromRequest('com_jem.jem.filter', 'filter', '', 'string');
            $uri->setVar('filter', $lists['filter']);
            $uri->setVar('filter_type', $app->input->getString('filter_type', ''));
        } else {
            $uri->delVar('filter');
            $uri->delVar('filter_type');
        }

        // Cause of group limits we can't use class here to build the categories tree
        $categories   = $this->get('CategoryTree');
        $catoptions   = array();
        $catoptions[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_SELECT_CATEGORY'));
        $catoptions   = array_merge($catoptions, JemCategories::getcatselectoptions($categories));
        $selectedcats = ($filter_category) ? array($filter_category) : array();

        // build selectlists
        $lists['categories'] = HTMLHelper::_('select.genericlist', $catoptions, 'filter_category', array('size'=>'1', 'class'=>'form-select'), 'value', 'text', $selectedcats);

        // Create the pagination object
        $pagination = $this->get('Pagination');

        // date filter
        $lists['date_from'] = HTMLHelper::_('calendar', $filter_date_from, 'filter_date_from', 'filter_date_from', '%Y-%m-%d', array('class'=>"inputbox", 'placeholder' => Text::_('COM_JEM_SEARCH_FROM'), 'showTime' => false));
        $lists['date_to']   = HTMLHelper::_('calendar', $filter_date_to, 'filter_date_to', 'filter_date_to', '%Y-%m-%d', array('class'=>"inputbox", 'placeholder' => Text::_('COM_JEM_SEARCH_TO'), 'showTime' => false));

        // country filter
        $continents = array();
        $continents[] = HTMLHelper::_('select.option', '',   Text::_('COM_JEM_SELECT_CONTINENT'));
        $continents[] = HTMLHelper::_('select.option', 'AF', Text::_('COM_JEM_AFRICA'));
        $continents[] = HTMLHelper::_('select.option', 'AS', Text::_('COM_JEM_ASIA'));
        $continents[] = HTMLHelper::_('select.option', 'EU', Text::_('COM_JEM_EUROPE'));
        $continents[] = HTMLHelper::_('select.option', 'NA', Text::_('COM_JEM_NORTH_AMERICA'));
        $continents[] = HTMLHelper::_('select.option', 'SA', Text::_('COM_JEM_SOUTH_AMERICA'));
        $continents[] = HTMLHelper::_('select.option', 'OC', Text::_('COM_JEM_OCEANIA'));
        $continents[] = HTMLHelper::_('select.option', 'AN', Text::_('COM_JEM_ANTARCTICA'));
        $lists['continents'] = HTMLHelper::_('select.genericlist', $continents, 'filter_continent', array('class'=>'form-select'), 'value', 'text', $filter_continent);
        unset($continents);

        // country filter
        $countries = array();
        $locationLabels = ['filter_continent' => 'COM_JEM_CONTINENT', 'filter_country' => 'COM_JEM_COUNTRY',
            'filter_state' => 'COM_JEM_TABLE_STATE', 'filter_city' => 'COM_JEM_CITY'];
        $this->location_filter_hints = [];
        foreach ($locationLabels as $field => $label) {
            $parent = $model->getLocationPrerequisite($field);
            $this->location_filter_hints[$field] = $parent === '' ? ''
                : Text::sprintf('COM_JEM_SEARCH_SELECT_LOCATION_FIRST', Text::_($locationLabels[$parent]));
        }
        $countries[] = HTMLHelper::_('select.option', '', Text::_('COM_JEM_SELECT_COUNTRY'));
        $countries = array_merge($countries, $this->get('CountryOptions'));
        $countryAttributes = ['class' => 'form-select'];
        if (!$model->isLocationFilterAvailable('filter_country')) {
            $countryAttributes['disabled'] = 'disabled';
            $countryAttributes['title'] = $this->location_filter_hints['filter_country'];
            $countryAttributes['aria-describedby'] = 'filter_country_hint';
        }
        $lists['countries'] = HTMLHelper::_('select.genericlist', $countries, 'filter_country', $countryAttributes, 'value', 'text', $filter_country);
        unset($countries);

        // Skip controls disabled by the menu; otherwise activate each geographic level in order.
        $locationAttributes = ['class' => 'form-select'];
        if (!$model->isLocationFilterAvailable('filter_state')) {
            $locationAttributes['disabled'] = 'disabled';
            $locationAttributes['title'] = $this->location_filter_hints['filter_state'];
            $locationAttributes['aria-describedby'] = 'filter_state_hint';
        }
        $states = [HTMLHelper::_('select.option', '', Text::_('COM_JEM_SEARCH_SELECT_STATE'))];
        $states = array_merge($states, $this->get('StateOptions'));
        $lists['states'] = HTMLHelper::_('select.genericlist', $states, 'filter_state',
            $locationAttributes, 'value', 'text', $model->getRegionFilter());

        $locationAttributes = ['class' => 'form-select'];
        if (!$model->isLocationFilterAvailable('filter_city')) {
            $locationAttributes['disabled'] = 'disabled';
            $locationAttributes['title'] = $this->location_filter_hints['filter_city'];
            $locationAttributes['aria-describedby'] = 'filter_city_hint';
        }
        $cities = [HTMLHelper::_('select.option', '', Text::_('COM_JEM_SELECT_CITY'))];
        $cities = array_merge($cities, $this->get('CityOptions'));
        $lists['cities'] = HTMLHelper::_('select.genericlist', $cities, 'filter_city',
            $locationAttributes, 'value', 'text', $filter_city);

        // event type filter
        $eventtypes   = array();
        $eventtypes[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_SELECT_EVENT_TYPE'));
        $eventtypes   = array_merge($eventtypes, $this->get('EventTypeOptions'));
        $lists['event_types'] = HTMLHelper::_('select.genericlist', $eventtypes, 'filter_type_id', array('class'=>'form-select'), 'value', 'text', $filter_type_id);
        unset($eventtypes);

        // venue filter
        $venues   = array();
        $venues[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_SELECT_VENUE'));
        $venues   = array_merge($venues, $this->get('VenueOptions'));
        $lists['venues'] = HTMLHelper::_('select.genericlist', $venues, 'filter_venue_id', array('class'=>'form-select'), 'value', 'text', $filter_venue_id);
        unset($venues);

        // Filter visibility params
        foreach ([
            'eventtype' => 'filter_type_id', 'category' => 'filter_category',
            'venue' => 'filter_venue_id', 'continent' => 'filter_continent',
            'country' => 'filter_country', 'state' => 'filter_state', 'city' => 'filter_city',
            'date_from' => 'filter_date_from', 'date_to' => 'filter_date_to', 'limit' => 'limit',
        ] as $option => $field) {
            $this->{'filter_show_' . $option} = $model->isSearchFilterEnabled($field);
        }
        $this->filter_show_dates = $this->filter_show_date_from || $this->filter_show_date_to;

        // Keep navigation context, not stale GET filters, in the POST target.
        $action = clone $uri;
        foreach (['filter', 'filter_search', 'filter_type', 'filter_category', 'filter_type_id',
            'filter_venue_id', 'filter_continent', 'filter_country', 'filter_state', 'filter_city',
            'filter_date_from', 'filter_date_to', 'limitstart', 'start', 'search_reset'] as $name) {
            $action->delVar($name);
        }
        $this->lists            = $lists;
        $this->action           = $action->toString();
        $this->rows             = $rows;
        $this->task             = $task;
        $this->noevents         = $noevents;
        $this->params           = $params;
        $this->pagination       = $pagination;
        $this->jemsettings      = $jemsettings;
        $this->settings         = $settings;
        $this->permissions      = $permissions;
        $this->pagetitle        = $pagetitle;
        $this->filter_continent = $filter_continent;
        $this->filter_country   = $filter_country;
        $this->filter_date_from = $filter_date_from;
        $this->filter_date_to   = $filter_date_to;
        $this->filter_type_id   = $filter_type_id;
        $this->filter_venue_id  = $filter_venue_id;
        $this->document         = $document;
        $this->pageclass_sfx    =$pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;

        parent::display($tpl);
    }

    /**
     * Method to build the sortlists
     *
     * @access private
     * @return array
     *
     */
    protected function _buildSortLists()
    {
        $app = Factory::getApplication();
        $task = $this->getModel('search')->getEventSearchScope() === 'archived' ? 'archive' : '';

        $filter_order = $app->input->getCmd('filter_order', 'a.dates');
        $filter_order_DirDefault = 'ASC';
        // Reverse default order for dates in archive mode
        if ($task == 'archive' && $filter_order == 'a.dates') {
            $filter_order_DirDefault = 'DESC';
        }
        $filter_order_Dir = $app->input->get('filter_order_Dir', $filter_order_DirDefault);
        $filter           = $this->getModel('search')->getSearchFilter('filter_search', '', 'string');
        $filter_type      = $app->input->getString('filter_type', '');

        $sortselects = array();
        $sortselects[] = HTMLHelper::_('select.option', 'title', Text::_('COM_JEM_TABLE_TITLE'));
        $sortselects[] = HTMLHelper::_('select.option', 'venue', Text::_('COM_JEM_TABLE_LOCATION'));
        $sortselect    = HTMLHelper::_('select.genericlist', $sortselects, 'filter_type', array('size'=>'1','class'=>'form-select'), 'value', 'text', $filter_type);

        $lists['order_Dir']    = $filter_order_Dir;
        $lists['order']        = $filter_order;
        $lists['filter']       = $filter;
        $lists['filter_types'] = $sortselect;

        return $lists;
    }
}
?>
