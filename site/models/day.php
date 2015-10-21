<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/eventslist.php';

/**
 * Model-Day
 */
class JemModelDay extends JemModelEventslist
{
	var $_date = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();
		$jemsettings = JemHelper::config();
		$jinput = JFactory::getApplication()->input;

		$rawday = $jinput->getInt('id', null);
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

		# Get the params of the active menu item
		$params = $app->getParams('com_jem');

		# 0 means we have a direct request from a menuitem and without any params (eg: calendar module)
		if ($date == 0) {
			$dayoffset	= $params->get('days');
			$timestamp	= mktime(0, 0, 0, date("m"), date("d") + $dayoffset, date("Y"));
			$date		= strftime('%Y-%m-%d', $timestamp);

		# a valid date has 8 characters (ymd)
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
	 * Return date
	 */
	function getDay()
	{
		return $this->_date;
	}


	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		# parent::populateState($ordering, $direction);

		$app               = JFactory::getApplication();
		$jemsettings       = JemHelper::config();
		$jinput            = JFactory::getApplication()->input;
		$itemid            = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);

		$params            = $app->getParams();
		$task              = $jinput->getCmd('task', '');
		$requestVenueId    = $jinput->getInt('locid', 0);
		$requestCategoryId = $jinput->getInt('catid', 0);
		$item              = $jinput->getInt('Itemid', 0);

		$locid = $app->getUserState('com_jem.venuecal.locid'.$item);
		if ($locid) {
			$this->setstate('filter.filter_locid',$locid);
		}

		// maybe list of venue ids from calendar module
		$locids = explode(',', $jinput->getString('locids', ''));
		foreach ($locids as $id) {
			if ((int)$id > 0) {
				$venues[] = (int)$id;
			}
		}
		if (!empty($venues)) {
			$this->setstate('filter.venue_id', $venues);
			$this->setstate('filter.venue_id.include', true);
		}

		$cal_category_catid = $app->getUserState('com_jem.categorycal.catid'.$item);
		if ($cal_category_catid) {
			$this->setState('filter.req_catid',$cal_category_catid);
		}

		// maybe list of venue ids from calendar module
		$catids = explode(',', $jinput->getString('catids', ''));
		foreach ($catids as $id) {
			if ((int)$id > 1) { // don't accept 'root'
				$cats[] = (int)$id;
			}
		}
		if (!empty($cats)) {
			$this->setstate('filter.category_id', $cats);
			$this->setstate('filter.category_id.include', true);
		}

		// maybe top category is given by calendar view
		$top_category = $jinput->getInt('topcat', 0);
		if ($top_category > 0) { // accept 'root'
			$children = JEMCategories::getChilds($top_category);
			if (count($children)) {
				$where = 'rel.catid IN ('. implode(',', $children) .')';
				$this->setState('filter.category_top', $where);
			}
		}

		# limit/start

		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.day.'.$itemid.'.limitstart', 0);
		}

		$limit = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
		$this->setState('list.limit', $limit);

		$limitstart = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
		$this->setState('list.start', $limitstart);

		# Search
		$search = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search);

		# FilterType
		$filtertype = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_type', 'filter_type', '', 'int');
		$this->setState('filter.filter_type', $filtertype);

		# filter_order
		$orderCol = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$this->setState('filter.filter_ordering', $orderCol);

		# filter_direction
		$listOrder = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
		$this->setState('filter.filter_direction', $listOrder);

		if ($orderCol == 'a.dates') {
			$orderby = array('a.dates ' . $listOrder, 'a.times ' . $listOrder);
		} else {
			$orderby = $orderCol . ' ' . $listOrder;
		}
		$this->setState('filter.orderby', $orderby);

		# params
		$this->setState('params', $params);

		# published
		/// @todo bring given pub together with eventslist's unpub calculation (_populatePublishState())
		$pub = explode(',', $jinput->getString('pub', ''));
		$published = array();
		// sanitize remote data
		foreach ($pub as $val) {
			if (((int)$val >= 1) && ((int)$val <= 2)) {
				$published[] = (int)$val;
			}
		}
		// default to 'published'
		if (empty($published)) {
			//$published[] = 1;
			$this->_populatePublishState($task);
		} else {
			$this->setState('filter.published', $published);
		}

		# request venue-id
		if ($requestVenueId) {
			$this->setState('filter.req_venid',$requestVenueId);
		}

		# request cat-id
		if ($requestCategoryId) {
			$this->setState('filter.req_catid',$requestCategoryId);
		}

		# groupby
		$this->setState('filter.groupby',array('a.id'));
	}

	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$items	= parent::getItems();

		if ($items) {
			return $items;
		}

		return array();
	}

	/**
	 * @return	JDatabaseQuery
	 */
	function getListQuery()
	{
		$params  = $this->state->params;
		$jinput  = JFactory::getApplication()->input;
		$task    = $jinput->get('task','','cmd');

		$requestVenueId 	= $this->getState('filter.req_venid');

		// Create a new query object.
		$query = parent::getListQuery();

		if ($requestVenueId){
			$query->where(' a.locid = '.$this->_db->quote($requestVenueId));
		}

		// Second is to only select events of the specified day
		$query->where('('.$this->_db->quote($this->_date).' BETWEEN (a.dates) AND (IF (a.enddates >= a.dates, a.enddates, a.dates)) OR '.$this->_db->quote($this->_date).' = a.dates)');

		return $query;
	}
}
?>