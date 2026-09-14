<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * JemView class with JEM specific extensions
 *
 * @package JEM
 */
class JemView extends HtmlView
{
    public $access = null;
    public $action = null;
    public $allowAnnulation = null;
    public $allowRegistration = null;
    public $archive_link = null;
    public $attending = null;
    public $attending_pagination = null;
    public $backlink = null;
    public $cal = null;
    public $categories = null;
    public $catid = null;
    public $catIds = null;
    public $centerLat = null;
    public $centerLng = null;
    public $cimage = null;
    public $cities = null;
    public $contacts = null;
    public $countries = null;
    public $currentweek = null;
    public $dateRegistationFrom = null;
    public $dateRegistationUntil = null;
    public $dateUnregistationUntil = null;
    public $day = null;
    public $daydate = null;
    public $dellink = null;
    public $description = null;
    public $dimage = null;
    public $dispatcher = null;
    public $e_reg = null;
    public $enableemailaddress = null;
    public $event = null;
    public $event_links = null;
    public $events = null;
    public $events_pagination = null;
    public $eventslist = null;
    public $filter = null;
    public $filterDate = null;
    public $filterMode = null;
    public $filter_continent = null;
    public $filter_country = null;
    public $filter_date_from = null;
    public $filter_date_to = null;
    public $filter_show_category = null;
    public $filter_show_continent = null;
    public $filter_show_country = null;
    public $filter_show_dates = null;
    public $filter_show_eventtype = null;
    public $filter_show_venue = null;
    public $filter_type_id = null;
    public $filter_venue_id = null;
    public $formhandler = null;
    public $heatMapLayer = null;
    public $height = null;
    public $ical_link = null;
    public $id = null;
    public $includechildevents = null;
    public $infoimage = null;
    public $invited = null;
    public $isregistered = null;
    public $item = null;
    public $item_root = null;
    public $itemid = null;
    public $jemItemid = null;
    public $jemsettings = null;
    public $limage = null;
    public $link = null;
    public $lists = null;
    public $locid = null;
    public $maxLevel = null;
    public $model = null;
    public $mylocMarker = null;
    public $needLoginFirst = null;
    public $noattending = null;
    public $noevents = null;
    public $novenues = null;
    public $numWaitingPlaces = null;
    public $pageclass_sfx = null;
    public $pagetitle = null;
    public $pagination = null;
    public $param_topcat = null;
    public $params = null;
    public $parent = null;
    public $permissions = null;
    public $print = null;
    public $print_link = null;
    public $registers = null;
    public $registration = null;
    public $returnto = null;
    public $rows = null;
    public $searchfilter = null;
    public $selectedCategoryId = null;
    public $selectedCity = null;
    public $selectedCountry = null;
    public $selectedDate = null;
    public $settings = null;
    public $show_status = null;
    public $showAttendees = null;
    public $showCategoryFilter = null;
    public $showCountryFilter = null;
    public $showDateFilter = null;
    public $showdaydate = null;
    public $showemptysubcats = null;
    public $showeventstate = null;
    public $showRegForm = null;
    public $showsubcats = null;
    public $showvenuestate = null;
    public $task = null;
    public $type = null;
    public $user = null;
    public $venue = null;
    public $venueMarker = null;
    public $venuedescription = null;
    public $venues = null;
    public $venueslist = null;
    public $zoom = null;

    /**
     * Layout style suffix
     *
     * @var    string
     * @since  2.3
     */
    protected $_layoutStyleSuffix = null;

    public function __construct($config = array())
    {
        parent::__construct($config);

        // additional path for layout style + corresponding override path
        $suffix = JemHelper::getLayoutStyleSuffix();
        if (!empty($suffix)) {
            $this->_layoutStyleSuffix = $suffix;
            if (is_dir($this->_basePath . '/view')) {
                $this->addTemplatePath($this->_basePath . '/view/' . $this->getName() . '/tmpl/' . $suffix);
            }
            else {
                $this->addTemplatePath($this->_basePath . '/views/' . $this->getName() . '/tmpl/' . $suffix);
            }
            $this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_jem/' . $this->getName() . '/' . $suffix);
        }
    }

    /**
     * Renders the view after scoping optional page text to its own menu item.
     *
     * @param   string|null  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        if (is_object($this->params)
            && method_exists($this->params, 'set')
            && !JemHelper::isActiveMenuView($this->getName())) {
            // Do not mutate Joomla's shared parameter registry for the request.
            $this->params = clone $this->params;
            $this->params->set('showintrotext', 0);
            $this->params->set('showfootertext', 0);
        }

        $this->prepareEventStatusIndicators();

        parent::display($tpl);
    }

    /**
     * Prepare configurable event status indicators for supported frontend views.
     *
     * The menu option only applies to its own view. This prevents, for example,
     * an Events Blog option from leaking into an event detail opened below that
     * menu item.
     *
     * @return void
     */
    protected function prepareEventStatusIndicators()
    {
        $view = strtolower((string) $this->getName());
        $supportedViews = array(
            'category',
            'day',
            'event',
            'eventsblog',
            'eventslist',
            'search',
            'typeevents',
            'venue',
        );

        if (!in_array($view, $supportedViews, true)
            || !is_object($this->params)
            || !method_exists($this->params, 'get')) {
            return;
        }

        $default = $view === 'eventsblog' ? 1 : 0;
        $enabled = JemHelper::isActiveMenuView($view)
            ? (int) $this->params->get('show_status_indicators', $default)
            : $default;

        if ($enabled !== 1) {
            return;
        }

        $settings = JemHelper::config();
        if (!(int) ($settings->module_status_ribbons ?? 1)) {
            return;
        }

        $candidates = $view === 'event'
            ? (isset($this->item) && is_object($this->item) ? array($this->item) : array())
            : (isset($this->rows) && is_array($this->rows) ? $this->rows : array());
        $events = array_values(array_filter($candidates, static function ($candidate) {
            return is_object($candidate)
                && property_exists($candidate, 'title')
                && (property_exists($candidate, 'event_status') || property_exists($candidate, 'dates'));
        }));

        if ($events === array()) {
            return;
        }

        JemOutput::prepareModuleEventStatuses($events, $settings);
        JemHelper::loadModuleStatusAssets();
    }

    /**
     * Adds a row to data indicating even/odd row number
     *
     * @return object $rows
     */
    public function getRows($rowname = "rows")
    {
        if (!isset($this->$rowname) || !is_array($this->$rowname) || !count($this->$rowname)) {
            return;
        }

        $k = 0;
        foreach($this->$rowname as $row) {
            $row->odd = $k;
            $k = 1 - $k;
        }

        return $this->$rowname;
    }

    /**
     * Add path for common templates.
     */
    protected function addCommonTemplatePath()
    {
        // additional path for list part + corresponding override path
        $this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl');
        $this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_jem/common');

        if (!empty($this->_layoutStyleSuffix)) {
            $this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl/'.$this->_layoutStyleSuffix);
            $this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_jem/common/'.$this->_layoutStyleSuffix);
        }
    }

    /**
     * Prepares the document.
     */
    protected function prepareDocument()
    {
        $app   = Factory::getApplication();
        $menus = $app->getMenu();
        $menu  = $menus->getActive();
        $print = $app->input->getBool('print', false);

        if ($print) {
            JemHelper::loadCss('print');
            $this->document->setMetaData('robots', 'noindex, nofollow');
        }

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            // TODO
            $this->params->def('page_heading', Text::_('COM_JEM_DEFAULT_PAGE_TITLE_DAY'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }
        $this->document->setTitle($title);

        // TODO: Metadata
        $this->document->setMetadata('keywords', $this->params->get('page_title'));
    }
}
