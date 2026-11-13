<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/helpers/calendarnavigation.php';

/** Exercise civil-date limits independently of the database and server timezone. */
final class CalendarNavigationTest extends TestCase
{
    /** @return iterable Named empty/visible/start-only/invalid-date boundary cases. */
    public static function windows(): iterable
    {
        yield 'empty default' => [null, null, 3, '2026-10-01', '2026-12-31'];
        yield 'empty one month' => [null, null, 1, '2026-11-01', '2026-11-30'];
        yield 'empty even window' => [null, null, 12, '2026-06-01', '2027-05-31'];
        yield 'events only one month' => ['2027-01-12', '2027-01-12', 12, '2027-01-01', '2027-01-31'];
        yield 'past events do not add today' => ['2024-02-03', '2024-02-29', 3, '2024-02-01', '2024-02-29'];
        yield 'event range with empty gaps' => ['2026-09-15', '2027-03-01', 3, '2026-09-01', '2027-03-31'];
        yield 'multi day end included' => ['2026-11-30', '2026-12-02', 3, '2026-11-01', '2026-12-31'];
        yield 'invalid database date' => ['0000-00-00', '0000-00-00', 3, '2026-10-01', '2026-12-31'];
        yield 'invalid negative option' => [null, null, -100, '2026-11-01', '2026-11-30'];
    }

    /** @return void Match exact inclusive months, not a fixed margin around actual events. */
    #[DataProvider('windows')]
    public function testWindow(?string $first, ?string $last, int $months, string $start, string $end): void
    {
        self::assertSame(['start' => $start, 'end' => $end], JemCalendarNavigation::bounds($first, $last, '2026-11-13', $months));
    }

    /** @return void The default remains centred when crossing year boundaries. */
    public function testEmptyJanuaryAndDecember(): void
    {
        self::assertSame(['start' => '2025-12-01', 'end' => '2026-02-28'], JemCalendarNavigation::bounds(null, null, '2026-01-01'));
        self::assertSame(['start' => '2026-11-01', 'end' => '2027-01-31'], JemCalendarNavigation::bounds(null, null, '2026-12-31'));
    }

    /** @return void Weeks/annual periods may overlap a boundary, but disjoint or malformed dates cannot. */
    public function testPeriodIntersectionAndDisabledDestinations(): void
    {
        $range = ['start' => '2026-11-01', 'end' => '2026-11-30'];
        JemCalendarNavigation::setRange($range);
        self::assertTrue(JemCalendarNavigation::allows('2026-11-01'));
        self::assertTrue(JemCalendarNavigation::allows('2026-11-30'));
        self::assertTrue(JemCalendarNavigation::allows('2026-10-26', '2026-11-01'));
        self::assertTrue(JemCalendarNavigation::allows('2026-01-01', '2026-12-31'));
        self::assertFalse(JemCalendarNavigation::allows('2026-10-31'));
        self::assertFalse(JemCalendarNavigation::allows('2026-12-01'));
        self::assertFalse(JemCalendarNavigation::allows('2026-11-31'));
        self::assertFalse(JemCalendarNavigation::allows('2026-11-15 garbage'));
        self::assertFalse(JemCalendarNavigation::allows('2026-11-20', '2026-11-10'));
        self::assertFalse(JemCalendarNavigation::allows('1973-01-01'));
    }
}
