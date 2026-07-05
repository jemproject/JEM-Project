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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\Component\Jem\Site\Helper\JemMapHelper;

/**
 * View: VenuesMap
 */
class JemViewVenuesMap extends JemView
{
    public function __construct($config = array())
    {
        parent::__construct($config);
        // additional path for common templates + corresponding override path
        $this->addCommonTemplatePath();
    }

    /**
     * Creates the VenuesMap View
     */
    public function display($tpl = null)
    {
        // Get data from model
        $rows = $this->get('Items');
        $pagination = $this->get('Pagination');

        // initialize variables
        $app         = Factory::getApplication();
        $app->getLanguage()->load('mod_jem_map', JPATH_SITE . '/modules/mod_jem_map');
        $document    = $app->getDocument();
        $wa          = $document->getWebAssetManager();
        $jemsettings = JemHelper::config();
        $settings    = JemHelper::globalattribs();
        $menu        = $app->getMenu();
        $menuitem    = $menu->getActive();
        $params      = $app->getParams();
        $uri         = Uri::getInstance();
        $user        = JemFactory::getUser();
        $userId      = $user->get('id');
        $pathway     = $app->getPathWay();
        $jinput      = $app->input;
        $print       = $jinput->getBool('print', false);
        $task        = $jinput->getCmd('task', '');

        // Decide which parameters should take priority
        $useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem' && $menuitem->query['view'] == 'venuesmap');

        // add javascript
        $wa->registerAndUseScript('leaflet', 'media/com_jem/js/leaflet.js');
        $wa->registerAndUseScript('leaflet.heat', 'media/com_jem/js/leaflet-heat.js');
        $wa->registerAndUseScript('leaflet.fullscreen', 'media/com_jem/js/leaflet-fullscreen.js');
        $wa->registerAndUseStyle('leaflet.css', 'media/com_jem/css/leaflet.css');
        $wa->registerAndUseStyle('leaflet.fullscreen', 'media/com_jem/css/leaflet-fullscreen.css');
        $wa->registerAndUseStyle('jem.css', 'media/com_jem/css/jem.css');

        // Load css
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        if ($print) {
            JemHelper::loadCss('print');
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        // Get data from model
        $rows = $this->get('Items');

        // are no venues available?
        $novenues = (!$rows) ? 1 : 0;

        // *******************************************************************************************
        $venueMarker = JemMapHelper::resolveMarkerUrl($params->get('venue_markerfile', 'media/com_jem/images/marker-red.webp'), 'media/com_jem/images/marker-red.webp');
        $mylocMarker = JemMapHelper::resolveMarkerUrl($params->get('mylocation_markerfile', 'media/com_jem/images/marker-blue.webp'), 'media/com_jem/images/marker-blue.webp');
        $height             = $params->get('height', '500px');
        $zoom               = (int) $params->get('map_zoom', 4);
        $showCountryFilter  = (int) $params->get('show_country_filter', 1);
        $showCategoryFilter = (int) $params->get('show_category_filter', 0);
        $defaultCountry     = trim((string) $params->get('default_country', ''));
        if ($defaultCountry === '0') {
            $defaultCountry = '';
        }
        $selectedCountry    = $showCountryFilter ? trim($app->input->getString('jem_map_filter_country', $defaultCountry)) : $defaultCountry;
        $selectedCity       = $showCountryFilter ? trim($app->input->getString('jem_map_filter_city', '')) : '';
        $selectedCategoryId = $showCategoryFilter ? $app->input->getInt('jem_map_filter_catid', 0) : 0;
        $venueOrder         = (string) $params->get('venues_order', 'name_asc');
        $countries          = JemMapHelper::getVenueCountries();
        $categories         = $showCategoryFilter ? JemMapHelper::getCategories($params) : [];
        foreach ($countries as $country) {
            $countryCode = (string) $country->country;
            $countryName = JemHelperCountries::getCountryName($countryCode);
            $country->country_name = $countryName ?: $countryCode;
        }
        usort($countries, static function ($a, $b) {
            return strcasecmp((string) $a->country_name, (string) $b->country_name);
        });
        $validCountries     = array_map(static function ($country) {
            return (string) $country->country;
        }, $countries);
        $validCategoryIds   = array_map(static function ($category) {
            return (int) $category->id;
        }, $categories);

        if ($selectedCountry !== '' && !in_array($selectedCountry, $validCountries, true)) {
            $selectedCountry = '';
            $selectedCity = '';
        }

        $cities = JemMapHelper::getVenueCities($selectedCountry);
        $validCities = array_map(static function ($city) {
            return (string) $city->city;
        }, $cities);

        if ($selectedCity !== '' && !in_array($selectedCity, $validCities, true)) {
            $selectedCity = '';
        }

        if ($selectedCategoryId > 0 && !in_array($selectedCategoryId, $validCategoryIds, true)) {
            $selectedCategoryId = 0;
        }

        $categoryStartDate = $selectedCategoryId > 0 ? Factory::getDate()->format('Y-m-d') : null;
        $venueslist = JemMapHelper::getVenues($params, $categoryStartDate, null, $selectedCategoryId, $selectedCountry, $selectedCity, $venueOrder);

        // Pagination over the filtered venueslist
        $listLimit   = max(1, (int) $params->get('venues_list_limit', $app->get('list_limit', 20)));
        $limitstart  = max(0, $jinput->getInt('limitstart', 0));
        $totalVenues = count($venueslist);
        if ($limitstart >= $totalVenues && $totalVenues > 0) {
            $limitstart = 0;
        }
        $venueslistPage = array_slice($venueslist, $limitstart, $listLimit);
        $pagination = new Pagination($totalVenues, $limitstart, $listLimit);
        $pagination->setAdditionalUrlParam('jem_map_filter_country', $selectedCountry);
        $pagination->setAdditionalUrlParam('jem_map_filter_city', $selectedCity);
        if ($selectedCategoryId > 0) {
            $pagination->setAdditionalUrlParam('jem_map_filter_catid', $selectedCategoryId);
        }

        if ($params->get('map_auto_center', 1)) {
            [$centerLat, $centerLng] = JemMapHelper::getCenter($venueslist);
        } else {
            $centerLat = $centerLng = 0;
        }

        // *******************************************************************************************

        // get variables
        $filter_order     = $app->getUserStateFromRequest('com_jem.venueslist.filter_order', 'filter_order',     'a.city', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest('com_jem.venueslist.filter_order_Dir', 'filter_order_Dir',    '', 'word');
//         $filter_state     = $app->getUserStateFromRequest('com_jem.venueslist.filter_state', 'filter_state',     '*', 'word');
        $filter           = $app->getUserStateFromRequest('com_jem.venueslist.filter_type', 'filter_type', '', 'int');
        $search           = $app->getUserStateFromRequest('com_jem.venueslist.filter_search', 'filter_search', '', 'string');

        // search filter
        $filters = array();

        // Workaround issue #557: Show venue name always.
        $jemsettings->showlocate = 1;

        //$filters[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_CHOOSE'));
        
        if ($jemsettings->showlocate == 1) {
            $filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
        }
        $filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));            
        $filters[] = HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE'));
        $lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'form-select'), 'value', 'text', $filter);

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
        $pagetitle = Text::_('COM_JEM_VENUESMAP_PAGETITLE');
        $pageheading = $pagetitle;
        $pageclass_sfx = '';

        // Check to see which parameters should take priority
        if ($useMenuItemParams) {
            // Menu item params take priority
            $params->def('page_title', $menuitem->title);
            $pagetitle = $params->get('page_title', Text::_('COM_JEM_VENUESMAP_PAGETITLE'));
            $pageheading = $params->get('page_heading', $pagetitle);
            $pageclass_sfx = $params->get('pageclass_sfx');
            $print_link = Route::_('index.php?option=com_jem&view=venuesmap&print=1&tmpl=component');
        }

        $params->set('page_heading', $pageheading);

        // Add site name to title if param is set
        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        }
        elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        //Check if the user has permission to add things
        $permissions = new stdClass();
        //$permissions->canAddEvent = $user->can('add', 'event');
        $permissions->canAddVenue = $user->can('add', 'venue');
        $permissions->canEditPublishVenue = $user->can(array('edit', 'publish'), 'venue');


        $this->action = $uri->toString();
        $this->rows = $rows;
        $this->task = $task;
        $this->print = $print;
        $this->params = $params;
        $this->pagination = $pagination;
        $this->jemsettings = $jemsettings;
        $this->settings = $settings;
        $this->pagetitle = $pagetitle;
        $this->lists = $lists;
        $this->novenues = $novenues;
        $this->permissions = $permissions;
        $this->show_status = $permissions->canEditPublishVenue;
        $this->print_link = $print_link;
        $this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;

        $this->venueslist = $venueslist;
        $this->venueslistPage = $venueslistPage;
        $this->height = $height;
        $this->venueMarker = $venueMarker;
        $this->mylocMarker = $mylocMarker;
        $this->zoom = $zoom;
        $this->heatMapLayer = (int) $params->get('heat_layer', 1);
        $this->jemItemid = (int) $params->get('jem_itemid', 0);
        $this->centerLat = $centerLat;
        $this->centerLng = $centerLng;
        $this->showCountryFilter = $showCountryFilter;
        $this->showCategoryFilter = $showCategoryFilter;
        $this->countries = $countries;
        $this->cities = $cities;
        $this->categories = $categories;
        $this->selectedCountry = $selectedCountry;
        $this->selectedCity = $selectedCity;
        $this->selectedCategoryId = $selectedCategoryId;

        parent::display($tpl);
    }
}
