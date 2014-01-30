<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Import Model
 * @package JEM
 */
class JEMModelImport extends JModelLegacy {

	private $prefix = "#__";

	/**
	 * Constructor
	 */
	function __construct() {
		$jinput = JFactory::getApplication()->input;
		$this->prefix = $jinput->get('prefix', '#__', 'CMD');
		if($this->prefix == "") {
			$this->prefix = '#__';
		}

		parent::__construct();
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
	* Get the table fields of the venues table
	*
	* @return  array  An array with the fields of the venues table
	*/
	function getVenueFields() {
		return $this->getFields('#__jem_venues');
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
		return $this->import('Event', 'JEMTable', $fieldsname, $data, $replace);
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
		return $this->import('Category', 'JEMTable', $fieldsname, $data, $replace);
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
		return $this->import('jem_cats_event_relations', '', $fieldsname, $data, $replace);
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
		return $this->import('Venue', 'JEMTable', $fieldsname, $data, $replace);
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
		$db = JFactory::getDbo();

		$ignore = array();
		if (!$replace) {
			$ignore[] = 'id';
		}
		$rec = array(
				'added' => 0,
				'updated' => 0,
				'ignored' => 0
		);

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

			if ($objectname == "JEMTableCategory") {

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

			// check/store function for the Category Table
			if ($objectname == "JEMTableCategory") {
				// Make sure the data is valid
				if (!$object->checkCsvImport()) {
					$this->setError($object->getError());
					echo JText::_('COM_JEM_IMPORT_ERROR_CHECK') . $object->getError() . "\n";
					continue;
				}

				// Store it in the db
				if ($replace) {

					if ($values['id'] != '1' && $objectname == "JEMTableCategory") {
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
					echo JText::_('COM_JEM_IMPORT_ERROR_CHECK') . $object->getError() . "\n";
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

		if ($objectname == "JEMTableEvent") {
			// we need to update the categories-events table too
			// store cat relation
			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_cats_event_relations'));
			$query->where('itemid = '.$object->id);
			$db->setQuery($query);
			$db->query();
				if (isset($values['categories'])) {
					$cats = explode(',', $values['categories']);
					if (count($cats)) {
						foreach($cats as $cat)
						{
							$db = JFactory::getDbo();
							$query = $db->getQuery(true);
							$columns = array('catid', 'itemid');
							$values = array($cat, $object->id);
							$query
							->insert($db->quoteName('#__jem_cats_event_relations'))
							->columns($db->quoteName($columns))
							->values(implode(',', $values));
							$db->setQuery($query);
							$db->query();
						}
					}
				}
			}
		}

		// Specific actions outside the foreach loop

		if ($objectname == "JEMTableCategory") {
			$object->rebuild();
		}

		if ($objectname == "JEMTableEvent") {
			// force the cleanup to update the imported events status
			$settings = JTable::getInstance('Settings', 'JEMTable');
			$settings->load(1);
			$settings->lastupdate = 0;
			$settings->store();
		}

		return $rec;
	}

	/**
	 * Detect an installation of Eventlist.
	 *
	 * @return string  The version string of the detected Eventlist component or false
	 */
	public function getEventlistVersion() {
		jimport( 'joomla.registry.registry' );

		$db = $this->_db;
		$query = $db->getQuery('true');
		$query->select("manifest_cache")
		->from("#__extensions")
		->where("type='component' AND (name='eventlist' AND element='com_eventlist')");

		$db->setQuery($query);

		$result = $db->loadObject();

		// Eventlist not found in extension table
		if(is_null($result)) {
			return false;
		}

		$par = $result->manifest_cache;
		$params = new JRegistry;
		$params->loadJSON($par);

		return $params->get('version', null);
	}

	/**
	 * Returns a list of Eventlist data tables and the number of rows or null
	 * if the table does not exist
	 * @return array The list of tables
	 */
	public function getEventlistTablesCount() {
		$tables = array("eventlist_categories" => "",
			"eventlist_events" => "",
			"eventlist_groupmembers" => "",
			"eventlist_groups" => "",
			"eventlist_register" => "",
			"eventlist_venues" => "");

		return $this->getTablesCount($tables);
	}

	/**
	 * Returns a list of JEM data tables and the number of rows or null if the
	 * table does not exist
	 * @return array The list of tables
	 */
	public function getJemTablesCount() {
		$tables = array("jem_attachments" => "",
				"jem_categories" => "",
				"jem_cats_event_relations" => "",
				"jem_events" => "",
				"jem_groupmembers" => "",
				"jem_groups" => "",
				"jem_register" => "",
				"jem_venues" => "");

		return $this->getTablesCount($tables);
	}

	/**
	 * Returns a list of tables and the number of rows or null if the
	 * table does not exist
	 * @param $tables  An array of table names without prefix
	 * @return array The list of tables
	 */
	public function getTablesCount($tables) {
		$db = $this->_db;

		foreach ($tables as $table => $value) {
			$query = $db->getQuery('true');

			$query->select("COUNT(*)")
				->from($this->prefix.$table);

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
		$tablesCount = $this->getTablesCount($tables);
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
		$db = $this->_db;
		$query = $db->getQuery('true');

		$query->select("*")
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
	public function transformEventlistData($tablename, &$data) {
		// categories
		if($tablename == "categories") {
			foreach($data as $row) {
				// JEM now has a root category, so we shift IDs by 1
				$row->id++;
				$row->parent_id++;

				// Description field has been renamed
				if($row->catdescription) {
					$row->description = $row->catdescription;
				}
			}
		}

		// cats_event_relations
		if($tablename == "cats_event_relations") {
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

		// events
		if($tablename == "events") {
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

		// groupmembers
		// groups

		// register
		if($tablename == "register") {
			foreach($data as $row) {
				// Check if uip contains crap
				if(strpos($row->uip, "COM_EVENTLIST") === 0) {
					$row->uip = "";
				}
			}
		}

		// venues
		if($tablename == "venues") {
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
	 * Saves the data to the database
	 * @param string $tablename  The name of the table
	 * @param array $data  The data to save
	 */
	public function storeJemData($tablename, &$data) {
		$replace = true;
		if($tablename == "jem_groupmembers" || $tablename == "jem_cats_event_relations") {
			$replace = false;
		}

		$ignore = array ();
//		if (!$replace) {
//			$ignore[] = 'id';
// 		}
		$rec = array ('added' => 0, 'updated' => 0, 'error' => 0);

		foreach($data as $row) {
			$object = JTable::getInstance($tablename, '');
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
		$tablesCount = $this->getJemTablesCount();

		foreach($tablesCount as $tableCount) {
			if($tableCount !== null && $tableCount > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copies the Eventlist images to JEM folder
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
}
?>