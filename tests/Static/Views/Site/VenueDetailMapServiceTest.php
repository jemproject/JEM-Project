<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenueDetailMapServiceTest extends TestCase
{
    public function testVenueMenuUsesTheGlobalMapServiceByDefault(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/tmpl/default.xml');

        self::assertMatchesRegularExpression(
            '/name="venue_map_display"[^>]*default="global"/s',
            $xml
        );
        self::assertStringContainsString('<option value="global">JGLOBAL_USE_GLOBAL</option>', $xml);
    }

    public function testBothVenueLayoutsScopeOverridesAndUseTheResolvedService(): void
    {
        foreach (array('default.php', 'responsive/default.php') as $template) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/tmpl/' . $template);

            self::assertStringContainsString('JemOutput::resolveVenueMapConfiguration(', $source);
            self::assertStringContainsString("JemHelper::isActiveMenuView('venue', (int) \$this->venue->id)", $source);
            self::assertStringContainsString("\$venueMapSettings->set('global_show_mapserv', \$venueMapService)", $source);
            self::assertStringContainsString('JemOutput::mapicon($this->venue, null, $venueMapSettings, $venueMapDisplay)', $source);
            self::assertStringNotContainsString("'https://www.openstreetmap.org/?mlat='", $source);
        }
    }

    public function testBothVenueLayoutsRenderEmbeddedMapsInsideTheOverviewCard(): void
    {
        foreach (array('default.php', 'responsive/default.php') as $template) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/tmpl/' . $template);
            $mapPosition = strpos($source, '<div class="jem-venue-map-section">');
            $descriptionPosition = strpos($source, "if (\$venueCustomFieldsPosition === 'before_description')");

            self::assertNotFalse($mapPosition);
            self::assertNotFalse($descriptionPosition);
            self::assertLessThan($descriptionPosition, $mapPosition);
            self::assertSame(1, substr_count($source, '<div class="jem-venue-map-section">'));
            self::assertStringNotContainsString('$venueShowMapSection', $source);
        }

        foreach (array('jem.css', 'jem-responsive.css') as $stylesheet) {
            $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/' . $stylesheet);

            self::assertStringContainsString(
                'div#jem.jem_venue .jem-venue-overview-panel > .jem-venue-map-section',
                $css
            );
            self::assertStringContainsString('grid-column: 1 / -1;', $css);
        }
    }

    public function testVenuePdfUsesTheSamePolicyAndProvider(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/pdfview.class.php');

        self::assertStringContainsString('JemOutput::resolveVenueMapConfiguration(', $source);
        self::assertStringContainsString("JemHelper::isActiveMenuView('venue', (int) (\$venue->id ?? 0))", $source);
        self::assertStringContainsString("\$mapConfiguration['service'] !== 0", $source);
        self::assertStringContainsString("buildPdfMapLink(\$venue, \$mapConfiguration['provider'])", $source);
        self::assertStringNotContainsString("buildPdfMapLink(\$venue, 'osm')", $source);
    }
}
