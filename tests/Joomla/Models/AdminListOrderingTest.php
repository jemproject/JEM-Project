<?php

declare(strict_types=1);

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise actual Joomla list state and JEM SQL without changing an installed site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminListOrderingTest extends TestCase
{
    private AdminSearchDatabase $db;

    /** Load native Joomla libraries and a disposable, populated SQLite database. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite.');
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
        define('JPATH_ROOT', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        require_once JEM_TEST_ROOT . '/tests/Support/admin_search_environment.php';
        $this->db = new AdminSearchDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(\Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
        $container->set('AdministratorRouter', new stdClass());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        (new ReflectionProperty(ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new ComponentRecord(['params' => '{}']),
        ]);
        $this->createFixture();
    }

    /** Create all columns used by the actual list SELECT, joins and filters. */
    private function createFixture(): void
    {
        $tables = [
            'users' => 'name TEXT, email TEXT',
            'viewlevels' => 'title TEXT',
            'jem_events' => 'locid INTEGER, published INTEGER',
            'jem_groups' => 'name TEXT, checked_out INTEGER, published INTEGER DEFAULT 1',
        ];
        $columns = [];
        foreach (explode(' ', 'venue alias color url street postalCode city state country latitude longitude locdescription meta_keywords meta_description locimage author_ip created modified checked_out_time publish_up publish_down') as $column) {
            $columns[] = $column . " TEXT DEFAULT ''";
        }
        foreach (explode(' ', 'map created_by modified_by version checked_out ordering type_id parent_venue_id venue_tree_order') as $column) {
            $columns[] = $column . ' INTEGER DEFAULT 0';
        }
        $tables['jem_venues'] = implode(', ', $columns) . ', access INTEGER DEFAULT 1, published INTEGER DEFAULT 1';
        foreach ($tables as $table => $definition) {
            $this->db->setQuery('CREATE TABLE t_' . $table . ' (id INTEGER PRIMARY KEY, ' . $definition . ')')->execute();
        }
        $this->db->setQuery("INSERT INTO t_users VALUES (1, 'Tester', 'test@example.invalid')")->execute();
        $this->db->setQuery("INSERT INTO t_viewlevels VALUES (1, 'Public')")->execute();
        foreach (['Hall', 'Hall0', 'Hall00', 'Alpha', 'A0B', 'Room10'] as $index => $name) {
            $id = $index + 1;
            $row = (object) ['id' => $id, 'name' => $name];
            $this->db->insertObject('#__jem_groups', $row);
            $row = (object) [
                'id' => $id, 'venue' => $name, 'alias' => $name, 'city' => 'Madrid',
                'created_by' => 1, 'ordering' => $id,
                'parent_venue_id' => in_array($id, [2, 3], true) ? 1 : 0,
            ];
            $this->db->insertObject('#__jem_venues', $row);
            $row = (object) ['id' => $id, 'locid' => $id, 'published' => 1];
            $this->db->insertObject('#__jem_events', $row);
        }
    }

    /**
     * Populate an actual JEM model using Joomla's native ListModel.
     *
     * @param string $name Model name.
     * @param array $request Synthetic administrator request.
     * @return object Populated model.
     */
    private function model(string $name, array $request): object
    {
        Factory::$application = new AdminSearchApplication($request);
        require_once JEM_TEST_ROOT . '/admin/models/' . $name . '.php';
        $class = 'JemModel' . ucfirst($name);
        $model = new $class(['dbo' => $this->db]);
        $model->getState();

        return $model;
    }

    /**
     * Execute native getItems, including pagination and aggregate joins.
     *
     * @param object $model Populated model.
     * @return int[] Returned IDs in SQL order.
     */
    private function ids(object $model): array
    {
        $items = $model->getItems();
        self::assertIsArray($items, (string) $model->getError());

        return array_map('intval', array_column($items, 'id'));
    }

    /** @return iterable<string, array{string, string}> Model and default ordering. */
    public static function models(): iterable
    {
        yield 'venues' => ['venues', 'a.venue'];
        yield 'groups' => ['groups', 'a.name'];
    }

    /** Partial list requests must not remove the default ordering or change limits. */
    #[DataProvider('models')]
    public function testPartialListRequests(string $name, string $default): void
    {
        foreach ([[], ['list' => ['limit' => 0]], ['list' => ['limit' => 2]], ['list' => ['limit' => 25]]] as $request) {
            $model = $this->model($name, $request);
            self::assertSame($default, $model->getState('list.ordering'));
            self::assertSame('ASC', $model->getState('list.direction'));
            self::assertSame($default . ' ASC', $model->getState('list.fullordering'));
            $limit = $request['list']['limit'] ?? 0;
            self::assertSame($limit, $model->getState('list.limit'));
            self::assertSame(array_slice([5, 4, 1, 2, 3, 6], 0, $limit ?: null), $this->ids($model));
            self::assertSame(6, (int) $model->getTotal());
        }
    }

    /** Every real sorting option must remain usable with the full SELECT. */
    #[DataProvider('models')]
    public function testEveryFormOrdering(string $name, string $default): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/filter_' . $name . '.xml');
        $options = $xml->xpath('//fields[@name="list"]/field[@name="fullordering"]/option');
        self::assertNotEmpty($options);
        foreach ($options as $option) {
            $ordering = (string) $option['value'];
            if ($ordering === '') {
                continue;
            }
            $model = $this->model($name, ['list' => ['limit' => 0, 'fullordering' => $ordering]]);
            self::assertSame($ordering, $model->getState('list.fullordering'));
            self::assertCount(6, $this->ids($model), $ordering);
        }
    }

    /** Filter-only identifiers must never be used as SQL ordering columns. */
    #[DataProvider('models')]
    public function testInvalidOrderingFallsBack(string $name, string $default): void
    {
        $invalid = $name === 'venues'
            ? ['search ASC', 'search_type ASC', 'venue_type_id ASC', 'missing DESC', '']
            : ['search ASC', 'state ASC', 'missing DESC', ''];
        foreach ($invalid as $ordering) {
            $model = $this->model($name, ['list' => ['limit' => 0, 'fullordering' => $ordering]]);
            self::assertSame($default, $model->getState('list.ordering'));
            self::assertCount(6, $this->ids($model));
            self::assertSame(
                $model->getState('list.ordering') . ' ' . $model->getState('list.direction'),
                $model->getState('list.fullordering')
            );
        }
    }

    /** Legacy aliases resolve to qualified columns, avoiding ambiguous published/id. */
    #[DataProvider('models')]
    public function testAliasesAndDescendingOrder(string $name, string $default): void
    {
        $aliases = ['id' => 'a.id', 'published' => 'a.published'];
        $aliases += $name === 'venues'
            ? ['venue' => 'a.venue', 'author' => 'u.name', 'access_level' => 'access_level', 'assignedevents' => 'assignedevents']
            : ['name' => 'a.name'];
        foreach ($aliases as $alias => $column) {
            $model = $this->model($name, ['list' => ['limit' => 0, 'fullordering' => $alias . ' DESC']]);
            self::assertSame($column . ' DESC', $model->getState('list.fullordering'));
            self::assertCount(6, $this->ids($model));
        }
        $model = $this->model($name, ['list' => ['limit' => 0, 'fullordering' => 'a.id DESC']]);
        self::assertSame([6, 5, 4, 3, 2, 1], $this->ids($model));
        $model = $this->model($name, ['list' => ['limit' => 0, 'fullordering' => $default . ' DESC']]);
        $hierarchy = $name === 'venues' && str_contains(
            file_get_contents(JEM_TEST_ROOT . '/admin/models/venues.php'), 'COALESCE(tree_parent.venue'
        );
        self::assertSame($hierarchy ? [6, 1, 3, 2, 4, 5] : [6, 3, 2, 1, 4, 5], $this->ids($model));
    }

    /** Direct state callers are checked again before building SQL. */
    #[DataProvider('models')]
    public function testQueryBoundaryNormalizesInvalidState(string $name, string $default): void
    {
        foreach ([null, '', 'search', ['a.id'], new stdClass()] as $ordering) {
            $model = $this->model($name, ['list' => ['limit' => 0]]);
            $model->setState('list.ordering', $ordering);
            $model->setState('list.direction', ['DESC']);
            $query = (new ReflectionMethod($model, 'getListQuery'))->invoke($model);
            self::assertSame([5, 4, 1, 2, 3, 6], array_map('intval', $this->db->setQuery($query)->loadColumn()));
            self::assertSame($default . ' ASC', $model->getState('list.fullordering'));
        }
    }

    /** Tied sort values need a stable ID tie-break across successive pages. */
    #[DataProvider('models')]
    public function testStablePagination(string $name, string $default): void
    {
        $ordering = $name === 'venues' ? 'a.city ASC' : 'a.published ASC';
        foreach ([0, 2, 4] as $start) {
            $model = $this->model($name, ['list' => ['limit' => 2, 'fullordering' => $ordering], 'limitstart' => $start]);
            self::assertSame([$start + 1, $start + 2], $this->ids($model));
            self::assertSame(6, (int) $model->getTotal());
        }
    }

    /** Normalize directions without losing valid legacy or session ordering. */
    #[DataProvider('models')]
    public function testDirectionsAndSavedOrdering(string $name, string $default): void
    {
        foreach ([null, '', 'invalid', ['DESC']] as $direction) {
            $model = $this->model($name, []);
            $model->setState('list.ordering', 'a.id');
            $model->setState('list.direction', $direction);
            $query = (new ReflectionMethod($model, 'getListQuery'))->invoke($model);
            self::assertSame([1, 2, 3, 4, 5, 6], array_map('intval', $this->db->setQuery($query)->loadColumn()));
            self::assertSame('a.id ASC', $model->getState('list.fullordering'));
        }
        $model = $this->model($name, ['filter_order' => 'id', 'filter_order_Dir' => 'desc']);
        self::assertSame([6, 5, 4, 3, 2, 1], $this->ids($model));
        self::assertSame('a.id DESC', $model->getState('list.fullordering'));

        $this->model($name, ['list' => ['limit' => 2, 'fullordering' => 'a.id DESC']]);
        Factory::$application->setRequest(['limitstart' => 2]);
        $class = get_class($model);
        $saved = new $class(['dbo' => $this->db]);
        self::assertSame('a.id DESC', $saved->getState('list.fullordering'));
        self::assertSame([4, 3], $this->ids($saved));
    }

    /** Publication, text, type and access filters retain their previous meaning. */
    public function testFiltersRemainEffective(): void
    {
        foreach (['venues', 'groups'] as $name) {
            $this->db->setQuery('UPDATE #__jem_' . $name . ' SET published = 0 WHERE id = 2')->execute();
            $key = $name === 'venues' ? 'published' : 'state';
            $model = $this->model($name, ['list' => ['limit' => 0], 'filter' => ['search' => '0', $key => '0']]);
            self::assertSame([2], $this->ids($model));
            self::assertSame(1, (int) $model->getTotal());
        }
        $this->db->setQuery('UPDATE #__jem_venues SET access = 2, type_id = 3 WHERE id = 4')->execute();
        foreach ([['access' => 2], ['venue_type_id' => 3]] as $filter) {
            self::assertSame([4], $this->ids($this->model('venues', ['list' => ['limit' => 0], 'filter' => $filter])));
        }
    }
}

