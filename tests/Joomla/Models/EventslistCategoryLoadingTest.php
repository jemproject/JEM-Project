<?php

declare(strict_types=1);

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** SQL/loader regressions for #2194; only a disposable in-memory database is written. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventslistCategoryLoadingTest extends TestCase
{
    private EventslistLoadingDatabase $db;
    private EventslistLoadingModel $model;
    private EventslistLoadingUser $user;

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
        require_once JEM_TEST_ROOT . '/site/models/eventslist.php';
        require_once JEM_TEST_ROOT . '/site/classes/categories.class.php';
        require_once JEM_TEST_ROOT . '/tests/Support/eventslist_loading_environment.php';

        $this->db = new EventslistLoadingDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(\Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        $this->user = new EventslistLoadingUser();
        (new ReflectionProperty(JemUserAbstract::class, 'instances_jemuser'))->setValue(null, [7 => $this->user]);
        Factory::$application = new EventslistLoadingApplication($this->user);
        JemHelper::$attribs = new Registry(['global_show_filter' => 1]);
        JemHelper::$config = (object) [
            'access_level_locked_categories' => '["1"]',
            'delivereventsyes' => 0, 'eventedit' => 0, 'eventowner' => 0, 'autopubl' => 0,
        ];
        $this->model = new EventslistLoadingModel($this->db);
        foreach ([
            'CREATE TABLE t_jem_categories (id INTEGER PRIMARY KEY, parent_id INTEGER, catname TEXT, alias TEXT, access INTEGER, published INTEGER, checked_out INTEGER DEFAULT 0, color TEXT, groupid INTEGER DEFAULT 0)',
            'CREATE TABLE t_jem_events (id INTEGER PRIMARY KEY, created_by INTEGER DEFAULT 7, attribs TEXT DEFAULT "", dates TEXT DEFAULT "2026-10-13")',
            'CREATE TABLE t_jem_cats_event_relations (id INTEGER PRIMARY KEY, itemid INTEGER, catid INTEGER, UNIQUE(catid,itemid))',
            'CREATE TABLE t_jem_config (keyname TEXT, value TEXT)',
            "INSERT INTO t_jem_categories (id,parent_id,catname,alias,access,published,color,groupid) VALUES
                (1,0,'Root','root',1,1,'#111111',0), (2,1,'Child','child',1,1,'#222222',9),
                (3,2,'Grandchild','',1,1,'#333333',0), (4,1,'Private','private',2,1,'#444444',0),
                (5,1,'Unpublished','hidden',1,0,'#555555',0), (6,1,'Empty','empty',1,1,'#666666',0)",
            'INSERT INTO t_jem_events (id) VALUES (10),(11),(12)',
            'INSERT INTO t_jem_cats_event_relations VALUES (1,10,3),(2,10,2),(3,10,4),(4,10,5),(5,11,2),(6,12,5)',
        ] as $sql) {
            $this->db->setQuery($sql)->execute();
        }
        $this->db->queries = [];
    }

    #[DataProvider('batchSizes')]
    public function testBatchBoundariesAndOneFilterPreparation(int $count): void
    {
        $ids = $count ? range(100, 99 + $count) : [];
        foreach ($ids as $id) {
            $this->db->setQuery('INSERT INTO t_jem_events (id) VALUES (' . $id . ')')->execute();
            $this->db->setQuery('INSERT INTO t_jem_cats_event_relations (itemid,catid) VALUES (' . $id . ',3)')->execute();
        }
        Factory::$application->params->set('includechildevents', 1);
        $this->model->setState('filter.req_catid', '1');
        $this->db->queries = [];
        $result = $this->model->getCategoriesForItems($ids);
        self::assertCount($count, $result);
        self::assertCount($count ? 1 + (int) ceil($count / 500) : 0, $this->db->queries);
        foreach ($result as $id => $categories) {
            self::assertSame([3], array_column($categories, 'id'));
            self::assertSame($id, $categories[0]->multi);
            self::assertFalse(property_exists($categories[0], 'category_relation_id'));
        }
        if ($count) {
            $this->model->getCategories('all');
            $queries = count($this->db->queries);
            self::assertSame([1, 2, 3, 6], $this->model->getCategories('all'));
            self::assertCount($queries, $this->db->queries);
        }
    }

    public static function batchSizes(): iterable
    {
        foreach ([0, 1, 499, 500, 501, 1001] as $count) {
            yield (string) $count => [$count];
        }
    }

    public function testAssignmentOrderShapeAndAclRelationsStaySeparate(): void
    {
        $bulk = $this->model->getCategoriesForItems([10, '10', 11, 12, 0, -1]);
        self::assertSame([10, 11], array_keys($bulk));
        self::assertSame([3, 2], array_column($bulk[10], 'id'));
        self::assertEquals($bulk[10], $this->model->getCategories(10));
        self::assertSame(['id', 'catname', 'access', 'cchecked_out', 'color', 'catslug', 'user_has_access_category', 'multi'], array_keys((array) $bulk[10][0]));
        self::assertSame('3', (string) $bulk[10][0]->catslug);
        self::assertSame('2:child', $bulk[10][1]->catslug);
        self::assertSame('#333333', $bulk[10][0]->color);
        self::assertSame([10 => [3, 2, 4, 5], 11 => [2], 12 => [5]], $this->model->aclIds([10, 11, 12]));
        $bulk[10][1]->catname = 'Changed';
        self::assertSame('Child', $bulk[11][0]->catname);
    }

    #[DataProvider('categoryFilters')]
    public function testCategoryFilters(array $state, bool $children, array $all, array $event): void
    {
        foreach ($state as $key => $value) {
            $this->model->setState($key, $value);
        }
        Factory::$application->params->set('includechildevents', $children);
        self::assertSame($all, $this->model->getCategories('all'));
        self::assertSame($event, array_column($this->model->getCategories(10), 'id'));
    }

    public static function categoryFilters(): iterable
    {
        yield 'default includes empty categories' => [[], false, [1, 2, 3, 6], [3, 2]];
        yield 'single include' => [['filter.category_id' => 2], false, [2], [2]];
        yield 'single exclude' => [['filter.category_id' => 2, 'filter.category_id.include' => false], false, [1, 3, 6], [3]];
        yield 'array include' => [['filter.category_id' => [3, 6]], false, [3, 6], [3]];
        yield 'array exclude' => [['filter.category_id' => [2, 3], 'filter.category_id.include' => false], false, [1, 6], []];
        yield 'requested parent only' => [['filter.req_catid' => '2'], false, [2], [2]];
        yield 'requested parent and descendants' => [['filter.req_catid' => '2'], true, [2, 3], [3, 2]];
        yield 'multiple requested roots' => [['filter.req_catid' => '2,6'], true, [2, 3, 6], [3, 2]];
        yield 'top relation excludes empty' => [['filter.category_top' => 'rel.catid IN (2,6)'], false, [2], [2]];
        yield 'top event condition keeps event join' => [['filter.category_top' => 'a.id = 11'], false, [2], []];
        yield 'search ID' => [['filter.filter_search' => 'id:3'], false, [3], [3]];
        yield 'category search' => [['filter.filter_search' => 'Child', 'filter.filter_type' => 4], false, [2, 3], [3, 2]];
        yield 'event search does not filter categories' => [['filter.filter_search' => 'Unknown', 'filter.filter_type' => 1], false, [1, 2, 3, 6], [3, 2]];
        yield 'no allowed levels' => [['filter.access_levels' => []], false, [], []];
    }

    public function testCacheTracksMenuModelAndAccessContext(): void
    {
        self::assertSame([1, 2, 3, 6], $this->model->getCategories('all'));
        self::assertStringNotContainsString('JOIN', $this->db->queries[0]);
        $other = new EventslistLoadingModel($this->db);
        $other->setState('filter.category_id', [2]);
        self::assertSame([2], $other->getCategories('all'));
        self::assertSame([1, 2, 3, 6], $this->model->getCategories('all'));
        JemHelper::$config->access_level_locked_categories = '["1","2"]';
        self::assertSame([1, 2, 3, 4, 6], $this->model->getCategories('all'));
        $categories = $this->model->getCategories(10);
        self::assertSame(0, $categories[2]->user_has_access_category);
        $this->model->setState('filter.strict_access', true);
        self::assertSame([1, 2, 3, 6], $this->model->getCategories('all'));
        $this->user->levels = [1, 2];
        self::assertSame([1, 2, 3, 4, 6], $this->model->getCategories('all'));
        $this->model->setState('filter.access_levels', [1]); // Public feed inside a registered session.
        self::assertSame([1, 2, 3, 6], $this->model->getCategories('all'));
        $this->model->setState('filter.req_catid', '2');
        self::assertSame([2], $this->model->getCategories('all'));
        Factory::$application->params->set('includechildevents', 1);
        self::assertSame([2, 3], $this->model->getCategories('all'));
    }

    public function testPathsReuseOnlyVisitedNodesAndRespectAccess(): void
    {
        self::assertEquals(['1:root', '2:child', '3'], (new JemCategories('3:old-alias'))->getPath());
        self::assertCount(3, $this->db->queries);
        self::assertEquals(['1:root', '2:child', '3'], (new JemCategories(3))->getPath());
        self::assertSame(['1:root', '2:child'], (new JemCategories(2))->getPath());
        self::assertCount(3, $this->db->queries);
        self::assertSame([], (new JemCategories(4))->getPath());
        self::assertSame([], (new JemCategories(4))->getPath());
        self::assertCount(4, $this->db->queries);
        $this->user->levels = [1, 2];
        self::assertSame(['1:root', '4:private'], (new JemCategories(4))->getPath());
        self::assertSame([], (new JemCategories(5))->getPath());
        self::assertSame([], (new JemCategories(999))->getPath());
        $this->db->setQuery("INSERT INTO t_jem_categories (id,parent_id,catname,alias,access,published) VALUES (7,8,'A','a',1,1),(8,7,'B','b',1,1)")->execute();
        self::assertSame(['8:b', '7:a'], (new JemCategories(7))->getPath());
    }

    #[DataProvider('permissionCases')]
    public function testAclDecisionIsUnchangedByPrefetch(string $scenario, bool $allowed): void
    {
        if ($scenario === 'allow') {
            foreach ([2, 3, 4, 5] as $id) {
                Access::$rules['com_jem.category.' . $id] = ['jem.events.edit' => [2 => true]];
            }
        } elseif ($scenario === 'hidden deny') {
            foreach ([2, 3, 4] as $id) {
                Access::$rules['com_jem.category.' . $id] = ['jem.events.edit' => [2 => true]];
            }
            Access::$rules['com_jem.category.5'] = ['jem.events.edit' => [2 => false]];
            $this->user->permissions['core.edit'] = true;
        } elseif ($scenario === 'owner' || $scenario === 'other owner') {
            $this->user->permissions['core.edit.own'] = true;
        } elseif ($scenario === 'JEM group') {
            $this->user->jemGroups = [9 => ['id' => 9]];
        } elseif ($scenario === 'guest') {
            $this->user->guest = 1;
        }
        $owner = $scenario === 'other owner' ? 8 : 7;
        $before = $this->user->getAccessDecision('edit', 'event', 10, $owner);
        $ids = $this->model->aclIds([10]);
        $after = $this->user->getAccessDecision('edit', 'event', 10, $owner, $ids[10]);
        self::assertSame($allowed, $before->isAllowed());
        self::assertSame($before->toArray(), $after->toArray());
    }

    public static function permissionCases(): iterable
    {
        foreach (['allow' => true, 'hidden deny' => false, 'owner' => true, 'other owner' => false, 'JEM group' => true, 'guest' => false, 'unset' => false] as $scenario => $allowed) {
            yield $scenario => [$scenario, $allowed];
        }
    }

    public function testRealGetItemsUsesOnlyPageIdsAndPassesAllAclCategories(): void
    {
        $this->model->setState('list.limit', 1);
        $this->user->permissions['core.edit'] = true;
        $items = $this->model->getItems();
        self::assertCount(1, $items);
        self::assertSame(10, $items[0]->id);
        self::assertSame([3, 2], array_column($items[0]->categories, 'id'));
        self::assertTrue($items[0]->params->get('access-edit'));
        self::assertSame([3, 2, 4, 5], $this->user->canArguments[0][4]);
        self::assertStringContainsString('rel.itemid IN (10)', implode('\n', $this->db->queries));
        self::assertStringNotContainsString('rel.itemid IN (10,11', implode('\n', $this->db->queries));
        $other = new EventslistLoadingModel($this->db);
        $other->setState('list.limit', 1);
        $other->setState('list.start', 1);
        self::assertSame(11, $other->getItems()[0]->id);
        self::assertSame(3, $other->getTotal());
    }

    public function testImportedEventWithNullAttribsRemainsVisibleInList(): void
    {
        $this->db->setQuery('UPDATE t_jem_events SET attribs = NULL WHERE id = 10')->execute();
        $this->model->setState('list.limit', 1);

        $items = $this->model->getItems();

        self::assertCount(1, $items);
        self::assertSame(10, $items[0]->id);
        self::assertSame(array('access-edit' => false), $items[0]->params->toArray());
    }

    public function testGuestsSkipAclPreloadAndRowsWithoutCategoriesAreRemoved(): void
    {
        $this->user->guest = 1;
        $items = $this->model->getItems();
        self::assertSame([10, 11], array_column($items, 'id'));
        self::assertCount(3, $this->db->queries); // Event page + categories + one-time ACL migration status.
        self::assertStringNotContainsString('SELECT itemid, catid', implode('\n', $this->db->queries));
        self::assertFalse($items[0]->params->get('access-edit'));
        self::assertFalse($this->user->canArguments[0][4]);
    }

    public function testAclBatchBoundaryAndEmptyInput(): void
    {
        self::assertSame([], $this->model->aclIds([]));
        self::assertCount(0, $this->db->queries);
        for ($id = 100; $id <= 600; $id++) {
            $this->db->setQuery('INSERT INTO t_jem_cats_event_relations (itemid,catid) VALUES (' . $id . ',5)')->execute();
        }
        $this->db->queries = [];
        $ids = $this->model->aclIds(range(100, 600));
        self::assertCount(501, $ids);
        self::assertCount(2, $this->db->queries);
        self::assertSame([5], $ids[600]); // Even an unpublished category remains authoritative for ACL.
    }

    public function testTypeEventsWithRowsUsesTheInheritedBulkLoader(): void
    {
        require_once JEM_TEST_ROOT . '/site/models/typeevents.php';
        $model = (new ReflectionClass(JemModelTypeevents::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($model, '__state_set'))->setValue($model, true);
        $model->setDatabase($this->db);
        $model->setState('list.skip_attendee_numbers', true);
        $model->setState('filter.type_id', 4);
        $key = (new ReflectionMethod($model, 'getStoreId'))->invoke($model);
        $items = $this->db->setQuery('SELECT * FROM t_jem_events WHERE id = 10')->loadObjectList();
        (new ReflectionProperty($model, 'cache'))->setValue($model, [$key => $items]);
        $this->user->permissions['core.edit'] = true;
        $items = $model->getItems();
        self::assertSame([10], array_column($items, 'id'));
        self::assertSame([3, 2], array_column($items[0]->categories, 'id'));
        self::assertSame([3, 2, 4, 5], $this->user->canArguments[0][4]);
    }

    public function testMissingAndHiddenParentsStopRoutingWithoutLeakingTheirSlugs(): void
    {
        $this->db->setQuery("INSERT INTO t_jem_categories (id,parent_id,catname,alias,access,published) VALUES
            (7,5,'Visible child','child-hidden-parent',1,1), (8,999,'Orphan','orphan',1,1)")->execute();
        self::assertSame(['7:child-hidden-parent'], (new JemCategories(7))->getPath());
        self::assertSame(['8:orphan'], (new JemCategories(8))->getPath());
        $queries = count($this->db->queries);
        self::assertSame(['7:child-hidden-parent'], (new JemCategories(7))->getPath());
        self::assertCount($queries, $this->db->queries);
    }

    #[DataProvider('countCases')]
    public function testCountAndFallbackPreserveQuery(string $shape, int $expected, bool $fast): void
    {
        $query = $this->db->getQuery(true)->select('a.id, COUNT(rel.id) AS assignments')
            ->from('#__jem_events as a')->leftJoin('#__jem_cats_event_relations AS rel ON rel.itemid = a.id')
            ->group('a.id')->order('a.id DESC')->setLimit(1, 1);
        if ($shape === 'empty') {
            $query->where('a.id < 0');
        } elseif ($shape === 'having') {
            $query->having('COUNT(rel.id) > 1');
        } elseif ($shape === 'other group') {
            $query->clear('group')->group('a.created_by');
        } elseif ($shape === 'extra group') {
            $query->group('rel.catid');
        } elseif ($shape === 'bound projection') {
            $label = 'test';
            $query->select(':label AS label')->bind(':label', $label);
        } elseif ($shape === 'right join') {
            $this->db->setQuery('INSERT INTO t_jem_cats_event_relations (itemid,catid) VALUES (999,1)')->execute();
            $query->clear('join')->join('RIGHT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
        } elseif ($shape === 'union') {
            $union = $this->db->getQuery(true)->select('99 AS id, 0 AS assignments');
            $query->unionAll($union);
        }
        $original = (string) $query;
        self::assertSame($expected, $this->model->countQuery($query));
        self::assertSame($original, (string) $query);
        self::assertSame($fast, str_contains(end($this->db->queries), 'COUNT(DISTINCT a.id)'));
    }

    public static function countCases(): iterable
    {
        yield 'standard' => ['standard', 3, true];
        yield 'empty' => ['empty', 0, true];
        yield 'having' => ['having', 1, false];
        yield 'other group' => ['other group', 1, false];
        yield 'extra group' => ['extra group', 6, false];
        yield 'bound projection' => ['bound projection', 3, false];
        yield 'right join' => ['right join', 4, false];
        yield 'union' => ['union', 4, false];
    }
}
