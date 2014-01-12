<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Day Model
 *
 * @package JEM
 *
 */
class JEMModelDay extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Date
	 *
	 * @var string
	 */
	var $_date = null;

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
	function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$jinput = JFactory::getApplication()->input;

		$limit      = $app->getUserStateFromRequest('com_jem.day.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.day.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$rawday = $jinput->get('id',null,'int');
		$this->setDate($rawday);
	}

	/**
	 * Method to set the date
	 *
	 * @access	public
	 * @param	string
	 */
	function setDate($date)
	{
		$app = JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params = $app->getParams('com_jem');

		//0 means we have a direct request from a menuitem and without any parameters (eg: calendar module)
		if ($date == 0) {
			$dayoffset	= $params->get('days');
			$timestamp	= mktime(0, 0, 0, date("m"), date("d") + $dayoffset, date("Y"));
			$date		= strftime('%Y-%m-%d', $timestamp);

		//a valid date  has 8 characters
		} elseif (strlen($date) == 8) {
			$year 	= substr($date, 0, -4);
			$month	= substr($date, 4, -2);
			$tag	= substr($date, 6);

			//check if date is valid
			if (checkdate($month, $tag, $year)) {
				$date = $year.'-'.$month.'-'.$tag;
			} else {
				//date isn't valid raise notice and use current date
				$date = date('Ymd');
				JError::raiseNotice('SOME_ERROR_CODE', JText::_('COM_JEM_INVALID_DATE_REQUESTED_USING_CURRENT'));
			}
		} else {
			//date isn't valid raise notice and use current date
			$date = date('Ymd');
			JError::raiseNotice('SOME_ERROR_CODE', JText::_('COM_JEM_INVALID_DATE_REQUESTED_USING_CURRENT'));
		}

		$this->_date = $date;
	}

	/**
	 * Method to get the Events
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
		$pop = JRequest::getBool('pop');

		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$pagination = $this->getPagination();
			$this->_data = $this->_getList($query, $pagination->limitstart, $pagination->limit);

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
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildWhere();
		$orderby	= $this->_buildOrderBy();
		$db = JFactory::getDbo();

		//Get Events from Database
		$query = 'SELECT DISTINCT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.fulltext,'
				. ' l.venue, l.city, l.state, l.url, l.street, ct.name as countryname, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__jem_events AS a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
				. $where
				. $orderby
				;
		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildOrderBy()
	{
		$app = JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest('com_jem.day.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.day.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order == 'a.dates') {
			$orderby = ' ORDER BY a.dates, a.times ' . $filter_order_Dir;
		} else {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		}

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildWhere()
	{
		$app 		= JFactory::getApplication();
		$settings 	= JEMHelper::globalattribs();

		$jinput = JFactory::getApplication()->input;
		$requestVenueId = $jinput->get('locid', null, 'int');
		$requestCategoryId = $jinput->get('catid', null, 'int');

		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$filter 		= $app->getUserStateFromRequest('com_jem.day.filter', 'filter', '', 'int');
		$search 		= $app->getUserStateFromRequest('com_jem.day.filter_search', 'filter_search', '', 'string');
		$search 		= $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		// First thing we need to do is to select only needed events
		$where[] = ' a.published = 1';
		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		if ($requestVenueId){
			$where[]= ' a.locid = '.$requestVenueId;
		}

		if ($requestCategoryId){
			$where[]= ' c.id = '.$requestCategoryId;
		}

		// Second is to only select events of the specified day
		$where[]= ' (\''.$this->_date.'\' BETWEEN (a.dates) AND (IF (a.enddates >= a.dates, a.enddates, a.dates)) OR \''.$this->_date.'\' = a.dates)';

		/*
		// get excluded categories
		$excluded_cats = trim($params->get('excluded_cats', ''));

		if ($excluded_cats != '') {
			$cats_excluded = explode(',', $excluded_cats);
			$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
		}
		// === END Excluded categories add === //
		*/

		if ($settings->get('global_show_filter') && $search)
		{
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

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	/**
	 * Return date
	 *
	 * @access public
	 * @return string
	 */
	function getDay()
	{
		return $this->_date;
	}


	/**
	 * get event categories
	 *
	 * @param int event id
	 * @return array
	 */
	function getCategories($id)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug');
		$query->from('#__jem_categories AS c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
		$query->where('rel.itemid = '.(int)$id);
		$query->where('c.published = 1');
		$query->where('c.access IN (' . implode(',', $levels) . ')');

		$db->setQuery($query);

		$this->_cats = $db->loadObjectList();

		return $this->_cats;
	}
}
?>
