<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filesystem\File;

jimport('joomla.application.component.model');

/**
 * JEM Component Import Model
 * @package JEM
 */
class JemModelImport extends BaseDatabaseModel
{
	/**
	 *  The table prefix of this site.
	 *  @var string
	 */
	private $jemprefix = '#__';

	/**
	 *  The prefix to use for the eventlist tables data should be imported from.
	 *  @var string
	 */
	private $elprefix = '#__';

	/**
	 *  Caches ids of all view access levels.
	 *  @var array
	 */
	protected static $_view_levels = null;

	/**
	 *  Caches ids of all users of this site.
	 *  @var array
	 */
	protected static $_user_ids = null;


	/**
	 * Constructor
	 */
	public function __construct()
	{
		$app = Factory::getApplication();
		$this->elprefix = $app->getUserStateFromRequest('com_jem.import.elimport.prefix', 'prefix', '#__', 'cmd');
		if ($this->elprefix == '') {
			$this->elprefix = '#__';
		}

		$this->jemprefix = '#__';

		parent::__construct();
	}

	/**
	 * Get the table fields of the events table
	 *
	 * @return array An array with the fields of the events table
	 */
	public function getEventFields()
	{
		return $this->getFields('#__jem_events');
	}

	/**
	* Get the table fields of the venues table
	*
	* @return array An array with the fields of the venues table
	*/
	public function getVenueFields()
	{
		return $this->getFields('#__jem_venues');
	}

	/**
	 * Get the table fields of the categories table
	 *
	 * @return array An array with the fields of the categories table
	 */
	public function getCategoryFields()
	{
		return $this->getFields('#__jem_categories');
	}

	/**
	 * Get the table fields of the cats_event_relations table
	 *
	 * @return array An array with the fields of the cats_event_relations table
	 */
	public function getCateventsFields()
	{
		return $this->getFields('#__jem_cats_event_relations');
	}

	/**
	 * Helper function to return table fields of a given table
	 *
	 * @param  string $tablename The name of the table we want to get fields from

	 * @return array An array with the fields of the table
	 */
	private function getFields($tablename)
	{
		return array_keys($this->_db->getTableColumns($tablename));
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param  array   $fieldsname Name of the fields
	 * @param  array   $data       The records
	 * @param  boolean $replace    Replace if ID already exists
	 *
	 * @return array   Number of records inserted and updated
	 */
	public function eventsimport($fieldsname, &$data, $replace = true)
	{
		return $this->import('Event', 'JemTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param  array   $fieldsname Name of the fields
	 * @param  array   $data       The records
	 * @param  boolean $replace    Replace if ID already exists
	 *
	 * @return array   Number of records inserted and updated
	 */
	public function categoriesimport($fieldsname, &$data, $replace = true)
	{
		return $this->import('Category', 'JemTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param  array   $fieldsname Name of the fields
	 * @param  array   $data       The records
	 * @param  boolean $replace    Replace if ID already exists
	 *
	 * @return array   Number of records inserted and updated
	 */
	public function cateventsimport($fieldsname, &$data, $replace = true)
	{
		return $this->import('jem_cats_event_relations', '', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param  array   $fieldsname Name of the fields
	 * @param  array   $data       The records
	 * @param  boolean $replace    Replace if ID already exists
	 *
	 * @return array   Number of records inserted and updated
	 */
	public function venuesimport($fieldsname, &$data, $replace = true)
	{
		return $this->import('Venue', 'JemTable', $fieldsname, $data, $replace);
	}

	/**
	 * Import data corresponding to fieldsname into events table
	 *
	 * @param  string  $tablename  Name of the table where to add the data
	 * @param  array   $fieldsname Name of the fields
	 * @param  array   $data       The records
	 * @param  boolean $replace    Replace if ID already exists
	 *
	 * @return array   Number of records inserted and updated
	 */
	private function import($tablename, $prefix, $fieldsname, &$data, $replace = true)
	{
		$rec = array('added' => 0, 'updated' => 0, 'ignored' => 0, 'ignoredids' => "" , 'duplicated' => 0, 'duplicatedids' => "", 'replaced' => 0, 'replacedids' => "", 'error' => 0, 'errorids' => "");

		// cats_event_relations table requires different handling
		if (strcasecmp($tablename, 'jem_cats_event_relations') == 0) {
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
				echo Text::_('COM_JEM_IMPORT_PARSE_ERROR') . "\n";
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

		$db = Factory::getContainer()->get('DatabaseDriver');

		// in case imported data has no key field explicitely add it with value 0 and don't try to replace
		$presetkey = in_array('id', $fieldsname) ? false : 'id';
		if (!empty($presetkey)) {
			$replace = false; // useless without imported key values
		}
		// we MUST reset key 'id' ourself
		$pk = $replace ? false : 'id';

		// retrieve the specified table
		$object = Table::getInstance($tablename, $prefix);
		$objectname = get_class($object);
		$rootkey = $this->_rootkey();

		$events = array(); // collects cat event relations

		// parse each row
		foreach ($data as $row) {
			$values = array();
			if ($presetkey) {
				$values[$presetkey] = 0;
			}
			// parse each specified field and retrieve corresponding value for the record
			foreach ($fieldsname as $k => $field) {
				$values[$field] = ($field !== $pk) ? $row[$k] : 0; // set key to given value or 0 depending on $replace
			}

			if (strcasecmp($objectname, 'JemTableCategory') == 0) {
				// check if column "parent_id" exists
				if (array_key_exists('parent_id', $values)) {
					// when not in replace mode the parent_id is set to the rootkey
					if (!$replace){
						$values['parent_id'] = $rootkey;
					} else {
						// when replacing and value is null or 0 the rootkey is assigned
						if ($values['parent_id'] == null || $values['parent_id'] == 0) {
							$values['parent_id'] = $rootkey;
							//$parentid = $values['parent_id'];
						} else {
						// in replace mode and value
							//$parentid = $values['parent_id'];
						}
					}
				} else {
					// column parent_id is not detected
					$values['parent_id'] = $rootkey;
					//$parentid = $values['parent_id'];
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
			} // if 'JemTableCategory'
		
			
			// Bind the data
			$object->reset(); // clear old data first - which does NOT reset 'id' !
			$object->bind($values);

			// check/store function for the Category Table
			if (strcasecmp($objectname, 'JemTableCategory') == 0) {
				// Make sure the data is valid
				if (!$object->checkCsvImport()) {
					$this->setError($object->getError());
					echo Text::_('COM_JEM_IMPORT_ERROR_CHECK') . $object->getError() . "\n";
					continue;
				}

				// Store it in the db
				if ($replace) {
					if ($values['id'] != '1') {
						// We want to keep id from database so first we try to insert into database.
						// if it fails, it means the record already exists, we can use store().
						$results = $object->insertIgnore();
						if ($results < 0) {
							if (!$object->storeCsvImport()) {
								echo Text::_('COM_JEM_IMPORT_ERROR_STORE') . $object->getError() . "\n";
								$rec['error']++;
								$rec['errorids'] .= ($rec['errorids']!=""?',':'') . $row[0];
								continue;
							} else {
								$rec['updated']++;
							}
						} else if( $result == 0) {
							$rec['duplicated']++;
							$rec['duplicatedids'] .= ($rec['duplicatedids']!=""?',':'') . $row[0];
						}else{
							$rec['added']++;
						}
					} else {
						// category with id=1 detected but it's not added or updated
						$rec['ignored']++;
						$rec['ignoredids'] .= ($rec['ignoredids']!=""?',':'') . $row[0];
					}
				} else {
					if (!$object->storeCsvImport()) {
						echo Text::_('COM_JEM_IMPORT_ERROR_STORE') . $object->getError() . "\n";
						$rec['error']++;
						$rec['errorids'] .= ($rec['errorids']!=""?',':'') . $row[0];
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
					echo Text::_('COM_JEM_IMPORT_ERROR_CHECK') . $object->getError() . "\n";
					$rec['error']++;
					$rec['errorids'] .= ($rec['errorids']!=""?',':'') . $row[0];
					continue;
				}

				// Store it in the db
				if ($replace) {
					// We want to keep id from database so first we try to insert into database.
					// if it fails, it means the record already exists, we can use store().
					if (!$object->insertIgnore()) {
						if (!$object->store()) {
							echo Text::_('COM_JEM_IMPORT_ERROR_STORE') . $object->getError() . "\n";
							$rec['error']++;
							$rec['errorids'] .= ($rec['errorids']!=""?',':'') . $row[0];
							continue;
						} else {
							$rec['updated']++;
						}
					} else {
						$rec['added']++;
					}
				} else {
					if (!$object->store()) {
						echo Text::_('COM_JEM_IMPORT_ERROR_STORE') . $object->getError() . "\n";
						$rec['error']++;
						$rec['errorids'] .= ($rec['errorids']!=""?',':'') . $row[0];
						continue;
					} else {
						$rec['added']++;
					}
				}
			}

			if (strcasecmp($objectname, 'JemTableEvent') == 0) {
				// we need to update the categories-events table too
				// store cat relations
				if (isset($values['categories'])) {
					$cats = explode(',', $values['categories']);
					foreach ($cats as $cat) {
						// collect categories for each event; we get array( itemid => array( catid => 0 ) )
						$events[$object->id][$cat] = 0;
					}
				}
			}
		} // foreach

		// Specific actions outside the foreach loop

		if (strcasecmp($objectname, 'JemTableCategory') == 0) {
			$object->rebuild();
		}

		if (strcasecmp($objectname, 'JemTableEvent') == 0) {
			// store cat event relations
			if (!empty($events)) {
				$this->storeCatsEventRelations($events, $replace);
			}

			// force the cleanup to update the imported events status
			JemConfig::getInstance()->set('lastupdate', 0);
		}

		return $rec;
	}

	/**
	 * Stores category event relations in cats_event_relations table
	 *
	 * @param  array   $events  event ids with categories and ordering
	 *                          format: array(itemid => array(catid => ordering))
	 * @param  boolean $replace Replace if event-cat pair already exists
	 *
	 * @return array   Number of records inserted and updated
	 */
	private function storeCatsEventRelations(array $events, $replace = true)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$columns = array('catid', 'itemid', 'ordering');
		$result  = array('added' => 0, 'updated' => 0, 'ignored' => 0);

		// store data
		foreach ($events as $itemid => $cats) {
			// remove "old", unneeded relations of this event
			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_cats_event_relations'));
			$query->where($db->quoteName('itemid') . '=' . $db->quote($itemid));
			if ($replace && is_array($cats) && count($cats)) { // keep records we can update
				$query->where('NOT catid IN ('.implode(',', $db->quote(array_keys($cats))).')');
			}
			$db->setQuery($query);
			$db->execute();

			if (is_array($cats) && count($cats)) {
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
						if ($db->execute() !== false) {
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
					if ($db->execute() !== false) {
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
	 * @return string The version string of the detected Eventlist component or false
	 */
	public function getEventlistVersion()
	{
		jimport( 'joomla.registry.registry' );

		$db = $this->_db;
		$query = $db->getQuery('true');
		$query->select('manifest_cache')
		      ->from('#__extensions')
		      ->where("type='component' AND (name='eventlist' AND element='com_eventlist')");

		$db->setQuery($query);
		$result = $db->loadObject();

		// Eventlist not found in extension table
		if (is_null($result)) {
			return false;
		}

		try {
			$par = $result->manifest_cache;
			$params = new JRegistry;
			$params->loadString($par, 'JSON');
			return $params->get('version', false);
		}
		catch(Exception $e) {
			JemHelper::addLogEntry($e->getMessage(), __METHOD__, JLog::ERROR);
			return false;
		}
	}

	/**
	 * Returns a list of Eventlist data tables and the number of rows or null
	 * if the table does not exist.
	 *
	 * @return array The list of tables
	 */
	public function getEventlistTablesCount()
	{
		$tables = array(
			'eventlist_attachments' => '',
			'eventlist_categories' => '',
			'eventlist_cats_event_relations' => '',
			'eventlist_events' => '',
			'eventlist_groupmembers' => '',
			'eventlist_groups' => '',
			'eventlist_register' => '',
			'eventlist_venues' => '');

		return $this->getTablesCount($tables, $this->elprefix);
	}

	/**
	 * Returns a list of JEM data tables and the number of rows or null if the
	 * table does not exist.
	 *
	 * @return array The list of tables
	 */
	public function getJemTablesCount()
	{
		$tables = array(
				'jem_attachments' => '',
				'jem_categories' => '',
				'jem_cats_event_relations' => '',
				'jem_events' => '',
				'jem_groupmembers' => '',
				'jem_groups' => '',
				'jem_register' => '',
				'jem_venues' => '');

		return $this->getTablesCount($tables, $this->jemprefix);
	}

	/**
	 * Returns a list of tables and the number of rows or null if the
	 * table does not exist.
	 *
	 * @param  array  $tables An array of table names without prefix
	 * @param  string $prefix The table prefix (JEM and EL tables can have a different prefix!)
	 * @return array  The list of tables
	 */
	public function getTablesCount($tables, $prefix)
	{
		$db = $this->_db;
		$db_tables = $db->setQuery('SHOW TABLES')->loadColumn();
		$db_prefix = $db->getPrefix();
	
		foreach ($tables as $table => $value) {
			if(in_array($db_prefix.$table,$db_tables)){
				$query = $db->getQuery('true');
				
				$query->select('COUNT(*)')
					->from($db->quoteName($prefix.$table));

				$db->setQuery($query);

				try {
					$tables[$table] = $db->loadResult();
					// Don't count the root category
					if (strcasecmp($table, 'jem_categories') == 0) {
						$tables[$table];
					}
				} catch (Exception $e) {
					$tables[$table] = null;
				}
			}else{
				$tables[$table] = null;
			}

		}

		return $tables;
	}

	/**
	 * Returns the number of rows of an eventlist table or null if the table does not exist.
	 *
	 * @param  string $table The name of the table without prefix
	 * @return mixed  The number of rows or null
	 */
	public function getEventlistTableCount($table)
	{
		$tables = array($table => '');
		$tablesCount = $this->getTablesCount($tables, $this->elprefix);
		return $tablesCount[$table];
	}


	/**
	 * Returns the data of a table.
	 *
	 * @param  string $tablename  The name of the table without prefix
	 * @param  int    $limitStart The limit start of the query
	 * @param  int    $limit      The limit of the query
	 * @return array  The data
	 */
	public function getEventlistData($tablename, $limitStart = null, $limit = null)
	{
		$db = $this->_db;
		$query = $db->getQuery('true');

		$query->select('*')
		      ->from($this->elprefix.$tablename);

		if ($limitStart !== null && $limit !== null) {
			$db->setQuery($query, $limitStart, $limit);
		} else {
			$db->setQuery($query);
		}

		return $db->loadObjectList();
	}

	/**
	 * Changes old Eventlist data to fit the JEM standards.
	 *
	 * @param  string $tablename The name of the table
	 * @param  array  $data      The data to work with
	 * @param  int    $j15       Import from Joomla! 1.5
	 * @return array  The changed data
	 *
	 * @Todo   potentially dangerous references to "foreign" objects:
	 *         user id: creator/editor, attendee (!), group member (!)
	 *         contact id: contact for events
	 *         access id: view access level for events, categories
	 */
	public function transformEventlistData($tablename, &$data, $j15)
	{
		// attachments - MUST be transformed after potential objects are stored!
		if (strcasecmp($tablename, 'attachments') === 0) {
			$default_view_level = Factory::getConfig()->get('access', 1);
			$valid_view_levels  = $this->_getViewLevels();
			$current_user_id    = JemFactory::getUser()->get('id');
			$valid_user_ids     = $this->_getUserIds();

			foreach ($data as $row) {
				// Set view access level to e.g. event's view level or default view level
				if (isset($row->access) && $j15) {
					// on Joomla! 1.5 levels are 0 (public), 1 (registered), 2 (special)
					// now we have (normally) 1 (public), 2 (registered), 3 (special), ...
					// (hopefully admin hadn't changed this default levels, but we have no other chance)
					++$row->access;
				}
				if (empty($row->access) || !in_array($row->access, $valid_view_levels)) {
					$row->access = $this->_getObjectViewLevel($row->object, $default_view_level);
				}

				// Check user id
				if (empty($row->added_by) || !in_array($row->added_by, $valid_user_ids)) {
					$row->added_by = $current_user_id;
				}
			}
		}
		// categories
		elseif (strcasecmp($tablename, 'categories') === 0) {
			$default_view_level = Factory::getConfig()->get('access', 1);
			$valid_view_levels  = $this->_getViewLevels();
			$current_user_id    = JemFactory::getUser()->get('id');
			$valid_user_ids     = $this->_getUserIds();

			foreach ($data as $row) {
				// JEM now has a root category, so we shift IDs by 1
				$row->id++;
				$row->parent_id++;

				// Description field has been renamed
				if ($row->catdescription) {
					$row->description = $row->catdescription;
				}

				// Ensure category has a valid view access level
				if (isset($row->access) && $j15) {
					// move from old (0, 1, 2) to (1, 2, 3)
					++$row->access;
				}
				if (empty($row->access) || !in_array($row->access, $valid_view_levels)) {
					$row->access = $default_view_level;
				}

				// Check user id
				if (empty($row->created_user_id) || !in_array($row->created_user_id, $valid_user_ids)) {
					$row->created_user_id = $current_user_id;
				}
			}
		}
		// cats_event_relations
		elseif (strcasecmp($tablename, 'cats_event_relations') === 0) {
			$dataNew = array();
			foreach ($data as $row) {
				// Category-event relations is now stored in seperate table
				if (isset($row->catsid)) { // events table of EL 1.0.2
					$rowNew = new stdClass();
					$rowNew->catid = $row->catsid;
					$rowNew->itemid = $row->id;
					$rowNew->ordering = 0;
					// JEM now has a root category, so we shift IDs by 1
					$rowNew->catid++;
					$dataNew[] = $rowNew;
				} else { // cats_event_relations table of EL 1.1
					$row->catid++;
					$dataNew[] = $row;
				}
			}

			return $dataNew;
		}
		// events
		elseif (strcasecmp($tablename, 'events') === 0) {
			$default_view_level = Factory::getConfig()->get('access', 1);
			$valid_view_levels  = $this->_getViewLevels();
			$cat_levels         = $this->_getCategoryViewLevels();
			$current_user_id    = JemFactory::getUser()->get('id');
			$valid_user_ids     = $this->_getUserIds();

			foreach ($data as $row) {
				// No start date is now represented by a NULL value
				if ($row->dates == '0000-00-00') {
					$row->dates = null;
				}

				// Recurrence fields have changed meaning between EL 1.0.2 and EL 1.1
				// Also on EL 1.0.2 we don't know first event of recurrence set
				//  so we MUST ignore archived events (there will be only one published event of each set)
				if (!isset($row->recurrence_limit_date)) {
					// EL 1.0.x
					if ($row->published == -1) {
						// archived: clear recurrence parameters
						$row->recurrence_number = 0;
						$row->recurrence_type = 0;
					}
					elseif ($row->recurrence_counter != '0000-00-00') {
						$row->recurrence_limit_date = $row->recurrence_counter;
					}
					$row->recurrence_counter = 0;
				} else {
					// EL 1.1 - nothings to adapt
				}

				// Published/state values have changed meaning
				if ($row->published == -1) {
					$row->published = 2; // archived
				}

				// Check if author_ip contains crap
				if (strpos($row->author_ip, 'COM_EVENTLIST') === 0) {
					$row->author_ip = "";
				}

				// Description field has been renamed
				if ($row->datdescription) {
					$row->introtext = $row->datdescription;
				}

				// Set view access level to category's view level or default view level
				if (isset($row->access) && $j15) {
					// move from old (0, 1, 2) to (1, 2, 3)
					++$row->access;
				}
				if (empty($row->access) || !in_array($row->access, $valid_view_levels)) {
					if (isset($row->catsid)) {
						$row->access = (empty($row->catsid) || !array_key_exists($row->catsid, $cat_levels))
						               ? $default_view_level : $cat_levels[$row->catsid];
					} else {
						// no catsid field, so we should have cats_event_relations table
						// try to find unique level
						$row->access = $this->_getEventViewLevelFromCats($row->id, $default_view_level, $j15);
						if (!in_array($row->access, $valid_view_levels)) {
							$row->access = $default_view_level;
						}
					}
				}

				// Check user id
				if (empty($row->created_by) || !in_array($row->created_by, $valid_user_ids)) {
					$row->created_by = $current_user_id;
				}
			}
		}
		// groupmembers
		elseif (strcasecmp($tablename, 'groupmembers') === 0) {
			$valid_user_ids = $this->_getUserIds();

			foreach ($data as $k => $row) {
				// Check user id - REMOVE unknown users
				if (empty($row->member) || !in_array($row->member, $valid_user_ids)) {
					//$row->member = 0;
					unset($data[$k]);
					continue;
				}
			}
		}
		// groups
		elseif (strcasecmp($tablename, 'groups') === 0) {
		}
		// register
		elseif (strcasecmp($tablename, 'register') === 0) {
			$valid_user_ids = $this->_getUserIds();

			foreach ($data as $k => $row) {
				// Check if uip contains crap
				if (strpos($row->uip, 'COM_EVENTLIST') === 0) {
					$row->uip = '';
				}

				// Check user id - REMOVE unknown users - !!!
				if (empty($row->uid) || !in_array($row->uid, $valid_user_ids)) {
					//$row->uid = 0;
					unset($data[$k]);
					continue;
				}
			}
		}
		// venues
		elseif (strcasecmp($tablename, 'venues') === 0) {
			$current_user_id = JemFactory::getUser()->get('id');
			$valid_user_ids  = $this->_getUserIds();

			foreach ($data as $row) {
				// Column name has changed
				$row->postalCode = $row->plz;

				// Check if author_ip contains crap
				if (strpos($row->author_ip, 'COM_EVENTLIST') === 0) {
					$row->author_ip = "";
				}

				// Country changes
				if (strcasecmp($row->country, 'AN') === 0) {
					$row->country = 'NL'; // Netherlands Antilles to Netherlands
				}

				// Check user id
				if (empty($row->created_by) || !in_array($row->created_by, $valid_user_ids)) {
					$row->created_by = $current_user_id;
				}
			}
		}

		return $data;
	}

	/**
	 * Saves the data to the database
	 * @param  string $tablename The name of the table
	 * @param  array  $data      The data to save
	 */
	public function storeJemData($tablename, &$data)
	{
		$replace = true;
		if ((strcasecmp($tablename, 'jem_groupmembers') === 0) ||
		    (strcasecmp($tablename, 'jem_cats_event_relations') === 0) ||
		    (strcasecmp($tablename, 'jem_attachments') === 0)) {
			$replace = false;
		}

		$ignore = array ();
		if (!$replace) {
			$ignore[] = 'id'; // we MUST ignore id field to ensure it's inserted. otherwise it will be silently (not) updated!
 		}
		$rec = array ('added' => 0, 'updated' => 0, 'error' => 0);

		foreach ($data as $row) {
			$object = Table::getInstance($tablename, ''); // don't optimise this, you get trouble with 'id'...
			$object->bind($row, $ignore);

			// Make sure the data is valid
			if (!$object->check()) {
				$this->setError($object->getError());
				echo Text::_('COM_JEM_IMPORT_ERROR_CHECK').$object->getError()."\n";
				continue ;
			}

			// Store it in the db
			if ($replace) {
				// We want to keep id from database so first we try to insert into database. if it fails,
				// it means the record already exists, we can use store().
				if (!$object->insertIgnore()) {
					if (!$object->store()) {
						echo Text::_('COM_JEM_IMPORT_ERROR_STORE').$object->getError()."\n";
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
					echo Text::_('COM_JEM_IMPORT_ERROR_STORE').$object->getError()."\n";
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
	public function getExistingJemData()
	{
		$tablesCount = $this->getJemTablesCount();

		foreach ($tablesCount as $tableCount) {
			if ($tableCount !== null && $tableCount > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copies the Eventlist images to JEM folder
	 */
	public function copyImages()
	{

		$folders = array('categories', 'events', 'venues');

		// Add the thumbnail folders to the folders list
		foreach ($folders as $folder) {
			$folders[] = $folder.'/small';
		}

		foreach ($folders as $folder) {
			$fromFolder = JPATH_SITE.'/images/eventlist/'.$folder.'/';
			$toFolder   = JPATH_SITE.'/images/jem/'.$folder.'/';

			if (Folder::exists($fromFolder) && Folder::exists($toFolder)) {
				$files = Folder::files($fromFolder, null, false, false);

				foreach ($files as $file) {
					if (!File::exists($toFolder.$file)) {
						File::copy($fromFolder.$file, $toFolder.$file);
					}
				}
			}
		}
	}

	/**
	 * Copies the Eventlist (v1.1) attachments to JEM folder
	 */
	public function copyAttachments()
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$jemsettings = JemHelper::config();

		$fromFolder = JPATH_SITE.'/media/com_eventlist/attachments/';
		$toFolder   = JPATH_SITE.'/'.$jemsettings->attachments_path.'/';

		if (!Folder::exists($toFolder)) {
			Folder::create($toFolder);
		}

		if (Folder::exists($fromFolder) && Folder::exists($toFolder)) {
			$files = Folder::files($fromFolder, null, false, false);
			foreach ($files as $file) {
				if (!File::exists($toFolder.$file)) {
					File::copy($fromFolder.$file, $toFolder.$file);
				}
			}

			// attachments are stored in folders like "event123"
			// so we need to walk through all these subfolders
			$folders = Folder::folders($fromFolder, null, false, false);
			foreach ($folders as $folder) {
				if (!Folder::exists($toFolder.$folder)) {
					Folder::create($toFolder.$folder);
				}

				$files = Folder::files($fromFolder.$folder, null, false, false);
				$folder .= '/';
				foreach ($files as $file) {
					if (!File::exists($toFolder.$folder.$file)) {
						File::copy($fromFolder.$folder.$file, $toFolder.$folder.$file);
					}
				}
			}
		}
	}

	/**
	 * Returns all valid user ids.
	 *
	 * @return array All known user ids
	 */
	protected function _getUserIds()
	{
		if (empty(static::$_user_ids))
		{
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);
			$query->select('a.id')
			      ->from($db->quoteName('#__users') . ' AS a')
			      ->order('a.id');

			$db->setQuery($query);
			static::$_user_ids = $db->loadColumn();
		}

		return static::$_user_ids;
	}

	/**
	 * Returns all view level ids.
	 *
	 * @return array All known view level ids
	 */
	protected function _getViewLevels()
	{
		if (empty(static::$_view_levels))
		{
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);
			$query->select('a.id')
			      ->from($db->quoteName('#__viewlevels') . ' AS a')
			      ->order('a.id');

			$db->setQuery($query);
			static::$_view_levels = $db->loadColumn();
		}

		return static::$_view_levels;
	}

	/**
	 * Returns view level of all JEM categories.
	 *
	 * @return array Assoziative array with category-id => view-level-id
	 */
	protected function _getCategoryViewLevels()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('a.id, a.access')
		      ->from($db->quoteName('#__jem_categories') . ' AS a')
		      ->order('a.id');

		$db->setQuery($query);
		return $db->loadAssocList('id', 'access');
	}

	/**
	 * Tries to find a unique view level for given event using EL tables.
	 *
	 * @param  int     $eventId      ID of the event
	 * @param  int     $defaultLevel Level returned if no one found
	 * @param  boolean $j15          Do we need to increment found level?
	 *                               On Joomla! 1.5 levels have IDs 0, 1, 2 - we need 1, 2, 3, ...
	 *
	 * @return int     The view level found or $defaultLevel.
	 */
	protected function _getEventViewLevelFromCats($eventId, $defaultLevel, $j15 = false)
	{
		$ret = $defaultLevel;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('c.access')
		      ->from($db->quoteName($this->elprefix.'eventlist_categories') . ' AS c')
		      ->from($db->quoteName($this->elprefix.'eventlist_cats_event_relations') . ' AS rel')
		      ->where(array('rel.itemid = '.(int)$eventId, 'rel.catid = c.id'));
		$db->setQuery($query);
		$result = $db->loadColumn();

		if (is_array($result)) {
			$result = array_unique($result);
			if (count($result) == 1) {
				$ret = array_pop($result) + ($j15 ? 1 : 0);
			}
		}

		return $ret;
	}

	/**
	 * Gets view level of the object an attachment refers to.
	 *
	 * @param  string $object  object in form 'typeid' as taken from attachments table,
	 *                         type = (event, venue, category), id = a number; e.g. 'event42'
	 * @param  int    $default view level to return if no one found
	 *
	 * @return int    The view level found or $defaultLevel.
	 */
	protected function _getObjectViewLevel($object, $default)
	{
		$result = $default;

		$ok = preg_match('/([^0-9]+)([0-9]+)/', $object, $matches);
		if ($ok && (count($matches) == 3)) {
			$id = $matches[2];
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);

			switch ($matches[1]) {
			case 'event':
				$query->select('access')->from($db->quoteName('#__jem_events'))->where('id = ' . $db->quote($id));
				$db->setQuery($query);
				$res = $db->loadResult();
				if (!empty($res)) {
					$result = $res;
				}
				break;
			case 'category':
				$query->select('access')->from($db->quoteName('#__jem_categories'))->where('id = ' . $db->quote($id));
				$db->setQuery($query);
				$res = $db->loadResult();
				if (!empty($res)) {
					$result = $res;
				}
				break;
			case 'venue':
			default:
				// return default value
				break;
			}
		}

		return $result;
	}

	/**
	 * Get id of root-category
	 */
	private function _rootkey()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('c.id');
		$query->from('#__jem_categories AS c');
		$query->where('c.alias LIKE "root"');
		$db->setQuery($query);
		

		// Check for DB error.
		try
		{
			$key = $db->loadResult();
			return $key;
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}
	}
}
?>
