<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\CMS\MVC\Model\ListModel;

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
					'venue', 'l.venue','venue_title',
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
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app         = Factory::getApplication();
		$jemsettings = JemHelper::config();
		$task        = $app->input->getCmd('task','');
		$format      = $app->input->getCmd('format',false);
		$itemid      = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);
		$params      = $app->getParams();

		# limit/start
		if (empty($format) || ($format == 'html')) {
			/* in J! 3.3.6 limitstart is removed from request - but we need it! */
			if ($app->input->get('limitstart', null, 'int') === null) {
				$app->setUserState('com_jem.eventslist.'.$itemid.'.limitstart', 0);
			}

			$limit       = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
			$this->setState('list.limit', $limit);
			$limitstart  = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
			// correct start value if required
			$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
			$this->setState('list.start', $limitstart);
		}

		# Search - variables
		$search      = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search); // must be escaped later

		$filtertype  = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
		$this->setState('filter.filter_type', $filtertype);

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

		$filter_order = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if ($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_reset = $app->input->getInt('filter_reset', 0);
		if ($filter_reset && $filter_order == 'a.dates') {
			$app->setUserState('com_jem.eventslist.'.$itemid.'.filter_order_Dir', $filter_order_DirDefault);
		}
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_order     = InputFilter::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = InputFilter::getInstance()->clean($filter_order_Dir, 'word');

		$default_order_Dir = ($task == 'archive') ? 'DESC' : 'ASC';

		if(!isset($_REQUEST["filter_type"])) {
			$tableInitialorderby = $params->get('tableorderby', '0');
			if ($tableInitialorderby) {
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
				}
				$filter_order = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_order', 'filter_order', $tableInitialorderby, 'cmd');
			}
			$tableInitialDirectionOrder = $params->get('tabledirectionorder', 'ASC');
			if ($tableInitialDirectionOrder) {
				$filter_order_Dir = $app->getUserStateFromRequest('com_jem.eventslist.' . $itemid . '.filter_order_Dir', 'filter_order_Dir', $tableInitialDirectionOrder, 'word');
			}
		}

		$orderby = array($filter_order . ' ' . $filter_order_Dir, 'a.dates ' . $default_order_Dir, 'a.times ' . $default_order_Dir, 'a.created ' . $default_order_Dir);

		$this->setState('filter.orderby',$orderby);

		################################
		## EXCLUDE/INCLUDE CATEGORIES ##
		################################

		$catswitch = $params->get('categoryswitch', '');
		$cats  = trim($params->get('categoryswitchcats', ''));
		$list_cats=[];
		if ($cats){
			$ids_cats = explode(",", $cats);
			if ($params->get('includesubcategories', 0))
			{
				//get subcategories
				foreach($ids_cats as $idcat)
				{
					if (!in_array($idcat, $list_cats))
					{
						$list_cats[] = $idcat;
						$child_cat   = $this->getListChildCat($idcat, 1);
						if ($child_cat !== false) {
							if(count($child_cat) > 0) {
								foreach ($child_cat as $child)
								{
									if (!in_array($child, $list_cats))
									{
										$list_cats[] = (string) $child;
									}
								}
							}
						}
					}
				}
			}else{
				$list_cats=$ids_cats;
			}

			if ($catswitch)
			{
				# set included categories
				$this->setState('filter.category_id', $list_cats);
				$this->setState('filter.category_id.include', true);
			}else{
				# set excluded categories
				$this->setState('filter.category_id', $list_cats);
				$this->setState('filter.category_id.include', false);
			}
		}
		$this->setState('filter.groupby',array('a.id'));

	}

	/**
	 * Method to get a all list of children categories (subtree) by $id category.
	 */
	public function getListChildCat($id, $reset){
		$user     = JemFactory::getUser();
		$levels   = $user->getAuthorisedViewLevels();
		$settings = JemHelper::globalattribs();

		static $catchildlist=[];
		if($reset){
			foreach ($catchildlist as $k => $c){
				unset($catchildlist[$k]);
			}
		}

		// Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query->select(array('DISTINCT c.id'));
		$query->from('#__jem_categories as c');
		$query->where('c.published = 1');
		$query->where('(c.access IN ('.implode(',', $levels).'))');
		$query->where('c.parent_id =' . (int) $id);

		$db->setQuery($query);
		$cats = $db->loadObjectList();

		if ($cats != null) {
			foreach ($cats as $cat){
				$catchildlist[] = $cat->id;
				$this->getListChildCat($cat->id,0);
			}
			return $catchildlist;
		}
		return false;
	}

	/**
	 * set limit
	 */
	public function setLimit($value)
	{
		$this->setState('list.limit', (int) $value);
	}

	/**
	 * set limitstart
	 */
	public function setLimitStart($value)
	{
		$this->setState('list.start', (int) $value);
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
		$app       = Factory::getApplication();
		$task      = $app->input->getCmd('task', '');
		$itemid    = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$params    = $app->getParams();
		$settings  = JemHelper::globalattribs();
		$user      = JemFactory::getUser();
		$levels    = $user->getAuthorisedViewLevels();

		# Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		# Event
		$query->select(
				$this->getState('list.select',
				'a.access,a.alias,a.attribs,a.checked_out,a.checked_out_time,a.contactid,a.created,a.created_by,a.created_by_alias,a.custom1,a.custom2,a.custom3,a.custom4,a.custom5,a.custom6,a.custom7,a.custom8,a.custom9,a.custom10,a.dates,a.datimage,a.enddates,a.endtimes,a.featured,' .
				'a.fulltext,a.hits,a.id,a.introtext,a.language,a.locid,a.maxplaces,a.reservedplaces,a.minbookeduser,a.maxbookeduser,a.metadata,a.meta_keywords,a.meta_description,a.modified,a.modified_by,a.published,a.registra,a.times,a.title,a.unregistra,a.waitinglist,a.requestanswer,DAYOFMONTH(a.dates) AS created_day, YEAR(a.dates) AS created_year, MONTH(a.dates) AS created_month,' .
				'a.recurrence_byday,a.recurrence_counter,a.recurrence_first_id,a.recurrence_limit,a.recurrence_limit_date,a.recurrence_number, a.recurrence_type,a.version'
			)
		);
		$query->from('#__jem_events as a');

		# Author
		$name = $settings->get('global_regname','1') ? 'u.name' : 'u.username';
		$query->select($name.' AS author');
		$query->join('LEFT', '#__users AS u on u.id = a.created_by');

		# Venue
		$query->select(array('l.alias AS l_alias','l.checked_out AS l_checked_out','l.checked_out_time AS l_checked_out_time','l.city','l.country','l.created AS l_created','l.created_by AS l_createdby'));
		$query->select(array('l.custom1 AS l_custom1','l.custom2 AS l_custom2','l.custom3 AS l_custom3','l.custom4 AS l_custom4','l.custom5 AS l_custom5','l.custom6 AS l_custom6','l.custom7 AS l_custom7','l.custom8 AS l_custom8','l.custom9 AS l_custom9','l.custom10 AS l_custom10'));
		$query->select(array('l.id AS l_id','l.latitude','l.locdescription','l.locimage','l.longitude','l.map','l.meta_description AS l_meta_description','l.meta_keywords AS l_meta_keywords','l.modified AS l_modified','l.modified_by AS l_modified_by','l.postalCode'));
		$query->select(array('l.publish_up AS l_publish_up','l.publish_down AS l_publish_down','l.published AS l_published','l.state','l.street','l.url','l.venue','l.version AS l_version'));
		$query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');

		# Country
		$query->select(array('ct.name AS countryname'));
		$query->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');

		# the rest
		$case_when_e  = ' CASE WHEN ';
		$case_when_e .= $query->charLength('a.alias','!=', '0');
		$case_when_e .= ' THEN ';
		$id_e = $query->castAsChar('a.id');
		$case_when_e .= $query->concatenate(array($id_e, 'a.alias'), ':');
		$case_when_e .= ' ELSE ';
		$case_when_e .= $id_e.' END as slug';

		$case_when_l  = ' CASE WHEN ';
		$case_when_l .= $query->charLength('l.alias', '!=', '0');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('a.locid');
		$case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as venueslug';

		$query->select(array($case_when_e, $case_when_l));

		# join over the category-tables
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		#############
		## FILTERS ##
		#############

		#####################
		## FILTER - EVENTS ##
		#####################

		# Filter by a single or group of events.
		$eventId = $this->getState('filter.event_id');

		if (is_numeric($eventId)) {
			$type = $this->getState('filter.event_id.include', true) ? '= ' : '<> ';
			$query->where('a.id '.$type.(int) $eventId);
		}
		elseif (is_array($eventId) && !empty($eventId)) {
			\Joomla\Utilities\ArrayHelper::toInteger($eventId);
			$eventId = implode(',', $eventId);
			$type = $this->getState('filter.event_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('a.id '.$type.' ('.$eventId.')');
		}

		###################
		## FILTER-ACCESS ##
		###################

		# Filter by access level - always.
		$query->where('a.access IN ('.implode(',', $levels).')');

		####################
		## FILTER-PUBLISH ##
		####################

		# Filter by published state.
		$where_pub = $this->_getPublishWhere();
		if (!empty($where_pub)) {
			$query->where('(' . implode(' OR ', $where_pub) . ')');
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
		}
		elseif (is_array($featured) && !empty($featured)) {
			\Joomla\Utilities\ArrayHelper::toInteger($featured);
			$featured = implode(',', $featured);
			$query->where('a.featured IN ('.$featured.')');
		}

		#############################
		## FILTER - CALENDAR_DATES ##
		#############################
		$cal_from = $this->getState('filter.calendar_from');
		$cal_to   = $this->getState('filter.calendar_to');

		if ($cal_from) {
			$query->where($cal_from);
		}

		if ($cal_to) {
			$query->where($cal_to);
		}

		#############################
		## FILTER - OPEN_DATES     ##
		#############################
		$opendates = $this->getState('filter.opendates');

		switch ($opendates) {
		case 0: // don't show events without start date
		default:
			$query->where('a.dates IS NOT NULL');
			break;
		case 1: // show all events, with or without start date
			break;
		case 2: // show only events without startdate
			$query->where('a.dates IS NULL');
			break;
		}

		#####################
		### FILTER - BYCAT ##
		#####################

		$filter_catid = $this->getState('filter.filter_catid');
		if ($filter_catid) { // categorycal
			$query->where('c.id = '.(int)$filter_catid);
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
			$query->where('a.locid = '.(int)$filter_locid);
		}

		####################
		## FILTER - VENUE ##
		####################

		$venueId = $this->getState('filter.venue_id');

		if (is_numeric($venueId)) {
			$type = $this->getState('filter.venue_id.include', true) ? '= ' : '<> ';
			$query->where('l.id '.$type.(int) $venueId);
		}
		elseif (is_array($venueId) && !empty($venueId)) {
			\Joomla\Utilities\ArrayHelper::toInteger($venueId);
			$venueId = implode(',', $venueId);
			$type = $this->getState('filter.venue_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('l.id '.$type.' ('.$venueId.')');
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
				$query->where('a.id = '.(int) substr($search, 3));
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

		// Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$case_when_c  = ' CASE WHEN ';
		$case_when_c .= $query->charLength('c.alias');
		$case_when_c .= ' THEN ';
		$id_c = $query->castAsChar('c.id');
		$case_when_c .= $query->concatenate(array($id_c, 'c.alias'), ':');
		$case_when_c .= ' ELSE ';
		$case_when_c .= $id_c.' END as catslug';

		$query->select(array('DISTINCT c.id','c.catname','c.access','c.checked_out AS cchecked_out','c.color',$case_when_c));
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

		# Filter by access level.

		###################################
		## FILTER - MAINTAINER/JEM GROUP ##
		###################################

		# -as maintainter someone who is registered can see a category that has special rights-
		# -let's see if the user has access to this category.-
		# ==> No. On frontend everybody needs proper access levels to see things. No exceptions.

	//	$query3	= $db->getQuery(true);
	//	$query3 = 'SELECT gr.id'
	//			. ' FROM #__jem_groups AS gr'
	//			. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
	//			. ' WHERE g.member = ' . (int) $user->get('id')
	//			//. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
	//			. ' AND g.member NOT LIKE 0';
	//	$db->setQuery($query3);
	//	$groupnumber = $db->loadColumn();

	//	$jemgroups = implode(',',$groupnumber);

	// JEM groups doesn't overrule view access levels!
	//	if ($jemgroups) {
	//		$query->where('(c.access IN ('.$groups.') OR c.groupid IN ('.$jemgroups.'))');
	//	} else {
			$query->where('(c.access IN ('.implode(',', $levels).'))');
	//	}

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
				$this->setState('filter.published', array(0, 1));
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
