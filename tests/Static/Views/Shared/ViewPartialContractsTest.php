<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewPartialContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function literalLoadTemplateProvider(): iterable
    {
        foreach (array(JEM_TEST_ROOT . '/admin/views', JEM_TEST_ROOT . '/site/views') as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                preg_match_all('/->loadTemplate\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\)/', $contents, $matches);

                foreach (array_unique($matches[1]) as $partial) {
                    yield self::relativePath($file->getPathname()) . ' loads ' . $partial => array(
                        $file->getPathname(),
                        pathinfo($file->getFilename(), PATHINFO_FILENAME),
                        $partial,
                    );
                }
            }
        }
    }

    #[DataProvider('literalLoadTemplateProvider')]
    public function testLiteralLoadTemplateCallsHaveMatchingPartial(string $path, string $baseTemplate, string $partial): void
    {
        $baseTemplates = array_unique(array($baseTemplate, strtok($baseTemplate, '_') ?: $baseTemplate));
        $candidates = array();

        foreach ($baseTemplates as $base) {
            $expectedPartial = $base . '_' . $partial . '.php';
            $candidates[] = dirname($path) . '/' . $expectedPartial;

            if (str_contains(self::relativePath($path), '/tmpl/responsive/')) {
                $candidates[] = dirname($path, 2) . '/' . $expectedPartial;
                $candidates[] = JEM_TEST_ROOT . '/site/common/views/tmpl/responsive/' . $expectedPartial;
            }

            $candidates[] = JEM_TEST_ROOT . '/site/common/views/tmpl/' . $expectedPartial;
            $candidates[] = JEM_TEST_ROOT . '/site/views/venueslist/tmpl/responsive/' . $expectedPartial;
            $candidates[] = JEM_TEST_ROOT . '/site/views/venueslist/tmpl/' . $expectedPartial;
        }

        $existing = array_filter($candidates, static fn (string $candidate): bool => is_file($candidate));

        self::assertNotSame(
            array(),
            array_values($existing),
            self::relativePath($path) . ' loads missing partial for ' . $partial
        );
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
