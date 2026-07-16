<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExportPortableEventsTest extends TestCase
{
    public function testPortableExportIsRenderedInExportAndRemovedFromImport(): void
    {
        $export = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/export/tmpl/default.php');
        $import = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/tmpl/default.php');

        self::assertStringContainsString('id="catalog-event-export"', $export);
        self::assertStringContainsString("jemSubmitExportTask('export.previewCatalogEvents')", $export);
        self::assertStringContainsString("jemSubmitExportTask('export.exportCatalogEvents')", $export);
        self::assertStringContainsString('catalog_export_format', $export);
        $exportView = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/export/view.html.php');
        self::assertStringContainsString("'catalog_fields[]'", $exportView);
        self::assertStringContainsString('mb_substr($value, 0, 30', $export);
        self::assertStringNotContainsString('COM_JEM_IMPORT_DOWNLOAD_LISTS_EXPORT_TITLE', $import);
    }

    public function testControllerSupportsCsvJsonAndXmlDownloads(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/export.php');

        self::assertStringContainsString('function previewCatalogEvents()', $controller);
        self::assertStringContainsString('function exportCatalogEvents()', $controller);
        self::assertStringContainsString("array('csv', 'json', 'xml')", $controller);
        self::assertStringContainsString("new DOMDocument('1.0', 'UTF-8')", $controller);
        self::assertStringContainsString('JSON_PRETTY_PRINT', $controller);
        self::assertStringContainsString("fputcsv(", $controller);
    }

    public function testPortableExportUsesBoundedPreviewAndFilteredModelQuery(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/export.php');

        self::assertStringContainsString('function getCatalogExportEvents(', $model);
        self::assertStringContainsString('function getCatalogExportPreview()', $model);
        self::assertStringContainsString('getCatalogExportEvents($filters, $includeCategories, 100, $fields)', $model);
        self::assertStringContainsString('getCatalogExportCount($filters)', $model);
        self::assertStringContainsString("a.locid IN", $model);
        self::assertStringContainsString("a.type_id IN", $model);
        self::assertStringContainsString('getActiveCatalogCustomFields()', $model);
        self::assertStringContainsString('normaliseCatalogExportFields(', $model);
        self::assertStringContainsString("a.title LIKE", $model);
        self::assertStringContainsString("a.published = ", $model);
        self::assertStringContainsString("rf.catid IN", $model);
    }
}
