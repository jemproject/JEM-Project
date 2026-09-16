<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WideModuleResponsiveTest extends TestCase
{
    public function testClassicTableLayoutContainsTextOnNarrowViewports(): void
    {
        $css = $this->narrowViewportRules();
        $tableRule = $this->ruleBody($css, 'div#jemmodulewide .eventset');
        $cellRule = $this->ruleBody($css, 'div#jemmodulewide .eventset td');

        self::assertStringContainsString('table-layout: fixed;', $tableRule);
        self::assertStringContainsString('box-sizing: border-box;', $tableRule);
        self::assertStringContainsString('min-width: 0;', $cellRule);
        self::assertStringContainsString('overflow-wrap: anywhere;', $cellRule);
        self::assertStringContainsString('word-break: break-word;', $cellRule);
    }

    public function testClassicTableImagesRemainInsideNarrowCells(): void
    {
        $css = $this->narrowViewportRules();
        $imageRule = $this->ruleBody($css, 'div#jemmodulewide .image-preview');

        self::assertStringContainsString('display: block;', $imageRule);
        self::assertStringContainsString('max-width: 100%;', $imageRule);
        self::assertStringContainsString('height: auto;', $imageRule);
        self::assertStringContainsString('box-sizing: border-box;', $imageRule);
        self::assertStringContainsString('margin: 3px auto;', $imageRule);
    }

    private function narrowViewportRules(): string
    {
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_wide/tmpl/default.css');
        $mediaStart = strpos($css, '@media (max-width: 480px)');

        self::assertNotFalse($mediaStart);

        return substr($css, $mediaStart);
    }

    private function ruleBody(string $css, string $selector): string
    {
        $matched = preg_match('/' . preg_quote($selector, '/') . '\\s*\\{([^}]+)\\}/', $css, $matches);

        self::assertSame(1, $matched, $selector);

        return $matches[1];
    }
}
