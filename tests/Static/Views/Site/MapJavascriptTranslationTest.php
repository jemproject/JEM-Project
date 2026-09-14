<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MapJavascriptTranslationTest extends TestCase
{
    public function testMapTemplatesJsonEncodeTranslationsUsedByInlineJavascript(): void
    {
        $templates = array(
            'site/views/eventsmap/tmpl/default.php',
            'site/views/eventsmap/tmpl/responsive.php',
            'site/views/venuesmap/tmpl/default.php',
            'modules/mod_jem_map/tmpl/default.php',
        );

        foreach ($templates as $relativePath) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringContainsString('$encodeMapText = static function', $source, $relativePath);
            self::assertStringContainsString(
                'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT',
                $source,
                $relativePath
            );
            self::assertStringContainsString("<?= \$encodeMapText('", $source, $relativePath);

            preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $source, $matches);
            self::assertNotEmpty($matches[1], $relativePath);

            $javascript = implode("\n", $matches[1]);

            self::assertDoesNotMatchRegularExpression('/<\?=\s*Text::_\s*\(/', $javascript, $relativePath);
            self::assertDoesNotMatchRegularExpression(
                '/[\'\"]\s*<\?=\s*\$encodeMapText\s*\(/',
                $javascript,
                $relativePath
            );
        }
    }

    public function testJavascriptEncodingFlagsProtectApostrophesAndClosingScriptTags(): void
    {
        $translation = "Location isn't available. </script>";
        $encoded = json_encode(
            $translation,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        self::assertIsString($encoded);
        self::assertStringContainsString('\\u0027', $encoded);
        self::assertStringContainsString('\\u003C\\/script\\u003E', $encoded);
        self::assertSame($translation, json_decode($encoded, true));
    }
}
