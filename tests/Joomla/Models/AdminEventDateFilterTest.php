<?php

declare(strict_types=1);

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Regression for #2387: real administrator model queries against an isolated database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminEventDateFilterTest extends TestCase
{
    private AdminSearchDatabase $db;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite; no installed site is changed.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/admin_search_environment.php';
        require_once JEM_TEST_ROOT . '/admin/models/events.php';
        $this->db = new AdminSearchDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(\Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        (new ReflectionProperty(ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new ComponentRecord(['params' => '{}']),
        ]);

        foreach ([
            'jem_events' => 'id INTEGER PRIMARY KEY, dates TEXT, enddates TEXT, title TEXT, alias TEXT,
                locid INTEGER, parent_event_id INTEGER, created_by INTEGER, modified_by INTEGER,
                checked_out INTEGER, access INTEGER DEFAULT 1, type_id INTEGER,
                published INTEGER DEFAULT 1, featured INTEGER DEFAULT 0',
            'jem_venues' => 'id INTEGER, venue TEXT, city TEXT, state TEXT, country TEXT, created_by INTEGER, checked_out INTEGER',
            'users' => 'id INTEGER, name TEXT, email TEXT',
            'viewlevels' => 'id INTEGER, title TEXT',
            'jem_countries' => 'iso2 TEXT, name TEXT',
            'jem_types' => 'id INTEGER, name TEXT, entity INTEGER',
        ] as $name => $columns) {
            $this->db->setQuery('CREATE TABLE t_' . $name . ' (' . $columns . ')')->execute();
        }
        foreach ([
            1 => ['2025-12-31', null], // Single-day event before the upper boundary.
            2 => ['2026-01-01', null], // Single-day event on the upper boundary.
            3 => ['2027-04-19', null], // Future single-day event reported in #2387.
            4 => ['2025-12-30', '2025-12-31'],
            5 => ['2025-12-31', '2026-01-01'],
            6 => ['2025-12-31', '2026-01-02'], // Spans the upper boundary.
            7 => ['2026-01-01', '2026-01-02'],
            8 => [null, null], // Undated: retain the existing one-boundary policy.
            9 => [null, '2025-12-31'], // An explicit end still restricts an unknown start.
            10 => [null, '2027-04-19'],
            11 => ['2025-12-29', null],
            12 => ['2025-12-29', '2026-01-02'], // Spans both boundaries.
            13 => ['2026-01-01', '2026-01-01'], // Explicit same-day end.
            14 => ['2027-04-18', '2027-04-19'],
        ] as $id => [$start, $end]) {
            $row = (object) ['id' => $id, 'dates' => $start, 'enddates' => $end, 'title' => 'Event ' . $id];
            $this->db->insertObject('#__jem_events', $row);
        }
    }

    private function model(array $request): JemModelEvents
    {
        Factory::$application = new AdminSearchApplication($request);
        $model = new JemModelEvents(['dbo' => $this->db]);
        $model->getState();
        $model->setState('list.select', 'a.id');
        $model->setState('list.ordering', 'a.id');
        $model->setState('list.direction', 'ASC');
        return $model;
    }

    private function ids(JemModelEvents $model): array
    {
        $query = (new ReflectionMethod($model, 'getListQuery'))->invoke($model);
        return array_map('intval', $this->db->setQuery($query)->loadColumn());
    }

    public static function dateRanges(): iterable
    {
        $cases = [
            'no boundaries' => ['', '', range(1, 14)],
            'end only excludes future single-day events' => ['', '2026-01-01', [1, 2, 4, 5, 8, 9, 11, 13]],
            'start only includes undated events' => ['2025-12-30', '', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 13, 14]],
            'both boundaries require full containment' => ['2025-12-30', '2026-01-01', [1, 2, 4, 5, 13]],
            'same-day boundaries are inclusive' => ['2026-01-01', '2026-01-01', [2, 13]],
            'inclusive lower boundary' => ['2026-01-01', '', [2, 3, 7, 8, 9, 10, 13, 14]],
            'earlier upper boundary' => ['', '2025-12-29', [8, 11]],
            'future upper boundary' => ['', '2027-04-19', range(1, 14)],
            'future lower boundary' => ['2027-04-19', '', [3, 8, 9, 10]],
            'future same-day boundaries' => ['2027-04-19', '2027-04-19', [3]],
            'reversed interval' => ['2026-01-02', '2025-12-30', []],
        ];
        foreach ($cases as $name => [$start, $end, $expected]) {
            foreach (['flat', 'nested'] as $mode) {
                yield $name . ' ' . $mode => [$mode, $start, $end, $expected];
            }
        }
    }

    #[DataProvider('dateRanges')]
    public function testDateFiltersAndTotals(string $mode, string $start, string $end, array $expected): void
    {
        $request = $mode === 'flat'
            ? ['filter_begin' => $start, 'filter_end' => $end]
            : ['filter' => ['begin' => $start, 'end' => $end]];
        $model = $this->model($request);
        self::assertSame($start, $model->getState('filter_begin'));
        self::assertSame($end, $model->getState('filter_end'));
        self::assertSame($start, $model->getState('filter.begin'));
        self::assertSame($end, $model->getState('filter.end'));
        self::assertSame($expected, $this->ids($model));
        $total = $model->getTotal();
        self::assertSame(count($expected), $total, (string) $model->getError());
    }

    public function testOtherFiltersStillApplyAndDatesAreNotModified(): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET published = 0, access = 2 WHERE id = 2')->execute();
        $before = $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList();
        $model = $this->model([
            'filter_end' => '2026-01-01', 'filter_state' => '0', 'filter_access' => 2, 'filter_search' => 'id:2',
        ]);
        self::assertSame([2], $this->ids($model));
        self::assertSame(1, $model->getTotal());
        self::assertSame($before, $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList());
    }

    public function testClearingDatesRestoresTheUnfilteredList(): void
    {
        $model = $this->model(['filter_begin' => '2026-01-01', 'filter_end' => '2026-01-01']);
        self::assertSame([2, 13], $this->ids($model));
        Factory::$application->setRequest(['filter' => ['begin' => '', 'end' => '']]);
        $cleared = new JemModelEvents(['dbo' => $this->db]);
        $cleared->getState();
        $cleared->setState('list.select', 'a.id');
        $cleared->setState('list.ordering', 'a.id');
        self::assertSame('', $cleared->getState('filter_begin'));
        self::assertSame('', $cleared->getState('filter_end'));
        self::assertSame(range(1, 14), $this->ids($cleared));
        self::assertSame(14, $cleared->getTotal());
    }
}
