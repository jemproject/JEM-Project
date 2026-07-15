<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

use Joomla\Utilities\ArrayHelper;

require_once JPATH_SITE . '/components/com_jem/classes/csv.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/csvmetadata.php';
/**
 * JEM Component Export Model
 */
class JemModelExport extends ListModel
{
    protected $jemVersion = null;

    /**
     * Return portable event records for catalog-ready CSV, JSON and XML exports.
     *
     * @param   array    $filters            Export filters.
     * @param   boolean  $includeCategories  Include category names and IDs.
     * @param   integer  $limit              Maximum rows, 0 for all.
     *
     * @return array
     */
    public function getCatalogExportEvents(array $filters, $includeCategories = true, $limit = 0, array $selectedFields = array())
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'a.id', 'a.title', 'a.alias', 'a.dates', 'a.enddates', 'a.times', 'a.endtimes',
                'a.introtext', 'a.fulltext', 'a.datimage', 'a.online_meeting_url', 'a.online_meeting_label',
                'a.published', 'a.language',
                'a.created', 'a.modified', 'a.locid',
                $db->quoteName('v.venue', 'venue'), $db->quoteName('v.street', 'street'),
                $db->quoteName('v.postalCode', 'postalCode'), $db->quoteName('v.city', 'city'),
                $db->quoteName('v.state', 'state'), $db->quoteName('v.country', 'country'),
                $db->quoteName('v.latitude', 'latitude'), $db->quoteName('v.longitude', 'longitude'),
            ))
            ->select("(SELECT GROUP_CONCAT(DISTINCT c2.catname ORDER BY c2.catname SEPARATOR ', ') FROM #__jem_cats_event_relations AS r2 INNER JOIN #__jem_categories AS c2 ON c2.id = r2.catid WHERE r2.itemid = a.id) AS categories")
            ->select("(SELECT GROUP_CONCAT(DISTINCT r3.catid ORDER BY r3.catid SEPARATOR ',') FROM #__jem_cats_event_relations AS r3 WHERE r3.itemid = a.id) AS category_ids")
            ->select("(SELECT l.url FROM #__jem_links AS l WHERE l.event_id = a.id AND l.state = 1 AND l.url <> '' ORDER BY l.ordering ASC, l.id ASC LIMIT 1) AS event_url")
            ->from($db->quoteName('#__jem_events', 'a'))
            ->join('LEFT', $db->quoteName('#__jem_venues', 'v') . ' ON v.id = a.locid');

        foreach ($this->getActiveCatalogCustomFields() as $outputField => $sourceField) {
            $query->select($sourceField . ' AS ' . $db->quoteName($outputField));
        }

        $this->applyCatalogEventFilters($query, $filters, $db);

        $query->order('CASE WHEN a.dates IS NULL THEN 1 ELSE 0 END ASC, a.dates ASC, a.times ASC, a.title ASC');
        $db->setQuery($query, 0, max(0, (int) $limit));
        $rows = $db->loadAssocList() ?: array();
        $version = $this->getInstalledJemVersion();

        $selectedFields = $this->normaliseCatalogExportFields($selectedFields, $includeCategories);

        foreach ($rows as &$row) {
            $filtered = array();

            foreach ($selectedFields as $field) {
                $filtered[$field] = $row[$field] ?? '';
            }

            $filtered['jem_export_version'] = $version;
            $row = $filtered;
        }
        unset($row);

        return $rows;
    }

    public function getCatalogExportCount(array $filters)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT a.id)')
            ->from($db->quoteName('#__jem_events', 'a'));
        $this->applyCatalogEventFilters($query, $filters, $db);
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    protected function applyCatalogEventFilters($query, array $filters, $db)
    {
        $startDate = trim((string) ($filters['dates'] ?? ''));
        $endDate = trim((string) ($filters['enddates'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $published = (string) ($filters['published'] ?? '');
        $categories = array_values(array_filter(array_map('intval', (array) ($filters['cid'] ?? array()))));
        $venues = array_values(array_filter(array_map('intval', (array) ($filters['venue_ids'] ?? array()))));
        $types = array_values(array_filter(array_map('intval', (array) ($filters['type_ids'] ?? array()))));

        if ($startDate !== '') {
            $query->where('((a.dates IS NULL) OR DATEDIFF(IF(a.enddates IS NOT NULL, a.enddates, a.dates), ' . $db->quote($startDate) . ') >= 0)');
        }

        if ($endDate !== '') {
            $query->where('((a.dates IS NULL AND DATEDIFF(CURDATE(), ' . $db->quote($endDate) . ') <= 0) OR DATEDIFF(a.dates, ' . $db->quote($endDate) . ') <= 0)');
        }

        if ($search !== '') {
            $query->where('a.title LIKE ' . $db->quote('%' . $db->escape($search, true) . '%', false));
        }

        if ($published !== '' && in_array((int) $published, array(-2, 0, 1, 2), true)) {
            $query->where('a.published = ' . (int) $published);
        }

        if ($categories) {
            $query->where('EXISTS (SELECT 1 FROM #__jem_cats_event_relations AS rf WHERE rf.itemid = a.id AND rf.catid IN (' . implode(',', $categories) . '))');
        }

        if ($venues) {
            $query->where('a.locid IN (' . implode(',', $venues) . ')');
        }

        if ($types) {
            $query->where('a.type_id IN (' . implode(',', $types) . ')');
        }
    }

    public function getCatalogExportVenues()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('venue', 'text')))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' IN (0,1)')
            ->order($db->quoteName('venue') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: array();
    }

    public function getCatalogExportTypes()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('name', 'text')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = 1')
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: array();
    }

    public function getCatalogExportPreview()
    {
        $state = (array) Factory::getApplication()->getUserState('com_jem.export.catalog', array());

        if (empty($state['requested'])) {
            return array('requested' => false, 'items' => array(), 'total' => 0);
        }

        $filters = (array) ($state['filters'] ?? array());
        $includeCategories = !empty($state['include_categories']);
        $fields = $this->normaliseCatalogExportFields((array) ($state['fields'] ?? array()), $includeCategories);
        $items = $this->getCatalogExportEvents($filters, $includeCategories, 100, $fields);
        $definitions = $this->getCatalogExportFieldDefinitions();
        $labels = array();

        foreach ($fields as $field) {
            $labels[$field] = $definitions[$field] ?? $field;
        }

        return array(
            'requested' => true,
            'items' => $items,
            'total' => $this->getCatalogExportCount($filters),
            'fields' => $fields,
            'labels' => $labels,
        );
    }

    public function getCatalogExportFieldDefinitions()
    {
        $fields = array(
            'id' => 'ID',
            'title' => Text::_('COM_JEM_TITLE'),
            'alias' => Text::_('JFIELD_ALIAS_LABEL'),
            'dates' => Text::_('COM_JEM_EXPORT_CATALOG_START_DATE'),
            'enddates' => Text::_('COM_JEM_EXPORT_CATALOG_END_DATE'),
            'times' => Text::_('COM_JEM_EXPORT_CATALOG_FIELD_TIME'),
            'endtimes' => Text::_('COM_JEM_ENDTIME'),
            'introtext' => Text::_('COM_JEM_INTROTEXT'),
            'fulltext' => Text::_('COM_JEM_EXPORT_CATALOG_FIELD_FULLTEXT'),
            'datimage' => Text::_('COM_JEM_IMAGE'),
            'event_url' => Text::_('COM_JEM_EVENT_LINK_URL'),
            'online_meeting_url' => Text::_('COM_JEM_EVENT_FIELD_ONLINE_MEETING_URL_LABEL'),
            'online_meeting_label' => Text::_('COM_JEM_EVENT_FIELD_ONLINE_MEETING_LABEL_LABEL'),
            'published' => Text::_('JSTATUS'),
            'language' => Text::_('JFIELD_LANGUAGE_LABEL'),
            'created' => Text::_('JGLOBAL_CREATED_DATE'),
            'modified' => Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'),
            'locid' => Text::_('COM_JEM_VENUE') . ' ID',
            'venue' => Text::_('COM_JEM_VENUE'),
            'street' => Text::_('COM_JEM_STREET'),
            'postalCode' => Text::_('COM_JEM_ZIP'),
            'city' => Text::_('COM_JEM_CITY'),
            'state' => Text::_('COM_JEM_STATE'),
            'country' => Text::_('COM_JEM_COUNTRY'),
            'latitude' => Text::_('COM_JEM_LATITUDE'),
            'longitude' => Text::_('COM_JEM_LONGITUDE'),
            'categories' => Text::_('COM_JEM_CATEGORIES'),
            'category_ids' => Text::_('COM_JEM_CATEGORIES') . ' IDs',
        );

        foreach (array('event', 'venue') as $context) {
            foreach (JemCustomFields::getOrderedFields($context, 'backend') as $field) {
                $prefix = $context === 'event' ? 'COM_JEM_EVENT_CUSTOM_FIELD' : 'COM_JEM_VENUE_CUSTOM_FIELD';
                $fallback = Text::_($prefix . (int) substr($field, 6));
                $fields[$context . '_' . $field] = ucfirst($context) . ': ' . JemCustomFields::getLabel($context, $field, $fallback);
            }
        }

        return $fields;
    }

    public function getCatalogExportFieldOptions()
    {
        $options = array();

        foreach ($this->getCatalogExportFieldDefinitions() as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, $label);
        }

        return $options;
    }

    public function getDefaultCatalogExportFields()
    {
        return array('id', 'title', 'dates', 'enddates', 'times', 'endtimes', 'venue', 'city', 'country', 'categories', 'event_url');
    }

    protected function normaliseCatalogExportFields(array $fields, $includeCategories = true)
    {
        $allowed = array_keys($this->getCatalogExportFieldDefinitions());
        $fields = array_values(array_unique(array_intersect($fields ?: $this->getDefaultCatalogExportFields(), $allowed)));

        if (!$includeCategories) {
            $fields = array_values(array_diff($fields, array('categories', 'category_ids')));
        }

        return $fields ?: array('id', 'title', 'dates');
    }

    protected function getActiveCatalogCustomFields()
    {
        $fields = array();

        foreach (array('event' => 'a', 'venue' => 'v') as $context => $alias) {
            foreach (JemCustomFields::getOrderedFields($context, 'backend') as $field) {
                $fields[$context . '_' . $field] = $alias . '.' . $field;
            }
        }

        return $fields;
    }

    /**
     * Writes a CSV row using the current PHP-safe fputcsv signature.
     *
     * @param  resource $handle
     * @param  array    $fields
     * @param  string   $separator
     * @param  string   $delimiter
     *
     * @return int|false
     */
    private function putCsv($handle, array $fields, $separator, $delimiter)
    {
        $fields = JemCsv::protectFormulaRow($fields);

        return fputcsv($handle, $fields, $separator, $delimiter, '\\');
    }

    /**
     * Read the installed component version for JEM-to-JEM CSV diagnostics.
     */
    private function getInstalledJemVersion()
    {
        if ($this->jemVersion !== null) {
            return $this->jemVersion;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $manifest = json_decode((string) $db->loadResult(), true);
        $this->jemVersion = JemCsvMetadataHelper::normaliseVersion($manifest['version'] ?? '');

        return $this->jemVersion;
    }

    private function addVersionHeader(array $header)
    {
        $header[] = JemCsvMetadataHelper::VERSION_FIELD;

        return $header;
    }

    private function addVersionValue($row)
    {
        return JemCsvMetadataHelper::addVersion((array) $row, $this->getInstalledJemVersion());
    }

    /**
     * Constructor.
     *
     * @param array An optional associative array of configuration settings.
     * @see   AdminController
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id',
                'a.id'
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @Note Calling getState in this method will result in recursion.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        // Load the filter state.
        $filter_form_type = $this->getUserStateFromRequest($this->context . '.filter.form_type', 'filter_form_type');
        $this->setState('filter.form_type', $filter_form_type);

        $filter_start_date = $this->getUserStateFromRequest($this->context . '.filter.start_date', 'filter_start_date');
        $this->setState('filter.start_date', $filter_start_date);

        $filter_end_date = $this->getUserStateFromRequest($this->context . '.filter.end_date', 'filter_end_date');
        $this->setState('filter.end_date', $filter_end_date);

        // Load the parameters.
        $params = ComponentHelper::getParams('com_jem');
        $this->setState('params', $params);

        // List state information.
        parent::populateState('a.first_name', 'asc');
    }

    /**
     * Build an SQL query to load the Events data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Retrieve variables
        $jinput    = Factory::getApplication()->input;
        $startdate = $jinput->get('dates', '', 'string');
        $enddate   = $jinput->get('enddates', '', 'string');
        $cats      = $jinput->get('cid', array(), 'array');

        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select('a.*');
        $query->from('#__jem_events AS a');
        $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
        $query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

        // check if startdate and/or enddate are set.
        if (!empty($startdate)) {
            // note: open date is always after $startdate
            $query->where('((a.dates IS NULL) OR (DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $db->quote($startdate) . ') >= 0))');
        }
        if (!empty($enddate)) {
            // note: open date is before $enddate as long as $enddate is not before today
            $query->where('(((a.dates IS NULL) AND (DATEDIFF(CURDATE(), ' . $db->quote($enddate) . ') <= 0)) OR (DATEDIFF(a.dates, ' . $db->quote($enddate) . ') <= 0))');
        }

        // check if specific category's have been selected
        if (! empty($cats)) {
            ArrayHelper::toInteger($cats);
            $query->where('  c.id IN (' . implode(',', $cats) . ')');
        }

        // Group the query
        $query->group('a.id');

        return $query;
    }

    /**
     * Returns a CSV file with Events data
     *
     * @return boolean
     */
    public function getCsv()
    {
        $this->populateState();

        $jinput = Factory::getApplication()->input;
        $includecategories = $jinput->get('categorycolumn', 0, 'int');

        $db  = Factory::getContainer()->get('DatabaseDriver');
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $csv = fopen('php://output', 'w');
        if ($csv_bom ==1 ) {
            //add BOM to fix UTF-8 in Excel
            fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        }

        if ($includecategories == 1) {
            $events = array_keys($db->getTableColumns('#__jem_events'));
            $categories = array();
            $categories[] = "categories";
            $header = $this->addVersionHeader(array_merge($events, $categories));

            $this->putCsv($csv, $header, $separator, $delimiter);

            $query = $this->getListQuery();
            $items = $this->_getList($query);

            foreach ($items as $item) {
                $item->categories = $this->getCatEvent($item->id);
            }
        } else {
            $header = $this->addVersionHeader(array_keys($db->getTableColumns('#__jem_events')));
            $this->putCsv($csv, $header, $separator, $delimiter);
            $query = $this->getListQuery();
            $items = $this->_getList($query);
        }

        foreach ($items as $lines) {
            $this->putCsv($csv, $this->addVersionValue($lines), $separator, $delimiter);
        }

        return fclose($csv);
    }

    /**
     * Build an SQL query to load the Categories data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQuerycats()
    {
        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select('a.*');
        $query->from('#__jem_categories AS a');
        $query->where('a.id <> 1');

        return $query;
    }

    /**
     * Returns a CSV file with Categories data
     *
     * @return boolean
     */
    public function getCsvcats()
    {
        $this->populateState();
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $csv = fopen('php://output', 'w');
        if ($csv_bom ==1 ) {
            //add BOM to fix UTF-8 in Excel
            fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $header = $this->addVersionHeader(array_keys($db->getTableColumns('#__jem_categories')));
        $this->putCsv($csv, $header, $separator, $delimiter);

        $db->setQuery($this->getListQuerycats());
        $items = $db->loadObjectList();

        foreach ($items as $lines) {
            $this->putCsv($csv, $this->addVersionValue($lines), $separator, $delimiter);
        }

        return fclose($csv);
    }

    /**
     * Build an SQL query to load the Venues data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQueryvenues()
    {
        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select('a.*');
        $query->from('#__jem_venues AS a');

        return $query;
    }

    /**
     * Returns a CSV file with Venues data
     * @return boolean
     */
    public function getCsvvenues()
    {
        $this->populateState();
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $csv = fopen('php://output', 'w');
        if ($csv_bom ==1 ) {
            //add BOM to fix UTF-8 in Excel
            fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $header = $this->addVersionHeader(array_keys($db->getTableColumns('#__jem_venues')));
        $this->putCsv($csv, $header, $separator, $delimiter);

        $db->setQuery($this->getListQueryvenues());
        $items = $db->loadObjectList();

        foreach ($items as $lines) {
            $this->putCsv($csv, $this->addVersionValue($lines), $separator, $delimiter);
        }

        return fclose($csv);
    }

    /**
     * Build an SQL query to load the Cats/Events data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQuerycatsevents()
    {
        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select('a.*');
        $query->from('#__jem_cats_event_relations AS a');

        return $query;
    }

    /**
     * Returns a CSV file with Cats/Events data
     * @return boolean
     */
    public function getCsvcatsevents()
    {
        $this->populateState();
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $csv = fopen('php://output', 'w');
        if ($csv_bom ==1 ) {
            //add BOM to fix UTF-8 in Excel
            fputs($csv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $header = $this->addVersionHeader(array_keys($db->getTableColumns('#__jem_cats_event_relations')));
        $this->putCsv($csv, $header, $separator, $delimiter);

        $db->setQuery($this->getListQuerycatsevents());
        $items = $db->loadObjectList();

        foreach ($items as $lines) {
            $this->putCsv($csv, $this->addVersionValue($lines), $separator, $delimiter);
        }

        return fclose($csv);
    }

    /**
     * Build an SQL query to load the Attachments data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQueryattachments()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('a.*');
        $query->from('#__jem_attachments AS a');
        return $query;
    }

    /**
     * Returns a CSV file with Attachments data
     * @return boolean
     */
    public function getCsvattachments()
    {
        $this->populateState();
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $csv = fopen('php://output', 'w');
        if ($csv_bom == 1) {
            fputs($csv, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $header = $this->addVersionHeader(array_keys($db->getTableColumns('#__jem_attachments')));
        $this->putCsv($csv, $header, $separator, $delimiter);

        $db->setQuery($this->getListQueryattachments());
        $items = $db->loadObjectList();

        foreach ($items as $lines) {
            $this->putCsv($csv, $this->addVersionValue($lines), $separator, $delimiter);
        }

        return fclose($csv);
    }

    /**
     * Build an SQL query to load the Types data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQuerytypes()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('a.*');
        $query->from('#__jem_types AS a');
        return $query;
    }

    /**
     * Returns a CSV file with Types data
     * @return boolean
     */
    public function getCsvtypes()
    {
        $this->populateState();
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $csv = fopen('php://output', 'w');
        if ($csv_bom == 1) {
            fputs($csv, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $header = $this->addVersionHeader(array_keys($db->getTableColumns('#__jem_types')));
        $this->putCsv($csv, $header, $separator, $delimiter);

        $db->setQuery($this->getListQuerytypes());
        $items = $db->loadObjectList();

        foreach ($items as $lines) {
            $this->putCsv($csv, $this->addVersionValue($lines), $separator, $delimiter);
        }

        return fclose($csv);
    }

    /**
     * logic to get the categories
     */
    public function getCategories()
    {
        // @todo alter function

        $db = Factory::getContainer()->get('DatabaseDriver');
        $where = ' WHERE c.published = 1';
        $query = 'SELECT c.* FROM #__jem_categories AS c' . $where . ' ORDER BY parent_id, c.lft';

        try
        {
            $db->setQuery($query);
            $mitems = $db->loadObjectList();
        }
        catch (RuntimeException $e)
        {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
        }

        if (!$mitems) {
            $children = array();
            $mitems   = array();
            $parentid = 0;
        } else {
            $children = array();
            // First pass - collect children
            foreach ($mitems as $v) {
                $pt = $v->parent_id;
                $list = $children[$pt] ?? array();
                array_push($list, $v);
                $children[$pt] = $list;
            }

            // list childs of "root" which has no parent and normally id 1
            $parentid = intval($children[0][0]->id ?? 1);
        }

        //get list of the items
        $list = JemCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

        return $list;
    }

    /**
     * Get Category IDs for a specific event.
     *
     * @param  int $id event id
     * @return string|boolean Comma separated list of ids on success or false otherwise.
     */
    public function getCatEvent($id)
    {
        // Create a new query object.
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select('catid');
        $query->from('#__jem_cats_event_relations');
        $query->where('itemid = ' . $db->quote($id));

        $db->setQuery($query);
        $catidlist = $db->loadObjectList();

        if (is_array($catidlist) && count($catidlist)) {
            $catidarray = array();
            foreach ($catidlist as $obj) {
                $catidarray[] = $obj->catid;
            }

            $catids = implode(',', $catidarray);
        } else {
            $catids = false;
        }

        return $catids;
    }
}
