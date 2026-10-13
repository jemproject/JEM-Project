<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DayHourRangeTest extends TestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('UTC');
        class_alias(DayHourRangeHelper::class, 'JemHelper');
    }

    #[DataProvider('rangeProvider')]
    public function testBothLayoutsKeepEventsOutsideDisplayHours(string $mode, int $start, int $end): void
    {
        $times = ['00:00', '06:00', '07:00', '07:30', '07:45', '08:00', '21:45', '22:00', '22:15', '23:45'];
        $rows = array_map(static fn ($time) => (object) [
            'id' => $time, 'dates' => '2026-10-22', 'enddates' => null,
            'times' => $time, 'endtimes' => null, 'locid' => 1, 'venue' => 'Hall',
        ], $times);
        foreach (['timeline.php', 'responsive/timeline.php'] as $template) {
            $days = $this->timeline($template, $rows, $mode, $start, $end);
            self::assertSame($times, array_column($days['2026-10-22']['timedRows'], 'id'));
            self::assertCount(count($times), $days['2026-10-22']['timedRowsByHour']);
        }

        $grid = $this->timetable($rows, $mode, $start, $end);
        self::assertSame($times, array_map(static fn ($item) => $item[0]->id, $grid['venueRows']['venue-1']['items']));
        foreach ($grid['timedRows'] as [$row, $eventStart, $eventEnd]) {
            $overlappingSlots = array_filter($grid['timeSlots'], static function ($slot) use ($mode, $eventStart, $eventEnd) {
                $slotStart = $mode === 'event_hours' ? strtotime(sprintf('2026-10-22 %02d:00', $slot)) : $slot;

                return $eventStart < $slotStart + 3600 && $eventEnd > $slotStart;
            });
            self::assertNotEmpty($overlappingSlots, 'Event ' . $row->id . ' needs a visible slot in either orientation.');
        }
        if ($mode === 'all_hours') {
            self::assertSame(strtotime('2026-10-22 00:00'), (int) $grid['gridStart']);
            self::assertSame(strtotime('2026-10-23 00:00'), (int) $grid['gridEnd']);
        }
    }

    #[DataProvider('modeProvider')]
    public function testAllDayAndMultiDayEventsKeepTheirDateBoundaries(string $mode): void
    {
        $rows = [
            (object) ['id' => 'all-day', 'dates' => '2026-10-22', 'times' => null],
            (object) ['id' => 'multi-day', 'dates' => '2026-10-21', 'enddates' => '2026-10-23', 'times' => '23:30'],
            (object) ['id' => 'outside-date-window', 'dates' => '2026-10-24', 'times' => '06:00'],
        ];
        foreach (['timeline.php', 'responsive/timeline.php'] as $template) {
            $days = $this->timeline($template, $rows, $mode, 8, 22, 2);
            self::assertSame(['2026-10-22', '2026-10-23'], array_keys($days));
            self::assertSame(['all-day'], array_column($days['2026-10-22']['allDayRows'], 'id'));
            foreach ($days as $day) {
                self::assertSame(['multi-day'], array_column($day['timedRows'], 'id'));
            }
        }
        $grid = $this->timetable([$rows[0]], $mode, 8, 22);
        self::assertSame(['all-day'], array_column($grid['allDayRows'], 'id'));
        self::assertSame([], $grid['timedRows']);
        self::assertNotEmpty($grid['timeSlots']);
    }

    public function testEventHoursRetainWholeIntervalsAndSkipEmptyGaps(): void
    {
        $rows = [
            (object) ['id' => 'early', 'times' => '06:30', 'endtimes' => '09:15', 'locid' => 1, 'venue' => 'Hall'],
            (object) ['id' => 'late', 'times' => '21:45', 'endtimes' => '23:30', 'locid' => 1, 'venue' => 'Hall'],
        ];
        $grid = $this->timetable($rows, 'event_hours', 8, 22);
        self::assertSame([6, 7, 8, 9, 21, 22, 23], $grid['timeSlots']);
    }

    public function testResponsiveTimetableUsesTheSameImplementation(): void
    {
        self::assertStringContainsString("include dirname(__DIR__) . '/timetable.php';", (string) file_get_contents(
            JEM_TEST_ROOT . '/site/views/day/tmpl/responsive/timetable.php'
        ));
    }

    public function testFullRangeKeepsConfiguredEmptyHours(): void
    {
        $rows = [(object) ['id' => 'noon', 'times' => '12:00', 'locid' => 1, 'venue' => 'Hall']];
        foreach ([$rows, []] as $items) {
            $grid = $this->timetable($items, 'all_hours', 8, 22);
            self::assertSame(strtotime('2026-10-22 08:00'), (int) $grid['gridStart']);
            self::assertSame(strtotime('2026-10-22 22:00'), (int) $grid['gridEnd']);
            self::assertCount(14, $grid['timeSlots']);
        }
    }

    public static function modeProvider(): iterable
    {
        yield ['event_hours'];
        yield ['all_hours'];
    }

    public static function rangeProvider(): iterable
    {
        foreach (['event_hours', 'all_hours'] as $mode) {
            foreach ([[8, 22], [6, 22], [12, 14], [0, 24]] as [$start, $end]) {
                yield "$mode $start-$end" => [$mode, $start, $end];
            }
        }
    }

    private function timeline(string $template, array $rows, string $hourDisplay, int $start, int $end, int $timelineDaysToShow = 1): array
    {
        $currentDate = new DateTimeImmutable('2026-10-22');
        $rangeEndDate = $currentDate->modify('+' . ($timelineDaysToShow - 1) . ' days');
        $rangeStartMinutes = $start * 60;
        $rangeEndMinutes = $end * 60;
        $getDisplayTimeValue = static fn ($row, $field) => empty($row->$field) ? null : strtotime($row->dates . ' ' . $row->$field);
        $sortTimelineRows = static function (array &$rows): void {
        };
        // Execute the actual template preparation, stopping before its HTML/CSS.
        eval($this->preparation($template, '$dayRange = array();'));

        return $dayRange;
    }

    private function timetable(array $rows, string $hourDisplay, int $rangeStartHour, int $rangeEndHour): array
    {
        $currentDate = new DateTimeImmutable('2026-10-22');
        $minimumVisibleHours = 1;
        $getVenueColorData = static fn ($row) => ['#ffffff', false];
        eval($this->preparation('timetable.php', '$slotMinutes = 60;'));

        return compact('venueRows', 'timedRows', 'allDayRows', 'timeSlots', 'gridStart', 'gridEnd');
    }

    private function preparation(string $template, string $marker): string
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/' . $template);
        $start = strpos($source, $marker);
        self::assertNotFalse($start);
        $end = strpos($source, '?>', $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }
}

final class DayHourRangeHelper
{
    public static function isValidDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) === 1;
    }
}
