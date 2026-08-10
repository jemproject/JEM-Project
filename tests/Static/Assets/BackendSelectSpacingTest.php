<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendSelectSpacingTest extends TestCase
{
    public function testResponsiveBackendSelectsReserveSpaceForTheNativeArrow(): void
    {
        $css = $this->read('media/css/backend-responsive.css');

        self::assertStringContainsString('select.inputbox {', $css);
        self::assertStringContainsString('padding-inline-end: 2.5rem;', $css);
        self::assertStringNotContainsString('select.inputbox, textarea.inputbox {', $css);
    }

    public function testBackendListFiltersUseJoomlaSelectStyling(): void
    {
        foreach (array('events', 'venues', 'categories', 'groups') as $view) {
            $layout = $this->read('admin/views/' . $view . '/tmpl/default.php');

            self::assertStringContainsString('form-select', $layout, $view);
        }
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
