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
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

jimport('joomla.application.component.model');

/**
 * JEM Component JEM Model
 *
 * @package JEM
 *
*/
class JemModelMyevents extends BaseDatabaseModel
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	protected $_events = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	protected $_total_events = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	protected $_pagination_events = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app = Factory::getApplication();
		$jemsettings = JemHelper::config();

		//get the number of events from database

		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.myevents.limitstart', 0);
		}

		$limit      = $app->getUserStateFromRequest('com_jem.myevents.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.myevents.limitstart', 'limitstart', 0, 'int');
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Events
	 *
	 * @access public
	 * @return array
	 */
	public function getEvents()
	{
		$pop = Factory::getApplication()->input->getBool('pop', false);
		$user = JemFactory::getUser();
		$userId = $user->get('id');

		if (empty($userId)) {
			$this->_events = array();
			return array();
		}

		// Lets load the content if it doesn't already exist
		if (empty($this->_events)) {
			$query = $this->_buildQueryEvents();
			$pagination = $this->getEventsPagination();

			if ($pop) {
				$this->_events = $this->_getList($query);
			} else {
				$pagination = $this->getEventsPagination();
				$this->_events = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}
		}

		if ($this->_events) {
			$now = time();
			foreach ($this->_events as $i => $item) {
				$item->categories = $this->getCategories($item->eventid);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_events[$i]);
				} else {
					if (empty($item->params)) {
						// Set event params.
						$registry = new JRegistry();
						$registry->loadString($item->attribs ??'{}');
						$item->params = clone JemHelper::globalattribs();
						$item->params->merge($registry);
					}
					# edit state access permissions.
					$item->params->set('access-change', $user->can('publish', 'event', $item->id, $item->created_by));

					# calculate if event has finished (which e.g. makes adding attendees useless)
					$date = $item->enddates ? $item->enddates : $item->dates;
					$time = $item->endtimes ? $item->endtimes : $item->times;
					$ts = strtotime($date . ' ' . $time);
					$item->finished = $ts && ($ts < $now); // we have a timestamp and it's in the past
				}
			}

			JemHelper::getAttendeesNumbers($this->_events); // does directly edit events
		}

		return $this->_events;
	}

	/**
	 * Method to (un)publish a event
	 *
	 * @access public
	 * @return boolean True on success
	 */
	public function publish($cid = array(), $publish = 1)
	{
		$result = false;
		$user   = JemFactory::getUser();
		$userid = (int) $user->get('id');

		if (is_array($cid) && count($cid)) {
			\Joomla\Utilities\ArrayHelper::toInteger($cid);
			$cids = implode(',', $cid);

			$query = 'UPDATE #__jem_events'
			       . ' SET published = '. (int) $publish
			       . ' WHERE id IN ('. $cids .')'
			       . ' AND (checked_out = 0 OR (checked_out = ' .$userid. '))'
			       ;

			$this->_db->setQuery($query);
			$result = true;

			if ($this->_db->execute() === false) {
				$this->setError($this->_db->getErrorMsg());
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	public function getTotalEvents()
	{
		// Lets load the total nr if it doesn't already exist
		if ( empty($this->_total_events)) {
			$query = $this->_buildQueryEvents();
			$this->_total_events = $this->_getListCount($query);
		}

		return $this->_total_events;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	public function getEventsPagination()
	{
		// Lets load the content if it doesn't already exist
		if ( empty($this->_pagination_events)) {
			$this->_pagination_events = new Pagination($this->getTotalEvents(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination_events;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQueryEvents()
	{
		# Get the WHERE and ORDER BY clauses for the query
		$where   = $this->_buildWhere();
		$orderby = $this->_buildOrderBy();

		# Get Events from Database
		$query = 'SELECT DISTINCT a.id as eventid, a.id, a.dates, a.enddates, a.published, a.times, a.endtimes, a.title, a.created, a.created_by, a.locid, a.registra, a.unregistra, a.maxplaces, a.waitinglist, a.requestanswer, '
		       . ' a.recurrence_type, a.recurrence_first_id, a.recurrence_byday, a.recurrence_counter, a.recurrence_limit, a.recurrence_limit_date, a.recurrence_number, a.attribs,'
		       . ' a.access, a.checked_out, a.checked_out_time, a.maxplaces, a.maxbookeduser, a.minbookeduser, a.reservedplaces, a.contactid, a.created_by_alias, a.datimage, a.featured,'
		       . ' a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10,'
		       . ' a.fulltext, a.hits, a.introtext, a.language, a.metadata, a.meta_keywords, a.meta_description, a.modified, a.modified_by, a.version,'
		       . ' l.id AS l_id, l.venue, l.street, l.postalCode, l.city, l.state, l.country, l.url, l.published AS l_published,'
		       . ' l.alias AS l_alias, l.checked_out AS l_checked_out, l.checked_out_time AS l_checked_out_time, l.created AS l_created, l.created_by AS l_createdby,'
		       . ' l.custom1 AS l_custom1, l.custom2 AS l_custom2, l.custom3 AS l_custom3, l.custom4 AS l_custom4, l.custom5 AS l_custom5, l.custom6 AS l_custom6, l.custom7 AS l_custom7, l.custom8 AS l_custom8, l.custom9 AS l_custom9, l.custom10 AS l_custom10,'
		       . ' l.latitude, l.locdescription, l.locimage, l.longitude, l.map, l.meta_description AS l_meta_description, l.meta_keywords AS l_meta_keywords, l.modified AS l_modified, l.modified_by AS l_modified_by,'
		       . ' l.publish_up AS l_publish_up, l.publish_down AS l_publish_down, l.version AS l_version,'
		       . ' c.catname, c.id AS catid,'
		       . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
		       . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
		       . ' FROM #__jem_events AS a'
		       . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
		       . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
		       . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
		       . $where
		       . ' GROUP BY a.id'
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
	protected function _buildOrderBy()
	{
		$app  = Factory::getApplication();
		$task = $app->input->getCmd('task', '');

		$filter_order      = $app->getUserStateFromRequest('com_jem.myevents.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir  = $app->getUserStateFromRequest('com_jem.myevents.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
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
		$app      = Factory::getApplication();
		$task     = $app->input->getCmd('task', '');
		$params   = $app->getParams();
		$settings = JemHelper::globalattribs();
		$user     = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels   = $user->getAuthorisedViewLevels();

		$filter   = $app->getUserStateFromRequest('com_jem.myevents.filter', 'filter', 0, 'int');
		$search   = $app->getUserStateFromRequest('com_jem.myevents.filter_search', 'filter_search', '', 'string');
		$search   = $this->_db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		$where = array();
		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' (a.published = 1 OR a.published = 0)';
		}
		$where[] = ' c.published = 1';
		$where[] = ' a.access IN (' . implode(',', $levels) . ')';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		// then if the user is the owner of the event
		$where[] = ' a.created_by = '.$this->_db->Quote($user->id);

		// get excluded categories
		$excluded_cats = trim($params->get('excluded_cats', ''));

		if ($excluded_cats != '') {
			$cats_excluded = explode(',', $excluded_cats);
			\Joomla\Utilities\ArrayHelper::toInteger($cats_excluded);
			$where[] = '  c.id NOT IN (' . implode(',', $cats_excluded) . ')';
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

		$where2 = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
		return $where2;
	}

	public function getCategories($id)
	{
		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out, c.groupid,'
		       . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
		       . ' FROM #__jem_categories AS c'
		       . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
		       . ' WHERE rel.itemid = '.(int)$id
		       . ' AND c.published = 1'
		       . ' AND c.access IN (' . implode(',', $levels) . ')'
		       ;

		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}
}
?>
