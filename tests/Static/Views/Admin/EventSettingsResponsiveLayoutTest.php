<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventSettingsResponsiveLayoutTest extends TestCase
{
    public function testBackendEventSettingsUseScopedResponsiveRows(): void
    {
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_settings.php');
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/backend.css');

        self::assertStringContainsString('jem-event-settings-tab', $layout);
        self::assertSame(4, substr_count($layout, 'jem-event-settings-grid'));
        self::assertStringContainsString('.jem-event-settings-tab .jem-event-settings-grid > li', $css);
        self::assertStringContainsString('grid-template-columns: minmax(14rem, 19rem) minmax(0, 1fr)', $css);
        self::assertMatchesRegularExpression(
            '/@media \(max-width: 767\.98px\)[\s\S]+\.jem-event-settings-tab \.jem-event-settings-grid > li[\s\S]+grid-template-columns: minmax\(0, 1fr\)/',
            $css
        );
    }
}
