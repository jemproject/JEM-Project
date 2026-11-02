<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Execute the checkout's actual helper with Joomla libraries and a fixed reference instant. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModuleHourOffsetTest extends TestCase
{
    private string $serverTimezone;

    /**
     * Provide valid decimal inputs and malformed persisted module parameters.
     *
     * @return iterable Configured hours and expected whole minutes.
     */
    public static function configuredOffsets(): iterable
    {
        foreach ([
            [0, 0], ['', 0], ['  ', 0], [null, 0], ['abc', 0],
            ['1.5', 90], ['1,5', 90], [-1.5, -90], [' -1,5 ', -90],
            ['+2', 120], ['.5', 30], [',5', 30], ['1.15', 69],
            ['0.01', 1], ['-0.01', -1], ['2abc', 0], ['1,2,3', 0],
            ['1e309', 0], [INF, 0], [NAN, 0], [[], 0], [true, 0],
            [new stdClass(), 0], [PHP_INT_MAX, 0], ['999999999999999999999999999999', 0],
        ] as $index => $case) {
            yield 'input ' . $index => $case;
        }
    }

    /**
     * Exercise production parsing without PHP warnings, implicit casts or overflow.
     *
     * @param mixed $hours Configured input.
     * @param int $expected Whole-minute result.
     * @return void
     */
    #[DataProvider('configuredOffsets')]
    public function testConfiguredOffsetParsing($hours, int $expected): void
    {
        self::assertSame($expected, JemHelper::normaliseHourOffsetMinutes($hours));
    }

    /**
     * Verify fractional hours around midnight, DST, and invalid legacy input.
     *
     * @return iterable Reference instant, raw input and expected local date.
     */
    public static function fractionalDates(): iterable
    {
        yield ['2026-10-29 22:45:00', '1,5', '2026-10-30'];
        yield ['2026-10-29 22:45:00', '1.5', '2026-10-30'];
        yield ['2026-10-29 01:15:00', '-1,5', '2026-10-28'];
        yield ['2026-03-29 00:15:00', '-0.5', '2026-03-28'];
        yield ['2026-03-29 01:45:00', '22', '2026-03-30'];
        yield ['2026-10-25 00:15:00', '24.5', '2026-10-25'];
        yield ['2026-10-29 22:45:00', 'abc', '2026-10-29'];
        yield ['2026-10-29 22:45:00', '2abc', '2026-10-29'];
        yield ['2026-10-29 22:45:00', [], '2026-10-29'];
    }

    /**
     * Execute the real date helper, preserving the supplied clock and Joomla timezone.
     *
     * @param string $reference Local reference time.
     * @param mixed $hours Configured input.
     * @param string $expected Local calendar date.
     * @return void
     */
    #[DataProvider('fractionalDates')]
    public function testFractionalToday(string $reference, $hours, string $expected): void
    {
        $clock = new DateTimeImmutable($reference, new DateTimeZone('Europe/Madrid'));
        self::assertSame($expected, JemHelper::getJoomlaDateWithHourOffset($hours, $clock));
    }

    /**
     * Load source through a read-only installation mapping, without booting an installed site.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->serverTimezone = date_default_timezone_get();
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT libraries; no site or database is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ROOT', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        Factory::$config = new Registry(['offset' => 'Europe/Madrid']);
        date_default_timezone_set('Pacific/Honolulu');
    }

    /**
     * Restore the process timezone after every scenario.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverTimezone);
    }

    /**
     * Supply explicit dates reached by elapsed hours, including 23/25-hour local days.
     *
     * @return iterable Named timezone, reference time, hour offset and expected date cases.
     */
    public static function hourOffsets(): iterable
    {
        yield 'zero' => ['Europe/Madrid', '2026-10-29 21:13:00', 0, '2026-10-29'];
        yield 'one hour crosses midnight' => ['Europe/Madrid', '2026-10-29 23:30:00', 1, '2026-10-30'];
        yield 'reported eleven hours' => ['Europe/Madrid', '2026-10-29 21:13:00', 11, '2026-10-30'];
        yield 'twelve hours stay today' => ['Europe/Madrid', '2026-10-29 01:00:00', 12, '2026-10-29'];
        yield 'twenty-three stay today' => ['Europe/Madrid', '2026-10-29 00:15:00', 23, '2026-10-29'];
        yield 'twenty-three cross midnight' => ['Europe/Madrid', '2026-10-29 21:13:00', 23, '2026-10-30'];
        yield 'ordinary twenty-four' => ['Europe/Madrid', '2026-10-29 21:13:00', 24, '2026-10-30'];
        yield 'forty-eight' => ['Europe/Madrid', '2026-10-29 21:13:00', 48, '2026-10-31'];
        yield 'negative one crosses midnight' => ['Europe/Madrid', '2026-10-29 00:15:00', -1, '2026-10-28'];
        yield 'negative eleven' => ['Europe/Madrid', '2026-10-29 01:00:00', -11, '2026-10-28'];
        yield 'negative twelve stay today' => ['Europe/Madrid', '2026-10-29 23:00:00', -12, '2026-10-29'];
        yield 'negative twenty-four' => ['Europe/Madrid', '2026-10-29 21:13:00', -24, '2026-10-28'];
        yield 'negative forty-eight' => ['Europe/Madrid', '2026-10-29 21:13:00', -48, '2026-10-27'];
        yield 'year boundary' => ['UTC', '2026-12-31 23:30:00', 1, '2027-01-01'];
        yield 'previous year' => ['UTC', '2027-01-01 00:30:00', -1, '2026-12-31'];
        yield 'leap day' => ['UTC', '2028-02-28 23:30:00', 1, '2028-02-29'];
        yield 'month boundary' => ['UTC', '2026-04-30 23:30:00', 1, '2026-05-01'];
        yield 'half-hour timezone' => ['Asia/Kolkata', '2026-10-29 23:15:00', 1, '2026-10-30'];
        yield 'quarter-hour timezone' => ['Asia/Kathmandu', '2026-10-29 23:15:00', 1, '2026-10-30'];
        yield 'UTC reference to Joomla date' => ['Europe/Madrid', '2026-10-29T23:30:00+00:00', 0, '2026-10-30'];
        yield 'spring elapsed day' => ['Europe/Madrid', '2026-03-28 23:30:00', 24, '2026-03-30'];
        yield 'autumn elapsed day' => ['Europe/Madrid', '2026-10-25 00:30:00', 24, '2026-10-25'];
        yield 'spring negative elapsed day' => ['Europe/Madrid', '2026-03-30 00:30:00', -24, '2026-03-28'];
        yield 'autumn negative elapsed day' => ['Europe/Madrid', '2026-10-25 23:30:00', -24, '2026-10-25'];
        yield 'spring missing hour' => ['Europe/Madrid', '2026-03-29 01:30:00', 1, '2026-03-29'];
        yield 'autumn repeated hour' => ['Europe/Madrid', '2026-10-25 01:30:00', 2, '2026-10-25'];
    }

    /**
     * Verify the real helper without changing the caller's clock or the server timezone.
     *
     * @param string $zone     Joomla timezone.
     * @param string $now      Reference instant, interpreted in that zone unless explicitly offset.
     * @param int    $hours    Signed elapsed hours.
     * @param string $expected Expected Joomla-local date.
     * @return void
     */
    #[DataProvider('hourOffsets')]
    public function testHourOffsetDate(string $zone, string $now, int $hours, string $expected): void
    {
        Factory::$config->set('offset', $zone);
        $clock = new DateTimeImmutable($now, new DateTimeZone($zone));
        $before = $clock->format('c');
        self::assertSame($expected, JemHelper::getJoomlaDateWithHourOffset($hours, $clock));
        self::assertSame($before, $clock->format('c'));
        self::assertSame('Pacific/Honolulu', date_default_timezone_get());
    }

    /**
     * A mutable injected instant is copied, not moved or converted in place.
     *
     * @return void
     */
    public function testMutableReferenceIsNotModified(): void
    {
        $clock = new DateTime('2026-10-29 21:13:00', new DateTimeZone('Europe/Madrid'));
        self::assertSame('2026-10-30', JemHelper::getJoomlaDateWithHourOffset(11, $clock));
        self::assertSame('2026-10-29T21:13:00+01:00', $clock->format('c'));
    }

    /**
     * Preserve the existing invalid-timezone fallback to UTC.
     *
     * @return void
     */
    public function testInvalidJoomlaTimezoneFallsBackToUtc(): void
    {
        Factory::$config->set('offset', 'Invalid/Zone');
        $clock = new DateTimeImmutable('2026-10-29T23:30:00+00:00');
        self::assertSame('2026-10-30', JemHelper::getJoomlaDateWithHourOffset(1, $clock));
    }

    /**
     * The default reference uses now, while the existing day-offset API remains calendar-based.
     *
     * @return void
     */
    public function testDefaultClockAndExistingDayHelper(): void
    {
        $zone = new DateTimeZone('Europe/Madrid');
        $before = new DateTimeImmutable('now', $zone);
        $current = JemHelper::getJoomlaDateWithHourOffset();
        $tomorrow = JemHelper::getJoomlaDate(1);
        $after = new DateTimeImmutable('now', $zone);
        self::assertContains($current, [$before->format('Y-m-d'), $after->format('Y-m-d')]);
        self::assertContains($tomorrow, [$before->modify('+1 day')->format('Y-m-d'), $after->modify('+1 day')->format('Y-m-d')]);
        self::assertSame('Pacific/Honolulu', date_default_timezone_get());
    }
}
