<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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
}
?>
