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
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/eventslist.php';

/**
 * Model-Day
 */
class JemModelDay extends JemModelEventslist
{
	protected $_date = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$rawday = Factory::getApplication()->input->getInt('id', null);
		$this->setDate($rawday);
	}

	/**
	 * Method to set the date
	 *
	 * @access public
	 * @param  string
	 */
	public function setDate($date)
	{
		$app = Factory::getApplication();

		# Get the params of the active menu item
		$params = $app->getParams('com_jem');

		# 0 means we have a direct request from a menuitem and without any params (eg: calendar module)
		if ($date == 0) {
			$dayoffset = $params->get('days');
			$timestamp = mktime(0, 0, 0, date("m"), date("d") + $dayoffset, date("Y"));
			$date      = date('Y-m-d', $timestamp);

		# a valid date has 8 characters (ymd)
		} elseif (strlen($date) == 8) {
			$year  = substr($date, 0, -4);
			$month = substr($date, 4, -2);
			$day   = substr($date, 6);

			//check if date is valid
			if (checkdate($month, $day, $year)) {
				$date = $year.'-'.$month.'-'.$day;
			} else {
				//date isn't valid raise notice and use current date
				$date = date('Ymd');
				Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_INVALID_DATE_REQUESTED_USING_CURRENT'), 'notice');
			}
		} else {
			//date isn't valid raise notice and use current date
			$date = date('Ymd');
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_INVALID_DATE_REQUESTED_USING_CURRENT'), 'notice');
		}

		$this->_date = $date;
	}

	/**
	 * Return date
	 */
	public function getDay()
	{
		return $this->_date;
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		# parent::populateState($ordering, $direction);

		$app               = Factory::getApplication();
		$jemsettings       = JemHelper::config();
		$itemid            = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);
		$params            = $app->getParams();
		$task              = $app->input->getCmd('task', '');
		$requestVenueId    = $app->input->getInt('locid', 0);
		$requestCategoryId = $app->input->getInt('catid', 0);
		$item              = $app->input->getInt('Itemid', 0);

		$locid = $app->getUserState('com_jem.venuecal.locid'.$item);
		if ($locid) {
			$this->setState('filter.filter_locid', $locid);
		}

		// maybe list of venue ids from calendar module
		$locids = explode(',', $app->input->getString('locids', ''));
		foreach ($locids as $id) {
			if ((int)$id > 0) {
				$venues[] = (int)$id;
			}
		}
		if (!empty($venues)) {
			$this->setState('filter.venue_id', $venues);
			$this->setState('filter.venue_id.include', true);
		}

		$cal_category_catid = $app->getUserState('com_jem.categorycal.catid'.$item);
		if ($cal_category_catid) {
			$this->setState('filter.req_catid', $cal_category_catid);
		}

		// maybe list of venue ids from calendar module
		$catids = explode(',', $app->input->getString('catids', ''));
		foreach ($catids as $id) {
			if ((int)$id > 1) { // don't accept 'root'
				$cats[] = (int)$id;
			}
		}
		if (!empty($cats)) {
			$this->setState('filter.category_id', $cats);
			$this->setState('filter.category_id.include', true);
		}
################################
		## EXCLUDE/INCLUDE CATEGORIES ##
		################################
		
		$catswitch = $params->get('categoryswitch', '');
		
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

		// maybe top category is given by calendar view
		$top_category = $app->input->getInt('topcat', 0);
		if ($top_category > 0) { // accept 'root'
			$children = JemCategories::getChilds($top_category);
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
		$filtertype = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
		$this->setState('filter.filter_type', $filtertype);

		# filter_order
		$orderCol = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$this->setState('filter.filter_ordering', $orderCol);

		# filter_direction
		$listOrder = $app->getUserStateFromRequest('com_jem.day.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
		$this->setState('filter.filter_direction', $listOrder);

		$defaultOrder = ($task == 'archive') ? 'DESC' : 'ASC';
		if ($orderCol == 'a.dates') {
			$orderby = array('a.dates ' . $listOrder, 'a.times ' . $listOrder, 'a.created ' . $listOrder);
		} else {
			$orderby = array($orderCol . ' ' . $listOrder,
			                 'a.dates ' . $defaultOrder, 'a.times ' . $defaultOrder, 'a.created ' . $defaultOrder);
		}
		$this->setState('filter.orderby', $orderby);

		# params
		$this->setState('params', $params);

		# published
		/// @todo bring given pub together with eventslist's unpub calculation (_populatePublishState())
		$pub = explode(',', $app->input->getString('pub', ''));
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
			$this->setState('filter.req_venid', $requestVenueId);
		}

		# request cat-id
		if ($requestCategoryId) {
			$this->setState('filter.req_catid', $requestCategoryId);
		}

		# groupby
		$this->setState('filter.groupby', array('a.id'));
	}

	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$items = parent::getItems();

		if ($items) {
			return $items;
		}

		return array();
	}

	/**
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$query = parent::getListQuery();

		$requestVenueId = $this->getState('filter.req_venid');
		if ($requestVenueId){
			$query->where(' a.locid = '.$this->_db->quote($requestVenueId));
		}

		// Second is to only select events of the specified day
		$query->where('('.$this->_db->quote($this->_date).' BETWEEN (a.dates) AND (IF (a.enddates >= a.dates, a.enddates, a.dates)) OR '.$this->_db->quote($this->_date).' = a.dates)');

		return $query;
	}
}
?>
