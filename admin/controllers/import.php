<?php
/**
 * @version 1.9 $Id$
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

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Attendees Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMControllerImport extends JEMController {
	/**
	 * Constructor
	 *
	 *@since 0.9
	 */
	function __construct() {
		parent::__construct();
	}

	function csveventimport() {
		$this->CsvImport('events', 'events');
	}

	function csvcategoriesimport() {
		$this->CsvImport('categories', 'categories');
	}

	function csvvenuesimport() {
		$this->CsvImport('venues', 'venues');
	}

	function csvcateventsimport() {
		$this->CsvImport('catevents', 'cats_event_relations');
	}

	private function CsvImport($type, $dbname) {
		$replace = JRequest::getVar('replace_'.$type, 0, 'post', 'int');
		$object = JTable::getInstance('jem_'.$dbname, '');
		$object_fields = get_object_vars($object);

		if($type == 'events') {
			// add additional fields
			$object_fields['categories'] = '';
		}

		$msg = '';
		if ($file = JRequest::getVar('File'.$type, null, 'files', 'array')) {
			$fc = iconv('windows-1250', 'utf-8', file_get_contents($file['tmp_name']));
			file_put_contents($file['tmp_name'], $fc);
			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			}

			// get fields, on first row of the file
			$fields = array();
			if(($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
				$numfields = count($data);
				for($c=0; $c < $numfields; $c++) {
					// here, we make sure that the field match one of the fields of jem_venues table or special fields,
					// otherwise, we don't add it
					if(array_key_exists($data[$c], $object_fields)) {
						$fields[$c] = $data[$c];
					}
				}
			}

			// If there is no validated fields, there is a problem...
			if(!count($fields)) {
				$msg .= "<p>".JText::_('COM_JEM_IMPORT_PARSE_ERROR')."</p>\n";
				$msg .= "<p>".JText::_('COM_JEM_IMPORT_PARSE_ERROR_INFOTEXT')."</p>\n";

				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			} else {
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS', $numfields)."</p>\n";
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS_USEABLE', count($fields))."</p>\n";
			}

			// Now get the records, meaning the rest of the rows.
			$records = array();
			$row = 1;
			while(($data = fgetcsv($handle, 10000, ';')) !== FALSE) {
				$num = count($data);

				if($numfields != $num) {
					$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS_COUNT_ERROR', $num, $row)."</p>\n";
				} else {
					$r = array();
					// only extract columns with validated header, from previous step.
					foreach($fields as $k => $v) {
						$r[$k] = $this->_formatcsvfield($v, $data[$k]);
					}
					$records[] = $r;
				}
				$row++;
			}
			fclose($handle);
			$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_FOUND', count($records))."</p>\n";

			// database update
			if(count($records)) {
				$model = $this->getModel('import');
				$result = $model->{$type.'import'}($fields, $records, $replace);
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_ADDED', $result['added'])."</p>\n";
				$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_UPDATED', $result['updated'])."</p>\n";
			}
			$this->setRedirect('index.php?option=com_jem&view=import', $msg);
		} else {
			parent::display();
		}
	}

	/**
	 * handle specific fields conversion if needed
	 *
	 * @param string column name
	 * @param string $value
	 * @return string
	 */
	function _formatcsvfield($type, $value) {
		switch($type) {
			case 'dates':
			case 'enddates':
			case 'recurrence_limit_date':
				if($value != '' && strtoupper($value) != 'NULL') {
					$date = strtotime($value);
					$field = strftime('%Y-%m-%d', $date);
				} else {
					$field = null;
				}
				break;
			default:
				$field = $value;
				break;
		}
		return $field;
	}

}
?>