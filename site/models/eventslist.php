<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Model-Eventslist
 **/
class JemModelEventslist extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app 			= JFactory::getApplication();
		$jemsettings 	= JEMHelper::config();
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);

		//get the number of events from database
		$limit		= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * set limit
	 * @param int value
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}

	/**
	 * Method to get the Events
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query 			= $this->_buildQuery();
			$pagination		= $this->getPagination();
			$this->_data	= $this->_getList($query, $pagination->limitstart, $pagination->limit);
		}

		if($this->_data)
		{
			# attendee
			$this->_data = JemHelper::getAttendeesNumbers($this->_data);
			
			# category
			$count = count($this->_data);
			for($i = 0; $i < $count; $i++)
			{
				$item = $this->_data[$i];
				$item->categories = $this->getCategories($item->id);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_data[$i]);
				}
			}
		}

		return $this->_data;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		$jemsettings 		= JemHelper::config();
		$app 				= JFactory::getApplication();
		$itemid 			= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);

		//get the number of events from database
		$limit				= $this->getState('limit');
		$limitstart 		= $this->getState('limitstart');
		
		$query 				= $this->_buildQuery();
		$total				= $this->_getListCount($query);
		
		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);
		
		return $pagination;	
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQuery()
	{
		$app 			= JFactory::getApplication();
		$jinput 		= JFactory::getApplication()->input;
		$task 			= $jinput->get('task','','cmd');
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		
		$params 		= $app->getParams();
		$settings 		= JemHelper::globalattribs();
		$user 			= JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels 		= $user->getAuthorisedViewLevels();
		$catswitch 		= $params->get('categoryswitch', '0');
		
		// Userstate variables
		$filter 		= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter', 'filter', '', 'int');
		$search 		= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$search 		= $this->_db->escape(trim(JString::strtolower($search)));
		
		$filter_order		= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');
			
		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);
		
		$case_when_e = ' CASE WHEN ';
		$case_when_e .= $query->charLength('a.alias');
		$case_when_e .= ' THEN ';
		$id_e = $query->castAsChar('a.id');
		$case_when_e .= $query->concatenate(array($id_e, 'a.alias'), ':');
		$case_when_e .= ' ELSE ';
		$case_when_e .= $id_e.' END as slug';
		
		$case_when_l = ' CASE WHEN ';
		$case_when_l .= $query->charLength('l.alias');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('a.locid');
		$case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as venueslug';
		
		# event
		$query->select(array('a.access','a.alias','a.author_ip','a.checked_out','a.checked_out_time','a.contactid','a.created','a.created_by','a.created_by_alias','a.custom1','a.custom2','a.custom3','a.custom4','a.custom5','a.custom6','a.custom7','a.custom8','a.custom9','a.custom10','a.dates','a.datimage','a.enddates','a.endtimes','a.featured'));
		$query->select(array('a.fulltext','a.hits','a.id','a.introtext','a.language','a.locid','a.maxplaces','a.metadata','a.meta_keywords','a.meta_description','a.modified','a.modified_by','a.published','a.registra','a.times','a.title','a.unregistra','a.waitinglist'));
		$query->select(array('a.recurrence_byday','a.recurrence_counter','a.recurrence_first_id','a.recurrence_limit','a.recurrence_limit_date','a.recurrence_number', 'a.recurrence_type','a.version'));
		# venue
		$query->select(array('l.alias AS l_alias','l.author_ip AS l_authorip','l.checked_out AS l_checked_out','l.checked_out_time AS l_checked_out_time','l.city','l.country','l.created AS l_created','l.created_by AS l_createdby'));
		$query->select(array('l.custom1 AS l_custom1','l.custom2 AS l_custom2','l.custom3 AS l_custom3','l.custom4 AS l_custom4','l.custom5 AS l_custom5','l.custom6 AS l_custom6','l.custom7 AS l_custom7','l.custom8 AS l_custom8','l.custom9 AS l_custom9','l.custom10 AS l_custom10'));
		$query->select(array('l.id AS l_id','l.latitude','l.locdescription','l.locimage','l.longitude','l.map','l.meta_description','l.meta_keywords','l.modified AS l_modified','l.modified_by AS l_modified_by','l.ordering','l.postalCode','l.publish_up','l.publish_down','l.published AS l_published','l.state','l.street','l.url','l.venue','l.version AS l_version'));
		# country
		$query->select(array('ct.name AS countryname'));
		# category
		$query->select(array('c.access AS c_access','c.alias AS c_alias','c.catname','c.color','c.description AS c_description','c.id AS catid','c.image','c.published AS c_published'));
		# the rest
		$query->select(array($case_when_e, $case_when_l));
		$query->from('#__jem_events as a');
		$query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
		$query->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');
				
		// where
		$where = array();
		
		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' a.published = 1';
		}
		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';
		
		// get included categories
		if ($catswitch == 1) {
			$included_cats = trim($params->get('categoryswitchcats', ''));
		
			if ($included_cats != '') {
				$cats_included = explode(',', $included_cats);
				$where [] = '  (c.id=' . implode(' OR c.id=', $cats_included) . ')';
			}
		}
		
		// get excluded categories
		if ($catswitch == 0) {
			$excluded_cats = trim($params->get('categoryswitchcats', ''));
		
			if ($excluded_cats != '') {
				$cats_excluded = explode(',', $excluded_cats);
				$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
			}
		}
		// === END Excluded categories add === //
		
		if ($settings->get('global_show_filter') && $search) {
			switch($filter) {
				case 1:
					$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
					break;
				case 2:
					$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
					break;
				case 3:
					$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
					break;
				case 4:
					$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
					break;
				case 5:
				default:
					$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
			}
		}
		$query->where($where);
		
		// Group
		$query->group('a.id');
		
		// ordering
		if ($filter_order == 'a.dates') {
			$orderby = array('a.dates '.$filter_order_Dir,'a.times '.$filter_order_Dir);
		} else {
			$orderby = $filter_order . ' ' . $filter_order_Dir;
		}
		$query->order($orderby);
				
		return $query;
	}


	/**
	 * Retrieve Categories
	 * 
	 * Due to multi-cat this function is needed
	 */

	function getCategories($id)
	{
		$user 			= JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels 		= $user->getAuthorisedViewLevels();
		$app 			= JFactory::getApplication();
		
		// Get the paramaters of the active menu item
		$params 		= $app->getParams();
		$catswitch 		= $params->get('categoryswitch', '0');
		
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
		
		$query->select(array('DISTINCT c.id','c.catname','c.access','c.checked_out AS cchecked_out',$case_when_c));
		$query->from('#__jem_categories as c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
		
		// where
		$where = array();
		
		$where[] = 'rel.itemid ='.(int)$id;
		$where[] = 'c.published = 1';
		$where[] = 'c.access IN ('.implode(',',$levels).')';
		
		// get included categories
		if ($catswitch == 1) {
			$included_cats = trim($params->get('categoryswitchcats', ''));
			if ($included_cats != '') {
				$cats_included = explode(',', $included_cats);
				$where[] = ' (c.id=' . implode(' OR c.id=', $cats_included) . ')';
			}
		}
		
		// get excluded categories
		if ($catswitch == 0) {
			$excluded_cats = trim($params->get('categoryswitchcats', ''));
		
			if ($excluded_cats != '') {
				$cats_excluded = explode(',', $excluded_cats);
				$where[] = ' (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
			}
		}
		
		$query->where($where);
		
		$db->setQuery($query);
		$cats = $db->loadObjectList();

		return $cats;
	}
}
?>