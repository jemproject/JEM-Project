<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenuesListMapPopupTest extends TestCase
{
    public function testEachVenuesListLayoutRendersPaginationOnce(): void
    {
        $classic = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/tmpl/default.php')
            . (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/tmpl/default_venues.php');
        $responsive = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/tmpl/responsive/default.php')
            . (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/tmpl/responsive/default_venues.php');

        self::assertSame(1, substr_count($classic, 'getPagesLinks()'));
        self::assertSame(1, substr_count($responsive, 'getPagesLinks()'));
    }

    public function testMapFillsConfiguredPopupHeightInBothLayouts(): void
    {
        $templates = [
            JEM_TEST_ROOT . '/site/views/venueslist/tmpl/default_venues.php',
            JEM_TEST_ROOT . '/site/views/venueslist/tmpl/responsive/default_venues.php',
        ];

        foreach ($templates as $templatePath) {
            $template = (string) file_get_contents($templatePath);

            self::assertStringContainsString(
                'height: var(--jem-venueslist-map-height, 70vh);',
                $template
            );
            self::assertStringContainsString(
                '$modalRoot . \' style="--jem-venueslist-map-height:\' . $modalHeight . \'vh"\'',
                $template
            );
            self::assertStringContainsString("'height' => '100%'", $template);
            self::assertStringContainsString('min-height: 0;', $template);
            self::assertStringNotContainsString('min-height: 22rem;', $template);
            self::assertStringNotContainsString("'height' => \$modalHeight . 'vh'", $template);
        }
    }

    public function testVenueMapUsesVenueTypeIconThenConfiguredMarker(): void
    {
        $output = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        $javascript = (string) file_get_contents(JEM_TEST_ROOT . '/media/js/osm-map.js');

        self::assertStringContainsString("resolveMarkerUrl(\$marker, 'media/com_jem/images/marker-red.webp')", $output);
        self::assertStringContainsString('data-type-icon=', $output);
        self::assertStringNotContainsString('L.circleMarker(', $javascript);
        self::assertStringContainsString('element.dataset.typeIcon', $javascript);
        self::assertStringContainsString('iconUrl: element.dataset.marker', $javascript);

        foreach (array(
            JEM_TEST_ROOT . '/site/views/venueslist/tmpl/default_venues.php',
            JEM_TEST_ROOT . '/site/views/venueslist/tmpl/responsive/default_venues.php',
        ) as $templatePath) {
            $template = (string) file_get_contents($templatePath);
            self::assertStringContainsString("\$row->type_icon ?? ''", $template);
            self::assertStringContainsString("\$row->type_color ?? ''", $template);
            self::assertStringContainsString("get('venue_markerfile', 'media/com_jem/images/marker-red.webp')", $template);
        }
    }
}
