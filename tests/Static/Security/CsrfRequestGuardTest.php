<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CsrfRequestGuardTest extends TestCase
{
    public function testPostFormsRenderCsrfTokens(): void
    {
        $postFormTest = (string) file_get_contents(JEM_TEST_ROOT . '/tests/Static/Security/PostFormTokenTest.php');

        self::assertStringContainsString('POST forms without an obvious CSRF token', $postFormTest);
        self::assertStringContainsString('form\\.token', $postFormTest);
    }

    public function testSensitiveControllerMethodsRejectMissingTokens(): void
    {
        $controllerGuard = (string) file_get_contents(JEM_TEST_ROOT . '/tests/Static/Security/ControllerTokenGuardTest.php');

        self::assertStringContainsString('Sensitive controller methods without a Joomla token check', $controllerGuard);
        self::assertStringContainsString('Session|JSession)::checkToken', $controllerGuard);
    }

    public function testStateChangingControllersCallCheckTokenBeforeMutatingInput(): void
    {
        $findings = array();

        foreach ($this->controllerFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            foreach ($this->publicMethods($contents) as $method => $body) {
                if (!preg_match('/\b(save|delete|remove|publish|unpublish|load|upload|truncate|cleanup|attendeeadd|attendeeremove)\b/i', $method)) {
                    continue;
                }

                $tokenPos = strpos($body, 'checkToken');

                if ($tokenPos === false) {
                    continue;
                }

                $mutationPos = $this->firstMutationPosition($body);

                if ($mutationPos !== null && $mutationPos < $tokenPos) {
                    $findings[] = $relative . ':' . $method;
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Token checks should happen before reading/mutating request data:\n" . implode("\n", $findings)
        );
    }

    private function firstMutationPosition(string $body): ?int
    {
        $positions = array();

        foreach (array('->input->', 'getInput()', 'getModel(', 'parent::save', 'parent::delete', 'File::upload', 'File::delete', 'setRedirect(') as $needle) {
            $pos = strpos($body, $needle);

            if ($pos !== false) {
                $positions[] = $pos;
            }
        }

        return $positions ? min($positions) : null;
    }

    /**
     * @return iterable<string>
     */
    private function controllerFiles(): iterable
    {
        foreach (array('admin/controllers', 'site/controllers') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;
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
            $openBrace = strpos($contents, '{', $matches[0][$index][1]);

            if ($openBrace !== false) {
                $methods[$match[0]] = $this->extractBraceBody($contents, $openBrace);
            }
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
            } elseif ($contents[$i] === '}') {
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
