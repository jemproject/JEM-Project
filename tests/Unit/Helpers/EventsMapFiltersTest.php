<?php
declare(strict_types=1);

namespace JemProject\Tests\Unit\Helpers\EventsMapFilters;

use Joomla\Component\Jem\Site\Helper\JemMapDateHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Civil-date navigation and configured/visible location filter contracts. */
final class EventsMapFiltersTest extends TestCase
{
    /** Load the pure resolver and isolate only the location-list database boundary. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/site/helpers/mapdate.php';
        require_once JEM_TEST_ROOT . '/site/classes/filterinput.class.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/site/helpers/map.php');
        $start = strpos($source, '    public static function getEventLocationFilters(');
        $end = strpos($source, '    /**', $start);
        $method = substr($source, $start, $end - $start);
        $method = str_replace('self::getEventLocations($country)', 'LocationFixture::get($country)', $method);
        $start = strpos($source, '    public static function getVenueMapFilters(');
        $end = strpos($source, '    /**', $start);
        $venueMethod = substr($source, $start, $end - $start);
        $method .= str_replace(['self::getVenueCountries()', 'self::getCategories($params)'],
            ['[(object) ["country" => "ES"]]', '[(object) ["id" => 1]]'], $venueMethod);
        eval('namespace ' . __NAMESPACE__ . '; class LocationResolver {' . $method . '}');
    }

    /** @return iterable Named mode/date/step/weekday cases and expected range/mode. */
    public static function periods(): iterable
    {
        yield 'all' => ['all', '2028-02-29', 1, 1, '', null, 'all'];
        yield 'unknown' => ['invalid', '', 0, 1, '', null, 'all'];
        yield 'today ignores stale field' => ['today', '2020-01-01', 0, 1, '2026-11-06', '2026-11-06', 'today'];
        yield 'tomorrow preset displayed as day' => ['tomorrow', '', 0, 1, '2026-11-07', '2026-11-07', 'date'];
        yield 'today previous' => ['today', '', -1, 1, '2026-11-05', '2026-11-05', 'date'];
        yield 'tomorrow preset ignores stale date' => ['tomorrow', '2026-11-20', 0, 1, '2026-11-07', '2026-11-07', 'date'];
        yield 'tomorrow next day' => ['tomorrow', '', 1, 1, '2026-11-08', '2026-11-08', 'date'];
        yield 'default date' => ['date', '', 0, 1, '2026-11-06', '2026-11-06', 'date'];
        yield 'Monday week' => ['week', '', 0, 1, '2026-11-02', '2026-11-08', 'week'];
        yield 'Sunday week' => ['week', '', 0, 0, '2026-11-01', '2026-11-07', 'week'];
        yield 'next week' => ['week', '2026-11-02', 1, 1, '2026-11-09', '2026-11-15', 'week'];
        yield 'previous week crosses year' => ['week', '2027-01-04', -1, 1, '2026-12-28', '2027-01-03', 'week'];
        yield 'month default' => ['month', '', 0, 1, '2026-11-01', '2026-11-30', 'month'];
        yield 'January 31 next month' => ['month', '2028-01-31', 1, 1, '2028-02-01', '2028-02-29', 'month'];
        yield 'March previous month' => ['month', '2026-03-31', -1, 1, '2026-02-01', '2026-02-28', 'month'];
        yield 'December next month' => ['month', '2026-12-01', 1, 1, '2027-01-01', '2027-01-31', 'month'];
        yield 'year default' => ['year', '', 0, 1, '2026-01-01', '2026-12-31', 'year'];
        yield 'next year' => ['year', '2028-02-29', 1, 1, '2029-01-01', '2029-12-31', 'year'];
        yield 'day upper boundary' => ['date', '9999-12-31', 1, 1, '9999-12-31', '9999-12-31', 'date'];
        yield 'day lower boundary' => ['date', '0001-01-01', -1, 1, '0001-01-01', '0001-01-01', 'date'];
        yield 'leap day next' => ['date', '2028-02-28', 1, 1, '2028-02-29', '2028-02-29', 'date'];
        yield 'DST spring' => ['date', '2026-03-29', 1, 1, '2026-03-30', '2026-03-30', 'date'];
        yield 'DST autumn' => ['date', '2026-10-25', -1, 1, '2026-10-24', '2026-10-24', 'date'];
        yield 'bad step' => ['date', '2026-11-06', 500, 1, '2026-11-06', '2026-11-06', 'date'];
        foreach (['2026-02-29', '2026-13-01', '2026-11-31', '0000-01-01', 'not-a-date', "2026-11-06\0"] as $date) {
            yield 'invalid ' . bin2hex($date) => ['date', $date, 0, 1, '2026-11-06', '2026-11-06', 'date'];
        }
    }

    /**
     * Exercise real resolver including rollover, leap years, DST and invalid requests.
     * @param string $mode Selected mode. @param string $date Input date.
     * @param int $step Navigation step. @param int $weekStart Configured first weekday.
     * @param string $start Expected displayed date. @param ?string $end Expected inclusive end.
     * @param string $effectiveMode Expected active mode. @return void
     */
    #[DataProvider('periods')]
    public function testPeriods(string $mode, string $date, int $step, int $weekStart, string $start, ?string $end, string $effectiveMode): void
    {
        $now = new \DateTimeImmutable('2026-11-06 12:30:00', new \DateTimeZone('Europe/Madrid'));
        $period = JemMapDateHelper::resolve($mode, $date, $now, $weekStart, $step);
        self::assertSame($start, $period['date']);
        self::assertSame($start === '' ? null : $start, $period['start']);
        self::assertSame($end, $period['end']);
        self::assertSame($effectiveMode, $period['mode']);
    }

    /** Midnight must follow Joomla timezone, not the browser or PHP default timezone. */
    public function testSiteTimezone(): void
    {
        $instant = new \DateTimeImmutable('2026-11-06 23:30:00', new \DateTimeZone('UTC'));
        self::assertSame('2026-11-07', JemMapDateHelper::resolve('today', '', $instant->setTimezone(new \DateTimeZone('Europe/Madrid')))['date']);
        self::assertSame('2026-11-06', JemMapDateHelper::resolve('today', '', $instant->setTimezone(new \DateTimeZone('America/New_York')))['date']);
    }

    /** @return iterable Larger jumps with expected complete ranges and active modes. */
    public static function jumps(): iterable
    {
        yield 'today forward' => ['today', '2020-01-01', 1, '2026-11-13', '2026-11-13', 'date'];
        yield 'today backward' => ['today', '', -1, '2026-10-30', '2026-10-30', 'date'];
        yield 'day forward through leap day' => ['date', '2028-02-25', 1, '2028-03-03', '2028-03-03', 'date'];
        yield 'day backward across year' => ['date', '2027-01-04', -1, '2026-12-28', '2026-12-28', 'date'];
        yield 'week forward month' => ['week', '2026-11-09', 1, '2026-12-07', '2026-12-13', 'week'];
        yield 'week backward month' => ['week', '2026-11-09', -1, '2026-10-05', '2026-10-11', 'week'];
        yield 'week clamp February' => ['week', '2028-01-31', 1, '2028-02-28', '2028-03-05', 'week'];
        yield 'month forward from leap February' => ['month', '2028-02-29', 1, '2029-02-01', '2029-02-28', 'month'];
        yield 'month backward to leap February' => ['month', '2029-02-28', -1, '2028-02-01', '2028-02-29', 'month'];
        yield 'year has no larger jump' => ['year', '2028-02-29', 1, '2028-01-01', '2028-12-31', 'year'];
        yield 'invalid jump ignored' => ['date', '2026-11-09', 100, '2026-11-09', '2026-11-09', 'date'];
        yield 'all remains unrestricted' => ['all', '2026-11-09', 1, null, null, 'all'];
        yield 'month upper boundary' => ['month', '9999-12-01', 1, '9999-12-01', '9999-12-31', 'month'];
        yield 'week upper boundary' => ['week', '9999-12-31', 1, '9999-12-27', '9999-12-31', 'week'];
    }

    /**
     * Verify double-arrow jumps retain complete periods, timezone and mode.
     * @param string $mode Selected mode. @param string $date Selected date.
     * @param int $jump Navigation direction. @param ?string $start Expected start.
     * @param ?string $end Expected end. @param string $active Expected mode.
     * @return void
     */
    #[DataProvider('jumps')]
    public function testLargerNavigation(string $mode, string $date, int $jump, ?string $start, ?string $end, string $active): void
    {
        $now = new \DateTimeImmutable('2026-11-06', new \DateTimeZone('Europe/Madrid'));
        $result = JemMapDateHelper::resolve($mode, $date, $now, 1, 0, $jump);
        self::assertSame($start, $result['start']);
        self::assertSame($end, $result['end']);
        self::assertSame($active, $result['mode']);
    }

    /** Hidden defaults cannot be cleared or replaced through request parameters. */
    public function testHiddenLocationDefaults(): void
    {
        $params = new Values(['default_county' => 'Region A', 'default_city' => 'City A']);
        $input = new Values(['jem_map_filter_county' => 'Injected', 'jem_map_filter_city' => '']);
        LocationFixture::$calls = [];
        $result = LocationResolver::getEventLocationFilters($params, $input, 'ES');
        self::assertSame('Region A', $result['county']);
        self::assertSame('City A', $result['city']);
        self::assertSame([], LocationFixture::$calls);
        self::assertSame([], $input->reads);
    }

    /** Visible filters inherit defaults, allow explicit clearing and narrow cities by region. */
    public function testVisibleLocationsAndIndependentOptions(): void
    {
        $params = new Values(['show_county_filter' => 1, 'show_city_filter' => 1,
            'default_county' => 'Region A', 'default_city' => 'City A']);
        $result = LocationResolver::getEventLocationFilters($params, new Values([]), 'ES');
        self::assertSame(['City A'], array_values($result['city_options']));
        self::assertSame(['Region A', 'Region B'], array_values($result['county_options']));
        self::assertSame('City A', $result['city']);
        $result = LocationResolver::getEventLocationFilters($params, new Values(['jem_map_filter_county' => '', 'jem_map_filter_city' => '']), 'ES');
        self::assertSame('', $result['county']);
        self::assertSame('', $result['city']);
        self::assertSame(['City A', 'City B'], array_values($result['city_options']));
        $params = new Values(['show_city_filter' => 1, 'default_county' => 'Region B']);
        $result = LocationResolver::getEventLocationFilters($params, new Values(['jem_map_filter_county' => 'Region A']), 'ES');
        self::assertSame('Region B', $result['county']);
        self::assertSame(['City B'], array_values($result['city_options']));
        self::assertSame('ES', end(LocationFixture::$calls));
    }

    /** A hidden country/county/city default remains authoritative for every output. */
    public function testVenueHiddenDefaultsAndUnknownCountryDoNotWidenResults(): void
    {
        $result = LocationResolver::getVenueMapFilters(new Values([
            'show_country_filter' => 0, 'show_city_filter' => 0,
            'default_country' => 'CH', 'default_county' => 'Region A', 'default_city' => 'City A',
        ]), new Values(['jem_map_filter_country' => 'ES', 'jem_map_filter_county' => 'Region B',
            'jem_map_filter_city' => 'City B', 'jem_map_filter_catid' => 1]));
        self::assertSame('CH', $result['country']);
        self::assertSame('Region A', $result['county']);
        self::assertSame('City A', $result['city']);
        self::assertSame(0, $result['category']);
        self::assertSame(['ES', 'CH'], array_column($result['countries'], 'country'));
    }

    /** Old menus keep City visible; explicit options can independently disable it. */
    public function testVenueIndependentSelectorsAndCategoryValidation(): void
    {
        $result = LocationResolver::getVenueMapFilters(new Values([]), new Values([]));
        self::assertTrue($result['show_city']);
        $result = LocationResolver::getVenueMapFilters(new Values(['show_city_filter' => 0,
            'show_county_filter' => 1, 'show_category_filter' => 1]),
            new Values(['jem_map_filter_county' => 'Region B', 'jem_map_filter_city' => 'City A',
                'jem_map_filter_catid' => 999]));
        self::assertSame('Region B', $result['county']);
        self::assertSame('', $result['city']);
        self::assertSame(0, $result['category']);
        self::assertFalse($result['show_city']);
        $result = LocationResolver::getVenueMapFilters(new Values(['show_category_filter' => 1]),
            new Values(['jem_map_filter_catid' => 1]));
        self::assertSame(1, $result['category']);
    }

    /** Structured requests fall back to configured defaults; valid scalars remain usable. */
    public function testStructuredLocationFiltersPreserveDefaults(): void
    {
        foreach (['county', 'city', 'region'] as $field) {
            $params = new Values(['show_' . $field . '_filter' => 1, 'default_' . $field => 'Configured']);
            foreach ([['Madrid'], ['nested' => ['Madrid']], new \stdClass()] as $value) {
                $result = LocationResolver::getEventLocationFilters($params, new Values(['jem_map_filter_' . $field => $value]), 'ES');
                self::assertSame('Configured', $result[$field]);
            }
            foreach (['Madrid', '', 'Unknown city', '0'] as $value) {
                $result = LocationResolver::getEventLocationFilters($params, new Values(['jem_map_filter_' . $field => $value]), 'ES');
                self::assertSame($value, $result[$field]);
            }
        }
    }

    /** The module uses the same scalar reader and keeps hidden defaults authoritative. */
    public function testMapModuleCountryInput(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_map/mod_jem_map.php');
        $start = strpos($source, '$selectedCountry = $showCountryFilter');
        $code = substr($source, $start, strpos($source, ';', $start) - $start + 1);
        $defaultCountry = 'ES';
        foreach ([true, false] as $showCountryFilter) {
            foreach ([['DE'], ['nested' => ['DE']], 'DE', ''] as $value) {
                $app = (object) ['input' => new Values(['jem_map_filter_country' => $value])];
                eval($code);
                self::assertSame($showCountryFilter && is_string($value) ? $value : 'ES', $selectedCountry);
            }
        }
    }

    /** Invalid country requests keep the configured Venues Map scope. */
    public function testVenueCountryArrayKeepsDefault(): void
    {
        $result = LocationResolver::getVenueMapFilters(new Values(['show_country_filter' => 1, 'default_country' => 'ES']),
            new Values(['jem_map_filter_country' => ['DE']]));
        self::assertSame('ES', $result['country']);
    }
}

/** Minimal parameter/input boundary. */
final class Values
{
    public array $reads = [];
    public function __construct(private array $values) {}
    public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
    public function getString(string $key, string $default = ''): string
    {
        $this->reads[] = $key;
        return (string) $this->get($key, $default);
    }
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
}

/** Database boundary only; selection logic remains production code. */
final class LocationFixture
{
    public static array $calls = [];
    public static function get(string $country): array
    {
        self::$calls[] = $country;
        return [(object) ['state' => 'Region A', 'city' => 'City A'],
            (object) ['state' => 'Region B', 'city' => 'City B'],
            (object) ['state' => 'Region A', 'city' => 'City A']];
    }
}
