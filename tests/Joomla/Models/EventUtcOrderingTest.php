<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Forum 13235: production table/helper/ordering against disposable SQLite. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventUtcOrderingTest extends TestCase
{
    private EventUtcOrderingDatabase $db;
    private JemModelEventslist $model;

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
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ROOT', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/site/models/eventslist.php';
        require_once JEM_TEST_ROOT . '/admin/tables/event.php';
        require_once JEM_TEST_ROOT . '/tests/Support/event_utc_ordering_environment.php';

        $this->db = new EventUtcOrderingDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Registry(['offset' => 'Europe/Madrid']);
        Factory::$language = new class {
            public function getTag(): string
            {
                return 'en-GB';
            }
        };
        $user = (new ReflectionClass(JemUser::class))->newInstanceWithoutConstructor();
        $user->id = 7;
        (new ReflectionProperty(JemUserAbstract::class, 'instances_jemuser'))->setValue(null, [7 => $user]);
        Factory::$application = new EventUtcOrderingApplication($user);
        $config = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(JemConfig::class, '_data'))->setValue($config, new Registry([
            'imageenabled' => 0, 'image_filetypes' => 'jpg,png',
        ]));
        (new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $config);
        (new ReflectionProperty(\Joomla\CMS\Component\ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new \Joomla\CMS\Component\ComponentRecord(['params' => '{}']),
        ]);
        $this->model = (new ReflectionClass(JemModelEventslist::class))->newInstanceWithoutConstructor();
        $this->model->setDatabase($this->db);
        $this->db->setQuery('CREATE TABLE t_jem_venues (id INTEGER PRIMARY KEY, timezone TEXT)')->execute();
        $this->db->setQuery("INSERT INTO t_jem_venues VALUES (1, 'America/New_York')")->execute();
        $this->db->setQuery('CREATE TABLE t_jem_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, dates TEXT, times TEXT, enddates TEXT, endtimes TEXT,
            start_utc TEXT, end_utc TEXT, timezone_mode TEXT, timezone TEXT, locid INTEGER DEFAULT 0,
            published INTEGER DEFAULT 1, access INTEGER DEFAULT 1, created TEXT, created_by INTEGER,
            modified TEXT, modified_by INTEGER, created_by_alias TEXT, language TEXT,
            datimage TEXT DEFAULT "", fullimage TEXT DEFAULT "", image_path TEXT DEFAULT "", attribs TEXT
        )')->execute();
        $this->db->queries = [];
    }

    private function insert(array $data): void
    {
        $event = (object) array_merge(['dates' => '2026-11-06', 'times' => '10:00:00', 'timezone_mode' => 'joomla'], $data);
        $this->db->insertObject('#__jem_events', $event);
    }

    private function query()
    {
        return $this->db->getQuery(true)->select('a.id')->from('#__jem_events AS a')
            ->leftJoin('#__jem_venues AS l ON l.id = a.locid')
            ->where('a.published = 1')->where('a.access = 1')->group('a.id');
    }

    private function ordering($query, array $order): array
    {
        return (new ReflectionMethod(JemModelEventslist::class, 'getEventOrdering'))->invoke($this->model, $query, $order);
    }

    #[DataProvider('missingTimes')]
    public function testMissingUtcIsConvertedBeforeLimitAndOffset(array $missing, array $expected): void
    {
        $this->insert(['id' => 1, 'start_utc' => '2026-11-06 09:00:00']);
        $this->insert(array_merge(['id' => 2], $missing));
        $this->db->queries = [];
        $query = $this->query();
        $before = (string) $query;
        $query->order($this->ordering($query, ['a.start_utc ASC', 'a.dates ASC', 'a.times ASC', 'a.created ASC']));
        self::assertSame($expected, array_map('intval', $this->db->setQuery($query)->loadColumn()));
        self::assertSame($expected[0], (int) $this->db->setQuery($query, 0, 1)->loadResult());
        self::assertSame($expected[1], (int) $this->db->setQuery($query, 1, 1)->loadResult());
        self::assertStringStartsWith($before, (string) $query);
        foreach ($this->db->queries as $sql) {
            self::assertStringStartsWith('SELECT', ltrim($sql));
            self::assertStringNotContainsString('CONVERT_TZ', $sql);
        }
        self::assertNull($this->db->setQuery('SELECT start_utc FROM t_jem_events WHERE id = 2')->loadResult());
    }

    public static function missingTimes(): iterable
    {
        yield 'later legacy event' => [['dates' => '2026-11-25'], [1, 2]];
        yield 'earlier legacy event' => [['dates' => '2026-11-04'], [2, 1]];
        yield 'custom crosses midnight' => [[
            'dates' => '2026-11-07', 'times' => '01:00:00', 'timezone_mode' => 'custom', 'timezone' => 'Pacific/Kiritimati',
        ], [1, 2]];
        yield 'custom earlier despite later wall time' => [[
            'times' => '22:00:00', 'timezone_mode' => 'custom', 'timezone' => 'Pacific/Kiritimati',
        ], [2, 1]];
        yield 'venue later despite earlier wall time' => [[
            'times' => '08:00:00', 'timezone_mode' => 'venue', 'locid' => 1,
        ], [1, 2]];
        yield 'invalid custom falls back to Joomla' => [[
            'times' => '09:30:00', 'timezone_mode' => 'custom', 'timezone' => 'Invalid/Zone',
        ], [2, 1]];
        yield 'missing time is midnight' => [['times' => null], [2, 1]];
    }

    public function testFallbackKeepsFiltersAndOriginalQueryAndSupportsDescending(): void
    {
        $this->insert(['id' => 1, 'start_utc' => '2026-11-06 09:00:00']);
        $this->insert(['id' => 2, 'dates' => '2026-11-25']);
        $this->insert(['id' => 3, 'access' => 2]);
        $this->insert(['id' => 4, 'published' => 0]);
        $this->insert(['id' => 5, 'dates' => null]);
        $this->insert(['id' => 6]);
        $query = $this->query()->where('a.id <> 6'); // Also retain caller/category/date restrictions.
        $before = (string) $query;
        $ordering = $this->ordering($query, ['a.start_utc DESC', 'a.created DESC']);
        self::assertSame($before, (string) $query);
        self::assertStringContainsString('WHEN 2 THEN', $ordering[0]);
        foreach ([3, 4, 5, 6] as $excluded) {
            self::assertStringNotContainsString('WHEN ' . $excluded . ' THEN', $ordering[0]);
        }
        self::assertSame('a.created DESC', $ordering[1]);
        $query->order($ordering);
        self::assertSame([2, 1, 5], array_map('intval', $this->db->setQuery($query)->loadColumn()));
    }

    public function testArchiveAndOtherOrderingDoNotRunFallbackQuery(): void
    {
        $ordering = ['a.dates DESC', 'a.times DESC', 'a.created DESC'];
        self::assertSame($ordering, $this->ordering($this->query(), $ordering));
        self::assertSame([], $this->db->queries);
    }

    public function testValidUtcRemainsUnchanged(): void
    {
        $this->insert(['id' => 1, 'start_utc' => '2026-11-06 09:00:00']);
        $this->db->queries = [];
        self::assertSame(['a.start_utc ASC'], $this->ordering($this->query(), ['a.start_utc ASC']));
        self::assertCount(1, $this->db->queries);
    }

    public function testManyMissingVenueValuesUseOneReadAndDstAtTheEventDate(): void
    {
        for ($id = 1; $id <= 500; $id++) {
            $this->insert(['id' => $id, 'dates' => '2026-11-06', 'timezone_mode' => 'venue', 'locid' => 1]);
        }
        $this->db->queries = [];
        $order = $this->ordering($this->query(), ['a.start_utc ASC']);
        self::assertCount(1, $this->db->queries);
        // New York is UTC-5 in November, regardless of the timezone/season today.
        self::assertSame(500, substr_count($order[0], "THEN '2026-11-06 15:00:00'"));
        self::assertStringNotContainsString('a.*', $this->db->queries[0]);
        self::assertStringNotContainsString('LIMIT', $this->db->queries[0]);
    }

    public function testNullUtcNoLongerWinsOverTheNearestEventWithCountOne(): void
    {
        $this->insert(['id' => 1, 'start_utc' => '2026-11-06 09:00:00']);
        $this->insert(['id' => 2, 'dates' => '2026-11-25']);
        $raw = $this->query()->order('a.start_utc ASC, a.dates ASC');
        self::assertSame(2, (int) $this->db->setQuery($raw, 0, 1)->loadResult());
        $fixed = $this->query();
        $fixed->order($this->ordering($fixed, ['a.start_utc ASC', 'a.dates ASC']));
        self::assertSame(1, (int) $this->db->setQuery($fixed, 0, 1)->loadResult());
    }

    #[DataProvider('recurrenceDates')]
    public function testDirectStoreRecalculatesCopiedUtcWithoutCheck(array $changes, ?string $start, ?string $end): void
    {
        $table = new JemTableEvent($this->db);
        $source = array_merge([
            'id' => 0, 'dates' => '2026-10-28', 'enddates' => '2026-10-30',
            'times' => '10:00:00', 'endtimes' => '18:00:00', 'timezone_mode' => 'joomla',
            'start_utc' => '2026-10-21 08:00:00', 'end_utc' => '2026-10-23 16:00:00',
            'created' => '2026-10-01 12:00:00',
        ], $changes);
        self::assertTrue($table->bind($source));
        $table->_autocreate = true;
        self::assertTrue($table->store());
        $stored = $this->db->setQuery('SELECT * FROM t_jem_events WHERE id = ' . (int) $table->id)->loadObject();
        self::assertSame($start, $stored->start_utc);
        self::assertSame($end, $stored->end_utc);
        foreach (['dates', 'enddates', 'times', 'endtimes', 'created'] as $field) {
            self::assertSame($source[$field], $stored->$field);
        }
    }

    public static function recurrenceDates(): iterable
    {
        yield 'Joomla DST and multi-day' => [[], '2026-10-28 09:00:00', '2026-10-30 17:00:00'];
        yield 'explicit timezone' => [[
            'timezone_mode' => 'custom', 'timezone' => 'Asia/Tokyo',
        ], '2026-10-28 01:00:00', '2026-10-30 09:00:00'];
        yield 'venue timezone' => [[
            'timezone_mode' => 'venue', 'locid' => 1,
        ], '2026-10-28 14:00:00', '2026-10-30 22:00:00'];
        yield 'open date clears copied values' => [['dates' => null, 'enddates' => null], null, null];
        yield 'all-day' => [[
            'times' => null, 'endtimes' => null, 'enddates' => null,
        ], '2026-10-27 23:00:00', '2026-10-28 22:59:59'];
    }

    public function testExistingInstallerRebuildRepairsStaleAndMissingValues(): void
    {
        $this->insert(['id' => 1, 'dates' => '2026-10-28', 'start_utc' => '2026-10-21 08:00:00']);
        $this->insert(['id' => 2, 'dates' => '2026-10-30', 'timezone_mode' => 'venue', 'locid' => 1]);
        require_once JEM_TEST_ROOT . '/script.php';
        (new ReflectionMethod(com_jemInstallerScript::class, 'rebuildEventUtcDates'))->invoke(new com_jemInstallerScript());
        self::assertSame([
            ['dates' => '2026-10-28', 'times' => '10:00:00', 'start_utc' => '2026-10-28 09:00:00'],
            ['dates' => '2026-10-30', 'times' => '10:00:00', 'start_utc' => '2026-10-30 14:00:00'],
        ], $this->db->setQuery('SELECT dates, times, start_utc FROM t_jem_events ORDER BY id')->loadAssocList());
    }

    public function testProductionQueryUsesTheOrderingBeforePagination(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        self::assertStringContainsString('$query->order($this->getEventOrdering($query, (array) $orderby));', $source);
        foreach (['mod_jem', 'mod_jem_wide', 'mod_jem_teaser'] as $module) {
            self::assertStringContainsString('a.start_utc ASC', file_get_contents(JEM_TEST_ROOT . '/modules/' . $module . '/helper.php'));
        }
        self::assertStringContainsString('$this->rebuildEventUtcDates();', file_get_contents(JEM_TEST_ROOT . '/script.php'));
    }
}
