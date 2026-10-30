<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual Types queries and temporal helper bodies over disposable SQLite, with fixed clocks. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TypesModuleEligibilityTest extends TestCase
{
    private TypesModuleDatabase $db;
    private string $serverTimezone;

    /**
     * Load Joomla libraries and source temporal methods; replace clocks, not comparison logic.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->serverTimezone = date_default_timezone_get();
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires Joomla libraries and pdo_sqlite; no installed site/database is changed.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/types_module_environment.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $methods = '';
        foreach (['getJoomlaTimeZoneName', 'getEventDateTimeWhere', 'getEventPublicationWhere'] as $name) {
            self::assertSame(1, preg_match('/    static public function ' . $name . '\\([^\\n]*\\)\\s*\\{.*?\\n    \\}/s', $source, $match), $name);
            $methods .= $match[0] . "\n";
        }
        $methods = str_replace(
            ["new \\DateTimeImmutable('now', new \\DateTimeZone('UTC'))", 'Factory::getDate()->toSql()'],
            ["new \\DateTimeImmutable(TypesModuleClock::\$now, new \\DateTimeZone('UTC'))", 'TypesModuleClock::$now'],
            $methods,
            $replaced
        );
        self::assertSame(2, $replaced, 'Only the two clocks are substituted; SQL and timezone resolution are real.');
        eval('use Joomla\\CMS\\Factory; final class JemHelper extends TypesModuleServices {' . $methods . '}');
        require_once JEM_TEST_ROOT . '/modules/mod_jem_types/helper.php';

        $this->db = new TypesModuleDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $this->db->connect();
        $this->db->getConnection()->sqliteCreateFunction(
            'CONCAT',
            static fn(...$parts) => in_array(null, $parts, true) ? null : implode('', $parts)
        );
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Registry(['offset' => 'Europe/Madrid']);
        Factory::$application = new TypesModuleApplication();
        date_default_timezone_set('Pacific/Honolulu');
        foreach ([
            "CREATE TABLE t_jem_types (
                id INTEGER PRIMARY KEY, name TEXT DEFAULT 'Type', alias TEXT DEFAULT '', icon TEXT DEFAULT '',
                color TEXT DEFAULT '', description TEXT DEFAULT '', base_language TEXT DEFAULT '',
                translation_languages TEXT DEFAULT '', translations TEXT DEFAULT '', language TEXT DEFAULT '*',
                published INTEGER DEFAULT 1, access INTEGER DEFAULT 1, entity INTEGER DEFAULT 1, ordering INTEGER DEFAULT 0
            )",
            "CREATE TABLE t_jem_events (
                id INTEGER PRIMARY KEY, title TEXT DEFAULT 'Event', alias TEXT DEFAULT '', attribs TEXT DEFAULT '{}',
                dates TEXT, enddates TEXT, times TEXT, endtimes TEXT, timezone_mode TEXT DEFAULT 'joomla',
                start_utc TEXT, end_utc TEXT, type_id INTEGER DEFAULT 1, recurrence_first_id INTEGER DEFAULT 0,
                published INTEGER DEFAULT 1, access INTEGER DEFAULT 1, language TEXT DEFAULT '*',
                publish_up TEXT, publish_down TEXT, locid INTEGER DEFAULT 0, article_id INTEGER DEFAULT 0,
                created_by INTEGER DEFAULT 1, parent_event_id INTEGER DEFAULT 0, show_in_calendar INTEGER DEFAULT 1,
                age_level_id INTEGER DEFAULT 0, fixture_parent_visible INTEGER DEFAULT 1, fixture_age_visible INTEGER DEFAULT 1
            )",
            "CREATE TABLE t_jem_categories (id INTEGER PRIMARY KEY, published INTEGER DEFAULT 1, access INTEGER DEFAULT 1, language TEXT DEFAULT '*')",
            "CREATE TABLE t_jem_cats_event_relations (itemid INTEGER, catid INTEGER)",
            "CREATE TABLE t_jem_venues (id INTEGER PRIMARY KEY, published INTEGER DEFAULT 1, access INTEGER DEFAULT 1, age_level_id INTEGER DEFAULT 0)",
            "CREATE TABLE t_jem_age_levels (id INTEGER PRIMARY KEY, title TEXT, min_age INTEGER, max_age INTEGER, badge_label TEXT, badge_background TEXT, badge_text TEXT)",
            "INSERT INTO t_jem_types (id) VALUES (1), (2)",
            "INSERT INTO t_jem_categories (id, published, access, language) VALUES (1, 1, 1, '*'), (2, 0, 1, '*'), (3, 1, 2, '*'), (4, 1, 1, 'es-ES')",
            "INSERT INTO t_jem_venues (id, published, access) VALUES (1, 0, 1), (2, 1, 2), (3, 1, 1)",
        ] as $sql) {
            $this->db->setQuery($sql)->execute();
        }
    }

    /** @return void Restore the caller's server timezone. */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverTimezone);
    }

    /**
     * Add a synthetic event and its category without touching an installed database.
     *
     * @param int $id Fixture identifier.
     * @param array $changes Event properties; category_id selects the relation.
     * @return void
     */
    private function insert(int $id, array $changes = []): void
    {
        $category = $changes['category_id'] ?? 1;
        unset($changes['category_id']);
        $row = (object) array_merge([
            'id' => $id, 'dates' => '2026-10-29', 'times' => '08:00:00', 'endtimes' => '08:00:00',
        ], $changes);
        $this->db->insertObject('#__jem_events', $row);
        $relation = (object) ['itemid' => $id, 'catid' => $category];
        $this->db->insertObject('#__jem_cats_event_relations', $relation);
    }

    /** @return array Snapshot of all stored event data to detect accidental writes. */
    private function storedEvents(): array
    {
        return $this->db->setQuery('SELECT * FROM #__jem_events ORDER BY id')->loadAssocList();
    }

    /** @return iterable Fixed UTC instants, Joomla zones, event properties and expected eligibility. */
    public static function timeCases(): iterable
    {
        $now = '2026-10-29 20:13:00';
        $cases = [
            'expired morning' => [$now, 'Europe/Madrid', [], false],
            'ongoing' => [$now, 'Europe/Madrid', ['times' => '20:00:00', 'endtimes' => '22:00:00'], true],
            'later today' => [$now, 'Europe/Madrid', ['times' => '22:00:00', 'endtimes' => '23:00:00'], true],
            'tomorrow' => [$now, 'Europe/Madrid', ['dates' => '2026-10-30'], true],
            'all day' => [$now, 'Europe/Madrid', ['times' => null, 'endtimes' => null], true],
            'no end time is not one hour' => [$now, 'Europe/Madrid', ['endtimes' => null], true],
            'multi-day finished' => [$now, 'Europe/Madrid', ['dates' => '2026-10-28', 'enddates' => '2026-10-29'], false],
            'multi-day ongoing' => [$now, 'Europe/Madrid', ['dates' => '2026-10-28', 'enddates' => '2026-10-30'], true],
            'multi-day final day without end time' => [$now, 'Europe/Madrid', ['dates' => '2026-10-28', 'enddates' => '2026-10-29', 'endtimes' => null], true],
            'yesterday' => [$now, 'Europe/Madrid', ['dates' => '2026-10-28'], false],
            'undated' => [$now, 'Europe/Madrid', ['dates' => null, 'times' => null, 'endtimes' => null], false],
            'undated stale custom UTC' => [$now, 'Europe/Madrid', ['dates' => null, 'timezone_mode' => 'custom', 'end_utc' => '2030-01-01 00:00:00'], false],
            'exact finish' => [$now, 'Europe/Madrid', ['endtimes' => '21:13:00'], false],
            'one second before finish' => [$now, 'Europe/Madrid', ['endtimes' => '21:13:01'], true],
            'Joomla ignores stale UTC cache' => [$now, 'Europe/Madrid', ['end_utc' => '2030-01-01 00:00:00'], false],
            'legacy timezone null' => [$now, 'Europe/Madrid', ['timezone_mode' => null, 'endtimes' => '22:00:00'], true],
            'custom previous date ongoing' => ['2026-10-29 08:00:00', 'Europe/Madrid', ['dates' => '2026-10-28', 'endtimes' => '23:30:00', 'timezone_mode' => 'custom', 'end_utc' => '2026-10-29 09:30:00'], true],
            'custom future date finished' => [$now, 'Europe/Madrid', ['dates' => '2026-10-30', 'endtimes' => '00:30:00', 'timezone_mode' => 'custom', 'end_utc' => '2026-10-29 10:30:00'], false],
            'venue UTC ongoing' => [$now, 'Europe/Madrid', ['endtimes' => '17:00:00', 'timezone_mode' => 'venue', 'end_utc' => '2026-10-29 21:00:00'], true],
            'missing custom UTC keeps legacy fallback' => [$now, 'Europe/Madrid', ['timezone_mode' => 'custom', 'endtimes' => '22:00:00'], true],
            'Joomla tomorrow UTC today' => ['2026-10-29 23:30:00', 'Europe/Madrid', ['times' => null, 'endtimes' => null], false],
            'Joomla today UTC tomorrow' => ['2026-10-30 02:30:00', 'America/New_York', ['times' => null, 'endtimes' => null], true],
            'half-hour zone' => ['2026-10-29 03:00:00', 'Asia/Kolkata', [], false],
            'spring DST expired' => ['2026-03-29 01:30:00', 'Europe/Madrid', ['dates' => '2026-03-29', 'times' => '01:00:00', 'endtimes' => '03:00:00'], false],
            'spring DST ongoing' => ['2026-03-29 01:30:00', 'Europe/Madrid', ['dates' => '2026-03-29', 'times' => '01:00:00', 'endtimes' => '04:00:00'], true],
            'autumn first occurrence ended' => ['2026-10-25 00:45:00', 'Europe/Madrid', ['dates' => '2026-10-25', 'times' => '01:00:00', 'endtimes' => '02:30:00', 'timezone_mode' => 'custom', 'end_utc' => '2026-10-25 00:30:00'], false],
            'autumn second occurrence ongoing' => ['2026-10-25 00:45:00', 'Europe/Madrid', ['dates' => '2026-10-25', 'times' => '01:00:00', 'endtimes' => '02:30:00', 'timezone_mode' => 'custom', 'end_utc' => '2026-10-25 01:30:00'], true],
            'explicit midnight is not missing' => [$now, 'Europe/Madrid', ['dates' => '2026-10-28', 'times' => '23:00:00', 'enddates' => '2026-10-29', 'endtimes' => '00:00:00'], false],
            'unknown finish late in final day' => ['2026-10-29 22:59:58', 'Europe/Madrid', ['endtimes' => null], true],
            'unknown finish after final day' => ['2026-10-29 23:00:00', 'Europe/Madrid', ['endtimes' => null], false],
            'invalid Joomla zone falls back to UTC' => [$now, 'Invalid/Zone', ['endtimes' => '21:00:00'], true],
        ];
        yield from $cases;
    }

    /**
     * Verify expiration before LIMIT, matching Summary counts and unchanged stored values.
     *
     * @param string $now UTC reference instant.
     * @param string $zone Joomla timezone.
     * @param array $changes Event fixture overrides.
     * @param bool $eligible Whether the target event has not yet finished.
     * @return void
     */
    #[DataProvider('timeCases')]
    public function testTemporalEligibility(string $now, string $zone, array $changes, bool $eligible): void
    {
        TypesModuleClock::$now = $now;
        Factory::$config->set('offset', $zone);
        $local = (new DateTimeImmutable($now, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(JemHelper::getJoomlaTimeZoneName()));
        $this->insert(1, $changes);
        $this->insert(100, ['dates' => $local->modify('+2 days')->format('Y-m-d')]);
        $before = $this->storedEvents();
        $params = new Registry(['top_n' => 1, 'hide_empty' => 1]);

        $top = ModJemTypesHelper::getTopNByType($params);
        self::assertSame([1], array_map('intval', array_column($top, 'id')));
        self::assertSame($eligible ? [1] : [100], array_map('intval', array_column($top[0]->events, 'id')));
        $params->set('top_n', 10);
        $top = ModJemTypesHelper::getTopNByType($params);
        self::assertSame($eligible ? [1, 100] : [100], array_map('intval', array_column($top[0]->events, 'id')));
        $summary = ModJemTypesHelper::getTypeSummary($params);
        self::assertSame([1], array_map('intval', array_column($summary, 'id')));
        self::assertSame($eligible ? 2 : 1, (int) $summary[0]->event_count);

        $this->db->setQuery('DELETE FROM #__jem_events WHERE id = 100')->execute();
        self::assertCount($eligible ? 1 : 0, ModJemTypesHelper::getTopNByType($params));
        self::assertCount($eligible ? 1 : 0, ModJemTypesHelper::getTypeSummary($params));
        $params->set('hide_empty', 0);
        $top = ModJemTypesHelper::getTopNByType($params);
        $summary = ModJemTypesHelper::getTypeSummary($params);
        self::assertCount(2, $top);
        self::assertCount(2, $summary);
        self::assertCount($eligible ? 1 : 0, $top[0]->events);
        self::assertSame($eligible ? 1 : 0, (int) $summary[0]->event_count);
        self::assertSame([], $top[1]->events);
        self::assertSame(0, (int) $summary[1]->event_count);
        self::assertSame([$before[0]], $this->storedEvents(), 'No inferred end time or other event data is persisted.');
        self::assertSame('Pacific/Honolulu', date_default_timezone_get());
    }

    /** @return iterable Existing publication/access/language boundaries for otherwise future events. */
    public static function excludedEvents(): iterable
    {
        yield 'unpublished' => [['published' => 0]];
        yield 'archived' => [['published' => 2]];
        yield 'trashed' => [['published' => -2]];
        yield 'private event' => [['access' => 2]];
        yield 'publication not started' => [['publish_up' => '2026-10-30 00:00:00']];
        yield 'publication ended' => [['publish_down' => '2026-10-29 20:13:00']];
        yield 'unpublished category' => [['category_id' => 2]];
        yield 'private category' => [['category_id' => 3]];
        yield 'other event language' => [['language' => 'es-ES']];
        yield 'other category language' => [['category_id' => 4]];
        yield 'unpublished venue' => [['locid' => 1]];
        yield 'private venue' => [['locid' => 2]];
    }

    /**
     * Retain the actual module's other SQL constraints while changing temporal eligibility.
     *
     * @param array $changes Single excluded event properties.
     * @return void
     */
    #[DataProvider('excludedEvents')]
    public function testExistingFiltersStillApply(array $changes): void
    {
        $this->insert(1, array_merge(['dates' => '2026-10-30'], $changes));
        $this->insert(100, ['dates' => '2026-10-31']);
        $params = new Registry(['top_n' => 1, 'hide_empty' => 1]);
        $top = ModJemTypesHelper::getTopNByType($params);
        self::assertCount(1, $top);
        self::assertSame([100], array_map('intval', array_column($top[0]->events, 'id')));
        $summary = ModJemTypesHelper::getTypeSummary($params);
        self::assertCount(1, $summary);
        self::assertSame(1, (int) $summary[0]->event_count);
    }

    /** @return void Keep #2382 date/time/ID ordering, deduplication and recurring type inheritance. */
    public function testOrderingDeduplicationAndInheritedType(): void
    {
        $this->insert(90, ['published' => 0, 'dates' => '2026-10-30']);
        $this->insert(1, ['dates' => '2026-10-30', 'times' => '20:00:00', 'endtimes' => '21:00:00']);
        $this->insert(2, ['dates' => '2026-10-30', 'times' => '09:00:00', 'endtimes' => '10:00:00']);
        $this->insert(3, ['dates' => '2026-10-30', 'times' => '09:00:00', 'endtimes' => '10:00:00', 'type_id' => 0, 'recurrence_first_id' => 90]);
        $this->insert(4, ['dates' => '2026-10-30', 'times' => null, 'endtimes' => null]);
        $this->db->setQuery('INSERT INTO #__jem_cats_event_relations VALUES (2, 1)')->execute();
        $params = new Registry(['top_n' => 3, 'hide_empty' => 1]);
        $top = ModJemTypesHelper::getTopNByType($params);
        self::assertSame([4, 2, 3], array_map('intval', array_column($top[0]->events, 'id')));
        $params->set('top_n', 10);
        $top = ModJemTypesHelper::getTopNByType($params);
        self::assertSame([4, 2, 3, 1], array_map('intval', array_column($top[0]->events, 'id')));
        $summary = ModJemTypesHelper::getTypeSummary($params);
        self::assertSame(4, (int) $summary[0]->event_count);
    }

    /** @return void Type publication/access/entity/language restrictions still precede listing. */
    public function testUnavailableTypesAreNotExposed(): void
    {
        foreach ([
            ['id' => 3, 'published' => 0],
            ['id' => 4, 'access' => 2],
            ['id' => 5, 'entity' => 2],
            ['id' => 6, 'language' => 'es-ES'],
        ] as $type) {
            $typeRow = (object) $type;
            $this->db->insertObject('#__jem_types', $typeRow);
            $this->insert($type['id'] + 100, ['type_id' => $type['id'], 'dates' => '2026-10-30']);
        }
        $params = new Registry(['hide_empty' => 0]);
        self::assertSame([1, 2], array_map('intval', array_column(ModJemTypesHelper::getTopNByType($params), 'id')));
        self::assertSame([1, 2], array_map('intval', array_column(ModJemTypesHelper::getTypeSummary($params), 'id')));
    }
}

