<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PostFormTokenTest extends TestCase
{
    public function testPostFormsIncludeCsrfTokenInTheTemplate(): void
    {
        $missing = array();

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            if (!preg_match('/<form\b/i', $contents) || !preg_match('/method\s*=\s*["\']post["\']/i', $contents)) {
                continue;
            }

            if (!preg_match('/(?:form\.token|getFormToken|Session::checkToken)/', $contents)) {
                $missing[] = $relative;
            }
        }

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            "POST forms without an obvious CSRF token:\n" . implode("\n", $missing)
        );
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(): iterable
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
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
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
