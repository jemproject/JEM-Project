<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * Model: Import
 */
class JEMModelImport extends JModelLegacy
{
	private $prefix 	= "#__";
	protected static $version = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$jinput 		= JFactory::getApplication()->input;

		$this->prefix	= $jinput->getCmd('prefix', '#__');
        if ($this->prefix == "") {
			$this->prefix = '#__';
		}

		parent::__construct();
	}

	/**
	 * Get the table fields of the attachments table
	 *
	 * @return  array  An array with the fields of the attachments table
	 */
	function getAttachmentsFields() {
		return $this->getFields('#__jem_attachments');
	}

	/**
	 * Get the table fields of the categories table
	 *
	 * @return  array  An array with the fields of the categories table
	 */
	function getCategoryFields() {
		return $this->getFields('#__jem_categories');
	}

	/**
	 * Get the table fields of the cats_event_relations table
	 *
	 * @return  array  An array with the fields of the cats_event_relations table
	 */
	function getCateventsFields() {
		return $this->getFields('#__jem_cats_event_relations');
	}

	/**
	 * Get the table fields of the dates table
	 *
	 * @return  array  An array with the fields of the dates table
	 */
	function getDatesFields() {
		return $this->getFields('#__jem_dates');
	}

	/**
	 * Get the table fields of the events table
	 *
	 * @return  array  An array with the fields of the events table
	 */
	function getEventFields() {
		return $this->getFields('#__jem_events');
	}

	/**
	 * Get the table fields of the groups table
	 *
	 * @return  array  An array with the fields of the groups table
	 */
	function getGroupsFields() {
		return $this->getFields('#__jem_groups');
	}

	/**
	 * Get the table fields of the recurrence_master table
	 *
	 * @return  array  An array with the fields of the recurrence_master table
	 */
	function getRecurrenceMasterFields() {
		return $this->getFields('#__jem_recurrence_master');
	}

	/**
	 * Get the table fields of the recurrence table
	 *
	 * @return  array  An array with the fields of the recurrence table
	 */
	function getRecurrenceFields() {
		return $this->getFields('#__jem_recurrence');
	}

	/**
	 * Get the table fields of the register table
	 *
	 * @return  array  An array with the fields of the register table
	 */
	function getRegisterFields() {
		return $this->getFields('#__jem_register');
	}

	/**
	* Get the table fields of the venues table
	*
	* @return  array  An array with the fields of the venues table
	*/
	function getVenueFields() {
		return $this->getFields('#__jem_venues');
	}

	/**
	 * Helper function to return table fields of a given table
	 *
	 * @param   string  $tablename  The name of the table we want to get fields from

	 * @return  array  An array with the fields of the table
	 */
	private function getFields($tablename) {
		return array_keys($this->_db->getTableColumns($tablename));
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param   array   $fieldsname  Name of the fields
	 * @param   array  $data  The records
	 * @param   boolean  $replace Replace if ID already exists
	 *
	 * @return  array  Number of records inserted and updated
	 */
	function eventsimport($fieldsname, & $data, $replace = true) {
		return $this->import('Events', 'JEMTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param   array   $fieldsname  Name of the fields
	 * @param   array  $data  The records
	 * @param   boolean  $replace Replace if ID already exists
	 *
	 * @return  array  Number of records inserted and updated
	 */
	function categoriesimport($fieldsname, & $data, $replace = true) {
		return $this->import('Categories', 'JEMTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param   array   $fieldsname  Name of the fields
	 * @param   array  $data  The records
	 * @param   boolean  $replace Replace if ID already exists
	 *
	 * @return  array  Number of records inserted and updated
	 */
	function cateventsimport($fieldsname, & $data, $replace = true) {
		return $this->import('Cats_event_relations', 'JEMTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param   array   $fieldsname  Name of the fields
	 * @param   array  $data  The records
	 * @param   boolean  $replace Replace if ID already exists
	 *
	 * @return  array  Number of records inserted and updated
	 */
	function venuesimport($fieldsname, & $data, $replace = true) {
		return $this->import('Venues', 'JEMTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param string $tablename Name of the table where to add the data
	 * @param array $fieldsname Name of the fields
	 * @param array $data The records
	 * @param boolean $replace Replace if ID already exists
	 *
	 * @return array Number of records inserted and updated
	 */
	private function import($tablename, $prefix, $fieldsname, & $data, $replace = true)
	{
		// cats_event_relations table requires different handling
		if ($tablename == 'Cats_event_relations') {
			$events = array();
			$itemidx = $catidx = $orderidx = false;

			foreach ($fieldsname as $k => $field) {
				switch ($field) {
				case 'itemid':   $itemidx  = $k; break;
				case 'catid':    $catidx   = $k; break;
				case 'ordering': $orderidx = $k; break;
				}
			}
			if (($itemidx === false) || ($catidx === false)) {
				echo JText::_('COM_JEM_IMPORT_PARSE_ERROR') . "\n";
				return $rec;
			}

			// parse each row
			foreach ($data as $row) {
				// collect categories for each event; we get array( itemid => array( catid => ordering ) )
				$events[$row[$itemidx]][$row[$catidx]] = ($orderidx !== false) ? $row[$orderidx] : 0;
			}

			// store data
			return $this->storeCatsEventRelations($events, $replace);
		}

		$db = JFactory::getDbo();

		$ignore = array();
		if (!$replace) {
			$ignore[] = 'id';
		}
		$rec = array('added' => 0, 'updated' => 0, 'ignored' => 0);
		$events = array(); // collects cat event relations

		// parse each row
		foreach ($data as $row) {

			$values = array();

			// parse each specified field and retrieve corresponding value for the record
			foreach ($fieldsname as $k => $field) {
				$values[$field] = $row[$k];
			}

			// retrieve the specified table
			$object = JTable::getInstance($tablename, $prefix);
			$objectname = get_class($object);
			$rootkey = $this->_rootkey();

			if ($objectname == "JemTableCategories") {
				// check if column "parent_id" exists
				if (array_key_exists('parent_id', $values)) {

					// when not in replace mode the parent_id is set to the rootkey
					if (!$replace){
						$values['parent_id'] = $rootkey;
					} else {
						// when replacing and value is null or 0 the rootkey is assigned
						if ($values['parent_id'] == null || $values['parent_id'] == 0) {
							$values['parent_id'] = $rootkey;
							$parentid = $values['parent_id'];
						} else {
						// in replace mode and value
							$parentid = $values['parent_id'];
						}
					}

				} else {
					// column parent_id is not detected
					$values['parent_id'] = $rootkey;
					$parentid = $values['parent_id'];
				}

				// check if column "alias" exists
				if (array_key_exists('alias', $values)) {
					if ($values['alias'] == 'root') {
						$values['alias'] = '';
					}
				}

				// check if column "lft" exists
				if (array_key_exists('lft', $values)) {
					if ($values['lft'] == '0') {
						$values['lft'] = '';
					}
				}
			}

			// Bind the data
			$object->bind($values, $ignore);

			if ($objectname == "JemTableCategories") {
				// Make sure the data is valid
				if (!$object->checkCsvImport()) {
					$this->setError($object->getError());
					echo JText::_('COM_JEM_IMPORT_ERROR_CHECK') . $object->getError() . "\n";
					continue;
				}

				// Store it in the db
				if ($replace) {

					if ($values['id'] != '1' && $objectname == "JemTableCategories") {
						// We want to keep id from database so first we try to insert into database.
						// if it fails, it means the record already exists, we can use store().
						if (!$object->insertIgnore()) {
							if (!$object->storeCsvImport()) {
								echo JText::_('COM_JEM_IMPORT_ERROR_STORE') . $this->_db->getErrorMsg() . "\n";
								continue;
							} else {
								$rec['updated']++;
							}
						} else {
							$rec['added']++;
						}
					} else {
						// category with id=1 detected but it's not added or updated
						$rec['ignored']++;
					}
				} else {
					if (!$object->storeCsvImport()) {
						echo JText::_('COM_JEM_IMPORT_ERROR_STORE') . $this->_db->getErrorMsg() . "\n";
						continue;
					} else {
						$rec['added']++;
					}
				}
			} else {

				// Check/Store of tables other then Category

				// Make sure the data is valid
				if (!$object->check()) {
					$this->setError($object->getError());
					continue;
				}

				// Store it in the db
				if ($replace) {
					// We want to keep id from database so first we try to insert into database.
					// if it fails, it means the record already exists, we can use store().
					if (!$object->insertIgnore()) {
						if (!$object->store()) {
							echo JText::_('COM_JEM_IMPORT_ERROR_STORE') . $this->_db->getErrorMsg() . "\n";
							continue;
						} else {
							$rec['updated']++;
						}
					} else {
						$rec['added']++;
					}
				} else {
					if (!$object->store()) {
						echo JText::_('COM_JEM_IMPORT_ERROR_STORE') . $this->_db->getErrorMsg() . "\n";
						continue;
					} else {
						$rec['added']++;
					}
				}
			}

			if ($objectname == "JemTableEvents") {
				// we need to update the categories-events table too
				// store cat relations
				// @todo alter
				if (isset($values['categories'])) {
					$cats = explode(',', $values['categories']);
					foreach ($cats as $cat) {
						// collect categories for each event; we get array( itemid => array( catid => 0 ) )
						$events[$object->id][$cat] = 0;
					}
				}

			}
		} // foreach

		##################################
		## actions outside foreach loop ##
		##################################

		if ($objectname == "JemTableCategories") {
			$object->rebuild();
		}

		if ($objectname == "JemTableEvents") {
			// store cat event relations
			if (!empty($events)) {
				$this->storeCatsEventRelations($events, $replace);
			}

			// force the cleanup to update the imported events status
			$settings = JTable::getInstance('Settings', 'JEMTable');
			$settings->load(1);
			$settings->lastupdate = 0;
			$settings->store();
		}

		return $rec;
	}

	/**
	 * Stores category event relations in cats_event_relations table
	 *
	 * @param array   $events  event ids with categories and ordering
	 *                         format: array( itemid => array( catid => ordering ) )
	 * @param boolean $replace Replace if event-cat pair already exists
	 *
	 * @return array Number of records inserted and updated
	 */
	private function storeCatsEventRelations(array $events, $replace = true)
	{
		$db = JFactory::getDbo();
		$columns = array('catid', 'itemid', 'ordering');
		$result = array('added' => 0, 'updated' => 0, 'ignored' => 0);

		// store data
		foreach ($events as $itemid => $cats) {
			// remove "old", unneeded relations of this event
			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_cats_event_relations'));
			$query->where($db->quoteName('itemid') . '=' . $db->quote($itemid));
			if ($replace && count($cats)) { // keep records we can update
				$query->where('NOT catid IN ('.implode(',',array_keys($cats)).')');
			}
			$db->setQuery($query);
			$db->execute();

			if (count($cats)) {
				$values = array();
				foreach($cats as $catid => $order)
				{
					if ($replace) { // search for record to update
						$query_id = $db->getQuery(true);
						$query_id->select($db->quoteName('id'));
						$query_id->from($db->quoteName('#__jem_cats_event_relations'));
						$query_id->where($db->quoteName('itemid') . '=' . $db->quote($itemid));
						$query_id->where($db->quoteName('catid') . '=' . $db->quote($catid));
						$db->setQuery($query_id);
						$id = $db->loadResult();
					} else { // all records are deleted so always insert new records
						$id = 0;
					}

					if ($id > 0) {
						// record found: update
						$query_upd = $db->getQuery(true);
						$query_upd->update($db->quoteName('#__jem_cats_event_relations'));
						$query_upd->set($db->quoteName('ordering') . '=' . $db->quote($order));
						$query_upd->where($db->quoteName('id') . '=' . $db->quote($id));
						$db->setQuery($query_upd);
						if ($db->execute()) {
							$result['updated']++;
						}
					} else {
						// not found: add new record
						$values[] = implode(',', array($catid, $itemid, $order));
					}
				}

				// some new records to add?
				if (count($values)) {
					$query = $db->getQuery(true);
					$query->insert($db->quoteName('#__jem_cats_event_relations'));
					$query->columns($db->quoteName($columns));
					$query->values($values);
					$db->setQuery($query);
					if ($db->execute()) {
						$result['added'] += count($values);
					}
				}
			}
		}

		return $result;
	}

	
	/**
	 * Detect an installation of Eventlist.
	 *
	 * @return string  The version string of the detected Eventlist component or false
	 */
	public function getEventlistVersion() {

		$db				= JFactory::getDbo();
		$secondCheck	= null;

		$prefix		= $this->prefix;
		$dbPrefix	= $db->getPrefix();

		if ($prefix == '#__' || $dbPrefix == $prefix) {
			$otherPrefix = 'false';
		} else {
			$otherPrefix = 'true';
		}

		$version = null;

		# are we checking for a version in current Joomla install?
		if ($otherPrefix == 'false' || $dbPrefix == $prefix) {
			# in case of an J25->J3 upgrade it can read the EL info from database
			jimport( 'joomla.registry.registry' );

			$db		= $this->_db;
			$query	= $db->getQuery('true');
			$query
			->select("manifest_cache")
			->from("#__extensions")
			->where("type='component' AND (name='eventlist' AND element='com_eventlist')");

			$db->setQuery($query);
			$result = $db->loadObject();

			// Eventlist not found in extension table
			if(is_null($result)) {
				$secondCheck = true;
			} else {
				$par = $result->manifest_cache;
				$params = new JRegistry;
				$params->loadString($par);

				$version = $params->get('version', null);
			}
		}

		if ($otherPrefix == 'true' || $secondCheck) {

			# here the prefix was filled so we'll check the tables with given prefix
			# if the EL info can't be retrieved from the manifest_cache we will look further
			# we take the provided prefix and will look if specific fields are in place, if so
			# then we can determine with what version we're dealing

			# do some checking, we do keep in mind that the whole component was installed
			# and by doing so we'll check for specific fields

			########################
			## Check for 1.0-1.02 ##
			########################

			# we'll see if the catsid is in the eventslist table, if so it's v1.0 or v1.02
			$query = $db->getQuery(true);
			$query->select('catsid');
			$query->from($this->prefix.'eventlist_events');
			$db->setQuery($query);

			# Set legacy to false to be able to catch DB errors.
			$legacyValue = JError::$legacy;
			JError::$legacy = false;

			try
			{
				$el10x = $db->loadResult();
				JError::$legacy = $legacyValue;
			}
			catch (Exception $e)
			{
				$el10x = false;
				JError::$legacy = $legacyValue;
			}

			if ($el10x == false) {

				##################
				## CHECK FOR 1.1 ##
				###################

				# now we'll check if it's v1.1

				$db = $this->_db;
				$query = $db->getQuery(true);
				$query = 'SHOW COLUMNS FROM '.$this->prefix.'eventlist_settings LIKE "ownedvenuesonly"';
				$db->setQuery($query);

				# Set legacy to false to be able to catch DB errors.
				$legacyValue = JError::$legacy;
				JError::$legacy = false;

				try {
					$el11x = $db->loadResult();
					JError::$legacy = $legacyValue;
				}
				catch (Exception $e) {
					$el11x = false;
					JError::$legacy = $legacyValue;
				}

				if ($el11x == false) {
					$version = null;
				} else {
					$version	=	'1.1.x';
				}

			} else {
				$version	=	'1.0/1.0.x';
			}
		}

		return $version;
	}

	/**
	 * Returns a list of Eventlist data tables and the number of rows or null
	 * if the table does not exist
	 * @return array The list of tables
	 */
	public function getEventlistTablesCount() {
		$tables = array(
			"eventlist_attachments" => "",
			"eventlist_categories" => "",
			"eventlist_cats_event_relations" => "",
			"eventlist_dates" => "",
			"eventlist_events" => "",
			"eventlist_groupmembers" => "",
			"eventlist_groups" => "",
			"eventlist_recurrence" => "",
			"eventlist_recurrence_master" => "",
			"eventlist_register" => "",
			"eventlist_venues" => "");

		return $this->getTablesCount($tables);
	}

	/**
	 * Returns a list of JEM data tables and the number of rows or null if the
	 * table does not exist
	 * @return array The list of tables
	 */
	public function getJemTablesCount($prefix = true) {
		$tables = array(
				"jem_attachments" => "",
				"jem_categories" => "",
				"jem_cats_event_relations" => "",
				"jem_dates" => "",
				"jem_events" => "",
				"jem_groupmembers" => "",
				"jem_groups" => "",
				"jem_recurrence" => "",
				"jem_recurrence_master" => "",
				"jem_register" => "",
				"jem_venues" => "");

		return $this->getTablesCount($tables,$prefix);
	}

	/**
	 * Returns a list of Eventlist data tables and the number of rows or null
	 * if the table does not exist
	 * @return array The list of tables
	 */
	public function EventlistTables($version,$imp=false) {

		$tables = array();

		if ($version == '1.0.2 Stable') {
			$tables = array(
				"eventlist_categories" => "",
				"eventlist_events" => "",
				"eventlist_groupmembers" => "",
				"eventlist_groups" => "",
				"eventlist_register" => "",
				"eventlist_venues" => "");
		}

		if ($version == '1.1.x') {
			$tables = array(
				"eventlist_attachments" => "",
				"eventlist_categories" => "",
				"eventlist_cats_event_relations" => "",
				"eventlist_events" => "",
				"eventlist_groupmembers" => "",
				"eventlist_groups" => "",
				"eventlist_register" => "",
				"eventlist_venues" => "");
		}

		if ($version == '1.0/1.0.x') {
			$tables = array(
				"eventlist_categories" => "",
				"eventlist_events" => "",
				"eventlist_groupmembers" => "",
				"eventlist_groups" => "",
				"eventlist_register" => "",
				"eventlist_venues" => "");
		}

		if ($imp) {

			$tablecount = $this->getTablesCount($tables,true);

			$tableimp = array();

			$tableFoundCount = 0;
			foreach($tablecount as $table => $rows) {
				if(!is_null($rows)) {
					$tableFoundCount++;
					$tableimp[] = $table;
				}
			}

			return $tableimp;

		} else {
			return $this->getTablesCount($tables,true);
		}
	}


	/**
	 * Returns a list of tables and the number of rows or null if the
	 * table does not exist
	 * @param $tables  An array of table names without prefix
	 * @return array The list of tables
	 */
	public function getTablesCount($tables,$prefix) {
		
		$db				= JFactory::getDbo();
		$dbPrefix		= $db->getPrefix();

		foreach ($tables as $table => $value) {
			$query = $db->getQuery('true');

			if ($prefix) {
				$query
					->select("COUNT(*)")
					->from($this->prefix.$table);
			} else {
				$query
				->select("COUNT(*)")
				->from($dbPrefix.$table);
			}

			$db->setQuery($query);

			// Set legacy to false to be able to catch DB errors.
			$legacyValue = JError::$legacy;
			JError::$legacy = false;

			try {
				$tables[$table] = $db->loadResult();
				// Don't count the root category
				if($table == "jem_categories") {
					$tables[$table]--;
				}
				JError::$legacy = $legacyValue;
			} catch (Exception $e) {
				$tables[$table] = null;
				JError::$legacy = $legacyValue;
			}
		}

		return $tables;
	}
	

	/**
	 * Returns the number of rows of a table or null if the table dies not exist
	 * @param $table  The name of the table without prefix
	 * @return mixed  The number of rows or null
	 */
	public function getTableCount($table) {
		$tables = array($table => "");
		$tablesCount = $this->getTablesCount($tables,true);
		return $tablesCount[$table];
	}

	
	/**
	 * Returns the data of a table
	 * @param string $tablename  The name of the table without prefix
	 * @param int $limitStart  The limit start of the query
	 * @param int $limit  The limit of the query
	 * @return array  The data
	 */
	public function getEventlistData($tablename, $limitStart = null, $limit = null) {
		$db		= $this->_db;
		$query	= $db->getQuery('true');

		$query
			->select("*")
			->from($this->prefix.$tablename);

		if($limitStart !== null && $limit !== null) {
			$db->setQuery($query, $limitStart, $limit);
		} else {
			$db->setQuery($query);
		}

		return $db->loadObjectList();
	}

	
	/**
	 * Changes old Eventlist data to fit the JEM standards
	 * @param string $tablename  The name of the table
	 * @param array $data  The data to work with
	 * @return array  The changed data
	 *
	 * @todo: increment catid when catid=1 exists.
	 */
	public function transformEventlistData($tablename, &$data,$version) {

		# in here we will pass the field-data of the table
		# and rearrange it a bit

		# categories
		if($tablename == "eventlist_categories") {
			foreach($data as $row) {
				// JEM now has a root category, so we shift IDs by 1
				$row->id++;
				$row->parent_id++;

				// Description field has been renamed
				if($row->catdescription) {
					$row->description = $row->catdescription;
				}

				if(empty($row->access)) {
					$row->access = '1';
				}
			}
		}

		# cats_event_relations
		if($tablename == "eventlist_cats_event_relations") {

			# check version
			if ($version == '1.1.x') {
				$dataNew = array();
				foreach($data as $row) {
					// JEM now has a root category, so we shift IDs by 1
					$rowNew = new stdClass();
					$rowNew->catid = $row->catid;
					$rowNew->itemid = $row->itemid;
					$rowNew->catid++;
					$dataNew[] = $rowNew;
				}
				return $dataNew;
			} else {
				$dataNew = array();
				foreach($data as $row) {
					// Category-event relations is now stored in seperate table
					$rowNew = new stdClass();
					$rowNew->catid = $row->catsid;
					$rowNew->itemid = $row->id;
					$rowNew->ordering = 0;

					// JEM now has a root category, so we shift IDs by 1
					$rowNew->catid++;

					$dataNew[] = $rowNew;
				}
				return $dataNew;
			}
		}

		# events
		if($tablename == "eventlist_events") {
			foreach($data as $row) {
				// No start date is now represented by a NULL value
				if($row->dates == "0000-00-00") {
					$row->dates = null;
				}
				// Recurrence fields have changed meaning
				if($row->recurrence_counter != "0000-00-00") {
					$row->recurrence_limit_date = $row->recurrence_counter;
				}
				$row->recurrence_counter = 0;
				// Published/state vaules have changed meaning
				if($row->published == -1) {
					$row->published = 2; // archive
				}
				// Check if author_ip contains crap
				if(strpos($row->author_ip, "COM_EVENTLIST") === 0) {
					$row->author_ip = "";
				}
				// Description field has been renamed
				if($row->datdescription) {
					$row->introtext = $row->datdescription;
				}
			}
		}

		# groupmembers
		# groups

		# register
		if($tablename == "eventlist_register") {
			foreach($data as $row) {
				// Check if uip contains crap
				if(strpos($row->uip, "COM_EVENTLIST") === 0) {
					$row->uip = "";
				}
			}
		}

		# venues
		if($tablename == "eventlist_venues") {
			foreach($data as $row) {
				// Column name has changed
				$row->postalCode = $row->plz;
				// Check if author_ip contains crap
				if(strpos($row->author_ip, "COM_EVENTLIST") === 0) {
					$row->author_ip = "";
				}
				// Country changes
				if($row->country == "AN") {
					$row->country = "NL"; // Netherlands Antilles to Netherlands
				}
			}
		}

		return $data;
	}


	/**
	 * Changes old Eventlist data to fit the JEM standards
	 * @param string $tablename  The name of the table
	 * @param array $data  The data to work with
	 * @return array  The changed data
	 *
	 * @todo: increment catid when catid=1 exists.
	 */
	public function transformJemTableData($tablename, &$data) {

		# in here we will pass the field-data of the table
		# and rearrange it a bit

		# categories
		if($tablename == "jem_categories") {};

		# cats_event_relations
		if($tablename == "jem_cats_event_relations") {};

		# events
		if($tablename == "jem_events") {};

		# groupmembers
		# groups

		# register
		if($tablename == "jem_register") {};

		# venues
		if($tablename == "jem_venues") {};

		return $data;
	}

	/**
	 * Saves the data to the database
	 * @param string $tablename  The name of the table
	 * @param array $data  The data to save
	 */
	public function storeTableData($tablename, &$data) {
		$replace = true;

		if (strpos($tablename, 'jem_') !== false) {
			$tablename = str_replace('jem_', '', $tablename);
		}

		if (strpos($tablename, 'eventlist') !== false) {
			$tablename = str_replace('eventlist_', '', $tablename);
		}

		$ignore = array ();
		//		if (!$replace) {
		//			$ignore[] = 'id';
		// 		}
		$rec = array ('added' => 0, 'updated' => 0, 'error' => 0);

		foreach($data as $row) {
			if (is_object($row)) {
				$row = get_object_vars($row);
			}

			$object = JTable::getInstance($tablename, 'JEMTable');
			$object->bind($row, $ignore);

			// Make sure the data is valid
			if (!$object->check()) {
				$this->setError($object->getError());
				echo JText::_('COM_JEM_IMPORT_ERROR_CHECK').$object->getError()."\n";
				continue ;
			}

			// Store it in the db
			if ($replace) {
				// We want to keep id from database so first we try to insert into database. if it fails,
				// it means the record already exists, we can use store().
				if (!$object->insertIgnore()) {
					if (!$object->store()) {
						echo JText::_('COM_JEM_IMPORT_ERROR_STORE').$this->_db->getErrorMsg()."\n";
						$rec['error']++;
						continue ;
					} else {
						$rec['updated']++;
					}
				} else {
					$rec['added']++;
				}
			} else {
				if (!$object->store()) {
					echo JText::_('COM_JEM_IMPORT_ERROR_STORE').$this->_db->getErrorMsg()."\n";
					$rec['error']++;
					continue ;
				} else {
					$rec['added']++;
				}
			}
		}
	}

	/**
	 * Returns true if the tables already contain JEM data
	 * @return boolean  True if data exists
	 */
	public function getExistingJemData() {
		$tablesCount = $this->getJemTablesCount(false);

		foreach($tablesCount as $tableCount) {
			if($tableCount !== null && $tableCount > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copies the EL-images to JEM folder
	 */
	public function copyImages() {
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$folders = array("categories", "events", "venues");

		// Add the thumbnail folders to the folders list
		foreach ($folders as $folder) {
			$folders[] = $folder."/small";
		}

		foreach ($folders as $folder) {
			$fromFolder = JPATH_SITE.'/images/eventlist/'.$folder.'/';
			$toFolder   = JPATH_SITE.'/images/jem/'.$folder.'/';

			if (JFolder::exists($fromFolder) && JFolder::exists($toFolder)) {
				$files = JFolder::files($fromFolder, null, false, false);

				foreach ($files as $file) {
					if(!JFile::exists($toFolder.$file)) {
						JFile::copy($fromFolder.$file, $toFolder.$file);
					}
				}
			}
		}
	}

	/**
	 * Get id of root-category
	 */
	private function _rootkey()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('c.id');
		$query->from('#__jem_categories AS c');
		$query->where('c.alias LIKE "root"');
		$db->setQuery($query);
		$key = $db->loadResult();

		// Check for DB error.
		if ($error = $db->getErrorMsg()) {
			JError::raiseWarning(500, $error);
			return false;
		}
		else {
			return $key;
		}
	}

	/**
	 * Copies the attachments to JEM folder
	 */
	public function copyAttachments() {
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		
		$result = false;
		
		// FOLDERS
		// Within EL1.1 we do have a setting for selecting the attachments folder but for now
		// let's consider the default option '/media/com_eventlist/attachments'
		$path = JPATH_SITE.'/media/com_eventlist/attachments';
		
		if (!JFolder::exists($path)) {
			return $result;
		}
		
		
		// PROCESS FOLDERS
		// Within JEM we can define a new attachment path but for now take the default
		$path_input = $path;
		$path_output = JPATH_SITE.'/media/com_jem/attachments';
		
		if (!JFolder::exists($path_output)) {
			return $result;
		}
		
		$recurse = true;
		$fullpath = true;
		$exclude = array();
		$excludefilter = array();
		$inputFolders	= JFolder::folders($path_input, $filter = '.', $recurse, $fullpath);
	
		$outputFolders	= array();
		foreach($inputFolders AS $inputfolder) {
				$outputFolder = str_replace('com_eventlist', 'com_jem', $inputfolder);
				if (!JFolder::exists($outputFolder)) {
					JFolder::create($outputFolder);
				}
		}
		

		// FILES
		// retrieve all files from attachment folder
		// in the previous steps we checked if the folders existed
		
		$recurse = true;
		$fullpath = true;
		$exclude = false;

		$inputFiles = JFolder::files($path_input, $filter = '.', $recurse, $fullpath);

		foreach($inputFiles AS $inputfile) {
				$outputFile	= str_replace('com_eventlist', 'com_jem', $inputfile);
			
				if(!JFile::exists($outputFile)) {
					JFile::copy($inputfile, $outputFile);
				}
		}
		
		$result = true;
		
		return $result;
	}
	
	
	function getVersion() {
		$app = JFactory::getApplication();
		$version = $app->getUserStateFromRequest("import.version", 'import.version');
		
		return $version;
	}
	
	function setVersion($version) {
		$app = JFactory::getApplication();
		$app->setUserState("import.version",$version);
		return true;
	}
}
