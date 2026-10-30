<?php

declare(strict_types=1);

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Regressions for #2387/#2402: real administrator queries against an isolated database. */
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

    /**
     * Populate an administrator model using synthetic input and optional existing session state.
     *
     * @param array $request Request fields, in legacy or Search Tools format.
     * @param bool  $reuseApplication Retain the previous test application's user state.
     * @return JemModelEvents The actual list model using only the in-memory database.
     */
    private function model(array $request, bool $reuseApplication = false): JemModelEvents
    {
        if ($reuseApplication) {
            Factory::$application->setRequest($request);
        } else {
            Factory::$application = new AdminSearchApplication($request);
        }
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
        $overlapCases = [
            'no boundaries' => ['', '', range(1, 14)],
            'end only' => ['', '2026-01-01', [1, 2, 4, 5, 6, 7, 11, 12, 13]],
            'start only' => ['2025-12-30', '', [1, 2, 3, 4, 5, 6, 7, 12, 13, 14]],
            'crossing either boundary' => ['2025-12-30', '2026-01-01', [1, 2, 4, 5, 6, 7, 12, 13]],
            'inclusive same day' => ['2026-01-01', '2026-01-01', [2, 5, 6, 7, 12, 13]],
            'inclusive lower bound' => ['2026-01-01', '', [2, 3, 5, 6, 7, 12, 13, 14]],
            'earlier upper bound' => ['', '2025-12-29', [11, 12]],
            'future upper bound' => ['', '2027-04-19', [1, 2, 3, 4, 5, 6, 7, 11, 12, 13, 14]],
            'future lower bound' => ['2027-04-19', '', [3, 14]],
            'future same day' => ['2027-04-19', '2027-04-19', [3, 14]],
            'reversed interval' => ['2026-01-02', '2025-12-30', []],
        ];
        foreach ($overlapCases as $name => [$start, $end, $expected]) {
            foreach (['flat', 'nested'] as $mode) {
                yield 'overlap ' . $name . ' ' . $mode => [$mode, $start, $end, $expected, 'overlap'];
            }
        }
    }

    #[DataProvider('dateRanges')]
    public function testDateFiltersAndTotals(string $mode, string $start, string $end, array $expected, string $dateMatch = ''): void
    {
        $request = $mode === 'flat'
            ? ['filter_begin' => $start, 'filter_end' => $end, 'filter_date_match' => $dateMatch]
            : ['filter' => ['begin' => $start, 'end' => $end, 'date_match' => $dateMatch]];
        $model = $this->model($request);
        self::assertSame($start, $model->getState('filter_begin'));
        self::assertSame($end, $model->getState('filter_end'));
        self::assertSame($start, $model->getState('filter.begin'));
        self::assertSame($end, $model->getState('filter.end'));
        self::assertSame($dateMatch, $model->getState('filter.date_match'));
        self::assertSame($dateMatch, $model->getState('filter_date_match'));
        self::assertSame($expected, $this->ids($model));
        $total = $model->getTotal();
        self::assertSame(count($expected), $total, (string) $model->getError());
        self::assertSame(
            $start !== '' && $end !== '' && $start > $end ? 'COM_JEM_EVENTS_FILTER_DATE_REVERSED' : '',
            $model->getState('filter.date_error')
        );
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

    /**
     * Supply invalid boundaries in both request formats and matching modes.
     *
     * @return iterable Test requests; the valid opposite boundary isolates each invalid value.
     */
    public static function invalidDates(): iterable
    {
        $invalid = ['2026-02-29', '2026-13-01', '0000-00-00', '2026-1-01', '2026-01-01 OR 1=1', '0', ['2026-01-01']];
        foreach ($invalid as $index => $value) {
            foreach (['', 'overlap'] as $match) {
                foreach (['flat', 'nested'] as $format) {
                    foreach (['begin', 'end'] as $side) {
                        $filter = ['begin' => '2026-01-01', 'end' => '2026-12-31', 'date_match' => $match];
                        $filter[$side] = $value;
                        $request = $format === 'nested' ? ['filter' => $filter] : [
                            'filter_begin' => $filter['begin'], 'filter_end' => $filter['end'], 'filter_date_match' => $match,
                        ];
                        yield $index . ' ' . $format . ' ' . $match . ' ' . $side => [$request];
                    }
                }
            }
        }
    }

    /**
     * Reject malformed dates before SQL and keep a warning instead of a model error.
     *
     * @param array $request Synthetic invalid filter input.
     * @return void
     */
    #[DataProvider('invalidDates')]
    public function testInvalidDateInputDoesNotWidenTheQuery(array $request): void
    {
        $model = $this->model($request);
        self::assertSame([], $this->ids($model));
        self::assertSame(0, $model->getTotal());
        self::assertSame('COM_JEM_EVENTS_FILTER_DATE_INVALID', $model->getState('filter.date_error'));
        self::assertEmpty($model->getErrors());
    }

    /**
     * Treat legacy zero ends as absent only in overlap mode, without changing stored dates.
     *
     * @return void
     */
    public function testOverlapHandlesLegacyZeroDatesWithoutChangingRecords(): void
    {
        foreach ([15 => ['2026-01-01', '0000-00-00'], 16 => ['0000-00-00', null], 17 => ['0000-00-00', '2026-01-01']] as $id => [$start, $end]) {
            $row = (object) ['id' => $id, 'dates' => $start, 'enddates' => $end];
            $this->db->insertObject('#__jem_events', $row);
        }
        $before = $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList();
        foreach ([
            ['2026-01-01', '2026-01-01', [2, 5, 6, 7, 12, 13, 15]],
            ['2026-01-02', '', [3, 6, 7, 12, 14]],
            ['', '2026-01-01', [1, 2, 4, 5, 6, 7, 11, 12, 13, 15]],
            ['', '', range(1, 17)],
        ] as [$start, $end, $expected]) {
            $model = $this->model(['filter' => ['begin' => $start, 'end' => $end, 'date_match' => 'overlap']]);
            self::assertSame($expected, $this->ids($model));
            self::assertSame(count($expected), $model->getTotal());
        }
        self::assertSame($before, $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList());
    }

    /**
     * Keep real leap days and historical dates valid without a moving year cutoff.
     *
     * @return void
     */
    public function testValidLeapAndHistoricalDatesAreAccepted(): void
    {
        foreach (['2028-02-29', '1970-01-01'] as $date) {
            $this->db->setQuery('UPDATE #__jem_events SET dates = ' . $this->db->quote($date) . ' WHERE id = 1')->execute();
            foreach (['', 'overlap'] as $match) {
                $model = $this->model(['filter' => ['begin' => $date, 'end' => $date, 'date_match' => $match]]);
                self::assertSame([1], $this->ids($model));
                self::assertSame('', $model->getState('filter.date_error'));
            }
        }
    }

    /**
     * The new option must not bypass existing publication, access, venue or type filters.
     *
     * @return void
     */
    public function testOverlapKeepsOtherFiltersAndEventData(): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET published = 0, access = 2, locid = 9, type_id = 3, featured = 1 WHERE id = 12')->execute();
        $before = $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList();
        $filter = [
            'begin' => '2026-01-01', 'end' => '2026-01-01', 'date_match' => 'overlap',
            'published' => '0', 'access' => 2, 'venue_id' => 9, 'event_type_id' => 3, 'featured' => '1', 'search' => 'id:12',
        ];
        $model = $this->model(['filter' => $filter]);
        self::assertSame([12], $this->ids($model));
        self::assertSame(1, $model->getTotal());
        foreach (['published' => '1', 'access' => 1, 'venue_id' => 8, 'event_type_id' => 2, 'featured' => '0'] as $key => $value) {
            self::assertSame([], $this->ids($this->model(['filter' => array_replace($filter, [$key => $value])])));
        }
        self::assertSame($before, $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList());
    }

    /**
     * Distinct match modes must invalidate Joomla's query/total cache and preserve paging.
     *
     * @return void
     */
    public function testMatchModeIsPartOfTheCacheIdentityAndPagination(): void
    {
        $model = $this->model(['filter_begin' => '2026-01-01', 'filter_end' => '2026-01-01']);
        $storeId = new ReflectionMethod($model, 'getStoreId');
        $containedId = $storeId->invoke($model);
        self::assertSame(2, $model->getTotal());
        $model->setState('filter_date_match', 'overlap');
        $model->setState('filter.date_match', 'overlap');
        self::assertNotSame($containedId, $storeId->invoke($model));
        self::assertSame(6, $model->getTotal());
        $query = (new ReflectionMethod($model, '_getListQuery'))->invoke($model);
        foreach ([[0, [2, 5]], [2, [6, 7]], [4, [12, 13]]] as [$offset, $expected]) {
            $rows = (new ReflectionMethod($model, '_getList'))->invoke($model, $query, $offset, 2);
            self::assertSame($expected, array_map(static fn ($row): int => (int) $row->id, $rows));
        }
        $model->setState('filter_date_match', '');
        $model->setState('filter.date_match', '');
        self::assertSame($containedId, $storeId->invoke($model));
        self::assertSame(2, $model->getTotal());
    }

    /**
     * Persist the selection between requests and restore the default with Clear.
     *
     * @return void
     */
    public function testMatchModePersistsAndClearsWithSearchTools(): void
    {
        $first = $this->model(['filter' => ['begin' => '2026-01-01', 'end' => '2026-01-01', 'date_match' => 'overlap']]);
        self::assertSame(6, $first->getTotal());
        $next = $this->model([], true);
        self::assertSame('overlap', $next->getState('filter_date_match'));
        self::assertSame(6, $next->getTotal());
        self::assertSame('overlap', $next->getActiveFilters()['date_match']);
        $cleared = $this->model(['filter' => ['begin' => '', 'end' => '', 'date_match' => '']], true);
        self::assertSame('', $cleared->getState('filter_date_match'));
        self::assertSame('', $cleared->getState('filter.date_match'));
        self::assertArrayNotHasKey('date_match', $cleared->getActiveFilters());
        self::assertSame(14, $cleared->getTotal());
        self::assertSame(14, $this->model([], true)->getTotal());
    }

    /**
     * Invalid option values cannot silently opt into the broader overlap policy.
     *
     * @return void
     */
    public function testUnknownModeFallsBackToContainment(): void
    {
        foreach (['other', ['overlap']] as $mode) {
            $model = $this->model(['filter' => ['begin' => '2026-01-01', 'end' => '2026-01-01', 'date_match' => $mode]]);
            self::assertSame('', $model->getState('filter.date_match'));
            self::assertSame('', $model->getState('filter_date_match'));
            self::assertSame([2, 13], $this->ids($model));
            self::assertArrayNotHasKey('date_match', $model->getActiveFilters());
        }
    }
}
