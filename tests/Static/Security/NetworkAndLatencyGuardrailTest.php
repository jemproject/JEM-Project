<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NetworkAndLatencyGuardrailTest extends TestCase
{
    private const ALLOWED_CLIENT_NETWORK_CALLS = array(
        'admin/views/venue/tmpl/edit.php:fetch',
        'media/js/load-more.js:XMLHttpRequest',
        'modules/mod_jem_cal/tmpl/grid.php:fetch',
        'site/controller.php:XMLHttpRequest',
    );

    private const ALLOWED_SERVER_NETWORK_CALLS = array(
        'admin/models/updatecheck.php:file_get_contents',
        'site/classes/output.class.php:file_get_contents',
    );

    public function testClientSideNetworkCallsAreReviewed(): void
    {
        $allowed = array_flip(self::ALLOWED_CLIENT_NETWORK_CALLS);
        $findings = array();
        $patterns = array(
            'fetch' => '/\bfetch\s*\(/',
            'XMLHttpRequest' => '/\bXMLHttpRequest\b/',
            'sendBeacon' => '/\bnavigator\.sendBeacon\s*\(/',
            'tracking image' => '/\bnew\s+Image\s*\(/',
        );

        foreach ($this->sourceFiles(array('php', 'js')) as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $key = $relative . ':' . $label;

                    if (!isset($allowed[$key])) {
                        $findings[] = $key;
                    }
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "New client-side network calls need review:\n" . implode("\n", $findings)
        );
    }

    public function testServerSideRemoteReadsAreReviewed(): void
    {
        $allowed = array_flip(self::ALLOWED_SERVER_NETWORK_CALLS);
        $findings = array();

        foreach ($this->sourceFiles(array('php')) as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            if (preg_match_all('/\b(curl_init|curl_exec|fsockopen|stream_socket_client|file_get_contents)\s*\(/i', $contents, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $function) {
                $function = strtolower($function);

                if ($function !== 'file_get_contents') {
                    $findings[] = $relative . ':' . $function;
                    continue;
                }

                if (preg_match('/file_get_contents\s*\(\s*(\$updateFile|\$search_url)/', $contents) === 1) {
                    $key = $relative . ':file_get_contents';

                    if (!isset($allowed[$key])) {
                        $findings[] = $key;
                    }
                }
            }
        }

        $findings = array_values(array_unique($findings));
        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "New server-side remote reads need review:\n" . implode("\n", $findings)
        );
    }

    public function testArtificialLatencyCallsAreNotIntroduced(): void
    {
        $findings = array();

        foreach ($this->sourceFiles(array('php', 'js')) as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->contentsForScan($path);

            if (preg_match('/\b(sleep|usleep|time_nanosleep|set_time_limit)\s*\(/i', $contents, $matches) === 1) {
                $findings[] = $relative . ':' . strtolower($matches[1]);
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Artificial latency or runtime limit changes need review:\n" . implode("\n", $findings)
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

    private function contentsForScan(string $path): string
    {
        $contents = (string) file_get_contents($path);

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            return $contents;
        }

        return $this->stripPhpComments($contents);
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
