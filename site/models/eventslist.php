<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Utilities\ArrayHelper; 
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Date\Date; // Añadido para mejor práctica en Joomla 5

// ensure JemFactory is loaded (because model is used by modules too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');

/**
 * Model-Eventslist
 **/
class JemModelEventslist extends ListModel
{
    /**
     * Constructor.
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields']))
        {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'title', 'a.title',
                'dates', 'a.dates',
                'times', 'a.times',
                'alias', 'a.alias',
                'venue', 'l.venue', 'venue_title',
                'city', 'l.city', 'venue_city',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'c.catname', 'category_title',
                'state', 'a.state',
                'access', 'a.access', 'access_level',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'ordering', 'a.ordering',
                'featured', 'a.featured',
                'language', 'a.language',
                'hits', 'a.hits',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
            );
        }

        parent::__construct($config);
    }

    /**
     * Get events for AJAX load more functionality
     */
    public function getEventsAjax(int $offset = 0, int $limit = 10)
    {
        // Keep current filters and sorting
        $currentStart = $this->getState('list.start', 0);
        $currentLimit = $this->getState('list.limit', 10);

        // set temporary new values
        $this->setState('list.start', $offset);
        $this->setState('list.limit', $limit);

        // load items
        $items = $this->getItems();
        $total = $this->getTotal();

        // Restore original values
        $this->setState('list.start', $currentStart);
        $this->setState('list.limit', $currentLimit);

        return [
            'items' => $items,
            'hasMore' => ($offset + $limit) < $total,
            'total' => $total
        ];
    }

    /**
     * Method to auto-populate the model state.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app         = Factory::getApplication();
        $jemsettings = JemHelper::config();
        $task        = $app->input->getCmd('task', '');
        $format      = $app->input->getCmd('format', false);
        $itemid      = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);
        $params      = $app->getParams();

        if (!$task) {
            $task = ($params->get('show_archived_events') ? 'archive' : '');
        }

        # limit/start
        if (empty($format) || ($format == 'html')) {
            /* in J! 3.3.6 limitstart is removed from request - but we need it! */
            if ($app->input->get('limitstart', null, 'int') === null) {
                $app->setUserState('com_jem.eventslist.' . $itemid . '.limitstart', 0);
            }

            $limit      = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.limit', 'limit', $jemsettings->display_num, 'int');
            $this->setState('list.limit', $limit);
            $limitstart = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.limitstart', 'limitstart', 0, 'int');
            // correct start value if required
            $limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
            $this->setState('list.start', $limitstart);
        }

        # Search - variables
        $search     = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_search', 'filter_search', '', 'string');
        $this->setState('filter.filter_search', $search); // must be escaped later

        $filtertype = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_type', 'filter_type', 0, 'int');
        $this->setState('filter.filter_type', $filtertype);

        $filtermonth = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_month', 'filter_month', 0, 'string');
        $this->setState('filter.filter_month', $filtermonth);

        # Search - Filter by setting menu
        $today = new Date('now', $app->get('offset'));

        $filterDaysBefore = $params->get('tablefiltereventfrom', 0);
        $dateFromValue = null;
        $whereFrom = null;
        if ($filterDaysBefore > 0) {
            $dateFromValue = (clone $today)->modify('-' . $filterDaysBefore . ' days')->format('Y-m-d');
             $whereFrom = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), "' . $dateFromValue . '") >= 0';
        }
        if (!empty($whereFrom)) {
            $this->setState('filter.calendar_from', $whereFrom);
        } else {
            $this->setState('filter.calendar_from', null);
        }

        $filterDaysAfter = $params->get('tablefiltereventuntil', 0);
        $whereTo = null;
        $dateToValue = null;
        if ($filterDaysAfter > 0) {
            $dateToValue = (clone $today)->modify('+' . $filterDaysAfter . ' days')->format('Y-m-d');
            $whereTo = ' DATEDIFF(a.dates, "' . $dateToValue . '") <= 0';
        }
        if (!empty($whereTo)) {
            $this->setState('filter.calendar_to', $whereTo);
        } else {
            $this->setState('filter.calendar_to', null);
        }

        # publish state
        $this->_populatePublishState($task);

        $params = $app->getParams();
        $this->setState('params', $params);

        ###############
        ## opendates ##
        ###############

        $this->setState('filter.opendates', $params->get('showopendates', 0));

        ###########
        ## ORDER ##
        ###########

        $filter_order = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_order', 'filter_order', 'a.dates', 'cmd');
        $filter_order_DirDefault = 'ASC';
        // Reverse default order for dates in archive mode
        if ($task == 'archive' && $filter_order == 'a.dates') {
            $filter_order_DirDefault = 'DESC';
        }

        $tableInitialorderby = $params->get('tableorderby', '0');
        
        if (empty($app->input->get('filter_type')) && $tableInitialorderby) {

            switch ($tableInitialorderby) {
                case 0:
                    $tableInitialorderby = 'a.dates';
                    break;
                case 1:
                    $tableInitialorderby = 'a.title';
                    break;
                case 2:
                    $tableInitialorderby = 'l.venue';
                    break;
                case 3:
                    $tableInitialorderby = 'l.city';
                    break;
                case 4:
                    $tableInitialorderby = 'l.state';
                    break;
                case 5:
                    $tableInitialorderby = 'c.catname';
                    break;
                default:
                    $tableInitialorderby = 'a.dates';
            }
            $filter_order = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_order', 'filter_order', $tableInitialorderby, 'cmd');

            $tableInitialDirectionOrder = $params->get('tabledirectionorder', 'ASC');
            if ($tableInitialDirectionOrder) {
                $filter_order_DirDefault = $tableInitialDirectionOrder;
            }
        }

        // Finalize order direction from request/session, falling back to determined default
        $filter_order_Dir = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');

        $default_order_Dir = ($task == 'archive') ? 'DESC' : 'ASC';

        $orderby = array($filter_order . ' ' . $filter_order_Dir, 'a.dates ' . $default_order_Dir, 'a.times ' . $default_order_Dir, 'a.created ' . $default_order_Dir);

        $this->setState('filter.orderby', $orderby);

        ################################
        ## EXCLUDE/INCLUDE CATEGORIES ##
        ################################

        $catswitch = $params->get('categoryswitch', '');
        $cats      = trim($params->get('categoryswitchcats', ''));
        $list_cats = [];

        if ($cats) {
            $ids_cats = explode(",", $cats);
            $ids_cats = ArrayHelper::toInteger($ids_cats);

            if ($params->get('includesubcategories', 0)) {
                // Get subcategories
                foreach ($ids_cats as $idcat) {
                    if (!in_array($idcat, $list_cats, true)) {
                        $list_cats[] = $idcat;
                        $child_cat   = $this->getListChildCat($idcat, false);

                        if ($child_cat) {
                            $list_cats = array_unique(array_merge($list_cats, $child_cat));
                        }
                    }
                }
            } else {
                $list_cats = $ids_cats;
            }

            if ($catswitch) {
                # set included categories
                $this->setState('filter.category_id', $list_cats);
                $this->setState('filter.category_id.include', true);
            } else {
                # set excluded categories
                $this->setState('filter.category_id', $list_cats);
                $this->setState('filter.category_id.include', false);
            }
        }
        $this->setState('filter.groupby', array('a.id'));
    }

    /**
     * Method to get a all list of children categories (subtree) by $id category.
     */
    public function getListChildCat(int $id, bool $reset)
    {
        $user     = JemFactory::getUser();
        $levels   = $user->getAuthorisedViewLevels();
        $settings = JemHelper::globalattribs();
        $db = Factory::getContainer()->get('DatabaseDriver');

        static $catchildlist = [];

        if ($reset) {
            $catchildlist = [];
        }

        // Query
        $query = $db->getQuery(true)
            ->select('DISTINCT c.id')
            ->from('#__jem_categories as c')
            ->where('c.published = 1')
            ->where('c.access IN (' . implode(',', $levels) . ')')
            ->where('c.parent_id = ' . $id);

        $db->setQuery($query);
        $cats = $db->loadColumn();

        if ($cats) {
            foreach ($cats as $catid) {
                $catchildlist[] = (int) $catid;
                $this->getListChildCat((int) $catid, false);
            }
        }

        return array_unique($catchildlist);
    }

    /**
     * set limit
     */
    public function setLimit(int $value)
    {
        $this->setState('list.limit', $value);
    }

    /**
     * set limitstart
     */
    public function setLimitStart(int $value)
    {
        $this->setState('list.start', $value);
    }

    /**
     * set limits for infinite scroll
     */
    public function getMoreEvents(int $limitstart)
    {
        $this->setLimitStart($limitstart);

        // make sure a limit is set
        if (!$this->getState('list.limit')) {
            $this->setLimit(10);
        }

        return $this->getItems();
    }

    /**
     * Method to get a store id based on model configuration state.
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . serialize($this->getState('filter.published'));
        $id .= ':' . $this->getState('filter.opendates');
        $id .= ':' . $this->getState('filter.featured');
        $id .= ':' . serialize($this->getState('filter.event_id'));
        $id .= ':' . $this->getState('filter.event_id.include');
        $id .= ':' . serialize($this->getState('filter.category_id'));
        $id .= ':' . $this->getState('filter.category_id.include');
        $id .= ':' . serialize($this->getState('filter.venue_id'));
        $id .= ':' . $this->getState('filter.venue_id.include');
        $id .= ':' . $this->getState('filter.venue_state');
        $id .= ':' . $this->getState('filter.venue_state.mode');
        $id .= ':' . $this->getState('filter.filter_search');
        $id .= ':' . $this->getState('filter.filter_type');
        $id .= ':' . $this->getState('list.start');
        $id .= ':' . $this->getState('list.limit');
        $id .= ':' . serialize($this->getState('filter.groupby'));
        $id .= ':' . serialize($this->getState('filter.orderby'));
        $id .= ':' . $this->getState('filter.category_top');
        $id .= ':' . $this->getState('filter.calendar_multiday');
        $id .= ':' . $this->getState('filter.calendar_startdayonly');
        $id .= ':' . $this->getState('filter.show_archived_events');
        $id .= ':' . $this->getState('filter.req_venid');
        $id .= ':' . $this->getState('filter.req_catid');
        $id .= ':' . $this->getState('filter.unpublished');
        $id .= ':' . serialize($this->getState('filter.unpublished.events.on_groups'));
        $id .= ':' . $this->getState('filter.unpublished.venues');
        $id .= ':' . $this->getState('filter.unpublished.on_user');

        return parent::getStoreId($id);
    }

    /**
     * Build the query
     */
    protected function getListQuery()
    {
        $app         = Factory::getApplication();
        $task        = $app->input->getCmd('task', '');
        $itemid      = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);
        $params      = $app->getParams();
        $settings    = JemHelper::globalattribs();
        $jemsettings = JemHelper::config();
        $user        = JemFactory::getUser();
        $levels      = $user->getAuthorisedViewLevels();

        # Query
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        # Event
        $query->select(
            $this->getState('list.select',
                'a.access,a.alias,a.attribs,a.checked_out,a.checked_out_time,a.contactid,a.created,a.created_by,a.created_by_alias,a.custom1,a.custom2,a.custom3,a.custom4,a.custom5,a.custom6,a.custom7,a.custom8,a.custom9,a.custom10,a.dates,a.datimage,a.enddates,a.endtimes,a.featured,' .
                'a.fulltext,a.hits,a.id,a.introtext,a.language,a.locid,a.maxplaces,a.reservedplaces,a.minbookeduser,a.maxbookeduser,a.metadata,a.meta_keywords,a.meta_description,a.modified,a.modified_by,a.published,a.registra,a.times,a.title,a.unregistra,a.waitinglist,a.requestanswer,a.seriesbooking,a.singlebooking, DAYOFMONTH(a.dates) AS created_day, YEAR(a.dates) AS created_year, MONTH(a.dates) AS created_month,' .
                'a.recurrence_byday,a.recurrence_counter,a.recurrence_first_id,a.recurrence_limit,a.recurrence_limit_date,a.recurrence_number, a.recurrence_type,a.version'
            )
        );
        $query->from('#__jem_events as a');

        # Author
        $name = $settings->get('global_regname', '1') ? 'u.name' : 'u.username';
        $query->select($name.' AS author');
        $query->join('LEFT', '#__users AS u on u.id = a.created_by');

        # Venue
        $query->select(array('l.alias AS l_alias', 'l.color AS venuecolor', 'l.checked_out AS l_checked_out', 'l.checked_out_time AS l_checked_out_time', 'l.city', 'l.country', 'l.created AS l_created', 'l.created_by AS l_createdby'));
        $query->select(array('l.custom1 AS l_custom1', 'l.custom2 AS l_custom2', 'l.custom3 AS l_custom3', 'l.custom4 AS l_custom4', 'l.custom5 AS l_custom5', 'l.custom6 AS l_custom6', 'l.custom7 AS l_custom7', 'l.custom8 AS l_custom8', 'l.custom9 AS l_custom9', 'l.custom10 AS l_custom10'));
        $query->select(array('l.id AS l_id', 'l.latitude', 'l.locdescription', 'l.locimage', 'l.longitude', 'l.map', 'l.meta_description AS l_meta_description', 'l.meta_keywords AS l_meta_keywords', 'l.modified AS l_modified', 'l.modified_by AS l_modified_by', 'l.postalCode'));
        $query->select(array('l.publish_up AS l_publish_up', 'l.publish_down AS l_publish_down', 'l.published AS l_published', 'l.state', 'l.street', 'l.url', 'l.venue', 'l.version AS l_version'));
        $query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');

        # Country
        $query->select(array('ct.name AS countryname'));
        $query->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');

        # the rest
        $case_when_e = ' CASE WHEN ';
        $case_when_e .= $query->charLength('a.alias', '!=', '0');
        $case_when_e .= ' THEN ';
        $id_e = $query->castAs('CHAR', 'a.id');
        $case_when_e .= $query->concatenate(array($id_e, 'a.alias'), ':');
        $case_when_e .= ' ELSE ';
        $case_when_e .= $id_e . ' END as slug';

        $case_when_l = ' CASE WHEN ';
        $case_when_l .= $query->charLength('l.alias', '!=', '0');
        $case_when_l .= ' THEN ';
        $id_l = $query->castAs('CHAR', 'a.locid');
        $case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
        $case_when_l .= ' ELSE ';
        $case_when_l .= $id_l . ' END as venueslug';

        $case_when_a = ' CASE WHEN ';
        $case_when_a .= " a.access IN (" . implode(',', $levels) . ")";
        $case_when_a .= ' THEN 1 ';
        $case_when_a .= ' ELSE 0 ';
        $case_when_a .= ' END as user_has_access_event';

        $case_when_v = ' CASE WHEN ';
        $case_when_v .= " l.access IN (" . implode(',', $levels) . ")";
        $case_when_v .= ' THEN 1 ';
        $case_when_v .= ' ELSE 0 ';
        $case_when_v .= ' END as user_has_access_venue';

        $case_when_c  = ' CASE WHEN ';
        $case_when_c .= " c.access IN (" . implode(',',$levels) . ")";
        $case_when_c .= ' THEN 1 ';
        $case_when_c .= ' ELSE 0 ';
        $case_when_c .= ' END as user_has_access_category';

        $query->select(array($case_when_e, $case_when_l, $case_when_a, $case_when_v, $case_when_c));

        # join over the category-tables
        $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
        $query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

        #############
        ## FILTERS ##
        #############

        ###################
        ## FILTER - TASK ##
        ###################

        if (!$task) {
            $task = ($params->get('show_archived_events') ? 'archive' : '');
        }

        #####################
        ## FILTER - EVENTS ##
        #####################

        # Filter by a single or group of events.
        $eventId = $this->getState('filter.event_id');

        if (is_numeric($eventId)) {
            $type = $this->getState('filter.event_id.include', true) ? '= ' : '<> ';
            $query->where('a.id ' . $type . (int) $eventId);
        } elseif (is_array($eventId) && !empty($eventId)) {
            ArrayHelper::toInteger($eventId);
            $eventId = implode(',', $eventId);
            $type    = $this->getState('filter.event_id.include', true) ? 'IN' : 'NOT IN';
            $query->where('a.id ' . $type . ' (' . $eventId . ')');
        }

        ###################
        ## FILTER-ACCESS ##
        ###################

        # Filter by access level - public or with access_level_locked_events active.
        if ($jemsettings->access_level_locked_events != "[\"1\"]") {
            $accessLevels = json_decode($jemsettings->access_level_locked_events, true);
            $newlevels    = array_values(array_unique(array_merge($levels, $accessLevels ?? [])));
            $query->where('a.access IN (' . implode(',', $newlevels) . ')');
        } else {
            $query->where('a.access IN (' . implode(',', $levels) . ')');
        }

        ####################
        ## FILTER-PUBLISH ##
        ####################

        # Filter by published state.
        $where_pub    = $this->_getPublishWhere();   
        $currentDate  = (new Date('now', $app->get('offset')))->format($db->getDateFormat(), true);

        if (!empty($where_pub)) {
            if ($this->getState('filter.published') == 2) {
                $ispublished = implode(' OR ', $where_pub);
            } else {
                $ispublished = '(' . implode(' OR ', $where_pub) . ') AND a.publish_up <= ' . $db->quote($currentDate) . ' AND (a.publish_down > ' . $db->quote($currentDate) . ' OR a.publish_down IS null)';
            }
            $query->where($ispublished);
        } else {
            // something wrong - fallback to published events
            $query->where('a.published = 1');
        }

        #####################
        ## FILTER-FEATURED ##
        #####################

        # Filter by featured flag.
        $featured = $this->getState('filter.featured');

        if (is_numeric($featured)) {
            $query->where('a.featured = ' . (int) $featured);
        } elseif (is_array($featured) && !empty($featured)) {
            ArrayHelper::toInteger($featured);
            $featured = implode(',', $featured);
            $query->where('a.featured IN (' . $featured . ')');
        }

        #############################
        ## FILTER - OPEN_DATES     ##
        #############################
        $opendates = $this->getState('filter.opendates');

        switch ($opendates) {
            case 0: // don't show events without start date
            default:
                $opendates_query = " AND a.dates IS NOT NULL)";
                break;
            case 1: // show all events, with or without start date
                $opendates_query = " OR a.dates IS NULL)";
                break;
            case 2: // show only events without startdate
                $opendates_query = " a.dates IS NULL";
                break;
        }

        #############################
        ## FILTER - CALENDAR_DATES ##
        #############################
        $cal_month = $this->getState('filter.filter_month');
        $today     = new Date('now', $app->get('offset'));

        if($opendates != 2) {
            if ($cal_month) {
                // Apply Month filter
                $filter_date_from = $cal_month . '-01';
                $filter_date_to = date("Y-m-t", strtotime($filter_date_from));

                // Check if event ENDS after or on the start date
                $where_from = ' (DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $db->quote($filter_date_from) . ') >= 0 ' . $opendates_query;
                $query->where($where_from);
                $this->setState('filter.calendar_from', $where_from);

                // Check if event STARTS before or on the end date
                $where_to = ' (DATEDIFF(a.dates, ' . $db->quote($filter_date_to) . ') <= 0' . $opendates_query;
                $query->where($where_to);
                $this->setState('filter.calendar_to', $where_to);
            } else {
                // Apply menu date filters
                $filterDaysBefore = $params->get('tablefiltereventfrom', 0);
                $filterDaysAfter = $params->get('tablefiltereventuntil', 0);
                if (empty($task) || ($task == 'archive' && $filterDaysBefore > 0)) {
                    $dateFrom = (clone $today)->modify('-' . $filterDaysBefore . ' days')->format('Y-m-d');
                    $where_from = ' (DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $db->quote($dateFrom) . ') >= 0' . $opendates_query;
                    $query->where($where_from);
                    $this->setState('filter.calendar_from', $where_from);
                }
                if ($filterDaysAfter) {
                    $dateTo = (clone $today)->modify($filterDaysAfter . ' days')->format('Y-m-d');
                    $where_to = ' (DATEDIFF(a.dates, ' . $db->quote($dateTo) . ') <= 0' . $opendates_query;
                    $query->where($where_to);
                    $this->setState('filter.calendar_to', $where_to);
                }
            }
        } else {
            // Only open day events
            $query->where($opendates_query);
        }

        #####################
        ### FILTER - BYCAT ##
        #####################

        $filter_catid = $this->getState('filter.filter_catid');
        if ($filter_catid) { // categorycal
            $query->where('c.id = ' . (int) $filter_catid);
        } else {
            $cats = $this->getCategories('all');
            if (!empty($cats)) {
                $query->where('c.id  IN (' . implode(',', $cats) . ')');
            }
        }

        ####################
        ## FILTER - BYLOC ##
        ####################
        $filter_locid = $this->getState('filter.filter_locid');
        if ($filter_locid) {
            $query->where('a.locid = ' . (int) $filter_locid);
        }

        ####################
        ## FILTER - VENUE ##
        ####################

        $venueId = $this->getState('filter.venue_id');

        if (is_numeric($venueId)) {
            $type = $this->getState('filter.venue_id.include', true) ? '= ' : '<> ';
            $query->where('l.id ' . $type . (int) $venueId);
        } elseif (is_array($venueId) && !empty($venueId)) {
            ArrayHelper::toInteger($venueId);
            $venueId = implode(',', $venueId);
            $type    = $this->getState('filter.venue_id.include', true) ? 'IN' : 'NOT IN';
            $query->where('l.id ' . $type . ' (' . $venueId . ')');
        }

        ##########################
        ## FILTER - VENUE STATE ##
        ##########################

        $venueState = $this->getState('filter.venue_state');

        if (!empty($venueState)) {
            $venueState = explode(',', $venueState);

            $venueStateMode = $this->getState('filter.venue_state.mode', 0);
            switch ($venueStateMode) {
                case 0: # complete match: venue's state must be equal (ignoring upper/lower case) one of the strings given by filter
                default:
                    array_walk($venueState, function(&$v,$k,$db) { $v = $db->quote(trim($v)); }, $db);
                    $query->where('l.state IN ('.implode(',', $venueState).')');
                    break;
                case 1: # contain: venue's state must contain one of the strings given by filter
                    array_walk($venueState, function(&$v,$k,$db) { $v = quotemeta($db->escape(trim($v), true)); }, $db);
                    $query->where('l.state REGEXP '.$db->quote(implode('|', $venueState)));
                    break;
            }
        }

        ###################
        ## FILTER-SEARCH ##
        ###################

        # define variables
        $filter = $this->getState('filter.filter_type');
        $search = $this->getState('filter.filter_search'); // not escaped

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%'.$db->escape($search, true).'%', false); // escape once

                if ($search && $settings->get('global_show_filter')) {
                    switch ($filter) {
                        # case 4 is category, so it is omitted
                        case 1:
                            $query->where('a.title LIKE '.$search);
                            break;
                        case 2:
                            $query->where('l.venue LIKE '.$search);
                            break;
                        case 3:
                            $query->where('l.city LIKE '.$search);
                            break;
                        case 5:
                            $query->where('l.state LIKE '.$search);
                            break;
                    }
                }
            }
        }

        # Group
        $group = $this->getState('filter.groupby');
        if ($group) {
            $query->group($group);
        }

        # ordering
        $orderby = $this->getState('filter.orderby');
        if ($orderby) {
            $query->order($orderby);
        }

        return $query;
    }

    /**
     * Method to get a list of events.
     */
    public function getItems()
    {
        $items = parent::getItems();

        if (empty($items)) {
            return array();
        }

        $user = JemFactory::getUser();
        $calendarMultiday = $this->getState('filter.calendar_multiday');
        $stateParams = $this->getState('params');

        # Convert the parameter fields into objects.
        foreach ($items as $index => $item)
        {
            $eventParams = new Registry;
            $eventParams->loadString($item->attribs);

            if (empty($stateParams)) {
                $item->params = new Registry;
                $item->params->merge($eventParams);
            } else {
                $item->params = clone $stateParams;
                $item->params->merge($eventParams);
            }

            # adding categories
            $item->categories = $this->getCategories($item->id);

            # check if the item-categories is empty, if so the user has no access to that event at all.
            if (empty($item->categories)) {
                unset ($items[$index]);
                continue;
            } else {
                # write access permissions.
                $item->params->set('access-edit', $user->can('edit', 'event', $item->id, $item->created_by));
            }
        } // foreach

        if ($items) {
            /*$items =*/ JemHelper::getAttendeesNumbers($items);

            if ($calendarMultiday) {
                $items = self::calendarMultiday($items);
            }
        }

        return $items;
    }

    /**
     * Retrieve Categories
     *
     * Due to multi-cat this function is needed
     * filter-index (4) is pointing to the cats
     */
    public function getCategories($id)
    {
        $user     = JemFactory::getUser();
        $levels   = $user->getAuthorisedViewLevels();
        $settings = JemHelper::globalattribs();
        $jemsettings = JemHelper::config();

        // Query
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $case_when_c  = ' CASE WHEN ';
        $case_when_c .= $query->charLength('c.alias');
        $case_when_c .= ' THEN ';
        $id_c = $query->castAs('CHAR', 'c.id');
        $case_when_c .= $query->concatenate(array($id_c, 'c.alias'), ':');
        $case_when_c .= ' ELSE ';
        $case_when_c .= $id_c.' END as catslug';

        $case_when_a  = ' CASE WHEN ';
        $case_when_a .= " c.access IN (" . implode(',',$levels) . ")";
        $case_when_a .= ' THEN 1 ';
        $case_when_a .= ' ELSE 0 ';
        $case_when_a .= ' END as user_has_access_category';
        $query->select(array('DISTINCT c.id','c.catname','c.access','c.checked_out AS cchecked_out','c.color',$case_when_c,$case_when_a));
        $query->from('#__jem_categories as c');
        $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');

        $query->select(array('a.id AS multi'));
        $query->join('LEFT','#__jem_events AS a ON a.id = rel.itemid');

        if ($id != 'all'){
            $query->where('rel.itemid ='.(int)$id);
        }

        $query->where('c.published = 1');

        ###################
        ## FILTER-ACCESS ##
        ###################

        # Filter by access level - public or with access_level_locked_categories active.
        if($jemsettings->access_level_locked_categories != "[\"1\"]") {
            $accessLevels = json_decode($jemsettings->access_level_locked_categories, true);
            $newlevels = array_values(array_unique(array_merge($levels, $accessLevels ?? [])));
            $query->where('c.access IN ('.implode(',', $newlevels).')');
        } else {
            $query->where('c.access IN ('.implode(',', $levels).')');
        }

        ###################################
        ## FILTER - MAINTAINER/JEM GROUP ##
        ###################################

        # -as maintainter someone who is registered can see a category that has special rights-
        # -let's see if the user has access to this category.-
        # ==> No. On frontend everybody needs proper access levels to see things. No exceptions.

        //    $query3    = $db->getQuery(true);
        //    $query3 = 'SELECT gr.id'
        //            . ' FROM #__jem_groups AS gr'
        //            . ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
        //            . ' WHERE g.member = ' . (int) $user->get('id')
        //            //. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
        //            . ' AND g.member NOT LIKE 0';
        //    $db->setQuery($query3);
        //    $groupnumber = $db->loadColumn();

        //    $jemgroups = implode(',',$groupnumber);

        // JEM groups doesn't overrule view access levels!
        //    if ($jemgroups) {
        //        $query->where('(c.access IN ('.$groups.') OR c.groupid IN ('.$jemgroups.'))');
        //    } else {
        //    $query->where('(c.access IN ('.implode(',', $levels).'))');
        //    }

        #######################
        ## FILTER - CATEGORY ##
        #######################

        # set filter for top_category
        $top_cat = $this->getState('filter.category_top');

        if ($top_cat) {
            $query->where($top_cat);
        }

        # Filter by a single or group of categories.
        $categoryId = $this->getState('filter.category_id');

        if (is_numeric($categoryId)) {
            $type = $this->getState('filter.category_id.include', true) ? '= ' : '<> ';
            $query->where('c.id '.$type.(int) $categoryId);
        }
        elseif (is_array($categoryId) && !empty($categoryId)) {
            \Joomla\Utilities\ArrayHelper::toInteger($categoryId);
            $categoryId = implode(',', $categoryId);
            $type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
            $query->where('c.id '.$type.' ('.$categoryId.')');
        }

        # filter set by day-view
        $requestCategoryId = $this->getState('filter.req_catid');

        if ($requestCategoryId) {
            $query->where('c.id = '.(int)$requestCategoryId);
        }

        ###################
        ## FILTER-SEARCH ##
        ###################

        # define variables
        $filter = $this->getState('filter.filter_type');
        $search = $this->getState('filter.filter_search'); // not escaped

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('c.id = '.(int) substr($search, 3));
            } else {
                $search = $db->Quote('%'.$db->escape($search, true).'%', false); // escape once

                if ($search && $settings->get('global_show_filter')) {
                    if ($filter == 4) {
                        $query->where('c.catname LIKE '.$search);
                    }
                }
            }
        }

        $db->setQuery($query);

        if ($id == 'all') {
            $cats = $db->loadColumn(0);
            $cats = array_unique($cats);
            return ($cats);
        } else {
            $cats = $db->loadObjectList();
        }
        return $cats;
    }

    /**
     * create multi-day events
     */
    protected function calendarMultiday($items)
    {
        if (empty($items)) {
            return array();
        }

        $startdayonly = $this->getState('filter.calendar_startdayonly');

        if (!$startdayonly) {
            foreach ($items as $item)
            {
                if (!is_null($item->enddates) && ($item->enddates != $item->dates)) {
                    $day = $item->start_day;
                    $multi = array();

                    # it's multiday regardless if other days are on next month
                    $item->multi = 'first';
                    $item->multitimes = $item->times;
                    $item->multiname = $item->title;
                    $item->sort = 'zlast';

                    for ($counter = 0; $counter <= $item->datesdiff-1; $counter++)
                    {
                        # next day:
                        $day++;
                        $nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);

                        # ensure we only generate days of current month in this loop
                        if (date('m', $this->_date) == date('m', $nextday)) {
                            $multi[$counter] = clone $item;
                            $multi[$counter]->dates = date('Y-m-d', $nextday);

                            if ($multi[$counter]->dates < $item->enddates) {
                                $multi[$counter]->multi = 'middle';
                                $multi[$counter]->multistartdate = $item->dates;
                                $multi[$counter]->multienddate = $item->enddates;
                                $multi[$counter]->multitimes = $item->times;
                                $multi[$counter]->multiname = $item->title;
                                $multi[$counter]->times = $item->times;
                                $multi[$counter]->endtimes = $item->endtimes;
                                $multi[$counter]->sort = 'middle';
                            } elseif ($multi[$counter]->dates == $item->enddates) {
                                $multi[$counter]->multi = 'zlast';
                                $multi[$counter]->multistartdate = $item->dates;
                                $multi[$counter]->multienddate = $item->enddates;
                                $multi[$counter]->multitimes = $item->times;
                                $multi[$counter]->multiname = $item->title;
                                $multi[$counter]->sort = 'first';
                                $multi[$counter]->times = $item->times;
                                $multi[$counter]->endtimes = $item->endtimes;
                            }
                        }
                    } // for

                    # add generated days to data
                    $items = array_merge($items, $multi);
                    # unset temp array holding generated days before working on the next multiday event
                    unset($multi);
                }
            } // foreach
        }

        # Sort the items
        foreach ($items as $item) {
            $time[] = $item->times;
            $title[] = $item->title;
        }

        array_multisort($time, SORT_ASC, $title, SORT_ASC, $items);

        return $items;
    }

    /**
     * Helper method to auto-populate publishing related model state.
     * Can be called in populateState()
     */
    protected function _populatePublishState($task)
    {
        $app         = Factory::getApplication();
        $jemsettings = JemHelper::config();
        $user        = JemFactory::getUser();
        $userId      = $user->id ?? null;

        # publish state
        $format = $app->input->getCmd('format', '');

        if ($task == 'archive') {
            $this->setState('filter.published', 2);
        } elseif (($format == 'raw') || ($format == 'feed')) {
            $this->setState('filter.published', 1);
        } else {
            $show_unpublished = $user->can(array('edit', 'publish'), 'event', false, false, 1);
            if ($show_unpublished) {
                // global editor or publisher permission
                $publishedStates = $this->show_archived_events ? array(0, 1, 2) : array(0, 1);
                $this->setState('filter.published', $publishedStates);
            } else {
                // no global permission but maybe on event level
                $this->setState('filter.published', 1);
                $this->setState('filter.unpublished', 0);

                $jemgroups = $user->getJemGroups(array('editevent', 'publishevent'));
                if (($userId !== 0) && ($jemsettings->eventedit == -1)) {
                    $jemgroups[0] = true; // we need key 0 to get unpublished events not attached to any jem group
                }
                // user permitted on that jem groups
                if (is_array($jemgroups) && count($jemgroups)) {
                    $this->setState('filter.unpublished.events.on_groups', array_keys($jemgroups));
                }
                // user permitted on own events
                if (($userId !== 0) && ($user->authorise('core.edit.own', 'com_jem') || $jemsettings->eventowner)) {
                    $this->setState('filter.unpublished.on_user', $userId);
                }
            }
        }
    }

    /**
     * Helper method to create publishing related where clauses.
     * Can be called in getListQuery()
     *
     * @param  $tbl   table alias to use
     *
     * @return array  where clauses related to publishing state and user permissons
     *                to combine with OR
     */
    protected function _getPublishWhere($tbl = 'a')
    {
        $tbl = empty($tbl) ? '' : $this->_db->quoteName($tbl) . '.';
        $where_pub = array();

        # Filter by published state.
        $published = $this->getState('filter.published');
        $show_archived_events = $this->getState('filter.show_archived_events');

        if (is_numeric($published)) {
            $where_pub[] = '(' . $tbl . 'published ' . ($show_archived_events? '>=':'=') . (int)$published . ')';
        }
        elseif (is_array($published) && !empty($published)) {
            \Joomla\Utilities\ArrayHelper::toInteger($published);
            $published = implode(',', $published);
            $where_pub[] = '(' . $tbl . 'published IN (' . $published . '))';
        }

        # Filter by specific conditions
        $unpublished = $this->getState('filter.unpublished');
        if (is_numeric($unpublished))
        {
            // Is user member of jem groups allowing to see unpublished events?
            $unpublished_on_groups = $this->getState('filter.unpublished.events.on_groups');
            if (is_array($unpublished_on_groups) && !empty($unpublished_on_groups)) {
                // to allow only events with categories attached to allowed jemgroups use this line:
                //$where_pub[] = '(' . $tbl . '.published = ' . $unpublished . ' AND c.groupid IN (' . implode(',', $unpublished_on_groups) . '))';
                // to allow also events with categories not attached to disallowed jemgroups use this crazy block:
                $where_pub[] = '(' . $tbl . 'published = ' . $unpublished . ' AND '
                    . $tbl . 'id NOT IN (SELECT rel3.itemid FROM #__jem_categories as c3 '
                    . '                   INNER JOIN #__jem_cats_event_relations as rel3 '
                    . '                   WHERE c3.id = rel3.catid AND c3.groupid NOT IN (0,' . implode(',', $unpublished_on_groups) . ')'
                    . '                   GROUP BY rel3.itemid)'
                    . ')';
                // hint: above it's a not not ;-)
                //       meaning: Show unpublished events not connected to a category which is not one of the allowed categories.
            }

            // Is user allowed to see own unpublished events?
            $unpublished_on_user = (int)$this->getState('filter.unpublished.on_user');
            if ($unpublished_on_user > 0) {
                $where_pub[] = '(' . $tbl . 'published = ' . $unpublished . ' AND ' . $tbl . 'created_by = ' . $unpublished_on_user . ')';
            }
        }

        return $where_pub;
    }
}
?>
