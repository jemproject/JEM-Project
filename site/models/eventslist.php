<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Model-Eventslist
 **/
class JemModelEventslist extends JModelList
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

		$app				= JFactory::getApplication();
		$jemsettings		= JemHelper::config();
		$jinput             = JFactory::getApplication()->input;
		$task               = $jinput->get('task','','cmd');
		$itemid				= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);

		# limit/start
		$value	= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
		$this->setState('list.limit', $value);

		$value = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
		$this->setState('list.start', $value);

		# Search - variables
		$search = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search);

		$filtertype = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_type', 'filter_type', '', 'int');
		$this->setState('filter.filter_type', $filtertype);

		# publish state
		if ($task == 'archive') {
			$this->setState('filter.published',2);
		} else {
			$this->setState('filter.published',1);
		}

		$params = $app->getParams();
		$this->setState('params', $params);

		$user = JFactory::getUser();

		if ($params->get('showopendates') == 1) {
			$this->setState('filter.opendate',1);
		}

		###########
		## ORDER ##
		###########

		$filter_order		= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order == 'a.dates') {
			$orderby = array('a.dates '.$filter_order_Dir,'a.times '.$filter_order_Dir);
		} else {
			$orderby = $filter_order . ' ' . $filter_order_Dir;
		}

		$this->setState('filter.orderby',$orderby);

		################################
		## EXCLUDE/INCLUDE CATEGORIES ##
		################################

		$catswitch 		= $params->get('categoryswitch', '');

		# set included categories
		if ($catswitch) {
		$included_cats = trim($params->get('categoryswitchcats', ''));
		if ($included_cats) {
			$included_cats = explode(",", $included_cats);
			$this->setState('filter.category_id', $included_cats);
			$this->setState('filter.category_id.include', true);

		}
		}

		# set excluded categories
		if (!$catswitch) {
		$excluded_cats = trim($params->get('categoryswitchcats', ''));
				if ($excluded_cats) {
				$excluded_cats = explode(",", $excluded_cats);
				$this->setState('filter.category_id', $excluded_cats);
				$this->setState('filter.category_id.include', false);
		}
		}

		$this->setState('filter.access', true);
		$this->setState('filter.groupby',array('a.id'));

		//parent::populateState('a.dates', 'ASC');
	}

	/**
	 * set limit
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}


	/**
	 * Method to get a store id based on model configuration state.
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . serialize($this->getState('filter.published'));
		$id .= ':' . $this->getState('filter.access');
		$id .= ':' . $this->getState('filter.opendate');
		$id .= ':' . $this->getState('filter.featured');
		$id .= ':' . serialize($this->getState('filter.event_id'));
		$id .= ':' . $this->getState('filter.event_id.include');
		$id .= ':' . serialize($this->getState('filter.category_id'));
		$id .= ':' . $this->getState('filter.category_id.include');
		$id .= ':' . $this->getState('filter.filter_search');
		$id .= ':' . $this->getState('filter.filter_type');
		$id .= ':' . $this->getState('list.start');
		$id .= ':' . $this->getState('list.limit');
		$id .= ':' . serialize($this->getState('filter.groupby'));
		$id .= ':' . serialize($this->getState('filter.orderby'));
		$id .= ':' . $this->getState('filter.category_top');
		$id .= ':' . $this->getState('filter.calendar_multiday');
		$id .= ':' . $this->getState('filter.calendar_startdayonly');
		$id .= ':' . $this->getState('filter.req_venid');
		$id .= ':' . $this->getState('filter.req_catid');

		return parent::getStoreId($id);
	}


	/**
	 * Build the query
	 */
	protected function getListQuery()
	{
		$app 			= JFactory::getApplication();
		$jinput 		= JFactory::getApplication()->input;
		$task 			= $jinput->get('task','','cmd');
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);

		$params 		= $app->getParams();
		$settings 		= JemHelper::globalattribs();
		$user 			= JFactory::getUser();

		# Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);

		$case_when_e = ' CASE WHEN ';
		$case_when_e .= $query->charLength('a.alias','!=', '0');
		$case_when_e .= ' THEN ';
		$id_e = $query->castAsChar('a.id');
		$case_when_e .= $query->concatenate(array($id_e, 'a.alias'), ':');
		$case_when_e .= ' ELSE ';
		$case_when_e .= $id_e.' END as slug';

		$case_when_l = ' CASE WHEN ';
		$case_when_l .= $query->charLength('l.alias','!=', '0');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('a.locid');
		$case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as venueslug';

		# event
		$query->select(
				$this->getState(
				'list.select',
				'a.access,a.alias,a.attribs,a.author_ip,a.checked_out,a.checked_out_time,a.contactid,a.created,a.created_by,a.created_by_alias,a.custom1,a.custom2,a.custom3,a.custom4,a.custom5,a.custom6,a.custom7,a.custom8,a.custom9,a.custom10,a.dates,a.datimage,a.enddates,a.endtimes,a.featured,' .
				'a.fulltext,a.hits,a.id,a.introtext,a.language,a.locid,a.maxplaces,a.metadata,a.meta_keywords,a.meta_description,a.modified,a.modified_by,a.published,a.registra,a.times,a.title,a.unregistra,a.waitinglist,DAYOFMONTH(a.dates) AS created_day, YEAR(a.dates) AS created_year, MONTH(a.dates) AS created_month,' .
				'a.recurrence_byday,a.recurrence_counter,a.recurrence_first_id,a.recurrence_limit,a.recurrence_limit_date,a.recurrence_number, a.recurrence_type,a.version'
			)
		);
		$query->from('#__jem_events as a');

		# venue
		$query->select(array('l.alias AS l_alias','l.author_ip AS l_authorip','l.checked_out AS l_checked_out','l.checked_out_time AS l_checked_out_time','l.city','l.country','l.created AS l_created','l.created_by AS l_createdby'));
		$query->select(array('l.custom1 AS l_custom1','l.custom2 AS l_custom2','l.custom3 AS l_custom3','l.custom4 AS l_custom4','l.custom5 AS l_custom5','l.custom6 AS l_custom6','l.custom7 AS l_custom7','l.custom8 AS l_custom8','l.custom9 AS l_custom9','l.custom10 AS l_custom10'));
		$query->select(array('l.id AS l_id','l.latitude','l.locdescription','l.locimage','l.longitude','l.map','l.meta_description','l.meta_keywords','l.modified AS l_modified','l.modified_by AS l_modified_by','l.ordering','l.postalCode','l.publish_up','l.publish_down','l.published AS l_published','l.state','l.street','l.url','l.venue','l.version AS l_version'));
		$query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');

		# country
		$query->select(array('ct.name AS countryname'));
		$query->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');

		# the rest
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
			JArrayHelper::toInteger($eventId);
			$eventId = implode(',', $eventId);
			$type = $this->getState('filter.event_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('a.id '.$type.' ('.$eventId.')');
		}

		###################
		## FILTER-ACCESS ##
		###################

		# Filter by access level.
		$access = $this->getState('filter.access');

		if ($access){
			$user = JFactory::getUser();
			$groups = implode(',', $user->getAuthorisedViewLevels());
			$query->where('a.access IN ('.$groups.')');
		}

		####################
		## FILTER-PUBLISH ##
		####################

		# Filter by published state.
		$published = $this->getState('filter.published');

		if (is_numeric($published)) {
			$query->where('a.published = ' . (int) $published);
		}
		elseif (is_array($published) && !empty($published)) {
			JArrayHelper::toInteger($published);
			$published = implode(',', $published);
			$query->where('a.published IN ('.$published.')');
		}
		
		
		
		####################
		## FILTER-FEATURED ##
		####################
		
		# Filter by published state.
		$featured = $this->getState('filter.featured');
		
		if (is_numeric($featured)) {
			$query->where('a.featured = ' . (int) $featured);
		}
		elseif (is_array($featured) && !empty($featured)) {
			JArrayHelper::toInteger($featured);
			$featured = implode(',', $featured);
			$query->where('a.featured IN ('.$featured.')');
		}
		

		#############################
		## FILTER - CALENDAR_DATES ##
		#############################
		$cal_from	= $this->setState('filter.calendar_from');
		$cal_to		= $this->setState('filter.calendar_to');

		if ($cal_from) {
			$query->where($cal_from);
		}

		if ($cal_to) {
			$query->where($cal_to);
		}

		#####################
		### FILTER - BYCAT ##
		#####################

		$cats = $this->getCategories('all');
		if (!empty($cats)) {
			$query->where('c.id  IN (' . implode(',', $cats) . ')');
		}

		####################
		## FILTER - BYLOC ##
		####################
		$filter_locid = $this->getState('filter.filter_locid');
		if ($filter_locid) {
			$query->where('a.locid = '.$filter_locid);
		}


		###################
		## FILTER-SEARCH ##
		###################

		# define variables
		$filter = $this->getState('filter.filter_type');
		$search = $this->getState('filter.filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				if($search && $settings->get('global_show_filter')) {
					switch($filter) {
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
		$items	= parent::getItems();

		$user	= JFactory::getUser();
		$userId	= $user->get('id');
		$guest	= $user->get('guest');
		$groups = $user->getAuthorisedViewLevels();
		$input	= JFactory::getApplication()->input;

		$calendarMultiday = $this->getState('filter.calendar_multiday');

		# Get the global params
		$globalParams = JComponentHelper::getParams('com_jem', true);

		# Convert the parameter fields into objects.
		foreach ($items as $index => $item)
		{
			$eventParams = new JRegistry;
			$eventParams->loadString($item->attribs);

			$item->params = clone $this->getState('params');
			$item->params->merge($eventParams);

			# access permissions.
			if (!$guest)
			{
				$asset = 'com_jem.event.' . $item->id;

				# Check general edit permission first.
				if ($user->authorise('core.edit', $asset))
				{
					$item->params->set('access-edit', true);
				}

				# Now check if edit.own is available.
				elseif (!empty($userId) && $user->authorise('core.edit.own', $asset))
				{
					# Check for a valid user and that they are the owner.
					if ($userId == $item->created_by)
					{
						$item->params->set('access-edit', true);
					}
				}
			}

			# adding categories
			$item->categories = $this->getCategories($item->id);

			# retrieving filter-access
			$access = $this->getState('filter.access');

			if ($access)
			{
				// If the access filter has been set, we already have only the events this user can view.
				$item->params->set('access-view', true);
			}


			# check if the item-categories is empty, if so the user has no access to that event at all.
			if (empty($item->categories)) {
				unset ($items[$index]);
			}
		}

		if ($items) {
			$items = JemHelper::getAttendeesNumbers($items);

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

	function getCategories($id)
	{
		$user 			= JFactory::getUser();
		$userid			= (int) $user->get('id');
		$levels 		= $user->getAuthorisedViewLevels();
		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();
		$catswitch 		= $params->get('categoryswitch', '0');
		$settings 		= JemHelper::globalattribs();

		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);

		$case_when_c = ' CASE WHEN ';
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
		$access = $this->getState('filter.access');


		###################################
		## FILTER - MAINTAINER/JEM GROUP ##
		###################################

		# as maintainter someone who is registered can see a category that has special rights
		# let's see if the user has access to this category.


		$query3	= $db->getQuery(true);
		$query3 = 'SELECT gr.id'
				. ' FROM #__jem_groups AS gr'
				. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
				. ' WHERE g.member = ' . (int) $user->get('id')
				//. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
				. ' AND g.member NOT LIKE 0';
		$db->setQuery($query3);
		$groupnumber = $db->loadColumn();

		if ($access){
			$groups = implode(',', $user->getAuthorisedViewLevels());
			$jemgroups = implode(',',$groupnumber);

			if ($jemgroups) {
				$query->where('(c.access IN ('.$groups.') OR c.groupid IN ('.$jemgroups.'))');
			} else {
				$query->where('(c.access IN ('.$groups.'))');
			}
		}


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
			JArrayHelper::toInteger($categoryId);
			$categoryId = implode(',', $categoryId);
			$type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('c.id '.$type.' ('.$categoryId.')');
		}

		# filter set by day-view
		$requestCategoryId = $this->getState('filter.req_catid');

		if ($requestCategoryId) {
			$query->where('c.id = '.$requestCategoryId);
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
			JArrayHelper::toInteger($venueId);
			$venueId = implode(',', $venueId);
			$type = $this->getState('filter.venue_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('l.id '.$type.' ('.$venueId.')');
		}



		###################
		## FILTER-SEARCH ##
		###################

		# define variables
		$filter = $this->getState('filter.filter_type');
		$search = $this->getState('filter.filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('c.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				if($search && $settings->get('global_show_filter')) {
					if ($filter == 4) {
							$query->where('c.catname LIKE '.$search);
					}
				}
			}
		}

		$db->setQuery($query);

		if ($id == 'all'){
			$cats = $db->loadColumn(0);
			$cats = array_unique($cats);
			return ($cats);
		} else {
			$cats = $db->loadObjectList();
		}
		return $cats;
	}

	function calendarMultiday($items) {

		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();
		$startdayonly	= $this->getState('filter.calendar_startdayonly');

		foreach($items AS $item) {
			if (!is_null($item->enddates) && $startdayonly) {
				if ($item->enddates != $item->dates) {
					$day = $item->start_day;

					for ($counter = 0; $counter <= $item->datesdiff-1; $counter++) {
						$day++;

						# next day:
						$nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);

						# ensure we only generate days of current month in this loop
						if (strftime('%m', $this->_date) == strftime('%m', $nextday)) {
							$multi[$counter] = clone $item;
							$multi[$counter]->dates = strftime('%Y-%m-%d', $nextday);

							$item->multi = 'first';
							$item->multitimes = $item->times;
							$item->multiname = $item->title;
							$item->sort = 'zlast';

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
							# add generated days to data
							$items = array_merge($items, $multi);
						}
						# unset temp array holding generated days before working on the next multiday event
						unset($multi);
					}
				}
			}
		}

		foreach ($items as $item) {
			$time[] = $item->times;
			$title[] = $item->title;
		}

		array_multisort($time, SORT_ASC, $title, SORT_ASC, $items);
		
		return $items;
	}
}
?>