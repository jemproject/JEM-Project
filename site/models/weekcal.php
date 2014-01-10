<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die ;

jimport('joomla.application.component.model');

/**
 * JEM Component Calendar Model
 *
 * @package JEM
 *
 */
class JEMModelWeekcal extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Tree categories data array
	 *
	 * @var array
	 */
	var $_categories = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * The reference date
	 *
	 * @var int unix timestamp
	 */
	var $_date = 0;

	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$this->setdate(time());
	}

	function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
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

				if (!is_null($item->enddates)) {
					if ($item->enddates != $item->dates) {
						// $day = $item->start_day;
						$day = $item->start_day;

						for ($counter = 0; $counter <= $item->datediff-1; $counter++) {
							//@todo sort out, multi-day events
							$day++;

							//next day:
							$nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);

							//generate days of current multi-day selection
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

			foreach ($items as $index => $item) {
				$date = $item->dates;
				$firstweekday = $params->get('firstweekday',1); // 1 = Monday, 0 = Sunday

				$config = JFactory::getConfig();
				$offset = $config->get('offset');
				$year = date('Y');

				date_default_timezone_set($offset);
				$datetime = new DateTime();
				$datetime->setISODate($year, $datetime->format("W"), 7);
				$numberOfWeeks = $params->get('nrweeks', '1');

				if ($firstweekday == 1) {
					if(date('N', time()) == 1) {
						#it's monday and monday is startdate;
						$startdate = $datetime->modify('-6 day');
						$startdate = $datetime->format('Y-m-d') . "\n";
						$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
						$enddate = $datetime->format('Y-m-d') . "\n";
					} else {
						#it's not monday but monday is startdate;..
						$startdate = $datetime->modify('-6 day');
						$startdate = $datetime->format('Y-m-d') . "\n";
						$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
						$enddate = $datetime->format('Y-m-d') . "\n";
					}
				}

				if ($firstweekday == 0) {
					if(date('N', time()) == 7) {
						#it's sunday and sunday is startdate;
						$startdate = $datetime->format('Y-m-d') . "\n";
						$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
						$enddate = $datetime->format('Y-m-d') . "\n";
					} else {
						#it's not sunday and sunday is startdate;
						$startdate = $datetime->modify('-7 day');
						$startdate = $datetime->format('Y-m-d') . "\n";
						$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
						$enddate = $datetime->format('Y-m-d') . "\n";
					}
				}

				$check_startdate = strtotime($startdate);
				$check_enddate = strtotime($enddate);
				$date_timestamp = strtotime($date);

				if ($date_timestamp > $check_enddate) {
					unset ($items[$index]);
				} elseif ($date_timestamp < $check_startdate) {
					unset ($items[$index]);
				}
			}

			// Do we still have events? Return if not.
			if(empty($items)) {
				return $items;
			}

			foreach ($items as $item) {
				$time[] = $item->times;
				$title[] = $item->title;
				$id[] = $item->id;
				$dates[] = $item->dates;
				$multi[] = (isset($item->multi) ? $item->multi : false);
				$multitime[] = (isset($item->multitime) ? $item->multitime : false);
				$multititle[] = (isset($item->multititle) ? $item->multititle : false);
				$sort[] = (isset($item->sort) ? $item->sort : 'zlast');
			}

			array_multisort($sort, SORT_ASC, $multitime, $multititle, $time, SORT_ASC, $title, $items);
		}

		return $items;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE clauses for the query
		$where = $this->_buildCategoryWhere();

		//Get Events from Database
		$query = 'SELECT DATEDIFF(a.enddates, a.dates) AS datediff, a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.created, l.venue, l.city, l.state, l.url,'
			.' DAYOFWEEK(a.dates) AS weekday, DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month, WEEK(a.dates) AS weeknumber, '
			.' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
			.' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
			.' FROM #__jem_events AS a'
			.' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
			.' LEFT JOIN #__jem_cats_event_relations AS r ON r.itemid = a.id '
			.' LEFT JOIN #__jem_categories AS c ON c.id = r.catid'
			.$where
			.' GROUP BY a.id '
			;
		return $query;
	}

	/**
	 * Method to build the WHERE clause
	 *
	 * In here we will have to calculate the given weeks
	 *
	 * @access private
	 * @return array
	 */
	function _buildCategoryWhere()
	{
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$db = $this->getDbo();

		$params = $app->getParams();
		$numberOfWeeks = $params->get('nrweeks', '1');
		$firstweekday = $params->get('firstweekday',1);
		$top_category = $params->get('top_category', 0);
		$task = JRequest::getWord('task');

		// First thing we need to do is to select only the published events
		if ($task == 'archive') {
			$where = ' WHERE a.published = 2 ';
		} else {
			$where = ' WHERE a.published = 1 ';
		}
		$where .= ' AND c.published = 1';
		$where .= ' AND c.access IN (' . implode(',', $levels) . ')';

		$config = JFactory::getConfig();
		$offset = $config->get('offset');
		$year = date('Y');
		date_default_timezone_set($offset);
		$datetime = new DateTime();
		$datetime->setISODate($year, $datetime->format("W"), 7);

		if ($firstweekday == 1) {
			if(date('N', time()) == 1) {
				#it's monday and monday is startdate;
				$startdate = $datetime->modify('-6 day');
				$startdate = $datetime->format('Y-m-d') . "\n";
				$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
				$enddate = $datetime->format('Y-m-d') . "\n";
			} else {
				# it's not monday but monday is startdate;
				$startdate = $datetime->modify('-6 day');
				$startdate = $datetime->format('Y-m-d') . "\n";
				$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
				$enddate = $datetime->format('Y-m-d') . "\n";
			}
		}

		if ($firstweekday == 0) {
			if(date('N', time()) == 7) {
				#it's sunday and sunday is startdate;
				$startdate = $datetime->format('Y-m-d') . "\n";
				$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
				$enddate = $datetime->format('Y-m-d') . "\n";
			} else {
				#it's not sunday and sunday is startdate;
				$startdate = $datetime->modify('-7 day');
				$startdate = $datetime->format('Y-m-d') . "\n";
				$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks');
				$enddate = $datetime->format('Y-m-d') . "\n";
			}
		}

		$where .= ' AND DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), "'. $startdate .'") >= 0';
		$where .= ' AND DATEDIFF(a.dates, "'. $enddate .'") <= 0';

		if ($top_category) {
			$children = JEMCategories::getChilds($top_category);
			if (count($children)) {
				$where .= ' AND r.catid IN ('. implode(',', $children) .')';
			}
		}

		return $where;
	}


	/**
	 * Method to get the Categories
	 *
	 * @access public
	 * @return integer
	 */
	function getCategories($id)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT c.id, c.catname, c.access, c.color, c.published, c.checked_out AS cchecked_out,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
			. ' FROM #__jem_categories AS c'
			. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
			. ' WHERE rel.itemid = '.(int)$id
			. ' AND c.published = 1'
			. ' AND c.access IN (' . implode(',', $levels) . ')'
			;

		$this->_db->setQuery($query);
		$this->_categories = $this->_db->loadObjectList();

		return $this->_categories;
	}


	/**
	 * Method to get the Currentweek
	 *
	 * Info MYSQL WEEK:
	 * http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_week
	 *
	 */
	function getCurrentweek()
	{
		$app = JFactory::getApplication();
		$params =  $app->getParams('com_jem');
		$weekday = $params->get('firstweekday',1); // 1 = Monday, 0 = Sunday

		if ($weekday == 1) {
			$number = 3; // Monday, with more than 3 days this year
		} else {
			$number = 6; // Sunday, with more than 3 days this year
		}

		$today =  Date("Y-m-d");
		$query = 'SELECT WEEK(\''.$today.'\','.$number.')' ;

		$this->_db->setQuery($query);
		$this->_currentweek = $this->_db->loadResult();

		return $this->_currentweek;
	}
}
?>