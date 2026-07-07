<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhpSyntaxTest extends TestCase
{
    public function testPhpFilesHaveNoSyntaxErrors(): void
    {
        $failures = array();

        foreach ($this->phpFiles() as $path) {
            $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                $failures[] = self::relativePath($path) . ":\n" . implode("\n", $output);
            }
        }

        self::assertSame(array(), $failures, "PHP syntax errors found:\n" . implode("\n\n", $failures));
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(): iterable
    {
        $excluded = array(
            str_replace('\\', '/', JEM_TEST_ROOT . '/vendor') => true,
            str_replace('\\', '/', JEM_TEST_ROOT . '/.git') => true,
            str_replace('\\', '/', JEM_TEST_ROOT . '/.phpunit.cache') => true,
        );

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(JEM_TEST_ROOT, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            foreach ($excluded as $directory => $_) {
                if (str_starts_with($path, $directory . '/')) {
                    continue 2;
                }
            }

            yield $file->getPathname();
        }
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
