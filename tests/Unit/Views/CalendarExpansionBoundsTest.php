<?php

declare(strict_types=1);

namespace JemProject\Tests\CalendarExpansion;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/tests/Support/calendar_expansion_reference.php';

/** Execute production expansion with real PHP dates and 5.1 programme helpers; no database/HTTP. */
final class CalendarExpansionBoundsTest extends TestCase
{
    private string $previousZone;

    /** @return void Install isolated model classes once and preserve the process timezone. */
    protected function setUp(): void
    {
        $this->previousZone = date_default_timezone_get();
        Factory::$app = new Application([], []);
        if (class_exists(__NAMESPACE__ . '\\CommonModel', false)) {
            return;
        }
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $projection = '';
        if (str_contains($source, 'function projectCalendarSchedules')) {
            require_once JEM_TEST_ROOT . '/site/classes/calendarschedule.class.php';
            $projection = self::method($source, 'projectCalendarSchedules');
        }
        eval('namespace ' . __NAMESPACE__ . ';
            use \Exception; use \JemEventSchedule; use \JemCalendarSchedule;
            use ' . __NAMESPACE__ . '\CountingDate as DateTimeImmutable;
            class CommonModel extends ModelBoundary {
                ' . self::method($source, 'calendarMultiday') . $projection . '
            }');
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/weekcal.php');
        eval('namespace ' . __NAMESPACE__ . ';
            use \Exception;
            use ' . __NAMESPACE__ . '\CountingDate as DateTimeImmutable;
            class WeekModel extends CommonModel {
                private ?array $calendarPeriod = null;
                ' . self::method($source, 'calendarMultiday')
                . self::method($source, 'getCalendarPeriod') . self::method($source, 'getItems') . '
            }');
    }

    /** @return void Restore timezone after every test, including failures. */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);
    }

    /** @param string $source Source file. @param string $name Method. @return string Actual method body and signature. */
    private static function method(string $source, string $name): string
    {
        self::assertSame(1, preg_match('/(?:protected|public) function ' . $name . '\(.*?^    \}/ms', $source, $match));
        return $match[0];
    }

    /** @return iterable<string, array{int, int, int, int}> ISO period, length and first weekday. */
    public static function weeks(): iterable
    {
        foreach ([[2026, 44], [2026, 53], [2024, 9], [2026, 13], [2026, 43]] as [$year, $week]) {
            foreach ([1, 2] as $weeks) {
                foreach ([0, 1] as $first) {
                    yield "$year/$week:$weeks:$first" => [$year, $week, $weeks, $first];
                }
            }
        }
    }

    /**
     * Verify full row/ordering parity across real ISO periods, timezones, bounds and start-only mode.
     *
     * @param int $year ISO year.
     * @param int $week ISO week.
     * @param int $weeks Number of displayed weeks.
     * @param int $first First weekday.
     * @return void
     */
    #[DataProvider('weeks')]
    public function testWeeklyParity(int $year, int $week, int $weeks, int $first): void
    {
        foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $zone) {
            date_default_timezone_set($zone);
            Factory::$app = new Application(['yearID' => $year, 'weekID' => $week], ['nrweeks' => $weeks, 'firstweekday' => $first]);
            $period = (new WeekModel([]))->getCalendarPeriod();
            $from = $period['start']->format('Y-m-d');
            $to = $period['end']->format('Y-m-d');
            foreach ([false, true] as $startOnly) {
                foreach (['explicit', 'fallback', 'partial'] as $bounds) {
                    $state = ['filter.calendar_startdayonly' => $startOnly];
                    if ($bounds !== 'fallback') {
                        $state['filter.date.from'] = $from;
                    }
                    if ($bounds === 'explicit') {
                        $state['filter.date.to'] = $to;
                    }
                    $model = new WeekModel($state);
                    $rows = self::fixtures($from);
                    $expected = $model->legacyWeek(self::copy($rows));
                    $actual = $model->run(self::copy($rows));
                    self::assertSame(serialize($expected), serialize($actual), "$zone/$bounds/startOnly=" . (int) $startOnly);
                    foreach ($actual as $row) {
                        self::assertGreaterThanOrEqual($from, $row->dates);
                        self::assertLessThanOrEqual($to, $row->dates);
                    }
                }
            }
        }
    }

    /** @return iterable<string, array{string, string, string}> Months and one-sided/absent/reversed bounds. */
    public static function ranges(): iterable
    {
        yield 'october DST' => ['2026-10-01', '2026-10-31', '2026-10-01'];
        yield 'march DST' => ['2026-03-01', '2026-03-31', '2026-03-01'];
        yield 'leap February' => ['2024-02-01', '2024-02-29', '2024-02-01'];
        yield 'new year' => ['2026-12-28', '2027-01-03', '2026-12-28'];
        yield 'one day' => ['2026-10-26', '2026-10-26', '2026-10-26'];
        yield 'no lower bound' => ['', '2026-10-31', '2026-10-01'];
        yield 'no upper bound' => ['2026-10-01', '', '2026-10-01'];
        yield 'no bounds' => ['', '', '2026-10-01'];
        yield 'reversed bounds' => ['2026-10-31', '2026-10-01', '2026-10-01'];
    }

    /**
     * Verify the shared monthly path without changing its original-row retention or invalid-date guards.
     *
     * @param string $from Lower bound.
     * @param string $to Upper bound.
     * @param string $anchor Fixture start date.
     * @return void
     */
    #[DataProvider('ranges')]
    public function testCommonParity(string $from, string $to, string $anchor): void
    {
        foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $zone) {
            date_default_timezone_set($zone);
            foreach ([false, true] as $startOnly) {
                $model = new CommonModel([
                    'filter.date.from' => $from, 'filter.date.to' => $to,
                    'filter.calendar_startdayonly' => $startOnly,
                ]);
                $rows = self::fixtures($anchor);
                unset($rows[0]->datesdiff); // Shared model also supports callers without the SQL alias.
                $rows[] = new Event(['id' => 90, 'title' => 'Invalid', 'dates' => 'invalid-date',
                    'enddates' => $anchor, 'times' => null, 'endtimes' => null]);
                self::assertSame(
                    serialize($model->legacyCommon(self::copy($rows))),
                    serialize($model->run(self::copy($rows))),
                    "$zone/$from/$to/startOnly=" . (int) $startOnly
                );
            }
        }
    }

    /** @return iterable<string, array{string, string, string, int}> Expansion path, visible range and day budget. */
    public static function boundedWork(): iterable
    {
        yield 'week' => ['WeekModel', '2026-10-26', '2026-11-01', 7];
        yield 'month' => ['CommonModel', '2026-10-01', '2026-10-31', 31];
        yield 'two weeks' => ['WeekModel', '2026-10-26', '2026-11-08', 14];
        yield 'no overlap before' => ['CommonModel', '2010-01-01', '2010-01-31', 0];
        yield 'no overlap after' => ['WeekModel', '2201-01-01', '2201-01-07', 0];
    }

    /**
     * Assert bounded work, not a timing threshold; monthly code must avoid off-screen date iterations too.
     *
     * @param string $modelName Model class.
     * @param string $from Lower bound.
     * @param string $to Upper bound.
     * @param int $days Maximum generated days.
     * @return void
     */
    #[DataProvider('boundedWork')]
    public function testWorkIsBoundedByVisibleDays(string $modelName, string $from, string $to, int $days): void
    {
        $class = __NAMESPACE__ . '\\' . $modelName;
        $model = new $class(['filter.date.from' => $from, 'filter.date.to' => $to]);
        $row = self::event(1, '2020-01-01', '2200-01-01');
        CountingDate::$modifications = Event::$clones = 0;
        $rows = $model->run([$row]);
        self::assertLessThanOrEqual($days, CountingDate::$modifications, 'No full-span date iteration.');
        self::assertSame($days, Event::$clones, 'Clone only intersecting additional days.');
        self::assertCount($days + ($modelName === 'CommonModel' ? 1 : 0), $rows);
        self::assertSame('2020-01-01', $row->dates);
        self::assertSame('2200-01-01', $row->enddates);
    }

    /** @return void Preserve exact first/last flags, empty results and the weekly timetable bypass. */
    public function testMetadataAndBypass(): void
    {
        $state = ['filter.date.from' => '2026-10-26', 'filter.date.to' => '2026-11-01'];
        foreach ([new WeekModel($state), new CommonModel($state)] as $model) {
            $rows = $model->run([self::event(1, '2026-10-26', '2026-10-28')]);
            $byDate = array_column($rows, null, 'dates');
            self::assertSame('first', $byDate['2026-10-26']->multi);
            self::assertSame('middle', $byDate['2026-10-27']->multi);
            self::assertSame('zlast', $byDate['2026-10-28']->multi);
            self::assertSame('2026-10-26', $byDate['2026-10-28']->multistartdate);
            self::assertSame('2026-10-28', $byDate['2026-10-28']->multienddate);
            self::assertSame('10:00:00', $byDate['2026-10-28']->times);
            self::assertSame([], $model->run([]));
        }
        $model = new WeekModel($state + ['filter.calendar_multiday' => false]);
        $model->items = [self::event(1, '2020-01-01', '2200-01-01')];
        CountingDate::$modifications = Event::$clones = 0;
        self::assertSame($model->items, $model->getItems());
        self::assertSame(0, Event::$clones);
        self::assertSame(0, CountingDate::$modifications);
    }

    /** @return void Verify actual per-day helpers when available, including closed days and start-only semantics. */
    public function testProgrammeProjection(): void
    {
        if (!class_exists(\JemCalendarSchedule::class, false)) {
            self::assertFileDoesNotExist(JEM_TEST_ROOT . '/site/classes/calendarschedule.class.php');
            return;
        }
        Factory::$app = new Application(['yearID' => 2026, 'weekID' => 44], ['firstweekday' => 1]);
        $event = self::programme('2026-10-25');
        $before = serialize($event);
        foreach (['WeekModel', 'CommonModel'] as $name) {
            $class = __NAMESPACE__ . '\\' . $name;
            foreach ([false, true] as $startOnly) {
                $model = new $class([
                    'filter.date.from' => '2026-10-26', 'filter.date.to' => '2026-11-01',
                    'filter.calendar_startdayonly' => $startOnly,
                ]);
                $rows = $model->run([$event]);
                $dates = array_column($rows, 'dates');
                sort($dates);
                self::assertSame($startOnly ? ['2026-10-26', '2026-10-26']
                    : ['2026-10-26', '2026-10-26', '2026-10-27', '2026-10-28'], $dates);
                self::assertSame($before, serialize($event), 'Programme projection must not mutate its source.');
            }
            $closed = new $class(['filter.date.from' => '2026-10-25', 'filter.date.to' => '2026-10-25']);
            self::assertSame([], $closed->run([$event]));
        }
        $rows = (new WeekModel([]))->run([$event]);
        self::assertCount(4, $rows, 'Fallback weekly bounds also reach the programme projector.');
        self::assertSame($before, serialize($event));
    }

    /** @param string $anchor Reference day. @return array Mixed fixtures; source records represent already-filtered query output. */
    private static function fixtures(string $anchor): array
    {
        $start = new \DateTimeImmutable($anchor);
        $date = static fn (int $offset): string => $start->modify("$offset days")->format('Y-m-d');
        $rows = [
            self::event(1, $date(-8), $date(35)),
            self::event(2, $date(-3), $date(2)),
            self::event(3, $date(4), $date(10)),
            self::event(4, $date(0), $date(0)),
            self::event(5, $date(1), null),
            self::event(6, $date(-20), $date(-18)),
            self::event(7, $date(40), $date(43)),
            self::event(8, $date(3), $date(2)),
            self::event(9, $date(2), $date(4), ['times' => null, 'endtimes' => null]),
            self::event(10, $date(4), $date(10), ['title' => 'Event 3', 'recurrence_first_id' => 3]),
        ];
        // Preserve opaque metadata/ACL fields rather than evaluating ACL in this post-query transformation.
        $rows[0]->categories = [(object) ['id' => 7, 'access' => 3]];
        $rows[0]->access = 3;
        $rows[0]->published = 2;
        if (class_exists(\JemCalendarSchedule::class, false)) {
            $rows[] = self::programme($anchor);
        }
        return $rows;
    }

    /** @param int $id ID. @param string $start Start date. @param ?string $end End date. @param array $extra Overrides. @return Event Fixture. */
    private static function event(int $id, string $start, ?string $end, array $extra = []): Event
    {
        return new Event($extra + [
            'id' => $id, 'dates' => $start, 'enddates' => $end,
            'datesdiff' => $end === null ? null : (new \DateTimeImmutable($start, new \DateTimeZone('UTC')))
                ->diff(new \DateTimeImmutable($end, new \DateTimeZone('UTC')))->format('%r%a'),
            'times' => '10:00:00', 'endtimes' => '12:00:00', 'title' => 'Event ' . $id,
        ]);
    }

    /** @param string $start Start date. @return Event Programme with closed day, two slots and all-day occurrence. */
    private static function programme(string $start): Event
    {
        return self::event(20, $start, (new \DateTimeImmutable($start))->modify('+3 days')->format('Y-m-d'), [
            'timezone' => 'Europe/Madrid',
            'attribs' => ['event_schedule' => ['version' => 1, 'mode' => 'per_day', 'days' => [
                ['offset' => 0, 'closed' => true, 'all_day' => false, 'slots' => []],
                ['offset' => 1, 'closed' => false, 'all_day' => false, 'slots' => [
                    ['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00'],
                ]],
                ['offset' => 2, 'closed' => false, 'all_day' => true, 'slots' => []],
                ['offset' => 3, 'closed' => false, 'all_day' => false, 'slots' => [['start' => '10:00', 'end' => '13:00']]],
            ]]],
        ]);
    }

    /** @param array $rows Synthetic rows. @return array Deep copy for independent mutating algorithms. */
    private static function copy(array $rows): array
    {
        return unserialize(serialize($rows));
    }
}

/** Count real PHP date modifications without replacing their calendar arithmetic. */
class CountingDate extends \DateTimeImmutable
{
    public static int $modifications = 0;

    /** @param string $modifier Relative date operation. @return static Actual modified date. */
    public function modify(string $modifier): static
    {
        self::$modifications++;
        return parent::modify($modifier);
    }
}

/** Count cloned synthetic query records. */
#[\AllowDynamicProperties]
class Event
{
    public static int $clones = 0;

    /** @param array $values Record fields. */
    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }

    /** @return void Count allocation without changing copied fields. */
    public function __clone()
    {
        self::$clones++;
    }
}

/** Query/state boundary; production transformation methods are loaded into derived classes. */
class ModelBoundary
{
    use LegacyCalendarExpansion;

    public array $items = [];

    /** @param array $state Model state. */
    public function __construct(public array $state)
    {
    }

    /** @param string $key State key. @param mixed $default Fallback. @return mixed Value. */
    public function getState(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /** @return array Synthetic preselected query rows. */
    public function getItems()
    {
        return $this->items;
    }

    /** @param array $rows Synthetic query rows. @return array Production transformation. */
    public function run(array $rows): array
    {
        return $this->calendarMultiday($rows);
    }
}

/** Minimal request/menu bag. */
class Bag
{
    /** @param array $values Values. */
    public function __construct(private array $values)
    {
    }

    /** @param string $key Key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Key. @param int $default Fallback. @return int Integer. */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
}

class Application
{
    public Bag $input;
    public Bag $params;

    /** @param array $input Request. @param array $params Menu settings. */
    public function __construct(array $input, array $params)
    {
        $this->input = new Bag($input);
        $this->params = new Bag($params);
    }

    /** @return Bag Menu parameters. */
    public function getParams(): Bag
    {
        return $this->params;
    }
}

class Factory
{
    public static Application $app;

    /** @return Application Current fixture application. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

class JemHelper
{
    /** @return string Stable site-local date. */
    public static function getJoomlaDate(): string
    {
        return '2026-11-01';
    }

    /** @param object $item Event. @return string Fixture event timezone. */
    public static function getEventTimeZoneName(object $item): string
    {
        return $item->timezone ?? 'Europe/Madrid';
    }
}
