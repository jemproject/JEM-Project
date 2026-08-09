<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenueEventFilterTest extends TestCase
{
    public function testAllVenueMenuTypesExposeCompatibleEventFilters(): void
    {
        foreach (array('venueslist', 'typevenues', 'venues') as $view) {
            $xml = $this->read('site/views/' . $view . '/tmpl/default.xml');

            self::assertMatchesRegularExpression('/name="venue_event_filter"[^>]*default="0"/s', $xml, $view);
            self::assertStringContainsString('<option value="1">COM_JEM_VENUE_EVENT_FILTER_WITH_EVENTS</option>', $xml, $view);
            self::assertStringContainsString('<option value="2">COM_JEM_VENUE_EVENT_FILTER_WITHOUT_EVENTS</option>', $xml, $view);
            self::assertStringContainsString('name="show_venue_event_filter"', $xml, $view);
        }
    }

    public function testVenueQueriesApplyTheSharedVisibleEventCondition(): void
    {
        $helper = $this->read('site/helpers/helper.php');
        $listModel = $this->read('site/models/venueslist.php');
        $venuesModel = $this->read('site/models/venues.php');

        self::assertStringContainsString('function getVenueEventExistsWhere(', $helper);
        self::assertStringContainsString("getEventPublicationWhere('ve', false)", $helper);
        self::assertStringContainsString('#__jem_cats_event_relations AS vrel', $helper);
        self::assertStringContainsString('ve.access IN (', $helper);
        self::assertStringContainsString('vc.access IN (', $helper);
        self::assertStringContainsString("getVenueEventExistsWhere('a', \$eventMode === 1)", $listModel);
        self::assertStringContainsString("getVenueEventExistsWhere('l', \$eventMode === 1, \$eventState)", $venuesModel);
    }

    public function testFrontendOverrideCanShowAllVenuesAgain(): void
    {
        foreach (array(
            'site/views/venueslist/tmpl/default_venues.php',
            'site/views/venueslist/tmpl/responsive/default_venues.php',
            'site/views/venues/tmpl/default.php',
            'site/views/venues/tmpl/responsive/default.php',
        ) as $path) {
            $template = $this->read($path);
            self::assertStringContainsString('name="show_all_venues"', $template, $path);
            self::assertStringContainsString("Text::_('COM_JEM_SHOW_ALL_VENUES')", $template, $path);
        }
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
