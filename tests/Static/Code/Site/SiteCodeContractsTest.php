<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteCodeContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function siteCodeProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/controllers');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/models');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/helpers');
    }

    #[DataProvider('siteCodeProvider')]
    public function testSiteCodeFilesBlockDirectAccess(string $path): void
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
    public static function siteControllerProvider(): iterable
    {
        yield from self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/site/controllers', 'JemController');
    }

    #[DataProvider('siteControllerProvider')]
    public function testSiteControllersKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteModelProvider(): iterable
    {
        yield from self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/site/models', 'JemModel');
    }

    #[DataProvider('siteModelProvider')]
    public function testSiteModelsKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function siteFormFieldProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/models/fields');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/models/links');
    }

    #[DataProvider('siteFormFieldProvider')]
    public function testSiteFormFieldsDefineJFormFieldClass(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/(?:class\s+JFormField[A-Za-z0-9_]+\s+extends\s+[A-Za-z0-9_\\\\]+|require_once\s+JPATH_ADMINISTRATOR\s*\.\s*[\'"]\/components\/com_jem\/models\/fields\/[A-Za-z0-9_]+\.php[\'"])/',
            self::read($path),
            self::relativePath($path) . ' should define or delegate to a JFormField class.'
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
