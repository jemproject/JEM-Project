<?php

declare(strict_types=1);

namespace Joomla\CMS {
    final class Factory
    {
        public static object $application;

        public static function getApplication(): object
        {
            return self::$application;
        }
    }
}

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }
    }
}

namespace Joomla\CMS\Router {
    final class Route
    {
        public static function _(string $url, bool $xhtml = true): string
        {
            return $xhtml ? str_replace('&', '&amp;', $url) : $url;
        }
    }
}

namespace Joomla\CMS\Uri {
    final class Uri
    {
        private array $query = ['option' => 'com_jem', 'view' => 'weekcal', 'weekID' => '45'];

        public static function getInstance(): self
        {
            return new self();
        }

        public function getQuery(bool $asArray = false): array
        {
            return $this->query;
        }

        public function setQuery(array $query): void
        {
            $this->query = $query;
        }

        public function __toString(): string
        {
            return 'index.php?' . http_build_query($this->query);
        }
    }
}

namespace {
    use PHPUnit\Framework\Attributes\PreserveGlobalState;
    use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
    use PHPUnit\Framework\TestCase;

    final class JemHelper
    {
        public static function isValidCalendarDate(string $date): bool
        {
            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
        }

        public static function getJoomlaTimeZoneName(): string
        {
            return 'Europe/Madrid';
        }

        public static function getJoomlaDate(): string
        {
            return '2026-11-05';
        }

        public static function getEventTimeZoneName(object $event): string
        {
            return 'Europe/Madrid';
        }

        public static function calendarSpecialDays(string $start, string $end): array
        {
            return ['2026-11-07' => [
                ['type' => 'Weekend', 'title' => 'Saturday and Sunday', 'color' => '#d1d5db'],
                ['type' => 'Holiday', 'title' => 'Local holiday', 'color' => '#ffcc00'],
            ]];
        }

        public static function renderCalendarSpecialDayBadges(string $date, ?array $specialDays = null): string
        {
            return implode('', array_map(static fn (array $day): string => $day['title'], $specialDays ?? []));
        }
    }

    final class JemOutput
    {
        public static function formatdate(string $date, string $format = ''): string
        {
            return $format === '' ? $date : (new \DateTimeImmutable($date))->format($format);
        }
    }

    final class JemHelperRoute
    {
        public static function getEventRoute(string $slug): string
        {
            return '/event/' . $slug;
        }
    }

    #[RunTestsInSeparateProcesses]
    #[PreserveGlobalState(false)]
    final class CalendarHourGridTest extends TestCase
    {
        protected function setUp(): void
        {
            require_once JEM_TEST_ROOT . '/site/helpers/calendaragenda.php';
            require_once JEM_TEST_ROOT . '/site/helpers/calendarhourgrid.php';

            \Joomla\CMS\Factory::$application = (object) [
                'input' => new class () {
                    public string $mode = '';

                    public function getCmd(string $key, string $default = ''): string
                    {
                        return $key === 'mode' ? $this->mode : $default;
                    }

                    public function getInt(string $key, int $default = 0): int
                    {
                        return $default;
                    }
                },
            ];
        }

        public function testWeekCyclePreservesTheWeekAndShowsOnlyTheNextMode(): void
        {
            $params = new class () {
                public function get(string $key, string $default = ''): string
                {
                    return 'calendar';
                }
            };
            $input = \Joomla\CMS\Factory::$application->input;

            foreach ([
                'calendar' => ['timetable', 'fa-clock'],
                'timetable' => ['agenda', 'fa-list'],
                'agenda' => ['calendar', 'fa-calendar-days'],
            ] as $current => [$next, $icon]) {
                $input->mode = $current;
                $html = JemCalendarAgendaHelper::renderCycleToggle('week', $params);
                self::assertStringContainsString('weekID=45', $html);
                self::assertStringContainsString('mode=' . $next, $html);
                self::assertStringContainsString($icon, $html);
                self::assertSame(1, substr_count($html, '<a '));
            }
        }

        public function testDayCycleAlternatesListAndTimetable(): void
        {
            $params = null;
            $input = \Joomla\CMS\Factory::$application->input;
            $input->mode = 'list';
            self::assertStringContainsString('mode=timetable', JemCalendarAgendaHelper::renderCycleToggle('day', $params));
            $input->mode = 'timetable';
            self::assertStringContainsString('mode=list', JemCalendarAgendaHelper::renderCycleToggle('day', $params));
        }

        public function testWeekNavigationPreservesListMode(): void
        {
            $html = JemCalendarHourGridHelper::renderNavigation('weekcal', '2026-11-02', 7, false, 'agenda');
            self::assertSame(3, substr_count($html, 'mode=agenda'));
            self::assertStringContainsString('weekID=44', $html);
            self::assertStringContainsString('weekID=46', $html);
            self::assertStringNotContainsString('&amp;amp;', $html);
        }

        public function testHourlyProjectionOmitsClosedDayAndKeepsTwoSessionsAndAllDay(): void
        {
            $event = (object) [
                'id' => 3,
                'slug' => '3:example',
                'title' => 'Example',
                'venue' => 'Hall',
                'dates' => '2026-11-05',
                'enddates' => '2026-11-07',
                'times' => '',
                'endtimes' => '',
                'attribs' => ['event_schedule' => [
                    'version' => 1,
                    'mode' => 'per_day',
                    'days' => [
                        ['offset' => 0, 'closed' => false, 'all_day' => false, 'slots' => [
                            ['start' => '09:00', 'end' => '12:00'],
                            ['start' => '14:00', 'end' => '17:00'],
                        ]],
                        ['offset' => 1, 'closed' => true, 'all_day' => false, 'slots' => []],
                        ['offset' => 2, 'closed' => false, 'all_day' => true, 'slots' => []],
                    ],
                ]],
            ];

            $html = JemCalendarHourGridHelper::render([$event], '2026-11-05', 3, 9);
            self::assertSame(1, substr_count($html, 'class="jem-hourgrid-axis-plot"'));
            self::assertSame(1, substr_count($html, 'class="jem-hourgrid-axis-all-day"'));
            self::assertSame(2, substr_count($html, 'class="jem-hourgrid-event"'));
            self::assertSame(1, substr_count($html, 'class="jem-hourgrid-all-day-event"'));
            self::assertStringContainsString('09:00 - 12:00', $html);
            self::assertStringContainsString('14:00 - 17:00', $html);
            self::assertStringContainsString('2026-11-06', $html);
            self::assertStringContainsString('COM_JEM_TIMETABLE_ALL_DAY', $html);
            self::assertStringContainsString('/event/3:example', $html);
            self::assertStringContainsString('class="jem-hourgrid-day-link" href="index.php?option=com_jem&amp;view=day&amp;id=20261107&amp;mode=timetable&amp;topcat=9"', $html);
            self::assertStringContainsString('class="jem-hourgrid-day-short">Sat 7', $html);
            self::assertStringContainsString('class="jem-hourgrid-day-mobile">Sat 7 Nov', $html);
            self::assertStringContainsString('jem-hourgrid-weekend', $html);
            self::assertStringContainsString('Local holiday', $html);
            self::assertStringNotContainsString('Saturday and Sunday', $html);
            self::assertStringNotContainsString('&amp;amp;', $html);
        }
    }
}
