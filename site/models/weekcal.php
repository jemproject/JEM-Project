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

require_once __DIR__ . '/eventslist.php';

/**
 * Model-Calendar
 */
class JemModelWeekcal extends JemModelEventslist
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app           = Factory::getApplication();
		$task          = $app->input->getCmd('task', '');
		$params        = $app->getParams();
		$top_category  = $params->get('top_category', 0);
		$startdayonly  = $params->get('show_only_start', false);
		$numberOfWeeks = $params->get('nrweeks', '1');
		$firstweekday  = $params->get('firstweekday', 1);

		# params
		$this->setState('params', $params);

		# publish state
		$this->_populatePublishState($task);

		###########
		## DATES ##
		###########

		#only select events within specified dates. (chosen weeknrs)

		$config = Factory::getConfig();
		$offset = $config->get('offset');
		date_default_timezone_set($offset);
		$datetime = new DateTime();
		// If week starts Monday we use dayoffset 1, on Sunday we use 0 but 7 if today is Sunday.
		$dayoffset = ($firstweekday == 1) ? 1 : ((($firstweekday == 0) && ($datetime->format('N') == 7)) ? 7 : 0);
		$datetime->setISODate($datetime->format('Y'), $datetime->format('W'), $dayoffset);
		$filter_date_from = $datetime->format('Y-m-d');
		$datetime->modify('+'.$numberOfWeeks.' weeks'.' -1 day'); // just to be compatible to php < 5.3 ;-)
		$filter_date_to   = $datetime->format('Y-m-d');

		$where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $this->_db->quote($filter_date_from) . ') >= 0';
		$this->setState('filter.calendar_from', $where);
		$this->setState('filter.date.from', $filter_date_from);

		$where = ' DATEDIFF(a.dates, ' . $this->_db->quote($filter_date_to) . ') <= 0';
		$this->setState('filter.calendar_to', $where);
		$this->setState('filter.date.to', $filter_date_to);

		##################
		## TOP-CATEGORY ##
		##################

		if ($top_category) {
			$children = JemCategories::getChilds($top_category);
			if (count($children)) {
				$where = 'rel.catid IN ('. implode(',', $children) .')';
				$this->setState('filter.category_top', $where);
			}
		}

		# set filter
		$this->setState('filter.calendar_startdayonly', (bool)$startdayonly);
		$this->setState('filter.groupby', 'a.id');
	}

	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$items = parent::getItems();

		if ($items) {
			$items = $this->calendarMultiday($items);

			return $items;
		}

		return array();
	}

	/**
	 * @return	JDatabaseQuery
	 */
	protected function getListQuery()
	{
		// Let parent create a new query object.
		$query = parent::getListQuery();

		$query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff, DAYOFWEEK(a.dates) AS weekday, DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month, WEEK(a.dates) AS weeknumber');

		return $query;
	}

	/**
	 * create multi-day events
	 */
	protected function calendarMultiday($items)
	{
		if (empty($items)) {
			return array();
		}

		$app          = Factory::getApplication();
		$params       = $app->getParams();
		$startdayonly = $this->getState('filter.calendar_startdayonly');

		if (!$startdayonly) {
			foreach ($items as $i => $item)
			{
				if (!is_null($item->enddates) && ($item->enddates != $item->dates)) {
					$day = $item->start_day;
					$multi = array();

					$item->multi = 'first';
					$item->multitimes = $item->times;
					$item->multiname = $item->title;
					$item->sort = 'zlast';

					for ($counter = 0; $counter <= $item->datesdiff-1; $counter++)
					{
						# next day:
						$day++;
						$nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);

						# generate days of current multi-day selection
						$multi[$counter] = clone $item;
						$multi[$counter]->dates = date('Y-m-d', $nextday);

						if ($multi[$counter]->dates < $item->enddates) {
							$multi[$counter]->multi = 'middle';
							$multi[$counter]->multistartdate = $item->dates;
							$multi[$counter]->multienddate = $item->enddates;
							$multi[$counter]->multitimes = $item->times;
							$multi[$counter]->multiname = $item->title;
							$multi[$counter]->times = $item->times;
							$multi[$counter]->endtimes = $item->endtimes;
							$multi[$counter]->sort = 'middle';
						} elseif ($multi[$counter]->dates == $item->enddates) {
							$multi[$counter]->multi = 'zlast';
							$multi[$counter]->multistartdate = $item->dates;
							$multi[$counter]->multienddate = $item->enddates;
							$multi[$counter]->multitimes = $item->times;
							$multi[$counter]->multiname = $item->title;
							$multi[$counter]->sort = 'first';
							$multi[$counter]->times = $item->times;
							$multi[$counter]->endtimes = $item->endtimes;
						}
					} // for

					# add generated days to data
					$items = array_merge($items, $multi);
					# unset temp array holding generated days before working on the next multiday event
					unset($multi);
				}
			} // foreach
		}

		# Remove items out of date range
		$startdate = $this->getState('filter.date.from');
		$enddate   = $this->getState('filter.date.to');
		if (empty($startdate) || empty($enddate)) {
			$config = Factory::getConfig();
			$offset = $config->get('offset');
			$firstweekday  = $params->get('firstweekday', 1); // 1 = Monday, 0 = Sunday
			$numberOfWeeks = $params->get('nrweeks', '1');

			date_default_timezone_set($offset);
			$datetime = new DateTime();
			# If week starts Monday we use dayoffset 1, on Sunday we use 0 but 7 if today is Sunday.
			$dayoffset = ($firstweekday == 1) ? 1 : ((($firstweekday == 0) && ($datetime->format('N') == 7)) ? 7 : 0);
			$datetime->setISODate($datetime->format('Y'), $datetime->format('W'), $dayoffset);
			$startdate = $datetime->format('Y-m-d');
			$datetime->modify('+'.$numberOfWeeks.' weeks'.' -1 day'); // just to be compatible to php < 5.3 ;-)
			$enddate   = $datetime->format('Y-m-d');
		}

		$check_startdate = strtotime($startdate);
		$check_enddate   = strtotime($enddate);

		foreach ($items as $index => $item) {
			$date = $item->dates;
			$date_timestamp = strtotime($date);

			if ($date_timestamp > $check_enddate) {
				unset ($items[$index]);
			} elseif ($date_timestamp < $check_startdate) {
				unset ($items[$index]);
			}
		}

		# Do we still have events? Return if not.
		if (empty($items)) {
			return array();
		}

		# Sort the items
		foreach ($items as $item) {
			$time[] = $item->times;
			$title[] = $item->title;
		//	$id[] = $item->id;
		//	$dates[] = $item->dates;
			$multi[] = (isset($item->multi) ? $item->multi : false);
			$multitime[] = (isset($item->multitime) ? $item->multitime : false);
			$multititle[] = (isset($item->multititle) ? $item->multititle : false);
			$sort[] = (isset($item->sort) ? $item->sort : 'zlast');
		}

		array_multisort($sort, SORT_ASC, $multitime, $multititle, $time, SORT_ASC, $title, $items);

		return $items;
	}

	/**
	 * Method to get the Currentweek
	 *
	 * Info MYSQL WEEK
	 * @link https://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_week
	 */
	public function getCurrentweek()
	{
		if (!isset($this->_currentweek)) {
			$app = Factory::getApplication();
			$params  = $app->getParams('com_jem');
			$weekday = $params->get('firstweekday', 1); // 1 = Monday, 0 = Sunday

			if ($weekday == 1) {
				$number = 3; // Monday, with more than 3 days this year
			} else {
				$number = 6; // Sunday, with more than 3 days this year
			}

			$today =  Date("Y-m-d");
			$query = 'SELECT WEEK(\''.$today.'\','.$number.')' ;

			$this->_db->setQuery($query);
			$this->_currentweek = $this->_db->loadResult();
		}

		return $this->_currentweek;
	}
}
?>
