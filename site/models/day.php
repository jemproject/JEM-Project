<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/classes/filterinput.class.php';

require_once __DIR__ . '/../classes/listlimit.class.php';
require_once __DIR__ . '/../classes/listorder.class.php';
require_once __DIR__ . '/../helpers/calendardaylink.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/eventslist.php';

/**
 * Model-Day
 */
class JemModelDay extends JemModelEventslist
{
    protected $_date = null;
    protected $_dateEnd = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $rawday = Factory::getApplication()->input->getCmd('id', '');
        $this->setDate($rawday);
    }

    /**
     * Return the menu configured offset date.
     */
    private function getOffsetDate($params)
    {
        $dayoffset = (int) $params->get('days', 0);

        return JemHelper::getJoomlaDate($dayoffset);
    }

    /**
     * Returns component params with active Day menu params applied.
     */
    private function getDayParams()
    {
        $app = Factory::getApplication();
        $params = $app->getParams('com_jem');
        $menuitem = $app->getMenu()->getActive();

        if ($menuitem
            && isset($menuitem->query['option'], $menuitem->query['view'])
            && $menuitem->query['option'] === 'com_jem'
            && $menuitem->query['view'] === 'day'
            && method_exists($menuitem, 'getParams')) {
            foreach ($menuitem->getParams()->toArray() as $key => $value) {
                $params->set($key, $value);
            }
        }

        return $params;
    }

    /**
     * Method to set the date
     *
     * @access public
     * @param  string    $date          Requested YYYYMMDD date.
     * @param  int|null  $daysOverride  Force a one-day range for a one-day layout.
     */
    public function setDate($date, ?int $daysOverride = null)
    {
        $app = Factory::getApplication();

        # Get the params of the active menu item
        $params = $this->getDayParams();

        $date = trim((string) $date);

        # 0 or empty means we have a direct request from a menu item and without any date params.
        if ($date === '' || $date === '0') {
            $date = $this->getOffsetDate($params);

        # a valid date has 8 digits (ymd)
        } elseif (preg_match('/^\d{8}$/', $date)) {
            $year  = substr($date, 0, -4);
            $month = substr($date, 4, -2);
            $day   = substr($date, 6);

            //check if date is valid
            if (checkdate($month, $day, $year)) {
                $date = $year.'-'.$month.'-'.$day;
            } else {
                // Date isn't valid: raise notice and use the menu offset date.
                $date = $this->getOffsetDate($params);
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_INVALID_DATE_REQUESTED_USING_CURRENT'), 'notice');
            }
        } else {
            // Date isn't valid: raise notice and use the menu offset date.
            $date = $this->getOffsetDate($params);
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_INVALID_DATE_REQUESTED_USING_CURRENT'), 'notice');
        }

        $this->_date = $date;

        $params = $this->getDayParams();
        $requestedDaysToShow = Factory::getApplication()->input->getInt('timeline_days_to_show', 0);
        $daysToShow = $requestedDaysToShow > 0
            ? max(1, min(30, $requestedDaysToShow))
            : max(1, min(30, (int) $params->get('timeline_days_to_show', 1)));
        if ($daysOverride !== null) {
            $daysToShow = max(1, min(30, $daysOverride));
        }
        $startDate = new DateTimeImmutable($date);
        $this->_dateEnd = $startDate->modify('+' . ($daysToShow - 1) . ' days')->format('Y-m-d');
    }

    /**
     * Return date
     */
    public function getDay()
    {
        return $this->_date;
    }

    /**
     * Return the end date of the timeline range.
     */
    public function getDayEnd()
    {
        return $this->_dateEnd ?: $this->_date;
    }

    /**
     * Method to auto-populate the model state.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        # parent::populateState($ordering, $direction);

        $app               = Factory::getApplication();
        $jemsettings       = JemHelper::config();
        $itemid            = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);
        $params            = $app->getParams();
        $sourceMenu = $app->getMenu()->getActive();
        $sourceView = JemCalendarDayLink::sourceView((array) ($sourceMenu->query ?? array()));
        $this->setState('filter.calendar_startdayonly', $sourceView !== '' && (bool) $params->get('show_only_start', false));
        $scope = JemCalendarDayLink::context($app->input);
        $this->setState('filter.calendar_excluded_categories', $scope['calcats'] ?? '');
        $this->setState('filter.calendar_excluded_venues', $scope['calvenues'] ?? '');
        $this->setState('filter.calendar_hide_all', !empty($scope['calhide']));
        $task              = $app->input->getCmd('task', '');
        $requestVenueId    = JemFilterInput::read($app->input, 'locid', 0, 'int');
        $requestCategoryId = JemFilterInput::read($app->input, 'catid', 0, 'int');
        $item              = $app->input->getInt('Itemid', 0);
        $showArchivedRequest = JemFilterInput::read($app->input, 'show_archived_events', null);
        $showArchivedEvents  = $showArchivedRequest === null
            ? (bool) $params->get('show_archived_events', 0)
            : (bool) JemFilterInput::read($app->input, 'show_archived_events', 0, 'int');

        $this->show_archived_events = $showArchivedEvents;
        $this->setState('filter.show_archived_events', $showArchivedEvents);
        $normaliseIds      = static function ($value) {
            if (is_array($value)) {
                $ids = $value;
            } else {
                $value = trim((string) $value);
                $ids   = $value === '' ? array() : explode(',', $value);
            }

            $ids = array_map('intval', $ids);

            return array_values(array_filter($ids));
        };
        $normaliseStrings  = static function ($value) {
            if (is_array($value)) {
                $values = $value;
            } else {
                $value  = trim((string) $value);
                $values = $value === '' ? array() : explode(',', $value);
            }

            $values = array_map('trim', $values);

            return array_values(array_filter($values, static function ($entry) {
                return $entry !== '';
            }));
        };

        $locid = $app->getUserState('com_jem.venuecal.locid'.$item);
        if ($locid) {
            $this->setState('filter.filter_locid', $locid);
        }

        // maybe list of venue ids from calendar module
        $locids = explode(',', JemFilterInput::read($app->input, 'locids', ''));
        foreach ($locids as $id) {
            if ((int)$id > 0) {
                $venues[] = (int)$id;
            }
        }
        if (!empty($venues)) {
            $this->setState('filter.venue_id', $venues);
            $this->setState('filter.venue_id.include', true);
        }

        $cal_category_catid = $app->getUserState('com_jem.categorycal.catid'.$item);
        if ($cal_category_catid) {
            $this->setState('filter.req_catid', $cal_category_catid);
        }

        // maybe list of venue ids from calendar module
        $catids = explode(',', JemFilterInput::read($app->input, 'catids', ''));
        foreach ($catids as $id) {
            if ((int)$id > 1) { // don't accept 'root'
                $cats[] = (int)$id;
            }
        }
        if (!empty($cats)) {
            $this->setState('filter.category_id', $cats);
            $this->setState('filter.category_id.include', true);
        }

        $timelineVenueIds = $normaliseIds($params->get('timeline_filter_venues', array()));
        if ($timelineVenueIds) {
            $this->setState('filter.venue_id', $timelineVenueIds);
            $this->setState('filter.venue_id.include', true);
        }

        $timelineTypeIds = $normaliseIds($params->get('timeline_filter_types', array()));
        if ($timelineTypeIds) {
            $this->setState('filter.type_id', $timelineTypeIds);
        }

        $timelineCountries = $normaliseStrings($params->get('timeline_filter_countries', array()));
        if ($timelineCountries) {
            $this->setState('filter.country_id', $timelineCountries);
            $this->setState('filter.country_id.include', true);
        }

        $this->applyMenuContactFilters($params);
################################
        ## EXCLUDE/INCLUDE CATEGORIES ##
        ################################

        $catswitch = $params->get('categoryswitch', '');
        $switchCats = $normaliseIds($params->get('categoryswitchcats', array()));

        # set included categories
        if ($catswitch && $switchCats) {
                $this->setState('filter.category_id', $switchCats);
                $this->setState('filter.category_id.include', true);
        }

        # set excluded categories
        if (!$catswitch && $switchCats) {
                $this->setState('filter.category_id', $switchCats);
                $this->setState('filter.category_id.include', false);
        }

        // Keep Day's menu policy identical to the source calendar (including featured/subcategories).
        $this->applyMenuEventFilters($params);

        // maybe top category is given by calendar view
        $top_category = JemFilterInput::read($app->input, 'topcat', 0, 'int');
        if ($top_category > 0) { // accept 'root'
            $children = JemCategories::getChilds($top_category);
            if (count($children)) {
                $where = 'rel.catid IN ('. implode(',', $children) .')';
                $this->setState('filter.category_top', $where);
            }
        }

        # limit/start

        [$limit, $limitstart] = \JemListLimit::resolve($app, 'com_jem.day.' . $itemid, $jemsettings->display_num);
        $this->setState('list.limit', $limit);
        $this->setState('list.start', $limitstart);

        # Search
        $search = JemFilterInput::state($app, 'com_jem.day.'.$itemid.'.filter_search', 'filter_search', '', 'string');
        $this->setState('filter.filter_search', $search);

        # FilterType
        $filtertype = JemFilterInput::state($app, 'com_jem.day.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
        $this->setState('filter.filter_type', $filtertype);

        [$orderCol, $listOrder] = JemListOrder::resolve(
            $app, 'com_jem.day.' . $itemid, JemListOrder::eventFields(),
            'a.dates', 'ASC', $task === 'archive'
        );
        $this->setState('filter.filter_ordering', $orderCol);
        $this->setState('filter.filter_direction', $listOrder);
        $this->setState('list.ordering', $orderCol);
        $this->setState('list.direction', $listOrder);

        $defaultOrder = ($task == 'archive') ? 'DESC' : 'ASC';
        if ($orderCol == 'a.dates') {
            $orderby = array('a.dates ' . $listOrder, 'a.times ' . $listOrder, 'a.created ' . $listOrder);
        } else {
            $orderby = array($orderCol . ' ' . $listOrder,
                             'a.dates ' . $defaultOrder, 'a.times ' . $defaultOrder, 'a.created ' . $defaultOrder);
        }
        $this->setState('filter.orderby', $orderby);

        # params
        $this->setState('params', $params);
        $this->setState('filter.event_tree', $params->get('event_tree_mode', 'calendar'));

        # published
        /// @todo bring given pub together with eventslist's unpub calculation (_populatePublishState())
        $pub = explode(',', JemFilterInput::read($app->input, 'pub', ''));
        $published = array();
        // sanitize remote data
        foreach ($pub as $val) {
            if (((int)$val >= 1) && ((int)$val <= 2)) {
                $published[] = (int)$val;
            }
        }
        // default to 'published'
        if ($task === 'archive' || empty($published)) {
            //$published[] = 1;
            $this->_populatePublishState($task);
        } else {
            $this->setState('filter.published', $published);
        }

        # request venue-id
        if ($requestVenueId) {
            $this->setState('filter.req_venid', $requestVenueId);
        }

        # request cat-id
        if ($requestCategoryId) {
            $this->setState('filter.req_catid', $requestCategoryId);
        }

        // Category calendars select the category itself, not Day's optional descendants.
        if ($sourceView === 'category' && $requestCategoryId > 0) {
            $this->setState('filter.req_catid', null);
            $this->setState('filter.filter_catid', $requestCategoryId);
        }
        // Venue calendars include the complete physical site in 5.1.
        if ($sourceView === 'venue' && $requestVenueId > 0) {
            $this->setState('filter.req_venid', null);
            $this->setState('filter.filter_locid', null);
            $this->setState('filter.venue_id', $this->getVenueTreeIds($requestVenueId));
            $this->setState('filter.venue_id.include', true);
        }

        # groupby
        $this->setState('filter.groupby', array('a.id'));
    }

    /**
     * Method to get a list of events.
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items) {
            return $items;
        }

        return array();
    }

    /**
     * Return the closest event date before or after the given date.
     *
     * Multi-day events are treated as active on every date between start and end.
     */
    public function getAdjacentEventDate($date, $direction = 'next')
    {
        if (!JemHelper::isValidDate($date)) {
            return null;
        }

        $direction = $direction === 'previous' ? 'previous' : 'next';
        $db = $this->_db;
        $query = parent::getListQuery();
        $query->clear('select');
        $this->applyDayScope($query);
        $query->clear('order');
        $query->clear('group');

        $eventEnd = $this->getState('filter.calendar_startdayonly', false)
            ? 'a.dates' : 'IF(a.enddates >= a.dates, a.enddates, a.dates)';

        if ($direction === 'previous') {
            $candidateDate = 'LEAST(' . $eventEnd . ', DATE_SUB(' . $db->quote($date) . ', INTERVAL 1 DAY))';
            $query->select($candidateDate . ' AS navigation_date')
                ->where('a.dates <= DATE_SUB(' . $db->quote($date) . ', INTERVAL 1 DAY)')
                ->order('navigation_date DESC');
        } else {
            $candidateDate = 'GREATEST(a.dates, DATE_ADD(' . $db->quote($date) . ', INTERVAL 1 DAY))';
            $query->select($candidateDate . ' AS navigation_date')
                ->where($eventEnd . ' >= DATE_ADD(' . $db->quote($date) . ', INTERVAL 1 DAY)')
                ->where('a.dates <= ' . $eventEnd)
                ->order('navigation_date ASC');
        }

        $db->setQuery($query, 0, 1);

        return $db->loadResult() ?: null;
    }

    /**
     * @return JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $query = parent::getListQuery();

        $this->applyDayScope($query);
        if ($this->getState('filter.calendar_startdayonly', false)) {
            $query->where('a.dates >= ' . $this->_db->quote($this->_date));
        }

        // Select events that overlap the configured day/timeline range.
        $query->where(
            'a.dates <= ' . $this->_db->quote($this->getDayEnd())
            . ' AND IF(a.enddates >= a.dates, a.enddates, a.dates) >= ' . $this->_db->quote($this->_date)
        );

        return $query;
    }

    /**
     * Narrow the shared ACL-filtered query to the venue and visible legend items.
     *
     * @param object $query Joomla query, modified in place.
     * @return void
     */
    private function applyDayScope($query): void
    {
        $venue = (int) $this->getState('filter.req_venid');
        if ($venue > 0) {
            $query->where('a.locid = ' . $venue);
        }
        if ($this->getState('filter.calendar_hide_all', false)) {
            $query->where('1 = 0');
        }
        $categories = $this->getState('filter.calendar_excluded_categories', '');
        if ($categories !== '') {
            $query->where('NOT EXISTS (SELECT 1 FROM #__jem_cats_event_relations AS hidden_category'
                . ' WHERE hidden_category.itemid = a.id AND hidden_category.catid IN (' . $categories . '))');
        }
        $venues = $this->getState('filter.calendar_excluded_venues', '');
        if ($venues !== '') {
            $query->where('a.locid NOT IN (' . $venues . ')');
        }
    }

    /**
     * Separate cached day results/totals when dates or legend restrictions differ.
     *
     * @param string $id Existing cache suffix.
     * @return string Parent cache key with the additional Day scope.
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->_date . ':' . $this->getDayEnd();
        foreach (array('calendar_excluded_categories', 'calendar_excluded_venues', 'calendar_hide_all', 'filter_catid') as $key) {
            $id .= ':' . serialize($this->getState('filter.' . $key));
        }

        return parent::getStoreId($id);
    }

    /**
     * Match the source venue calendar's descendants, including cycle protection.
     *
     * @param int $rootId Physical site's venue ID.
     * @return array Root and descendant IDs; shared query still applies ACL.
     */
    protected function getVenueTreeIds($rootId)
    {
        $ids = array((int) $rootId);
        $pending = $ids;
        $db = Factory::getContainer()->get('DatabaseDriver');

        while ($pending && count($ids) < 1000) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('parent_venue_id') . ' IN (' . implode(',', array_map('intval', $pending)) . ')');
            $db->setQuery($query);
            $children = array_values(array_diff(array_map('intval', $db->loadColumn() ?: array()), $ids));
            $ids = array_merge($ids, $children);
            $pending = $children;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
?>
