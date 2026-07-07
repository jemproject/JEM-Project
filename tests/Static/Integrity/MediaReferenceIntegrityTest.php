<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MediaReferenceIntegrityTest extends TestCase
{
    public function testLiteralMediaCssAndJsReferencesExistInTheRepository(): void
    {
        $missing = array();

        foreach ($this->sourceFiles() as $path) {
            $contents = (string) file_get_contents($path);
            preg_match_all('/media\/com_jem\/(css|js)\/[A-Za-z0-9_.\/-]+\.(?:css|js)/', $contents, $matches);

            foreach (array_unique($matches[0]) as $reference) {
                $localPath = JEM_TEST_ROOT . '/' . preg_replace('#^media/com_jem/#', 'media/', $reference);

                if (!is_file($localPath)) {
                    $missing[] = $this->relativePath($path) . ' -> ' . $reference;
                }
            }
        }

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            "Missing literal media asset references:\n" . implode("\n", $missing)
        );
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), array('php', 'xml'), true)) {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
