<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminCodeContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function adminCodeProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/controllers');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/models');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/helpers');
    }

    #[DataProvider('adminCodeProvider')]
    public function testAdminCodeFilesBlockDirectAccess(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/defined\s*\(\s*[\'"](?:_JEXEC|JPATH_BASE|JPATH_PLATFORM)[\'"]\s*\)\s*or\s+die\s*;?/i',
            self::read($path),
            self::relativePath($path) . ' should block direct access with a Joomla guard.'
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminControllerProvider(): iterable
    {
        yield from self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/admin/controllers', 'JemController');
    }

    #[DataProvider('adminControllerProvider')]
    public function testAdminControllersKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminModelProvider(): iterable
    {
        yield from self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/admin/models', 'JemModel');
    }

    #[DataProvider('adminModelProvider')]
    public function testAdminModelsKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminFormFieldProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/models/fields');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/models/links');
    }

    #[DataProvider('adminFormFieldProvider')]
    public function testAdminFormFieldsDefineJFormFieldClass(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/class\s+JFormField[A-Za-z0-9_]+\s+extends\s+[A-Za-z0-9_\\\\]+/',
            self::read($path),
            self::relativePath($path) . ' should define a JFormField class.'
        );
    }

    public function testFrontendMenuGeneratorUsesAliasThatDoesNotCollideWithExistingMainMenuItems(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString("'JEM', 'jem-frontend'", $code);
        self::assertStringContainsString("array('jem')", $code);
        self::assertStringContainsString('array_merge(array($alias), $legacyAliases)', $code);
        self::assertStringContainsString("->where(\$db->quoteName('alias') . ' IN ('", $code);
    }

    public function testFrontendMenuGeneratorIncludesMapViews(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString("array('Events Map', 'events-map', 'index.php?option=com_jem&view=eventsmap', \$groups['events'])", $code);
        self::assertStringContainsString("array('Venues Map', 'venues-map', 'index.php?option=com_jem&view=venuesmap', \$groups['venues'])", $code);
    }

    public function testFrontendMenuGeneratorDoesNotCreateSampleCategoryLinksToRootCategory(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString('$category = $this->getRandomCategoryRecord();', $code);
        self::assertStringContainsString('protected function getRandomCategoryRecord()', $code);
        self::assertStringContainsString("->from(\$db->quoteName('#__jem_categories'))", $code);
        self::assertStringContainsString("->where(\$db->quoteName('id') . ' > 1')", $code);
        self::assertStringContainsString("->where(\$db->quoteName('alias') . ' <> ' . \$db->quote('root'))", $code);
        self::assertStringContainsString("->where(\$db->quoteName('catname') . ' <> ' . \$db->quote('root'))", $code);
        self::assertStringContainsString("\$this->keepExistingGeneratedMenuItems(\$menutype, array('sample-category', 'sample-category-calendar', 'category-calendar'));", $code);
        self::assertStringContainsString("'index.php?option=com_jem&view=category&layout=calendar&id=' . \$this->slug(\$category)", $code);
    }

    public function testFrontendMenuGeneratorIncludesAllStandaloneFrontendViews(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString("array('Day Timeline', 'day-timeline', 'index.php?option=com_jem&view=day&layout=timeline&id=0', \$groups['calendars'])", $code);
        self::assertStringContainsString("array('Venues', 'venues-overview', 'index.php?option=com_jem&view=venues', \$groups['venues'])", $code);
        self::assertStringContainsString("array('My Timeline', 'my-timeline', 'index.php?option=com_jem&view=mytimeline', \$groups['user'])", $code);
        self::assertStringContainsString("array('My Attendances Timeline', 'my-attendances-timeline', 'index.php?option=com_jem&view=myattendances&layout=timeline', \$groups['user'])", $code);
    }

    public function testFrontendMenuGeneratorAddsVenueCalendarLastWithSelectorEnabled(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString("'Venue Calendar',", $code);
        self::assertStringContainsString("'venue-calendar',", $code);
        self::assertStringContainsString("'index.php?option=com_jem&view=venue&layout=calendar&id=' . \$this->slug(\$venue)", $code);
        self::assertStringContainsString("array('show_venue_selector' => '1')", $code);
        self::assertStringContainsString('// Keep Venue Calendar as the final entry in the Calendars group.', $code);
        self::assertStringContainsString('$items[] = $venueCalendarItem;', $code);
        self::assertStringContainsString("if (\$item[1] === 'venue-calendar')", $code);
        self::assertStringContainsString("\$this->moveMenuItemToLastChild(\$menuItemId, \$groups['calendars']);", $code);
        self::assertStringContainsString('protected function moveMenuItemToLastChild($id, $parentId)', $code);

        self::assertGreaterThan(
            strpos($code, "array('Category Calendar', 'category-calendar'"),
            strpos($code, '$items[] = $venueCalendarItem;')
        );
    }

    public function testFrontendMenuGeneratorRepairsExistingGeneratedAliasesAcrossTheMenuType(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString('$existing = (int) $db->loadResult();', $code);
        self::assertMatchesRegularExpression(
            '/if\s*\(\$existing\)\s*\{\s*return\s+\$existing;\s*\}/',
            $code
        );
        self::assertMatchesRegularExpression(
            '/->where\(\$db->quoteName\(\'menutype\'\).*?->where\(\$db->quoteName\(\'alias\'\).*?->where\(\$db->quoteName\(\'client_id\'\).*?\$db->setQuery\(\$query,\s*0,\s*1\);/s',
            $code
        );
    }

    public function testDeletePathsForCategoriesVenuesAndTypesAvoidLegacyInputFilterNamespace(): void
    {
        foreach (array(
            'admin/controllers/categories.php',
            'admin/models/category.php',
            'admin/tables/category.php',
            'admin/controllers/venues.php',
            'admin/models/venue.php',
            'admin/tables/venue.php',
            'admin/controllers/types.php',
            'admin/models/type.php',
            'admin/tables/jem_types.php',
        ) as $relativePath) {
            $code = self::read(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringNotContainsString(
                'use Joomla\Filter\InputFilter;',
                $code,
                $relativePath . ' must not use the Joomla 6-incompatible InputFilter namespace.'
            );
            self::assertStringNotContainsString(
                'Joomla\Filter\InputFilter::getInstance',
                $code,
                $relativePath . ' must not call the Joomla 6-incompatible InputFilter factory.'
            );
        }
    }

    public function testEventsToolbarShowsStateActionsForComponentAdministrators(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/views/events/view.html.php');

        self::assertStringContainsString("\$canChangeState = \$canDo->get('core.edit.state') || \$canDo->get('core.admin');", $code);
        self::assertStringContainsString("\$toolbar = Toolbar::getInstance('toolbar');", $code);
        self::assertStringContainsString("\$dropdown = \$toolbar->dropdownButton('status-group')", $code);
        self::assertStringContainsString("\$childBar->publish('events.publish')->listCheck(true);", $code);
        self::assertStringContainsString("\$childBar->checkin('events.checkin')->listCheck(true);", $code);
        self::assertStringContainsString("\$childBar->trash('events.trash')->listCheck(true);", $code);
    }

    public function testEventsTableShowsSortableHitsColumn(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/admin/views/events/tmpl/default.php');

        self::assertStringContainsString(
            "HTMLHelper::_('grid.sort', 'COM_JEM_HITS', 'a.hits', \$listDirn, \$listOrder)",
            $code
        );
        self::assertStringContainsString('<?php echo (int) $row->hits; ?>', $code);
        self::assertStringContainsString(
            "HTMLHelper::_('grid.sort', 'COM_JEM_LAST_VISIT', 'a.last_visit', \$listDirn, \$listOrder)",
            $code
        );
        self::assertStringContainsString('$row->last_visit', $code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    private static function phpFilesIn(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                yield self::relativePath($file->getPathname()) => array($file->getPathname());
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    private static function rootPhpFilesWithExpectedClass(string $directory, string $prefix): iterable
    {
        foreach (new DirectoryIterator($directory) as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $expectedClass = $prefix . self::classSuffixFromFilename($file->getBasename('.php'));
            yield self::relativePath($file->getPathname()) => array($file->getPathname(), $expectedClass);
        }
    }

    private static function classSuffixFromFilename(string $filename): string
    {
        return implode('', array_map('ucfirst', preg_split('/[^A-Za-z0-9]+/', strtolower($filename)) ?: array()));
    }

    private static function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }

    private function assertExpectedClassByPrefix(string $path, string $expectedClass): void
    {
        preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+[A-Za-z0-9_\\\\]+/', self::read($path), $match);

        self::assertNotSame(array(), $match, self::relativePath($path) . ' should define a class with an extends clause.');
        self::assertSame(
            strtolower($expectedClass),
            strtolower($match[1]),
            self::relativePath($path) . ' should define ' . $expectedClass . ' ignoring camel-case word boundaries.'
        );
    }

}
