<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filter\InputFilter;

/**
 * JEM Component JEM Model
 *
 * @package JEM
 *
 */
class JemModelMyattendances extends BaseDatabaseModel
{
	protected $_attending = null;
	protected $_total_attending = null;
	protected $_pagination_attending = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app = Factory::getApplication();
		$jemsettings = JemHelper::config();

		//get the number of events

		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.myattendances.limitstart', 0);
		}

		$limit      = $app->getUserStateFromRequest('com_jem.myattendances.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.myattendances.limitstart', 'limitstart', 0, 'int');
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Events user is attending
	 *
	 * @access public
	 * @return array
	 */
	public function getAttending()
	{
		$pop = Factory::getApplication()->input->getBool('pop', false);

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

			foreach ($this->_attending as $i => $item) {
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
	public function getTotalAttending()
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
	public function getAttendingPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination_attending)) {
			$this->_pagination_attending = new Pagination($this->getTotalAttending(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination_attending;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQueryAttending()
	{
		# Get the WHERE and ORDER BY clauses for the query
		$where   = $this->_buildAttendingWhere();
		$orderby = $this->_buildOrderByAttending();
		$groupby = ' GROUP BY a.id';

		# Get Events from Database
		$query = 'SELECT DISTINCT a.id AS eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.published, '
		       . ' a.recurrence_type, a.recurrence_first_id,'
		       . ' a.access, a.checked_out, a.checked_out_time, a.contactid, a.created, a.created_by, a.created_by_alias, a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, a.datimage, a.featured,'
		       . ' a.fulltext, a.hits, a.introtext, a.language, a.maxplaces, a.maxbookeduser, a.minbookeduser, a.reservedplaces, r.places, a.metadata, a.meta_keywords, a.meta_description, a.modified, a.modified_by, a.registra, a.unregistra,'
		       . ' a.recurrence_byday, a.recurrence_counter, a.recurrence_limit, a.recurrence_limit_date, a.recurrence_number, a.version,'
		       . ' a.waitinglist, a.requestanswer, r.status, r.waiting, r.comment,'
		       . ' l.id, l.venue, l.postalCode, l.city, l.state, l.country, l.url, l.published AS l_published,'
		       . ' l.alias AS l_alias, l.checked_out AS l_checked_out, l.checked_out_time AS l_checked_out_time, l.created AS l_created, l.created_by AS l_createdby,'
		       . ' l.custom1 AS l_custom1, l.custom2 AS l_custom2, l.custom3 AS l_custom3, l.custom4 AS l_custom4, l.custom5 AS l_custom5, l.custom6 AS l_custom6, l.custom7 AS l_custom7, l.custom8 AS l_custom8, l.custom9 AS l_custom9, l.custom10 AS l_custom10,'
		       . ' l.id AS l_id, l.latitude, l.locdescription, l.locimage, l.longitude, l.map, l.meta_description AS l_meta_description, l.meta_keywords AS l_meta_keywords, l.modified AS l_modified, l.modified_by AS l_modified_by,'
		       . ' l.publish_up AS l_publish_up, l.publish_down AS l_publish_down, l.street, l.version AS l_version,'
		       . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
		       . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
		       . ' FROM #__jem_events AS a'
		       . ' LEFT JOIN #__jem_register AS r ON r.event = a.id'
		       . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
		       . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
		       . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
		       . $where
		       . $groupby
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
	protected function _buildOrderByAttending()
	{
		$app  = Factory::getApplication();
		$task = $app->input->getCmd('task', '');

		$filter_order = $app->getUserStateFromRequest('com_jem.myattendances.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order = InputFilter::getInstance()->clean($filter_order, 'cmd');

		// Reverse default order for dates in archive mode
		$filter_order_DirDefault = 'ASC';
		if (($task == 'archive') && ($filter_order == 'a.dates')) {
			$filter_order_DirDefault = 'DESC';
		}

		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.myattendances.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_order_Dir = InputFilter::getInstance()->clean($filter_order_Dir, 'word');

		$default_order_Dir	= ($task == 'archive') ? 'DESC' : 'ASC';

		if ($filter_order == 'r.status') {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir . ', r.waiting ' . $filter_order_Dir . ', a.dates ' . $filter_order_Dir .', a.times ' . $filter_order_Dir;
		//	$orderby = ' ORDER BY CASE WHEN r.status < 0 THEN r.status * (-3) WHEN r.status = 1 AND r.waiting > 0 THEN r.status + 1 ELSE r.status END '.$filter_order_Dir.', a.dates ' . $filter_order_Dir .', a.times ' . $filter_order_Dir;
		} elseif ($filter_order == 'a.dates') {
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
	protected function _buildAttendingWhere()
	{
		$app      = Factory::getApplication();

		// Get the paramaters of the active menu item
		$params   = $app->getParams();
		$task     = $app->input->getCmd('task', '');
		$settings = JemHelper::globalattribs();
		$user     = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels   = $user->getAuthorisedViewLevels();

		$filter   = $app->getUserStateFromRequest('com_jem.myattendances.filter', 'filter', 0, 'int');
		$search   = $app->getUserStateFromRequest('com_jem.myattendances.filter_search', 'filter_search', '', 'string');
		$search   = $this->_db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		$where = array();
		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' a.published IN (0,1)';
		}
		$where[] = ' c.published = 1';
		$where[] = ' a.access IN (' . implode(',', $levels) . ')';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		//limit output so only future events the user attends will be shown
		// but also allow events without start date because they will be normally in the future too
		if ($params->get('filtermyregs')) {
			$where[] = ' (a.dates IS NULL OR DATE_SUB(NOW(), INTERVAL '.(int)$params->get('myregspast').' DAY) < (IF (a.enddates IS NOT NULL, a.enddates, a.dates)))';
		}

		// then if the user is attending the event
		$where[] = ' r.uid = '.$this->_db->Quote($user->id);

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

		$where2 = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
		return $where2;
	}

	public function getCategories($id)
	{
		$user = JemFactory::getUser();
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
