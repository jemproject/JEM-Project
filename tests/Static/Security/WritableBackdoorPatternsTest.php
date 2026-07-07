<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WritableBackdoorPatternsTest extends TestCase
{
    public function testCodeDoesNotWriteExecutablePhpPayloads(): void
    {
        $findings = array();

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            if (preg_match('/\b(file_put_contents|fwrite)\s*\([^;]*(<\?php|base64_decode|eval\s*\()/is', $contents) === 1) {
                $findings[] = $relative;
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Writing executable or encoded payloads is not allowed:\n" . implode("\n", $findings)
        );
    }

    public function testCodeDoesNotGrantWorldWritablePermissions(): void
    {
        $findings = array();

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            if (preg_match('/\bchmod\s*\([^;]*(0777|777)/i', $contents) === 1) {
                $findings[] = $relative;
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "World-writable permissions need security review:\n" . implode("\n", $findings)
        );
    }

    public function testCodeDoesNotCopyRemoteUrlsToLocalFiles(): void
    {
        $findings = array();

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            if (preg_match('/\bcopy\s*\(\s*[\'"]https?:\/\//i', $contents) === 1) {
                $findings[] = $relative;
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Copying remote URLs into local files needs review:\n" . implode("\n", $findings)
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

    private function stripPhpComments(string $contents): string
    {
        $tokens = token_get_all($contents);
        $clean = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            $clean .= is_array($token) ? $token[1] : $token;
        }

        return $clean;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
