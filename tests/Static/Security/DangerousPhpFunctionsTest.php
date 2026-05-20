<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DangerousPhpFunctionsTest extends TestCase
{
    /**
     * Existing reviewed uses. New uses should be reviewed before being added here.
     */
    private const ALLOWED_CALLS = array(
        'admin/controllers/source.php:base64_decode',
        'admin/models/source.php:base64_decode',
        'site/classes/iCalcreator.class.php:unserialize',
        'site/controllers/event.php:base64_decode',
        'site/controllers/venue.php:base64_decode',
        'site/models/editevent.php:base64_decode',
        'site/models/editvenue.php:base64_decode',
        'site/views/attendees/view.html.php:base64_decode',
    );

    public function testNoNewDangerousFunctionCallsAreIntroduced(): void
    {
        $allowed = array_flip(self::ALLOWED_CALLS);
        $findings = array();

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripComments((string) file_get_contents($path));

            preg_match_all(
                '/\b(eval|exec|shell_exec|system|passthru|proc_open|popen|unserialize|base64_decode)\s*\(/',
                $contents,
                $matches
            );

            foreach ($matches[1] as $function) {
                $key = $relative . ':' . $function;

                if (!isset($allowed[$key])) {
                    $findings[] = $key;
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "New dangerous PHP calls need review:\n" . implode("\n", $findings)
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

    private function stripComments(string $contents): string
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
