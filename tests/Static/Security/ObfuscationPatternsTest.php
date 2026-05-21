<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ObfuscationPatternsTest extends TestCase
{
    /**
     * Vendored browser libraries are tracked as third-party assets. Project code must stay readable.
     */
    private const VENDORED_JS = array(
        'media/js/highlighter.js',
        'media/js/infobox.js',
        'media/js/jquery.geocomplete.js',
        'media/js/leaflet.js',
        'media/js/lightbox.min.js',
    );

    public function testProjectCodeDoesNotUseObfuscationPrimitives(): void
    {
        $findings = array();
        $patterns = array(
            'eval' => '/\beval\s*\(/i',
            'Function constructor' => '/\bFunction\s*\(/',
            'string setTimeout' => '/\bsetTimeout\s*\(\s*[\'"]/i',
            'string setInterval' => '/\bsetInterval\s*\(\s*[\'"]/i',
            'fromCharCode' => '/\bfromCharCode\b/i',
            'atob' => '/\batob\s*\(/i',
            'btoa' => '/\bbtoa\s*\(/i',
            'gzinflate' => '/\bgzinflate\s*\(/i',
            'gzuncompress' => '/\bgzuncompress\s*\(/i',
            'str_rot13' => '/\bstr_rot13\s*\(/i',
            'preg_replace /e' => '/\bpreg_replace\s*\([^;]+\/e[\'"]/is',
            'assert call' => '/\bassert\s*\(/i',
        );

        foreach ($this->sourceFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->contentsForScan($path);

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
            "Obfuscation-like primitives need security review:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
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
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()), array('php', 'js'), true)) {
                    continue;
                }

                $relative = $this->relativePath($file->getPathname());

                if (in_array($relative, self::VENDORED_JS, true)) {
                    continue;
                }

                yield $file->getPathname();
            }
        }
    }

    private function contentsForScan(string $path): string
    {
        $contents = (string) file_get_contents($path);

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            return $contents;
        }

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
