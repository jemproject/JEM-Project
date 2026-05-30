<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
require_once JPATH_SITE . '/components/com_jem/helpers/countries.php';
if (is_file(JPATH_SITE . '/components/com_jem/helpers/map.php')) {
    require_once JPATH_SITE . '/components/com_jem/helpers/map.php';
}

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Date\Date;
use Joomla\Component\Jem\Site\Helper\JemMapHelper;


use Joomla\CMS\Router\Route;

/**
 * EventsMap-View
 */
class JemViewEventsMap extends JemView
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        // additional path for common templates + corresponding override path
        $this->addCommonTemplatePath();
    }

    /**
     * Creates the Events Map View
     */
    public function display($tpl = null)
    {
        // Create JEM's file logger (for debug)
        JemHelper::addFileLogger();

        // Get data from model
        $rows = $this->get('Items');
        $pagination = $this->get('Pagination');

        // Initialize variables
        $app         = Factory::getApplication();
        $app->getLanguage()->load('mod_jem_map', JPATH_SITE . '/modules/mod_jem_map');
        $document    = $app->getDocument();
        $jemsettings = JemHelper::config();
        $settings    = JemHelper::globalattribs();
        $menu        = $app->getMenu();
        $menuitem    = $menu->getActive();
        $params      = $app->getParams();
        $uri         = Uri::getInstance();       
        $jinput      = $app->input;
        $task        = $jinput->getCmd('task', '');
        $print       = $jinput->getBool('print', false);
        $pathway     = $app->getPathWay();
        $user        = JemFactory::getUser();
        $itemid      = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);

        // Load CSS
        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();
        JemHelper::loadCss('leaflet');

        if ($print) {
            JemHelper::loadCss('print');
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        // Get data from model
        $rows = $this->get('Items');

        // Are no venues available?
        $novenues = (!$rows) ? 1 : 0;

        // *******************************************************************************************
        $venueMarker = JemMapHelper::resolveMarkerUrl($params->get('venue_markerfile', 'media/com_jem/images/marker-red.webp'), 'media/com_jem/images/marker-red.webp');
        $mylocMarker = JemMapHelper::resolveMarkerUrl($params->get('mylocation_markerfile', 'media/com_jem/images/marker-blue.webp'), 'media/com_jem/images/marker-blue.webp');

        $height             = $params->get('height', '500px');
        $zoom               = (int) $params->get('map_zoom', 8);
        $showDateFilter     = (int) $params->get('show_date_filter', 0);
        $showCategoryFilter = (int) $params->get('show_category_filter', 0);
        $showCountryFilter  = (int) $params->get('show_country_filter', 0);
        $dateFilterDefault  = $params->get('date_filter_default', 'all');
        $defaultCountry     = trim((string) $params->get('default_country', ''));
        if ($defaultCountry === '0') {
            $defaultCountry = '';
        }
        $jemItemid          = (int) $params->get('jem_itemid', 0);

        // Filter from request (only if backend option is enabled)
        $filterMode      = 'all';
        $filterDate      = null;
        $selectedDate    = '';
        $filterStartDate   = null;
        $filterEndDate     = null;
        $selectedCategoryId = $showCategoryFilter ? $app->input->getInt('jem_map_filter_catid', 0) : 0;
        $selectedCountry    = $showCountryFilter ? trim($app->input->getString('jem_map_filter_country', $defaultCountry)) : $defaultCountry;

        if ($showDateFilter) {
            $filterMode = $app->input->get('jem_map_filter_mode', $dateFilterDefault, 'string');
            $filterDate = $app->input->get('jem_map_filter_date', '', 'string');

            if ($filterMode == 'date' && $filterDate === null) {
                $filterMode = 'all';
            }

            // Get the current date
            $now = Factory::getDate();

            switch ($filterMode) {
                case 'date':
                    // Filter for a single, specific date selected by the user.
                    $selectedDate = $app->input->get('jem_map_filter_date', '', 'string');
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
                        $dt = Date::createFromFormat('Y-m-d', $selectedDate);
                        if ($dt && $dt->format('Y-m-d') === $selectedDate) {
                            $filterStartDate = $selectedDate;
                            $filterEndDate   = $selectedDate;
                        }
                    }
                    break;

                case 'today':
                    // Shows events from today onwards, ending today.
                    $filterStartDate = $now->format('Y-m-d');
                    $filterEndDate   = $now->format('Y-m-d');
                    break;

                case 'tomorrow':
                    // Shows events for tomorrow.
                    $tomorrow        = $now->modify('+1 day');
                    $filterStartDate = $tomorrow->format('Y-m-d');
                    $filterEndDate   = $tomorrow->format('Y-m-d');
                    break;

                case 'week':
                    // Shows events from today until the end of the current week (Sunday).
                    $filterStartDate = $now->format('Y-m-d');
                    $endOfWeek       = $now->modify('Sunday this week');
                    $filterEndDate   = $endOfWeek->format('Y-m-d');
                    break;

                case 'month':
                    // Shows events from today until the last day of the current month.
                    $filterStartDate = $now->format('Y-m-d');
                    $endOfMonth      = $now->modify('last day of this month');
                    $filterEndDate   = $endOfMonth->format('Y-m-d');
                    break;

                case 'year':
                    // Shows events from today until the last day of the current year (Dec 31).
                    $filterStartDate = $now->format('Y-m-d');
                    $endOfYear       = $now->setDate((int) $now->format('Y'), 12, 31);
                    $filterEndDate   = $endOfYear->format('Y-m-d');
                    break;

                case 'all':
                default:
                    // Shows all venues with or without events.
                    break;
            }
        }

        if (!$filterStartDate) {
            $filterStartDate = Factory::getDate()->format('Y-m-d');
        }

        $categories = $showCategoryFilter ? JemMapHelper::getCategories($params) : [];
        $countries = $showCountryFilter ? JemMapHelper::getVenueCountries() : [];

        foreach ($countries as $country) {
            $countryCode = (string) $country->country;
            $countryName = JemHelperCountries::getCountryName($countryCode);
            $country->country_name = $countryName ?: $countryCode;
        }
        usort($countries, static function ($a, $b) {
            return strcasecmp((string) $a->country_name, (string) $b->country_name);
        });

        if ($selectedCategoryId > 0) {
            $validCategoryIds = array_map('intval', array_column($categories, 'id'));

            if (!in_array($selectedCategoryId, $validCategoryIds, true)) {
                $selectedCategoryId = 0;
            }
        }

        if ($selectedCountry !== '') {
            $validCountries = array_map(static function ($country) {
                return (string) $country->country;
            }, $countries);

            if ($showCountryFilter && !in_array($selectedCountry, $validCountries, true)) {
                $selectedCountry = '';
            }
        }

        // Fetch active events with venues that have valid coordinates.
        $events = JemMapHelper::getEvents($params, $filterStartDate, $filterEndDate, $selectedCategoryId, $selectedCountry);

        // Get auto center map
        if ($params->get('map_auto_center', 1)) {
            [$centerLat, $centerLng] = JemMapHelper::getCenter($events);
        } else {
            $centerLat = $centerLng = 0;
        }

        // *******************************************************************************************

        // get variables
        $filter_order     = $app->getUserStateFromRequest('com_jem.venueslist.filter_order', 'filter_order', 'a.city', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest('com_jem.venueslist.filter_order_Dir', 'filter_order_Dir', '', 'word');
        // $filter_state     = $app->getUserStateFromRequest('com_jem.venueslist.filter_state', 'filter_state',     '*', 'word');
        $filter           = $app->getUserStateFromRequest('com_jem.venueslist.filter_type', 'filter_type', '', 'int');
        $search           = $app->getUserStateFromRequest('com_jem.venueslist.filter_search', 'filter_search', '', 'string');

        // search filter
        $filters = [];

        // Workaround issue #557: Show venue name always.
        $jemsettings->showlocate = 1;

        //$filters[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_CHOOSE'));

        if ($jemsettings->showlocate == 1) {
            $filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
        }
        $filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));
        $filters[] = HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE'));

        $lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', ['size' => '1', 'class' => 'form-select'], 'value', 'text', $filter);

        // search filter
        $lists['search'] = $search;

        // table ordering
        $lists['order_Dir'] = $filter_order_Dir;
        $lists['order'] = $filter_order;

        // pathway
        if ($menuitem) {
            $pathway->setItemName(1, $menuitem->title);
        }

        // Set Page title        
        $params->def('page_title', $menuitem->title);
        $pagetitle      = $params->get('page_title', Text::_('COM_JEM_EVENTSMAP_PAGETITLE'));
        $pageheading    = $params->get('page_heading', $pagetitle);
        $pageclass_sfx  = $params->get('pageclass_sfx');
		$print_link = $uri->toString() . "?tmpl=component&print=1";

        $params->set('page_heading', $pageheading);

        // Add site name to title if param is set
        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        //Check if the user has permission to add things
        $permissions = new stdClass();
        //$permissions->canAddEvent = $user->can('add', 'event');
        $permissions->canAddVenue = $user->can('add', 'venue');
        $permissions->canEditPublishVenue = $user->can(array('edit', 'publish'), 'venue');

        // Create the pagination object
        // $pagination = $this->get('Pagination');


        $this->action        = $uri->toString();
        $this->rows          = $rows;
        $this->task          = $task;
        $this->print         = $print_link;
        $this->params        = $params;
        $this->pagination    = $pagination;
        $this->jemsettings   = $jemsettings;
        $this->settings      = $settings;
        $this->pagetitle     = $pagetitle;
        $this->lists         = $lists;
        $this->novenues      = $novenues;
        $this->permissions   = $permissions;
        $this->show_status   = $permissions->canEditPublishVenue;
        $this->print_link    = $print_link;
        $this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
        $this->eventslist    = $events;
        $this->venueslist    = $events;
        $this->height        = $height;
        $this->venueMarker   = $venueMarker;
        $this->mylocMarker   = $mylocMarker;
        $this->zoom          = $zoom;
        $this->showDateFilter = $showDateFilter;
        $this->showCategoryFilter = $showCategoryFilter;
        $this->showCountryFilter = $showCountryFilter;
        $this->categories    = $categories;
        $this->countries     = $countries;
        $this->selectedCategoryId = $selectedCategoryId;
        $this->selectedCountry = $selectedCountry;
        $this->filterMode    = $filterMode;
        $this->filterDate    = $filterDate;
        $this->selectedDate  = $selectedDate;
        $this->centerLat     = $centerLat;
        $this->centerLng     = $centerLng;
        $this->jemItemid     = $jemItemid;

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @since  1.6
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_SETTINGS_TITLE'), 'settings');
        ToolbarHelper::apply('settings.apply');
        ToolbarHelper::save('settings.save');
        ToolbarHelper::cancel('settings.cancel');

        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolbarHelper::help('settings', true, 'https://www.joomlaeventmanager.net/documentation/views/eventsmap');
    }

    /**
     * Fetches venues with valid coordinates.
     * Optionally filters venues to include only those hosting events within a given date range.
     *
     * @param   string|null $filterStartDate  The start date of the filter range ('YYYY-MM-DD'). If null, no date filter is applied.
     * @param   string|null $filterEndDate    The end date of the filter range ('YYYY-MM-DD'). If null, the range is open-ended (from start date to infinity).
     *
     * @return  array<object> An array of venue objects.
     * @throws  \Exception if a database error occurs.
     */
    public static function getVenues($params, $filterStartDate, $filterEndDate = null)
    {
        return JemMapHelper::getVenues($params, $filterStartDate, $filterEndDate);
    }

    /**
     * Fetches published events hosted at venues with valid coordinates.
     *
     * @param   mixed       $params           Menu parameters.
     * @param   string|null $filterStartDate  Start date of the requested event range.
     * @param   string|null $filterEndDate    End date of the requested event range.
     *
     * @return  array<object>
     */
    public static function getEvents($params, $filterStartDate = null, $filterEndDate = null, $selectedCategoryId = 0, $country = '')
    {
        return JemMapHelper::getEvents($params, $filterStartDate, $filterEndDate, $selectedCategoryId, $country);
    }
}

