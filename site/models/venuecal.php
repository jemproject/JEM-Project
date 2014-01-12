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
 * JEM Component Venue Model
 *
 * @package JEM
 *
*/
class JEMModelVenueCal extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * venue data array
	 *
	 * @var array
	 */
	var $_venue = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

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
		$params = $app->getParams();

		$this->setdate(time());

		if ($jinput->get('id',null,'int')) {
			$id = $jinput->get('id',null,'int');
		} else {
			$id = $params->get('id');
		}

		$this->setId((int)$id);

		//get the number of events from database
		$limit		= $app->getUserStateFromRequest('com_jem.venue.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.venue.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}


	function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to set the venue id
	 *
	 * @access	public
	 * @param	int	venue ID number
	 */
	function setId($id)
	{
		// Set new venue ID and wipe data
		$this->_id			= $id;
		$this->_data		= null;
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
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
		$jinput = JFactory::getApplication()->input;
		$layout = $jinput->get('layout', null, 'word');

		$app = JFactory::getApplication();
		$params = $app->getParams();

		$items = $this->_data;

		// Lets load the content if it doesn't already exist
		if (empty($items)) {
			$query = $this->_buildQuery();
			$items = $this->_getList($query);

			$multi = array();

			foreach($items AS $item) {
				$item->categories = $this->getCategories($item->id);

				if (!is_null($item->enddates) && !$params->get('show_only_start', 1)) {
					if ($item->enddates != $item->dates) {
						$day = $item->start_day;

						for ($counter = 0; $counter <= $item->datediff-1; $counter++) {
							//@todo sort out, multi-day events
							$day++;

							//next day:
							$nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);

							//ensure we only generate days of current month in this loop
							if (strftime('%m', $this->_date) == strftime('%m', $nextday)) {
								$multi[$counter] = clone $item;
								$multi[$counter]->dates = strftime('%Y-%m-%d', $nextday);

								$item->multi = 'first';
								$item->multitimes = $item->times;
								$item->multiname = $item->title;
								$item->sort = 'zlast';

								if ($multi[$counter]->dates < $item->enddates) {
									$multi[$counter]->multi = 'middle';
									$multi[$counter]->multistartdate = $item->dates;
									$multi[$counter]->multienddate = $item->enddates;
									$multi[$counter]->multitimes = $item->times;
									$multi[$counter]->multiname = $item->title;
									$multi[$counter]->times = $item->times;
									$multi[$counter]->endtimes = $item->endtimes;
									$multi[$counter]->sort = 'middle';
								} elseif ($multi[$counter]->dates = $item->enddates) {
									$multi[$counter]->multi = 'zlast';
									$multi[$counter]->multistartdate = $item->dates;
									$multi[$counter]->multienddate = $item->enddates;
									$multi[$counter]->multitimes = $item->times;
									$multi[$counter]->multiname = $item->title;
									$multi[$counter]->sort = 'first';
									$multi[$counter]->times = $item->times;
									$multi[$counter]->endtimes = $item->endtimes;
								}

								//add generated days to data
								$items = array_merge($items, $multi);
							}
							//unset temp array holding generated days before working on the next multiday event
							unset($multi);
						}
					}
				}

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($item);
				}
			}

			// Do we have events now? Return if we don't have one.
			if(empty($items)) {
				return $items;
			}

			foreach ($items as $item) {
				$time[] = $item->times;
				$title[] = $item->title;
			}

			array_multisort($time, SORT_ASC, $title, SORT_ASC, $items);
		}

		return $items;
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
		if (empty($this->_total)) {
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
		if (empty($this->_pagination)) {
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

		//Get Events from Database
		$query = 'SELECT DATEDIFF(a.enddates, a.dates) AS datediff, a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.created, '
				. ' l.venue, l.city, l.state, l.url, l.street, l.custom1, l.custom2, l.custom3, l.custom4, l.custom5, l.custom6, l.custom7, l.custom8, l.custom9, l.custom10, c.catname, ct.name AS countryname, '
				.' DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__jem_events AS a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
				. ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
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
	function _buildOrderBy()
	{
		$app = JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest('com_jem.venue.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.venue.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

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
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildWhere()
	{
		$app 			= JFactory::getApplication();
		$task 			= JRequest::getWord('task');
		$jemsettings 	= JEMHelper::config();

		$user 			= JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels 		= $user->getAuthorisedViewLevels();

		$filter 		= $app->getUserStateFromRequest('com_jem.venue.filter', 'filter', '', 'int');
		$search 		= $app->getUserStateFromRequest('com_jem.venue.filter_search', 'filter_search', '', 'string');
		$search 		= $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2 && a.locid = '.$this->_id;
		} else {
			$where[] = ' a.published = 1 && a.locid = '.$this->_id;
		}
		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		// only select events within specified dates. (chosen month)
		$monthstart = mktime(0, 0, 1, strftime('%m', $this->_date), 1, strftime('%Y', $this->_date));
		$monthend = mktime(0, 0, -1, strftime('%m', $this->_date)+1, 1, strftime('%Y', $this->_date));

		$filter_date_from = $this->_db->Quote(strftime('%Y-%m-%d', $monthstart));
		$where[] = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $filter_date_from .') >= 0';
		$filter_date_to = $this->_db->Quote(strftime('%Y-%m-%d', $monthend));
		$where[] = ' DATEDIFF(a.dates, '. $filter_date_to .') <= 0';

		/* get excluded categories
		 $excluded_cats = trim($params->get('excluded_cats', ''));

		if ($excluded_cats != '') {
		$cats_excluded = explode(',', $excluded_cats);
		$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
		}
		// === END Excluded categories add === //
		*/

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	/**
	 * Method to get the Category
	 *
	 * @access public
	 * @return array
	 */
	function getCategories($id)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('DISTINCT c.id, c.catname, c.access, c.color, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug');
		$query->from('#__jem_categories AS c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
		$query->where('rel.itemid = ' . (int) $id);
		$query->where('c.published = 1');
		$query->where('c.access IN (' . implode(',', $levels) . ')');
		$query->group('c.id');

		$db->setQuery($query);

		$this->_cats = $db->loadObjectList();

		$count = count($this->_cats);

		for($i = 0; $i < $count; $i++) {
			$item = $this->_cats[$i];
			$cats = new JEMCategories($item->id);
			$item->parentcats = $cats->getParentlist();
		}

		return $this->_cats;
	}

	/**
	 * Method to get the Venue
	 *
	 * @access public
	 * @return array
	 */
	function getVenuecal()
	{
		$user = JFactory::getUser();

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('id, venue, city, state, url, street, custom1, custom2, custom3, custom4, custom5, '.
				' custom6, custom7, custom8, custom9, custom10, locimage, meta_keywords, meta_description, '.
				' created, locdescription, country, map, latitude, longitude, postalCode, '.
				' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug');
		$query->from($db->quoteName('#__jem_venues'));
		$query->where('id = '.$this->_id);
		$query->group('id');

		$db->setQuery($query);

		$_venue = $this->_db->loadObject();
		return $_venue;
	}
}
?>
