<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

jimport('joomla.application.component.modellist');

/**
 * JEM Component Export Model
 */
class JemModelExport extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param array An optional associative array of configuration settings.
	 * @see   JController
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
	 * @Note Calling getState in this method will result in recursion.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Load the filter state.
		$filter_form_type = $this->getUserStateFromRequest($this->context . '.filter.form_type', 'filter_form_type');
		$this->setState('filter.form_type', $filter_form_type);

		$filter_start_date = $this->getUserStateFromRequest($this->context . '.filter.start_date', 'filter_start_date');
		$this->setState('filter.start_date', $filter_start_date);

		$filter_end_date = $this->getUserStateFromRequest($this->context . '.filter.end_date', 'filter_end_date');
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
		$jinput    = Factory::getApplication()->input;
		$startdate = $jinput->get('dates', '', 'string');
		$enddate   = $jinput->get('enddates', '', 'string');
		$cats      = $jinput->get('cid', array(), 'array');

		// Create a new query object.
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_events AS a');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		// check if startdate and/or enddate are set.
		if (!empty($startdate)) {
			// note: open date is always after $startdate
			$query->where('((a.dates IS NULL) OR (DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $db->quote($startdate) . ') >= 0))');
		}
		if (!empty($enddate)) {
			// note: open date is before $enddate as long as $enddate is not before today
			$query->where('(((a.dates IS NULL) AND (DATEDIFF(CURDATE(), ' . $db->quote($enddate) . ') <= 0)) OR (DATEDIFF(a.dates, ' . $db->quote($enddate) . ') <= 0))');
		}

		// check if specific category's have been selected
		if (! empty($cats)) {
			\Joomla\Utilities\ArrayHelper::toInteger($cats);
			$query->where('  c.id IN (' . implode(',', $cats) . ')');
		}

		// Group the query
		$query->group('a.id');

		return $query;
	}

	/**
	 * Returns a CSV file with Events data
	 *
	 * @return boolean
	 */
	public function getCsv()
	{
		$this->populateState();

		$jinput = Factory::getApplication()->input;
		$includecategories = $jinput->get('categorycolumn', 0, 'int');

		$db  = Factory::getContainer()->get('DatabaseDriver');
		$jemconfig = JemConfig::getInstance()->toRegistry();
		$separator = $jemconfig->get('csv_separator', ';');
		$delimiter = $jemconfig->get('csv_delimiter', '"');
		$csv_bom   = $jemconfig->get('csv_bom', '1');
		$csv = fopen('php://output', 'w');
		if ($csv_bom ==1 ) {
			//add BOM to fix UTF-8 in Excel
			fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
		}

		if ($includecategories == 1) {
			$events = array_keys($db->getTableColumns('#__jem_events'));
			$categories = array();
			$categories[] = "categories";
			$header = array_merge($events, $categories);

			fputcsv($csv, $header, $separator, $delimiter);

			$query = $this->getListQuery();
			$items = $this->_getList($query);

			foreach ($items as $item) {
				$item->categories = $this->getCatEvent($item->id);
			}
		} else {
			$header = array_keys($db->getTableColumns('#__jem_events'));
			fputcsv($csv, $header, $separator, $delimiter);
			$query = $this->getListQuery();
			$items = $this->_getList($query);
		}

		foreach ($items as $lines) {
			fputcsv($csv, (array) $lines, $separator, $delimiter);
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
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_categories AS a');

		return $query;
	}

	/**
	 * Returns a CSV file with Categories data
	 *
	 * @return boolean
	 */
	public function getCsvcats()
	{
		$this->populateState();
		$jemconfig = JemConfig::getInstance()->toRegistry();
		$separator = $jemconfig->get('csv_separator', ';');
		$delimiter = $jemconfig->get('csv_delimiter', '"');
		$csv_bom   = $jemconfig->get('csv_bom', '1');
		$csv = fopen('php://output', 'w');
		if ($csv_bom ==1 ) {
			//add BOM to fix UTF-8 in Excel
			fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
		}
		$db = Factory::getContainer()->get('DatabaseDriver');
		$header = array_keys($db->getTableColumns('#__jem_categories'));
		fputcsv($csv, $header, $separator, $delimiter);

		$db->setQuery($this->getListQuerycats());
		$items = $db->loadObjectList();

		foreach ($items as $lines) {
			fputcsv($csv, (array) $lines, $separator, $delimiter);
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
		$db    = Factory::getContainer()->get('DatabaseDriver');
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
		$jemconfig = JemConfig::getInstance()->toRegistry();
		$separator = $jemconfig->get('csv_separator', ';');
		$delimiter = $jemconfig->get('csv_delimiter', '"');
		$csv_bom   = $jemconfig->get('csv_bom', '1');
		$csv = fopen('php://output', 'w');
		if ($csv_bom ==1 ) {
			//add BOM to fix UTF-8 in Excel
			fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
		}
		$db = Factory::getContainer()->get('DatabaseDriver');
		$header = array_keys($db->getTableColumns('#__jem_venues'));
		fputcsv($csv, $header, $separator, $delimiter);

		$db->setQuery($this->getListQueryvenues());
		$items = $db->loadObjectList();

		foreach ($items as $lines) {
			fputcsv($csv, (array) $lines, $separator, $delimiter);
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
		$db    = Factory::getContainer()->get('DatabaseDriver');
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
		$jemconfig = JemConfig::getInstance()->toRegistry();
		$separator = $jemconfig->get('csv_separator', ';');
		$delimiter = $jemconfig->get('csv_delimiter', '"');
		$csv_bom   = $jemconfig->get('csv_bom', '1');
		$csv = fopen('php://output', 'w');
		if ($csv_bom ==1 ) {
			//add BOM to fix UTF-8 in Excel
			fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
		}
		$db = Factory::getContainer()->get('DatabaseDriver');
		$header = array_keys($db->getTableColumns('#__jem_cats_event_relations'));
		fputcsv($csv, $header, $separator, $delimiter);

		$db->setQuery($this->getListQuerycatsevents());
		$items = $db->loadObjectList();

		foreach ($items as $lines) {
			fputcsv($csv, (array) $lines, $separator, $delimiter);
		}

		return fclose($csv);
	}

	/**
	 * logic to get the categories
	 */
	public function getCategories()
	{
		// @todo alter function

        $db = Factory::getContainer()->get('DatabaseDriver');
		$where = ' WHERE c.published = 1';
		$query = 'SELECT c.* FROM #__jem_categories AS c' . $where . ' ORDER BY parent_id, c.lft';
		

		// Check for a database error.
		// if ($db->getErrorNum()){
		// 	Factory::getApplication()->enqueueMessage($db->getErrorMsg(), 'notice');
		// }
		try
		{
			$db->setQuery($query);
			$mitems = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
		}

		if (!$mitems) {
			$children = array();
			$mitems   = array();
			$parentid = 0;
		} else {
			$children = array();
			// First pass - collect children
			foreach ($mitems as $v) {
				$pt = $v->parent_id;
				$list = isset($children[$pt]) ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}

			// list childs of "root" which has no parent and normally id 1
			$parentid = intval(isset($children[0][0]->id) ? $children[0][0]->id : 1);
		}

		//get list of the items
		$list = JemCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		return $list;
	}

	/**
	 * Get Category IDs for a specific event.
	 *
	 * @param  int $id event id
	 * @return string|boolean Comma separated list of ids on success or false otherwise.
	 */
	public function getCatEvent($id)
	{
		// Create a new query object.
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('catid');
		$query->from('#__jem_cats_event_relations');
		$query->where('itemid = ' . $db->quote($id));

		$db->setQuery($query);
		$catidlist = $db->loadObjectList();

		if (is_array($catidlist) && count($catidlist)) {
			$catidarray = array();
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
