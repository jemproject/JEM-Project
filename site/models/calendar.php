<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die ;

require_once dirname(__FILE__) . '/eventslist.php';

/**
 * Model-Calendar
 */
class JemModelCalendar extends JemModelEventslist
{
	protected $_date = 0;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$this->setdate(time());
	}

	public function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		# parent::populateState($ordering, $direction);
		$app          = JFactory::getApplication();
		$params       = $app->getParams();
		$task         = $app->input->get('task','','cmd');
		$top_category = $params->get('top_category', 0);
		$startdayonly = $params->get('show_only_start', false);

		# params
		$this->setState('params', $params);

		# publish state
		$this->_populatePublishState($task);

		###########
		## DATES ##
		###########

		#only select events within specified dates. (chosen month)
		$monthstart = mktime(0, 0, 1, strftime('%m', $this->_date), 1, strftime('%Y', $this->_date));
		$monthend   = mktime(0, 0, -1, strftime('%m', $this->_date)+1, 1, strftime('%Y', $this->_date));

		$filter_date_from = $this->_db->Quote(strftime('%Y-%m-%d', $monthstart));
		$filter_date_to   = $this->_db->Quote(strftime('%Y-%m-%d', $monthend));

		$where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $filter_date_from .') >= 0';
		$this->setState('filter.calendar_from',$where);

		$where = ' DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
		$this->setState('filter.calendar_to',$where);

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
		$this->setState('filter.calendar_multiday',true);
		$this->setState('filter.calendar_startdayonly',(bool)$startdayonly);
		$this->setState('filter.groupby',array('a.id'));
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

		// here we can extend the query of the Eventslist model
		$query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff, DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month');

		return $query;
	}

}
?>