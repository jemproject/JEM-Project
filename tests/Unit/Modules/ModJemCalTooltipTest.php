<?php

declare(strict_types=1);

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModJemCalTooltipTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/calendar_tooltip_stubs.php';
        require_once JEM_TEST_ROOT . '/modules/mod_jem_cal/helper.php';
    }

    #[DataProvider('timeProvider')]
    public function testFirstAndSubsequentTooltipsKeepOnlySpecifiedTimes(
        ?string $start,
        ?string $end,
        string $prefix,
        string $timezone
    ): void {
        $originalTimezone = date_default_timezone_get();

        try {
            date_default_timezone_set($timezone);
            BaseDatabaseModel::$items = [
                $this->event('Alpha', $start, $end),
                $this->event('Beta', $start, $end),
            ];
            $params = new CalendarTooltipParams(['DisplayTime' => 1]);
            $days = ModJemCalHelper::getDays(2026, 10, $params);

            self::assertSame([30], array_keys($days));
            self::assertSame($prefix . 'Alpha+%+%+' . $prefix . 'Beta', $days[30][1]);
            self::assertStringContainsString('view=day', $days[30][0]);
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public static function timeProvider(): iterable
    {
        $cases = [
            'null' => [null, null, ''],
            'empty' => ['', '', ''],
            'whitespace' => [' ', ' ', ''],
            'start only' => ['09:30:00', null, '9:30 '],
            'end only' => [null, '17:00:00', 'until 17:00 '],
            'midnight start' => ['00:00:00', null, '0:00 '],
            'midnight end' => [null, '00:00:00', 'until 0:00 '],
            'explicit midnight range' => ['00:00:00', '00:00:00', '0:00-0:00 '],
            'complete' => ['09:30:00', '17:45:00', '9:30-17:45 '],
            'overnight' => ['23:30:00', '00:15:00', '23:30-0:15 '],
            'minutes only' => ['09:30', '17:45', '9:30-17:45 '],
            'invalid' => ['not a time', '25:00:00', ''],
            'invalid end' => ['09:30:00', '17:99:00', '9:30 '],
            'invalid start' => ['09:30:99', '17:45:00', 'until 17:45 '],
        ];

        foreach (['UTC', 'Europe/Madrid', 'America/New_York', 'Asia/Kolkata'] as $timezone) {
            foreach ($cases as $name => $case) {
                yield $name . ' in ' . $timezone => [...$case, $timezone];
            }
        }
    }

    public function testDisplayTimeCanBeDisabledForAllEvents(): void
    {
        BaseDatabaseModel::$items = [
            $this->event('Alpha', null, '17:00:00'),
            $this->event('Beta', '09:30:00', '18:00:00'),
        ];
        $params = new CalendarTooltipParams(['DisplayTime' => 0]);
        $days = ModJemCalHelper::getDays(2026, 10, $params);

        self::assertSame('Alpha+%+%+Beta', $days[30][1]);
    }

    public function testMultidayTooltipPreservesLinksAndEscapedDetails(): void
    {
        $event = $this->event('Music & art', null, null);
        $event->enddates = '2026-10-31';
        $event->categories = [(object) ['catname' => 'Arts & crafts']];
        $event->venue = 'Hall <A>';
        BaseDatabaseModel::$items = [$event];
        $params = new CalendarTooltipParams([
            'DisplayTime' => 1,
            'DisplayLink' => 1,
            'DisplayCat' => 1,
            'DisplayVenue' => 1,
        ]);
        $days = ModJemCalHelper::getDays(2026, 10, $params);

        self::assertSame([30, 31], array_keys($days));
        self::assertSame(
            '<a href="index.php?option=com_jem&view=event&id=1">Music &amp; art'
                . ' (Arts &amp; crafts) @Hall &lt;A&gt;</a>',
            $days[30][1]
        );
        self::assertSame($days[30], $days[31]);
    }

    public function testNoEventsReturnsAnEmptyCalendar(): void
    {
        BaseDatabaseModel::$items = [];
        $params = new CalendarTooltipParams(['DisplayTime' => 1]);

        self::assertSame([], ModJemCalHelper::getDays(2026, 10, $params));
    }

    private function event(string $title, ?string $start, ?string $end): object
    {
        return (object) [
            'slug' => '1',
            'title' => $title,
            'dates' => '2026-10-30',
            'enddates' => null,
            'created_year' => 2026,
            'created_month' => 10,
            'created_day' => 30,
            'times' => $start,
            'endtimes' => $end,
            'categories' => [(object) ['catname' => 'Public']],
        ];
    }
}
