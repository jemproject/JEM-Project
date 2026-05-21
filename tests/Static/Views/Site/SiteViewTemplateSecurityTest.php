<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteViewTemplateSecurityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function siteViewPhpProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/views');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/common/views');
    }

    #[DataProvider('siteViewPhpProvider')]
    public function testSiteViewPhpFilesBlockDirectAccess(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/defined\s*\(\s*[\'"]_JEXEC[\'"]\s*\)\s*or\s+die\s*;?/i',
            (string) file_get_contents($path),
            self::relativePath($path) . ' should block direct access with the _JEXEC guard.'
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

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
