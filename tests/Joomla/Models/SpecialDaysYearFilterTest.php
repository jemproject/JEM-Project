<?php

declare(strict_types=1);

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** #2388: use real list models and SQL, without modifying an installed site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SpecialDaysYearFilterTest extends TestCase
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
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');

        $this->db = new AdminSearchDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(\Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        foreach ([
            'jem_special_days' => 'id INTEGER PRIMARY KEY, title TEXT, description TEXT, start_date TEXT,
                end_date TEXT, weekdays TEXT, day_type_id INTEGER, day_type TEXT, ordering INTEGER DEFAULT 0,
                published INTEGER DEFAULT 1, access INTEGER DEFAULT 1, created_by INTEGER',
            'users' => 'id INTEGER, name TEXT',
            'viewlevels' => 'id INTEGER, title TEXT',
        ] as $name => $columns) {
            $this->db->setQuery('CREATE TABLE t_' . $name . ' (' . $columns . ')')->execute();
        }
    }

    private function model(string $client, array $request = [], int $startMonth = 1): JemModelSpecialdays
    {
        require_once JEM_TEST_ROOT . '/' . $client . '/models/specialdays.php';
        (new ReflectionProperty(ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new ComponentRecord(['params' => json_encode(['annual_start_month' => $startMonth])]),
        ]);
        Factory::$application = new AdminSearchApplication($request);
        $model = new JemModelSpecialdays(['dbo' => $this->db]);
        $model->getState();
        return $model;
    }

    private function insert(int $id, ?string $start, ?string $end, int $published = 1): void
    {
        $row = (object) [
            'id' => $id, 'title' => 'Rule ' . $id, 'start_date' => $start,
            'end_date' => $end, 'weekdays' => '0,6', 'published' => $published,
        ];
        $this->db->insertObject('#__jem_special_days', $row);
    }

    private function ids(JemModelSpecialdays $model): array
    {
        $query = (new ReflectionMethod($model, 'getListQuery'))->invoke($model);
        return array_map('intval', $this->db->setQuery($query)->loadColumn());
    }

    private static function currentYear(int $startMonth): int
    {
        $today = new DateTimeImmutable('now');
        return (int) $today->format('Y') - ((int) $today->format('n') < $startMonth ? 1 : 0);
    }

    public static function ranges(): iterable
    {
        $cases = [
            'reported weekend rule' => ['1900-01-01', '2100-12-31', 1, 2026, range(1900, 2100)],
            'single day' => ['2026-04-03', '2026-04-03', 1, 2026, [2026]],
            'cross year' => ['2026-12-31', '2027-01-01', 1, 2027, [2026, 2027]],
            'January in September period' => ['2027-01-01', '2027-01-31', 9, 2026, [2026]],
            'annual boundary inclusive' => ['2026-08-31', '2026-09-01', 9, 2025, [2025, 2026]],
        ];
        foreach (['admin', 'site'] as $client) {
            foreach ($client === 'admin' ? ['flat', 'nested'] : ['flat'] as $mode) {
                foreach ($cases as $label => $case) {
                    yield "$client $mode $label" => [$client, $mode, ...$case];
                }
            }
        }
    }

    #[DataProvider('ranges')]
    public function testOptionsSelectionAndResults(
        string $client,
        string $mode,
        string $start,
        string $end,
        int $startMonth,
        int $selected,
        array $expected
    ): void {
        $this->insert(1, $start, $end);
        $before = $this->db->setQuery('SELECT * FROM t_jem_special_days')->loadAssocList();
        $request = $mode === 'flat' ? ['filter_year' => $selected] : ['filter' => ['year' => $selected]];
        $model = $this->model($client, $request, $startMonth);
        if ($client === 'site') {
            $expected[] = self::currentYear($startMonth);
        }
        $expected = array_combine($expected, $expected);
        ksort($expected);
        self::assertSame($expected, $model->getAvailableYears());
        self::assertSame($selected, (int) $model->getState('filter.year'));
        self::assertSame([1], $this->ids($model));
        self::assertSame(1, $model->getTotal(), (string) $model->getError());
        self::assertSame($before, $this->db->setQuery('SELECT * FROM t_jem_special_days')->loadAssocList());
    }

    public static function clients(): array
    {
        return [['admin'], ['site']];
    }

    #[DataProvider('clients')]
    public function testDefaultsToTheCurrentAnnualPeriod(string $client): void
    {
        $this->insert(1, '1900-01-01', '2100-12-31');
        $model = $this->model($client, [], 12);
        self::assertSame(self::currentYear(12), (int) $model->getState('filter.year'));
        self::assertSame([1], $this->ids($model));
    }

    #[DataProvider('clients')]
    public function testEmptyListHasSafeDefault(string $client): void
    {
        $model = $this->model($client, [], 9);
        $year = self::currentYear(9);
        self::assertSame($client === 'site' ? [$year => $year] : [], $model->getAvailableYears());
        self::assertSame($year, (int) $model->getState('filter.year'));
        self::assertSame([], $this->ids($model));
    }

    #[DataProvider('clients')]
    public function testPublicationPolicyAndOtherFiltersArePreserved(string $client): void
    {
        $this->insert(1, '2025-01-01', '2027-12-31', 1);
        $this->insert(2, '2025-01-01', '2027-12-31', 0);
        $this->insert(3, '1880-01-01', '1890-12-31', 2);
        $this->insert(4, '2200-01-01', '2210-12-31', -2);
        $model = $this->model($client, ['filter_year' => 2026, 'filter_state' => '0', 'filter_search' => 'Rule 2']);
        self::assertArrayNotHasKey(1880, $model->getAvailableYears());
        self::assertArrayNotHasKey(2200, $model->getAvailableYears());
        self::assertSame([2], $this->ids($model));
        self::assertSame(1, $model->getTotal());
    }

    public function testAdminKeepsIntermediateYearInSessionAndResetsUnknownYear(): void
    {
        $this->insert(1, '1900-01-01', '2100-12-31');
        $model = $this->model('admin', ['filter' => ['year' => 2027]]);
        self::assertSame(2027, (int) $model->getState('filter_year'));
        Factory::$application->setRequest([]);
        $reloaded = new JemModelSpecialdays(['dbo' => $this->db]);
        self::assertSame(2027, (int) $reloaded->getState('filter.year'));
        Factory::$application->setRequest(['filter' => ['year' => 9999]]);
        $invalid = new JemModelSpecialdays(['dbo' => $this->db]);
        self::assertSame(self::currentYear(1), (int) $invalid->getState('filter.year'));
    }

    #[DataProvider('clients')]
    public function testOpenRulesOfferCurrentPeriodAndKnownBoundary(string $client): void
    {
        $year = self::currentYear(1);
        $this->insert(1, ($year - 10) . '-01-01', null);
        $this->insert(2, null, null);
        $model = $this->model($client, ['filter_year' => $year]);
        $expected = [$year - 10, ...range($year - 2, $year + 2)];
        self::assertSame(array_combine($expected, $expected), $model->getAvailableYears());
        self::assertSame([1, 2], $this->ids($model));
        self::assertSame(2, $model->getTotal());
    }

    #[DataProvider('clients')]
    public function testOptionReadFailureUsesExistingFallback(string $client): void
    {
        $model = $this->model($client);
        $this->db->setQuery('DROP TABLE t_jem_special_days')->execute();
        $year = self::currentYear(1);
        self::assertSame($client === 'site' ? [$year => $year] : [], $model->getAvailableYears());
    }

    public function testAdminDefaultWithoutCurrentRulesStillUsesNearestAvailablePeriod(): void
    {
        $year = self::currentYear(1);
        $this->insert(1, ($year - 10) . '-01-01', ($year - 5) . '-12-31');
        $model = $this->model('admin');
        self::assertSame($year - 5, (int) $model->getState('filter.year'));
        $this->insert(2, ($year + 5) . '-01-01', ($year + 10) . '-12-31');
        $model = $this->model('admin');
        self::assertSame($year + 5, (int) $model->getState('filter.year'));
    }
}
