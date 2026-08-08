<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SpecialDayMenuViewTest extends TestCase
{
    public function testSpecialDaySubmissionIsExposedAsAJemMenuItemType(): void
    {
        $path = JEM_TEST_ROOT . '/site/views/specialday/tmpl/edit.xml';

        self::assertFileExists($path);

        $xml = simplexml_load_file($path);

        self::assertInstanceOf(SimpleXMLElement::class, $xml);
        self::assertSame('COM_JEM_SPECIALDAY_VIEW_EDIT_TITLE', (string) $xml->layout['title']);
        self::assertSame('COM_JEM_SPECIALDAY_VIEW_EDIT_DESC', trim((string) $xml->layout->message));
        self::assertNotSame('true', (string) $xml->layout['hidden']);
    }

    public function testSpecialDaySubmissionMenuLanguageKeysExist(): void
    {
        $language = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini'
        );

        self::assertMatchesRegularExpression(
            '/^COM_JEM_SPECIALDAY_VIEW_EDIT_TITLE=/m',
            $language
        );
        self::assertMatchesRegularExpression(
            '/^COM_JEM_SPECIALDAY_VIEW_EDIT_DESC=/m',
            $language
        );
    }

    public function testGeneratedFrontendMenuUsesTheExposedSpecialDayLayout(): void
    {
        $controller = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php'
        );

        self::assertStringContainsString(
            "index.php?option=com_jem&view=specialday&layout=edit",
            $controller
        );
    }
}
