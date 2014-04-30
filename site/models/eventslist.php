<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
jimport('joomla.application.component.modellist');
# libraries/joomla/application/component/modellist

/**
 * Eventslist model
 */
class JemModelEventslist extends JModelList
{
	/**
	 * Constructor.
	 * @param   array  $config  An optional associative array of configuration settings.
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
				'venue', 'l.venue','venue',
				'city', 'l.city', 'city',
				'c.catname', 'category_title',
				'state', 'a.state',
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
		
		// List state information
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
			$this->setState('filter.archived', 2);
		} else {
			$this->setState('filter.published', 1);
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
				
		# filter_order
		$orderCol = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		if (!in_array($orderCol, $this->filter_fields))
		{
			$orderCol = 'a.dates';
		}
		$this->setState('filter.filter_ordering', $orderCol);

		# filter_direction
		$listOrder = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
		if (!in_array(strtoupper($listOrder), array('ASC', 'DESC', '')))
		{
			if ($task == 'archive') {
				$listOrder = 'DESC';
			} else {
				$listOrder = 'ASC';
			}
		}
		$this->setState('filter.filter_direction', $listOrder);
		
		
		############################
		## EXCLUDE/INCLUDE EVENTS ##
		############################
		
		$eventswitch 		= $params->get('eventswitch', '');
		
		# set included events
		if ($eventswitch) {
		$included_events = trim($params->get('eventswitchevents', ''));
				if ($included_events) {
				$included_events = explode(",", $included_events);
				$this->setState('filter.event_id', $included_events);
				$this->setState('filter.event_id.include', true);
		
			}
		}
		
		# set excluded categories
		if (!$eventswitch) {
		$excluded_events = trim($params->get('eventswitchevents', ''));
		if ($excluded_events) {
		$excluded_events = explode(",", $excluded_events);
		$this->setState('filter.event_id', $excluded_events);
		$this->setState('filter.event_id.include', false);
		}
		}
		
		
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
		
		//parent::populateState('a.dates', 'ASC');
	}

	
	/**
	 * set limit
	 * used with ical output
	 */
	function setLimit($value)
	{
		$this->setState('limit',$value);
	}
	
	/**
	 * set limitstart
	 * used with ical output
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart',$value);
	}
	
	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 * 
	 * @return  string  A store id.
	 *
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . serialize($this->getState('filter.published'));
		$id .= ':' . $this->getState('filter.access');
		$id .= ':' . $this->getState('filter.opendate');		
		$id .= ':' . $this->getState('filter.featured');
		$id .= ':' . $this->getState('filter.event_id');
		$id .= ':' . $this->getState('filter.event_id.include');
		$id .= ':' . $this->getState('filter.category_id');
		$id .= ':' . $this->getState('filter.category_id.include');
		$id .= ':' . $this->getState('filter.filter_search');
		$id .= ':' . $this->getState('filter.filter_type');
		$id .= ':' . $this->getState('list.start');
		$id .= ':' . $this->getState('list.limit');
		$id .= ':' . $this->getState('filter.filter_ordering');
		$id .= ':' . $this->getState('filter.filter_direction');

		return parent::getStoreId($id);
	}

	/**
	 * Get the master query for retrieving a list of events to the model state.
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);
		$app	= JFactory::getApplication();
		$jinput	= JFactory::getApplication()->input;
		$task	= $jinput->get('task','','cmd');
		
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'a.id, a.title, a.alias,  a.introtext, a.fulltext,a.times,a.endtimes, ' .
				'a.checked_out, a.dates, a.enddates, a.checked_out_time, ' .
				'a.created, a.locid, a.created_by, a.created_by_alias, ' .
				'CASE WHEN a.modified = ' . $db->quote($db->getNullDate()) . ' THEN a.created ELSE a.modified END as modified, ' .
				'a.modified_by,' .
				'a.attribs, a.metadata, a.meta_keywords, a.meta_description, a.access, ' .
				'a.hits, a.featured,' . ' ' . $query->length('a.fulltext') . ' AS readmore'
			)
		);

		$query->from('#__jem_events AS a');
		
		// Join venues.
		$query->select('l.country, l.venue, l.city, l.state, l.url, l.street')
		->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');

		// Join country.
		$query->select('ct.name AS countryname')
		->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');
		
		// Join cat-relations.
		$query->select('rel.itemid');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		
		// Join categories.
		$query->select('c.catname AS category_title,c.id AS catid,c.access AS category_access');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias', '!=', '0');
		$case_when .= ' THEN ';
		$a_id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($a_id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $a_id.' END as slug';
		$query->select($case_when);
		
		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('l.alias', '!=', '0');
		$case_when .= ' THEN ';
		$a_id = $query->castAsChar('a.locid');
		$case_when .= $query->concatenate(array($a_id, 'l.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $a_id.' END as venueslug';
		$query->select($case_when);
		
		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('c.alias', '!=', '0');
		$case_when .= ' THEN ';
		$c_id = $query->castAsChar('c.id');
		$case_when .= $query->concatenate(array($c_id, 'c.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $c_id.' END as catslug';
		$query->select($case_when);
		
		#############
		## FILTERS ##
		#############
		
		#####################
		## FILTER - EVENTS ##
		#####################
		
		# Filter by a single or group of articles.
		$eventId = $this->getState('filter.event_id');
		
		if (is_numeric($eventId)) {
			$type = $this->getState('filter.event_id.include', true) ? '= ' : '<> ';
			$query->where('a.id '.$type.(int) $eventId);
		}
		elseif (is_array($eventId)) {
			JArrayHelper::toInteger($eventId);
			$eventId = implode(',', $eventId);			
			$type = $this->getState('filter.event_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('a.id '.$type.' ('.$eventId.')');
		}
		
		#######################
		## FILTER - CATEGORY ##
		#######################
		
		# Filter by a single or group of articles.
		$categoryId = $this->getState('filter.category_id');
		
		if (is_numeric($categoryId)) {
		$type = $this->getState('filter.category_id.include', true) ? '= ' : '<> ';
				$query->where('c.id '.$type.(int) $categoryId);
		}
		elseif (is_array($categoryId)) {
		JArrayHelper::toInteger($categoryId);
		$categoryId = implode(',', $categoryId);
		$type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
				$query->where('c.id '.$type.' ('.$categoryId.')');
		}
		
		###################
		## FILTER-ACCESS ##
		###################
		
		# Filter by access level.
		$access = $this->getState('filter.access');
		
		// if ($access){
			$user = JFactory::getUser();
			$groups = implode(',', $user->getAuthorisedViewLevels());
			$query->where('a.access IN (' . $groups . ')');
			$query->where('c.access IN (' . $groups . ')');
		// }
		
		
		####################
		## FILTER-PUBLISH ##
		####################
		
		# Filter by published state.
		$published	= $this->getState('filter.published');
		$archived	= $this->getState('filter.archived');
	
		if ($published) {
			$query->where('a.published = 1');
		}
		
		if ($archived) {
			$query->where('a.published = 2');
		}
		
		#####################
		## FILTER-OPENDATE ##
		#####################
		
		# Filter by opendate.
		$opendate	= $this->getState('filter.opendate');
		
		if ($opendate) {
				$query->where('a.dates NOT LIKE "0000:00:00"');
		}
		
		###################
		## FILTER-SEARCH ##
		###################
		
		# define variables
		$filter = $this->getState('filter.filter_type');
		$search = $this->getState('filter.filter_search');
	
		# define search-parameters
		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');
		
				if($search) {			
					switch($filter) {
						case 1:
							/* search venue or alias */
							$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.')');
							break;
						case 2:
							/* search venue */
							$query->where('l.venue LIKE '.$search);
							break;
						case 3:
							/* search city */
							$query->where('l.city LIKE '.$search);
							break;
						case 4:							
							# search category
							$query->where('c.title LIKE '.$search);
							break;
						case 5:
							# search state
							$query->where('l.state LIKE '.$search);
							break;
						case 6:
						default:
							/* search all */
							$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.' OR c.catname LIKE '.$search.' OR l.venue LIKE '.$search.' OR l.country LIKE '.$search.')');
					}
				}
			}
		}
		
		##################
		## FILTER-ORDER ##
		##################
		//$query->group('a.id');
		
		$filter_order		= $this->getState('filter.filter_ordering', '');
		$filter_direction	= $this->getState('filter.filter_direction', 'ASC');
		
		
		if ($filter_order == 'a.dates') {
			$orderby = array('a.dates ' . $filter_direction, 'a.times ' . $filter_direction);
		} else {
			$orderby = $filter_order . ' ' . $filter_direction;
		}
		
		$query->order($orderby);
			
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

		// Get the global params
		$globalParams = JComponentHelper::getParams('com_jem', true);

		// Convert the parameter fields into objects.
		foreach ($items as &$item)
		{
			$eventParams = new JRegistry;
			$eventParams->loadString($item->attribs);

			$item->params = clone $this->getState('params');
			$item->params->merge($eventParams);
		
			// access permissions.
			if (!$guest)
			{
				$asset = 'com_jem.event.' . $item->id;

				// Check general edit permission first.
				if ($user->authorise('core.edit', $asset))
				{
					$item->params->set('access-edit', true);
				}

				// Now check if edit.own is available.
				elseif (!empty($userId) && $user->authorise('core.edit.own', $asset))
				{
					// Check for a valid user and that they are the owner.
					if ($userId == $item->created_by)
					{
						$item->params->set('access-edit', true);
					}
				}
			}
			
			$item->categories = $this->getCategories($item->id);

			# retrieving filter-access
			$access = $this->getState('filter.access');

			if ($access)
			{
				// If the access filter has been set, we already have only the events this user can view.
				$item->params->set('access-view', true);
			}
			else
			{
				// If no access filter is set, the layout takes some responsibility for display of limited information.
				if ($item->catid == 0 || $item->category_access === null)
				{
					$item->params->set('access-view', in_array($item->access, $groups));
				}
				else
				{
					$item->params->set('access-view', in_array($item->access, $groups) && in_array($item->category_access, $groups));
				}
			}
		}
			
		return $items;
	}

	/**
	 * Method to get the starting number of items for the data set.
	 *
	 * @return  integer  The starting number of items available in the data set.
	 */
	public function getStart()
	{
		return $this->getState('list.start');
	}
	
	
	function getCategories($id)
	{
		// it's used due the multi-category feature
		
		$user = JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
	
		$where		= $this->_buildWhere2();
	
		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access IN (' . implode(',', $levels) . ')'
				. $where
				;
		$this->_db->setQuery($query);
		$this->_cats = $this->_db->loadObjectList();
		
		return $this->_cats;
	}
	
	protected function _buildWhere2()
	{
		// it's used due the multi-category feature
		$app = JFactory::getApplication();
	
		// Get the paramaters of the active menu item
		$params 	= $app->getParams();
		$catswitch = $params->get('categoryswitch', '0');
	
		// get included categories
		if ($catswitch == 1) {
			$included_cats = trim($params->get('categoryswitchcats', ''));
			if ($included_cats != '') {
				$cats_included = explode(',', $included_cats);
				$where = ' AND (c.id=' . implode(' OR c.id=', $cats_included) . ')';
			} else {		// === END Exlucded categories add === //
				$where = '';
			}
		}
	
		// get excluded categories
		if ($catswitch == 0) {
			$excluded_cats = trim($params->get('categoryswitchcats', ''));
	
			if ($excluded_cats != '') {
				$cats_excluded = explode(',', $excluded_cats);
				$where = ' AND (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
			} else {		// === END Exlucded categories add === //
				$where = '';
			}
		}
	
		return $where;
	}
}