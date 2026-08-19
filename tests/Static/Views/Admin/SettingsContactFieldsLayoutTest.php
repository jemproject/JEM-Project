<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SettingsContactFieldsLayoutTest extends TestCase
{
    public function testSettingsFormOwnsContentAwareResponsiveRows(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default.php');
        self::assertStringContainsString('form-validate jem-settings-form', $template);

        $file = 'backend.css';
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/' . $file);
        self::assertStringContainsString('.jem-settings-form ul.adminformlist > li', $css, $file);
        self::assertStringContainsString('.jem-settings-form .label-form > .control-group', $css, $file);
        self::assertStringContainsString('.jem-settings-form .label-form .controls', $css, $file);
        self::assertStringContainsString('height: auto;', $css, $file);
        self::assertStringContainsString('.jem-settings-form .label-form select[multiple]', $css, $file);
        self::assertStringContainsString('@media (max-width: 767.98px)', $css, $file);
    }
}
