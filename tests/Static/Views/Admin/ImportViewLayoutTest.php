<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportViewLayoutTest extends TestCase
{
    public function testImportViewKeepsCurrentImportTabsAndBlocks(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/tmpl/default.php');

        foreach (array(
            'event-import',
            'venue-import',
            'jem-migration',
            'special-days',
            'advanced-tools',
            'download-lists',
        ) as $tabId) {
            self::assertStringContainsString("'" . $tabId . "'", $template);
        }

        foreach (array(
            'COM_JEM_IMPORT_EXTERNAL_EVENTS',
            'COM_JEM_IMPORT_EXTERNAL_VENUES',
            'COM_JEM_IMPORT_SPECIAL_DAYS',
            'COM_JEM_IMPORT_CATALOG',
            'COM_JEM_IMPORT_VENUES',
            'COM_JEM_IMPORT_CATEGORIES',
            'COM_JEM_IMPORT_EVENTS',
            'COM_JEM_IMPORT_CAT_EVENTS',
            'COM_JEM_IMPORT_TYPES',
            'COM_JEM_IMPORT_ATTACHMENTS',
        ) as $languageKey) {
            self::assertStringContainsString($languageKey, $template);
        }

        self::assertStringContainsString('$renderImportMappingBlock', $template);
        self::assertStringContainsString('jem-import-paged-table', $template);
        self::assertStringContainsString('data-page-size="50"', $template);
        self::assertStringContainsString('data-page-size="100"', $template);
        self::assertStringContainsString('data-server-paginated=', $template);
        self::assertStringContainsString('venue_preview_page=', $template);
        self::assertStringContainsString('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAGE_STATUS', $template);
        self::assertStringContainsString('JemImportCatalogHelper::getContext', $template);
        self::assertStringContainsString('jem-import-profile-control', $template);
        self::assertStringContainsString("JemImportSubmit('import.uploadCatalog', 'download-lists')", $template);
        self::assertStringContainsString("JemImportSubmit('import.removeCustomCatalog', 'download-lists')", $template);
        self::assertStringContainsString('COM_JEM_IMPORT_CATALOG_CUSTOM_ACTIVE', $template);
        self::assertStringContainsString('COM_JEM_IMPORT_CATALOG_OFFICIAL_ACTIVE', $template);
        self::assertStringContainsString('COM_JEM_IMPORT_CATALOG_TABLE_ITEMS', $template);
        self::assertStringContainsString("entry['item_count']", $template);
        self::assertStringContainsString('id="jem-import-catalog-type"', $template);
        self::assertStringContainsString('id="jem-import-catalog-format"', $template);
        self::assertStringContainsString('data-type="', $template);
        self::assertStringContainsString('data-format="', $template);
        self::assertStringContainsString("row.getAttribute('data-type')", $template);
        self::assertStringContainsString("row.getAttribute('data-format')", $template);
        self::assertStringContainsString('onclick="JemImportRefreshCatalog();"', $template);
        self::assertStringContainsString("sessionStorage.setItem('jemImportResetCatalogFilters', '1')", $template);
        self::assertStringContainsString("sessionStorage.removeItem('jemImportResetCatalogFilters')", $template);
        self::assertStringContainsString('filterCatalog();', $template);
        self::assertStringContainsString("url.hash = 'download-lists'", $template);
        self::assertStringContainsString('$this->externalVenueTypeOptions', $template);
        self::assertStringContainsString("'type.save', 3", $template);
        self::assertGreaterThan(
            strpos($template, 'COM_JEM_IMPORT_DOWNLOAD_LISTS_CATALOG_TITLE'),
            strpos($template, 'id="jem-import-catalog-file"'),
            'The custom catalog upload belongs in the Catalog structure card.'
        );
    }

    public function testImportGridCssKeepsTwoColumnsWithSingleColumnResponsiveFallback(): void
    {
        foreach (array('media/css/backend.css', 'media/css/backend-responsive.css') as $relativePath) {
            $css = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertMatchesRegularExpression(
                '/\.jem-import-grid\s*\{[^}]*display\s*:\s*grid\s*;/s',
                $css,
                $relativePath . ' should define the import wrapper as a CSS grid.'
            );

            self::assertMatchesRegularExpression(
                '/\.jem-import-grid\s*\{[^}]*grid-template-columns\s*:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)\s*;/s',
                $css,
                $relativePath . ' should keep the desktop import view in two columns.'
            );

            self::assertMatchesRegularExpression(
                '/@media\s*\(max-width:\s*900px\)\s*\{[^}]*\.jem-import-grid\s*\{[^}]*grid-template-columns\s*:\s*1fr\s*;/s',
                $css,
                $relativePath . ' should collapse the import grid to one column on smaller screens.'
            );
        }


        $backendCss = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/backend.css');
        self::assertMatchesRegularExpression(
            '/\.jem-import-catalog-filters\s*\{[^}]*grid-template-columns\s*:\s*repeat\(5,\s*minmax\(0,\s*1fr\)\)\s*;/s',
            $backendCss
        );
        self::assertMatchesRegularExpression(
            '/\.jem-import-mapping-table \.jem-import-mapping-select\s*\{[^}]*field-sizing\s*:\s*content\s*;[^}]*min-width\s*:\s*28rem\s*;[^}]*width\s*:\s*max-content\s*;/s',
            $backendCss
        );
    }

    public function testIcsCatalogPreviewExposesAutomaticFieldMapping(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');

        self::assertStringContainsString('function buildExternalIcsEventSourceRecords(', $controller);
        self::assertStringContainsString("'SUMMARY' => 'title'", $controller);
        self::assertStringContainsString("'DTSTART' => 'start_datetime'", $controller);
        self::assertStringContainsString("'DTEND' => 'end_datetime'", $controller);
        self::assertStringContainsString("'DESCRIPTION' => 'introtext'", $controller);
        self::assertStringContainsString("'source_fields' => \$sourceFields", $controller);
        self::assertStringContainsString("'source_records' => \$sourceRecords", $controller);
    }

    public function testEventAndVenueImportsUseEntitySpecificTypeLists(): void
    {
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/view.html.php');
        $typeView = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/type/view.html.php');

        self::assertStringContainsString('getExternalTypeOptions(1)', $view);
        self::assertStringContainsString('getExternalTypeOptions(3)', $view);
        self::assertStringContainsString("quoteName('entity') . ' = ' . \$entity", $view);
        self::assertStringContainsString("input->getInt('entity', 0)", $typeView);
        self::assertStringContainsString("form->setValue('entity', null, \$requestedEntity)", $typeView);
    }

    public function testLargeVenuePreviewsStayOutsideTheSessionAndImportInBatches(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/view.html.php');
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/importpreview.php');

        self::assertStringContainsString('EXTERNAL_IMPORT_BATCH_SIZE = 100', $controller);
        self::assertStringContainsString('array_slice($preview[\'records\'], $offset, self::EXTERNAL_IMPORT_BATCH_SIZE)', $controller);
        self::assertStringContainsString('JemImportPreviewHelper::storeVenuePreview', $controller);
        self::assertStringContainsString('JemImportPreviewHelper::loadVenuePreviewPage', $view);
        self::assertStringContainsString('public const PAGE_SIZE = 100', $helper);
        self::assertStringContainsString('$preview[\'records\'] = array();', $helper);
    }
}
