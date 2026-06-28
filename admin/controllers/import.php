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
use Joomla\CMS\Log\Log;
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
    protected static $importLoggers = array();

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

    /**
     * Preview an external CSV as normalized JEM events.
     *
     * @return void
     */
    public function previewExternalCsv()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $catid = $input->post->getInt('external_csv_catid', 0);
        $file = $input->files->get('FileExternalCsv', array(), 'array');

        if ($catid <= 0) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_CATEGORY_REQUIRED_ERROR');
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        if (empty($file['name']) || !empty($file['error']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_SELECT_FILE');
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        $options = array(
            'catid' => $catid,
            'category_label' => $this->getCategoryLabel($catid),
            'mode' => $input->post->getCmd('external_csv_mode', 'standard'),
            'type_id' => $input->post->getInt('external_csv_type_id', 0),
            'locid' => $input->post->getInt('external_csv_locid', 0),
            'published' => $input->post->getInt('external_csv_published', 1),
            'publish_up' => $this->normaliseExternalPublishUp($input->post->getString('external_csv_publish_up', '')),
            'language' => $input->post->getCmd('external_csv_language', '*'),
        );
        $options['type_label'] = $this->getTypeLabel($options['type_id']);
        $options['venue_label'] = $this->getVenueLabel($options['locid']);
        $options['language_label'] = $this->getLanguageLabel($options['language']);

        $preview = $this->buildExternalCsvPreview($file, $options);
        $app->setUserState('com_jem.import.external_csv.preview', $preview);

        $this->addImportLogEntry(
            'external_csv',
            'External CSV preview for file "' . $file['name'] . '". '
            . 'Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#event-import', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Import the valid rows from the last external CSV preview.
     *
     * @return void
     */
    public function commitExternalCsv()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jem.import.external_csv.preview', null);

        if (empty($preview['records'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_NO_PREVIEW');
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        $fields = array('title', 'dates', 'enddates', 'times', 'endtimes', 'introtext', 'fulltext', 'metadata', 'published', 'publish_up', 'type_id', 'locid', 'language', 'categories');
        $records = $preview['records'];
        $model = $this->getModel('import');
        ob_start();
        $result = $model->eventsimport($fields, $records, false);
        $importOutput = trim((string) ob_get_clean());
        $app->setUserState('com_jem.import.external_csv.preview', null);

        $msg = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_COMMIT_RESULT', (int) $result['added'], (int) $result['error'], (int) $preview['skipped_count']);
        $this->addImportLogEntry(
            'external_csv',
            'External CSV import committed. ' . strip_tags($msg)
            . $this->formatExternalImportLogDetails($preview, $result, $importOutput),
            $result['error'] ? Log::WARNING : Log::INFO
        );
        $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, $result['error'] ? 'warning' : 'message');
    }

    /**
     * Clear the external CSV preview.
     *
     * @return void
     */
    public function clearExternalCsvPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        Factory::getApplication()->setUserState('com_jem.import.external_csv.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#event-import');
    }

    /**
     * Preview an external ICS file as normalized JEM events.
     *
     * @return void
     */
    public function previewExternalIcs()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $catid = $input->post->getInt('external_ics_catid', 0);
        $file = $input->files->get('FileExternalIcs', array(), 'array');

        if ($catid <= 0) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_CATEGORY_REQUIRED_ERROR');
            $this->addImportLogEntry('external_ics', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        if (empty($file['name']) || !empty($file['error']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'ics' || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_SELECT_FILE');
            $this->addImportLogEntry('external_ics', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        $options = array(
            'catid' => $catid,
            'category_label' => $this->getCategoryLabel($catid),
            'mode' => $input->post->getCmd('external_ics_mode', 'standard'),
            'type_id' => $input->post->getInt('external_ics_type_id', 0),
            'locid' => $input->post->getInt('external_ics_locid', 0),
            'published' => $input->post->getInt('external_ics_published', 1),
            'publish_up' => $this->normaliseExternalPublishUp($input->post->getString('external_ics_publish_up', '')),
            'language' => $input->post->getCmd('external_ics_language', '*'),
        );
        $options['type_label'] = $this->getTypeLabel($options['type_id']);
        $options['venue_label'] = $this->getVenueLabel($options['locid']);
        $options['language_label'] = $this->getLanguageLabel($options['language']);

        $preview = $this->buildExternalIcsPreview($file, $options);
        $app->setUserState('com_jem.import.external_ics.preview', $preview);

        $this->addImportLogEntry(
            'external_ics',
            'External ICS preview for file "' . $file['name'] . '". '
            . 'Valid events: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#event-import', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Import the valid rows from the last external ICS preview.
     *
     * @return void
     */
    public function commitExternalIcs()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jem.import.external_ics.preview', null);

        if (empty($preview['records'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_NO_PREVIEW');
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        $fields = array('title', 'dates', 'enddates', 'times', 'endtimes', 'introtext', 'fulltext', 'metadata', 'published', 'publish_up', 'type_id', 'locid', 'language', 'categories');
        $model = $this->getModel('import');
        ob_start();
        $result = $model->eventsimport($fields, $preview['records'], false);
        $importOutput = trim((string) ob_get_clean());
        $app->setUserState('com_jem.import.external_ics.preview', null);

        $msg = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_ICS_COMMIT_RESULT', (int) $result['added'], (int) $result['error'], (int) $preview['skipped_count']);
        $this->addImportLogEntry(
            'external_ics',
            'External ICS import committed. ' . strip_tags($msg)
            . $this->formatExternalImportLogDetails($preview, $result, $importOutput),
            $result['error'] ? Log::WARNING : Log::INFO
        );
        $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, $result['error'] ? 'warning' : 'message');
    }

    /**
     * Clear the external ICS preview.
     *
     * @return void
     */
    public function clearExternalIcsPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        Factory::getApplication()->setUserState('com_jem.import.external_ics.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#event-import');
    }

    /**
     * Preview an external CSV or ICS file as normalized JEM events.
     *
     * @return void
     */
    public function previewExternalImport()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $catid = $input->post->getInt('external_import_catid', 0);
        $file = $input->files->get('FileExternalImport', array(), 'array');
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if ($catid <= 0) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_CATEGORY_REQUIRED_ERROR');
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        if (empty($file['name']) || !empty($file['error']) || !in_array($extension, array('csv', 'ics'), true) || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_UNSUPPORTED_FILE');
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        $options = array(
            'catid' => $catid,
            'category_label' => $this->getCategoryLabel($catid),
            'mode' => $input->post->getCmd('external_import_mode', 'standard'),
            'type_id' => $input->post->getInt('external_import_type_id', 0),
            'locid' => $input->post->getInt('external_import_locid', 0),
            'published' => $input->post->getInt('external_import_published', 1),
            'publish_up' => $this->normaliseExternalPublishUp($input->post->getString('external_import_publish_up', '')),
            'language' => $input->post->getCmd('external_import_language', '*'),
        );
        $options['type_label'] = $this->getTypeLabel($options['type_id']);
        $options['venue_label'] = $this->getVenueLabel($options['locid']);
        $options['language_label'] = $this->getLanguageLabel($options['language']);

        $preview = $extension === 'ics'
            ? $this->buildExternalIcsPreview($file, $options)
            : $this->buildExternalCsvPreview($file, $options);
        $preview['format'] = $extension;
        $app->setUserState('com_jem.import.external_import.preview', $preview);
        $app->setUserState('com_jem.import.external_csv.preview', null);
        $app->setUserState('com_jem.import.external_ics.preview', null);

        $logKey = $extension === 'ics' ? 'external_ics' : 'external_csv';
        $this->addImportLogEntry(
            $logKey,
            'External ' . strtoupper($extension) . ' preview for file "' . $file['name'] . '". Parser: ' . strtoupper($extension) . '. '
            . 'Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#event-import', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Import the valid rows from the last unified external event preview.
     *
     * @return void
     */
    public function commitExternalImport()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jem.import.external_import.preview', null);

        if (empty($preview['records'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_NO_PREVIEW');
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        $fields = array('title', 'dates', 'enddates', 'times', 'endtimes', 'introtext', 'fulltext', 'metadata', 'published', 'publish_up', 'type_id', 'locid', 'language', 'categories');
        $model = $this->getModel('import');
        ob_start();
        $result = $model->eventsimport($fields, $preview['records'], false);
        $importOutput = trim((string) ob_get_clean());
        $app->setUserState('com_jem.import.external_import.preview', null);

        $format = strtolower($preview['format'] ?? 'csv');
        $msgKey = $format === 'ics' ? 'COM_JEM_IMPORT_EXTERNAL_ICS_COMMIT_RESULT' : 'COM_JEM_IMPORT_EXTERNAL_COMMIT_RESULT';
        $msg = Text::sprintf($msgKey, (int) $result['added'], (int) $result['error'], (int) $preview['skipped_count']);
        $this->addImportLogEntry(
            $format === 'ics' ? 'external_ics' : 'external_csv',
            'External ' . strtoupper($format) . ' import committed. ' . strip_tags($msg)
            . $this->formatExternalImportLogDetails($preview, $result, $importOutput),
            $result['error'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, $result['error'] ? 'warning' : 'message');
    }

    /**
     * Clear the unified external event import preview.
     *
     * @return void
     */
    public function clearExternalImportPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        Factory::getApplication()->setUserState('com_jem.import.external_import.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#event-import');
    }

    /**
     * Preview external CSV rows as JEM Special Days.
     *
     * @return void
     */
    public function previewSpecialDaysCsv()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $dayType = trim($input->post->getString('specialdays_csv_day_type', ''));
        $file = $input->files->get('FileSpecialDaysCsv', array(), 'array');

        if ($dayType === '') {
            $msg = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_REQUIRED');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        if (empty($file['name']) || !empty($file['error']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_SELECT_FILE');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        $preview = $this->buildSpecialDaysCsvPreview($file, array(
            'day_type' => $dayType,
            'replace' => $input->post->getInt('replace_specialdays_csv', 0),
            'source' => 'csv',
            'title' => Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_PREVIEW_TITLE'),
        ));

        $app->setUserState('com_jem.import.specialdays_csv.preview', $preview);
        $this->addImportLogEntry(
            'special_days',
            'Special Days CSV preview for file "' . $file['name'] . '". Type of day: ' . $dayType
            . '. Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#special-days', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Commit the valid rows from the last Special Days CSV preview.
     *
     * @return void
     */
    public function commitSpecialDaysCsv()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $this->commitSpecialDaysPreview('com_jem.import.specialdays_csv.preview', 'CSV');
    }

    /**
     * Clear the Special Days CSV preview.
     *
     * @return void
     */
    public function clearSpecialDaysCsvPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        Factory::getApplication()->setUserState('com_jem.import.specialdays_csv.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#special-days');
    }

    /**
     * Preview external ICS events as JEM Special Days.
     *
     * @return void
     */
    public function previewSpecialDaysIcs()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $dayType = trim($input->post->getString('specialdays_ics_day_type', ''));
        $file = $input->files->get('FileSpecialDaysIcs', array(), 'array');

        if ($dayType === '') {
            $msg = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_REQUIRED');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        if (empty($file['name']) || !empty($file['error']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'ics' || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_SELECT_FILE');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        $preview = $this->buildSpecialDaysIcsPreview($file, array(
            'day_type' => $dayType,
            'replace' => $input->post->getInt('replace_specialdays_ics', 0),
            'source' => 'ics',
            'title' => Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_ICS_PREVIEW_TITLE'),
        ));

        $app->setUserState('com_jem.import.specialdays_ics.preview', $preview);
        $this->addImportLogEntry(
            'special_days',
            'Special Days ICS preview for file "' . $file['name'] . '". Type of day: ' . $dayType
            . '. Valid events: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#special-days', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Commit the valid rows from the last Special Days ICS preview.
     *
     * @return void
     */
    public function commitSpecialDaysIcs()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $this->commitSpecialDaysPreview('com_jem.import.specialdays_ics.preview', 'ICS');
    }

    /**
     * Clear the Special Days ICS preview.
     *
     * @return void
     */
    public function clearSpecialDaysIcsPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        Factory::getApplication()->setUserState('com_jem.import.specialdays_ics.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#special-days');
    }

    /**
     * Preview CSV or ICS files as JEM Special Days.
     *
     * @return void
     */
    public function previewSpecialDaysImport()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $dayType = trim($input->post->getString('specialdays_import_day_type', ''));
        $file = $input->files->get('FileSpecialDaysImport', array(), 'array');
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if ($dayType === '') {
            $msg = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_REQUIRED');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        if (empty($file['name']) || !empty($file['error']) || !in_array($extension, array('csv', 'ics'), true) || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_UNSUPPORTED_FILE');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        $options = array(
            'day_type' => $dayType,
            'replace' => $input->post->getInt('replace_specialdays_import', 0),
            'source' => $extension,
            'title' => Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_PREVIEW_TITLE'),
        );
        $preview = $extension === 'ics'
            ? $this->buildSpecialDaysIcsPreview($file, $options)
            : $this->buildSpecialDaysCsvPreview($file, $options);
        $preview['format'] = $extension;

        $app->setUserState('com_jem.import.specialdays_import.preview', $preview);
        $app->setUserState('com_jem.import.specialdays_csv.preview', null);
        $app->setUserState('com_jem.import.specialdays_ics.preview', null);

        $this->addImportLogEntry(
            'special_days',
            'Special Days ' . strtoupper($extension) . ' preview for file "' . $file['name'] . '". Parser: ' . strtoupper($extension)
            . '; Type fallback: ' . $dayType . '; Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#special-days', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Commit the last unified Special Days preview.
     *
     * @return void
     */
    public function commitSpecialDaysImport()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $preview = Factory::getApplication()->getUserState('com_jem.import.specialdays_import.preview', null);
        $format = strtoupper($preview['format'] ?? 'CSV');
        $this->commitSpecialDaysPreview('com_jem.import.specialdays_import.preview', $format);
    }

    /**
     * Clear the unified Special Days preview.
     *
     * @return void
     */
    public function clearSpecialDaysImportPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        Factory::getApplication()->setUserState('com_jem.import.specialdays_import.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#special-days');
    }

    /**
     * Log an object created from the external import setup modal.
     *
     * @return bool
     */
    public function logCreatedImportOption()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $source = $input->getCmd('source', 'external');
        $object = $input->getCmd('object', 'option');
        $value = $input->getInt('value', 0);
        $label = trim($input->getString('label', ''));
        $select = $input->getCmd('select', '');
        $logKey = $source === 'ics' ? 'external_ics' : 'external_csv';

        $this->addImportLogEntry(
            $logKey,
            'Import setup option created/selected. Source: ' . strtoupper($source)
            . '; Object: ' . $object
            . '; Label: ' . ($label !== '' ? $label : '-')
            . '; ID: ' . $value
            . '; Select: ' . $select . '.',
            Log::INFO
        );

        if ($source === 'ics') {
            $app->setUserState('com_jem.import.external_ics.preview', null);
        } else {
            $app->setUserState('com_jem.import.external_csv.preview', null);
        }

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode(array('success' => true));
        $app->close();

        return true;
    }

    /**
     * Display a known JEM import log file.
     *
     * @return bool
     */
    public function viewLog()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanImport();

        $log = $this->getKnownImportLogFile();
        $content = $this->readLogTail($log['path']);

        if ($content === '') {
            $content = Text::_('COM_JEM_IMPORT_LOGS_EMPTY');
        }

        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'text/html; charset=utf-8', true);

        echo '<!doctype html><html><head><meta charset="utf-8"><title>'
            . htmlspecialchars($log['name'], ENT_QUOTES, 'UTF-8')
            . '</title><style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:1rem;}h1{font-size:1.25rem;margin:0 0 1rem;}pre{white-space:pre-wrap;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.9rem;line-height:1.45;background:#f6f8fa;border:1px solid #d8dee4;padding:1rem;}</style></head><body><h1>'
            . htmlspecialchars($log['name'], ENT_QUOTES, 'UTF-8')
            . '</h1><pre>'
            . htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
            . '</pre></body></html>';

        $app->close();

        return true;
    }

    /**
     * Download a known JEM import log file.
     *
     * @return bool
     */
    public function downloadLog()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanImport();

        $log = $this->getKnownImportLogFile();
        $app = Factory::getApplication();

        if (!is_file($log['path']) || !is_readable($log['path'])) {
            throw new Exception(Text::_('COM_JEM_IMPORT_LOGS_EMPTY'), 404);
        }

        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . basename($log['name']) . '"', true);
        $app->setHeader('Content-Length', (string) filesize($log['path']), true);

        readfile($log['path']);
        $app->close();

        return true;
    }

    /**
     * Resolve a request key to a known JEM import log file.
     *
     * @return array
     */
    protected function getKnownImportLogFile()
    {
        $app = Factory::getApplication();
        $key = $app->input->getCmd('log', '');
        $files = array(
            'external_csv' => 'jem-import-external-csv.log.php',
            'external_ics' => 'jem-import-external-ics.log.php',
            'jem_venues' => 'jem-import-venues.log.php',
            'jem_categories' => 'jem-import-categories.log.php',
            'jem_events' => 'jem-import-events.log.php',
            'jem_catevents' => 'jem-import-catevents.log.php',
            'jem_attachments' => 'jem-import-attachments.log.php',
            'jem_types' => 'jem-import-types.log.php',
            'special_days' => 'jem-import-specialdays.log.php',
        );

        if (!isset($files[$key])) {
            throw new Exception(Text::_('COM_JEM_IMPORT_LOGS_INVALID'), 400);
        }

        $logPath = rtrim($app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\');

        return array(
            'name' => $files[$key],
            'path' => $logPath . DIRECTORY_SEPARATOR . $files[$key],
        );
    }

    /**
     * Read the last part of a log file for backend preview.
     *
     * @param   string  $file  Absolute log file path.
     *
     * @return string
     */
    protected function readLogTail($file)
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        $maxBytes = 250000;
        $size = filesize($file);
        $handle = fopen($file, 'rb');

        if (!$handle) {
            return '';
        }

        if ($size > $maxBytes) {
            fseek($handle, -$maxBytes, SEEK_END);
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        return $content === false ? '' : $content;
    }

    private function CsvImport($type, $dbname) {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $replace = Factory::getApplication()->input->post->getInt('replace_'.$type, 0);
        $logKey = $this->getImportLogKeyForCsvType($type);
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
            $this->addImportLogEntry($logKey, $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
            return;
        }

        if (!empty($file['error'])) {
            $msg = Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
            $this->addImportLogEntry($logKey, $msg . ' File: ' . $file['name'], Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
            return;
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($file['tmp_name'])) {
            $msg = Text::_('COM_JEM_IMPORT_PARSE_ERROR');
            $this->addImportLogEntry($logKey, $msg . ' File: ' . $file['name'], Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
            return;
        }

        if ($file['name']) {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $msg = Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR');
                $this->addImportLogEntry($logKey, $msg . ' File: ' . $file['name'], Log::WARNING);
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
                $this->addImportLogEntry($logKey, strip_tags($msg) . ' File: ' . $file['name'], Log::WARNING);

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
                $this->addImportLogEntry(
                    $logKey,
                    'CSV import "' . $type . '" from file "' . $file['name'] . '" completed. '
                    . trim(preg_replace('/\s+/', ' ', strip_tags($msg))),
                    $result['error'] ? Log::WARNING : Log::INFO
                );
            } else {
                $this->addImportLogEntry(
                    $logKey,
                    'CSV import "' . $type . '" from file "' . $file['name'] . '" found no importable records. '
                    . trim(preg_replace('/\s+/', ' ', strip_tags($msg))),
                    Log::WARNING
                );
            }
            $this->setRedirect('index.php?option=com_jem&view=import', $msg);
        } else {
            parent::display();
        }
    }

    /**
     * Resolve a CSV import type to an import log key.
     *
     * @param   string  $type  CSV import type.
     *
     * @return string
     */
    protected function getImportLogKeyForCsvType($type)
    {
        $map = array(
            'venues' => 'jem_venues',
            'categories' => 'jem_categories',
            'events' => 'jem_events',
            'catevents' => 'jem_catevents',
            'attachments' => 'jem_attachments',
            'types' => 'jem_types',
        );

        return $map[$type] ?? 'jem_events';
    }

    /**
     * Add a message to one of the known import log files.
     *
     * @param   string   $key       Import log key.
     * @param   string   $message   Log message.
     * @param   integer  $priority  Joomla log priority.
     *
     * @return void
     */
    protected function addImportLogEntry($key, $message, $priority = Log::INFO)
    {
        $files = array(
            'external_csv' => 'jem-import-external-csv.log.php',
            'external_ics' => 'jem-import-external-ics.log.php',
            'jem_venues' => 'jem-import-venues.log.php',
            'jem_categories' => 'jem-import-categories.log.php',
            'jem_events' => 'jem-import-events.log.php',
            'jem_catevents' => 'jem-import-catevents.log.php',
            'jem_attachments' => 'jem-import-attachments.log.php',
            'jem_types' => 'jem-import-types.log.php',
            'special_days' => 'jem-import-specialdays.log.php',
        );

        if (!isset($files[$key])) {
            return;
        }

        $category = 'JEM_IMPORT_' . strtoupper($key);

        if (empty(self::$importLoggers[$key])) {
            Log::addLogger(
                array(
                    'text_file' => $files[$key],
                    'text_entry_format' => '{DATE} {TIME} | {PRIORITY} | {CATEGORY} | {MESSAGE}',
                ),
                Log::ALL,
                array($category)
            );
            self::$importLoggers[$key] = true;
        }

        Log::add($message, $priority, $category);
        $this->normaliseImportLogHeader($files[$key]);
    }

    /**
     * Normalise JEM import log headers after Joomla creates the log file.
     *
     * @param   string  $fileName  Log file name.
     *
     * @return void
     */
    protected function normaliseImportLogHeader($fileName)
    {
        $app = Factory::getApplication();
        $path = rtrim($app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\') . DIRECTORY_SEPARATOR . $fileName;

        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            return;
        }

        $content = file($path, FILE_IGNORE_NEW_LINES);

        if (!$content) {
            return;
        }

        $content = array_values(array_filter($content, function ($line) {
            $trimmed = trim((string) $line);

            return $trimmed !== '#' && $trimmed !== "#<?php die('Forbidden.'); ?>";
        }));

        $jemInfo = $this->getJemManifestLogInfo();

        foreach ($content as &$line) {
            if (strpos($line, '#Software:') === 0 && strpos($line, 'JEM ') === false) {
                $line .= ' | JEM ' . $jemInfo['version'] . ' (' . $jemInfo['date'] . ')';
                break;
            }
        }
        unset($line);

        file_put_contents($path, implode(PHP_EOL, $content) . PHP_EOL, LOCK_EX);
    }

    /**
     * Read JEM version information for import log headers.
     *
     * @return array
     */
    protected function getJemManifestLogInfo()
    {
        $manifestPath = JPATH_COMPONENT_ADMINISTRATOR . '/jem.xml';
        $version = 'unknown';
        $date = 'unknown';

        if (is_file($manifestPath)) {
            $manifest = simplexml_load_file($manifestPath);

            if ($manifest) {
                $version = trim((string) $manifest->version) ?: $version;
                $date = trim((string) $manifest->creationDate) ?: $date;
            }
        }

        return array('version' => $version, 'date' => $date);
    }

    /**
     * Format diagnostic details for external event import commits.
     *
     * @param   array   $preview       Stored preview payload.
     * @param   array   $result        Import result.
     * @param   string  $importOutput  Captured importer output.
     *
     * @return string
     */
    protected function formatExternalImportLogDetails(array $preview, array $result, $importOutput)
    {
        $details = array(
            'Target category: ' . ($preview['category_label'] ?? '-') . ' (#' . (int) ($preview['catid'] ?? 0) . ')',
            'Default type: ' . ($preview['type_label'] ?? Text::_('JNONE')) . ' (#' . (int) ($preview['type_id'] ?? 0) . ')',
            'Default venue: ' . ($preview['venue_label'] ?? Text::_('JNONE')) . ' (#' . (int) ($preview['locid'] ?? 0) . ')',
            'Language: ' . ($preview['language_label'] ?? ($preview['language'] ?? '*')) . ' (' . ($preview['language'] ?? '*') . ')',
            'Publish up: ' . ($preview['publish_up_label'] ?? ($preview['publish_up'] ?? '-')),
            'Preview rows: ' . (int) count($preview['rows'] ?? array()) . ', records submitted: ' . (int) count($preview['records'] ?? array()),
        );

        if (!empty($result['errorids'])) {
            $details[] = 'Importer error ids/titles: ' . $result['errorids'];
        }

        if ($importOutput !== '') {
            $details[] = 'Importer output: ' . trim(preg_replace('/\s+/', ' ', strip_tags($importOutput)));
        }

        if (!empty($preview['rows'])) {
            $samples = array();

            foreach (array_slice($preview['rows'], 0, 5) as $row) {
                $samples[] = ($row['title'] ?? '-') . ' [' . ($row['date_label'] ?? '-') . ' ' . ($row['time_label'] ?? '-') . ']';
            }

            $details[] = 'Preview sample: ' . implode(' | ', $samples);
        }

        return ' Details: ' . implode(' ; ', $details) . '.';
    }

    /**
     * Build a preview from an external CSV upload.
     *
     * @param   array  $file     Uploaded file info.
     * @param   array  $options  Import defaults.
     *
     * @return array
     */
    protected function buildExternalCsvPreview(array $file, array $options)
    {
        $rows = array();
        $records = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        $handle = fopen($file['tmp_name'], 'r');

        if (!$handle) {
            return array(
                'rows' => array(),
                'records' => array(),
                'valid_count' => 0,
                'error_count' => 1,
                'skipped_count' => 0,
                'has_errors' => true,
                'catid' => (int) $options['catid'],
                'type_id' => (int) $options['type_id'],
                'locid' => (int) $options['locid'],
                'language' => $options['language'],
                'publish_up' => $options['publish_up'],
                'category_label' => $options['category_label'],
                'type_label' => $options['type_label'],
                'venue_label' => $options['venue_label'],
                'language_label' => $options['language_label'],
                'publish_up_label' => $options['publish_up'],
                'summary' => Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'),
            );
        }

        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        $firstChars = fread($handle, 3);
        $hasBom = strncmp($firstChars, $bom, 3) === 0;

        if (!$hasBom) {
            fseek($handle, 0);
        }

        $header = fgetcsv($handle, 10000, $separator, $delimiter);
        if (is_array($header) && count($header) === 1 && strpos((string) $header[0], ',') !== false && $separator !== ',') {
            $separator = ',';
            fseek($handle, $hasBom ? 3 : 0);
            $header = fgetcsv($handle, 10000, $separator, $delimiter);
        } elseif (is_array($header) && count($header) === 1 && strpos((string) $header[0], ';') !== false && $separator !== ';') {
            $separator = ';';
            fseek($handle, $hasBom ? 3 : 0);
            $header = fgetcsv($handle, 10000, $separator, $delimiter);
        }

        if ($header === false) {
            fclose($handle);
            return array(
                'rows' => array(),
                'records' => array(),
                'valid_count' => 0,
                'error_count' => 1,
                'skipped_count' => 0,
                'has_errors' => true,
                'catid' => (int) $options['catid'],
                'type_id' => (int) $options['type_id'],
                'locid' => (int) $options['locid'],
                'language' => $options['language'],
                'publish_up' => $options['publish_up'],
                'category_label' => $options['category_label'],
                'type_label' => $options['type_label'],
                'venue_label' => $options['venue_label'],
                'language_label' => $options['language_label'],
                'publish_up_label' => $options['publish_up'],
                'summary' => Text::_('COM_JEM_IMPORT_PARSE_ERROR'),
            );
        }

        array_walk($header, 'jem_normalise_csv_utf8');
        $fields = $this->normaliseExternalCsvHeader($header);
        $line = 1;

        while (($raw = fgetcsv($handle, 10000, $separator, $delimiter)) !== false) {
            $line++;
            array_walk($raw, 'jem_normalise_csv_utf8');

            if (count(array_filter($raw, 'strlen')) === 0) {
                continue;
            }

            $data = array();
            foreach ($fields as $index => $field) {
                if ($field === null) {
                    continue;
                }
                $data[$field] = $raw[$index] ?? '';
            }

            $row = $this->normaliseExternalCsvRow($data, $options, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $records[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        fclose($handle);

        return array(
            'rows' => $rows,
            'records' => $records,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'catid' => (int) $options['catid'],
            'type_id' => (int) $options['type_id'],
            'locid' => (int) $options['locid'],
            'language' => $options['language'],
            'publish_up' => $options['publish_up'],
            'category_label' => $options['category_label'],
            'type_label' => $options['type_label'],
            'venue_label' => $options['venue_label'],
            'language_label' => $options['language_label'],
            'publish_up_label' => $options['publish_up'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    /**
     * Build a preview from an external ICS upload.
     *
     * @param   array  $file     Uploaded file info.
     * @param   array  $options  Import defaults.
     *
     * @return array
     */
    protected function buildExternalIcsPreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return array(
                'rows' => array(),
                'records' => array(),
                'valid_count' => 0,
                'error_count' => 1,
                'skipped_count' => 0,
                'has_errors' => true,
                'catid' => (int) $options['catid'],
                'type_id' => (int) $options['type_id'],
                'locid' => (int) $options['locid'],
                'language' => $options['language'],
                'publish_up' => $options['publish_up'],
                'category_label' => $options['category_label'],
                'type_label' => $options['type_label'],
                'venue_label' => $options['venue_label'],
                'language_label' => $options['language_label'],
                'publish_up_label' => $options['publish_up'],
                'summary' => Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'),
            );
        }

        $events = $this->parseExternalIcsEvents($content);

        if (!$events) {
            return array(
                'rows' => array(),
                'records' => array(),
                'valid_count' => 0,
                'error_count' => 1,
                'skipped_count' => 0,
                'has_errors' => true,
                'catid' => (int) $options['catid'],
                'type_id' => (int) $options['type_id'],
                'locid' => (int) $options['locid'],
                'language' => $options['language'],
                'publish_up' => $options['publish_up'],
                'category_label' => $options['category_label'],
                'type_label' => $options['type_label'],
                'venue_label' => $options['venue_label'],
                'language_label' => $options['language_label'],
                'publish_up_label' => $options['publish_up'],
                'summary' => Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_NO_EVENTS'),
            );
        }

        $rows = array();
        $records = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        $line = 0;

        foreach ($events as $event) {
            $line++;
            $row = $this->normaliseExternalIcsEvent($event, $options, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $records[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        return array(
            'rows' => $rows,
            'records' => $records,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'catid' => (int) $options['catid'],
            'type_id' => (int) $options['type_id'],
            'locid' => (int) $options['locid'],
            'language' => $options['language'],
            'publish_up' => $options['publish_up'],
            'category_label' => $options['category_label'],
            'type_label' => $options['type_label'],
            'venue_label' => $options['venue_label'],
            'language_label' => $options['language_label'],
            'publish_up_label' => $options['publish_up'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    /**
     * Build a preview from a Special Days CSV upload.
     *
     * @param   array  $file     Uploaded file info.
     * @param   array  $options  Import defaults.
     *
     * @return array
     */
    protected function buildSpecialDaysCsvPreview(array $file, array $options)
    {
        $rows = array();
        $records = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        $handle = fopen($file['tmp_name'], 'r');

        if (!$handle) {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        $firstChars = fread($handle, 3);
        $hasBom = strncmp($firstChars, $bom, 3) === 0;

        if (!$hasBom) {
            fseek($handle, 0);
        }

        $header = fgetcsv($handle, 10000, $separator, $delimiter);
        if (is_array($header) && count($header) === 1 && strpos((string) $header[0], ',') !== false && $separator !== ',') {
            $separator = ',';
            fseek($handle, $hasBom ? 3 : 0);
            $header = fgetcsv($handle, 10000, $separator, $delimiter);
        } elseif (is_array($header) && count($header) === 1 && strpos((string) $header[0], ';') !== false && $separator !== ';') {
            $separator = ';';
            fseek($handle, $hasBom ? 3 : 0);
            $header = fgetcsv($handle, 10000, $separator, $delimiter);
        }

        if ($header === false) {
            fclose($handle);
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        array_walk($header, 'jem_normalise_csv_utf8');
        $fields = $this->normaliseSpecialDaysCsvHeader($header);

        if (!$fields) {
            fclose($handle);
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        $line = 1;

        while (($raw = fgetcsv($handle, 10000, $separator, $delimiter)) !== false) {
            $line++;
            array_walk($raw, 'jem_normalise_csv_utf8');

            if (count(array_filter($raw, 'strlen')) === 0) {
                continue;
            }

            $data = array();
            foreach ($fields as $index => $field) {
                if ($field === null) {
                    continue;
                }
                $data[$field] = $raw[$index] ?? '';
            }

            $row = $this->normaliseSpecialDaysCsvRow($data, $options, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $records[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        fclose($handle);

        return array(
            'title' => $options['title'],
            'rows' => $rows,
            'records' => $records,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'day_type' => $options['day_type'],
            'replace' => (int) $options['replace'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    /**
     * Build a preview from a Special Days ICS upload.
     *
     * @param   array  $file     Uploaded file info.
     * @param   array  $options  Import defaults.
     *
     * @return array
     */
    protected function buildSpecialDaysIcsPreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        $events = $this->parseExternalIcsEvents($content);

        if (!$events) {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_NO_EVENTS'));
        }

        $rows = array();
        $records = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        $line = 0;

        foreach ($events as $event) {
            $line++;
            $row = $this->normaliseSpecialDaysIcsEvent($event, $options, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $records[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        return array(
            'title' => $options['title'],
            'rows' => $rows,
            'records' => $records,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'day_type' => $options['day_type'],
            'replace' => (int) $options['replace'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    /**
     * Empty Special Days preview payload.
     *
     * @param   array    $options  Import defaults.
     * @param   integer  $errors   Error count.
     * @param   string   $summary  Summary text.
     *
     * @return array
     */
    protected function emptySpecialDaysPreview(array $options, $errors, $summary)
    {
        return array(
            'title' => $options['title'],
            'rows' => array(),
            'records' => array(),
            'valid_count' => 0,
            'error_count' => (int) $errors,
            'skipped_count' => 0,
            'has_errors' => (int) $errors > 0,
            'day_type' => $options['day_type'],
            'replace' => (int) $options['replace'],
            'summary' => $summary,
        );
    }

    /**
     * Commit a stored Special Days preview.
     *
     * @param   string  $stateKey  User state key.
     * @param   string  $format    Source format label.
     *
     * @return void
     */
    protected function commitSpecialDaysPreview($stateKey, $format)
    {
        $app = Factory::getApplication();
        $preview = $app->getUserState($stateKey, null);

        if (empty($preview['records'])) {
            $msg = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_NO_PREVIEW');
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        $result = $this->storeSpecialDaysRecords($preview['records'], !empty($preview['replace']));
        $app->setUserState($stateKey, null);

        $msg = Text::sprintf('COM_JEM_SPECIAL_DAYS_IMPORT_RESULT', $result['added'], $result['updated'], $result['ignored'], $result['error']);
        $this->addImportLogEntry(
            'special_days',
            'Special Days ' . $format . ' import committed. Type of day: ' . ($preview['day_type'] ?? '-')
            . '. Added: ' . $result['added'] . ', updated: ' . $result['updated']
            . ', ignored: ' . $result['ignored'] . ', errors: ' . $result['error']
            . '. Preview rows: ' . count($preview['rows'] ?? array()) . '.',
            $result['error'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, $result['error'] ? 'warning' : 'message');
    }

    /**
     * Parse VEVENT blocks from an ICS document.
     *
     * @param   string  $content  Raw ICS content.
     *
     * @return array
     */
    protected function parseExternalIcsEvents($content)
    {
        $content = str_replace(array("\r\n", "\r"), "\n", (string) $content);
        $content = preg_replace("/\n[ \t]/", '', $content);
        $lines = explode("\n", $content);
        $events = array();
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (strcasecmp($line, 'BEGIN:VEVENT') === 0) {
                $current = array();
                continue;
            }

            if (strcasecmp($line, 'END:VEVENT') === 0) {
                if ($current !== null) {
                    $events[] = $current;
                }
                $current = null;
                continue;
            }

            if ($current === null || strpos($line, ':') === false) {
                continue;
            }

            list($left, $value) = explode(':', $line, 2);
            $parts = explode(';', $left);
            $name = strtoupper(array_shift($parts));
            $params = array();

            foreach ($parts as $part) {
                if (strpos($part, '=') === false) {
                    continue;
                }

                list($paramName, $paramValue) = explode('=', $part, 2);
                $params[strtoupper($paramName)] = trim($paramValue, '"');
            }

            if (!isset($current[$name])) {
                $current[$name] = array();
            }

            $current[$name][] = array(
                'value' => $this->decodeExternalIcsText($value),
                'raw' => $value,
                'params' => $params,
            );
        }

        return $events;
    }

    /**
     * Normalise one ICS VEVENT into a JEM event import record.
     *
     * @param   array    $event    Parsed VEVENT.
     * @param   array    $options  Import defaults.
     * @param   integer  $line     VEVENT number.
     *
     * @return array
     */
    protected function normaliseExternalIcsEvent(array $event, array $options, $line)
    {
        $notes = array();
        $title = trim((string) $this->getExternalIcsValue($event, 'SUMMARY'));
        $description = trim((string) $this->getExternalIcsValue($event, 'DESCRIPTION'));
        $location = trim((string) $this->getExternalIcsValue($event, 'LOCATION'));
        $uid = trim((string) $this->getExternalIcsValue($event, 'UID'));
        $start = $this->normaliseExternalIcsDateProperty($this->getExternalIcsProperty($event, 'DTSTART'));
        $end = $this->normaliseExternalIcsDateProperty($this->getExternalIcsProperty($event, 'DTEND'));

        if ($end['date'] && !$end['time'] && $start['date'] && !$start['time']) {
            $endTimestamp = strtotime($end['date']);
            $startTimestamp = strtotime($start['date']);

            if ($endTimestamp && $startTimestamp && $endTimestamp > $startTimestamp) {
                $end['date'] = date('Y-m-d', strtotime('-1 day', $endTimestamp));
            }
        }

        if ($options['mode'] === 'openday') {
            $start['time'] = null;
            $end['time'] = null;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_NOTE_FORCED_OPEN_DAY');
        }

        if ($uid !== '') {
            $notes[] = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_ICS_UID_NOTE', $uid);
        }

        if ($location !== '' && empty($options['locid'])) {
            $notes[] = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_ICS_LOCATION_NOTE', $location);
        }

        if (!empty($start['utc']) || !empty($end['utc'])) {
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_UTC_NOTE');
        }

        if ($end['time'] && !$start['time']) {
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_END_TIME_WITHOUT_START');
        }

        if ($end['time'] && !$end['date']) {
            $end['date'] = $start['date'];
        }

        $valid = true;

        if ($title === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_MISSING_TITLE');
        }

        if (!$start['date']) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_MISSING_DATE');
        }

        if ($start['date'] && $end['date'] && $end['date'] < $start['date']) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_END_BEFORE_START');
        }

        if ($start['date'] && $end['date'] && $start['date'] === $end['date'] && $start['time'] && $end['time'] && $end['time'] < $start['time']) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_END_BEFORE_START');
        }

        $status = $valid ? Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK') : Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR');

        return array(
            'valid' => $valid,
            'record' => array(
                $title,
                $start['date'],
                $end['date'],
                $start['time'],
                $end['time'],
                $description,
                '',
                '{}',
                (int) $options['published'],
                (string) $options['publish_up'],
                !empty($options['type_id']) ? (int) $options['type_id'] : null,
                !empty($options['locid']) ? (int) $options['locid'] : null,
                (string) $options['language'],
                (string) (int) $options['catid'],
            ),
            'preview' => array(
                'status' => $status,
                'title' => $title !== '' ? $title : Text::sprintf('COM_JEM_IMPORT_EXTERNAL_UNTITLED_ROW', $line),
                'date_label' => trim(($start['date'] ?: '-') . ($end['date'] ? ' - ' . $end['date'] : '')),
                'time_label' => trim(($start['time'] ?: '-') . ($end['time'] ? ' - ' . $end['time'] : '')),
                'notes' => $notes,
            ),
        );
    }

    /**
     * Decode escaped ICS text values.
     *
     * @param   string  $value  Encoded ICS value.
     *
     * @return string
     */
    protected function decodeExternalIcsText($value)
    {
        return strtr((string) $value, array(
            '\\n' => "\n",
            '\\N' => "\n",
            '\\,' => ',',
            '\\;' => ';',
            '\\\\' => '\\',
        ));
    }

    /**
     * Get the first parsed ICS property.
     *
     * @param   array   $event  Parsed VEVENT.
     * @param   string  $name   Property name.
     *
     * @return array|null
     */
    protected function getExternalIcsProperty(array $event, $name)
    {
        $name = strtoupper($name);

        return $event[$name][0] ?? null;
    }

    /**
     * Get the first parsed ICS property value.
     *
     * @param   array   $event  Parsed VEVENT.
     * @param   string  $name   Property name.
     *
     * @return string|null
     */
    protected function getExternalIcsValue(array $event, $name)
    {
        $property = $this->getExternalIcsProperty($event, $name);

        return $property['value'] ?? null;
    }

    /**
     * Normalise an ICS date/time property into JEM date and time values.
     *
     * @param   array|null  $property  Parsed ICS property.
     *
     * @return array
     */
    protected function normaliseExternalIcsDateProperty($property)
    {
        $empty = array('date' => null, 'time' => null, 'utc' => false);

        if (!$property || empty($property['raw'])) {
            return $empty;
        }

        $value = trim((string) $property['raw']);
        $isDate = isset($property['params']['VALUE']) && strtoupper($property['params']['VALUE']) === 'DATE';

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $match)) {
            return array('date' => $match[1] . '-' . $match[2] . '-' . $match[3], 'time' => null, 'utc' => false);
        }

        if ($isDate) {
            return $empty;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?(Z)?$/', $value, $match)) {
            return array(
                'date' => $match[1] . '-' . $match[2] . '-' . $match[3],
                'time' => $match[4] . ':' . $match[5] . ':' . (!empty($match[6]) ? $match[6] : '00'),
                'utc' => !empty($match[7]),
            );
        }

        $timestamp = strtotime($value);

        return array(
            'date' => $timestamp ? date('Y-m-d', $timestamp) : null,
            'time' => $timestamp ? date('H:i:s', $timestamp) : null,
            'utc' => substr($value, -1) === 'Z',
        );
    }

    /**
     * Normalise friendly CSV headers to JEM import fields.
     *
     * @param   array  $header  CSV header row.
     *
     * @return array
     */
    protected function normaliseExternalCsvHeader(array $header)
    {
        $aliases = array(
            'title' => 'title',
            'name' => 'title',
            'event' => 'title',
            'event_title' => 'title',
            'date' => 'dates',
            'dates' => 'dates',
            'start' => 'dates',
            'start_date' => 'dates',
            'end' => 'enddates',
            'end_date' => 'enddates',
            'enddates' => 'enddates',
            'time' => 'times',
            'start_time' => 'times',
            'times' => 'times',
            'end_time' => 'endtimes',
            'endtimes' => 'endtimes',
            'datetime' => 'start_datetime',
            'start_datetime' => 'start_datetime',
            'end_datetime' => 'end_datetime',
            'description' => 'introtext',
            'introtext' => 'introtext',
            'text' => 'introtext',
        );

        $fields = array();

        foreach ($header as $column) {
            $key = strtolower(trim((string) $column));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim($key, '_');
            $fields[] = $aliases[$key] ?? null;
        }

        return $fields;
    }

    /**
     * Normalise a friendly CSV row into a JEM event import record.
     *
     * @param   array    $data     Row data keyed by normalized fields.
     * @param   array    $options  Import defaults.
     * @param   integer  $line     CSV line number.
     *
     * @return array
     */
    protected function normaliseExternalCsvRow(array $data, array $options, $line)
    {
        $notes = array();
        $title = trim((string) ($data['title'] ?? ''));
        $startDate = $this->normaliseExternalCsvDate($data['dates'] ?? '');
        $endDate = $this->normaliseExternalCsvDate($data['enddates'] ?? '');
        $startTime = $this->normaliseExternalCsvTime($data['times'] ?? '');
        $endTime = $this->normaliseExternalCsvTime($data['endtimes'] ?? '');

        if (!empty($data['start_datetime'])) {
            $startParts = $this->normaliseExternalCsvDateTime($data['start_datetime']);
            $startDate = $startParts['date'] ?: $startDate;
            $startTime = $startParts['time'] ?: $startTime;
        }

        if (!empty($data['end_datetime'])) {
            $endParts = $this->normaliseExternalCsvDateTime($data['end_datetime']);
            $endDate = $endParts['date'] ?: $endDate;
            $endTime = $endParts['time'] ?: $endTime;
        }

        if ($options['mode'] === 'openday') {
            $startTime = null;
            $endTime = null;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_NOTE_FORCED_OPEN_DAY');
        }

        if ($endTime && !$startTime) {
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_END_TIME_WITHOUT_START');
        }

        if ($endTime && !$endDate) {
            $endDate = $startDate;
        }

        $valid = true;

        if ($title === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_MISSING_TITLE');
        }

        if (!$startDate) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_MISSING_DATE');
        }

        if ($startDate && $endDate && $endDate < $startDate) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_END_BEFORE_START');
        }

        if ($startDate && $endDate && $startDate === $endDate && $startTime && $endTime && $endTime < $startTime) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_END_BEFORE_START');
        }

        $status = $valid ? Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK') : Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR');

        return array(
            'valid' => $valid,
            'record' => array(
                $title,
                $startDate,
                $endDate,
                $startTime,
                $endTime,
                trim((string) ($data['introtext'] ?? '')),
                '',
                '{}',
                (int) $options['published'],
                (string) $options['publish_up'],
                !empty($options['type_id']) ? (int) $options['type_id'] : null,
                !empty($options['locid']) ? (int) $options['locid'] : null,
                (string) $options['language'],
                (string) (int) $options['catid'],
            ),
            'preview' => array(
                'status' => $status,
                'title' => $title !== '' ? $title : Text::sprintf('COM_JEM_IMPORT_EXTERNAL_UNTITLED_ROW', $line),
                'date_label' => trim(($startDate ?: '-') . ($endDate ? ' - ' . $endDate : '')),
                'time_label' => trim(($startTime ?: '-') . ($endTime ? ' - ' . $endTime : '')),
                'notes' => $notes,
            ),
        );
    }

    protected function normaliseExternalCsvDate($date)
    {
        $date = trim((string) $date);

        if ($date === '' || strtoupper($date) === 'NULL' || $date === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    protected function normaliseExternalCsvTime($time)
    {
        $time = trim((string) $time);

        if ($time === '' || strtoupper($time) === 'NULL') {
            return null;
        }

        $timestamp = strtotime($time);

        return $timestamp ? date('H:i:s', $timestamp) : null;
    }

    protected function normaliseExternalCsvDateTime($value)
    {
        $value = trim((string) $value);
        $timestamp = $value !== '' ? strtotime($value) : false;

        return array(
            'date' => $timestamp ? date('Y-m-d', $timestamp) : null,
            'time' => $timestamp ? date('H:i:s', $timestamp) : null,
        );
    }

    protected function normaliseExternalPublishUp($value)
    {
        $value = trim((string) $value);
        $timestamp = $value !== '' ? strtotime($value) : false;

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : Factory::getDate()->toSql();
    }

    protected function normaliseSpecialDaysCsvHeader(array $header)
    {
        $aliases = array(
            'name' => 'title',
            'type' => 'day_type',
            'daytype' => 'day_type',
            'day_type' => 'day_type',
            'start' => 'start_date',
            'startdate' => 'start_date',
            'start_date' => 'start_date',
            'date' => 'start_date',
            'end' => 'end_date',
            'enddate' => 'end_date',
            'end_date' => 'end_date',
            'weekday' => 'weekdays',
            'weekdays' => 'weekdays',
            'desc' => 'description',
            'text' => 'description',
        );
        $allowed = array('id', 'title', 'alias', 'day_type', 'start_date', 'end_date', 'weekdays', 'country', 'region', 'city', 'description', 'published', 'ordering');
        $fields = array();

        foreach ($header as $column) {
            $key = strtolower(trim((string) $column));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim($key, '_');
            $key = $aliases[$key] ?? $key;
            $fields[] = in_array($key, $allowed, true) ? $key : null;
        }

        return array_filter($fields, static function ($field) {
            return $field !== null;
        }) ? $fields : array();
    }

    protected function normaliseSpecialDaysCsvRow(array $data, array $options, $line)
    {
        $notes = array();
        $title = trim((string) ($data['title'] ?? ''));
        $dayType = trim((string) ($data['day_type'] ?? ''));
        $fallbackType = trim((string) ($options['day_type'] ?? ''));

        if ($dayType === '') {
            $dayType = $fallbackType;
            $notes[] = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_NOTE_FALLBACK_TYPE');
        }

        $startDate = $this->normaliseSpecialDaysDate($data['start_date'] ?? '');
        $endDate = $this->normaliseSpecialDaysDate($data['end_date'] ?? '');

        if ($startDate && !$endDate) {
            $endDate = $startDate;
        }

        $weekdays = $this->normaliseSpecialDaysWeekdays($data['weekdays'] ?? '');
        $valid = true;

        if ($title === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_MISSING_TITLE');
        }

        if ($dayType === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_SPECIAL_DAY_ERROR_TYPE_REQUIRED');
        }

        if (!$startDate || !$endDate) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_SPECIAL_DAY_ERROR_DATE_RANGE_REQUIRED');
        }

        if ($startDate && $endDate && $endDate < $startDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
            $notes[] = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_NOTE_DATE_RANGE_SWAPPED');
        }

        $record = array(
            'id' => isset($data['id']) ? (int) $data['id'] : 0,
            'title' => $title,
            'alias' => trim((string) ($data['alias'] ?? '')),
            'day_type' => $dayType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'weekdays' => $weekdays,
            'country' => trim((string) ($data['country'] ?? '')),
            'region' => trim((string) ($data['region'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'published' => isset($data['published']) && trim((string) $data['published']) !== '' ? (int) $data['published'] : 1,
            'ordering' => isset($data['ordering']) ? (int) $data['ordering'] : 0,
        );

        return array(
            'valid' => $valid,
            'record' => $record,
            'preview' => array(
                'status' => $valid ? Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK') : Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR'),
                'title' => $title !== '' ? $title : Text::sprintf('COM_JEM_IMPORT_EXTERNAL_UNTITLED_ROW', $line),
                'date_label' => trim(($startDate ?: '-') . ($endDate && $endDate !== $startDate ? ' - ' . $endDate : '')),
                'day_type' => $dayType,
                'description' => $record['description'],
                'notes' => $notes,
            ),
        );
    }

    protected function normaliseSpecialDaysIcsEvent(array $event, array $options, $line)
    {
        $notes = array();
        $title = trim((string) $this->getExternalIcsValue($event, 'SUMMARY'));
        $description = trim((string) $this->getExternalIcsValue($event, 'DESCRIPTION'));
        $start = $this->normaliseExternalIcsDateProperty($this->getExternalIcsProperty($event, 'DTSTART'));
        $end = $this->normaliseExternalIcsDateProperty($this->getExternalIcsProperty($event, 'DTEND'));

        if ($end['date'] && !$end['time'] && $start['date'] && !$start['time']) {
            $endTimestamp = strtotime($end['date']);
            $startTimestamp = strtotime($start['date']);

            if ($endTimestamp && $startTimestamp && $endTimestamp > $startTimestamp) {
                $end['date'] = date('Y-m-d', strtotime('-1 day', $endTimestamp));
            }
        }

        if ($start['date'] && !$end['date']) {
            $end['date'] = $start['date'];
        }

        $dayType = trim((string) ($options['day_type'] ?? ''));
        $valid = true;

        if ($title === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ERROR_MISSING_TITLE');
        }

        if ($dayType === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_SPECIAL_DAY_ERROR_TYPE_REQUIRED');
        }

        if (!$start['date'] || !$end['date']) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_SPECIAL_DAY_ERROR_DATE_RANGE_REQUIRED');
        }

        if (!empty($start['utc']) || !empty($end['utc'])) {
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_UTC_NOTE');
        }

        $record = array(
            'id' => 0,
            'title' => $title,
            'alias' => '',
            'day_type' => $dayType,
            'start_date' => $start['date'],
            'end_date' => $end['date'],
            'weekdays' => '',
            'country' => '',
            'region' => '',
            'city' => '',
            'description' => $description,
            'published' => 1,
            'ordering' => 0,
        );

        return array(
            'valid' => $valid,
            'record' => $record,
            'preview' => array(
                'status' => $valid ? Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK') : Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR'),
                'title' => $title !== '' ? $title : Text::sprintf('COM_JEM_IMPORT_EXTERNAL_UNTITLED_ROW', $line),
                'date_label' => trim(($start['date'] ?: '-') . ($end['date'] && $end['date'] !== $start['date'] ? ' - ' . $end['date'] : '')),
                'day_type' => $dayType,
                'description' => $description,
                'notes' => $notes,
            ),
        );
    }

    protected function normaliseSpecialDaysDate($date)
    {
        $date = trim((string) $date);

        if ($date === '' || strtoupper($date) === 'NULL' || $date === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    protected function normaliseSpecialDaysWeekdays($weekdays)
    {
        $map = array(
            'sun' => 0, 'sunday' => 0,
            'mon' => 1, 'monday' => 1,
            'tue' => 2, 'tues' => 2, 'tuesday' => 2,
            'wed' => 3, 'wednesday' => 3,
            'thu' => 4, 'thur' => 4, 'thurs' => 4, 'thursday' => 4,
            'fri' => 5, 'friday' => 5,
            'sat' => 6, 'saturday' => 6,
        );
        $values = preg_split('/[,\|; ]+/', strtolower((string) $weekdays));
        $result = array();

        foreach ($values as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $weekday = is_numeric($value) ? (int) $value : ($map[$value] ?? null);
            if ($weekday !== null && $weekday >= 0 && $weekday <= 6) {
                $result[] = $weekday;
            }
        }

        return implode(',', array_values(array_unique($result)));
    }

    protected function storeSpecialDaysRecords(array $records, $replace)
    {
        $result = array('added' => 0, 'updated' => 0, 'ignored' => 0, 'error' => 0);
        $now = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        foreach ($records as $record) {
            $table = Table::getInstance('jem_special_days', '');
            $id = (int) ($record['id'] ?? 0);
            $exists = false;

            if ($replace && $id > 0) {
                $exists = $table->load($id);
            }

            if (!$replace || !$exists) {
                $record['id'] = 0;
                $record['created'] = $now;
                $record['created_by'] = $userId;
            } else {
                $record['modified'] = $now;
                $record['modified_by'] = $userId;
            }

            if (!$table->bind($record) || !$table->check() || !$table->store()) {
                $result['error']++;
                continue;
            }

            if ($replace && $exists) {
                $result['updated']++;
            } else {
                $result['added']++;
            }
        }

        return $result;
    }

    protected function getCategoryLabel($catid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('catname'))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('id') . ' = ' . (int) $catid);
        $db->setQuery($query);

        return (string) $db->loadResult();
    }

    protected function getTypeLabel($typeId)
    {
        if (!$typeId) {
            return Text::_('JNONE');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('id') . ' = ' . (int) $typeId);
        $db->setQuery($query);

        return (string) ($db->loadResult() ?: Text::_('JNONE'));
    }

    protected function getVenueLabel($venueId)
    {
        if (!$venueId) {
            return Text::_('JNONE');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('venue'))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('id') . ' = ' . (int) $venueId);
        $db->setQuery($query);

        return (string) ($db->loadResult() ?: Text::_('JNONE'));
    }

    protected function getLanguageLabel($language)
    {
        $language = trim((string) $language);

        if ($language === '' || $language === '*') {
            return Text::_('JALL');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($language));
        $db->setQuery($query);

        return (string) ($db->loadResult() ?: $language);
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
