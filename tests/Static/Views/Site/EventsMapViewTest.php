<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventsMapViewTest extends TestCase
{
    public function testEventsMapUsesVenuesMapDefaultEuropeViewport(): void
    {
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/view.html.php');
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/tmpl/default.php');
        $responsive = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/tmpl/responsive.php');
        $xml = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/tmpl/default.xml');

        self::assertStringContainsString("\$params->get('map_zoom', 4)", $view);

        foreach (array($template, $responsive) as $source) {
            self::assertStringContainsString("\$this->params->get('map_center_lat', '54.526')", $source);
            self::assertStringContainsString("\$this->params->get('map_center_lng', '15.255')", $source);
            self::assertStringContainsString("\$this->params->get('map_zoom', '4')", $source);
        }

        self::assertStringContainsString('default="54.526"', $xml);
        self::assertStringContainsString('default="15.255"', $xml);
        self::assertStringContainsString('default="4"', $xml);
    }

    public function testFullscreenControlIsEnabledByDefault(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/tmpl/default.xml');

        self::assertMatchesRegularExpression('/name="full_screen_map"[^>]*default="1"/s', $xml);

        foreach (array('default.php', 'responsive.php') as $template) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/tmpl/' . $template);
            self::assertStringContainsString("get('full_screen_map', '1')", $source);
        }
    }

    public function testVenueTypeBadgeStartsEachEventMapPopup(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/site/helpers/map.php');

        self::assertStringContainsString("'vt.name AS venue_type_name'", $helper);
        self::assertStringContainsString("'vt.color AS venue_type_color'", $helper);
        self::assertStringContainsString("quoteName('vt.entity') . ' = 3'", $helper);

        foreach (array('default.php', 'responsive.php') as $template) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventsmap/tmpl/' . $template);
            self::assertStringContainsString("'venue_type_name' =>", $source);
            self::assertStringContainsString("'venue_type_color' =>", $source);
            self::assertSame(2, substr_count($source, 'JemMapHelper::typeBadgeHtml('));
        }
    }
}
