<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MenuXmlLanguageKeysTest extends TestCase
{
    public function testMenuXmlLanguageKeysExistInAdminSysLanguageFile(): void
    {
        $defined = $this->readLanguageKeys(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini');
        $used = array();

        foreach (array(
            JEM_TEST_ROOT . '/site/views/myevents/tmpl/default.xml',
            JEM_TEST_ROOT . '/site/views/myattendances/tmpl/default.xml',
        ) as $file) {
            $xml = simplexml_load_file($file);

            if ($xml === false) {
                continue;
            }

            $this->collectXmlLanguageKeys($xml, $used);
        }

        $missing = array_values(array_filter(
            array_keys($used),
            static fn (string $key): bool => !isset($defined[$key])
        ));

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            "Menu XML language keys must exist in admin/language/en-GB/com_jem.sys.ini:\n" . implode("\n", $missing)
        );
    }

    /**
     * @param array<string, true> $keys
     */
    private function collectXmlLanguageKeys(SimpleXMLElement $element, array &$keys): void
    {
        foreach (array('title', 'label', 'description') as $attribute) {
            $value = trim((string) $element[$attribute]);

            if ($this->isIssue2199LanguageKey($value)) {
                $keys[$value] = true;
            }
        }

        if ($element->getName() === 'message') {
            $value = trim((string) $element);

            if ($this->isIssue2199LanguageKey($value)) {
                $keys[$value] = true;
            }
        }

        foreach ($element->children() as $child) {
            $this->collectXmlLanguageKeys($child, $keys);
        }
    }

    private function isIssue2199LanguageKey(string $value): bool
    {
        return preg_match('/^COM_JEM_(MYEVENTS|MYATTENDANCES)_(FIELDSET_COLUMNS|DISPLAYATTENDEECOLUMN(?:_DESC)?|SHOW_[A-Z]+_COLUMN(?:_DESC)?)$/', $value) === 1;
    }

    /**
     * @return array<string, true>
     */
    private function readLanguageKeys(string $path): array
    {
        self::assertFileExists($path);

        preg_match_all('/^([A-Z0-9_]+)=/m', (string) file_get_contents($path), $matches);

        return array_fill_keys($matches[1], true);
    }
}
