<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * JEM Component search Model
 *
 * @package JEM
 *
 */
class JemModelSearch extends BaseDatabaseModel
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	protected $_data = null;

	/**
	 * Events total count
	 *
	 * @var integer
	 */
	protected $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	protected $_pagination = null;

	/**
	 * the query
	 *
	 * @var string
	 */
	protected $_query = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app = Factory::getApplication();
		$jemsettings = JemHelper::config();

		//get the number of events from database
		$limit      = $app->getUserStateFromRequest('com_jem.search.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->input->getInt('limitstart', 0);
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Get the filter request variables
		$filter_order = $app->input->getCmd('filter_order', 'a.dates');
		$this->setState('filter_order', $filter_order);

		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		$task = $app->input->getCmd('task', '');
		if (($task == 'archive') && ($filter_order == 'a.dates')) {
			$filter_order_DirDefault = 'DESC';
		}
		$this->setState('filter_order_Dir', $app->input->getCmd('filter_order_Dir', $filter_order_DirDefault));
	}

	/**
	 * Method to get the Events
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		$pop = Factory::getApplication()->input->getBool('pop', false);

		// Lets load the content if it doesn't already exist
		if (empty($this->_data)) {
			$query = $this->_buildQuery();

			if ($pop) {
				$this->_data = $this->_getList($query);
			} else {
				$pagination = $this->getPagination();
				$this->_data = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}

			foreach ($this->_data as $i => $item) {
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
	public function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			$this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQuery()
	{
		if (empty($this->_query)) {
			# Get the WHERE and ORDER BY clauses for the query
			$where   = $this->_buildWhere();
			$orderby = $this->_buildOrderBy();

			# Get Events from Database
			$this->_query = 'SELECT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.created_by, a.created_by_alias, a.locid, a.published, a.access,'
			              . ' a.recurrence_type, a.recurrence_first_id, a.recurrence_byday, a.recurrence_counter, a.recurrence_limit, a.recurrence_limit_date, a.recurrence_number,'
			              . ' a.alias, a.attribs, a.checked_out ,a.checked_out_time, a.contactid, a.datimage, a.featured, a.hits, a.language, a.version,'
			              . ' a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10,'
			              . ' a.introtext, a.fulltext, a.registra, a.unregistra, a.maxplaces, a.waitinglist, a.metadata, a.meta_keywords, a.meta_description, a.modified, a.modified_by,'
			              . ' l.id AS l_id, l.venue, l.street, l.postalCode, l.city, l.state, l.country, l.url, l.published AS l_published,'
			              . ' l.alias AS l_alias, l.checked_out AS l_checked_out, l.checked_out_time AS l_checked_out_time, l.created AS l_created, l.created_by AS l_createdby,'
			              . ' l.custom1 AS l_custom1, l.custom2 AS l_custom2, l.custom3 AS l_custom3, l.custom4 AS l_custom4, l.custom5 AS l_custom5, l.custom6 AS l_custom6, l.custom7 AS l_custom7, l.custom8 AS l_custom8, l.custom9 AS l_custom9, l.custom10 AS l_custom10,'
			              . ' l.locdescription, l.locimage, l.latitude, l.longitude, l.map, l.meta_description AS l_meta_description, l.meta_keywords AS l_meta_keywords, l.modified AS l_modified, l.modified_by AS l_modified_by,'
			              . ' l.publish_up AS l_publish_up, l.publish_down AS l_publish_down, l.version AS l_version,'
			              . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
			              . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
			              . ' FROM #__jem_events AS a'
			              . ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
			              . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
			              . ' LEFT JOIN #__jem_countries AS c ON c.iso2 = l.country'
			              . $where
			              . ' GROUP BY a.id '
			              . $orderby
			              ;
		}

		return $this->_query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildOrderBy()
	{
		$app  = Factory::getApplication();
		$task = $app->input->getCmd('task', '');

		$filter_order      = $this->getState('filter_order');
		$filter_order_Dir  = $this->getState('filter_order_Dir');
		$default_order_Dir = ($task == 'archive') ? 'DESC' : 'ASC';

		$filter_order      = InputFilter::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir  = InputFilter::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order == 'a.dates') {
			$orderby = ' ORDER BY a.dates ' . $filter_order_Dir .', a.times ' . $filter_order_Dir
			         . ', a.created ' . $filter_order_Dir;
		} else {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir
			         . ', a.dates ' . $default_order_Dir . ', a.times ' . $default_order_Dir
			         . ', a.created ' . $default_order_Dir;
		}

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildWhere()
	{
		$app = Factory::getApplication();

		// Get the paramaters of the active menu item
		$params       = $app->getParams();
		$task         = $app->input->getCmd('task', '');
		$user         = JemFactory::getUser();
		$levels       = $user->getAuthorisedViewLevels();
		$top_category = $params->get('top_category', 1);

		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where = ' WHERE a.published = 2';
		} else {
			$where = ' WHERE a.published = 1';
		}

		// filter by user's access levels
		$where .= ' AND a.access IN (' . implode(', ', $levels) .')';

		//$filter            = $app->input->getString('filter', '');
		$filter            = $app->getUserStateFromRequest('com_jem.search.filter_search', 'filter_search', '', 'string');
		$filter_type       = $app->input->get('filter_type', '');
		$filter_continent  = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');
		$filter_country    = $app->getUserStateFromRequest('com_jem.search.filter_country', 'filter_country', '', 'string');
		$filter_city       = $app->getUserStateFromRequest('com_jem.search.filter_city', 'filter_city', '', 'string');
		$filter_date_from  = $app->getUserStateFromRequest('com_jem.search.filter_date_from', 'filter_date_from', '', 'string');
		$filter_date_to    = $app->getUserStateFromRequest('com_jem.search.filter_date_to', 'filter_date_to', '', 'string');
		$filter_category   = $app->getUserStateFromRequest('com_jem.search.filter_category', 'filter_category', 0, 'int');
		// "Please select..." entry has number 1 which must be interpreted as "not set" and replaced by top category (which maybe 1 ;-)
		$filter_category   = (($filter_category > 1) ? $filter_category : $top_category);

		// no result if no filter:
		if (!($filter || $filter_continent || $filter_country || $filter_city || $filter_date_from || $filter_date_to || $filter_category != $top_category)) {
			return ' WHERE 0 ';
		}

		if ($filter) {
			// clean filter variables
			$filter      = \Joomla\String\StringHelper::strtolower($filter);
			$filter      = $this->_db->Quote('%'.$this->_db->escape($filter, true).'%', false);
			$filter_type = \Joomla\String\StringHelper::strtolower($filter_type);

			switch ($filter_type) {
				case 'title' :
					$where .= ' AND LOWER(a.title) LIKE '.$filter;
					break;
				case 'venue' :
					$where .= ' AND LOWER(l.venue) LIKE '.$filter;
					break;
				case 'city' :
					$where .= ' AND LOWER(l.city) LIKE '.$filter;
					break;
			}
		}

		// filter date
		if ($params->get('date_filter_type', 0) == 1) // match on all events dates (between start and end)
		{
			if ($filter_date_from && strtotime($filter_date_from))
			{
				$filter_date_from = $this->_db->Quote(date('Y-m-d', strtotime($filter_date_from)));
				$where .= ' AND DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $filter_date_from .') >= 0';
			}
			if ($filter_date_to && strtotime($filter_date_to))
			{
				$filter_date_to = $this->_db->Quote(date('Y-m-d', strtotime($filter_date_to)));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
			}
		} else {
			// match only on start date
			if ($filter_date_from && strtotime($filter_date_from)) {
				$filter_date_from = $this->_db->Quote(date('Y-m-d', strtotime($filter_date_from)));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_from .') >= 0';
			}
			if ($filter_date_to && strtotime($filter_date_to)) {
				$filter_date_to = $this->_db->Quote(date('Y-m-d', strtotime($filter_date_to)));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
			}
		}
		// filter continent
		if ($filter_continent) {
			$where .= ' AND c.continent = ' . $this->_db->Quote($filter_continent);
		}
		// filter country
		if ($filter_country) {
			$where .= ' AND l.country = ' . $this->_db->Quote($filter_country);
		}
		// filter city
		if ($filter_country && $filter_city) {
			$where .= ' AND l.city = ' . $this->_db->Quote($filter_city);
		}
		// filter category
		if ($filter_category) {
			$cats = JemCategories::getChilds((int) $filter_category);
			$where .= ' AND rel.catid IN (' . implode(', ', $cats) .')';
		}

		return $where;
	}

	public function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}
		return $this->_total;
	}

	public function getCategories($id)
	{
		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT c.id, c.catname, c.access, c.lft, c.checked_out AS cchecked_out,'
		       . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
		       . ' FROM #__jem_categories AS c'
		       . ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
		       . ' WHERE rel.itemid = '.(int)$id
		       . ' AND c.published = 1'
		       . ' AND c.access IN (' . implode(',', $levels) . ')'
		       ;

		$this->_db->setQuery($query);

		return $this->_db->loadObjectList();
	}

	public function getCountryOptions()
	{
		$app = Factory::getApplication();

		$filter_continent = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');

		$query = ' SELECT c.iso2 as value, c.name as text '
		       . ' FROM #__jem_events AS a'
		       . ' INNER JOIN #__jem_venues AS l ON l.id = a.locid'
		       . ' INNER JOIN #__jem_countries as c ON c.iso2 = l.country '
		       ;

		if ($filter_continent) {
			$query .= ' WHERE c.continent = ' . $this->_db->Quote($filter_continent);
		}
		$query .= ' GROUP BY c.iso2 ';
		$query .= ' ORDER BY c.name ';
		$this->_db->setQuery($query);

		return $this->_db->loadObjectList();
	}

	public function getCityOptions()
	{
		if (!$country = Factory::getApplication()->input->getString('filter_country', '')) {
			return array();
		}
		$query = ' SELECT DISTINCT l.city as value, l.city as text '
		       . ' FROM #__jem_events AS a'
		       . ' INNER JOIN #__jem_venues AS l ON l.id = a.locid'
		       . ' INNER JOIN #__jem_countries as c ON c.iso2 = l.country '
		       . ' WHERE l.country = ' . $this->_db->Quote($country)
		       . ' ORDER BY l.city ';

		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}

	/**
	 * logic to get the categories
	 *
	 * @access public
	 * @return void
	 */
	public function getCategoryTree()
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');

		// Get the paramaters of the active menu item
		$params = $app->getParams('com_jem');
		$top_id = max(1, $params->get('top_category', 1)); // not below 'root'

		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$where = ' WHERE c.published = 1 AND c.access IN (' . implode(',', $levels) . ')';

		//get the maintained categories and the categories whithout any group
		//or just get all if somebody have edit rights
		$query = 'SELECT c.*'
		       . ' FROM #__jem_categories AS c'
		       . $where
		       . ' ORDER BY c.lft'
		       ;
		

		try
		{
			$db->setQuery($query);
			$mitems = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{			
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
		}

		// Check for a database error.
		// if ($db->getErrorNum())
		// {
		// 	\Joomla\CMS\Factory::getApplication()->enqueueMessage($db->getErrorMsg(), 'notice');
		// }

		if (!$mitems) {
			$mitems = array();
			$children = array();
		} else {
			$children = array();
			// First pass - collect children
			foreach ($mitems as $v)
			{
				$pt = $v->parent_id;
				$list = isset($children[$pt]) ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}
		}

		//get list of the items
		$list = JemCategories::treerecurse($top_id, '', array(), $children, 9999, 0, 0);

		return $list;
	}
}
?>
