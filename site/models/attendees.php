<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component attendees Model
 *
 * @package JEM
 *
 */
class JemModelAttendees extends JModelLegacy
{
	/**
	 * Attendees data array
	 *
	 * @access protected
	 * @var array
	 */
	protected $_data = null;

	/**
	 * Attendees total
	 *
	 * @access protected
	 * @var integer
	 */
	protected $_total = null;

	/**
	 * Event data
	 *
	 * @access protected
	 * @var object
	 */
	protected $_event = null;

	/**
	 * Pagination object
	 *
	 * @access protected
	 * @var object
	 */
	protected $_pagination = null;

	/**
	 * Event id
	 *
	 * @access protected
	 * @var int
	 */
	protected $_id = null;

	/**
	 * Cached setting if name or username should be shown.
	 *
	 * @access protected
	 * @var    int
	 */
	protected $_reguser = 1;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app         = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$settings    = JEMHelper::globalattribs();

		$id = $app->input->getInt('id', 0);
		$this->setId((int)$id);

		$this->_reguser = $settings->get('global_regname', '1');

		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.attendees.limitstart', 0);
		}

		$limit		= $app->getUserStateFromRequest( 'com_jem.attendees.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.attendees.limitstart', 'limitstart', 0, 'int' );
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		//set unlimited if export or print action | task=export or task=print
		$task = $app->input->get('task', '');
		$this->setState('unlimited', ($task == 'export' || $task == 'print') ? '1' : '');
	}

	/**
	 * Method to set the event identifier
	 *
	 * @access	public
	 * @param	int Event identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	    = $id;
		$this->_data 	= null;
	}

	/**
	 * Method to get data of the attendees.
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();

			if ($this->getState('unlimited') == '') {
				$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			} else {
				$pagination = $this->getPagination();
				$this->_data = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}
		}

		return $this->_data;
	}

	/**
	 * Method to get the total number of attendees
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the attendees
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
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the attendees
	 *
	 * @access protected
	 * @return string
	 *
	 */
	protected function _buildQuery()
	{
		// Get the ORDER BY clause for the query
		$orderby	= $this->_buildContentOrderBy();
		$where		= $this->_buildContentWhere();

		$query = 'SELECT r.*, u.username, u.name, u.email, a.created_by, a.published,'
				. ' c.catname, c.id AS catid'
		. ' FROM #__jem_register AS r'
		. ' LEFT JOIN #__jem_events AS a ON r.event = a.id'
		. ' LEFT JOIN #__users AS u ON u.id = r.uid'
		. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
		. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
		. $where
		. ' GROUP BY r.id'
		. $orderby
		;

		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the attendees
	 *
	 * @access protected
	 * @return string
	 *
	 */
	protected function _buildContentOrderBy()
	{
		$app =  JFactory::getApplication();

		$filter_order     = $app->getUserStateFromRequest('com_jem.attendees.filter_order',     'filter_order',     'r.waiting', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.attendees.filter_order_Dir', 'filter_order_Dir', 'ASC',       'word' );

		if ($this->_reguser && ($filter_order == 'u.username')) {
			$filter_order = 'u.name';
		}

		$filter_order     = JFilterInput::getinstance()->clean($filter_order,     'cmd');
		$filter_order_Dir = JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$orderby = ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', u.name';

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the attendees
	 *
	 * @access protected
	 * @return string
	 *
	 */
	protected function _buildContentWhere()
	{
		$app =  JFactory::getApplication();
		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();
		$canEdit = $user->can('edit', 'event', $this->_id, $user->id); // where cluase ensures user is the event owner

		$filter         = $app->getUserStateFromRequest('com_jem.attendees.filter',        'filter',        '', 'int');
		$filter_status  = $app->getUserStateFromRequest('com_jem.attendees.filter_status', 'filter_status', -2, 'int');
		$search         = $app->getUserStateFromRequest('com_jem.attendees.filter_search', 'filter_search', '', 'string');
		$search         = $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		$where[] = 'r.event = '.$this->_db->Quote($this->_id);
		if ($filter_status > -2) {
			if ($filter_status >= 1) {
				$waiting = $filter_status == 2 ? 1 : 0;
				$filter_status = 1;
				$where[] = '(a.waitinglist = 0 OR r.waiting = '.$waiting.')';
			}
			$where[] = 'r.status = '.$filter_status;
		}

		// First thing we need to do is to select only needed events
		if (!$canEdit) {
			$where[] = ' a.published = 1';
		}
		$where[] = ' c.published = 1';
		$where[] = ' a.access  IN (' . implode(',', $levels) . ')';
		$where[] = ' c.access  IN (' . implode(',', $levels) . ')';

		// then if the user is the owner of the event
		$where[] = ' a.created_by = '.$this->_db->Quote($user->id);

		/*
		* Search name or username (depends on global setting "reguser"
		*/
		if ($search && $filter == 1) {
			$where[] = ' LOWER(u.name) LIKE \'%'.$search.'%\' ';
		}
		if ($search && $filter == 2) {
			$where[] = ' LOWER(u.username) LIKE \'%'.$search.'%\' ';
		}

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	/**
	 * Get event data
	 *
	 * @access public
	 * @return object
	 *
	 */
	function getEvent()
	{

		$query = 'SELECT id, alias, title, dates, enddates, times, endtimes, maxplaces, waitinglist FROM #__jem_events WHERE id = '.$this->_db->Quote($this->_id);

		$this->_db->setQuery( $query );

		$_event = $this->_db->loadObject();

		return $_event;
	}

	/**
	 * Delete registered users
	 *
	 * @access public
	 * @param  array  $cid  array of attendee IDs
	 * @return true on success
	 *
	 */
	function remove($cid = array())
	{
		if (count($cid))
		{
			JArrayHelper::toInteger($cid);
			$query = 'DELETE FROM #__jem_register WHERE id IN ('. implode(',', $cid) .') ';

			$this->_db->setQuery($query);

			if ($this->_db->execute() === false) {
				JError::raiseError(1001, $this->_db->getErrorMsg());
			}
		}
		return true;
	}
}
?>