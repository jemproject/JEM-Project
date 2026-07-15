<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JemImportSecurityTestRegistry
{
    public static array $values = array();

    public function get($key, $default = null)
    {
        return self::$values[$key] ?? $default;
    }
}

if (!class_exists('JemHelper')) {
    class JemHelper
    {
        public static function globalattribs(): JemImportSecurityTestRegistry
        {
            return new JemImportSecurityTestRegistry();
        }
    }
}

require_once JEM_TEST_ROOT . '/admin/helpers/importsecurity.php';

final class ImportSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        JemImportSecurityTestRegistry::$values = array();
        JemImportSecurityHelper::resetPolicyCache();
    }

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

    public function testReportsTheBlockedTagNames(): void
    {
        $findings = JemImportSecurityHelper::findValueThreats(
            'introtext',
            '<iframe src="https://example.invalid"></iframe><svg></svg><IFRAME></IFRAME>'
        );

        self::assertSame(
            array('introtext contains blocked HTML tags [iframe, svg]'),
            $findings
        );
    }

    public function testReportsTheBlockedInlineHandlerNames(): void
    {
        $findings = JemImportSecurityHelper::findValueThreats(
            'introtext',
            '<img src="event.jpg" onerror="alert(1)" onload="alert(2)">'
        );

        self::assertContains('introtext contains inline event handlers [onerror, onload]', $findings);
    }

    public function testBlockedRecordIncludesEntityLineAndSafeIdentifiers(): void
    {
        try {
            JemImportSecurityHelper::sanitiseRecord(
                array(
                    'id' => '791',
                    'locid' => '12',
                    'introtext' => '<iframe src="https://example.invalid"></iframe>',
                ),
                'jem_events',
                23
            );
            self::fail('The unsafe record should have been rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Unsafe import data blocked (entity=jem_events, line=23, id=791, locid=12): '
                . 'introtext contains blocked HTML tags [iframe]',
                $exception->getMessage()
            );
            self::assertStringNotContainsString('example.invalid', $exception->getMessage());
        }
    }

    public function testAdditionalBlockedTagsExtendButDoNotReplaceCorePolicy(): void
    {
        JemImportSecurityTestRegistry::$values['import_additional_blocked_tags'] = 'video';
        JemImportSecurityHelper::resetPolicyCache();

        self::assertSame(
            array('introtext contains blocked HTML tags [video, script]'),
            JemImportSecurityHelper::findValueThreats(
                'introtext',
                '<video controls></video><script>alert(1)</script>'
            )
        );
    }

    public function testAllowsAndRebuildsIframeFromTrustedHttpsHost(): void
    {
        JemImportSecurityTestRegistry::$values = array(
            'import_allow_trusted_iframes' => 1,
            'import_trusted_iframe_hosts' => "youtube.com\nplayer.vimeo.com",
        );
        JemImportSecurityHelper::resetPolicyCache();

        $html = '<p>Video</p><iframe src="https://www.youtube.com/embed/abc" width="560" height="315" '
            . 'allow="autoplay; fullscreen" allowfullscreen data-note="ignored"></iframe>';
        $result = JemImportSecurityHelper::sanitiseValue('introtext', $html, 'jem_events');

        self::assertStringContainsString('<iframe src="https://www.youtube.com/embed/abc"', $result);
        self::assertStringContainsString('width="560" height="315"', $result);
        self::assertStringContainsString('allowfullscreen', $result);
        self::assertStringNotContainsString('data-note', $result);
    }

    public function testRejectsIframeFromUntrustedOrInsecureSource(): void
    {
        JemImportSecurityTestRegistry::$values = array(
            'import_allow_trusted_iframes' => 1,
            'import_trusted_iframe_hosts' => 'youtube.com',
        );
        JemImportSecurityHelper::resetPolicyCache();

        self::assertContains(
            'introtext contains iframe from an untrusted host [evil.example]',
            JemImportSecurityHelper::findValueThreats(
                'introtext',
                '<iframe src="https://evil.example/embed/abc"></iframe>'
            )
        );
        self::assertContains(
            'introtext contains iframe with an unsafe source',
            JemImportSecurityHelper::findValueThreats(
                'introtext',
                '<iframe src="http://www.youtube.com/embed/abc"></iframe>'
            )
        );
    }

    public function testNormalisesSecuritySettingLists(): void
    {
        $invalid = array();
        self::assertSame('video, audio', JemImportSecurityHelper::normaliseTagList(' VIDEO, audio video ', $invalid));
        self::assertSame(array(), $invalid);

        self::assertSame(
            "youtube.com\nplayer.vimeo.com",
            JemImportSecurityHelper::normaliseHostList("YouTube.com\nplayer.vimeo.com\nyoutube.com", $invalid)
        );
        self::assertSame(array(), $invalid);
    }
}
