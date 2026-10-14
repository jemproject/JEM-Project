<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DayLayoutRenderTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/day_layout_fixture.php';
    }

    #[DataProvider('timetables')]
    public function testTimetableRendersEveryEventAndSession(string $layout, string $orientation, string $hours): void
    {
        $view = new DayLayoutFixture(['timetable_orientation' => $orientation, 'timetable_hour_display' => $hours]);
        $html = $view->render($layout);
        foreach (range(1, 17) as $id) {
            self::assertStringContainsString('data-event-id="' . $id . '"', $html);
        }
        self::assertStringContainsString('23:30 - 24:00', $html);
        self::assertStringContainsString('09:00 - 12:00', $html);
        self::assertStringContainsString('14:00 - 17:00', $html);
        self::assertStringNotContainsString('06:00 - 07:00', $html);
        self::assertStringContainsString('Itemid=42&catid=7', $html);
        self::assertStringContainsString('All-day venue', $html);
        self::assertSame('2026-11-05', $view->rows[9]->dates);
        self::assertFalse(isset($view->rows[9]->day_schedule));
    }

    public static function timetables(): iterable
    {
        foreach (['timetable', 'responsive/timetable'] as $layout) {
            foreach (['horizontal', 'vertical'] as $orientation) {
                foreach (['event_hours', 'all_hours'] as $hours) {
                    yield "$layout-$orientation-$hours" => [$layout, $orientation, $hours];
                }
            }
        }
    }

    #[DataProvider('timelines')]
    public function testTimelineShowsOvernightPartsAndNotClosedProgrammeDay(string $layout, string $hours): void
    {
        $view = new DayLayoutFixture(['timetable_hour_display' => $hours]);
        $html = $view->render($layout);
        self::assertStringContainsString('23:30 - 24:00', $html);
        self::assertStringContainsString('00:00 - 01:30', $html);
        self::assertStringContainsString('09:00 - 12:00', $html);
        self::assertStringNotContainsString('06:00 - 07:00', $html);
        $daySections = preg_split('/<section class="jem-day-timeline-day/', $html);
        self::assertCount(4, $daySections);
        self::assertStringContainsString('Programme', $daySections[1]);
        self::assertStringNotContainsString('Programme', $daySections[2]);
        self::assertStringContainsString('Programme', $daySections[3]);
        self::assertSame('2026-11-05', $view->rows[9]->dates);
    }

    public static function timelines(): iterable
    {
        foreach (['timeline', 'responsive/timeline'] as $layout) {
            foreach (['event_hours', 'all_hours'] as $hours) {
                yield "$layout-$hours" => [$layout, $hours];
            }
        }
    }
}
