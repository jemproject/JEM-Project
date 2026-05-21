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
        self::assertStringContainsString("\$this->unpublishGeneratedMenuItems(\$menutype, array('sample-category', 'sample-category-calendar', 'category-calendar'));", $code);
        self::assertStringContainsString("'index.php?option=com_jem&view=category&layout=calendar&id=' . \$this->slug(\$category)", $code);
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
