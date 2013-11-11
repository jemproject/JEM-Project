<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */
defined('_JEXEC') or die();

jimport('joomla.application.component.modellist');

/**
 * JEM Component Export Model
 */
class JEMModelExport extends JModelList
{
	/**
	 * Constructor.
	 *
	 * @param array An optional associative array of configuration settings.
	 * @see JController
	 *
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'id',
				'a.id'
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app = JApplication::getInstance('administrator');

		// Load the filter state.
		$filter_form_type = $app->getUserStateFromRequest($this->context . '.filter.form_type', 'filter_form_type');
		$this->setState('filter.form_type', $filter_form_type);

		$filter_start_date = $app->getUserStateFromRequest($this->context . '.filter.start_date', 'filter_start_date');
		$this->setState('filter.start_date', $filter_start_date);

		$filter_end_date = $app->getUserStateFromRequest($this->context . '.filter.end_date', 'filter_end_date');
		$this->setState('filter.end_date', $filter_end_date);

		// Load the parameters.
		$params = JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('a.first_name', 'asc');
	}


	/**
	 * Build an SQL query to load the Events data.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		// Retrieve variables
		$jinput = JFactory::getApplication()->input;
		$startdate = $jinput->get('dates', '', 'string');
		$enddate = $jinput->get('enddates', '', 'string');
		$cats = $jinput->get('cid', array(), 'post', 'array');

		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('`#__jem_events` AS a');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		// check if startdate + enddate are set.
		if (! empty($startdate) && ! empty($enddate)) {
			$query->where('DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), "' . $startdate . '") >= 0');
			$query->where('DATEDIFF(a.dates, "' . $enddate . '") <= 0');
		}

		// check if specific category's have been selected
		if (! empty($cats)) {
			$query->where('  (c.id=' . implode(' OR c.id=', $cats) . ')');
		}

		// Group the query
		$query->group('a.id');

		return $query;
	}

	/**
	 * Returns a CSV file with Events data
	 * @return boolean
	 */
	public function getCsv()
	{
		$this->populateState();

		$jinput = JFactory::getApplication()->input;
		$includecategories = $jinput->get('categorycolumn', 0, 'int');

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();

		if ($includecategories == 1) {
			$header = array();
			$events = array_keys($db->getTableColumns('#__jem_events'));
			$categories = array();
			$categories[] = "categories";
			$header = array_merge($events, $categories);

			fputcsv($csv, $header, ';');

			$query = $this->getListQuery();
			$items = $this->_getList($query);

			foreach ($items as $item) {
				$item->categories = $this->getCatEvent($item->id);
			}
		} else {
			$header = array_keys($db->getTableColumns('#__jem_events'));
			fputcsv($csv, $header, ';');
			$query = $this->getListQuery();
			$items = $this->_getList($query);
		}

		foreach ($items as $lines) {
			foreach ($lines as &$line) {
				$line = mb_convert_encoding($line, 'Windows-1252', 'auto');
			}
			fputcsv($csv, (array) $lines, ';', '"');
		}

		return fclose($csv);
	}


	/**
	 * Build an SQL query to load the Categories data.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuerycats()
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_categories AS a');

		return $query;
	}

	/**
	 * Returns a CSV file with Categories data
	 * @return boolean
	 */
	public function getCsvcats()
	{
		$this->populateState();

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();
		$header = array();
		$header = array_keys($db->getTableColumns('#__jem_categories'));
		fputcsv($csv, $header, ';');

		$items = $db->setQuery($this->getListQuerycats())
			->loadObjectList();

		foreach ($items as $lines) {
			foreach ($lines as &$line) {
				$line = mb_convert_encoding($line, 'Windows-1252', 'auto');
			}
			fputcsv($csv, (array) $lines, ';', '"');
		}

		return fclose($csv);
	}


	/**
	 * Build an SQL query to load the Venues data.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQueryvenues()
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_venues AS a');

		return $query;
	}

	/**
	 * Returns a CSV file with Venues data
	 * @return boolean
	 */
	public function getCsvvenues()
	{
		$this->populateState();

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();
		$header = array();
		$header = array_keys($db->getTableColumns('#__jem_venues'));
		fputcsv($csv, $header, ';');

		$items = $db->setQuery($this->getListQueryvenues())
			->loadObjectList();

		foreach ($items as $lines) {
			foreach ($lines as &$line) {
				$line = mb_convert_encoding($line, 'Windows-1252', 'auto');
			}
			fputcsv($csv, (array) $lines, ';', '"');
		}

		return fclose($csv);
	}


	/**
	 * Build an SQL query to load the Cats/Events data.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuerycatsevents()
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_cats_event_relations AS a');

		return $query;
	}

	/**
	 * Returns a CSV file with Cats/Events data
	 * @return boolean
	 */
	public function getCsvcatsevents()
	{
		$this->populateState();

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();
		$header = array();
		$header = array_keys($db->getTableColumns('#__jem_cats_event_relations'));
		fputcsv($csv, $header, ';');

		$items = $db->setQuery($this->getListQuerycatsevents())
			->loadObjectList();

		foreach ($items as $lines) {
			foreach ($lines as &$line) {
				$line = mb_convert_encoding($line, 'Windows-1252', 'auto');
			}
			fputcsv($csv, (array) $lines, ';', '"');
		}

		return fclose($csv);
	}

	/**
	 * logic to get the categories
	 *
	 * @return void
	 */
	public function getCategories()
	{
		// @todo alter function
		$user = JFactory::getUser();
		$jemsettings = JEMHelper::config();
		$userid = (int) $user->get('id');
		$superuser = JEMUser::superuser();
		$db = JFactory::getDBO();

		$where = ' WHERE c.published = 1';

		$query = 'SELECT c.* FROM #__jem_categories AS c' . $where . ' ORDER BY parent_id, c.ordering';
		$db->setQuery($query);

		$mitems = $db->loadObjectList();

		// Check for a database error.
		if ($db->getErrorNum()){
			JError::raiseNotice(500, $db->getErrorMsg());
		}

		if (!$mitems){
			$mitems = array();
			$children = array();

			$parentid = $mitems;
		}else{

		$mitems_temp = $mitems;

		$children = array();
		// First pass - collect children
		foreach ($mitems as $v){
			$pt = $v->parent_id;
			$list = @$children[$pt] ? $children[$pt] : array();
			array_push($list, $v);
			$children[$pt] = $list;
		}

		$parentid = intval($mitems[0]->parent_id);
		}

		//get list of the items
		$list = JEMCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		return $list;
	}

	/**
	 * Get Cat ID for a specific event
	 * @param unknown $id event id
	 * @return Ambigous <boolean, string>
	 */
	function getCatEvent($id)
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('catid');
		$query->from('#__jem_cats_event_relations');
		$query->where('itemid = ' . $id);

		$db->setQuery($query);
		$catidlist = $db->loadObjectList();

		if (count($catidlist)) {
			$catidarray;
			foreach ($catidlist as $obj) {
				$catidarray[] = $obj->catid;
			}

			$catids = implode(',', $catidarray);
		} else {
			$catids = false;
		}

		return $catids;
	}
}
