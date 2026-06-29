<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importsecurity.php';

final class ImportSecurityTest extends TestCase
{
    public function testBlocksScriptTags(): void
    {
        $this->expectException(RuntimeException::class);

        JemImportSecurityHelper::sanitiseValue('title', '<script>alert(1)</script>');
    }

    public function testBlocksInlineEventHandlers(): void
    {
        $this->expectException(RuntimeException::class);

        JemImportSecurityHelper::sanitiseValue('introtext', '<img src=x onerror=alert(1)>');
    }

    public function testBlocksUnsafeUrlSchemes(): void
    {
        $this->expectException(RuntimeException::class);

        JemImportSecurityHelper::sanitiseValue('url', 'javascript:alert(1)');
    }

    public function testBlocksSpreadsheetFormulaText(): void
    {
        $this->expectException(RuntimeException::class);

        JemImportSecurityHelper::sanitiseValue('venue', '=IMPORTXML("https://example.invalid")');
    }

    public function testAllowsNegativeCoordinates(): void
    {
        self::assertSame('-3.703790', JemImportSecurityHelper::sanitiseValue('longitude', '-3.70379'));
    }

    public function testPlainTextIsStrippedOfBenignMarkup(): void
    {
        self::assertSame('Madrid venue', JemImportSecurityHelper::sanitiseValue('venue', '<strong>Madrid venue</strong>'));
    }
}
