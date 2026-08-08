<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NativeColorFieldTest extends TestCase
{
    public function testEditableColorsUseTheJoomlaNativeField(): void
    {
        foreach (array(
            'admin/models/forms/category.xml',
            'admin/models/forms/venue.xml',
            'admin/models/forms/event.xml',
            'admin/models/forms/type.xml',
        ) as $relativePath) {
            $form = simplexml_load_file(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertInstanceOf(SimpleXMLElement::class, $form, $relativePath);
            self::assertNotEmpty(
                $form->xpath('//field[@name="color" and translate(@type, "COLOR", "color")="color"]'),
                $relativePath . ' must use Joomla\'s native color field.'
            );
        }

        $settings = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');

        self::assertInstanceOf(SimpleXMLElement::class, $settings);
        self::assertNotEmpty($settings->xpath('//field[translate(@type, "COLOR", "color")="color"]'));
    }

    public function testLegacyColorPickerImplementationIsRemoved(): void
    {
        foreach (array(
            'admin/models/fields/customcolor.php',
            'media/js/colorpicker.js',
            'media/css/colorpicker.css',
            'media/css/colorpicker-responsive.css',
            'media/images/clear.webp',
            'media/images/defaultcolor.webp',
        ) as $relativePath) {
            self::assertFileDoesNotExist(JEM_TEST_ROOT . '/' . $relativePath);
        }

        foreach (array(
            'admin/views/category/view.html.php',
            'admin/views/category/tmpl/edit.php',
            'admin/models/forms/settings.xml',
            'admin/models/cssmanager.php',
            'admin/models/source.php',
            'admin/sql/install.mysql.utf8.sql',
            'media/css/backend.css',
            'media/css/backend-responsive.css',
        ) as $relativePath) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringNotContainsString('colorpicker', strtolower($contents), $relativePath);
            self::assertStringNotContainsString('openpicker', strtolower($contents), $relativePath);
        }
    }
}
