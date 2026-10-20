<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminMenuLanguageTest extends TestCase
{
    public function testFieldMenuUsesControlPanelKeysAvailableInSystemLanguage(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/jem.xml');
        self::assertNotFalse($manifest);

        $translations = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini');
        self::assertIsArray($translations);

        $expected = array(
            'option=com_fields&view=fields&context=com_jem.event' => 'COM_JEM_MAIN_JOOMLA_FIELDS',
            'option=com_fields&view=groups&context=com_jem.event' => 'COM_JEM_MAIN_JOOMLA_FIELD_GROUPS',
        );

        foreach ($manifest->administration->submenu->menu as $item) {
            $link = (string) $item['link'];
            if (!isset($expected[$link])) {
                continue;
            }

            $key = $expected[$link];
            self::assertSame($key, (string) $item);
            self::assertNotEmpty($translations[$key] ?? null);
            unset($expected[$link]);
        }

        self::assertSame(array(), $expected);
    }
}
