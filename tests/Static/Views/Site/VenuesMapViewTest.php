<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenuesMapViewTest extends TestCase
{
    public function testVenuesMapTemplateKeepsVisibleCanvasAndLeafletFallback(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venuesmap/tmpl/default.php');

        self::assertStringContainsString('class="jem-venuesmap-canvas"', $template);
        self::assertStringContainsString('min-height:300px', $template);
        self::assertStringContainsString("if (typeof L === 'undefined')", $template);
        self::assertStringContainsString("Text::_('COM_JEM_VENUESMAP_MAP_UNAVAILABLE')", $template);
        self::assertStringContainsString('map.invalidateSize();', $template);
        self::assertStringContainsString("typeof L.heatLayer === 'function' && heatPoints.length", $template);
    }

    public function testVenuesMapLanguageHasLeafletFallbackMessage(): void
    {
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini');

        self::assertStringContainsString('COM_JEM_VENUESMAP_MAP_UNAVAILABLE=', $language);
    }

    public function testVenueButtonsUseSharedContrastColorForAllBootstrapStates(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venuesmap/tmpl/default.php');

        self::assertStringContainsString('JemHelper::getContrastTextColor($color)', $template);
        self::assertStringContainsString('--bs-btn-color:', $template);
        self::assertStringContainsString('--bs-btn-hover-color:', $template);
        self::assertStringContainsString('--bs-btn-active-color:', $template);
        self::assertStringNotContainsString('function jem_venuesmap_contrast_color', $template);
    }

    public function testVenueTypeIconUsesTheIndividualVenueColorOnTheMap(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/site/helpers/map.php');
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venuesmap/tmpl/default.php');
        $moduleTemplate = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_map/tmpl/default.php');

        self::assertStringContainsString('vt.icon AS venue_type_icon', $helper);
        self::assertStringContainsString('vt.color AS venue_type_color', $helper);
        self::assertStringContainsString("quoteName('vt.entity') . ' = 3'", $helper);
        self::assertStringContainsString('getLeafletVenueTypeMarker', $template);
        self::assertStringContainsString('getGoogleVenueTypeMarker', $template);
        self::assertStringContainsString("jem_venuesmap_normalise_color(\$v->color ?? '', \$venueTypeColor)", $template);
        self::assertStringContainsString('getLeafletVenueTypeMarker', $moduleTemplate);
        self::assertStringContainsString('getGoogleVenueTypeMarker', $moduleTemplate);
        self::assertStringContainsString("jem_map_normalise_marker_color(\$v->color ?? '', \$venueTypeColor)", $moduleTemplate);
    }

    public function testCountryFlagsUseTheConfiguredExistingAsset(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venuesmap/tmpl/default.php');

        self::assertStringContainsString('function jem_venuesmap_country_flag_html($code)', $template);
        self::assertStringContainsString('$settings->flagicons_path', $template);
        self::assertStringContainsString("is_file(JPATH_SITE . '/' . \$flag)", $template);
        self::assertStringContainsString('$countryLine', $template);
        self::assertStringNotContainsString('flags/w20-png', $template);
    }
}
