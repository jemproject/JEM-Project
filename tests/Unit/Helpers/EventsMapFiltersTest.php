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
        eval('namespace ' . __NAMESPACE__ . '; class LocationResolver {' . $method . '}');
    }

    /** @return iterable Named mode/date/step/weekday cases and expected range/mode. */
    public static function periods(): iterable
    {
        yield 'all' => ['all', '2028-02-29', 1, 1, '', null, 'all'];
        yield 'unknown' => ['invalid', '', 0, 1, '', null, 'all'];
        yield 'today ignores stale field' => ['today', '2020-01-01', 0, 1, '2026-11-06', '2026-11-06', 'today'];
        yield 'tomorrow' => ['tomorrow', '', 0, 1, '2026-11-07', '2026-11-07', 'tomorrow'];
        yield 'today previous' => ['today', '', -1, 1, '2026-11-05', '2026-11-05', 'date'];
        yield 'tomorrow next' => ['tomorrow', '', 1, 1, '2026-11-08', '2026-11-08', 'date'];
        yield 'default date' => ['date', '', 0, 1, '2026-11-06', '2026-11-06', 'date'];
        yield 'Monday week' => ['week', '', 0, 1, '2026-11-02', '2026-11-08', 'week'];
        yield 'Sunday week' => ['week', '', 0, 0, '2026-11-01', '2026-11-07', 'week'];
        yield 'next week' => ['week', '2026-11-02', 1, 1, '2026-11-09', '2026-11-15', 'week'];
        yield 'previous week crossing year' => ['week', '2027-01-04', -1, 1, '2026-12-28', '2027-01-03', 'week'];
        yield 'month default' => ['month', '', 0, 1, '2026-11-01', '2026-11-30', 'month'];
        yield 'January 31 next month' => ['month', '2028-01-31', 1, 1, '2028-02-01', '2028-02-29', 'month'];
        yield 'March previous month' => ['month', '2026-03-31', -1, 1, '2026-02-01', '2026-02-28', 'month'];
        yield 'December next' => ['month', '2026-12-01', 1, 1, '2027-01-01', '2027-01-31', 'month'];
        yield 'year default' => ['year', '', 0, 1, '2026-01-01', '2026-12-31', 'year'];
        yield 'leap year next' => ['year', '2028-02-29', 1, 1, '2029-01-01', '2029-12-31', 'year'];
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

    /** Structured requests fall back to configured defaults; valid scalars remain usable. */
    public function testStructuredLocationFiltersPreserveDefaults(): void
    {
        foreach (['county', 'city'] as $field) {
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
