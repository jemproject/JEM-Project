<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Import Model
 *
 * @package JEM
 *
 */
class JEMModelImport extends JModelLegacy {
	/**
	 * Constructor
	 *
	 */
	function __construct() {
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
	 *
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
		return $this->import('jem_events', $fieldsname, $data, $replace);
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
		return $this->import('jem_categories', $fieldsname, $data, $replace);
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
		return $this->import('jem_cats_event_relations', $fieldsname, $data, $replace);
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
		return $this->import('jem_venues', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param   string  $tablename  Name of the table where to add the data
	 * @param   array   $fieldsname  Name of the fields
	 * @param   array  $data  The records
	 * @param   boolean  $replace Replace if ID already exists
	 *
	 * @return  array  Number of records inserted and updated
	 */
	private function import($tablename, $fieldsname, & $data, $replace = true) {
		$ignore = array ();
		if (!$replace) {
			$ignore[] = 'id';
		}
		$rec = array ('added' => 0, 'updated' => 0);

		// parse each row
		foreach ($data AS $row) {
			$values = array ();
			// parse each specified field and retrieve corresponding value for the record
			foreach ($fieldsname AS $k => $field) {
				$values[$field] = $row[$k];
			}

			$object = JTable::getInstance($tablename, '');

			$object->bind($values, $ignore);

			// Make sure the data is valid
			if (!$object->check()) {
				$this->setError($object->getError());
				echo JText::_('Error check: ').$object->getError()."\n";
				continue ;
			}

			// Store it in the db
			if ($replace) {
				// We want to keep id from database so first we try to insert into database. if it fails,
				// it means the record already exists, we can use store().
				if (!$object->insertIgnore()) {
					if (!$object->store()) {
						echo JText::_('Error store: ').$this->_db->getErrorMsg()."\n";
						continue ;
					} else {
						$rec['updated']++;
					}
				} else {
					$rec['added']++;
				}
			} else {
				if (!$object->store()) {
					echo JText::_('Error store: ').$this->_db->getErrorMsg()."\n";
					continue ;
				} else {
					$rec['added']++;
				}
			}

			if($tablename == "jem_events") {
				// we need to update the categories-events table too
				// store cat relation
				$query = 'DELETE FROM #__jem_cats_event_relations WHERE itemid = '.$object->id;
				$this->_db->setQuery($query);
				$this->_db->query();

				if ( isset ($values['categories'])) {
					$cats = explode(',', $values['categories']);
					if (count($cats)) {
						foreach ($cats as $cat) {
							$query = 'INSERT INTO #__jem_cats_event_relations (`catid`, `itemid`) VALUES('.$cat.','.$object->id.')';
							$this->_db->setQuery($query);
							$this->_db->query();
						}
					}
				}
			}
		}

		if($tablename == "jem_events") {
			// force the cleanup to update the imported events status
			$settings = JTable::getInstance('jem_settings', '');
			$settings->load(1);
			$settings->lastupdate = 0;
			$settings->store();
		}

		return $rec;
	}

	/**
	 * Detect an installation of Eventlist.
	 *
	 * @return string  The version string of the detected Eventlist component or null
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
			return null;
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
		$tables = array("#__eventlist_categories" => "",
			"#__eventlist_events" => "",
			"#__eventlist_groupmembers" => "",
			"#__eventlist_groups" => "",
			"#__eventlist_register" => "",
			"#__eventlist_venues" => "");

		return $this->getTablesCount($tables);
	}

	/**
	 * Returns a list of JEM data tables and the number of rows or null if the
	 * table does not exist
	 * @return array The list of tables
	 */
	public function getJemTablesCount() {
		$tables = array("#__jem_attachments" => "",
				"#__jem_categories" => "",
				"#__jem_cats_event_relations" => "",
				"#__jem_events" => "",
				"#__jem_groupmembers" => "",
				"#__jem_groups" => "",
				"#__jem_register" => "",
				"#__jem_venues" => "");

		return $this->getTablesCount($tables);
	}

	/**
	 * Returns a list of tables and the number of rows or null if the
	 * table does not exist
	 * @param $tables  An array of table names
	 * @return array The list of tables
	 */
	public function getTablesCount($tables) {
		$db = $this->_db;

		foreach ($tables as $table => $value) {
			$query = $db->getQuery('true');

			$query->select("COUNT(*)")
			->from($table);

			$db->setQuery($query);

			// Set legacy to false to be able to catch DB errors.
			$legacyValue = JError::$legacy;
			JError::$legacy = false;

			try {
				$tables[$table] = $db->loadResult();
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
	 * @param $table  The name of the table
	 * @return mixed  The number of rows or null
	 */
	public function getTableCount($table) {
		$tables = array($table => "");
		$tablesCount = $this->getTablesCount($tables);
		return $tablesCount[$table];
	}


	/**
	 * Returns the data of a table
	 * @param string $tablename  The name of the table
	 * @param int $limitStart  The limit start of the query
	 * @param int $limit  The limit of the query
	 */
	public function getEventlistData($tablename, $limitStart = null, $limit = null) {
		$db = $this->_db;
		$query = $db->getQuery('true');

		$query->select("*")
			->from($tablename)
			;

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
	 */
	public function transformEventlistData($tablename, &$data) {
		// categories

		// cats_event_relations
		if($tablename == "cats_event_relations") {
			$dataNew = array();
			foreach($data as $row) {
				// Category-event relations is now stored in seperate table
				$rowNew = new stdClass();
				$rowNew->catid = $row->catsid;
				$rowNew->itemid = $row->id;
				$rowNew->ordering = 0;

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
		$replace = false;

		$ignore = array ();
		if (!$replace) {
			$ignore[] = 'id';
		}
		$rec = array ('added' => 0, 'updated' => 0);

		foreach($data as $row) {
			$object = JTable::getInstance($tablename, '');
			$object->bind($row, $ignore);

			// Make sure the data is valid
			if (!$object->check()) {
				$this->setError($object->getError());
				echo JText::_('Error check: ').$object->getError()."\n";
				continue ;
			}

			// Store it in the db
			if ($replace) {
				// We want to keep id from database so first we try to insert into database. if it fails,
				// it means the record already exists, we can use store().
				if (!$object->insertIgnore()) {
					if (!$object->store()) {
						echo JText::_('Error store: ').$this->_db->getErrorMsg()."\n";
						continue ;
					} else {
						$rec['updated']++;
					}
				} else {
					$rec['added']++;
				}
			} else {
				if (!$object->store()) {
					echo JText::_('Error store: ').$this->_db->getErrorMsg()."\n";
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
}
?>
