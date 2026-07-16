<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importcatalog.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importsecurity.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importpreview.php';

/**
 * View class for the JEM import screen
 *
 * @package JEM
 *
 */

class JemViewImport extends JemAdminView
{

    public function display($tpl = null) {
        // Load css
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->usePreset('choicesjs')->useScript('webcomponent.field-fancy-select');

        // Get data from the model
        $eventfields = $this->get('EventFields');
        $catfields   = $this->get('CategoryFields');
        $venuefields = $this->get('VenueFields');
        $cateventsfields = $this->get('CateventsFields');
        $attachmentfields = $this->get('AttachmentFields');
        $typefields = $this->get('TypeFields');

        //assign vars to the template
        $this->eventfields         = $eventfields;
        $this->catfields           = $catfields;
        $this->venuefields         = $venuefields;
        $this->cateventsfields     = $cateventsfields;
        $this->attachmentfields    = $attachmentfields;
        $this->typefields          = $typefields;

        $this->eventlistVersion = $this->get('EventlistVersion');
        $this->eventlistTables     = $this->get('EventlistTablesCount');
        $this->jemTables         = $this->get('JemTablesCount');
        $this->existingJemData     = $this->get('ExistingJemData');

        $app = Factory::getApplication();
        $jinput = $app->input;
        $progress = new stdClass();
        $progress->step     = $jinput->get('step', 0, 'INT');
        $progress->current     = $jinput->get('current', 0, 'INT');
        $progress->total     = $jinput->get('total', 0, 'INT');
        $progress->table     = $jinput->get('table', '', 'INT');
        $progress->prefix     = $jinput->get('prefix', null, 'CMD');
        $progress->copyImages = $jinput->get('copyImages', null, 'INT');
        $progress->copyAttachments = $jinput->get('copyAttachments', null, 'INT');
        $this->progress = $progress;
        $this->attachmentsPossible = !empty($this->eventlistTables['eventlist_attachments']);
        $this->importLogPath = rtrim($app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\');
        $this->importLogs = array(
            array('key' => 'external_csv', 'label' => Text::_('COM_JEM_IMPORT_EXTERNAL_CSV_TITLE'), 'file' => 'jem-import-external-csv.log.php'),
            array('key' => 'external_ics', 'label' => Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_TITLE'), 'file' => 'jem-import-external-ics.log.php'),
            array('key' => 'jem_venues', 'label' => Text::_('COM_JEM_IMPORT_VENUES'), 'file' => 'jem-import-venues.log.php'),
            array('key' => 'jem_categories', 'label' => Text::_('COM_JEM_IMPORT_CATEGORIES'), 'file' => 'jem-import-categories.log.php'),
            array('key' => 'jem_events', 'label' => Text::_('COM_JEM_IMPORT_EVENTS'), 'file' => 'jem-import-events.log.php'),
            array('key' => 'jem_catevents', 'label' => Text::_('COM_JEM_IMPORT_CAT_EVENTS'), 'file' => 'jem-import-catevents.log.php'),
            array('key' => 'jem_attachments', 'label' => Text::_('COM_JEM_IMPORT_ATTACHMENTS'), 'file' => 'jem-import-attachments.log.php'),
            array('key' => 'jem_types', 'label' => Text::_('COM_JEM_IMPORT_TYPES'), 'file' => 'jem-import-types.log.php'),
            array('key' => 'special_days', 'label' => Text::_('COM_JEM_SPECIAL_DAYS'), 'file' => 'jem-import-specialdays.log.php'),
        );
        $requestedImportPreview = $jinput->getCmd('preview', '');
        $activeImportPreview = in_array($requestedImportPreview, array('events', 'venues', 'specialdays'), true)
            ? $requestedImportPreview
            : (string) $app->getUserState('com_jem.import.active_preview', '');
        $app->setUserState('com_jem.import.active_preview', null);
        $applyProfileSelection = $jinput->getBool('profile_selection', false);
        $this->selectedExternalImportProfileId = $applyProfileSelection ? (int) $app->getUserState('com_jem.import.external_import.selected_profile_id', 0) : 0;
        $this->selectedExternalVenueImportProfileId = $applyProfileSelection ? (int) $app->getUserState('com_jem.import.external_venue_import.selected_profile_id', 0) : 0;
        $this->selectedSpecialDaysImportProfileId = $applyProfileSelection ? (int) $app->getUserState('com_jem.import.specialdays_import.selected_profile_id', 0) : 0;
        $app->setUserState('com_jem.import.external_import.selected_profile_id', null);
        $app->setUserState('com_jem.import.external_venue_import.selected_profile_id', null);
        $app->setUserState('com_jem.import.specialdays_import.selected_profile_id', null);
        $this->specialDaysImportFormState = (array) $app->getUserState('com_jem.import.specialdays_import.form', array());
        $this->selectedImportCatalogEntry = (array) $app->getUserState('com_jem.import.catalog.selected', array());
        $this->externalImportPreview = $activeImportPreview === 'events' ? $this->normaliseImportPreviewState('com_jem.import.external_import.preview') : null;
        $this->externalCsvPreview = $this->externalImportPreview;
        $this->externalIcsPreview = null;
        $this->externalCategoryOptions = $this->getExternalCategoryOptions();
        $this->externalTypeOptions = $this->getExternalTypeOptions(1);
        $this->externalVenueTypeOptions = $this->getExternalTypeOptions(3);
        $this->externalVenueOptions = $this->getExternalVenueOptions();
        $this->externalImportProfileOptions = $this->getExternalImportProfileOptions('events');
        $this->externalVenueImportPreview = $activeImportPreview === 'venues' ? $this->normaliseImportPreviewState('com_jem.import.external_venue_import.preview') : null;
        if (!empty($this->externalVenueImportPreview['payload_token'])) {
            try {
                $this->externalVenueImportPreview = JemImportPreviewHelper::loadVenuePreviewPage(
                    $this->externalVenueImportPreview,
                    (int) $app->getIdentity()->id,
                    $jinput->getInt('venue_preview_page', 1),
                    JemImportPreviewHelper::PAGE_SIZE
                );
            } catch (RuntimeException $e) {
                JemImportPreviewHelper::deleteVenuePreview($this->externalVenueImportPreview['payload_token'], (int) $app->getIdentity()->id);
                $app->setUserState('com_jem.import.external_venue_import.preview', null);
                $app->enqueueMessage(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'), 'error');
                $this->externalVenueImportPreview = null;
            }
        }
        $this->externalVenueImportProfileOptions = $this->getExternalImportProfileOptions('venues');
        $this->externalLanguageOptions = HTMLHelper::_('contentlanguage.existing', true, true);
        $this->externalPublishUpDefault = Factory::getDate()->toSql();
        $this->specialDaysImportPreview = $activeImportPreview === 'specialdays' ? $this->normaliseImportPreviewState('com_jem.import.specialdays_import.preview') : null;
        $this->specialDaysCsvPreview = $this->specialDaysImportPreview;
        $this->specialDaysIcsPreview = null;
        $this->specialDaysImportProfileOptions = $this->getExternalImportProfileOptions('specialdays');
        $this->specialDayTypeOptions = $this->getSpecialDayTypeOptions();
        $this->importCatalogStatus = JemImportCatalogHelper::getStatus();
        $this->importCatalogSource = (string) ($this->importCatalogStatus['source'] ?? JemImportCatalogHelper::getCatalogSource());
        $this->canManageImportCatalog = $app->getIdentity()->authorise('core.admin');
        $this->importCatalogEntries = JemImportCatalogHelper::getEntries();
        $this->importCatalogCountries = JemImportCatalogHelper::getCountries($this->importCatalogEntries);
        $this->importCatalogCounties = JemImportCatalogHelper::getCounties($this->importCatalogEntries);
        $this->importCatalogCities = JemImportCatalogHelper::getCities($this->importCatalogEntries);
        $this->importCatalogTypes = JemImportCatalogHelper::getTypes($this->importCatalogEntries);
        $this->importCatalogFormats = JemImportCatalogHelper::getFormats($this->importCatalogEntries);
        $importSecurity = JemHelper::globalattribs();
        $this->canConfigureImportSecurity = $app->getIdentity()->authorise('core.admin');
        $this->importSecuritySettings = array(
            'additional_blocked_tags' => (string) $importSecurity->get('import_additional_blocked_tags', ''),
            'allow_trusted_iframes' => (int) $importSecurity->get('import_allow_trusted_iframes', 0),
            'trusted_iframe_hosts' => (string) $importSecurity->get('import_trusted_iframe_hosts', ''),
        );

        // Do not show default prefix #__ but its replacement value
        $this->prefixToShow = $progress->prefix;
        if (empty($this->prefixToShow) || $this->prefixToShow == "#__") {
            $this->prefixToShow = $app->get('dbprefix');
        }

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Load a preview state and discard stale empty previews.
     *
     * @param   string  $stateKey  Joomla user-state key.
     *
     * @return  array|null
     */
    protected function normaliseImportPreviewState($stateKey)
    {
        $app = Factory::getApplication();
        $preview = $app->getUserState($stateKey, null);

        if (empty($preview)) {
            return null;
        }

        $hasRows = !empty($preview['rows']);
        $hasRecords = !empty($preview['records']);
        $hasValidRows = !empty($preview['valid_count']);

        if (!$hasRows && !$hasRecords && !$hasValidRows) {
            $app->setUserState($stateKey, null);
            return null;
        }

        return $preview;
    }

    /**
     * Get category options for external event imports.
     *
     * @return array
     */
    protected function getExternalCategoryOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('catname', 'text'), $db->quoteName('level')))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('published') . ' IN (0,1)')
            ->where($db->quoteName('catname') . ' <> ' . $db->quote('root'))
            ->order($db->quoteName('lft') . ' ASC');
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: array();

        foreach ($rows as $row) {
            $row->text = str_repeat('- ', max(0, (int) $row->level - 1)) . $row->text;
        }

        return array_merge(array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_IMPORT_EXTERNAL_SELECT_CATEGORY'))), $rows);
    }

    /**
     * Get saved import profile options for external event imports.
     *
     * @return array
     */
    protected function getExternalImportProfileOptions($context = 'events')
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $tables = $db->getTableList();
            if (!in_array(str_replace('#__', $db->getPrefix(), '#__jem_import_profiles'), $tables, true)) {
                return array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_IMPORT_PROFILE_NONE')));
            }

            $query = $db->getQuery(true)
                ->select(array($db->quoteName('id', 'value'), $db->quoteName('title', 'text'), $db->quoteName('source_format'), $db->quoteName('mapping'), $db->quoteName('options')))
                ->from($db->quoteName('#__jem_import_profiles'))
                ->where($db->quoteName('context') . ' = ' . $db->quote((string) $context))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('access') . ' IN (' . implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels())) . ')')
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: array();
        } catch (RuntimeException $e) {
            $rows = array();
        }

        foreach ($rows as $row) {
            $format = strtoupper((string) $row->source_format);
            $row->text = $format ? $row->text . ' (' . $format . ')' : $row->text;
            $config = json_decode((string) ($row->options ?? ''), true);
            $config = is_array($config) ? $config : array();

            foreach ((array) ($config['static_values'] ?? array()) as $staticValue) {
                $field = (string) ($staticValue['field'] ?? '');

                if (in_array($field, array('catid', 'mode', 'type_id', 'locid', 'published', 'publish_up', 'language'), true)
                    && !array_key_exists($field, $config)) {
                    $config[$field] = $staticValue['value'] ?? '';
                }
            }

            $row->profile_config = $config;
            $mapping = json_decode((string) ($row->mapping ?? ''), true);
            $row->profile_mapping = is_array($mapping) ? $mapping : array();
        }

        return array_merge(array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_IMPORT_PROFILE_NONE'))), $rows);
    }

    /**
     * Get event type options for external event imports.
     *
     * @return array
     */
    protected function getExternalTypeOptions($entity = 1)
    {
        $entity = in_array((int) $entity, array(1, 3), true) ? (int) $entity : 1;
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('name', 'text')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('entity') . ' = ' . $entity)
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query);

        return array_merge(array(HTMLHelper::_('select.option', 0, Text::_('JNONE'))), $db->loadObjectList() ?: array());
    }

    /**
     * Get venue options for external event imports.
     *
     * @return array
     */
    protected function getExternalVenueOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('venue', 'text')))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' IN (0,1)')
            ->order($db->quoteName('venue') . ' ASC');
        $db->setQuery($query);

        return array_merge(array(HTMLHelper::_('select.option', 0, Text::_('JNONE'))), $db->loadObjectList() ?: array());
    }

    /**
     * Get configured Types of Days for Special Days imports.
     *
     * @return array
     */
    protected function getSpecialDayTypeOptions()
    {
        $options = array(HTMLHelper::_('select.option', '', Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_SELECT_TYPE')));
        $types = JemHelper::calendarSpecialDayTypes();

        foreach ($types as $type) {
            $name = is_array($type) ? (string) ($type['name'] ?? '') : (string) $type;
            $id = is_array($type) ? (int) ($type['id'] ?? 0) : 0;

            if ($name !== '') {
                $options[] = HTMLHelper::_('select.option', $id > 0 ? $id : $name, $name);
            }
        }

        return $options;
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_IMPORT'), 'tableimport');

        ToolbarHelper::back();
        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('import', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel/import-data');
    }
}
?>
