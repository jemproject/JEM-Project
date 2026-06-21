<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

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
        $timestamp = mktime(0, 0, 0, date("m"), date("d") + $dayoffset, date("Y"));

        return date('Y-m-d', $timestamp);
    }

    /**
     * Method to set the date
     *
     * @access public
     * @param  string
     */
    public function setDate($date)
    {
        $app = Factory::getApplication();

        # Get the params of the active menu item
        $params = $app->getParams('com_jem');

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

        $params = Factory::getApplication()->getParams('com_jem');
        $requestedDaysToShow = Factory::getApplication()->input->getInt('timeline_days_to_show', 0);
        $daysToShow = $requestedDaysToShow > 0
            ? max(1, min(30, $requestedDaysToShow))
            : max(1, min(30, (int) $params->get('timeline_days_to_show', 1)));
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
        $task              = $app->input->getCmd('task', '');
        $requestVenueId    = $app->input->getInt('locid', 0);
        $requestCategoryId = $app->input->getInt('catid', 0);
        $item              = $app->input->getInt('Itemid', 0);
        $showArchivedRequest = $app->input->getString('show_archived_events', null);
        $showArchivedEvents  = $showArchivedRequest === null
            ? (bool) $params->get('show_archived_events', 0)
            : (bool) $app->input->getInt('show_archived_events', 0);

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
        $locids = explode(',', $app->input->getString('locids', ''));
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
        $catids = explode(',', $app->input->getString('catids', ''));
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

        // maybe top category is given by calendar view
        $top_category = $app->input->getInt('topcat', 0);
        if ($top_category > 0) { // accept 'root'
            $children = JemCategories::getChilds($top_category);
            if (count($children)) {
                $where = 'rel.catid IN ('. implode(',', $children) .')';
                $this->setState('filter.category_top', $where);
            }
        }

        # limit/start

        /* Preserve limitstart when it is missing from the request. */
        if ($app->input->getInt('limitstart', null) === null) {
            $app->setUserState('com_jem.day.'.$itemid.'.limitstart', 0);
        }

        $limit = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
        $this->setState('list.limit', $limit);

        $limitstart = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
        $limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
        $this->setState('list.start', $limitstart);

        # Search
        $search = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_search', 'filter_search', '', 'string');
        $this->setState('filter.filter_search', $search);

        # FilterType
        $filtertype = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
        $this->setState('filter.filter_type', $filtertype);

        # filter_order
        $orderCol = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
        $this->setState('filter.filter_ordering', $orderCol);

        # filter_direction
        $listOrder = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
        $this->setState('filter.filter_direction', $listOrder);

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

        # published
        /// @todo bring given pub together with eventslist's unpub calculation (_populatePublishState())
        $pub = explode(',', $app->input->getString('pub', ''));
        $published = array();
        // sanitize remote data
        foreach ($pub as $val) {
            if (((int)$val >= 1) && ((int)$val <= 2)) {
                $published[] = (int)$val;
            }
        }
        // default to 'published'
        if (empty($published)) {
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
        $query->clear('order');
        $query->clear('group');

        $eventEnd = 'IF(a.enddates >= a.dates, a.enddates, a.dates)';

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

        $requestVenueId = $this->getState('filter.req_venid');
        if ($requestVenueId){
            $query->where(' a.locid = '.$this->_db->quote($requestVenueId));
        }

        // Select events that overlap the configured day/timeline range.
        $query->where(
            'a.dates <= ' . $this->_db->quote($this->getDayEnd())
            . ' AND IF(a.enddates >= a.dates, a.enddates, a.dates) >= ' . $this->_db->quote($this->_date)
        );

        return $query;
    }
}
?>
