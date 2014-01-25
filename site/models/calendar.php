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
class JEMModelCalendar extends JModelLegacy
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
		$query = 'SELECT DATEDIFF(a.enddates, a.dates) AS datediff, a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.created, l.venue,'
			.' DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month,'
			.' c.catname, c.access, c.id AS catid,'
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
	 * @access private
	 * @return array
	 */
	function _buildCategoryWhere()
	{
		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		// Get the paramaters of the active menu item
		$params = $app->getParams();

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

		// only select events within specified dates. (chosen month)
		$monthstart = mktime(0, 0, 1, strftime('%m', $this->_date), 1, strftime('%Y', $this->_date));
		$monthend = mktime(0, 0, -1, strftime('%m', $this->_date)+1, 1, strftime('%Y', $this->_date));

		$filter_date_from = $this->_db->Quote(strftime('%Y-%m-%d', $monthstart));
		$where .= ' AND DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $filter_date_from .') >= 0';
		$filter_date_to = $this->_db->Quote(strftime('%Y-%m-%d', $monthend));
		$where .= ' AND DATEDIFF(a.dates, '. $filter_date_to .') <= 0';

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
}
?>
