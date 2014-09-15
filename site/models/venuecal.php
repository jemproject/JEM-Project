<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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
		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$jinput = JFactory::getApplication()->input;
		$params = $app->getParams();

		if ($jinput->get('id',null,'int')) {
			$id = $jinput->get('id',null,'int');
		} else {
			$id = $params->get('id');
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
		$this->_id			= $id;
	}


	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		# parent::populateState($ordering, $direction);
		$app 			= JFactory::getApplication();
		$jemsettings	= JemHelper::config();
		$jinput			= JFactory::getApplication()->input;
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		$params 		= $app->getParams();
		$task           = $jinput->get('task','','cmd');
		$startdayonly 	= $params->get('show_only_start', false);

		# params
		$this->setState('params', $params);

		# publish state
		$this->setState('filter.published', 1);

		# access
		$this->setState('filter.access', true);


		###########
		## DATES ##
		###########

		#only select events within specified dates. (chosen month)
		$monthstart	= mktime(0, 0, 1, strftime('%m', $this->_date), 1, strftime('%Y', $this->_date));
		$monthend	= mktime(0, 0, -1, strftime('%m', $this->_date)+1, 1, strftime('%Y', $this->_date));

		$filter_date_from	= $this->_db->Quote(strftime('%Y-%m-%d', $monthstart));
		$filter_date_to		= $this->_db->Quote(strftime('%Y-%m-%d', $monthend));

		$where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $filter_date_from .') >= 0';
		$this->setState('filter.calendar_from',$where);

		$where = ' DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
		$this->setState('filter.calendar_to',$where);

		# set filter
		$this->setState('filter.calendar_multiday',true);
		$this->setState('filter.calendar_startdayonly',(bool)$startdayonly);
		$this->setState('filter.filter_locid',$this->_id);

		$item = JRequest::getInt('Itemid');
		$app->setUserState('com_jem.venuecal.locid'.$item, $this->_id);

		# groupby
		$this->setState('filter.groupby',array('a.id'));
	}

	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();

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

		// Create a new query object.
		$query = parent::getListQuery();

		$query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff,DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month');

		//$query->where('a.locid = '.$this->_id);

		// here we can extend the query of the Eventslist model
		return $query;
	}
}
?>