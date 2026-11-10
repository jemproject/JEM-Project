<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationStatusOptionsTest extends TestCase
{
    public function testMenuOptionsAndLanguageKeysAreComplete(): void
    {
        $metadata = simplexml_load_file(JEM_TEST_ROOT . '/site/views/registrationstatus/tmpl/default.xml');
        self::assertNotFalse($metadata);
        $strings = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        $fields = [];
        foreach ($metadata->xpath('//field') as $field) {
            $name = (string) $field['name'];
            self::assertArrayNotHasKey($name, $fields);
            $fields[$name] = (string) $field['default'];
            self::assertArrayHasKey((string) $field['label'], $strings);
        }
        foreach (['date', 'category', 'venue', 'country', 'region', 'county', 'city', 'publication', 'event_status', 'search'] as $filter) {
            self::assertArrayHasKey('show_' . $filter . '_filter', $fields);
            self::assertArrayHasKey('default_' . $filter, $fields);
        }
        self::assertSame('upcoming', $fields['date_filter_default']);
        self::assertSame('1', $fields['default_publication']);
        self::assertSame('0', $fields['refresh_seconds']);
        $source = file_get_contents(JEM_TEST_ROOT . '/site/common/views/tmpl/registrationstatus.php');
        preg_match_all("/'(COM_JEM_RS_[A-Z_]+)'/", $source, $keys);
        foreach (array_unique($keys[1]) as $key) {
            if (str_ends_with($key, '_')) { continue; }
            self::assertArrayHasKey($key, $strings);
        }
    }
}
