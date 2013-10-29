<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * JEM Component Export Model
 *
 **/
class JEMModelExport extends JModelList {
	/**
	* Constructor.
	*
	* @param array An optional associative array of configuration settings.
	* @see JController
	*
	*/
	public function __construct($config = array()) {
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'id', 'a.id',
			);
		}

		parent::__construct($config);
	}

	/**
	* Method to auto-populate the model state.
	*
	* Note. Calling getState in this method will result in recursion.
	*/
	protected function populateState($ordering = null, $direction = null) {
		// Initialise variables.
		$app = JApplication::getInstance('administrator');

		// Load the filter state.
		$filter_form_type = $app->getUserStateFromRequest($this->context.'.filter.form_type', 'filter_form_type');
		$this->setState('filter.form_type', $filter_form_type);

		$filter_start_date = $app->getUserStateFromRequest($this->context.'.filter.start_date', 'filter_start_date');
		$this->setState('filter.start_date', $filter_start_date);

		$filter_end_date = $app->getUserStateFromRequest($this->context.'.filter.end_date', 'filter_end_date');
		$this->setState('filter.end_date', $filter_end_date);

		// Load the parameters.
		$params = JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('a.first_name', 'asc');
	}


	/********
	* Events
	********/

	/**
	* Build an SQL query to load the list data.
	* @return JDatabaseQuery
	*/
	protected function getListQuery() {
		// @todo: add category hr+export

		// Retrieve variables
		$jinput 	= JFactory::getApplication()->input;
		$startdate 	= $jinput->get('dates','','string');
		$enddate 	= $jinput->get('enddates','','string');
		$cats 		= $jinput->get('cid', array(), 'post', 'array');

		// Create a new query object.
		$db 		= $this->getDbo();
		$query 		= $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('`#__jem_events` AS a');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		// check if startdate + enddate are set.
		if (!empty($startdate) && !empty($enddate))
		{
		$query->where('DATEDIFF(IF (a.enddates IS NOT NULL AND a.enddates <> '. $db->Quote('0000-00-00') .', a.enddates, a.dates), "'. $startdate .'") >= 0');
		$query->where('DATEDIFF(a.dates, "'. $enddate .'") <= 0');
		}

		// check if specific category's have been selected
		if (!empty($cats)) {
			$query->where('  (c.id=' . implode(' OR c.id=', $cats) . ')');
		}

		// Group the query
		$query->group('a.id');

		return $query;
	}


	public function getCsv() {
		$this->populateState();

		$jinput = JFactory::getApplication()->input;
		$includecategories = $jinput->get('categorycolumn', 0, 'int');

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();

		if ($includecategories == 1)
		{
		$header = array();
		$events = array_keys($db->getTableColumns('#__jem_events'));
		$categories = array();
		$categories[] = "categories";
		$header = array_merge($events,$categories);

		fputcsv($csv, $header, ';');

		$query = $this->getListQuery();
		$items = $this->_getList($query);

		foreach($items AS $item) {
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

	/********
	* Categories
	********/

	/**
	* Build an SQL query to load the list data.
	* @return JDatabaseQuery
	*/
	protected function getListQuerycats() {
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_categories AS a');

		return $query;
	}

	public function getCsvcats() {
		$this->populateState();

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();
		$header = array();
		$header = array_keys($db->getTableColumns('#__jem_categories'));
		fputcsv($csv, $header, ';');

		$items = $db->setQuery($this->getListQuerycats())->loadObjectList();

		foreach ($items as $lines) {
			foreach ($lines as &$line) {
				$line = mb_convert_encoding($line, 'Windows-1252', 'auto');
			}
			fputcsv($csv, (array) $lines, ';', '"');
		}

		return fclose($csv);
	}


	/********
	* Venues
	********/

	/**
	* Build an SQL query to load the list data.
	* @return JDatabaseQuery
	*/
	protected function getListQueryvenues() {
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_venues AS a');

		return $query;
	}

	public function getCsvvenues() {
		$this->populateState();

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();
		$header = array();
		$header = array_keys($db->getTableColumns('#__jem_venues'));
		fputcsv($csv, $header, ';');

		$items = $db->setQuery($this->getListQueryvenues())->loadObjectList();

		foreach ($items as $lines) {
			foreach ($lines as &$line) {
				$line = mb_convert_encoding($line, 'Windows-1252', 'auto');
			}
			fputcsv($csv, (array) $lines, ';', '"');
		}

		return fclose($csv);
	}

	/********
	* CATS_EVENTS
	********/

	/**
	* Build an SQL query to load the list data.
	* @return JDatabaseQuery
	*/
	protected function getListQuerycatsevents() {
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('a.*');
		$query->from('#__jem_cats_event_relations AS a');

		return $query;
	}

	public function getCsvcatsevents() {
		$this->populateState();

		$csv = fopen('php://output', 'w');
		$db = $this->getDbo();
		$header = array();
		$header = array_keys($db->getTableColumns('#__jem_cats_event_relations'));
		fputcsv($csv, $header, ';');

		$items = $db->setQuery($this->getListQuerycatsevents())->loadObjectList();

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
	 * @access public
	 * @return void
	 */
	function getCategories()
	{
		// @todo alter function

		$user		= JFactory::getUser();
		$jemsettings = JEMHelper::config();
		$userid		= (int) $user->get('id');
		$superuser	= JEMUser::superuser();

		$gid = JEMHelper::getGID($user);

		$where = ' WHERE c.published = 1 AND c.access <= '.$gid;

		//only check for maintainers if we don't have an edit action
		//if(!$this->_id) {
			//get the ids of the categories the user maintaines
		$query = 'SELECT g.group_id'
				. ' FROM #__jem_groupmembers AS g'
				. ' WHERE g.member = '.$userid
				;
		$this->_db->setQuery( $query );
		$catids = $this->_db->loadColumn();

		$categories = implode(' OR c.groupid = ', $catids);

			//build ids query
		if ($categories) {
			//check if user is allowed to submit events in general, if yes allow to submit into categories
			//which aren't assigned to a group. Otherwise restrict submission into maintained categories only
			if (JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes)) {
				$where .= ' AND c.groupid = 0 OR c.groupid = '.$categories;
			} else {
				$where .= ' AND c.groupid = '.$categories;
					}
			} else {
				$where .= ' AND c.groupid = 0';
					}

		//}

		//administrators or superadministrators have access to all categories, also maintained ones
		if($superuser) {
			$where = ' WHERE c.published = 1';
		}

		//get the maintained categories and the categories whithout any group
		//or just get all if somebody have edit rights
		$query = 'SELECT c.*'
				. ' FROM #__jem_categories AS c'
				. $where
				. ' ORDER BY c.ordering'
				;
		$this->_db->setQuery( $query );

		//	$this->_category = array();
		//	$this->_category[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_SELECT_CATEGORY' ) );
		//	$this->_categories = array_merge( $this->_category, $this->_db->loadObjectList() );

		$rows = $this->_db->loadObjectList();

		//set depth limit
		$levellimit = 10;

		//get children
		$children = array();
			foreach ($rows as $child) {
				$parent = $child->parent_id;
				$list = @$children[$parent] ? $children[$parent] : array();
				array_push($list, $child);
				$children[$parent] = $list;
				}
		//get list of the items
		$this->_categories = JEMCategories::treerecurse(0, '', array(), $children, true, max(0, $levellimit-1));

		return $this->_categories;
	}


	function getCatEvent($catid) {

		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select('catid');
		$query->from('#__jem_cats_event_relations');
		$query->where('itemid = '.$catid);

		$db->setQuery($query);
		$catidlist = $db->loadObjectList();

		if (count($catidlist)) {

			$catidarray;
			foreach($catidlist as $obj){
				$catidarray[] = $obj->catid;
			}

			$catids = implode(',',$catidarray);
		} else
		{
			$catids = false;
		}

		return $catids;
	}





}
