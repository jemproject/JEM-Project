<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importencoding.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importcatalog.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importsecurity.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importxlsx.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importvenue.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importpreview.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/csvmetadata.php';

/**
 * JEM Component Import Controller
 *
 * @package JEM
 *
 */
class JemControllerImport extends BaseController
{
    private const EXTERNAL_IMPORT_BATCH_SIZE = 100;
    protected static $importLoggers = array();
    protected $externalSourceWarnings = array();
    protected $externalSourceWarningCount = 0;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Load an import catalog entry and create/select its mapping profile.
     *
     * @return void
     */
    public function loadCatalogItem()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $id = $app->input->post->getCmd('catalog_id', '');
        $entry = JemImportCatalogHelper::getEntry($id);

        if (!$entry) {
            $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_LOAD_ERROR'), 'error');
            return;
        }

        $context = JemImportCatalogHelper::getContext($entry['type']);
        $tab = JemImportCatalogHelper::getTab($entry['type']);
        $profileId = 0;

        if (!empty($entry['mapping']) && !empty($entry['profile'])) {
            $profile = $this->saveExternalImportProfile(
                $context,
                $entry['format'],
                $entry['profile'],
                (array) $entry['mapping'],
                array(
                    'static_values' => (array) ($entry['static_values'] ?? array()),
                    'source_mode' => 'url',
                    'source_url' => (string) ($entry['source'] ?? ''),
                    'source_name' => (string) ($entry['source'] ?? ''),
                    '_preserve_existing' => true,
                )
            );

            if ($profile) {
                $profileId = (int) $profile['id'];
            }
        }

        $entry['profile_id'] = $profileId;
        $app->setUserState('com_jem.import.catalog.selected', $entry);

        if ($profileId > 0) {
            if ($context === 'venues') {
                $app->setUserState('com_jem.import.external_venue_import.selected_profile_id', $profileId);
            } elseif ($context === 'specialdays') {
                $app->setUserState('com_jem.import.specialdays_import.selected_profile_id', $profileId);
            } else {
                $app->setUserState('com_jem.import.external_import.selected_profile_id', $profileId);
            }
        }

        $message = $profileId > 0
            ? Text::sprintf('COM_JEM_IMPORT_CATALOG_LOADED_WITH_PROFILE', $entry['title'], $entry['profile'])
            : Text::sprintf('COM_JEM_IMPORT_CATALOG_LOADED', $entry['title']);

        $this->setRedirect('index.php?option=com_jem&view=import&profile_selection=1#' . $tab, $message);
    }

    /**
     * Validate and activate a custom Import Catalog XML file.
     *
     * @return void
     */
    public function uploadCatalog()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanImport();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $file = $app->input->files->get('FileImportCatalog', array(), 'array');

        if (empty($file['name']) || !empty($file['error']) || !is_uploaded_file($file['tmp_name'] ?? '')) {
            $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_CUSTOM_UPLOAD_ERROR'), 'error');
            return;
        }

        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'xml'
            || (int) ($file['size'] ?? 0) <= 0
            || (int) ($file['size'] ?? 0) > JemImportCatalogHelper::MAX_CATALOG_SIZE) {
            $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_CUSTOM_FILE_INVALID'), 'error');
            return;
        }

        $xmlSource = @file_get_contents($file['tmp_name']);
        $validationError = '';

        if (!is_string($xmlSource) || !JemImportCatalogHelper::validateCatalogXml($xmlSource, $validationError)) {
            JemHelper::addLogEntry('Custom Import Catalog rejected: ' . ($validationError ?: 'invalid XML'), __METHOD__, Log::WARNING);
            $this->setRedirect(
                'index.php?option=com_jem&view=import#download-lists',
                Text::sprintf('COM_JEM_IMPORT_CATALOG_CUSTOM_VALIDATION_ERROR', $validationError ?: 'invalid_xml'),
                'error'
            );
            return;
        }

        $path = JemImportCatalogHelper::getCatalogPath();
        $directory = dirname($path);

        if ((!is_dir($directory) && !Folder::create($directory)) || !File::write($path, $xmlSource)) {
            $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_CUSTOM_SAVE_ERROR'), 'error');
            return;
        }

        JemHelper::addLogEntry('Custom Import Catalog activated: ' . JemImportCatalogHelper::getCustomCatalogSource(), __METHOD__, Log::INFO);
        $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_CUSTOM_LOADED'));
    }

    /**
     * Remove the custom catalog and return to the official server catalog.
     *
     * @return void
     */
    public function removeCustomCatalog()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanImport();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $path = JemImportCatalogHelper::getCatalogPath();

        if (is_file($path) && !File::delete($path)) {
            $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_CUSTOM_REMOVE_ERROR'), 'error');
            return;
        }

        JemHelper::addLogEntry('Custom Import Catalog removed; official server catalog restored.', __METHOD__, Log::INFO);
        $this->setRedirect('index.php?option=com_jem&view=import#download-lists', Text::_('COM_JEM_IMPORT_CATALOG_OFFICIAL_RESTORED'));
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
     * Validate and save the policy shared by all JEM import operations.
     *
     * @return void
     */
    public function saveSecuritySettings()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanImport();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $posted = $app->input->post->get('import_security', array(), 'array');
        $invalidTags = array();
        $normalisedTags = JemImportSecurityHelper::normaliseTagList(
            $posted['additional_blocked_tags'] ?? '',
            $invalidTags
        );

        if ($invalidTags) {
            $this->setRedirect(
                'index.php?option=com_jem&view=import#import-security',
                Text::sprintf('COM_JEM_SETTINGS_SECURITY_INVALID_TAGS', implode(', ', $invalidTags)),
                'error'
            );
            return;
        }

        $additionalTags = $normalisedTags === '' ? array() : preg_split('/,\s*/', $normalisedTags);
        $additionalTags = array_values(array_diff($additionalTags, JemImportSecurityHelper::getCoreBlockedTags()));

        if (in_array('iframe', $additionalTags, true)) {
            $this->setRedirect(
                'index.php?option=com_jem&view=import#import-security',
                Text::_('COM_JEM_SETTINGS_SECURITY_IFRAME_POLICY_ERROR'),
                'error'
            );
            return;
        }

        $invalidHosts = array();
        $trustedHosts = JemImportSecurityHelper::normaliseHostList(
            $posted['trusted_iframe_hosts'] ?? '',
            $invalidHosts
        );

        if ($invalidHosts) {
            $this->setRedirect(
                'index.php?option=com_jem&view=import#import-security',
                Text::sprintf('COM_JEM_SETTINGS_SECURITY_INVALID_HOSTS', implode(', ', $invalidHosts)),
                'error'
            );
            return;
        }

        $allowTrustedIframes = !empty($posted['allow_trusted_iframes']) ? 1 : 0;

        if ($allowTrustedIframes && $trustedHosts === '') {
            $this->setRedirect(
                'index.php?option=com_jem&view=import#import-security',
                Text::_('COM_JEM_SETTINGS_SECURITY_TRUSTED_HOSTS_REQUIRED'),
                'error'
            );
            return;
        }

        $config = JemConfig::getInstance();
        $configRegistry = $config->toRegistry();
        $global = new Registry($configRegistry->get('globalattribs', array()));
        $previous = array(
            'additional_blocked_tags' => (string) $global->get('import_additional_blocked_tags', ''),
            'allow_trusted_iframes' => (int) $global->get('import_allow_trusted_iframes', 0),
            'trusted_iframe_hosts' => (string) $global->get('import_trusted_iframe_hosts', ''),
        );
        $current = array(
            'additional_blocked_tags' => implode(', ', $additionalTags),
            'allow_trusted_iframes' => $allowTrustedIframes,
            'trusted_iframe_hosts' => $trustedHosts,
        );

        $global->set('import_additional_blocked_tags', $current['additional_blocked_tags']);
        $global->set('import_allow_trusted_iframes', $current['allow_trusted_iframes']);
        $global->set('import_trusted_iframe_hosts', $current['trusted_iframe_hosts']);
        $configRegistry->set('globalattribs', $global->toArray());

        if (!$config->store()) {
            $this->setRedirect(
                'index.php?option=com_jem&view=import#import-security',
                Text::_('COM_JEM_IMPORT_SECURITY_SETTINGS_SAVE_ERROR'),
                'error'
            );
            return;
        }

        JemImportSecurityHelper::resetPolicyCache();

        if ($previous !== $current) {
            JemHelper::addLogEntry(
                'Import security settings updated: additional_tags=[' . $current['additional_blocked_tags']
                . '], trusted_iframes=' . ($current['allow_trusted_iframes'] ? 'enabled' : 'disabled')
                . ', trusted_hosts=[' . str_replace(array("\r", "\n"), ',', $current['trusted_iframe_hosts']) . ']',
                __METHOD__,
                Log::INFO
            );
        }

        $this->setRedirect(
            'index.php?option=com_jem&view=import#import-security',
            Text::_('COM_JEM_IMPORT_SECURITY_SETTINGS_SAVED')
        );
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

        try {
            $preview = $this->buildExternalCsvPreview($file, $options);
        } catch (RuntimeException $e) {
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }
        $app->setUserState('com_jem.import.external_csv.preview', $preview);

        $this->addImportLogEntry(
            'external_csv',
            'External CSV preview for file "' . $file['name'] . '". '
            . 'Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $app->setUserState('com_jem.import.active_preview', 'events');
        $this->setRedirect('index.php?option=com_jem&view=import&profile_selection=1#event-import', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
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

        $input = $app->input;
        $postedMapping = $this->getPostedImportMapping('external_import_mapping');
        $rawPostedMapping = $input->post->get('external_import_mapping', null, 'array');
        $postedStaticValues = $this->getPostedImportStaticValues('external_import_static_values');
        $rawPostedStaticValues = $input->post->get('external_import_static_values', null, 'array');
        if ((is_array($rawPostedMapping) || is_array($rawPostedStaticValues)) && !empty($preview['source_records']) && strtolower((string) ($preview['format'] ?? '')) !== 'ics') {
            $options = array(
                'catid' => $input->post->getInt('external_import_catid', (int) ($preview['catid'] ?? 0)),
                'category_label' => $preview['category_label'] ?? '',
                'mode' => $input->post->getCmd('external_import_mode', 'standard'),
                'type_id' => $input->post->getInt('external_import_type_id', (int) ($preview['type_id'] ?? 0)),
                'locid' => $input->post->getInt('external_import_locid', (int) ($preview['locid'] ?? 0)),
                'published' => $input->post->getInt('external_import_published', 1),
                'publish_up' => $this->normaliseExternalPublishUp($input->post->getString('external_import_publish_up', (string) ($preview['publish_up'] ?? ''))),
                'language' => $input->post->getCmd('external_import_language', (string) ($preview['language'] ?? '*')),
                'mapping' => $postedMapping,
                'static_values' => $postedStaticValues,
            );
            $options['type_label'] = $this->getTypeLabel($options['type_id']);
            $options['venue_label'] = $this->getVenueLabel($options['locid']);
            $options['language_label'] = $this->getLanguageLabel($options['language']);
            $options['record_fields'] = $this->getExternalEventRecordFields($postedMapping);
            try {
                $preview = $this->buildExternalStructuredPreviewFromRecords((array) $preview['source_records'], $options, (array) ($preview['source_fields'] ?? array()));
                $preview['format'] = strtolower((string) ($app->getUserState('com_jem.import.external_import.preview', array())['format'] ?? 'csv'));
            } catch (RuntimeException $e) {
                $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
                $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
                $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
                return;
            }
        }

        $fields = !empty($preview['record_fields']) && is_array($preview['record_fields'])
            ? $preview['record_fields']
            : $this->getExternalEventRecordFields();
        $records = $preview['records'];
        $model = $this->getModel('import');
        ob_start();
        try {
            $result = $model->eventsimport($fields, $records, false);
        } catch (RuntimeException $e) {
            ob_end_clean();
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }
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

        try {
            $preview = $this->buildExternalIcsPreview($file, $options);
        } catch (RuntimeException $e) {
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_ics', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }
        $app->setUserState('com_jem.import.external_ics.preview', $preview);

        $this->addImportLogEntry(
            'external_ics',
            'External ICS preview for file "' . $file['name'] . '". '
            . 'Valid events: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $app->setUserState('com_jem.import.active_preview', 'events');
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
        try {
            $result = $model->eventsimport($fields, $preview['records'], false);
        } catch (RuntimeException $e) {
            ob_end_clean();
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_ics', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }
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
        $sourceMode = $input->post->getCmd('external_import_source_mode', 'file');
        $catalogEntry = $this->getSelectedImportCatalogEntry('events');
        $postedSourceUrl = trim($input->post->getString('external_import_source_url', ''));
        $catalogSource = $sourceMode === 'url' ? ($postedSourceUrl ?: (string) ($catalogEntry['source'] ?? '')) : '';
        $downloadedFile = '';
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $hasUpload = !empty($file['name']) && empty($file['error']) && is_uploaded_file($file['tmp_name']);

        if ($catid <= 0) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_CATEGORY_REQUIRED_ERROR');
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        if ($catalogSource !== '') {
            try {
                $file = $this->downloadExternalImportSource($catalogSource, array('csv', 'json', 'xml', 'ics'), (string) ($catalogEntry['format'] ?? ''));
                $downloadedFile = (string) $file['tmp_name'];
                $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                $hasUpload = true;
            } catch (RuntimeException $e) {
                $msg = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_URL_ERROR', $e->getMessage());
                $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
                $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
                return;
            }
        }

        $existingPreview = $app->getUserState('com_jem.import.external_import.preview', null);
        if (!$hasUpload && $sourceMode !== 'url' && !empty($existingPreview['source_records']) && !empty($existingPreview['format'])) {
            $extension = strtolower((string) $existingPreview['format']);
        }

        if ((!$hasUpload && empty($existingPreview['source_records'])) || !in_array($extension, array('csv', 'json', 'xml', 'ics'), true)) {
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
            'source_mode' => $sourceMode === 'url' ? 'url' : 'file',
            'source_url' => $catalogSource,
            'source_name' => $catalogSource !== '' ? $catalogSource : ($hasUpload ? basename((string) ($file['name'] ?? '')) : basename((string) ($existingPreview['source_name'] ?? ''))),
        );
        $selectedProfileId = $input->post->getInt('external_import_profile_id', 0);
        $app->setUserState('com_jem.import.external_import.selected_profile_id', $selectedProfileId);
        $profile = $this->getExternalImportProfile($selectedProfileId, $extension, 'events');
        $postedMapping = $this->getPostedImportMapping('external_import_mapping');
        $postedStaticValues = $this->getPostedImportStaticValues('external_import_static_values');
        $options['mapping'] = $postedMapping ?: ($profile['mapping'] ?? array());
        $options['static_values'] = $postedStaticValues ?: ($profile['options']['static_values'] ?? array());
        $options['record_fields'] = $this->getExternalEventRecordFields($options['mapping']);
        $options['profile_id'] = (int) ($profile['id'] ?? 0);
        $options['profile_title'] = (string) ($profile['title'] ?? '');
        $options['type_label'] = $this->getTypeLabel($options['type_id']);
        $options['venue_label'] = $this->getVenueLabel($options['locid']);
        $options['language_label'] = $this->getLanguageLabel($options['language']);

        try {
            if (!$hasUpload && $sourceMode !== 'url' && $extension !== 'ics') {
                $preview = $this->buildExternalStructuredPreviewFromRecords(
                    (array) ($existingPreview['source_records'] ?? array()),
                    $options,
                    (array) ($existingPreview['source_fields'] ?? array())
                );
            } elseif ($extension === 'ics') {
                $preview = $this->buildExternalIcsPreview($file, $options);
            } elseif ($extension === 'json') {
                $preview = $this->buildExternalJsonPreview($file, $options);
            } elseif ($extension === 'xml') {
                $preview = $this->buildExternalXmlPreview($file, $options);
            } else {
                $preview = $this->buildExternalCsvPreview($file, $options);
            }
        } catch (RuntimeException $e) {
            if ($downloadedFile !== '' && is_file($downloadedFile)) {
                unlink($downloadedFile);
            }

            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }

        if ($downloadedFile !== '' && is_file($downloadedFile)) {
            unlink($downloadedFile);
        }

        $preview['format'] = $extension;
        $preview['source_name'] = $catalogSource !== '' ? $catalogSource : ($hasUpload ? (string) ($file['name'] ?? '') : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'));
        $preview['source_mode'] = $catalogSource !== '' ? 'url' : 'file';
        $preview['source_url'] = $catalogSource;
        $preview['mapping'] = (array) ($preview['mapping'] ?? $options['mapping']);
        $preview['static_values'] = (array) ($preview['static_values'] ?? $options['static_values']);
        $preview['profile_id'] = (int) $options['profile_id'];
        $preview['profile_title'] = (string) $options['profile_title'];

        $profileTitle = $input->post->getString('external_import_profile_title', '');
        if ($extension !== 'ics' && $input->post->getInt('external_import_profile_save', 0)) {
            $savedProfile = $this->saveExternalImportProfile(
                'events',
                $extension,
                $profileTitle,
                (array) $preview['mapping'],
                array_merge($options, array('static_values' => (array) ($preview['static_values'] ?? array())))
            );

            if ($savedProfile) {
                $preview['profile_id'] = (int) $savedProfile['id'];
                $preview['profile_title'] = (string) $savedProfile['title'];
            }
        }

        $app->setUserState('com_jem.import.external_import.preview', $preview);
        $app->setUserState('com_jem.import.external_csv.preview', null);
        $app->setUserState('com_jem.import.external_ics.preview', null);

        $logKey = $extension === 'ics' ? 'external_ics' : 'external_csv';
        $fileName = $catalogSource !== '' ? $catalogSource : ($hasUpload ? $file['name'] : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'));
        $this->addImportLogEntry(
            $logKey,
            'External ' . strtoupper($extension) . ' preview for file "' . $fileName . '". Parser: ' . strtoupper($extension) . '. '
            . 'Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $app->setUserState('com_jem.import.active_preview', 'events');
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

        $input = $app->input;
        $format = strtolower($preview['format'] ?? 'csv');
        $postedMapping = $this->getPostedImportMapping('external_import_mapping');
        $rawPostedMapping = $input->post->get('external_import_mapping', null, 'array');
        $postedStaticValues = $this->getPostedImportStaticValues('external_import_static_values');
        $rawPostedStaticValues = $input->post->get('external_import_static_values', null, 'array');

        if ($format !== 'ics' && (is_array($rawPostedMapping) || is_array($rawPostedStaticValues)) && !empty($preview['source_records'])) {
            $options = array(
                'catid' => $input->post->getInt('external_import_catid', (int) ($preview['catid'] ?? 0)),
                'category_label' => $preview['category_label'] ?? '',
                'mode' => $input->post->getCmd('external_import_mode', 'standard'),
                'type_id' => $input->post->getInt('external_import_type_id', (int) ($preview['type_id'] ?? 0)),
                'locid' => $input->post->getInt('external_import_locid', (int) ($preview['locid'] ?? 0)),
                'published' => $input->post->getInt('external_import_published', 1),
                'publish_up' => $this->normaliseExternalPublishUp($input->post->getString('external_import_publish_up', (string) ($preview['publish_up'] ?? ''))),
                'language' => $input->post->getCmd('external_import_language', (string) ($preview['language'] ?? '*')),
                'mapping' => $postedMapping,
                'static_values' => $postedStaticValues,
            );
            $options['type_label'] = $this->getTypeLabel($options['type_id']);
            $options['venue_label'] = $this->getVenueLabel($options['locid']);
            $options['language_label'] = $this->getLanguageLabel($options['language']);
            $options['record_fields'] = $this->getExternalEventRecordFields($postedMapping);
            try {
                $preview = $this->buildExternalStructuredPreviewFromRecords((array) $preview['source_records'], $options, (array) ($preview['source_fields'] ?? array()));
                $preview['format'] = $format;
            } catch (RuntimeException $e) {
                $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
                $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
                $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
                return;
            }
        }

        $profileTitle = $input->post->getString('external_import_profile_title', '');
        if ($format !== 'ics' && $input->post->getInt('external_import_profile_save', 0)) {
            $savedProfile = $this->saveExternalImportProfile(
                'events',
                $format,
                $profileTitle,
                (array) ($preview['mapping'] ?? $postedMapping),
                array(
                    'static_values' => (array) ($preview['static_values'] ?? $postedStaticValues),
                    'catid' => $input->post->getInt('external_import_catid', (int) ($preview['catid'] ?? 0)),
                    'mode' => $input->post->getCmd('external_import_mode', (string) ($preview['mode'] ?? 'standard')),
                    'type_id' => $input->post->getInt('external_import_type_id', (int) ($preview['type_id'] ?? 0)),
                    'locid' => $input->post->getInt('external_import_locid', (int) ($preview['locid'] ?? 0)),
                    'published' => $input->post->getInt('external_import_published', (int) ($preview['published'] ?? 1)),
                    'publish_up' => $input->post->getString('external_import_publish_up', (string) ($preview['publish_up'] ?? '')),
                    'language' => $input->post->getCmd('external_import_language', (string) ($preview['language'] ?? '*')),
                    'source_mode' => $input->post->getCmd('external_import_source_mode', (string) ($preview['source_mode'] ?? 'file')),
                    'source_url' => $input->post->getString('external_import_source_url', (string) ($preview['source_url'] ?? '')),
                    'source_name' => (string) ($preview['source_name'] ?? ''),
                )
            );

            if ($savedProfile) {
                $preview['profile_id'] = (int) $savedProfile['id'];
                $preview['profile_title'] = (string) $savedProfile['title'];
            }
        }

        $fields = !empty($preview['record_fields']) && is_array($preview['record_fields'])
            ? $preview['record_fields']
            : $this->getExternalEventRecordFields();
        $model = $this->getModel('import');
        ob_start();
        try {
            $result = $model->eventsimport($fields, $preview['records'], false);
        } catch (RuntimeException $e) {
            ob_end_clean();
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry($format === 'ics' ? 'external_ics' : 'external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#event-import', $msg, 'error');
            return;
        }
        $importOutput = trim((string) ob_get_clean());
        $app->setUserState('com_jem.import.external_import.preview', null);

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
     * Preview an external CSV, JSON or XML file as normalized JEM venues.
     *
     * @return void
     */
    public function previewExternalVenueImport()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $input = $app->input;
        $file = $input->files->get('FileExternalVenueImport', array(), 'array');
        $sourceMode = $input->post->getCmd('external_venue_import_source_mode', 'file');
        $catalogEntry = $this->getSelectedImportCatalogEntry('venues');
        $postedSourceUrl = trim($input->post->getString('external_venue_import_source_url', ''));
        $catalogSource = $sourceMode === 'url' ? ($postedSourceUrl ?: (string) ($catalogEntry['source'] ?? '')) : '';
        $downloadedFile = '';
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $hasUpload = !empty($file['name']) && empty($file['error']) && is_uploaded_file($file['tmp_name']);

        if ($catalogSource !== '') {
            try {
                $file = $this->downloadExternalImportSource($catalogSource, array('csv', 'json', 'xml', 'xlsx'), (string) ($catalogEntry['format'] ?? ''));
                $downloadedFile = (string) $file['tmp_name'];
                $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                $hasUpload = true;
            } catch (RuntimeException $e) {
                $msg = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_URL_ERROR', $e->getMessage());
                $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
                $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
                return;
            }
        }

        $existingPreview = $app->getUserState('com_jem.import.external_venue_import.preview', null);
        $previousPayloadToken = (string) ($existingPreview['payload_token'] ?? '');

        if (!empty($existingPreview['payload_token'])) {
            try {
                $existingPreview = $this->loadExternalVenuePreviewPayload((array) $existingPreview);
            } catch (RuntimeException $e) {
                $this->deleteExternalVenuePreviewPayload((string) $existingPreview['payload_token']);
                $existingPreview = null;
                $app->setUserState('com_jem.import.external_venue_import.preview', null);
            }
        }
        if (!$hasUpload && $sourceMode !== 'url' && !empty($existingPreview['source_records']) && !empty($existingPreview['format'])) {
            $extension = strtolower((string) $existingPreview['format']);
        }

        if ((!$hasUpload && empty($existingPreview['source_records'])) || !in_array($extension, array('csv', 'json', 'xml', 'xlsx'), true)) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_UNSUPPORTED_VENUE_FILE');
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
            return;
        }

        $selectedProfileId = $input->post->getInt('external_venue_import_profile_id', 0);
        $app->setUserState('com_jem.import.external_venue_import.selected_profile_id', $selectedProfileId);
        $profile = $this->getExternalImportProfile($selectedProfileId, $extension, 'venues');
        $postedMapping = $this->getPostedImportMapping('external_venue_import_mapping');
        $postedStaticValues = $this->getPostedImportStaticValues('external_venue_import_static_values');
        $options = array(
            'type_id' => $input->post->getInt('external_venue_import_type_id', 0),
            'published' => $input->post->getInt('external_venue_import_published', 1),
            'language' => $input->post->getCmd('external_venue_import_language', '*'),
            'mapping' => $postedMapping ?: ($profile['mapping'] ?? array()),
            'static_values' => $postedStaticValues ?: ($profile['options']['static_values'] ?? array()),
            'profile_id' => (int) ($profile['id'] ?? 0),
            'profile_title' => (string) ($profile['title'] ?? ''),
            'source_mode' => $sourceMode === 'url' ? 'url' : 'file',
            'source_url' => $catalogSource,
            'source_name' => $catalogSource !== '' ? $catalogSource : ($hasUpload ? basename((string) ($file['name'] ?? '')) : basename((string) ($existingPreview['source_name'] ?? ''))),
        );
        $options['type_label'] = $this->getTypeLabel($options['type_id']);
        $options['language_label'] = $this->getLanguageLabel($options['language']);
        $options['record_fields'] = $this->getExternalVenueRecordFields($options['mapping']);

        try {
            if (!$hasUpload && $sourceMode !== 'url') {
                $preview = $this->buildExternalVenuePreviewFromRecords(
                    (array) ($existingPreview['source_records'] ?? array()),
                    $options,
                    (array) ($existingPreview['source_fields'] ?? array())
                );
            } elseif ($extension === 'json') {
                $preview = $this->buildExternalJsonVenuePreview($file, $options);
            } elseif ($extension === 'xml') {
                $preview = $this->buildExternalXmlVenuePreview($file, $options);
            } elseif ($extension === 'xlsx') {
                $preview = $this->buildExternalXlsxVenuePreview($file, $options);
            } else {
                $preview = $this->buildExternalCsvVenuePreview($file, $options);
            }
        } catch (RuntimeException $e) {
            if ($downloadedFile !== '' && is_file($downloadedFile)) {
                unlink($downloadedFile);
            }

            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
            return;
        }

        if ($downloadedFile !== '' && is_file($downloadedFile)) {
            unlink($downloadedFile);
        }

        $preview['format'] = $extension;
        $preview['source_name'] = $catalogSource !== '' ? $catalogSource : ($hasUpload ? (string) ($file['name'] ?? '') : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'));
        $preview['source_mode'] = $catalogSource !== '' ? 'url' : 'file';
        $preview['source_url'] = $catalogSource;
        $preview['mapping'] = (array) ($preview['mapping'] ?? $options['mapping']);
        $preview['static_values'] = (array) ($preview['static_values'] ?? $options['static_values']);
        $preview['profile_id'] = (int) $options['profile_id'];
        $preview['profile_title'] = (string) $options['profile_title'];

        $profileTitle = $input->post->getString('external_venue_import_profile_title', '');
        if ($input->post->getInt('external_venue_import_profile_save', 0)) {
            $savedProfile = $this->saveExternalImportProfile(
                'venues',
                $extension,
                $profileTitle,
                (array) $preview['mapping'],
                array_merge($options, array('static_values' => (array) ($preview['static_values'] ?? array())))
            );

            if ($savedProfile) {
                $preview['profile_id'] = (int) $savedProfile['id'];
                $preview['profile_title'] = (string) $savedProfile['title'];
                $app->setUserState('com_jem.import.external_venue_import.selected_profile_id', (int) $savedProfile['id']);
            }
        }

        if ($this->externalSourceWarningCount > 0) {
            $preview['source_warnings'] = $this->externalSourceWarnings;
            $preview['source_warning_count'] = $this->externalSourceWarningCount;
            $warningMessage = Text::sprintf('COM_JEM_IMPORT_SOURCE_VALUES_IGNORED', $this->externalSourceWarningCount);
            $app->enqueueMessage($warningMessage, 'warning');
            $this->addImportLogEntry(
                'external_csv',
                $warningMessage . ' ' . implode(' | ', array_slice($this->externalSourceWarnings, 0, 20)),
                Log::WARNING
            );
        }

        try {
            $preview = $this->storeExternalVenuePreviewPayload($preview);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
            return;
        }

        if ($previousPayloadToken !== '' && $previousPayloadToken !== (string) ($preview['payload_token'] ?? '')) {
            $this->deleteExternalVenuePreviewPayload($previousPayloadToken);
        }

        $app->setUserState('com_jem.import.external_venue_import.preview', $preview);

        $fileName = $catalogSource !== '' ? $catalogSource : ($hasUpload ? $file['name'] : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'));
        $this->addImportLogEntry(
            'external_csv',
            'External venue ' . strtoupper($extension) . ' preview for file "' . $fileName . '". '
            . 'Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $app->setUserState('com_jem.import.active_preview', 'venues');
        $this->setRedirect('index.php?option=com_jem&view=import&profile_selection=1#venue-import', $preview['summary'], $preview['error_count'] ? 'warning' : 'message');
    }

    /**
     * Import the valid rows from the last external venue preview.
     *
     * @return void
     */
    public function commitExternalVenueImport()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jem.import.external_venue_import.preview', null);
        $payloadToken = (string) ($preview['payload_token'] ?? '');

        try {
            $preview = $this->loadExternalVenuePreviewPayload((array) $preview);
        } catch (RuntimeException $e) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING');
            $app->setUserState('com_jem.import.external_venue_import.preview', null);
            $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
            return;
        }

        if (empty($preview['records'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_NO_PREVIEW');
            $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
            return;
        }

        $input = $app->input;
        $postedMapping = $this->getPostedImportMapping('external_venue_import_mapping');
        $rawPostedMapping = $input->post->get('external_venue_import_mapping', null, 'array');
        $postedStaticValues = $this->getPostedImportStaticValues('external_venue_import_static_values');
        $rawPostedStaticValues = $input->post->get('external_venue_import_static_values', null, 'array');
        if ((is_array($rawPostedMapping) || is_array($rawPostedStaticValues)) && !empty($preview['source_records'])) {
            $options = array(
                'type_id' => $input->post->getInt('external_venue_import_type_id', (int) ($preview['type_id'] ?? 0)),
                'published' => $input->post->getInt('external_venue_import_published', 1),
                'language' => $input->post->getCmd('external_venue_import_language', (string) ($preview['language'] ?? '*')),
                'mapping' => $postedMapping,
                'static_values' => $postedStaticValues,
            );
            $options['type_label'] = $this->getTypeLabel($options['type_id']);
            $options['language_label'] = $this->getLanguageLabel($options['language']);
            $options['record_fields'] = $this->getExternalVenueRecordFields($postedMapping);
            $previousFormat = strtolower((string) ($preview['format'] ?? 'csv'));
            try {
                $preview = $this->buildExternalVenuePreviewFromRecords((array) $preview['source_records'], $options, (array) ($preview['source_fields'] ?? array()));
                $preview['format'] = $previousFormat;
            } catch (RuntimeException $e) {
                $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
                $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
                $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
                return;
            }
        }

        $profileTitle = $input->post->getString('external_venue_import_profile_title', '');
        if ($input->post->getInt('external_venue_import_profile_save', 0)) {
            $savedProfile = $this->saveExternalImportProfile(
                'venues',
                strtolower((string) ($preview['format'] ?? 'csv')),
                $profileTitle,
                (array) ($preview['mapping'] ?? $postedMapping),
                array(
                    'static_values' => (array) ($preview['static_values'] ?? $postedStaticValues),
                    'type_id' => $input->post->getInt('external_venue_import_type_id', (int) ($preview['type_id'] ?? 0)),
                    'published' => $input->post->getInt('external_venue_import_published', (int) ($preview['published'] ?? 1)),
                    'language' => $input->post->getCmd('external_venue_import_language', (string) ($preview['language'] ?? '*')),
                    'source_mode' => $input->post->getCmd('external_venue_import_source_mode', (string) ($preview['source_mode'] ?? 'file')),
                    'source_url' => $input->post->getString('external_venue_import_source_url', (string) ($preview['source_url'] ?? '')),
                    'source_name' => (string) ($preview['source_name'] ?? ''),
                )
            );

            if ($savedProfile) {
                $preview['profile_id'] = (int) $savedProfile['id'];
                $preview['profile_title'] = (string) $savedProfile['title'];
            }
        }

        $fields = !empty($preview['record_fields']) && is_array($preview['record_fields'])
            ? $preview['record_fields']
            : $this->getExternalVenueRecordFields();
        $model = $this->getModel('import');
        $result = array('added' => 0, 'updated' => 0, 'ignored' => 0, 'ignoredids' => '', 'duplicated' => 0, 'duplicatedids' => '', 'replaced' => 0, 'replacedids' => '', 'error' => 0, 'errorids' => '');
        ob_start();
        try {
            $recordCount = count($preview['records']);

            for ($offset = 0; $offset < $recordCount; $offset += self::EXTERNAL_IMPORT_BATCH_SIZE) {
                $batch = array_slice($preview['records'], $offset, self::EXTERNAL_IMPORT_BATCH_SIZE);
                $batchResult = $model->venuesimport($fields, $batch, false);

                foreach (array('added', 'updated', 'ignored', 'duplicated', 'replaced', 'error') as $key) {
                    $result[$key] += (int) ($batchResult[$key] ?? 0);
                }

                foreach (array('ignoredids', 'duplicatedids', 'replacedids', 'errorids') as $key) {
                    $ids = trim((string) ($batchResult[$key] ?? ''), ',');
                    if ($ids !== '') {
                        $result[$key] .= ($result[$key] !== '' ? ',' : '') . $ids;
                    }
                }

                unset($batch, $batchResult);
            }
        } catch (RuntimeException $e) {
            ob_end_clean();
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('external_csv', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, 'error');
            return;
        }
        $importOutput = trim((string) ob_get_clean());
        $app->setUserState('com_jem.import.external_venue_import.preview', null);
        $this->deleteExternalVenuePreviewPayload($payloadToken);

        $msg = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_VENUES_COMMIT_RESULT', (int) $result['added'], (int) $result['error'], (int) $preview['skipped_count']);
        $this->addImportLogEntry(
            'external_csv',
            'External venue import committed. ' . strip_tags($msg) . $this->formatExternalImportLogDetails($preview, $result, $importOutput),
            $result['error'] ? Log::WARNING : Log::INFO
        );

        $this->setRedirect('index.php?option=com_jem&view=import#venue-import', $msg, $result['error'] ? 'warning' : 'message');
    }

    /**
     * Clear the external venue preview.
     *
     * @return void
     */
    public function clearExternalVenueImportPreview()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanImport();

        $app = Factory::getApplication();
        $preview = (array) $app->getUserState('com_jem.import.external_venue_import.preview', array());
        $this->deleteExternalVenuePreviewPayload((string) ($preview['payload_token'] ?? ''));
        $app->setUserState('com_jem.import.external_venue_import.preview', null);
        $this->setRedirect('index.php?option=com_jem&view=import#venue-import');
    }

    protected function storeExternalVenuePreviewPayload(array $preview)
    {
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        return JemImportPreviewHelper::storeVenuePreview($preview, $userId);
    }

    protected function loadExternalVenuePreviewPayload(array $preview)
    {
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        return JemImportPreviewHelper::loadVenuePreview($preview, $userId);
    }

    protected function deleteExternalVenuePreviewPayload($token)
    {
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        JemImportPreviewHelper::deleteVenuePreview($token, $userId);
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

        $app->setUserState('com_jem.import.active_preview', 'specialdays');
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

        $app->setUserState('com_jem.import.active_preview', 'specialdays');
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
        $specialDaysFormState = array(
            'profile_id' => $input->post->getInt('specialdays_import_profile_id', 0),
            'day_type' => $input->post->getString('specialdays_import_day_type', ''),
            'replace' => $input->post->getInt('replace_specialdays_import', 0),
            'show_dates' => $input->post->getInt('specialdays_import_show_dates', 1),
        );
        $app->setUserState('com_jem.import.specialdays_import.form', $specialDaysFormState);
        $app->setUserState('com_jem.import.specialdays_import.selected_profile_id', (int) $specialDaysFormState['profile_id']);

        $selectedDayType = $this->resolveSpecialDaysImportType($specialDaysFormState['day_type']);
        $dayType = (string) ($selectedDayType['name'] ?? '');
        $dayTypeId = (int) ($selectedDayType['id'] ?? 0);
        $file = $input->files->get('FileSpecialDaysImport', array(), 'array');
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $hasUpload = !empty($file['name']) && empty($file['error']) && is_uploaded_file($file['tmp_name']);

        $existingPreview = $app->getUserState('com_jem.import.specialdays_import.preview', null);
        if (!$hasUpload && !empty($existingPreview['source_records']) && !empty($existingPreview['format'])) {
            $extension = strtolower((string) $existingPreview['format']);
        }

        if ($extension === 'clm') {
            $extension = 'csv';
        }

        if ((!$hasUpload && empty($existingPreview['source_records'])) || !in_array($extension, array('csv', 'json', 'xml', 'ics'), true)) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_UNSUPPORTED_FILE');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        if ($extension === 'ics' && $dayType === '') {
            $msg = Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_REQUIRED');
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        $options = array(
            'day_type' => $dayType,
            'day_type_id' => $dayTypeId,
            'replace' => (int) $specialDaysFormState['replace'],
            'show_dates' => (int) $specialDaysFormState['show_dates'],
            'source' => $extension,
            'title' => Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_PREVIEW_TITLE'),
        );
        $profile = $this->getExternalImportProfile((int) $specialDaysFormState['profile_id'], $extension, 'specialdays');
        $postedMapping = $this->getPostedImportMapping('specialdays_import_mapping');
        $postedStaticValues = $this->getPostedImportStaticValues('specialdays_import_static_values');
        $options['mapping'] = $postedMapping ?: ($profile['mapping'] ?? array());
        $options['static_values'] = $postedStaticValues ?: ($profile['options']['static_values'] ?? array());
        $options['profile_id'] = (int) ($profile['id'] ?? 0);
        $options['profile_title'] = (string) ($profile['title'] ?? '');

        try {
            $preview = !$hasUpload
                ? $this->buildSpecialDaysPreviewFromRecords((array) ($existingPreview['source_records'] ?? array()), $options, (array) ($existingPreview['source_fields'] ?? array()))
                : ($extension === 'ics'
                ? $this->buildSpecialDaysIcsPreview($file, $options)
                : ($extension === 'json'
                ? $this->buildSpecialDaysJsonPreview($file, $options)
                : ($extension === 'xml'
                ? $this->buildSpecialDaysXmlPreview($file, $options)
                : $this->buildSpecialDaysCsvPreview($file, $options))));
        } catch (RuntimeException $e) {
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }
        $preview['format'] = $extension;
        $preview['source_name'] = $hasUpload ? (string) ($file['name'] ?? '') : (string) ($existingPreview['source_name'] ?? Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'));
        $preview['day_type'] = $dayType;
        $preview['day_type_id'] = $dayTypeId;
        $preview['replace'] = (int) $specialDaysFormState['replace'];
        $preview['show_dates'] = (int) $specialDaysFormState['show_dates'];
        $preview['static_values'] = (array) ($preview['static_values'] ?? $options['static_values']);
        $preview['profile_id'] = (int) ($options['profile_id'] ?? 0);
        $preview['profile_title'] = (string) ($options['profile_title'] ?? '');

        $profileTitle = $input->post->getString('specialdays_import_profile_title', '');
        if ($input->post->getInt('specialdays_import_profile_save', 0)) {
            $savedProfile = $this->saveExternalImportProfile(
                'specialdays',
                $extension,
                $profileTitle,
                (array) ($preview['mapping'] ?? $options['mapping']),
                array('static_values' => (array) ($preview['static_values'] ?? array()))
            );

            if ($savedProfile) {
                $preview['profile_id'] = (int) $savedProfile['id'];
                $preview['profile_title'] = (string) $savedProfile['title'];
            }
        }

        $app->setUserState('com_jem.import.specialdays_import.preview', $preview);
        $app->setUserState('com_jem.import.specialdays_csv.preview', null);
        $app->setUserState('com_jem.import.specialdays_ics.preview', null);

        $this->addImportLogEntry(
            'special_days',
            'Special Days ' . strtoupper($extension) . ' preview for file "' . ($hasUpload ? $file['name'] : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW')) . '". Parser: ' . strtoupper($extension)
            . '; Type fallback: ' . $dayType . '; Valid rows: ' . $preview['valid_count'] . ', errors: ' . $preview['error_count'] . '.',
            $preview['error_count'] ? Log::WARNING : Log::INFO
        );

        $app->setUserState('com_jem.import.active_preview', 'specialdays');
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

        $app = Factory::getApplication();
        $input = $app->input;
        $preview = $app->getUserState('com_jem.import.specialdays_import.preview', null);

        if (empty($preview['records'])) {
            $msg = Text::_('COM_JEM_IMPORT_EXTERNAL_NO_PREVIEW');
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }

        $format = strtolower((string) ($preview['format'] ?? 'csv'));
        $postedMapping = $this->getPostedImportMapping('specialdays_import_mapping');
        $rawPostedMapping = $input->post->get('specialdays_import_mapping', null, 'array');
        $postedStaticValues = $this->getPostedImportStaticValues('specialdays_import_static_values');
        $rawPostedStaticValues = $input->post->get('specialdays_import_static_values', null, 'array');
        $specialDaysFormState = (array) $app->getUserState('com_jem.import.specialdays_import.form', array());
        $selectedTypeValue = $input->post->getString(
            'specialdays_import_day_type',
            (string) (($preview['day_type_id'] ?? '') ?: ($specialDaysFormState['day_type'] ?? ($preview['day_type'] ?? '')))
        );
        $replaceSpecialDays = $input->post->getInt('replace_specialdays_import', (int) ($specialDaysFormState['replace'] ?? ($preview['replace'] ?? 0)));
        $showDatesSpecialDays = $input->post->getInt('specialdays_import_show_dates', (int) ($specialDaysFormState['show_dates'] ?? ($preview['show_dates'] ?? 1)));

        $app->setUserState('com_jem.import.specialdays_import.form', array(
            'profile_id' => $input->post->getInt('specialdays_import_profile_id', (int) ($preview['profile_id'] ?? ($specialDaysFormState['profile_id'] ?? 0))),
            'day_type' => $selectedTypeValue,
            'replace' => $replaceSpecialDays,
            'show_dates' => $showDatesSpecialDays,
        ));

        if ((is_array($rawPostedMapping) || is_array($rawPostedStaticValues)) && !empty($preview['source_records'])) {
            $selectedDayType = $this->resolveSpecialDaysImportType($selectedTypeValue);
            $options = array(
                'day_type' => (string) ($selectedDayType['name'] ?? ($preview['day_type'] ?? '')),
                'day_type_id' => (int) ($selectedDayType['id'] ?? ($preview['day_type_id'] ?? 0)),
                'replace' => $replaceSpecialDays,
                'show_dates' => $showDatesSpecialDays,
                'source' => $format,
                'title' => $preview['title'] ?? Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_PREVIEW_TITLE'),
                'mapping' => $postedMapping,
                'static_values' => $postedStaticValues,
                'profile_title' => $preview['profile_title'] ?? '',
            );
            try {
                $preview = $this->buildSpecialDaysPreviewFromRecords((array) $preview['source_records'], $options, (array) ($preview['source_fields'] ?? array()));
                $preview['format'] = $format;
            } catch (RuntimeException $e) {
                $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
                $this->addImportLogEntry('special_days', $msg, Log::WARNING);
                $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
                return;
            }
        }

        $profileTitle = $input->post->getString('specialdays_import_profile_title', '');
        if ($input->post->getInt('specialdays_import_profile_save', 0)) {
            $savedProfile = $this->saveExternalImportProfile(
                'specialdays',
                $format,
                $profileTitle,
                (array) ($preview['mapping'] ?? $postedMapping),
                array('static_values' => (array) ($preview['static_values'] ?? $postedStaticValues))
            );

            if ($savedProfile) {
                $preview['profile_id'] = (int) $savedProfile['id'];
                $preview['profile_title'] = (string) $savedProfile['title'];
            }
        }

        $app->setUserState('com_jem.import.specialdays_import.preview', $preview);
        $this->commitSpecialDaysPreview('com_jem.import.specialdays_import.preview', strtoupper($format));
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
        $sourceJemVersion = '';
        $sourceVersionConflict = false;
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
            $versionColumn = null;
            if (($data = fgetcsv($handle, 1000, $separator, $delimiter)) !== false) {
                $numfields = count($data);

                // normalise to utf-8; UTF-8 without BOM must not be converted again
                if (!$hasBom) {
                    $msg .= "<p>".Text::_('COM_JEM_IMPORT_BOM_NOT_FOUND')."</p>\n";
                }
                array_walk($data, 'jem_normalise_csv_utf8');
                $versionColumn = JemCsvMetadataHelper::findVersionColumn($data);

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
                    $rowJemVersion = JemCsvMetadataHelper::extractVersion($data, $versionColumn);
                    if ($rowJemVersion !== '') {
                        if ($sourceJemVersion === '') {
                            $sourceJemVersion = $rowJemVersion;
                        } elseif ($sourceJemVersion !== $rowJemVersion) {
                            $sourceVersionConflict = true;
                        }
                    }

                    $r = array();
                    // only extract columns with validated header, from previous step.
                    foreach ($fields as $k => $v) {
                        $r[$k] = $this->_formatcsvfield($v, $data[$k]);
                    }
                    $r['_jem_source_line'] = $row + 1;
                    $records[] = $r;
                }
                $row++;
            }

            fclose($handle);
            $localJemVersion = $this->getInstalledJemVersion();
            $versionMessage = $this->buildJemVersionImportMessage(
                $sourceJemVersion,
                $localJemVersion,
                $sourceVersionConflict
            );
            $msg .= '<p>' . htmlspecialchars($versionMessage, ENT_QUOTES, 'UTF-8') . "</p>\n";
            $this->addImportLogEntry(
                $logKey,
                $versionMessage . ' File: ' . $file['name'],
                ($sourceVersionConflict || ($sourceJemVersion !== '' && $localJemVersion !== '' && $sourceJemVersion !== $localJemVersion))
                    ? Log::WARNING
                    : Log::INFO
            );
            $msg .= "<p>".Text::sprintf('COM_JEM_IMPORT_NUMBER_OF_ROWS_FOUND', count($records))."</p>\n";

            // database update
            if (count($records)) {
                $model = $this->getModel('import');
                try {
                    $result = $model->{$type.'import'}($fields, $records, $replace);
                } catch (RuntimeException $e) {
                    $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
                    $this->addImportLogEntry($logKey, $msg . ' File: ' . $file['name'], Log::WARNING);
                    $this->setRedirect('index.php?option=com_jem&view=import', $msg, 'error');
                    return;
                }
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
     * Read the installed JEM component version from Joomla's manifest cache.
     */
    protected function getInstalledJemVersion()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $manifest = json_decode((string) $db->loadResult(), true);

        return JemCsvMetadataHelper::normaliseVersion($manifest['version'] ?? '');
    }

    /**
     * Describe JEM-to-JEM version context without treating a difference as incompatible.
     */
    protected function buildJemVersionImportMessage($sourceVersion, $localVersion, $conflict = false)
    {
        $hasSourceVersion = $sourceVersion !== '';
        $hasLocalVersion = $localVersion !== '';
        $sourceVersion = $sourceVersion !== '' ? $sourceVersion : Text::_('COM_JEM_IMPORT_JEM_VERSION_UNKNOWN');
        $localVersion = $localVersion !== '' ? $localVersion : Text::_('COM_JEM_IMPORT_JEM_VERSION_UNKNOWN');

        if ($conflict) {
            return Text::sprintf('COM_JEM_IMPORT_JEM_VERSION_CONFLICT', $sourceVersion, $localVersion);
        }

        if ($hasSourceVersion && $hasLocalVersion && $sourceVersion !== $localVersion) {
            return Text::sprintf('COM_JEM_IMPORT_JEM_VERSION_DIFFERENT', $sourceVersion, $localVersion);
        }

        return Text::sprintf('COM_JEM_IMPORT_JEM_VERSION_INFO', $sourceVersion, $localVersion);
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
        $sourceRecords = array();
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
                'mode' => $options['mode'] ?? 'standard',
                'type_id' => (int) $options['type_id'],
                'locid' => (int) $options['locid'],
                'published' => (int) ($options['published'] ?? 1),
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
                'mode' => $options['mode'] ?? 'standard',
                'type_id' => (int) $options['type_id'],
                'locid' => (int) $options['locid'],
                'published' => (int) ($options['published'] ?? 1),
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
        $effectiveMapping = $this->getEffectiveExternalMapping($header, $options['mapping'] ?? array(), 'events');
        $fields = $this->normaliseExternalSourceFields($header, $effectiveMapping, 'events');
        $staticValues = $this->normaliseImportStaticValues($options['static_values'] ?? array());
        $rowOptions = $options;
        $rowOptions['mapping'] = $effectiveMapping;
        $rowOptions['static_values'] = $staticValues;
        $rowOptions['record_fields'] = $this->mergeImportRecordFields($this->getExternalEventRecordFields($effectiveMapping), $staticValues);
        $line = 1;

        while (($raw = fgetcsv($handle, 10000, $separator, $delimiter)) !== false) {
            $line++;
            array_walk($raw, 'jem_normalise_csv_utf8');

            if (count(array_filter($raw, 'strlen')) === 0) {
                continue;
            }

            $data = array();
            $sourceRecord = array();
            foreach ($header as $index => $sourceField) {
                $sourceRecord[$sourceField] = JemImportSecurityHelper::sanitiseValue($sourceField, $raw[$index] ?? '', 'source', $line);
                $field = $fields[$sourceField] ?? null;

                if ($field === null) {
                    continue;
                }
                $this->addExternalMappedValue($data, $field, $raw[$index] ?? '', $line);
            }
            $this->applyImportStaticValues($data, $staticValues);
            $sourceRecords[] = $sourceRecord;

            $row = $this->normaliseExternalCsvRow($data, $rowOptions, $line);
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
            'mode' => $options['mode'] ?? 'standard',
            'type_id' => (int) $options['type_id'],
            'locid' => (int) $options['locid'],
            'published' => (int) ($options['published'] ?? 1),
            'language' => $options['language'],
            'publish_up' => $options['publish_up'],
            'category_label' => $options['category_label'],
            'type_label' => $options['type_label'],
            'venue_label' => $options['venue_label'],
            'language_label' => $options['language_label'],
            'publish_up_label' => $options['publish_up'],
            'profile_title' => $options['profile_title'] ?? '',
            'source_fields' => $header,
            'source_records' => $sourceRecords,
            'mapping' => $effectiveMapping,
            'static_values' => $staticValues,
            'record_fields' => $rowOptions['record_fields'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    /**
     * Build a preview from an external JSON upload.
     *
     * @param   array  $file     Uploaded file info.
     * @param   array  $options  Import defaults.
     *
     * @return array
     */
    protected function buildExternalJsonPreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptyExternalPreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        $json = json_decode($content, true);

        if (!is_array($json)) {
            return $this->emptyExternalPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        $records = $this->findExternalStructuredRecords($json);

        return $this->buildExternalStructuredPreviewFromRecords($records, $options);
    }

    /**
     * Build a preview from an external XML upload.
     *
     * @param   array  $file     Uploaded file info.
     * @param   array  $options  Import defaults.
     *
     * @return array
     */
    protected function buildExternalXmlPreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptyExternalPreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            libxml_clear_errors();
            return $this->emptyExternalPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        $records = $this->extractExternalXmlRecords($xml);

        return $this->buildExternalStructuredPreviewFromRecords($records, $options);
    }

    protected function buildExternalStructuredPreviewFromRecords(array $records, array $options, array $sourceFields = array())
    {
        if (!$records) {
            return $this->emptyExternalPreview($options, 1, Text::_('COM_JEM_IMPORT_EXTERNAL_STRUCTURED_NO_RECORDS'));
        }

        $rows = array();
        $importRecords = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        if (!$sourceFields) {
            $sourceFields = array_keys(reset($records) ?: array());
        }

        $effectiveMapping = $this->getEffectiveExternalMapping($sourceFields, $options['mapping'] ?? array(), 'events');
        $fields = $this->normaliseExternalSourceFields($sourceFields, $effectiveMapping, 'events');
        $staticValues = $this->normaliseImportStaticValues($options['static_values'] ?? array());
        $rowOptions = $options;
        $rowOptions['mapping'] = $effectiveMapping;
        $rowOptions['static_values'] = $staticValues;
        $rowOptions['record_fields'] = $this->mergeImportRecordFields($this->getExternalEventRecordFields($effectiveMapping), $staticValues);
        $line = 0;

        foreach ($records as $record) {
            $line++;
            $data = array();

            foreach ($fields as $source => $field) {
                if ($field === null) {
                    continue;
                }

                $this->addExternalMappedValue($data, $field, $record[$source] ?? '', $line);
            }
            $this->applyImportStaticValues($data, $staticValues);

            $row = $this->normaliseExternalCsvRow($data, $rowOptions, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $importRecords[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        return array(
            'rows' => $rows,
            'records' => $importRecords,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'catid' => (int) $options['catid'],
            'mode' => $options['mode'] ?? 'standard',
            'type_id' => (int) $options['type_id'],
            'locid' => (int) $options['locid'],
            'published' => (int) ($options['published'] ?? 1),
            'language' => $options['language'],
            'publish_up' => $options['publish_up'],
            'category_label' => $options['category_label'],
            'type_label' => $options['type_label'],
            'venue_label' => $options['venue_label'],
            'language_label' => $options['language_label'],
            'publish_up_label' => $options['publish_up'],
            'profile_title' => $options['profile_title'] ?? '',
            'source_fields' => $sourceFields,
            'source_records' => $records,
            'mapping' => $effectiveMapping,
            'static_values' => $staticValues,
            'record_fields' => $rowOptions['record_fields'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    protected function emptyExternalPreview(array $options, $errors, $summary)
    {
        return array(
            'rows' => array(),
            'records' => array(),
            'valid_count' => 0,
            'error_count' => (int) $errors,
            'skipped_count' => 0,
            'has_errors' => (int) $errors > 0,
            'catid' => (int) $options['catid'],
            'mode' => $options['mode'] ?? 'standard',
            'type_id' => (int) $options['type_id'],
            'locid' => (int) $options['locid'],
            'published' => (int) ($options['published'] ?? 1),
            'language' => $options['language'],
            'publish_up' => $options['publish_up'],
            'category_label' => $options['category_label'],
            'type_label' => $options['type_label'],
            'venue_label' => $options['venue_label'],
            'language_label' => $options['language_label'],
            'publish_up_label' => $options['publish_up'],
            'profile_title' => $options['profile_title'] ?? '',
            'static_values' => $this->normaliseImportStaticValues($options['static_values'] ?? array()),
            'summary' => $summary,
        );
    }

    protected function buildExternalCsvVenuePreview(array $file, array $options)
    {
        $handle = fopen($file['tmp_name'], 'r');

        if (!$handle) {
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
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

        list($separator, $header) = $this->detectExternalCsvSeparator($handle, $hasBom ? 3 : 0, $separator, $delimiter);

        if ($header === false) {
            fclose($handle);
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        array_walk($header, 'jem_normalise_csv_utf8');
        $sourceRecords = array();

        $line = 1;
        while (($raw = fgetcsv($handle, 10000, $separator, $delimiter)) !== false) {
            $line++;
            array_walk($raw, 'jem_normalise_csv_utf8');

            if (count(array_filter($raw, 'strlen')) === 0) {
                continue;
            }

            $record = array();
            foreach ($header as $index => $field) {
                $record[$field] = $raw[$index] ?? '';
            }
            $sourceRecords[] = $this->sanitiseExternalSourceRecord($record, 'source_csv', $line);
        }

        fclose($handle);

        return $this->buildExternalVenuePreviewFromRecords($sourceRecords, $options, $header);
    }

    protected function detectExternalCsvSeparator($handle, $offset, $configuredSeparator, $delimiter)
    {
        $configuredSeparator = (string) $configuredSeparator;
        $delimiter = strlen((string) $delimiter) === 1 ? (string) $delimiter : '"';
        $candidates = array_values(array_unique(array_filter(array($configuredSeparator, ';', ',', "\t"), static fn($value) => strlen($value) === 1)));
        $bestSeparator = $candidates[0] ?? ';';
        $bestHeader = false;
        $bestColumns = 0;

        foreach ($candidates as $candidate) {
            fseek($handle, (int) $offset);
            $header = fgetcsv($handle, 10000, $candidate, $delimiter);
            $columns = is_array($header) ? count($header) : 0;

            if ($columns > $bestColumns) {
                $bestSeparator = $candidate;
                $bestHeader = $header;
                $bestColumns = $columns;
            }
        }

        fseek($handle, (int) $offset);
        $bestHeader = fgetcsv($handle, 10000, $bestSeparator, $delimiter);

        return array($bestSeparator, $bestHeader);
    }

    protected function buildExternalXlsxVenuePreview(array $file, array $options)
    {
        $worksheet = JemImportXlsxHelper::readRecords($file['tmp_name']);

        if (empty($worksheet['records'])) {
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_EXTERNAL_STRUCTURED_NO_RECORDS'));
        }

        $records = array();

        foreach ((array) $worksheet['records'] as $index => $record) {
            $records[] = $this->sanitiseExternalSourceRecord((array) $record, 'source_xlsx', $index + 2);
        }

        return $this->buildExternalVenuePreviewFromRecords($records, $options, (array) $worksheet['fields']);
    }

    protected function buildExternalJsonVenuePreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        $json = json_decode($content, true);

        if (!is_array($json)) {
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        $records = $this->findExternalStructuredRecords($json);

        return $this->buildExternalVenuePreviewFromRecords($records, $options);
    }

    protected function buildExternalXmlVenuePreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            libxml_clear_errors();
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        return $this->buildExternalVenuePreviewFromRecords($this->extractExternalXmlRecords($xml), $options);
    }

    protected function buildExternalVenuePreviewFromRecords(array $records, array $options, array $sourceFields = array())
    {
        if (!$records) {
            return $this->emptyExternalVenuePreview($options, 1, Text::_('COM_JEM_IMPORT_EXTERNAL_STRUCTURED_NO_RECORDS'));
        }

        if (!$sourceFields) {
            $sourceFields = array_keys(reset($records) ?: array());
        }

        $effectiveMapping = $this->getEffectiveExternalMapping($sourceFields, $options['mapping'] ?? array(), 'venues');
        $fields = $this->normaliseExternalSourceFields($sourceFields, $effectiveMapping, 'venues');
        $staticValues = $this->normaliseImportStaticValues($options['static_values'] ?? array());
        $rowOptions = $options;
        $rowOptions['mapping'] = $effectiveMapping;
        $rowOptions['static_values'] = $staticValues;
        $rowOptions['record_fields'] = $this->mergeImportRecordFields($this->getExternalVenueRecordFields($effectiveMapping), $staticValues);
        $rows = array();
        $importRecords = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        $line = 0;

        foreach ($records as $record) {
            $line++;
            $data = array();

            foreach ($fields as $source => $field) {
                if ($field === null) {
                    continue;
                }

                $this->addExternalMappedValue($data, $field, $record[$source] ?? '', $line);
            }
            $this->applyImportStaticValues($data, $staticValues);

            $row = $this->normaliseExternalVenueRow($data, $rowOptions, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $importRecords[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        return array(
            'rows' => $rows,
            'records' => $importRecords,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'type_id' => (int) $options['type_id'],
            'published' => (int) ($options['published'] ?? 1),
            'language' => $options['language'],
            'type_label' => $options['type_label'],
            'language_label' => $options['language_label'],
            'profile_title' => $options['profile_title'] ?? '',
            'source_fields' => $sourceFields,
            'source_records' => $records,
            'mapping' => $effectiveMapping,
            'static_values' => $staticValues,
            'record_fields' => $rowOptions['record_fields'],
            'summary' => Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    protected function emptyExternalVenuePreview(array $options, $errors, $summary)
    {
        return array(
            'rows' => array(),
            'records' => array(),
            'valid_count' => 0,
            'error_count' => (int) $errors,
            'skipped_count' => 0,
            'has_errors' => (int) $errors > 0,
            'type_id' => (int) $options['type_id'],
            'published' => (int) ($options['published'] ?? 1),
            'language' => $options['language'],
            'type_label' => $options['type_label'],
            'language_label' => $options['language_label'],
            'profile_title' => $options['profile_title'] ?? '',
            'static_values' => $this->normaliseImportStaticValues($options['static_values'] ?? array()),
            'summary' => $summary,
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
        $sourceRecords = $this->buildExternalIcsEventSourceRecords($events);
        $sourceFields = array('SUMMARY', 'DTSTART', 'DTEND', 'DESCRIPTION', 'LOCATION', 'URL', 'UID');
        $mapping = array(
            'SUMMARY' => 'title',
            'DTSTART' => 'start_datetime',
            'DTEND' => 'end_datetime',
            'DESCRIPTION' => 'introtext',
            'LOCATION' => '',
            'URL' => '',
            'UID' => '',
        );
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
            'mode' => $options['mode'] ?? 'standard',
            'type_id' => (int) $options['type_id'],
            'locid' => (int) $options['locid'],
            'published' => (int) ($options['published'] ?? 1),
            'language' => $options['language'],
            'publish_up' => $options['publish_up'],
            'category_label' => $options['category_label'],
            'type_label' => $options['type_label'],
            'venue_label' => $options['venue_label'],
            'language_label' => $options['language_label'],
            'publish_up_label' => $options['publish_up'],
            'source_fields' => $sourceFields,
            'source_records' => $sourceRecords,
            'mapping' => $mapping,
            'static_values' => $this->normaliseImportStaticValues($options['static_values'] ?? array()),
            'record_fields' => $this->getExternalEventRecordFields($mapping),
            'profile_title' => $options['profile_title'] ?? '',
            'summary' => Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    protected function buildExternalIcsEventSourceRecords(array $events)
    {
        $records = array();

        foreach ($events as $event) {
            $start = $this->normaliseExternalIcsDateProperty($this->getExternalIcsProperty($event, 'DTSTART'));
            $end = $this->normaliseExternalIcsDateProperty($this->getExternalIcsProperty($event, 'DTEND'));
            $records[] = array(
                'SUMMARY' => (string) $this->getExternalIcsValue($event, 'SUMMARY'),
                'DTSTART' => trim((string) ($start['date'] ?? '') . ' ' . (string) ($start['time'] ?? '')),
                'DTEND' => trim((string) ($end['date'] ?? '') . ' ' . (string) ($end['time'] ?? '')),
                'DESCRIPTION' => (string) $this->getExternalIcsValue($event, 'DESCRIPTION'),
                'LOCATION' => (string) $this->getExternalIcsValue($event, 'LOCATION'),
                'URL' => (string) $this->getExternalIcsValue($event, 'URL'),
                'UID' => (string) $this->getExternalIcsValue($event, 'UID'),
            );
        }

        return JemImportSecurityHelper::sanitiseRecordList($records, 'source');
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
        $sourceRecords = array();
        $effectiveMapping = $this->getEffectiveSpecialDaysMapping($header, $options['mapping'] ?? array());
        $fields = $this->normaliseSpecialDaysCsvHeader($header, $effectiveMapping);
        $staticValues = $this->normaliseImportStaticValues($options['static_values'] ?? array());

        if (!$fields) {
            fclose($handle);
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        $rowOptions = $options;
        $rowOptions['mapping'] = $effectiveMapping;
        $rowOptions['static_values'] = $staticValues;
        $rowOptions['record_fields'] = $this->mergeImportRecordFields($this->getSpecialDaysRecordFields($effectiveMapping), $staticValues);

        $line = 1;

        while (($raw = fgetcsv($handle, 10000, $separator, $delimiter)) !== false) {
            $line++;
            array_walk($raw, 'jem_normalise_csv_utf8');

            if (count(array_filter($raw, 'strlen')) === 0) {
                continue;
            }

            $data = array();
            $sourceRecord = array();
            foreach ($fields as $index => $field) {
                $sourceField = $header[$index] ?? (string) $index;
                $sourceRecord[$sourceField] = JemImportSecurityHelper::sanitiseValue($sourceField, $raw[$index] ?? '', 'source', $line);

                if ($field === null) {
                    continue;
                }

                $this->addExternalMappedValue($data, $field, $raw[$index] ?? '', $line);
            }
            $this->applyImportStaticValues($data, $staticValues);
            $sourceRecords[] = $sourceRecord;

            $row = $this->normaliseSpecialDaysCsvRow($data, $rowOptions, $line);
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
            'day_type_id' => (int) ($options['day_type_id'] ?? 0),
            'replace' => (int) $options['replace'],
            'show_dates' => (int) ($options['show_dates'] ?? 1),
            'source_fields' => $header,
            'source_records' => $sourceRecords,
            'mapping' => $effectiveMapping,
            'static_values' => $staticValues,
            'record_fields' => $rowOptions['record_fields'],
            'profile_title' => $options['profile_title'] ?? '',
            'summary' => Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    protected function buildSpecialDaysPreviewFromRecords(array $records, array $options, array $sourceFields = array())
    {
        if (!$records) {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_EXTERNAL_STRUCTURED_NO_RECORDS'));
        }

        if (!$sourceFields) {
            $sourceFields = array_keys(reset($records) ?: array());
        }

        $effectiveMapping = $this->getEffectiveSpecialDaysMapping($sourceFields, $options['mapping'] ?? array());
        $fields = $this->normaliseSpecialDaysCsvHeader($sourceFields, $effectiveMapping);
        $staticValues = $this->normaliseImportStaticValues($options['static_values'] ?? array());
        $rowOptions = $options;
        $rowOptions['mapping'] = $effectiveMapping;
        $rowOptions['static_values'] = $staticValues;
        $rowOptions['record_fields'] = $this->mergeImportRecordFields($this->getSpecialDaysRecordFields($effectiveMapping), $staticValues);
        $rows = array();
        $importRecords = array();
        $valid = 0;
        $errors = 0;
        $skipped = 0;
        $line = 0;

        foreach ($records as $record) {
            $line++;
            $data = array();

            foreach ($sourceFields as $index => $sourceField) {
                $field = $fields[$index] ?? null;

                if ($field === null) {
                    continue;
                }

                $this->addExternalMappedValue($data, $field, $record[$sourceField] ?? '', $line);
            }
            $this->applyImportStaticValues($data, $staticValues);

            $row = $this->normaliseSpecialDaysCsvRow($data, $rowOptions, $line);
            $rows[] = $row['preview'];

            if ($row['valid']) {
                $importRecords[] = $row['record'];
                $valid++;
            } else {
                $errors++;
                $skipped++;
            }
        }

        return array(
            'title' => $options['title'],
            'rows' => $rows,
            'records' => $importRecords,
            'valid_count' => $valid,
            'error_count' => $errors,
            'skipped_count' => $skipped,
            'has_errors' => $errors > 0,
            'day_type' => $options['day_type'],
            'day_type_id' => (int) ($options['day_type_id'] ?? 0),
            'replace' => (int) $options['replace'],
            'show_dates' => (int) ($options['show_dates'] ?? 1),
            'source_fields' => $sourceFields,
            'source_records' => $records,
            'mapping' => $effectiveMapping,
            'static_values' => $staticValues,
            'record_fields' => $rowOptions['record_fields'],
            'profile_title' => $options['profile_title'] ?? '',
            'summary' => Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    protected function buildSpecialDaysJsonPreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        $json = json_decode($content, true);

        if (!is_array($json)) {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        $records = $this->findExternalStructuredRecords($json);

        return $this->buildSpecialDaysPreviewFromRecords($records, $options);
    }

    protected function buildSpecialDaysXmlPreview(array $file, array $options)
    {
        $content = file_get_contents($file['tmp_name']);

        if ($content === false || trim($content) === '') {
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            libxml_clear_errors();
            return $this->emptySpecialDaysPreview($options, 1, Text::_('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        return $this->buildSpecialDaysPreviewFromRecords($this->extractExternalXmlRecords($xml), $options);
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

        $sourceFields = array('SUMMARY', 'DTSTART', 'DTEND', 'DESCRIPTION', 'UID', 'LOCATION');
        $sourceRecords = $this->buildSpecialDaysIcsSourceRecords($events);

        if ($sourceRecords) {
            return $this->buildSpecialDaysPreviewFromRecords($sourceRecords, $options, $sourceFields);
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
            'day_type_id' => (int) ($options['day_type_id'] ?? 0),
            'replace' => (int) $options['replace'],
            'show_dates' => (int) ($options['show_dates'] ?? 1),
            'source_fields' => $sourceFields,
            'source_records' => $sourceRecords,
            'mapping' => array(),
            'record_fields' => $this->getSpecialDaysRecordFields(array()),
            'profile_title' => $options['profile_title'] ?? '',
            'summary' => Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_PREVIEW_SUMMARY', $valid, $errors),
        );
    }

    protected function buildSpecialDaysIcsSourceRecords(array $events): array
    {
        $records = array();

        foreach ($events as $event) {
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

            $records[] = array(
                'SUMMARY' => JemImportSecurityHelper::sanitiseValue('SUMMARY', (string) $this->getExternalIcsValue($event, 'SUMMARY'), 'source'),
                'DTSTART' => JemImportSecurityHelper::sanitiseValue('DTSTART', (string) ($start['date'] ?? ''), 'source'),
                'DTEND' => JemImportSecurityHelper::sanitiseValue('DTEND', (string) ($end['date'] ?? ''), 'source'),
                'DESCRIPTION' => JemImportSecurityHelper::sanitiseValue('DESCRIPTION', (string) $this->getExternalIcsValue($event, 'DESCRIPTION'), 'source'),
                'UID' => JemImportSecurityHelper::sanitiseValue('UID', (string) $this->getExternalIcsValue($event, 'UID'), 'source'),
                'LOCATION' => JemImportSecurityHelper::sanitiseValue('LOCATION', (string) $this->getExternalIcsValue($event, 'LOCATION'), 'source'),
            );
        }

        return $records;
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
            'day_type_id' => (int) ($options['day_type_id'] ?? 0),
            'replace' => (int) $options['replace'],
            'show_dates' => (int) ($options['show_dates'] ?? 1),
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

        try {
            $result = $this->storeSpecialDaysRecords($preview['records'], !empty($preview['replace']));
        } catch (RuntimeException $e) {
            $msg = Text::sprintf('COM_JEM_IMPORT_SECURITY_BLOCKED', $e->getMessage());
            $this->addImportLogEntry('special_days', $msg, Log::WARNING);
            $this->setRedirect('index.php?option=com_jem&view=import#special-days', $msg, 'error');
            return;
        }
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
        $title = JemImportSecurityHelper::sanitiseValue('title', $title, 'events', $line);
        $description = JemImportSecurityHelper::sanitiseValue('introtext', $description, 'events', $line);
        $location = JemImportSecurityHelper::sanitiseValue('location', $location, 'events', $line);
        $uid = JemImportSecurityHelper::sanitiseValue('uid', $uid, 'events', $line);
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
        return array_values($this->normaliseExternalSourceFields($header));
    }

    protected function normaliseExternalSourceFields(array $sourceFields, array $mapping = array(), $context = 'events')
    {
        $eventAliases = array(
            'title' => 'title',
            'name' => 'title',
            'nombre' => 'title',
            'summary' => 'title',
            'event' => 'title',
            'event_title' => 'title',
            'date' => 'dates',
            'dates' => 'dates',
            'start' => 'dates',
            'start_date' => 'dates',
            'dtstart' => 'start_datetime',
            'end' => 'enddates',
            'end_date' => 'enddates',
            'enddates' => 'enddates',
            'dtend' => 'end_datetime',
            'time' => 'times',
            'start_time' => 'times',
            'times' => 'times',
            'end_time' => 'endtimes',
            'endtimes' => 'endtimes',
            'datetime' => 'start_datetime',
            'start_datetime' => 'start_datetime',
            'end_datetime' => 'end_datetime',
            'description' => 'introtext',
            'descripcion' => 'introtext',
            'descripcion_entidad' => 'introtext',
            'introtext' => 'introtext',
            'text' => 'introtext',
        );
        $venueAliases = array(
            'venue' => 'venue',
            'title' => 'venue',
            'name' => 'venue',
            'nombre' => 'venue',
            'nombre_entidad' => 'venue',
            'fuente' => 'venue',
            'url' => 'url',
            'link' => 'url',
            'relation' => 'url',
            'content_url' => 'url',
            'street' => 'street',
            'street_address' => 'street',
            'address_street_address' => 'street',
            'nombre_via' => 'street',
            'postalcode' => 'postalCode',
            'postal_code' => 'postalCode',
            'postalcode' => 'postalCode',
            'codigo_postal' => 'postalCode',
            'city' => 'city',
            'locality' => 'city',
            'address_locality' => 'city',
            'localidad' => 'city',
            'district' => 'district',
            'district_name' => 'district',
            'city_district' => 'district',
            'borough' => 'district',
            'distrito' => 'district',
            'level' => 'level',
            'classification' => 'level',
            'rating' => 'level',
            'nivel' => 'level',
            'capacity' => 'capacity',
            'maximum_capacity' => 'capacity',
            'max_capacity' => 'capacity',
            'aforo' => 'capacity',
            'state' => 'state',
            'province' => 'state',
            'provincia' => 'state',
            'country' => 'country',
            'email' => 'email',
            'e_mail' => 'email',
            'correo' => 'email',
            'correo_electronico' => 'email',
            'phone' => 'phone',
            'telephone' => 'phone',
            'telefono' => 'phone',
            'mobile' => 'mobile',
            'mobile_phone' => 'mobile',
            'movil' => 'mobile',
            'latitude' => 'latitude',
            'latitud' => 'latitude',
            'location_latitude' => 'latitude',
            'longitude' => 'longitude',
            'longitud' => 'longitude',
            'location_longitude' => 'longitude',
            'coordinates' => 'coordinates',
            'coordenadas' => 'coordinates',
            'coordinate' => 'coordinates',
            'coords' => 'coordinates',
            'gps' => 'coordinates',
            'latlng' => 'coordinates',
            'lat_long' => 'coordinates',
            'geo_point_2d' => 'coordinates',
            'description' => 'locdescription',
            'descripcion' => 'locdescription',
            'descripcion_entidad' => 'locdescription',
            'organization_desc' => 'locdescription',
            'organization_organization_desc' => 'locdescription',
        );
        $allowedFields = (string) $context === 'venues'
            ? $this->getExternalVenueAllowedFields()
            : $this->getExternalEventAllowedFields();
        $aliases = (string) $context === 'venues' ? $venueAliases : $eventAliases;

        $fields = array();
        $mappingByKey = array();
        $allowedByKey = array();

        foreach ($allowedFields as $field) {
            $allowedByKey[$this->normaliseExternalSourceKey($field)] = $field;
        }

        foreach ($mapping as $source => $target) {
            $target = trim((string) $target);
            $sourceKey = $this->normaliseExternalSourceKey($source);

            if ($sourceKey === '') {
                continue;
            }

            $mappingByKey[$sourceKey] = $target !== '' && in_array($target, $allowedFields, true) ? $target : '';
        }

        foreach ($sourceFields as $column) {
            $key = $this->normaliseExternalSourceKey($column);
            if (array_key_exists($key, $mappingByKey)) {
                $fields[$column] = $mappingByKey[$key] !== '' ? $mappingByKey[$key] : null;
            } else {
                $fields[$column] = $aliases[$key] ?? ($allowedByKey[$key] ?? null);
            }
        }

        return $fields;
    }

    protected function getEffectiveExternalMapping(array $sourceFields, array $mapping = array(), $context = 'events')
    {
        $fields = $this->normaliseExternalSourceFields($sourceFields, $mapping, $context);
        $effective = array();

        foreach ($fields as $source => $target) {
            if ($target !== null && trim((string) $target) !== '') {
                $effective[$source] = $target;
            }
        }

        return $effective;
    }

    protected function addExternalMappedValue(array &$data, $field, $value, $sourceLine = null)
    {
        $field = trim((string) $field);
        $value = JemImportSecurityHelper::sanitiseValue($field, $value, 'external', $sourceLine);
        $value = trim((string) $value);

        if ($field === '' || $value === '') {
            return;
        }

        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $data[$field] = $value;
            return;
        }

        $data[$field] = rtrim((string) $data[$field]) . ', ' . $value;
    }

    protected function buildExternalRecord(array $fields, array $data)
    {
        $data = JemImportSecurityHelper::sanitiseRecord($data, 'external');
        $record = array();

        foreach ($fields as $field) {
            $record[] = array_key_exists($field, $data) ? $data[$field] : '';
        }

        return $record;
    }

    protected function getExternalEventBaseFields()
    {
        return array(
            'title',
            'alias',
            'dates',
            'enddates',
            'times',
            'endtimes',
            'introtext',
            'fulltext',
            'metadata',
            'published',
            'publish_up',
            'publish_down',
            'type_id',
            'locid',
            'language',
            'categories',
            'online_meeting_url',
            'online_meeting_label',
            'meta_keywords',
            'meta_description',
            'event_status',
            'ticket_availability',
        );
    }

    protected function getExternalVenueBaseFields()
    {
        return array(
            'venue',
            'alias',
            'color',
            'url',
            'street',
            'postalCode',
            'city',
            'district',
            'level',
            'capacity',
            'state',
            'country',
            'email',
            'phone',
            'mobile',
            'latitude',
            'longitude',
            'coordinates',
            'locdescription',
            'meta_keywords',
            'meta_description',
            'locimage',
            'map',
            'published',
            'publish_up',
            'publish_down',
            'access',
            'attribs',
            'language',
            'type_id',
        );
    }

    protected function getExternalCustomFields()
    {
        $fields = array();

        for ($i = 1; $i <= 10; $i++) {
            $fields[] = 'custom' . $i;
        }

        return $fields;
    }

    protected function getExternalEventAllowedFields()
    {
        return array_values(array_unique(array_merge($this->getExternalEventBaseFields(), $this->getExternalCustomFields())));
    }

    protected function getExternalVenueAllowedFields()
    {
        return array_values(array_unique(array_merge($this->getExternalVenueBaseFields(), $this->getExternalCustomFields())));
    }

    protected function getMappedExternalFields(array $mapping, array $allowedFields)
    {
        $fields = array();

        foreach ($mapping as $target) {
            $target = trim((string) $target);

            if ($target !== '' && in_array($target, $allowedFields, true)) {
                $fields[] = $target;
            }
        }

        return array_values(array_unique($fields));
    }

    protected function getExternalEventRecordFields(array $mapping = array())
    {
        $required = array('title', 'dates', 'enddates', 'times', 'endtimes', 'introtext', 'fulltext', 'metadata', 'published', 'publish_up', 'type_id', 'locid', 'language', 'categories');
        $mapped = $this->getMappedExternalFields($mapping, $this->getExternalEventAllowedFields());

        return array_values(array_unique(array_merge($required, $mapped)));
    }

    protected function getExternalVenueRecordFields(array $mapping = array())
    {
        $required = array('venue', 'alias', 'url', 'street', 'postalCode', 'city', 'district', 'level', 'capacity', 'state', 'country', 'email', 'phone', 'mobile', 'latitude', 'longitude', 'locdescription', 'published', 'type_id', 'language', 'map');
        $mapped = array_values(array_diff($this->getMappedExternalFields($mapping, $this->getExternalVenueAllowedFields()), array('coordinates')));

        return array_values(array_unique(array_merge($required, $mapped)));
    }

    protected function getSelectedImportCatalogEntry($context)
    {
        $entry = (array) Factory::getApplication()->getUserState('com_jem.import.catalog.selected', array());

        if (!$entry || JemImportCatalogHelper::getContext($entry['type'] ?? '') !== (string) $context) {
            return array();
        }

        return $entry;
    }

    protected function downloadExternalImportSource($url, array $allowedExtensions, $preferredExtension = '')
    {
        $url = trim((string) $url);
        $parts = parse_url($url);

        if (!$parts || !in_array(strtolower((string) ($parts['scheme'] ?? '')), array('http', 'https'), true)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_URL_INVALID'));
        }

        if (!empty($parts['user']) || !empty($parts['pass']) || empty($parts['host'])) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_URL_INVALID'));
        }

        $extension = strtolower(pathinfo((string) ($parts['path'] ?? ''), PATHINFO_EXTENSION));
        $preferredExtension = strtolower(trim((string) $preferredExtension));

        if ($extension === '' && $preferredExtension !== '') {
            $extension = $preferredExtension;
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_URL_UNSUPPORTED'));
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_URL_INVALID'));
        }

        $context = stream_context_create(array(
            'http' => array(
                'follow_location' => 3,
                'ignore_errors' => false,
                'method' => 'GET',
                'timeout' => 20,
                'user_agent' => 'JEM import catalog',
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $content = @file_get_contents($url, false, $context, 0, 10485761);

        if ($content === false || trim((string) $content) === '') {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_URL_DOWNLOAD_FAILED'));
        }

        if (strlen($content) > 10485760) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_URL_TOO_LARGE'));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'jem-import-');

        if (!$tmp || file_put_contents($tmp, $content) === false) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        return array(
            'name' => basename((string) ($parts['path'] ?? 'catalog-source.' . $extension)) ?: ('catalog-source.' . $extension),
            'tmp_name' => $tmp,
            'error' => 0,
            'size' => strlen($content),
            'type' => '',
        );
    }

    protected function normaliseExternalSourceKey($value)
    {
        $key = strtolower(trim((string) $value));
        $key = strtr($key, array(
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key);

        return trim($key, '_');
    }

    protected function getPostedImportMapping($name)
    {
        $mapping = Factory::getApplication()->input->post->get((string) $name, array(), 'array');
        $clean = array();

        foreach ((array) $mapping as $source => $target) {
            $source = trim((string) $source);
            $target = trim((string) $target);

            if ($source === '') {
                continue;
            }

            $clean[$source] = $target;
        }

        return $clean;
    }

    protected function getPostedImportStaticValues($name)
    {
        $rows = Factory::getApplication()->input->post->get((string) $name, array(), 'array');

        return $this->normaliseImportStaticValues($rows);
    }

    protected function normaliseImportStaticValues(array $rows)
    {
        $clean = array();

        foreach ($rows as $row) {
            $field = trim((string) ($row['field'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $mode = trim((string) ($row['mode'] ?? 'if_empty'));

            if ($field === '' || $value === '' || !preg_match('/^[A-Za-z0-9_]+$/', $field)) {
                continue;
            }

            $clean[] = array(
                'field' => $field,
                'value' => JemImportSecurityHelper::sanitiseValue($field, $value, 'source'),
                'mode' => $mode === 'always' ? 'always' : 'if_empty',
            );
        }

        return $clean;
    }

    protected function applyImportStaticValues(array &$data, array $staticValues)
    {
        foreach ($staticValues as $staticValue) {
            $field = (string) ($staticValue['field'] ?? '');
            $value = (string) ($staticValue['value'] ?? '');
            $mode = (string) ($staticValue['mode'] ?? 'if_empty');

            if ($field === '' || $value === '') {
                continue;
            }

            if ($mode === 'always' || trim((string) ($data[$field] ?? '')) === '') {
                $data[$field] = $value;
            }
        }
    }

    protected function mergeImportRecordFields(array $fields, array $staticValues)
    {
        foreach ($staticValues as $staticValue) {
            $field = (string) ($staticValue['field'] ?? '');

            if ($field !== '' && !in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return array_values($fields);
    }

    protected function normaliseImportProfileOptions(array $options)
    {
        $clean = array();
        $staticValues = $this->normaliseImportStaticValues((array) ($options['static_values'] ?? array()));

        if ($staticValues) {
            $clean['static_values'] = $staticValues;
        }

        foreach (array('catid', 'type_id', 'locid') as $key) {
            if (array_key_exists($key, $options)) {
                $clean[$key] = max(0, (int) $options[$key]);
            }
        }

        if (array_key_exists('published', $options)) {
            $clean['published'] = empty($options['published']) ? 0 : 1;
        }

        if (isset($options['mode']) && in_array((string) $options['mode'], array('standard', 'openday'), true)) {
            $clean['mode'] = (string) $options['mode'];
        }

        if (isset($options['language']) && preg_match('/^(?:\*|[a-z]{2,3}-[A-Z]{2})$/', (string) $options['language'])) {
            $clean['language'] = (string) $options['language'];
        }

        if (isset($options['publish_up'])) {
            $publishUp = trim((string) $options['publish_up']);

            if ($publishUp === '' || preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/', $publishUp)) {
                $clean['publish_up'] = $publishUp;
            }
        }

        if (isset($options['source_mode']) && in_array((string) $options['source_mode'], array('url', 'file'), true)) {
            $clean['source_mode'] = (string) $options['source_mode'];
        }

        if (!empty($options['source_url'])) {
            $sourceUrl = trim((string) $options['source_url']);

            if (strlen($sourceUrl) <= 2048 && filter_var($sourceUrl, FILTER_VALIDATE_URL)
                && in_array(strtolower((string) parse_url($sourceUrl, PHP_URL_SCHEME)), array('http', 'https'), true)) {
                $clean['source_url'] = $sourceUrl;
            }
        }

        if (!empty($options['source_name'])) {
            $sourceName = trim(strip_tags((string) $options['source_name']));

            if ($sourceName !== '' && strlen($sourceName) <= 2048) {
                $clean['source_name'] = $sourceName;
            }
        }

        return $clean;
    }

    protected function saveExternalImportProfile($context, $format, $title, array $mapping, array $options = array())
    {
        $title = trim((string) $title);
        $preserveExisting = !empty($options['_preserve_existing']);
        unset($options['_preserve_existing']);
        $options = $this->normaliseImportProfileOptions($options);

        if ($title === '' || (!$mapping && !$options)) {
            return null;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getApplication()->getIdentity();
        $now = Factory::getDate()->toSql();

        try {
            $query = $db->getQuery(true)
                ->select(array($db->quoteName('id'), $db->quoteName('options')))
                ->from($db->quoteName('#__jem_import_profiles'))
                ->where($db->quoteName('context') . ' = ' . $db->quote((string) $context))
                ->where($db->quoteName('source_format') . ' = ' . $db->quote(strtolower((string) $format)))
                ->where($db->quoteName('title') . ' = ' . $db->quote($title));
            $db->setQuery($query);
            $existing = $db->loadAssoc();
            $existingId = (int) ($existing['id'] ?? 0);

            if ($existingId > 0) {
                if ($preserveExisting) {
                    $existingOptions = json_decode((string) ($existing['options'] ?? ''), true);
                    $options = array_replace(is_array($existingOptions) ? $existingOptions : array(), $options);
                }

                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__jem_import_profiles'))
                    ->set($db->quoteName('mapping') . ' = ' . $db->quote(json_encode($mapping)))
                    ->set($db->quoteName('options') . ' = ' . $db->quote(json_encode((object) $options)))
                    ->set($db->quoteName('published') . ' = 1')
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                    ->where($db->quoteName('id') . ' = ' . (int) $existingId);
                $db->setQuery($query);
                $db->execute();

                return array(
                    'id' => $existingId,
                    'title' => $title,
                );
            }

            $query = $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ')')
                ->from($db->quoteName('#__jem_import_profiles'))
                ->where($db->quoteName('context') . ' = ' . $db->quote((string) $context));
            $db->setQuery($query);
            $ordering = (int) $db->loadResult() + 1;

            $columns = array(
                'title',
                'context',
                'source_format',
                'mapping',
                'options',
                'published',
                'access',
                'ordering',
                'created',
                'created_by',
                'modified',
                'modified_by',
            );
            $values = array(
                $db->quote($title),
                $db->quote((string) $context),
                $db->quote(strtolower((string) $format)),
                $db->quote(json_encode($mapping)),
                $db->quote(json_encode((object) $options)),
                1,
                1,
                (int) $ordering,
                $db->quote($now),
                (int) $user->id,
                $db->quote($now),
                (int) $user->id,
            );

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__jem_import_profiles'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $db->setQuery($query);
            $db->execute();

            return array(
                'id' => (int) $db->insertid(),
                'title' => $title,
            );
        } catch (RuntimeException $e) {
            return null;
        }
    }

    protected function getExternalImportProfile($profileId, $format, $context = 'events')
    {
        $profileId = (int) $profileId;

        if ($profileId <= 0) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jem_import_profiles'))
                ->where($db->quoteName('id') . ' = ' . (int) $profileId)
                ->where($db->quoteName('context') . ' = ' . $db->quote((string) $context))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('access') . ' IN (' . implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels())) . ')');
            $db->setQuery($query);
            $profile = $db->loadAssoc();
        } catch (RuntimeException $e) {
            return array();
        }

        if (!$profile) {
            return array();
        }

        $profileFormat = strtolower((string) ($profile['source_format'] ?? ''));
        if ($profileFormat !== '' && $profileFormat !== strtolower((string) $format)) {
            return array();
        }

        $mapping = json_decode((string) ($profile['mapping'] ?? ''), true);
        $options = json_decode((string) ($profile['options'] ?? ''), true);

        $profile['mapping'] = is_array($mapping) ? $mapping : array();
        $profile['options'] = is_array($options) ? $options : array();

        return $profile;
    }

    protected function findExternalStructuredRecords(array $data)
    {
        if ($this->isExternalRecordList($data)) {
            $records = array();

            foreach ($data as $index => $record) {
                $flat = $this->flattenExternalStructuredRecord($record);
                $records[] = $this->sanitiseExternalSourceRecord($flat, 'source_json', $index + 1);
            }

            return $records;
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $records = $this->findExternalStructuredRecords($value);

                if ($records) {
                    return $records;
                }
            }
        }

        return array();
    }

    protected function sanitiseExternalSourceRecord(array $record, $context, $sourceLine)
    {
        $warnings = array();
        $record = JemImportSecurityHelper::sanitiseSourceRecord($record, $context, $sourceLine, $warnings);

        if ($warnings) {
            $this->externalSourceWarningCount += count($warnings);
            $remaining = max(0, 100 - count($this->externalSourceWarnings));

            if ($remaining > 0) {
                $this->externalSourceWarnings = array_merge($this->externalSourceWarnings, array_slice($warnings, 0, $remaining));
            }
        }

        return $record;
    }

    protected function isExternalRecordList(array $data)
    {
        if (!$data) {
            return false;
        }

        $first = reset($data);

        return is_array($first) && array_keys($data) === range(0, count($data) - 1);
    }

    protected function flattenExternalStructuredRecord(array $record, $prefix = '')
    {
        $flat = array();

        foreach ($record as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                if ($this->isExternalRecordList($value)) {
                    $flat[$path] = implode(', ', array_map(static function ($item) {
                        return is_scalar($item) ? (string) $item : json_encode($item);
                    }, $value));
                } else {
                    $flat += $this->flattenExternalStructuredRecord($value, $path);
                }
            } else {
                $flat[$path] = is_scalar($value) ? (string) $value : '';
            }

        }

        return $flat;
    }

    protected function extractExternalXmlRecords(SimpleXMLElement $xml)
    {
        $records = array();

        foreach ($xml->xpath('//contenido') ?: array() as $contenido) {
            $record = array();

            foreach ($contenido->xpath('.//atributo[@nombre]') ?: array() as $attribute) {
                $name = (string) $attribute['nombre'];
                $value = trim((string) $attribute);

                if ($name !== '' && $value !== '') {
                    $record[$name] = JemImportSecurityHelper::sanitiseValue($name, $value, 'source');
                }
            }

            if ($record) {
                $records[] = $record;
            }
        }

        if ($records) {
            return $records;
        }

        foreach ($xml->children() as $child) {
            $flat = $this->flattenExternalXmlNode($child);

            if ($flat) {
                $records[] = $flat;
            }
        }

        return $records;
    }

    protected function flattenExternalXmlNode(SimpleXMLElement $node, $prefix = '')
    {
        $flat = array();
        $name = $node->getName();
        $path = $prefix === '' ? $name : $prefix . '.' . $name;
        $children = $node->children();

        if ($children->count() === 0) {
            $flat[$path] = JemImportSecurityHelper::sanitiseValue($path, trim((string) $node), 'source');
            return $flat;
        }

        foreach ($children as $child) {
            $flat += $this->flattenExternalXmlNode($child, $path);
        }

        return $flat;
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

        $recordData = array(
            'title' => $title,
            'dates' => $startDate,
            'enddates' => $endDate,
            'times' => $startTime,
            'endtimes' => $endTime,
            'introtext' => trim((string) ($data['introtext'] ?? '')),
            'fulltext' => (string) ($data['fulltext'] ?? ''),
            'metadata' => (string) ($data['metadata'] ?? '{}'),
            'published' => array_key_exists('published', $data) ? (int) $data['published'] : (int) $options['published'],
            'publish_up' => (string) ($data['publish_up'] ?? $options['publish_up']),
            'type_id' => array_key_exists('type_id', $data) ? (int) $data['type_id'] : (!empty($options['type_id']) ? (int) $options['type_id'] : null),
            'locid' => array_key_exists('locid', $data) ? (int) $data['locid'] : (!empty($options['locid']) ? (int) $options['locid'] : null),
            'language' => (string) ($data['language'] ?? $options['language']),
            'categories' => (string) ($data['categories'] ?? (int) $options['catid']),
        );

        foreach ($data as $field => $value) {
            if (!array_key_exists($field, $recordData)) {
                $recordData[$field] = $value;
            }
        }
        $recordData = JemImportSecurityHelper::sanitiseRecord($recordData, 'events', $line);

        return array(
            'valid' => $valid,
            'record' => $this->buildExternalRecord((array) ($options['record_fields'] ?? $this->getExternalEventRecordFields()), $recordData),
            'preview' => array(
                'status' => $status,
                'title' => $title !== '' ? $title : Text::sprintf('COM_JEM_IMPORT_EXTERNAL_UNTITLED_ROW', $line),
                'date_label' => trim(($startDate ?: '-') . ($endDate ? ' - ' . $endDate : '')),
                'time_label' => trim(($startTime ?: '-') . ($endTime ? ' - ' . $endTime : '')),
                'notes' => $notes,
                'import_data' => $recordData,
            ),
        );
    }

    protected function normaliseExternalVenueRow(array $data, array $options, $line)
    {
        $notes = array();
        $venue = trim((string) ($data['venue'] ?? ''));
        $street = trim((string) ($data['street'] ?? ''));
        $postalCode = trim((string) ($data['postalCode'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $district = trim((string) ($data['district'] ?? ''));
        $level = trim((string) ($data['level'] ?? ''));
        $capacityRaw = trim((string) ($data['capacity'] ?? ''));
        $capacity = $capacityRaw === '' ? 0 : filter_var($capacityRaw, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 4294967295)));
        $state = trim((string) ($data['state'] ?? ''));
        $country = strtoupper(trim((string) ($data['country'] ?? 'ES')));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $latitude = $this->normaliseExternalVenueCoordinate($data['latitude'] ?? '');
        $longitude = $this->normaliseExternalVenueCoordinate($data['longitude'] ?? '');
        $combinedCoordinates = JemImportVenueHelper::normaliseCoordinatePair($data['coordinates'] ?? '');

        if ($combinedCoordinates !== null) {
            $latitude = $latitude ?? $combinedCoordinates['latitude'];
            $longitude = $longitude ?? $combinedCoordinates['longitude'];
        }
        $description = trim((string) ($data['locdescription'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if (strlen($country) !== 2) {
            $country = 'ES';
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_NOTE_COUNTRY_DEFAULT');
        }

        if ($url !== '' && strlen($url) > 199) {
            $url = '';
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_NOTE_URL_TOO_LONG');
        }

        $valid = true;

        if (StringHelper::strlen($level) > 100) {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_ERROR_LEVEL_TOO_LONG');
        }

        if ($capacity === false) {
            $valid = false;
            $capacity = 0;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_ERROR_CAPACITY_INVALID');
        }

        if ($venue === '') {
            $valid = false;
            $notes[] = Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_ERROR_MISSING_VENUE');
        }

        $status = $valid ? Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK') : Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR');

        $recordData = array(
            'venue' => $venue,
            'alias' => (string) ($data['alias'] ?? ''),
            'url' => $url,
            'street' => $street,
            'postalCode' => $postalCode,
            'city' => $city,
            'district' => $district,
            'level' => $level,
            'capacity' => (int) $capacity,
            'state' => $state,
            'country' => $country,
            'email' => $email,
            'phone' => $phone,
            'mobile' => $mobile,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'locdescription' => $description,
            'published' => array_key_exists('published', $data) ? (int) $data['published'] : (int) $options['published'],
            'type_id' => array_key_exists('type_id', $data) ? (int) $data['type_id'] : (!empty($options['type_id']) ? (int) $options['type_id'] : null),
            'language' => (string) ($data['language'] ?? $options['language']),
            'map' => array_key_exists('map', $data) ? (int) $data['map'] : (($latitude !== null && $longitude !== null) ? 1 : 0),
        );

        foreach ($data as $field => $value) {
            if (!array_key_exists($field, $recordData)) {
                $recordData[$field] = $value;
            }
        }
        $recordData = JemImportSecurityHelper::sanitiseRecord($recordData, 'venues', $line);

        return array(
            'valid' => $valid,
            'record' => $this->buildExternalRecord((array) ($options['record_fields'] ?? $this->getExternalVenueRecordFields()), $recordData),
            'preview' => array(
                'status' => $status,
                'venue' => $venue !== '' ? $venue : Text::sprintf('COM_JEM_IMPORT_EXTERNAL_UNTITLED_ROW', $line),
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'notes' => $notes,
                'import_data' => $recordData,
            ),
        );
    }

    protected function normaliseExternalVenueCoordinate($value)
    {
        $value = trim(str_replace(',', '.', (string) $value));

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 6, '.', '');
    }

    protected function normaliseExternalCsvDate($date)
    {
        $date = trim((string) $date);

        if ($date === '' || strtoupper($date) === 'NULL' || $date === '0000-00-00') {
            return null;
        }

        if (preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{4}$/', $date)) {
            $format = strpos($date, '/') !== false ? '!d/m/Y' : '!d-m-Y';
            $dateValue = \DateTime::createFromFormat($format, $date);
            $errors = \DateTime::getLastErrors();

            if ($dateValue && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $dateValue->format('Y-m-d');
            }

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

    protected function resolveSpecialDaysImportType($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return array('id' => 0, 'name' => '');
        }

        $type = JemHelper::resolveCalendarSpecialDayType($value);

        if ($type) {
            return array(
                'id' => (int) ($type['id'] ?? 0),
                'name' => (string) ($type['name'] ?? ''),
            );
        }

        return array(
            'id' => 0,
            'name' => $value,
        );
    }

    protected function normaliseSpecialDaysCsvHeader(array $header, array $mapping = array())
    {
        $aliases = array(
            'name' => 'title',
            'summary' => 'title',
            'type' => 'day_type',
            'typeid' => 'day_type_id',
            'type_id' => 'day_type_id',
            'daytypeid' => 'day_type_id',
            'day_type_id' => 'day_type_id',
            'daytype' => 'day_type',
            'day_type' => 'day_type',
            'start' => 'start_date',
            'startdate' => 'start_date',
            'start_date' => 'start_date',
            'date' => 'start_date',
            'dtstart' => 'start_date',
            'end' => 'end_date',
            'enddate' => 'end_date',
            'end_date' => 'end_date',
            'dtend' => 'end_date',
            'weekday' => 'weekdays',
            'weekdays' => 'weekdays',
            'desc' => 'description',
            'text' => 'description',
            'description' => 'description',
            'showdays' => 'show_dates',
            'show_days' => 'show_dates',
            'showdates' => 'show_dates',
            'show_dates' => 'show_dates',
            'listdays' => 'show_dates',
            'list_days' => 'show_dates',
            'listdates' => 'show_dates',
            'list_dates' => 'show_dates',
            'accesslevel' => 'access',
            'access_level' => 'access',
            'viewlevel' => 'access',
            'view_level' => 'access',
        );
        $allowed = $this->getSpecialDaysAllowedFields();
        $fields = array();
        $mappingByKey = array();

        foreach ($mapping as $source => $target) {
            $target = trim((string) $target);
            $sourceKey = $this->normaliseExternalSourceKey($source);

            if ($sourceKey === '') {
                continue;
            }

            $mappingByKey[$sourceKey] = $target !== '' && in_array($target, $allowed, true) ? $target : '';
        }

        foreach ($header as $column) {
            $key = strtolower(trim((string) $column));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim($key, '_');
            $sourceKey = $this->normaliseExternalSourceKey($column);

            if (array_key_exists($sourceKey, $mappingByKey)) {
                $key = $mappingByKey[$sourceKey];
            } else {
                $key = $aliases[$key] ?? $key;
            }
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
        $rawDayTypeId = isset($data['day_type_id']) ? trim((string) $data['day_type_id']) : '';
        $rawDayType = trim((string) ($data['day_type'] ?? ''));
        $dayTypeId = $rawDayTypeId !== '' ? (int) $rawDayTypeId : 0;
        $dayType = $rawDayType;
        $fallbackType = trim((string) ($options['day_type'] ?? ''));
        $fallbackTypeId = (int) ($options['day_type_id'] ?? 0);
        $hasFileDayType = $rawDayTypeId !== '' || $rawDayType !== '';
        $dayTypeStatus = array(
            'state' => $hasFileDayType ? 'missing' : 'fallback',
            'source' => $rawDayType !== '' ? $rawDayType : $rawDayTypeId,
            'resolved' => '',
            'fallback' => $fallbackType,
        );

        if ($dayTypeId > 0 && $dayType === '') {
            $resolvedType = $this->resolveSpecialDaysImportType($dayTypeId);
            if ((int) ($resolvedType['id'] ?? 0) > 0) {
                $dayType = (string) ($resolvedType['name'] ?? '');
            }
        }

        if ($dayTypeId <= 0 && $dayType !== '') {
            $resolvedType = $this->resolveSpecialDaysImportType($dayType);
            $dayTypeId = (int) ($resolvedType['id'] ?? 0);
            if ($dayTypeId > 0) {
                $dayType = (string) ($resolvedType['name'] ?? $dayType);
            }
        }

        if ($hasFileDayType && $dayTypeId > 0 && $dayType !== '') {
            $dayTypeStatus['state'] = 'ok';
            $dayTypeStatus['resolved'] = $dayType;
        } elseif ($hasFileDayType) {
            $notes[] = Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_NOTE_TYPE_FALLBACK_USED', $dayTypeStatus['source'], $fallbackType);
            $dayType = $fallbackType;
            $dayTypeId = $fallbackTypeId;
            $dayTypeStatus['state'] = 'error';
            $dayTypeStatus['resolved'] = $dayType;
        }

        if ($dayType === '') {
            $dayType = $fallbackType;
            $dayTypeId = $fallbackTypeId;
            $dayTypeStatus['resolved'] = $dayType;
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
            'day_type_id' => $dayTypeId,
            'day_type' => $dayType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'weekdays' => $weekdays,
            'country' => trim((string) ($data['country'] ?? '')),
            'region' => trim((string) ($data['region'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'article_id' => isset($data['article_id']) ? max(0, (int) $data['article_id']) : 0,
            'url' => trim((string) ($data['url'] ?? '')),
            'show_dates' => $this->normaliseSpecialDaysBoolean($data['show_dates'] ?? ($options['show_dates'] ?? 1), $options['show_dates'] ?? 1),
            'published' => isset($data['published']) && trim((string) $data['published']) !== '' ? (int) $data['published'] : 1,
            'access' => isset($data['access']) && trim((string) $data['access']) !== '' ? max(1, (int) $data['access']) : 1,
            'ordering' => isset($data['ordering']) ? (int) $data['ordering'] : 0,
        );
        $record = JemImportSecurityHelper::sanitiseRecord($record, 'specialdays', $line);

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
                'import_data' => $record,
                'field_status' => array(
                    'day_type' => $dayTypeStatus,
                    'day_type_id' => $dayTypeStatus,
                ),
            ),
        );
    }

    protected function getSpecialDaysAllowedFields()
    {
        return array('id', 'title', 'alias', 'day_type_id', 'day_type', 'start_date', 'end_date', 'weekdays', 'country', 'region', 'city', 'description', 'article_id', 'url', 'show_dates', 'published', 'access', 'ordering');
    }

    protected function getSpecialDaysRecordFields(array $mapping = array())
    {
        $required = array('title', 'day_type_id', 'day_type', 'start_date', 'end_date', 'weekdays', 'country', 'region', 'city', 'description', 'article_id', 'url', 'show_dates', 'published', 'access', 'ordering');
        $mapped = $this->getMappedExternalFields($mapping, $this->getSpecialDaysAllowedFields());

        return array_values(array_unique(array_merge($required, $mapped)));
    }

    protected function getEffectiveSpecialDaysMapping(array $sourceFields, array $mapping = array())
    {
        $fields = $this->normaliseSpecialDaysCsvHeader($sourceFields, $mapping);
        $effective = array();

        foreach (array_values($sourceFields) as $index => $source) {
            $target = $fields[$index] ?? null;

            if ($target !== null && trim((string) $target) !== '') {
                $effective[$source] = $target;
            }
        }

        return $effective;
    }

    protected function normaliseSpecialDaysIcsEvent(array $event, array $options, $line)
    {
        $notes = array();
        $title = trim((string) $this->getExternalIcsValue($event, 'SUMMARY'));
        $description = trim((string) $this->getExternalIcsValue($event, 'DESCRIPTION'));
        $title = JemImportSecurityHelper::sanitiseValue('title', $title, 'specialdays', $line);
        $description = JemImportSecurityHelper::sanitiseValue('description', $description, 'specialdays', $line);
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
        $dayTypeId = (int) ($options['day_type_id'] ?? 0);
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
            'day_type_id' => $dayTypeId,
            'day_type' => $dayType,
            'start_date' => $start['date'],
            'end_date' => $end['date'],
            'weekdays' => '',
            'country' => '',
            'region' => '',
            'city' => '',
            'description' => $description,
            'show_dates' => $this->normaliseSpecialDaysBoolean($options['show_dates'] ?? 1, 1),
            'published' => 1,
            'access' => 1,
            'ordering' => 0,
        );
        $record = JemImportSecurityHelper::sanitiseRecord($record, 'specialdays', $line);

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
                'field_status' => array(
                    'day_type' => array(
                        'state' => 'fallback',
                        'source' => '',
                        'resolved' => $dayType,
                        'fallback' => $dayType,
                    ),
                    'day_type_id' => array(
                        'state' => 'fallback',
                        'source' => '',
                        'resolved' => $dayType,
                        'fallback' => $dayType,
                    ),
                ),
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

    protected function normaliseSpecialDaysBoolean($value, $default = 1)
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return (int) $default;
        }

        if (in_array($value, array('1', 'yes', 'y', 'true', 'on', 'si', 'sí'), true)) {
            return 1;
        }

        if (in_array($value, array('0', 'no', 'n', 'false', 'off'), true)) {
            return 0;
        }

        return (int) $default;
    }

    protected function storeSpecialDaysRecords(array $records, $replace)
    {
        $result = array('added' => 0, 'updated' => 0, 'ignored' => 0, 'error' => 0);
        $now = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        foreach ($records as $record) {
            $record = JemImportSecurityHelper::sanitiseRecord($record, 'specialdays');
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
