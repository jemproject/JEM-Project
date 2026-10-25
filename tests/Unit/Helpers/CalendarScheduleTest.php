<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/calendarschedule.class.php';
require_once JEM_TEST_ROOT . '/site/classes/pdfview.class.php';

final class CalendarScheduleTest extends TestCase
{
    private function event(): object
    {
        return (object) [
            'id' => 7,
            'dates' => '2026-11-05',
            'enddates' => '2026-11-07',
            'times' => '10:00:00',
            'endtimes' => '18:00:00',
            'attribs' => ['event_schedule' => ['version' => 1, 'mode' => 'per_day', 'days' => [
                ['offset' => 0, 'closed' => false, 'all_day' => false, 'slots' => [
                    ['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00'],
                ]],
                ['offset' => 1, 'closed' => true, 'all_day' => false, 'slots' => []],
                ['offset' => 2, 'closed' => false, 'all_day' => true, 'slots' => []],
            ]]],
        ];
    }

    public function testProjectsSlotsAndAllDayWithoutClosedDatesOrSourceMutation(): void
    {
        $event = $this->event();
        $rows = JemCalendarSchedule::projectPerDay($event, 'Europe/Madrid');

        self::assertCount(3, $rows);
        self::assertSame(['2026-11-05', '2026-11-05', '2026-11-07'], array_column($rows, 'dates'));
        self::assertSame(['09:00:00', '14:00:00', null], array_column($rows, 'times'));
        self::assertSame(['12:00:00', '17:00:00', null], array_column($rows, 'endtimes'));
        self::assertSame(['2026-11-05', '2026-11-05', '2026-11-07'], array_column($rows, 'enddates'));
        self::assertSame(['0-0', '0-1', '2-0'], array_column($rows, 'calendar_occurrence_key'));
        self::assertSame('2026-11-05', $event->dates);
        self::assertSame('2026-11-07', $event->enddates);
        self::assertSame('10:00:00', $event->times);
    }

    public function testClipsToVisiblePeriodAndCanReturnNoRows(): void
    {
        $event = $this->event();

        self::assertSame(['2026-11-07'], array_column(
            JemCalendarSchedule::projectPerDay($event, 'Europe/Madrid', '2026-11-06', '2026-11-07'),
            'dates'
        ));
        self::assertSame([], JemCalendarSchedule::projectPerDay(
            $event,
            'Europe/Madrid',
            '2026-11-06',
            '2026-11-06'
        ));
    }

    public function testPdfIdentityDistinguishesSlotsButStillDeduplicatesLegacyDays(): void
    {
        $rows = JemCalendarSchedule::projectPerDay($this->event(), 'Europe/Madrid');
        $identity = new ReflectionMethod(JemPdfView::class, 'getCalendarEventIdentity');

        self::assertNotSame(
            $identity->invoke(null, $rows[0], '2026-11-05'),
            $identity->invoke(null, $rows[1], '2026-11-05')
        );
        self::assertSame(
            $identity->invoke(null, (object) ['id' => 7, 'times' => '09:00:00'], '2026-11-05'),
            $identity->invoke(null, (object) ['id' => 7, 'times' => '14:00:00'], '2026-11-05')
        );
    }

    public function testStartDayOnlyUsesFirstActiveDayEvenWhenOriginalStartIsClosed(): void
    {
        $event = $this->event();
        $event->attribs['event_schedule']['days'][0] = [
            'offset' => 0, 'closed' => true, 'all_day' => false, 'slots' => [],
        ];
        $event->attribs['event_schedule']['days'][1] = [
            'offset' => 1, 'closed' => false, 'all_day' => false, 'slots' => [
                ['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00'],
            ],
        ];

        $rows = JemCalendarSchedule::projectPerDay($event, 'Europe/Madrid', '', '', true);
        self::assertSame(['2026-11-06', '2026-11-06'], array_column($rows, 'dates'));
        self::assertSame([], JemCalendarSchedule::projectPerDay(
            $event,
            'Europe/Madrid',
            '2026-11-07',
            '2026-11-07',
            true
        ));
    }

    public function testLocalClockTimesRemainStableAcrossDstAndPhpDefaultTimezone(): void
    {
        $event = $this->event();
        $event->dates = '2026-10-25';
        $event->enddates = '2026-10-27';
        $original = date_default_timezone_get();

        try {
            date_default_timezone_set('Pacific/Auckland');
            $rows = JemCalendarSchedule::projectPerDay($event, 'Europe/Madrid');
            self::assertSame('09:00:00', $rows[0]->times);
            self::assertSame('12:00:00', $rows[0]->endtimes);
            self::assertSame('2026-10-25', $rows[0]->dates);
        } finally {
            date_default_timezone_set($original);
        }
    }
}
