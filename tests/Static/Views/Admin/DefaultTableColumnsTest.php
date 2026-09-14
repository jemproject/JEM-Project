<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DefaultTableColumnsTest extends TestCase
{
    public function testAuthorIsInitiallyHiddenButRemainsAvailableInBackendTables(): void
    {
        foreach (array('events', 'venues', 'categories', 'types', 'specialdays') as $view) {
            $template = (string) file_get_contents(
                JEM_TEST_ROOT . '/admin/views/' . $view . '/tmpl/default.php'
            );

            self::assertMatchesRegularExpression(
                '/<th[^>]*data-jem-default-hidden[^>]*>[\s\S]*?COM_JEM_AUTHOR[\s\S]*?<\/th>/',
                $template,
                $view
            );
        }
    }

    public function testVenueWebsiteIsInitiallyHiddenInsteadOfRemoved(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/venues/tmpl/default.php');

        self::assertMatchesRegularExpression(
            '/<th[^>]*data-jem-default-hidden[^>]*>[\s\S]*?COM_JEM_WEBSITE[\s\S]*?<\/th>/',
            $template
        );
        self::assertStringContainsString("if (\$item->url)", $template);
    }

    public function testColumnDefaultsRespectStoredJoomlaPreferences(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/media/js/admin-table-columns.js');

        self::assertStringContainsString("window.localStorage.getItem(storageKey) !== null", $script);
        self::assertStringContainsString("header.hasAttribute('data-jem-default-hidden')", $script);
        self::assertStringContainsString('checkbox.click();', $script);

        foreach (array(
            'admin/views/events/tmpl/default.php',
            'admin/views/venues/tmpl/default.php',
            'admin/views/categories/tmpl/default.php',
            'admin/views/types/view.html.php',
            'admin/views/specialdays/view.html.php',
        ) as $relativePath) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
            self::assertStringContainsString('jem.admin-table-columns', $contents, $relativePath);
        }
    }
}
