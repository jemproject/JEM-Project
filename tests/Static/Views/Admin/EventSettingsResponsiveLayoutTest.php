<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventSettingsResponsiveLayoutTest extends TestCase
{
    public function testBackendEventSettingsUseScopedResponsiveRows(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_settings.php');

        self::assertStringContainsString('jem-event-settings-tab', $layout);
        self::assertSame(4, substr_count($layout, 'jem-event-settings-grid'));

        $fileName = 'backend.css';
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/' . $fileName);

        self::assertStringContainsString('.jem-event-settings-tab .jem-event-settings-grid > li', $css, $fileName);
        self::assertStringContainsString(
            'grid-template-columns: minmax(14rem, 19rem) minmax(0, 1fr)',
            $css,
            $fileName
        );
        self::assertMatchesRegularExpression(
            '/@media \(max-width: 767\.98px\)[\s\S]+\.jem-event-settings-tab \.jem-event-settings-grid > li[\s\S]+grid-template-columns: minmax\(0, 1fr\)/',
            $css,
            $fileName
        );
    }
}
