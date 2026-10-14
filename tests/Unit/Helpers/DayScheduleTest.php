<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/dayschedule.class.php';

final class DayScheduleTest extends TestCase
{
    private function event(?string $mode = null): object
    {
        return (object) ['id' => 1, 'dates' => '2026-11-05', 'enddates' => '2026-11-07',
            'times' => '10:00:00', 'endtimes' => '18:00:00',
            'attribs' => $mode ? ['event_schedule' => ['version' => 1, 'mode' => $mode, 'days' => []]] : []];
    }

    #[DataProvider('dailyModes')]
    public function testDailyHoursAndLegacyStayDailyWithoutMutatingDates(?string $mode): void
    {
        $event = $this->event($mode);
        foreach (['2026-11-05', '2026-11-06', '2026-11-07'] as $day) {
            $row = JemDaySchedule::project($event, $day, 'Europe/Madrid')[0];
            self::assertSame('10:00 - 18:00', $row->day_schedule['label']);
            self::assertSame('2026-11-05', $row->dates);
            self::assertSame('2026-11-07', $row->enddates);
        }
        self::assertSame([], JemDaySchedule::project($event, '2026-11-08'));
        self::assertFalse(isset($event->day_schedule));
    }

    public static function dailyModes(): array
    {
        return [[null], ['daily']];
    }

    public function testContinuousClipsEachDayAndKeepsActualRange(): void
    {
        $event = $this->event('continuous');
        foreach (['2026-11-05' => '10:00 - 24:00', '2026-11-06' => '00:00 - 24:00',
            '2026-11-07' => '00:00 - 18:00'] as $day => $label) {
            $part = JemDaySchedule::project($event, $day, 'Europe/Madrid')[0]->day_schedule;
            self::assertSame($label, $part['label']);
            self::assertTrue($part['continuation']);
            self::assertSame('2026-11-05 10:00 - 2026-11-07 18:00 Europe/Madrid', $part['actual']);
        }
    }

    #[DataProvider('overnightModes')]
    public function testLegacyAndExplicitOvernightUseActualEnd(?string $mode): void
    {
        $event = $this->event($mode);
        $event->enddates = '2026-11-06';
        $event->times = '23:30:00';
        $event->endtimes = '01:30:00';
        self::assertSame('23:30 - 24:00', JemDaySchedule::project($event, '2026-11-05')[0]->day_schedule['label']);
        self::assertSame('00:00 - 01:30', JemDaySchedule::project($event, '2026-11-06')[0]->day_schedule['label']);
        $event->endtimes = '00:00:00';
        self::assertSame([], JemDaySchedule::project($event, '2026-11-06'));
    }

    public static function overnightModes(): array
    {
        return [[null], ['continuous']];
    }

    public function testProgrammeSessionsClosedAndAllDay(): void
    {
        $event = $this->event('per_day');
        $event->attribs['event_schedule']['days'] = [
            ['offset' => 0, 'closed' => false, 'all_day' => false, 'slots' => [
                ['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00']]],
            ['offset' => 1, 'closed' => true, 'all_day' => false, 'slots' => []],
            ['offset' => 2, 'closed' => false, 'all_day' => true, 'slots' => []],
        ];
        $sessions = JemDaySchedule::project($event, '2026-11-05');
        self::assertCount(2, $sessions);
        self::assertSame(['09:00 - 12:00', '14:00 - 17:00'], array_column(array_column($sessions, 'day_schedule'), 'label'));
        self::assertSame([], JemDaySchedule::project($event, '2026-11-06'));
        self::assertTrue(JemDaySchedule::project($event, '2026-11-07')[0]->day_schedule['all_day']);
    }

    public function testUnknownEndAndPointHaveVisualSpaceWithoutInventedLabels(): void
    {
        $event = $this->event('daily');
        $event->times = '23:45:00';
        $event->endtimes = null;
        $part = JemDaySchedule::project($event, '2026-11-05')[0]->day_schedule;
        self::assertSame('23:45', $part['label']);
        self::assertSame('', $part['end_label']);
        self::assertSame(strtotime('2026-11-06 UTC'), $part['end']);
        $event->endtimes = '23:45:00';
        $point = JemDaySchedule::project($event, '2026-11-05')[0]->day_schedule;
        self::assertSame('23:45', $point['label']);
        self::assertGreaterThan($point['start'], $point['end']);
    }

    public function testClockCoordinatesAreIndependentOfPhpTimezoneAcrossDst(): void
    {
        $original = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Auckland');
            $event = $this->event('continuous');
            $event->dates = '2026-10-24';
            $event->enddates = '2026-10-26';
            $part = JemDaySchedule::project($event, '2026-10-25', 'Europe/Madrid')[0]->day_schedule;
            self::assertSame('00:00 - 24:00', $part['label']);
            self::assertSame(86400, $part['end'] - $part['start']);
            self::assertSame('00:00', gmdate('H:i', $part['start']));
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testLanesUseFinalRenderedExtentsAndReuseFreeLanes(): void
    {
        $items = [];
        foreach ([[0, 10], [0, 30], [8, 5], [13, 3], [30, 8]] as $id => $position) {
            $row = (object) ['id' => $id, 'day_schedule' => ['key' => '0-0']];
            $items[] = [$row, 0, 0, 'position' => $position];
        }
        $result = JemDaySchedule::lanes($items);
        self::assertSame(3, $result['count']);
        foreach ($result['items'] as $a => $left) {
            foreach (array_slice($result['items'], $a + 1) as $right) {
                if ($left['position'][2] === $right['position'][2]) {
                    self::assertLessThanOrEqual($right['position'][0], $left['position'][0] + $left['position'][1]);
                }
            }
        }
        self::assertSame($result, JemDaySchedule::lanes(array_reverse($items)));
    }
}
