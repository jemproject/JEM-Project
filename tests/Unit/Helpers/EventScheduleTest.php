<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/eventschedule.class.php';
require_once JEM_TEST_ROOT . '/site/classes/eventseries.class.php';
require_once JEM_TEST_ROOT . '/site/classes/recurrencevalidator.class.php';

final class EventScheduleTest extends TestCase
{
    private static function event(string $mode = 'daily', array $days = []): object
    {
        return (object) ['dates' => '2026-10-15', 'enddates' => '2026-10-17', 'times' => '10:00:00',
            'endtimes' => '18:00:00', 'attribs' => ['other' => 'keep',
                'event_schedule' => ['version' => 1, 'mode' => $mode, 'days' => $days]]];
    }

    private static function day(int $offset, array $slots = [], bool $closed = false, bool $allDay = false): array
    {
        return ['offset' => $offset, 'closed' => $closed, 'all_day' => $allDay, 'slots' => $slots];
    }

    public function testDailyAndContinuousAreDifferentIntervals(): void
    {
        $daily = JemEventSchedule::intervals(self::event(), 'Europe/Madrid');
        self::assertCount(3, $daily);
        self::assertSame('2026-10-16 10:00:00+02:00', $daily[1]['start']->format('Y-m-d H:i:sP'));
        self::assertSame('2026-10-16 18:00:00', $daily[1]['end']->format('Y-m-d H:i:s'));
        $continuous = JemEventSchedule::intervals(self::event('continuous'), 'Europe/Madrid');
        self::assertCount(1, $continuous);
        self::assertSame('2026-10-15 10:00:00', $continuous[0]['start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-17 18:00:00', $continuous[0]['end']->format('Y-m-d H:i:s'));
    }

    public function testPerDaySortsSessionsAndSkipsClosedDays(): void
    {
        $event = self::event('per_day', [
            self::day(2, [], false, true), self::day(1, [], true),
            self::day(0, [['start' => '15:00', 'end' => '18:00'], ['start' => '09:00', 'end' => '12:00']]),
        ]);
        $sessions = JemEventSchedule::intervals($event, 'Europe/Madrid');
        self::assertCount(3, $sessions);
        self::assertSame('09:00:00', $sessions[0]['start']->format('H:i:s'));
        self::assertSame('12:00:00', $sessions[0]['end']->format('H:i:s'));
        self::assertTrue($sessions[2]['all_day']);
        self::assertSame('2026-10-18 00:00:00', $sessions[2]['end']->format('Y-m-d H:i:s'));
        self::assertSame([], JemEventSchedule::onDay($event, '2026-10-16'));
        JemEventSchedule::synchroniseEnvelope($event);
        self::assertSame('09:00:00', $event->times);
        self::assertNull($event->endtimes);
    }

    public function testDayClippingDoesNotChangeActualInterval(): void
    {
        $event = self::event('continuous');
        $middle = JemEventSchedule::onDay($event, '2026-10-16')[0];
        self::assertSame('2026-10-15 10:00:00', $middle['start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-16 00:00:00', $middle['visible_start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-17 00:00:00', $middle['visible_end']->format('Y-m-d H:i:s'));
        self::assertSame([], JemEventSchedule::onDay($event, '2026-10-18'));
        $event->endtimes = '00:00:00';
        self::assertSame([], JemEventSchedule::onDay($event, '2026-10-17'));
    }

    public function testContinuousOvernightKeepsMidnightEnd(): void
    {
        $event = self::event('continuous');
        $event->enddates = '2026-10-16';
        $event->times = '22:00';
        $event->endtimes = '00:00';
        $interval = JemEventSchedule::intervals($event)[0];
        self::assertSame(7200, $interval['end']->getTimestamp() - $interval['start']->getTimestamp());
    }

    public function testPointEventAndUnknownEndAreNotInventedDurations(): void
    {
        $event = self::event();
        $event->enddates = null;
        $event->endtimes = null;
        self::assertFalse(JemEventSchedule::intervals($event)[0]['end_known']);
        $event->endtimes = $event->times;
        $point = JemEventSchedule::onDay($event, $event->dates);
        self::assertCount(1, $point);
        self::assertEquals($point[0]['start'], $point[0]['end']);
        $event->times = $event->endtimes = null;
        self::assertTrue(JemEventSchedule::intervals($event)[0]['all_day']);
    }

    #[DataProvider('dstDates')]
    public function testDailyWallClockAcrossDst(string $from, string $to, int $elapsed): void
    {
        $event = self::event();
        $event->dates = $from;
        $event->enddates = $to;
        $sessions = JemEventSchedule::intervals($event, 'Europe/Madrid');
        self::assertCount(3, $sessions);
        foreach ($sessions as $session) {
            self::assertSame('10:00:00', $session['start']->format('H:i:s'));
        }
        self::assertSame($elapsed, $sessions[2]['start']->getTimestamp() - $sessions[0]['start']->getTimestamp());
    }

    public static function dstDates(): array
    {
        return [['2026-03-28', '2026-03-30', 47 * 3600], ['2026-10-24', '2026-10-26', 49 * 3600]];
    }

    public function testNonexistentDstTimeIsRejected(): void
    {
        $event = self::event();
        $event->dates = $event->enddates = '2026-03-29';
        $event->times = '02:30';
        $this->expectExceptionMessage('COM_JEM_SCHEDULE_INVALID_LOCAL_TIME');
        JemEventSchedule::intervals($event, 'Europe/Madrid');
    }

    public function testOpenDatesAndOutsideWindows(): void
    {
        $event = self::event();
        self::assertSame([], JemEventSchedule::intervals($event, 'UTC', '2100-01-01', '2100-01-02'));
        self::assertCount(1, JemEventSchedule::intervals($event, 'UTC', '2026-10-16', '2026-10-16'));
        $event->dates = null;
        JemEventSchedule::validate($event);
        self::assertSame([], JemEventSchedule::intervals($event));
    }

    public function testLegacyAndOmittedFormsPreserveCompatibility(): void
    {
        $event = self::event();
        $oldForm = ['dates' => $event->dates, 'enddates' => $event->enddates, 'attribs' => ['other' => 'unchanged']];
        JemEventSchedule::mergeSubmission($oldForm, $event);
        self::assertSame(JemEventSchedule::definition($event), $oldForm['attribs']['event_schedule']);
        self::assertSame('unchanged', $oldForm['attribs']['other']);
        $legacy = (object) ['attribs' => '{}'];
        $form = ['event_schedule' => '', 'attribs' => ['other' => 'keep']];
        JemEventSchedule::mergeSubmission($form, $legacy);
        self::assertNull(JemEventSchedule::definition((object) $form));
        self::assertSame(['other' => 'keep'], $form['attribs']);
        self::assertArrayNotHasKey('event_schedule', $form);
    }

    public function testExplicitSubmissionAndSerializationKeepOtherParameters(): void
    {
        $event = (array) self::event();
        $event['event_schedule'] = '{"version":1,"mode":"continuous","days":[]}';
        JemEventSchedule::mergeSubmission($event);
        self::assertSame('continuous', $event['attribs']['event_schedule']['mode']);
        self::assertSame('keep', $event['attribs']['other']);
        $event['attribs'] = json_encode($event['attribs']);
        self::assertSame('continuous', JemEventSchedule::definition((object) $event)['mode']);
    }

    public function testCustomAndWeeklyOccurrencesKeepRelativeProgrammes(): void
    {
        $event = (array) self::event('per_day', [self::day(0, [], false, true),
            self::day(1, [], true), self::day(2, [['start' => '09:00', 'end' => '11:00']])]);
        $copy = $event;
        JemEventSeriesSchedule::apply($copy, ['date' => '2026-11-05', 'end_date' => '2026-11-07', 'time' => '12:00', 'end_time' => '15:00']);
        $sessions = JemEventSchedule::intervals((object) $copy);
        self::assertSame('2026-11-07 09:00:00', $sessions[1]['start']->format('Y-m-d H:i:s'));
        self::assertSame($event['attribs'], $copy['attribs']);
        $event['recurrence_number'] = 1;
        $event['recurrence_byday'] = 'W:4';
        $weekly = JemRecurrenceValidator::nextWeeklyOccurrence($event);
        self::assertSame('2026-10-22', $weekly['dates']);
        self::assertSame('2026-10-24', $weekly['enddates']);
        self::assertSame('2026-10-24 09:00:00', JemEventSchedule::intervals((object) $weekly)[1]['start']->format('Y-m-d H:i:s'));
        $copy['enddates'] = '2026-11-08';
        $this->expectExceptionMessage('COM_JEM_SCHEDULE_DAYS_MISMATCH');
        JemEventSchedule::validate((object) $copy);
    }

    #[DataProvider('timeSelectors')]
    public function testEditorTimeSelectors(string $hour, string $minute, ?string $expected): void
    {
        self::assertSame($expected, JemEventSchedule::editorTime($hour, $minute));
    }

    public static function timeSelectors(): array
    {
        return [['', '', null], ['0', '0', '00:00:00'], ['00', '30', '00:30:00'],
            ['9', '', '09:00:00'], ['', '15', '00:15:00'], ['23', '59', '23:59:00']];
    }

    public function testMaximumProgrammeExceedsLegacyStorageButRemainsBounded(): void
    {
        $days = [];
        for ($offset = 0; $offset < JemEventSchedule::MAX_DAYS; $offset++) {
            $slots = [];
            for ($hour = 0; $hour < 16; $hour += 2) {
                $slots[] = ['start' => sprintf('%02d:00', $hour), 'end' => sprintf('%02d:00', $hour + 1)];
            }
            $days[] = self::day($offset, $slots);
        }
        $event = self::event('per_day', $days);
        $event->dates = '2028-01-01';
        $event->enddates = '2028-12-31';
        $normal = JemEventSchedule::normalise(json_encode(JemEventSchedule::definition($event)), $event);
        self::assertGreaterThan(65535, strlen(json_encode($normal)));
        self::assertLessThan(200000, strlen(json_encode($normal)));
        self::assertCount(366 * 8, JemEventSchedule::intervals($event));
        $event->enddates = '2029-01-01';
        $this->expectExceptionMessage('COM_JEM_SCHEDULE_DAYS_MISMATCH');
        JemEventSchedule::validate($event);
    }

    public function testUnrelatedStoredParametersSurvivePartialForms(): void
    {
        $existing = self::event();
        $form = ['attribs' => ['new' => 'yes']];
        JemEventSchedule::mergeSubmission($form, $existing);
        self::assertSame('keep', $form['attribs']['other']);
        self::assertSame('yes', $form['attribs']['new']);
    }

    #[DataProvider('invalidProgrammes')]
    public function testInvalidProgrammesAreRejected($raw, array $changes = []): void
    {
        $event = (object) array_replace((array) self::event(), $changes);
        $this->expectException(InvalidArgumentException::class);
        JemEventSchedule::normalise($raw, $event);
    }

    public static function invalidProgrammes(): array
    {
        $base = ['version' => 1, 'mode' => 'daily', 'days' => []];
        $slot = ['start' => '09:00', 'end' => '12:00'];
        $days = [self::day(0, [$slot]), self::day(1, [], true), self::day(2, [], false, true)];
        $bad = [['broken'], [str_repeat('x', 200001)], [null], [array_replace($base, ['version' => 2])],
            [array_replace($base, ['mode' => '<script>'])], [$base, ['dates' => '2026-02-30']],
            [$base, ['dates' => '0000-01-01']], [$base, ['dates' => "2026-01-01\0"]],
            [$base, ['enddates' => '2026-10-14']], [$base, ['times' => '25:00']],
            [$base, ['times' => null]], [$base, ['times' => '22:00', 'endtimes' => '02:00']],
            [['version' => 1, 'mode' => 'per_day', 'days' => $days], ['dates' => null]]];
        $variants = [[], [$days[0]], [$days[0], $days[0], $days[2]]];
        foreach (['offset' => 3, 'closed' => 'false', 'all_day' => 1, 'slots' => array_fill(0, 9, $slot)] as $field => $value) {
            $variant = $days;
            $variant[0][$field] = $value;
            $variants[] = $variant;
        }
        foreach ([[], [['start' => '10:00', 'end' => '09:00']], [['start' => '10:00', 'end' => '']],
            [$slot, ['start' => '11:00', 'end' => '13:00']]] as $slots) {
            $variants[] = [self::day(0, $slots), $days[1], $days[2]];
        }
        $variants[] = [self::day(0, [$slot], true), $days[1], $days[2]];
        $variants[] = [self::day(0, [], true, true), $days[1], $days[2]];
        $variants[] = [self::day(0, [], true), self::day(1, [], true), self::day(2, [], true)];
        foreach ($variants as $variant) {
            $bad[] = [['version' => 1, 'mode' => 'per_day', 'days' => $variant]];
        }
        return $bad;
    }
}
