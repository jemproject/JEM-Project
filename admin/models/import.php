<?php
/**
 * @version $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Import Model
 *
 * @package JEM
 * @since 1.1
 */
class JEMModelImport extends JModelLegacy {
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * return __jem_events table fields name
	 *
	 * @return array
	 */
	function getEventFields() {
		$tables = array ('#__jem_events');
		$tablesfields = $this->_db->getTableFields($tables);

		return array_keys($tablesfields['#__jem_events']);
	}

	/**
	*
	* Return Venue Fields
	*/  
	function getVenueFields() {
		$tables = array ('#__jem_venues');
		$tablesfields = $this->_db->getTableFields($tables);

		return array_keys($tablesfields['#__jem_venues']);
	}

	/**
	 * return __jem_categories table fields name
	 *
	 * @return array
	 */
	function getCategoryFields() {
		$tables = array ('#__jem_categories');
		$tablesfields = $this->_db->getTableFields($tables);

		return array_keys($tablesfields['#__jem_categories']);
	}

	/**
	 * return __jem_categories table fields name
	 *
	 * @return array
	 */
	function getCateventsFields() {
		$tables = array ('#__jem_cats_event_relations');
		$tablesfields = $this->_db->getTableFields($tables);

		return array_keys($tablesfields['#__jem_cats_event_relations']);
	}

	/**
	 * import data corresponding to fieldsname into events table
	 *
	 * @param array $fieldsname
	 * @param array $data the records
	 * @param boolean $replace replace if id already exists
	 * @return int number of records inserted
	 */
	function eventsimport($fieldsname, & $data, $replace = true) {
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

			$object = JTable::getInstance('jem_events', '');

			//print_r($values);exit;
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

			// print_r($object); exit;
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

		// force the cleanup to update the imported events status
		$settings = JTable::getInstance('jem_settings', '');
		$settings->load(1);
		$settings->lastupdate = 0;
		$settings->store();

		return $rec;
	}


	/**
	 * import data corresponding to fieldsname into events table
	 *
	 * @param array $fieldsname
	 * @param array $data the records
	 * @param boolean $replace replace if id already exists
	 * @return int number of records inserted
	 */
	function categoriesimport($fieldsname, & $data, $replace = true) {
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

			$object = JTable::getInstance('jem_categories', '');

			//print_r($values);exit;
			$object->bind($values, $ignore);

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

		return $rec;
	}

	/**
	 * import data corresponding to fieldsname into events table
	 *
	 * @param array $fieldsname
	 * @param array $data the records
	 * @param boolean $replace replace if id already exists
	 * @return int number of records inserted
	 */
	function cateventsimport($fieldsname, & $data, $replace = true) {
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

			$object =  JTable::getInstance('jem_cats_event_relations', '');

			//print_r($values);exit;
			$object->bind($values, $ignore);

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

		return $rec;
	}

	/**
	 * import data corresponding to fieldsname into events table
	 *
	 * @param array $fieldsname
	 * @param array $data the records
	 * @param boolean $replace replace if id already exists
	 * @return int number of records inserted
	 */
	function venuesimport($fieldsname, & $data, $replace = true) {
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

			$object = JTable::getInstance('jem_venues', '');

			//print_r($values);exit;
			$object->bind($values, $ignore);

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
				} else 	{
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

		return $rec;
	}
}
?>
