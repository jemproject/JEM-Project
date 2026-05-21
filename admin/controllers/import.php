<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importencoding.php';

/**
 * JEM Component Import Controller
 *
 * @package JEM
 *
 */
class JemControllerImport extends BaseController
{
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Check whether the current user can import JEM data.
     *
     * @return void
     */
    private function assertCanImport()
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public function csveventimport() {
        $this->CsvImport('events', 'events');
    }

    public function csvcategoriesimport() {
        $this->CsvImport('categories', 'categories');
    }

    public function csvvenuesimport() {
        $this->CsvImport('venues', 'venues');
    }

    public function csvcateventsimport() {
        $this->CsvImport('catevents', 'cats_event_relations');
    }

    public function csvattachmentsimport() {
        $this->CsvImport('attachments', 'attachments');
    }

    public function csvtypesimport() {
        $this->CsvImport('types', 'types');
    }

    private function CsvImport($type, $dbname) {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $replace = Factory::getApplication()->input->post->getInt('replace_'.$type, 0);
        $object = Table::getInstance('jem_'.$dbname, '');
        $object_fields = get_object_vars($object);
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');

        if ($type === 'events') {
            // add additional fields
            $object_fields['categories'] = '';
        }

        $msg = '';
        $file = Factory::getApplication()->input->files->get('File'.$type, array(), 'array');

        if (empty($file['name'])) {
            $msg = Text::_('COM_JEM_IMPORT_SELECT_FILE');
            $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
            return;
        }

        if (!empty($file['error'])) {
            $msg = Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
            $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
            return;
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_PARSE_ERROR');
            $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
            return;
        }

        if ($file['name']) {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $msg = Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
                $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
                return;
            }

            // search for bom - then it is explicitly utf-8
            $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
            $fc = fread($handle, 3);
            $hasBom = strncmp($fc, $bom, 3) === 0;
            if (!$hasBom) {
                // no bom - rewind file
                fseek($handle, 0);
            }

            // get fields, on first row of the file
            $fields = array();
            if (($data = fgetcsv($handle, 1000, $separator, $delimiter)) !== false) {
                $numfields = count($data);

                // normalise to utf-8; UTF-8 without BOM must not be converted again
                if (!$hasBom) {
                    $msg .= "<p>".Text::_('COM_JEM_IMPORT_BOM_NOT_FOUND')."</p>\n";
                }
                array_walk($data, 'jem_normalise_csv_utf8');

                for ($c = 0; $c < $numfields; $c++) {
                    // here, we make sure that the field match one of the fields of jem_venues table or special fields,
                    // otherwise, we don't add it
                    if (array_key_exists($data[$c], $object_fields)) {
                        $fields[$c] = $data[$c];
                    }
                }
            }

            // If there is no validated fields, there is a problem...
            if (!count($fields)) {
                $msg .= "<p>".Text::_('COM_JEM_IMPORT_PARSE_ERROR')."</p>\n";
                $msg .= "<p>".Text::_('COM_JEM_IMPORT_PARSE_ERROR_INFOTEXT')."</p>\n";

                $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
                return;
            } else {
                $msg .= "<p>".Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS', $numfields)."</p>\n";
                $msg .= "<p>".Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS_USEABLE', count($fields))."</p>\n";
            }

            // Now get the records, meaning the rest of the rows.
            $records = array();
            $row = 1;

            while (($data = fgetcsv($handle, 10000, $separator, $delimiter)) !== FALSE) {
                $num = count($data);

                if ($numfields != $num) {
                    $msg .= "<p>".Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_FIELDS_COUNT_ERROR', $num, $row)."</p>\n";
                } else {
                    // normalise to utf-8; UTF-8 without BOM must not be converted again
                    array_walk($data, 'jem_normalise_csv_utf8');

                    $r = array();
                    // only extract columns with validated header, from previous step.
                    foreach ($fields as $k => $v) {
                        $r[$k] = $this->_formatcsvfield($v, $data[$k]);
                    }
                    $records[] = $r;
                }
                $row++;
            }

            fclose($handle);
            $msg .= "<p>".Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_FOUND', count($records))."</p>\n";

            // database update
            if (count($records)) {
                $model = $this->getModel('import');
                $result = $model->{$type.'import'}($fields, $records, $replace);
                if ($result['added']) {
                    $msg .= "<p>" . Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_ADDED', $result['added']) . "</p>\n";
                }
                if ($result['updated']) {
                    $msg .= "<p>" . Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_UPDATED', $result['updated']) . "</p>\n";
                }
                if ($result['duplicated']) {
                    $msg .= "<p>" . Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_DUPLICATED', $result['duplicated']) . " [Ids: " . $result['duplicatedids'] . "]</p>\n";
                }
                if ($result['replaced']) {
                    $msg .= "<p>" . Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_REPLACED', $result['replaced']) . " [Ids: " . $result['replacedids'] . "]</p>\n";
                }
                if ($result['ignored']) {
                    $msg .= "<p>" . Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_IGNORED', $result['ignored']) . " [Ids: " . $result['ignoredids'] . "]</p>\n";
                }
                if ($result['error']) {
                    $msg .= "<p>" . Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_ERROR', $result['error']) . " [Ids: " . $result['errorids'] . "]</p>\n";
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
        switch ($type) {
            case 'times':
            case 'endtimes':
                if ($value !== '' && strtoupper($value) !== 'NULL') {
                    $time = strtotime($value);
                    $field = date('H:i:s',$time);
                } else {
                    $field = null;
                }
                break;
            case 'dates':
            case 'enddates':
            case 'recurrence_limit_date':
                if ($value !== '' && strtoupper($value) !== 'NULL' && $value != '0000-00-00') {
                    $date = strtotime($value);
                    $field = date('Y-m-d', $date);
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
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $model = $this->getModel('import');
        $size = 5000;

        // Handling the different names for all classes and db table names (possibly substrings).
        $tables = new stdClass();
        // Note: 'attachments' MUST be last entry!
        $tables->eltables  = array("categories", "events", "cats_event_relations", "groupmembers", "groups", "register", "venues", "attachments");
        $tables->jemtables = array("categories", "events", "cats_event_relations", "groupmembers", "groups", "register", "venues", "attachments");

        $app = Factory::getApplication();
        $jinput = $app->input;
        $step = $jinput->get('step', 0, 'INT');
        $current = $jinput->get->get('current', 0, 'INT');
        $total = $jinput->get->get('total', 0, 'INT');
        $table = $jinput->get->get('table', 0, 'INT');
        $prefix = $app->getUserStateFromRequest('com_jem.import.elimport.prefix', 'prefix', '#__', 'cmd');
        $copyImages = $app->getUserStateFromRequest('com_jem.import.elimport.copyImages', 'copyImages', 0, 'int');
        $copyAttachments = $app->getUserStateFromRequest('com_jem.import.elimport.copyAttachments', 'copyAttachments', 0, 'int');
        $link = 'index.php?option=com_jem&view=import';
        $msg = Text::_('COM_JEM_IMPORT_EL_IMPORT_WORK_IN_PROGRESS')." ";

        if ($table < 0 || $table >= count($tables->eltables)) {
            $this->setRedirect($link, Text::_('COM_JEM_IMPORT_PARSE_ERROR'), 'error');
            return;
        }

        if ($jinput->get('startToken', 0, 'INT') || ($step === 1)) {
            // Are the JEM tables empty at start? If no, stop import
            if ($model->getExistingJemData()) {
                $this->setRedirect($link);
                return;
            }
        }

        if ($step <= 1) {
            $app->setUserState('com_jem.import.elimport.copyImages', '0');
            $app->setUserState('com_jem.import.elimport.copyAttachments', '0');

            if ($step === 1) {
                $attachments = $model->getEventlistTableCount("eventlist_attachments") !== null;
                $app->setUserState('com_jem.import.elimport.attachmentsPossible', $attachments);
            }

            parent::display();
            return;
        } elseif ($step === 2) {
            // Special handling of cats_event_relations table which only exists on EL 1.1
            if (($tables->eltables[$table] == 'cats_event_relations')) {
                $tot = $model->getEventlistTableCount("eventlist_".$tables->eltables[$table]);
                if (!empty($tot)) {
                    $total = $tot;
                } else {
                    $tables->eltables[$table] = 'events';
                }
            }

            // Get number of rows if it is still 0 or we have moved to the next table
            if ($total == 0 || $current == 0) {
                $total = $model->getEventlistTableCount("eventlist_".$tables->eltables[$table]);
            }

            // If $total is null, the table does not exist, so we skip import for this table.
            if ($total === null) {
                // This helps to prevent special cases in the following code
                $total = 0;
            } else {
                // The real work is done here:
                // Loading from EL tables, changing data, storing in JEM tables
                $data = $model->getEventlistData("eventlist_".$tables->eltables[$table], $current, $size);
                $data = $model->transformEventlistData($tables->jemtables[$table], $data);
                $model->storeJemData("jem_".$tables->jemtables[$table], $data);
            }

            // Proceed with next bunch of data
            $current += $size;

            // Current table is imported completely, proceed with next table
            if ($current > $total) {
                $table++;
                $current = 0;
            }

            // Check if table import is complete
            if ($current <= $total && $table < count($tables->eltables)) {
                // Don't add default prefix to link because of special character #
                if ($prefix == "#__") {
                    $prefix = "";
                }

                $link .= '&step='.$step.'&table='.$table.'&current='.$current.'&total='.$total;
                //todo: we say "importing..." so we must show table of next step - but we don't know their entry count ($total).
                $msg .= Text::sprintf('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_DB', $tables->jemtables[$table], $current, '?');
            } else {
                $step++;
                $link .= '&step='.$step;
                $msg .= Text::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_REBUILD');
            }
        } elseif ($step === 3) {
            // We have to rebuild the hierarchy of the categories due to the plain database insertion
            Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/tables');
            $categoryTable = Table::getInstance('Category', 'JemTable');
            $categoryTable->rebuild();
            $step++;
            $link .= '&step='.$step;
            if ($copyImages) {
                $msg .= Text::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_IMAGES');
            } else {
                $msg .= Text::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_IMAGES_SKIPPED');
            }
        } elseif ($step === 4) {
            // Copy EL images to JEM image destination?
            if ($copyImages) {
                $model->copyImages();
            }
            $step++;
            $link .= '&step='.$step;
            if ($copyAttachments) {
                $msg .= Text::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_ATTACHMENTS');
            } else {
                $msg .= Text::_('COM_JEM_IMPORT_EL_IMPORT_WORKING_STEP_COPY_ATTACHMENTS_SKIPPED');
            }
        } elseif ($step === 5) {
            // Copy EL images to JEM image destination?
            if ($copyAttachments) {
                $model->copyAttachments();
            }
            $step++;
            $link .= '&step='.$step;
            $msg = Text::_('COM_JEM_IMPORT_EL_IMPORT_FINISHED');
        } else {
            // cleanup stored fields for users importing multiple time ;-)
            $app->setUserState('com_jem.import.elimport.prefix', null);
            $app->setUserState('com_jem.import.elimport.copyImages', null);
            $app->setUserState('com_jem.import.elimport.copyAttachments', null);
            $app->setUserState('com_jem.import.elimport.attachmentsPossible', null);

            // perform forced cleanup (archive, delete, recurrence)
            JemHelper::cleanup(true);

            $msg = Text::_('COM_JEM_IMPORT_EL_IMPORT_FINISHED');
        }

        $app->enqueueMessage($msg);
        $this->setRedirect($link);
    }
}
?>
