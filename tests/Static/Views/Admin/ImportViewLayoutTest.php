<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportViewLayoutTest extends TestCase
{
    public function testImportViewKeepsSixImportBlocksInsideResponsiveGrid(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/import/tmpl/default.php');

        self::assertSame(
            1,
            substr_count($template, 'class="jem-import-grid"'),
            'The backend import view should have one responsive grid wrapper around the import blocks.'
        );

        preg_match_all('/<fieldset\s+class="adminform"/i', $template, $fieldsetMatches);

        self::assertCount(
            6,
            $fieldsetMatches[0],
            'The backend import view should expose the six expected import blocks in the grid.'
        );

        foreach (array(
            'COM_JEM_IMPORT_VENUES',
            'COM_JEM_IMPORT_CATEGORIES',
            'COM_JEM_IMPORT_EVENTS',
            'COM_JEM_IMPORT_CAT_EVENTS',
            'COM_JEM_IMPORT_TYPES',
            'COM_JEM_IMPORT_ATTACHMENTS',
        ) as $languageKey) {
            self::assertStringContainsString($languageKey, $template);
        }
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
