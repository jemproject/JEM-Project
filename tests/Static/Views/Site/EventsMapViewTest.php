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
}
