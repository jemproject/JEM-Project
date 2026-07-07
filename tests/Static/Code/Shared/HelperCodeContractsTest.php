<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HelperCodeContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function helperProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/helpers');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/helpers');
    }

    #[DataProvider('helperProvider')]
    public function testHelperFilesExposeAClassOrFunction(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/\b(?:class|function)\s+[A-Za-z_][A-Za-z0-9_]*/',
            self::read($path),
            self::relativePath($path) . ' should expose a helper class or function.'
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

    private static function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
