<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/venuemappolicy.class.php';

final class VenueMapPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, mixed, bool, array{display: string, service: int, provider: string}}>
     */
    public static function configurationProvider(): iterable
    {
        yield 'global disabled' => array('global', 0, true, array('display' => 'none', 'service' => 0, 'provider' => 'osm'));
        yield 'global Google link' => array('global', 1, true, array('display' => 'link_text', 'service' => 1, 'provider' => 'google'));
        yield 'global Google embed' => array('global', 2, true, array('display' => 'map', 'service' => 2, 'provider' => 'google'));
        yield 'global Google API' => array('global', 3, true, array('display' => 'map', 'service' => 3, 'provider' => 'google'));
        yield 'global OSM link' => array('global', 4, true, array('display' => 'link_text', 'service' => 4, 'provider' => 'osm'));
        yield 'global OSM embed' => array('global', 5, true, array('display' => 'map', 'service' => 5, 'provider' => 'osm'));
        yield 'explicit hide' => array('none', 2, true, array('display' => 'none', 'service' => 0, 'provider' => 'osm'));
        yield 'Google text override' => array('link_text', 2, true, array('display' => 'link_text', 'service' => 1, 'provider' => 'google'));
        yield 'OSM button override' => array('link_button', 5, true, array('display' => 'link_button', 'service' => 4, 'provider' => 'osm'));
        yield 'Google embed override' => array('map', 1, true, array('display' => 'map', 'service' => 2, 'provider' => 'google'));
        yield 'Google API override' => array('map', 3, true, array('display' => 'map', 'service' => 3, 'provider' => 'google'));
        yield 'OSM embed override' => array('map', 4, true, array('display' => 'map', 'service' => 5, 'provider' => 'osm'));
        yield 'explicit link fallback' => array('link_button', 0, true, array('display' => 'link_button', 'service' => 4, 'provider' => 'osm'));
        yield 'legacy hide alias' => array('hide', 2, true, array('display' => 'none', 'service' => 0, 'provider' => 'osm'));
        yield 'legacy link alias' => array('link', 2, true, array('display' => 'link_button', 'service' => 1, 'provider' => 'google'));
        yield 'unrelated menu ignored' => array('link_button', 0, false, array('display' => 'none', 'service' => 0, 'provider' => 'osm'));
        yield 'unknown display uses global' => array('invalid', 5, true, array('display' => 'map', 'service' => 5, 'provider' => 'osm'));
        yield 'unknown service is disabled' => array('global', 99, true, array('display' => 'none', 'service' => 0, 'provider' => 'osm'));
    }

    #[DataProvider('configurationProvider')]
    public function testVenueMapConfigurationIsResolved(
        mixed $display,
        mixed $globalMapService,
        bool $allowMenuOverride,
        array $expected
    ): void {
        self::assertSame(
            $expected,
            JemVenueMapPolicy::resolve($display, $globalMapService, $allowMenuOverride)
        );
    }
}
