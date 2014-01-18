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
jimport('joomla.html.pagination');

/**
 * JEM Component JEM Model
 *
 * @package JEM
 *
 */
class JEMModelMyattendances extends JModelLegacy
{
	var $_attending = null;
	var $_total_attending = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();

		//get the number of events
		$limit		= $app->getUserStateFromRequest('com_jem.myattendances.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.myattendances.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}


	/**
	 * Method to get the Events user is attending
	 *
	 * @access public
	 * @return array
	 */
	function & getAttending()
	{
		$pop = JRequest::getBool('pop');

		// Lets load the content if it doesn't already exist
		if (empty($this->_attending)) {
			$query = $this->_buildQueryAttending();
			$pagination = $this->getAttendingPagination();

			if ($pop) {
				$this->_attending = $this->_getList($query);
			} else {
				$pagination = $this->getAttendingPagination();
				$this->_attending = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}

			$count = count($this->_attending);
			for($i = 0; $i < $count; $i++) {
				$item = $this->_attending[$i];
				$item->categories = $this->getCategories($item->eventid);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_attending[$i]);
				}
			}
		}

		return $this->_attending;
	}


	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotalAttending()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total_attending))
		{
			$query = $this->_buildQueryAttending();
			$this->_total_attending = $this->_getListCount($query);
		}

		return $this->_total_attending;
	}


	/**
	 * Method to get a pagination object for the attending events
	 *
	 * @access public
	 * @return integer
	 */
	function getAttendingPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination_attending)) {
			jimport('joomla.html.pagination');
			$this->_pagination_attending = new JPagination($this->getTotalAttending(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination_attending;
	}


	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQueryAttending()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where = $this->_buildAttendingWhere();
		$orderby = $this->_buildOrderByAttending();

		//Get Events from Database
		$query = 'SELECT DISTINCT a.id AS eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.published, '
			.' l.id, l.venue, l.city, l.state, l.url,'
			. ' c.catname, c.id AS catid,'
			.' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
			.' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
			.' FROM #__jem_events AS a'
			.' LEFT JOIN #__jem_register AS r ON r.event = a.id'
			.' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
			. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
			. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
			.$where
			.$orderby
			;

		return $query;
	}


	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildOrderByAttending()
	{
		$app = JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest('com_jem.myattendances.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.myattendances.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

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
	function _buildAttendingWhere()
	{
		$app = JFactory::getApplication();

		$user = JFactory::getUser();

		// Get the paramaters of the active menu item
		$params = $app->getParams();
		$task = JRequest::getWord('task');

		$settings = JEMHelper::globalattribs();

		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$filter 		= $app->getUserStateFromRequest('com_jem.myattendances.filter', 'filter', '', 'int');
		$search 		= $app->getUserStateFromRequest('com_jem.myattendances.filter_search', 'filter_search', '', 'string');
		$search 		= $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' a.published = 1';
		}
		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		//limit output so only future events the user attends will be shown
		if ($params->get('filtermyregs')) {
			$where [] = ' DATE_SUB(NOW(), INTERVAL '.(int)$params->get('myregspast').' DAY) < (IF (a.enddates IS NOT NULL, a.enddates, a.dates))';
		}

		// then if the user is attending the event
		$where [] = ' r.uid = '.$this->_db->Quote($user->id);

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

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}


	function getCategories($id)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access IN (' . implode(',', $levels) . ')'
				;

		$this->_db->setQuery($query);

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}
}
?>
