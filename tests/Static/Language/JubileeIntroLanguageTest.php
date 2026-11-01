<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Guards Jubilee editor guidance without changing module parameter semantics. */
final class JubileeIntroLanguageTest extends TestCase
{
    /**
     * Require one declaration per file and identical, complete intro guidance.
     *
     * @return void
     */
    public function testIntroKeysAreUniqueAndConsistent(): void
    {
        $translations = [];
        foreach (['mod_jem_jubilee.ini', 'mod_jem_jubilee.sys.ini'] as $filename) {
            $source = file_get_contents(
                JEM_TEST_ROOT . '/modules/mod_jem_jubilee/language/en-GB/' . $filename
            );
            foreach (['MOD_JEM_JUBILEE_INTROTEXT', 'MOD_JEM_JUBILEE_INTROTEXT_DESC'] as $key) {
                self::assertSame(1, preg_match_all('/^' . $key . '\\s*=/m', $source), $filename . ': ' . $key);
            }
            $strings = parse_ini_string($source, false, INI_SCANNER_RAW);
            self::assertIsArray($strings);
            self::assertSame('Intro text', $strings['MOD_JEM_JUBILEE_INTROTEXT']);
            $description = $strings['MOD_JEM_JUBILEE_INTROTEXT_DESC'];
            foreach (['[day]', '[month]', '[year]'] as $token) {
                self::assertStringContainsString($token, $description);
            }
            self::assertStringContainsString('above the module content', $description);
            self::assertStringContainsString('Leave empty', $description);
            self::assertStringNotContainsString('beside the calendar', $description);
            $translations[] = $description;
        }
        self::assertSame($translations[0], $translations[1]);
    }

    /**
     * Keep the existing editor field, visibility switch and footer controls.
     *
     * @return void
     */
    public function testExistingEditorOptionsRemainConnected(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/modules/mod_jem_jubilee/mod_jem_jubilee.xml');
        self::assertNotFalse($xml);
        $fields = $xml->xpath('//field[@name="introtext"]');
        self::assertCount(1, $fields);
        self::assertSame('editor', (string) $fields[0]['type']);
        self::assertSame('MOD_JEM_JUBILEE_INTROTEXT', (string) $fields[0]['label']);
        self::assertSame('MOD_JEM_JUBILEE_INTROTEXT_DESC', (string) $fields[0]['description']);
        self::assertSame('showintrotext:1', (string) $fields[0]['showon']);
        foreach (['showintrotext', 'showfootertext', 'footertext'] as $field) {
            self::assertCount(1, $xml->xpath('//field[@name="' . $field . '"]'));
        }
    }
}
