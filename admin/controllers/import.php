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

if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
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
		$replace = JRequest::getVar('replace_events', 0, 'post', 'int');
		$object = JTable::getInstance('jem_events', '');
		$object_fields = get_object_vars($object);
		// add additional fields
		$object_fields['categories'] = '';

		$msg = '';
		if ($file = JRequest::getVar('Fileevents', null, 'files', 'array')) {
			$fc = iconv('windows-1250', 'utf-8', file_get_contents($file['tmp_name']));
			file_put_contents($file['tmp_name'], $fc);
			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('Cannot open uploaded file.');
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
				$msg .= "<p>Error parsing column names. Are you sure this is a proper csv export ?<br />try to export first to get an example of formatting</p>\n";
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			} else {
				$msg .= "<p>".$numfields." fields found in first row</p>\n";
				$msg .= "<p>".count($fields)." fields were kept</p>\n";
			}

			// Now get the records, meaning the rest of the rows.
			$records = array();
			$row = 1;
			while(($data = fgetcsv($handle, 10000, ';')) !== FALSE) {
				$num = count($data);

				if($numfields != $num) {
					$msg .= "<p>Wrong number of fields($num) record $row<br /></p>\n";
				} else {
					$r = array();
					// only extract columns with validated header, from previous step.
					foreach($fields as $k => $v) {
						$r[] = $this->_formatcsvfield($v, $data[$k]);
					}
					$records[] = $r;
				}
				$row++;
			}
			fclose($handle);
			$msg .= "<p>total records found: ".count($records)."<br /></p>\n";

			// database update
			if(count($records)) {
				$model = $this->getModel('import');
				$result = $model->eventsimport($fields, $records, $replace);
				$msg .= "<p>total added records: ".$result['added']."<br /></p>\n";
				$msg .= "<p>total updated records: ".$result['updated']."<br /></p>\n";
			}
			$this->setRedirect('index.php?option=com_jem&view=import', $msg);
		} else {
			parent::display();
		}
	}


	function csvcategoriesimport() {
		$replace = JRequest::getVar('replace_cats', 0, 'post', 'int');
		$object = JTable::getInstance('jem_categories', '');
		$object_fields = get_object_vars($object);

		$msg = '';
		if($file = JRequest::getVar('Filecats', null, 'files', 'array')) {
			$fc = iconv('windows-1250', 'utf-8', file_get_contents($file['tmp_name']));
			file_put_contents($file['tmp_name'], $fc);

			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('Cannot open uploaded file.');
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
						$fields[$c]=$data[$c];
					}
				}
			}

			// If there is no validated fields, there is a problem...
			if(!count($fields)) {
				$msg .= "<p>Error parsing column names. Are you sure this is a proper csv export ?<br />try to export first to get an example of formatting</p>\n";
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			} else {
				$msg .= "<p>".$numfields." fields found in first row</p>\n";
				$msg .= "<p>".count($fields)." fields were kept</p>\n";
			}

			// Now get the records, meaning the rest of the rows.
			$records = array();
			$row = 1;
			while(($data = fgetcsv($handle, 10000, ';')) !== FALSE) {
				$num = count($data);
				if($numfields != $num) {
					$msg .= "<p>Wrong number of fields($num) line $row<br /></p>\n";
				} else {
					$r = array();
					// only extract columns with validated header, from previous step.
					foreach($fields as $k => $v) {
						$r[] = $this->_formatcsvfield($v, $data[$k]);
					}
					$records[] = $r;
				}
				$row++;
			}
			fclose($handle);
			$msg .= "<p>total records found: ".count($records)."<br /></p>\n";

			// database update
			if(count($records)) {
				$model = $this->getModel('import');
				$result = $model->categoriesimport($fields, $records, $replace);
				$msg .= "<p>total added records: ".$result['added']."<br /></p>\n";
				$msg .= "<p>total updated records: ".$result['updated']."<br /></p>\n";
			}
			$this->setRedirect('index.php?option=com_jem&view=import', $msg);
		} else {
			parent::display();
		}
	}

	function csvvenuesimport() {
		$replace = JRequest::getVar('replace_venues', 0, 'post', 'int');
		$object = JTable::getInstance('jem_venues', '');
		$object_fields = get_object_vars($object);

		$msg = '';
		if($file = JRequest::getVar('Filevenues', null, 'files', 'array')) {
			$fc = iconv('windows-1250', 'utf-8', file_get_contents($file['tmp_name']));
			file_put_contents($file['tmp_name'], $fc);

			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('Cannot open uploaded file.');
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
						$fields[$c]=$data[$c];
					}
				}
			}

			// If there is no validated fields, there is a problem...
			if(!count($fields)) {
				$msg .= "<p>Error parsing column names. Are you sure this is a proper csv export ?<br />try to export first to get an example of formatting</p>\n";
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			} else {
				$msg .= "<p>".$numfields." fields found in first row</p>\n";
				$msg .= "<p>".count($fields)." fields were kept</p>\n";
			}

			// Now get the records, meaning the rest of the rows.
			$records = array();
			$row = 1;
			while(($data = fgetcsv($handle, 10000, ';')) !== FALSE) {
				$num = count($data);
				if($numfields != $num) {
					$msg .= "<p>Wrong number of fields($num) line $row<br /></p>\n";
				} else {
					$r = array();
					// only extract columns with validated header, from previous step.
					foreach($fields as $k => $v) {
						$r[] = $this->_formatcsvfield($v, $data[$k]);
					}
					$records[] = $r;
				}
				$row++;
			}
			fclose($handle);
			$msg .= "<p>total records found: ".count($records)."<br /></p>\n";

			// database update
			if(count($records)) {
				$model = $this->getModel('import');
				$result = $model->venuesimport($fields, $records, $replace);
				$msg .= "<p>total added records: ".$result['added']."<br /></p>\n";
				$msg .= "<p>total updated records: ".$result['updated']."<br /></p>\n";
			}
			$this->setRedirect('index.php?option=com_jem&view=import', $msg);
		} else {
			parent::display();
		}
	}


	function csvcateventsimport() {
		$replace = JRequest::getVar('replace_catevents', 0, 'post', 'int');
		$object =	JTable::getInstance('jem_cats_event_relations', '');
		$object_fields = get_object_vars($object);

		$msg = '';
		if($file = JRequest::getVar('Filecatevents', null, 'files', 'array')) {
			$fc = iconv('windows-1250', 'utf-8', file_get_contents($file['tmp_name']));
			file_put_contents($file['tmp_name'], $fc);

			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('Cannot open uploaded file.');
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			}

			// get fields, on first row of the file
			$fields = array();
			if(($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
				$numfields = count($data);
				for($c = 0; $c < $numfields; $c++) {
					// here, we make sure that the field match one of the fields of jem_venues table or special fields,
					// otherwise, we don't add it
					if(array_key_exists($data[$c], $object_fields)) {
						$fields[$c] = $data[$c];
					}
				}
			}

			// If there is no validated fields, there is a problem...
			if(!count($fields)) {
				$msg .= "<p>Error parsing column names. Are you sure this is a proper csv export ?<br />try to export first to get an example of formatting</p>\n";
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			} else {
				$msg .= "<p>".$numfields." fields found in first row</p>\n";
				$msg .= "<p>".count($fields)." fields were kept</p>\n";
			}

			// Now get the records, meaning the rest of the rows.
			$records = array();
			$row = 1;
			while(($data = fgetcsv($handle, 10000, ';')) !== FALSE) {
				$num = count($data);
				if($numfields != $num) {
					$msg .= "<p>Wrong number of fields($num) line $row<br /></p>\n";
				} else {
					$r = array();
					// only extract columns with validated header, from previous step.
					foreach($fields as $k => $v) {
						$r[] = $this->_formatcsvfield($v, $data[$k]);
					}
					$records[] = $r;
				}
				$row++;
			}
			fclose($handle);
			$msg .= "<p>total records found: ".count($records)."<br /></p>\n";

			// database update
			if(count($records)) {
				$model = $this->getModel('import');
				$result = $model->cateventsimport($fields, $records, $replace);
				$msg .= "<p>total added records: ".$result['added']."<br /></p>\n";
				$msg .= "<p>total updated records: ".$result['updated']."<br /></p>\n";
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
				if($value != '') {
					//strtotime does a good job in converting various date formats...
					$date = strtotime($value);
					$field = strftime('%Y-%m-%d', date);
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