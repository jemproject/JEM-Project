<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssetHygieneTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function cssAndJsProvider(): iterable
    {
        foreach (array(JEM_TEST_ROOT . '/media/css', JEM_TEST_ROOT . '/media/js') as $directory) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()), array('css', 'js'), true)) {
                    continue;
                }

                yield self::relativePath($file->getPathname()) => array($file->getPathname());
            }
        }
    }

    #[DataProvider('cssAndJsProvider')]
    public function testMediaAssetsDoNotContainPhpBlocks(string $path): void
    {
        $contents = $this->read($path);

        self::assertStringNotContainsString('<?php', $contents, self::relativePath($path) . ' should be a static asset.');
        self::assertStringNotContainsString('?>', $contents, self::relativePath($path) . ' should be a static asset.');
        self::assertStringNotContainsString('$this->', $contents, self::relativePath($path) . ' should not contain PHP object access.');
    }

    #[DataProvider('cssAndJsProvider')]
    public function testMediaAssetsDoNotContainPersonalLocalPaths(string $path): void
    {
        $contents = $this->read($path);

        self::assertDoesNotMatchRegularExpression('/(?:[A-Z]:\\\\|\/xampp\/|\/mamp\/|\/users\/)/i', $contents);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cssProvider(): iterable
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(JEM_TEST_ROOT . '/media/css', FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
                yield self::relativePath($file->getPathname()) => array($file->getPathname());
            }
        }
    }

    #[DataProvider('cssProvider')]
    public function testCssBracesAreBalanced(string $path): void
    {
        $contents = preg_replace('#/\*.*?\*/#s', '', $this->read($path)) ?? '';

        self::assertSame(
            substr_count($contents, '{'),
            substr_count($contents, '}'),
            self::relativePath($path) . ' should have balanced CSS braces.'
        );
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
