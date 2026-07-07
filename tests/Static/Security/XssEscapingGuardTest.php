<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class XssEscapingGuardTest extends TestCase
{
    public function testKnownXssPayloadsAreEscapedForTextAndAttributes(): void
    {
        $payloads = array(
            '<script>alert(\'XSS\')</script>',
            '<img src=x onerror=alert(1)>',
        );

        foreach ($payloads as $payload) {
            $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

            self::assertStringNotContainsString('<script', $escaped);
            self::assertStringNotContainsString('<img', $escaped);
            self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $escaped);
            self::assertStringContainsString('&lt;', $escaped);
        }
    }

    public function testKnownXssPayloadsAreStrippedForPlainTextSummaries(): void
    {
        $payloads = array(
            '<script>alert(\'XSS\')</script>',
            '<img src=x onerror=alert(1)>Visible text',
        );

        foreach ($payloads as $payload) {
            $plain = trim(strip_tags($payload));

            self::assertStringNotContainsString('<', $plain);
            self::assertStringNotContainsString('>', $plain);
            self::assertStringNotContainsString('onerror=', $plain);
        }
    }

    public function testEventTypeOutputEscapesNameDescriptionIconColorAndLinks(): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');

        self::assertStringContainsString('htmlspecialchars($item->{$nameProperty}, ENT_QUOTES, \'UTF-8\')', $contents);
        self::assertStringContainsString('strip_tags((string) $description)', $contents);
        self::assertStringContainsString('htmlspecialchars($item->{$colorProperty}, ENT_QUOTES, \'UTF-8\')', $contents);
        self::assertStringContainsString('htmlspecialchars($item->{$iconProperty}, ENT_QUOTES, \'UTF-8\')', $contents);
        self::assertStringContainsString('JemHelperRoute::getTypeeventsRoute($typeRouteId)', $contents);
        self::assertStringContainsString('htmlspecialchars(Route::_($route), ENT_QUOTES, \'UTF-8\')', $contents);
    }

    public function testSiteTemplatesEscapeCommonTextInputs(): void
    {
        $allowedHtml = array_flip(array(
            'site/views/categories/tmpl/default.php:echo $row->description;',
            'site/views/categories/tmpl/responsive/default.php:echo $row->description;',
            'site/views/category/tmpl/default.php:echo $this->description;',
            'site/views/category/tmpl/responsive/default.php:echo $this->description;',
            'site/views/venue/tmpl/default.php:echo $this->venuedescription;',
            'site/views/venue/tmpl/responsive/default.php:echo $this->venuedescription;',
        ));
        $findings = array();
        $fields = array('title', 'name', 'venue', 'city', 'country', 'description', 'street', 'state', 'postalCode', 'url', 'urlclean', 'latitude', 'longitude');

        foreach ($this->siteTemplates() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            foreach ($fields as $field) {
                $pattern = '/echo\s+\$[A-Za-z_][A-Za-z0-9_]*(?:->|\[[\'"])' . preg_quote($field, '/') . '[\'"]?[^;]*;/';

                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach ($matches[0] as $echo) {
                        $finding = $relative . ':' . trim($echo);

                        if (!isset($allowedHtml[$finding]) && !preg_match('/(escape|htmlspecialchars|strip_tags|Text::_|JemOutput::|HTMLHelper::|Route::_)/', $echo)) {
                            $findings[] = $finding;
                        }
                    }
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Common text fields should be escaped or intentionally rendered through a helper:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function siteTemplates(): iterable
    {
        $directory = JEM_TEST_ROOT . '/site/views';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                yield $file->getPathname();
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
