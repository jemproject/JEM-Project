<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ControllerTokenGuardTest extends TestCase
{
    private const SENSITIVE_METHODS = array(
        'apply',
        'attendeeadd',
        'attendeeremove',
        'cancel',
        'cleanupCatsEventRelations',
        'create',
        'delete',
        'delreguser',
        'export',
        'featured',
        'load',
        'publish',
        'rebuild',
        'remove',
        'resizethumbs',
        'save',
        'saveorder',
        'saveorderDisabled',
        'store',
        'trash',
        'triggerarchive',
        'truncateAllData',
        'unfeatured',
        'unpublish',
        'uploadimage',
        'userregister',
    );

    /**
     * Reviewed public methods that immediately delegate to a protected method containing the token check.
     */
    private const TOKEN_CHECKED_DELEGATES = array(
        'site/controllers/myvenues.php:publish',
        'site/controllers/myvenues.php:unpublish',
    );

    /**
     * Reviewed methods whose body only redirects or opens a read-only workflow.
     */
    private const READ_ONLY_ACTIONS = array(
        'admin/controllers/cssmanager.php:cancel',
    );

    public function testSensitiveControllerMethodsValidateJoomlaToken(): void
    {
        $sensitive = array_flip(self::SENSITIVE_METHODS);
        $delegates = array_flip(self::TOKEN_CHECKED_DELEGATES);
        $readOnly = array_flip(self::READ_ONLY_ACTIONS);
        $findings = array();

        foreach ($this->controllerFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            foreach ($this->publicMethods($contents) as $method => $body) {
                if (!isset($sensitive[$method])) {
                    continue;
                }

                $key = $relative . ':' . $method;

                if (isset($delegates[$key]) || isset($readOnly[$key])) {
                    continue;
                }

                if (!preg_match('/(?:Session|JSession)::checkToken\s*\(/', $body)) {
                    $findings[] = $key;
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Sensitive controller methods without a Joomla token check:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function controllerFiles(): iterable
    {
        foreach (array('admin/controllers', 'site/controllers') as $root) {
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

    /**
     * @return array<string, string>
     */
    private function publicMethods(string $contents): array
    {
        $methods = array();

        if (!preg_match_all('/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*\{/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return $methods;
        }

        foreach ($matches[1] as $index => $match) {
            $name = $match[0];
            $openBrace = strpos($contents, '{', $matches[0][$index][1]);

            if ($openBrace === false) {
                continue;
            }

            $methods[$name] = $this->extractBraceBody($contents, $openBrace);
        }

        return $methods;
    }

    private function extractBraceBody(string $contents, int $openBrace): string
    {
        $depth = 0;
        $length = strlen($contents);

        for ($i = $openBrace; $i < $length; $i++) {
            if ($contents[$i] === '{') {
                $depth++;
                continue;
            }

            if ($contents[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($contents, $openBrace, $i - $openBrace + 1);
                }
            }
        }

        return substr($contents, $openBrace);
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
