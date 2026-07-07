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
        self::assertStringContainsString('JemImportCatalogHelper::getContext', $template);
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
    }
}
