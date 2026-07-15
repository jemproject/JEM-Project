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

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * JEM Component Export Controller
 */
class JemControllerExport extends AdminController
{
    /**
     * Check whether the current user can export JEM data.
     *
     * @return void
     */
    private function assertCanExport()
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
    * Proxy for getModel.
    *
    */
    public function getModel($name = 'Export', $prefix = 'JemModel', $config=array()) {
        $model = parent::getModel($name, $prefix, array('ignore_request' => true));
        return $model;
    }

    public function export() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportEvents')), "text/csv");
        $this->getModel()->getCsv();
        jexit();
    }

    public function exportcats() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportCategories')), "text/csv");
        $this->getModel()->getCsvcats();
        jexit();
    }

    public function exportvenues() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportVenues')), "text/csv");
        $this->getModel()->getCsvvenues();
        jexit();
    }

    public function exportcatevents() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportCatEvents')), "text/csv");
        $this->getModel()->getCsvcatsevents();
        jexit();
    }

    public function exportattachments() {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportAttachments')), "text/csv");
        $this->getModel()->getCsvattachments();
        jexit();
    }

    public function exporttypes() {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportTypes')), "text/csv");
        $this->getModel()->getCsvtypes();
        jexit();
    }

    /**
     * Store portable event export filters and show a preview.
     *
     * @return void
     */
    public function previewCatalogEvents()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $app = Factory::getApplication();
        $state = array(
            'requested' => true,
            'filters' => $this->getCatalogEventFilters(),
            'include_categories' => $app->input->post->getInt('catalog_include_categories', 1),
            'fields' => (array) $app->input->post->get('catalog_fields', array(), 'array'),
        );
        $app->setUserState('com_jem.export.catalog', $state);
        $this->setRedirect('index.php?option=com_jem&view=export#catalog-event-export');
    }

    /**
     * Download the filtered portable event list as CSV, JSON or XML.
     *
     * @return void
     */
    public function exportCatalogEvents()
    {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $app = Factory::getApplication();
        $format = strtolower($app->input->post->getCmd('catalog_export_format', 'csv'));

        if (!in_array($format, array('csv', 'json', 'xml'), true)) {
            $format = 'csv';
        }

        $filters = $this->getCatalogEventFilters();
        $includeCategories = $app->input->post->getInt('catalog_include_categories', 1) === 1;
        $selectedFields = (array) $app->input->post->get('catalog_fields', array(), 'array');
        $items = $this->getModel()->getCatalogExportEvents($filters, $includeCategories, 0, $selectedFields);
        $app->setUserState('com_jem.export.catalog', array(
            'requested' => true,
            'filters' => $filters,
            'include_categories' => $includeCategories ? 1 : 0,
            'fields' => $selectedFields,
        ));

        if (!$items) {
            $this->setRedirect('index.php?option=com_jem&view=export#catalog-event-export', Text::_('COM_JEM_EXPORT_CATALOG_NO_EVENTS'), 'warning');
            return;
        }

        $filename = 'JEM-event-list-' . date('Ymd-His') . '.' . $format;
        $contentTypes = array(
            'csv' => 'text/csv; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'xml' => 'application/xml; charset=UTF-8',
        );
        $this->sendHeaders($filename, $contentTypes[$format]);

        if ($format === 'json') {
            echo json_encode(array(
                'jem_export_version' => (string) ($items[0]['jem_export_version'] ?? ''),
                'exported_at' => Factory::getDate()->toSql(),
                'events' => $items,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            jexit();
        }

        if ($format === 'xml') {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->formatOutput = true;
            $root = $document->createElement('jem-events');
            $root->setAttribute('jem-version', (string) ($items[0]['jem_export_version'] ?? ''));
            $root->setAttribute('exported-at', Factory::getDate()->toSql());
            $document->appendChild($root);

            foreach ($items as $item) {
                $event = $document->createElement('event');

                foreach ($item as $name => $value) {
                    $field = $document->createElement((string) $name);
                    $field->appendChild($document->createTextNode((string) ($value ?? '')));
                    $event->appendChild($field);
                }

                $root->appendChild($event);
            }

            echo $document->saveXML();
            jexit();
        }

        $config = JemConfig::getInstance()->toRegistry();
        $separator = (string) $config->get('csv_separator', ';');
        $delimiter = (string) $config->get('csv_delimiter', '"');
        $csv = fopen('php://output', 'w');

        if ((int) $config->get('csv_bom', 1) === 1) {
            fwrite($csv, "\xEF\xBB\xBF");
        }

        fputcsv($csv, array_keys($items[0]), $separator, $delimiter, '\\');

        foreach ($items as $item) {
            fputcsv($csv, JemCsv::protectFormulaRow(array_values($item)), $separator, $delimiter, '\\');
        }

        fclose($csv);
        jexit();
    }

    private function getCatalogEventFilters()
    {
        $input = Factory::getApplication()->input;
        $categories = array_values(array_unique(array_filter(array_map('intval', (array) $input->post->get('cid', array(), 'array')))));
        $venues = array_values(array_unique(array_filter(array_map('intval', (array) $input->post->get('catalog_venue_ids', array(), 'array')))));
        $types = array_values(array_unique(array_filter(array_map('intval', (array) $input->post->get('catalog_type_ids', array(), 'array')))));
        $published = $input->post->getString('catalog_published', '');

        return array(
            'dates' => $this->normaliseDateFilter($input->post->getString('dates', '')),
            'enddates' => $this->normaliseDateFilter($input->post->getString('enddates', '')),
            'cid' => $categories,
            'venue_ids' => $venues,
            'type_ids' => $types,
            'search' => substr(trim($input->post->getString('catalog_search', '')), 0, 255),
            'published' => $published !== '' && in_array((int) $published, array(-2, 0, 1, 2), true)
                ? (string) (int) $published
                : '',
        );
    }

    private function normaliseDateFilter($value)
    {
        $value = trim((string) $value);
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function getRequestedFilename($default = 'export.csv') {
        $filename = Factory::getApplication()->input->post->getString('export_filename', $default);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($filename));

        if ($filename === '' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
            return $default;
        }

        return $filename;
    }

    private function buildExportFilename($name) {
        return $name . '-' . date('Ymd-His') . '.csv';
    }

    private function sendHeaders($filename = 'export.csv', $contentType = 'text/plain') {
        // TODO: Use UTF-8
        // We have to fix the model->getCsv* methods too!
        // header("Content-type: text/csv; charset=UTF-8");
        header('Content-Type: ' . $contentType);
        header("Content-Disposition: attachment; filename=\"" . basename($filename) . "\"");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}
