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
 * Model-Venuecal
 **/
class JemModelVenueCal extends JemModelEventslist
{
	protected $_venue = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$app         = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$jinput      = $app->input;
		$params      = $app->getParams();

		$id = $jinput->getInt('id', 0);
		if (empty($id)) {
			$id = $params->get('id', 0);
		}

		$this->setdate(time());
		$this->setId((int)$id);

		parent::__construct();
	}


	function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to set the venue id
	 */
	function setId($id)
	{
		// Set new venue ID and wipe data
		$this->_id = $id;
	}


	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app          = JFactory::getApplication();
		$jemsettings  = JemHelper::config();
		$params       = $app->getParams();
		$jinput       = $app->input;
		$itemid       = $jinput->getInt('Itemid', 0);
		$task         = $jinput->getCmd('task', '');
		$startdayonly = $params->get('show_only_start', false);

		# params
		$this->setState('params', $params);

		# publish state
		$this->_populatePublishState($task);


		###########
		## DATES ##
		###########

		#only select events within specified dates. (chosen month)
		$monthstart	= mktime(0, 0,  1, strftime('%m', $this->_date),   1, strftime('%Y', $this->_date));
		$monthend	= mktime(0, 0, -1, strftime('%m', $this->_date)+1, 1, strftime('%Y', $this->_date));

		$filter_date_from = strftime('%Y-%m-%d', $monthstart);
		$filter_date_to   = strftime('%Y-%m-%d', $monthend);

		$where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $this->_db->Quote($filter_date_from) .') >= 0';
		$this->setState('filter.calendar_from', $where);

		$where = ' DATEDIFF(a.dates, '. $this->_db->Quote($filter_date_to) .') <= 0';
		$this->setState('filter.calendar_to', $where);

		# set filter
		$this->setState('filter.calendar_multiday', true);
		$this->setState('filter.calendar_startdayonly', (bool)$startdayonly);
		$this->setState('filter.filter_locid', $this->_id);

		$app->setUserState('com_jem.venuecal.locid'.$itemid, $this->_id);

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
	 * @return	JDatabaseQuery
	 */
	function getListQuery()
	{
		// Let parent create a new query object.
		$query = parent::getListQuery();

		// here we can extend the query of the Eventslist model
		$query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff,DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month');
		//$query->where('a.locid = '.$this->_id);

		return $query;
	}
}
?>