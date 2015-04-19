<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


// helper callback function to convert all elements of an array
function jem_convert_ansi2utf8(&$value, $key) {
	$value = iconv('windows-1252', 'utf-8', $value);
}

/**
 * Controller: Import
 */
class JemControllerImport extends JControllerLegacy {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
	}

	function csveventimport() {
		$this->CsvImport('events', 'Events');
	}

	function csvcategoriesimport() {
		$this->CsvImport('categories', 'Categories');
	}

	function csvvenuesimport() {
		$this->CsvImport('venues', 'Venues');
	}

	function csvcateventsimport() {
		$this->CsvImport('catevents', 'Cats_event_relations');
	}
	
	function back() {
		$this->setRedirect('index.php?option=com_jem&view=main');
	}

	private function CsvImport($type, $dbname) {

		$replace = JFactory::getApplication()->input->post->get('replace_'.$type, 0, 'int');

		# in here we're retrieving the $dbname
		$object = JTable::getInstance($dbname, 'JEMTable');
		$object_fields = get_object_vars($object);

		if($type == 'events') {
			// add additional fields
			// @todo alter
			$object_fields['categories'] = '';
		}

		$msg = '';
		$file = JFactory::getApplication()->input->files->get('File'.$type, NULL, 'array');

		if ($file['name'] == false)
		{
			$msg = JText::_('COM_JEM_IMPORT_SELECT_FILE');
			$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
			return;
		}

		if ($file['name']) {
			$handle = fopen($file['tmp_name'], 'r');
			if(!$handle) {
				$msg = JText::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
				$this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
				return;
			}

			// search for bom - then it is utf-8
			$bom = pack('CCC', 0xEF, 0xBB, 0xBF);
			$fc = fread($handle, 3);
			$convert = strncmp($fc, $bom, 3) !== 0;
			if ($convert) {
				// no bom - rewind file
				fseek($handle, 0);
			}

			// get fields, on first row of the file
			$fields = array();
			if(($data = fgetcsv($handle, 1000, ';')) !== false) {
				$numfields = count($data);

				// convert from ansi to utf-8 if required
				if ($convert) {
					array_walk($data, 'jem_convert_ansi2utf8');
				}

				for($c=0; $c < $numfields; $c++) {
					// here, we make sure that the field match one of the fields of jem_table or special fields,
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

					// convert from ansi to utf-8 if required
					if ($convert) {
						array_walk($data, 'jem_convert_ansi2utf8');
					}

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
				if ($result['ignored']){
					$msg .= "<p>".JText::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_IGNORED', $result['ignored'])."</p>\n";
				}
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
	protected function _formatcsvfield($type, $value) {
		switch($type) {
			case 'times':
			case 'endtimes':
				if($value != '' && strtoupper($value) != 'NULL') {
					$time = strtotime($value);
					$field = strftime('%H:%M', $time);
				} else {
					$field = null;
				}
			break;
			case 'dates':
			case 'enddates':
			case 'recurrence_limit_date':
				if($value != '' && strtoupper($value) != 'NULL' && $value != '0000-00-00') {
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

	/**
	 * Imports data from an old Eventlist installation
	 */
	public function eventlistImport() {

		$model 		= $this->getModel('import');
		$version	= $model->getEventlistVersion();
		
		$link 		= 'index.php?option=com_jem&view=import';

		# define the table names we're going to use/show
		$tables = new stdClass();
		$tables->imptables = $model->EventlistTables($version,true);

		# some variables
		$size 				= 50000;
		$app				= JFactory::getApplication();
		$jinput				= $app->input;
		$step				= $jinput->getInt('step', 0);
		$current			= $jinput->get->getInt('current', 0);
		$total 				= $jinput->get->getInt('total', 0);
		$table 				= $jinput->get->getInt('table', 0);
		$prefix 			= $jinput->getCmd('prefix', '#__');
		$copyImages 		= $jinput->getInt('copyImages', 0);
		$copyAttachments	= $jinput->getInt('copyAttachments', 0);
		$link 				= 'index.php?option=com_jem&view=import';
		$msg 				= JText::_('COM_JEM_IMPORT_EL_IMPORT_WORK_IN_PROGRESS')." ";
		
		# check for a token
		if($jinput->getInt('startToken', 0)) {
			# Are the JEM tables empty at start? If no, stop import
			if($model->getExistingJemData()) {
				$this->setRedirect($link);
				return;
			}
		}
		
		if($step <= 1) {
			parent::display();
			return;
		} elseif($step == 2) {
			if ($version) {
				$model->setVersion($version);
			}

		############
		## import ##
		############

			// Get number of rows if it is still 0 or we have moved to the next table
			if($total == 0 || $current == 0) {
				$total = $model->getTableCount($tables->imptables[$table]);
			}

			// If $total is null, the table does not exist, so we skip import for this table.
			if($total == null) {

				# check if we're dealing with the cat_events table
				# if so then we're going to something with it.


				if ($tables->imptables[$table] == 'eventlist_cats_event_relations') {
					# check if category table exists
					$check_cat = $model->getTableCount("eventlist_categories");

					if ($check_cat) {
						# there are results for the categories, but there is no result in the cat_event table
						# it can be that the table does not exist or that's empty

						# get data of the Eventlist-table
						$data = $model->getEventlistData("eventlist_events", $current, $size);

						# transform eventlist-data to jem-data
						$data = $model->transformEventlistData("eventlist_cats_event_relations", $data,$version);

						# EL-data is transformed, now we'll store it in the jem-table
						$model->storeTableData($tables->imptables[$table], $data);
					} else {
						// This helps to prevent special cases in the following code
						$total = 0;
					}
				}

			} else {

				####################
				## TRANSFORM DATA ##
				####################

				# The real work is done here:
				# Loading from EL tables, changing data, storing in JEM tables


				# check if we're dealing wit the cat_events table
				# if so then we're going to something with it.

				if ($tables->imptables[$table] == 'eventlist_categories') {

					# check results for cats_event_relations table
					$check_cat = $model->getTableCount("eventlist_cats_event_relations");

					if (is_null($check_cat)) {
						# there are results for the categories, but there is no result in the cat_event table
						# it can be that the table does not exist or that's empty

						# get data of the Eventlist-table
						$data = $model->getEventlistData("eventlist_events", $current, $size);

						# transform eventlist-data to jem-data
						$data = $model->transformEventlistData("eventlist_cats_event_relations", $data,$version);

						# EL-data is transformed, now we'll store it in the jem-table
						$model->storeTableData("eventlist_cats_event_relations", $data);
					}

					# get data of the categories-table
					$data = $model->getEventlistData("eventlist_categories", $current, $size);

					# transform eventlist-data to jem-data
					$data = $model->transformEventlistData("eventlist_categories", $data,$version);

					# EL-data is transformed, now we'll store it in the jem-table
					$model->storeTableData("eventlist_categories", $data);

				} else {
						# get data of the Eventlist-table
						$data = $model->getEventlistData($tables->imptables[$table], $current, $size);

						# transform eventlist-data to jem-data
						$data = $model->transformEventlistData($tables->imptables[$table], $data,$version);

						# EL-data is transformed, now we'll store it in the jem-tables
						$model->storeTableData($tables->imptables[$table], $data);
					}
			}

			// Proceed with next bunch of data
			$current += $size;

			// Current table is imported completely, proceed with next table
			if($current > $total) {
				$table++;
				$current = 0;
			}

			// Check if table import is complete
			if($current <= $total && $table < count($tables->imptables)) {
				// Don't add default prefix to link because of special character #
				if($prefix == "#__") {
					$prefix = "";
				}

				$link .= '&step='.$step.'&copyImages='.$copyImages.'&copyAttachments='.$copyAttachments.'&table='.$table.'&prefix='.$prefix.'&current='.$current.'&total='.$total;
			} else {
				$step++;
				$link .= '&step='.$step.'&copyImages='.$copyImages.'&copyAttachments='.$copyAttachments;
			}
			$app->enqueueMessage(JText::sprintf('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_DB', $tables->imptables[$table-1], $current, $total));
		} elseif($step == 3) {

			########################
			## REBUILD CATEGORIES ##
			########################

			// We have to rebuild the hierarchy of the categories due to the plain database insertion
			JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/tables');
			$categoryTable = JTable::getInstance('Categories', 'JEMTable');
			$categoryTable->rebuild();
			$app->enqueueMessage(JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_REBUILD'));
			$step++;
			$link .= '&step='.$step.'&copyImages='.$copyImages.'&copyAttachments='.$copyAttachments;
		} elseif($step == 4) {
			$version = $model->getVersion();
			
			# Copy EL images to JEM image destination?
			if($copyImages) {
				$copyImages = $model->copyImages();
				if ($copyImages) {
				} else {
				}
				$app->enqueueMessage(JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_IMAGES'));
			} else {
				$app->enqueueMessage(JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_IMAGES_SKIPPED'));
			}

			# Copy Attachments
			if ($version == '1.1.x') {
				if($copyAttachments) {
					$model->copyAttachments();
					$app->enqueueMessage(JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_ATTACHMENTS'));
				} else {
					$app->enqueueMessage(JText::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_ATTACHMENTS_SKIPPED'));
				}
			}

			$step++;
			$link .= '&step='.$step;
		} else {
			$app->enqueueMessage(JText::_('COM_JEM_IMPORT_EL_IMPORT_FINISHED'));
		}

		$this->setRedirect($link);
	}
}
