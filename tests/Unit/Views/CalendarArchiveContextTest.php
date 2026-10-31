<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use DateTimeImmutable;
use JemProject\Tests\AnnualCalendar\Database;
use JemProject\Tests\AnnualCalendar\Input;
use JemProject\Tests\AnnualCalendar\Registry;
use PHPUnit\Framework\TestCase;

/** Execute production archive context fragments without replacing their URL or state decisions. */
final class CalendarArchiveContextTest extends TestCase
{
    /** @return void Load isolated Joomla service boundaries shared with the calendar period tests. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/annual_calendar_environment.php';
    }

    /** @param string $file Relative path. @param string $start First statement. @param string $end End marker. @return string Production fragment. */
    private function fragment(string $file, string $start, string $end): string
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
        $first = strpos($source, $start);
        self::assertNotFalse($first);
        $last = strpos($source, $end, $first);
        self::assertNotFalse($last);

        return substr($source, $first, $last - $first);
    }

    /** @return void HTML Annual Calendar must not overwrite the model's archive publication state. */
    public function testAnnualHtmlKeepsModelPublicationState(): void
    {
        $code = $this->fragment('site/views/annualcalendar/view.html.php', '$model->getState(\'params\');', '$rows  =');
        $db = new Database();
        $periodStart = new DateTimeImmutable('2026-01-01');
        $periodEnd = new DateTimeImmutable('2026-12-31');
        foreach ([1, 2] as $published) {
            foreach ([false, true] as $showArchived) {
                $params = new Registry(['show_archived_events' => $showArchived]);
                $model = new class ($published) {
                    public array $state;
                    /** @param int $published Model-selected state. */
                    public function __construct(int $published)
                    {
                        $this->state = ['filter.published' => $published];
                    }
                    /** @param string $key State key. @return mixed Existing state. */
                    public function getState(string $key): mixed
                    {
                        return $this->state[$key] ?? null;
                    }
                    /** @param string $key State key. @param mixed $value New value. @return void */
                    public function setState(string $key, mixed $value): void
                    {
                        $this->state[$key] = $value;
                    }
                };
                eval($code);
                self::assertSame($published, $model->state['filter.published']);
                self::assertSame('2026-01-01', $model->state['filter.date.from']);
                self::assertSame('2026-12-31', $model->state['filter.date.to']);
            }
        }
    }

    /** @return void Archive exports retain explicit calendar bounds, without changing ordinary archived lists. */
    public function testCalendarPeriodGuardDoesNotEnableRelativeArchiveFilters(): void
    {
        $code = $this->fragment('site/models/eventslist.php', '$hasCalendarPeriod =', 'if($opendates != 2');
        foreach ([
            [2, false, false, false],
            [2, true, false, true],
            [2, false, true, true],
            [1, false, false, true],
            [[1, 2], false, false, true],
        ] as [$published, $period, $mixed, $expected]) {
            $boundary = new class ($period, $mixed) {
                /** @param bool $period Explicit period. @param bool $mixed Include archived. */
                public function __construct(private bool $period, private bool $mixed)
                {
                }
                /** @param string $key Key. @param mixed $default Fallback. @return mixed State. */
                public function getState(string $key, mixed $default = null): mixed
                {
                    return match ($key) {
                        'filter.date.from' => $this->period ? '2026-01-01' : null,
                        'filter.date.to' => $this->period ? '2026-12-31' : null,
                        'filter.show_archived_events' => $this->mixed,
                        default => $default,
                    };
                }
            };
            $run = function () use ($code, $published): bool {
                eval($code);
                return $applyCalendarDateFilters;
            };
            self::assertSame($expected, $run->call($boundary));
        }
    }

    /** @return void Both Timeline templates and Timetable carry archive, scope, print and display settings. */
    public function testDedicatedDayNavigationAndTimelineFormKeepArchive(): void
    {
        foreach (['timetable.php', 'timeline.php', 'responsive/timeline.php'] as $template) {
            $timeline = str_contains($template, 'timeline');
            $code = $this->fragment('site/views/day/tmpl/' . $template,
                '$itemId = $input->getInt(\'Itemid\', 0);', $timeline ? '$navigationTitle =' : '$specialDayBadges =');
            $code = str_replace('JemHelper::getJoomlaDate()', "'2026-10-15'", $code);
            foreach (['', 'archive', 'event.remove'] as $task) {
                $input = new Input(['task' => $task, 'Itemid' => 101, 'catid' => 9, 'locid' => 11, 'print' => true]);
                $currentDate = new DateTimeImmutable('2026-10-15');
                $previousDate = $currentDate->modify('-1 day');
                $nextDate = $currentDate->modify('+1 day');
                $previousWeekDate = $currentDate->modify('-1 week');
                $nextWeekDate = $currentDate->modify('+1 week');
                $timelineDaysToShow = 3;
                $layoutOverride = 'details';
                $cardLayout = 'details';
                $layoutToggleTarget = 'compact';
                eval('use JemProject\\Tests\\AnnualCalendar\\Route;' . $code);
                foreach ([$previousLink, $nextLink, $todayLink] as $link) {
                    parse_str(parse_url(html_entity_decode($link), PHP_URL_QUERY), $query);
                    self::assertSame($task === 'archive' ? 'archive' : null, $query['task'] ?? null);
                    self::assertSame('101', $query['Itemid']);
                    self::assertSame('9', $query['catid']);
                    self::assertSame('11', $query['locid']);
                    self::assertSame('1', $query['print']);
                    self::assertSame('component', $query['tmpl']);
                    if ($timeline) {
                        self::assertSame('3', $query['timeline_days_to_show']);
                        self::assertSame('details', $query['jem_timeline_layout']);
                    }
                }
                if ($timeline) {
                    $form = $this->fragment('site/views/day/tmpl/' . $template,
                        '<input type="hidden" name="option"', '<?php if ($itemId > 0)');
                    ob_start();
                    eval('?>' . $form);
                    $hidden = ob_get_clean();
                    self::assertSame($task === 'archive', str_contains($hidden, 'name="task" value="archive"'));
                    self::assertStringContainsString('name="print" value="1"', $hidden);
                }
            }
        }
    }
}

