<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendSelectSpacingTest extends TestCase
{
    public function testBackendSelectsReserveSpaceForTheNativeArrow(): void
    {
        $css = $this->read('media/css/backend.css');

        self::assertStringContainsString('select.inputbox {', $css);
        self::assertStringContainsString('padding-inline-end: 2.5rem;', $css);
        self::assertStringContainsString(
            '.control-group select.inputbox:not([multiple]):not([size]) {',
            $css
        );
        self::assertStringContainsString('padding-inline-end: calc(2.5rem + 5px);', $css);
        self::assertStringNotContainsString('select.inputbox, textarea.inputbox {', $css);
    }

    public function testBackendListFiltersUseJoomlaSelectStyling(): void
    {
        foreach (array('events', 'venues', 'categories', 'types') as $view) {
            $layout = $this->read('admin/views/' . $view . '/tmpl/default.php');

            self::assertStringContainsString(
                "LayoutHelper::render('joomla.searchtools.default'",
                $layout,
                $view
            );
        }

        $groups = $this->read('admin/views/groups/tmpl/default.php');
        self::assertStringContainsString('form-select', $groups, 'groups');
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
