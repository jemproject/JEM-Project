<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
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
        $wa->registerAndUseStyle('leaflet.css', 'media/com_jem/css/leaflet.css');
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
        $venueMarker = $params->get('venue_markerfile', 'media/com_jem/images/marker-red.webp');
        $mylocMarker = $params->get('mylocation_markerfile', 'media/com_jem/images/marker-shadow.webp');
        $height = $params->get('height', '500px');
        $zoom = (int)$params->get('zoom', 8);
        $showDateFilter = (int)$params->get('show_date_filter', 1);

        // ---- Filter from request (only if backend option is enabled) ----
        $app = Factory::getApplication();
        $filterMode = 'all';
        $filterDate = null;
        $selectedRaw = '';

        if ($showDateFilter) {
            $filterMode = $app->input->get('jemfilter', 'all', 'cmd');   // 'all' of 'date'
            $selectedRaw = $app->input->get('jemdate', '', 'string');     // 'YYYY-MM-DD'

            if ($filterMode === 'date') {
                // Strict validation: only allow valid YYYY-MM-DD values
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedRaw)) {
                    $dt = DateTime::createFromFormat('Y-m-d', $selectedRaw);
                    if ($dt && $dt->format('Y-m-d') === $selectedRaw) {
                        $filterDate = $selectedRaw;
                    }
                }
                // Invalid date? Fall back to 'all' (prevents SQL error)
                if ($filterDate === null) {
                    $filterMode = 'all';
                }
            }
        }
		
		// Venues ophalen (JOIN + datumfilter alleen als $filterDate != null)
		//$venues = ModLeafletmapHelper::getVenues($params, $filterDate);

		// Layout renderen
		//require JModuleHelper::getLayoutPath('mod_leafletmap', $params->get('layout', 'default'));

        // Get Venues
        $db = Factory::getDbo();
        $q = $db->getQuery(true);

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
            $print_link = Route::_('index.php?option=com_jem&view=venuesamp&print=1&tmpl=component');
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

        // Create the pagination object
        // $pagination = $this->get('Pagination');


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
        $this->height = $height;

        parent::display($tpl);
    }
}