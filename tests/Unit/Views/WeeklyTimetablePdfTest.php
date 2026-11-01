<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use DateTimeImmutable;
use JemProject\Tests\WeekPdf\Environment;
use JemProject\Tests\WeekPdf\Factory;
use JemProject\Tests\WeekPdf\Bag;
use JemProject\Tests\WeekPdf\Document;
use JemProject\Tests\WeekPdf\HtmlView;
use JemProject\Tests\WeekPdf\JemCalendarHourGridHelper;
use JemProject\Tests\WeekPdf\JemPdfWeekTimetable;
use JemProject\Tests\WeekPdf\JemPdf;
use JemProject\Tests\WeekPdf\JemViewWeekcal;
use JemProject\Tests\WeekPdf\OutputCaptured;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Support/week_pdf_environment.php';

/** New timetable regression: production projection/drawing/dispatch with isolated external services. */
final class WeeklyTimetablePdfTest extends TestCase
{
    /** @return void Load this branch's actual implementation. */
    public static function setUpBeforeClass(): void
    {
        Environment::load(JEM_TEST_ROOT);
    }

    /** @return iterable<string, array{int, int, int}> ISO and multi-week boundaries. */
    public static function periods(): iterable
    {
        foreach ([1, 44, 53] as $week) {
            foreach ([0, 1] as $first) {
                foreach ([1, 2] as $weeks) {
                    yield "$week:$first:$weeks" => [$week, $first, $weeks];
                }
            }
        }
    }

    /**
     * Every normalized week has exactly one overview even with no events.
     *
     * @param int $week ISO week.
     * @param int $first First weekday.
     * @param int $weeks Visible weeks.
     * @return void
     */
    #[DataProvider('periods')]
    public function testEmptyWeeksKeepDateOrderAndHours(int $week, int $first, int $weeks): void
    {
        $pdf = new Document();
        $period = Environment::period($week, $first, $weeks);
        JemPdfWeekTimetable::render($pdf, 'Week', [], $period);
        self::assertSame($weeks, $pdf->pages);
        self::assertCount($weeks * 7, $pdf->rectangles);
        $texts = array_column(array_column($pdf->text, 1), 2);
        self::assertSame($weeks, count(array_filter($texts, static fn($text) => $text === 'No events')));
        self::assertContains('07:00', $texts);
        self::assertContains('22:00', $texts);
        for ($offset = 0; $offset < $weeks * 7; $offset++) {
            self::assertContains($period['start']->modify("+$offset days")->format('D d/m'), $texts);
        }
    }

    /** @return void Projection preserves sessions, overlaps and unknown actual finish times. */
    public function testSharedProjectionDoesNotMutateOrInventTimes(): void
    {
        $rows = Environment::rows();
        $before = serialize($rows);
        $grid = JemCalendarHourGridHelper::prepare($rows, '2026-10-26', 7);
        self::assertSame($before, serialize($rows));
        self::assertCount(7, $grid['columns']);
        self::assertCount(1, $grid['columns'][0]['all_day']);
        self::assertCount(5, $grid['columns'][0]['timed']);
        self::assertCount(1, $grid['columns'][1]['timed']);
        self::assertSame(0, $grid['first_hour']);
        self::assertSame(24, $grid['last_hour']);
        $unknown = array_values(array_filter($grid['columns'][0]['timed'], static fn($item) => $item['row']->id === 3))[0];
        self::assertSame('', $unknown['row']->day_schedule['end_label']);
        self::assertSame(60, $unknown['end'] - $unknown['start'], 'Visual fallback only.');
        foreach ($grid['columns'] as $column) {
            foreach ($column['timed'] as $a => $left) {
                foreach ($column['timed'] as $b => $right) {
                    if ($a < $b && $left['start'] < $right['end'] && $right['start'] < $left['end']) {
                        self::assertNotSame($left['lane'], $right['lane']);
                    }
                }
            }
        }
    }

    /** @return void Dense grids retain every reference and full detail text without leaking inaccessible categories. */
    public function testDenseOutputHasLosslessDetailsAndInBoundsRectangles(): void
    {
        $rows = Environment::rows();
        for ($i = 10; $i < 40; $i++) {
            $row = clone $rows[0];
            $row->id = $i;
            $row->title = 'Long title ' . $i . ' ' . str_repeat('extended detail ', 20);
            $rows[] = $row;
        }
        $private = clone $rows[0];
        $private->title = 'Must not appear';
        $private->user_has_access_category = false;
        $rows[] = $private;
        $before = serialize($rows);
        $pdf = new Document();
        JemPdfWeekTimetable::render($pdf, 'Week', $rows, Environment::period());
        self::assertSame($before, serialize($rows));
        self::assertSame(2, $pdf->pages);
        $html = implode("\n", $pdf->html);
        self::assertStringNotContainsString('Must not appear', $html);
        self::assertStringContainsString('Opening &amp; welcome', $html);
        self::assertStringContainsString('23:30 - 24:00', $html);
        self::assertStringContainsString('00:00 - 01:30', $html);
        foreach (array_slice($rows, 0, -1) as $row) {
            self::assertStringContainsString(htmlspecialchars(trim($row->title), ENT_QUOTES, 'UTF-8'), $html);
        }
        foreach ($pdf->rectangles as [$page, $args]) {
            [$x, $y, $width, $height] = $args;
            self::assertGreaterThan(0, $width);
            self::assertGreaterThan(0, $height);
            self::assertLessThanOrEqual(283.01, $x + $width);
            self::assertLessThanOrEqual(190.01, $y + $height);
        }
    }

    /** @return void A late session with no finish cannot escape the bottom grid edge. */
    public function testMidnightRectangleStaysInsideGrid(): void
    {
        $row = Environment::rows()[0];
        $row->times = '23:59:00';
        $row->endtimes = null;
        $pdf = new Document();
        JemPdfWeekTimetable::render($pdf, 'Week', [$row], Environment::period());
        $events = array_values(array_filter($pdf->rectangles, static fn($entry) => ($entry[1][4] ?? '') === 'DF'));
        self::assertCount(1, $events);
        [$x, $y, $width, $height] = $events[0][1];
        self::assertGreaterThan(0, $height);
        self::assertLessThanOrEqual(190.0, $y + $height);
        self::assertStringContainsString('23:59', implode('', $pdf->html));
        self::assertStringNotContainsString('23:59 - 24:00', implode('', $pdf->html));
    }

    /** @return iterable<string, array{string, string}> Timetable selection from request or menu options. */
    public static function modes(): iterable
    {
        yield 'explicit' => ['timetable', 'calendar'];
        yield 'configured' => ['', 'timetable'];
        yield 'invalid fallback' => ['invalid', 'timetable'];
    }

    /** @param string $request Mode input. @param string $configured Menu mode. @return void */
    #[DataProvider('modes')]
    public function testRawViewLoadsUnsplitRowsBeforeDrawing(string $request, string $configured): void
    {
        Factory::$app->input = new Bag(['layout' => 'pdf', 'mode' => $request]);
        Factory::$app->params = new Bag(['calendar_display_mode' => $configured]);
        HtmlView::$rows = Environment::rows();
        HtmlView::$reads = [];
        try {
            (new JemViewWeekcal())->display();
            self::fail('Expected document output.');
        } catch (OutputCaptured) {
            self::assertSame(false, HtmlView::$reads[0]['filter.calendar_multiday']);
            self::assertSame(0, HtmlView::$reads[0]['list.limit']);
            self::assertGreaterThan(0, count(JemPdf::$document->rectangles));
        }
    }

    /** @return void The weekly-only addition must not enable timetable in the monthly selector. */
    public function testExistingCalendarAndAgendaSelectorUnchanged(): void
    {
        $method = new \ReflectionMethod(\JemProject\Tests\WeekPdf\JemPdfView::class, 'getCalendarPdfLayout');
        foreach (['calendar', 'agenda'] as $mode) {
            Factory::$app->input = new Bag(['mode' => $mode]);
            self::assertSame($mode, $method->invoke(null, new Bag()));
        }
        Factory::$app->input = new Bag(['mode' => 'timetable']);
        self::assertSame('calendar', $method->invoke(null, new Bag()));
    }

    /** @return void JEM 5.1 programme slots and timezone survive the shared projection. */
    public function testProgrammeWhenSupported(): void
    {
        if (!class_exists('JemProject\Tests\WeekPdf\JemEventSchedule', false)) {
            self::assertFalse(is_file(JEM_TEST_ROOT . '/site/classes/dayschedule.class.php'));
            return;
        }
        $row = Environment::rows()[0];
        $row->timezone = 'America/New_York';
        $row->attribs = ['event_schedule' => ['version' => 1, 'mode' => 'per_day', 'days' => [
            ['offset' => 0, 'closed' => false, 'all_day' => false, 'slots' => [
                ['start' => '08:00', 'end' => '09:00'], ['start' => '16:00', 'end' => '17:00'],
            ]],
        ]]];
        $grid = JemCalendarHourGridHelper::prepare([$row], '2026-10-26', 1);
        self::assertCount(2, $grid['columns'][0]['timed']);
        self::assertSame('08:00', $grid['columns'][0]['timed'][0]['row']->day_schedule['start_label']);
        self::assertStringContainsString('America/New_York', $grid['columns'][0]['timed'][0]['row']->day_schedule['actual']);
    }
}
