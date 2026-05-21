<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DebugArtifactTest extends TestCase
{
    private const VENDORED_JS = array(
        'media/js/leaflet.js',
        'media/js/leaflet-fullscreen.js',
        'media/js/lightbox.min.js',
    );

    public function testPhpCodeDoesNotContainActiveDebugOutput(): void
    {
        $findings = array();
        $patterns = array(
            'var_dump' => '/\bvar_dump\s*\(/',
            'print_r' => '/\bprint_r\s*\(/',
            'debug_zval_dump' => '/\bdebug_zval_dump\s*\(/',
            'debug_backtrace' => '/\bdebug_backtrace\s*\(/',
            'phpinfo' => '/\bphpinfo\s*\(/',
            'xdebug_break' => '/\bxdebug_break\s*\(/',
        );

        foreach ($this->sourceFiles(array('php')) as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $findings[] = $relative . ':' . $label;
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Active debug output can leak data and must be removed:\n" . implode("\n", $findings)
        );
    }

    public function testProjectJavascriptDoesNotContainConsoleDebugOutput(): void
    {
        $findings = array();

        foreach ($this->sourceFiles(array('js')) as $path) {
            $relative = $this->relativePath($path);

            if (in_array($relative, self::VENDORED_JS, true)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            if (preg_match('/\bconsole\.(log|debug|trace|table)\s*\(/', $contents, $matches) === 1) {
                $findings[] = $relative . ':console.' . $matches[1];
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Console debug output should not ship in project JavaScript:\n" . implode("\n", $findings)
        );
    }

    /**
     * @param list<string> $extensions
     * @return iterable<string>
     */
    private function sourceFiles(array $extensions): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins', 'media/js') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
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
