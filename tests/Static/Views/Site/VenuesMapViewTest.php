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
}
