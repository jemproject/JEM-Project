<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Date\Date;


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

        // Add JavaScript
        $document->addScript(Uri::root() . 'media/com_jem/js/leaflet.js');

        if ($print) {
            JemHelper::loadCss('print');
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        // Get data from model
        $rows = $this->get('Items');

        // Are no venues available?
        $novenues = (!$rows) ? 1 : 0;

        // *******************************************************************************************
        $venueMarker = $params->get('venue_markerfile', 'media/com_jem/images/marker.webp');
        $mylocMarker = $params->get('mylocation_markerfile', 'media/com_jem/images/marker-red.webp');

        $venueMarker = rtrim(Uri::root(), '/') . '/' . ltrim((string) $venueMarker, '/');
        $mylocMarker = rtrim(Uri::root(), '/') . '/' . ltrim((string) $mylocMarker, '/');

        $height         = $params->get('height', '500px');
        $zoom           = (int) $params->get('zoom', 8);
        $showDateFilter = (int) $params->get('show_date_filter', 0);
        $jemItemid      = (int) $params->get('jem_itemid', 0);

        // Filter from request (only if backend option is enabled)
        $filterMode      = 'all';
        $filterDate      = null;
        $selectedDate    = '';
        $filterStartDate = null;
        $filterEndDate   = null;

        if ($showDateFilter) {
            $filterMode = $app->input->get('jem_map_filter_mode', 'all', 'string');
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

        // Fetch venues (JOIN + date filter only if $filterDate is not null)
        $venues = $this->getVenues($params, $filterStartDate, $filterEndDate);

        // Get auto center map
        $centerLat    = 0;
        $centerLng    = 0;
        $totalLat     = 0;
        $totalLng     = 0;
        $countVenues  = 0;

        if ($params->get('map_auto_center', 1)) {
            foreach ($venues as $venue) {
                if (!empty($venue->latitude) && !empty($venue->longitude)) {
                    $totalLat += (float) $venue->latitude;
                    $totalLng += (float) $venue->longitude;
                    $countVenues++;
                }
            }

            if ($countVenues > 0) {
                $centerLat = $totalLat / $countVenues;
                $centerLng = $totalLng / $countVenues;
            }
        }

        // Venues ophalen (JOIN + datumfilter alleen als $filterDate != null)
        $venues = $this->getVenues($params, $filterDate);

        // Layout renderen
        //require JModuleHelper::getLayoutPath('mod_leafletmap', $params->get('layout', 'default'));

        // Get Venues
        $db = Factory::getDbo();
        $q  = $db->getQuery(true);

        $q->select('DISTINCT v.id, v.venue, v.alias, v.city, v.latitude, v.longitude, v.country')
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where('v.latitude IS NOT NULL')
            ->where("v.latitude <> ''")
            ->where('v.longitude IS NOT NULL')
            ->where("v.longitude <> ''");

        // Only apply JOIN + date filter when filterDate is truly valid (YYYY-MM-DD)
        if ($filterDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $d = $db->quote($filterDate);

            $q->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.locid = v.id');
            $q->where('(e.dates = ' . $d . ' OR (e.enddates IS NOT NULL AND e.dates <= ' . $d . ' AND ' . $d . ' <= e.enddates))');
        }

        $q->order('v.venue ASC');

        $db->setQuery($q);
        $venueslist = $db->loadObjectList();

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
        $this->venueslist    = $venueslist;
        $this->height        = $height;

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
        ToolBarHelper::help('settings', true, 'https://www.joomlaeventmanager.net/documentation/manual/views/eventsmap');
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
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select('DISTINCT v.id, v.venue, v.alias, v.city, v.latitude, v.longitude, v.country')
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where([
                'v.latitude IS NOT NULL',
                "v.latitude <> ''",
                'v.longitude IS NOT NULL',
                "v.longitude <> ''",
            ]);

        // Apply date filtering only if a start date is provided.
        if ($filterStartDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterStartDate)) {
            $query->join('INNER', $db->quoteName('#__jem_events', 'e'), 'e.locid = v.id');
            $effectiveEventEndDate = 'COALESCE(' . $db->quoteName('e.enddates') . ', ' . $db->quoteName('e.dates') . ')';
            $conditions = [];

            if ($filterEndDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterEndDate)) {
                $conditions[] = $db->quoteName('e.dates') . ' <= ' . $db->quote($filterEndDate);
                $conditions[] = $effectiveEventEndDate . ' >= ' . $db->quote($filterStartDate);
            } else {
                $conditions[] = $effectiveEventEndDate . ' >= ' . $db->quote($filterStartDate);
            }

            $query->where($conditions);
        }

        $query->order($db->quoteName('v.venue') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList();
    }
}

