<?php

declare(strict_types=1);

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Regression for #2386. Only an in-memory database is written, never the installed site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminZeroSearchTest extends TestCase
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
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ROOT', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        require_once JEM_TEST_ROOT . '/tests/Support/admin_search_environment.php';
        $this->db = new AdminSearchDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $this->db->addConcat();
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

    private function createFixture(): void
    {
        $tables = [
            'users' => 'name TEXT, username TEXT, email TEXT, block INTEGER DEFAULT 0',
            'viewlevels' => 'title TEXT',
            'languages' => 'lang_code TEXT, title TEXT',
            'categories' => 'title TEXT, extension TEXT, published INTEGER, lft INTEGER, rgt INTEGER',
            'contact_details' => 'name TEXT, address TEXT, suburb TEXT, state TEXT, catid INTEGER, published INTEGER DEFAULT 1, ordering INTEGER',
            'jem_groups' => 'name TEXT, checked_out INTEGER, published INTEGER DEFAULT 1',
            'jem_venues' => 'venue TEXT, alias TEXT, city TEXT, state TEXT, country TEXT, parent_venue_id INTEGER,
                created_by INTEGER, modified_by INTEGER, checked_out INTEGER, access INTEGER DEFAULT 1,
                type_id INTEGER, published INTEGER DEFAULT 1',
            'jem_events' => 'title TEXT, alias TEXT, locid INTEGER, parent_event_id INTEGER, created_by INTEGER,
                modified_by INTEGER, checked_out INTEGER, access INTEGER DEFAULT 1, type_id INTEGER,
                dates TEXT, published INTEGER DEFAULT 1, featured INTEGER DEFAULT 0, waitinglist INTEGER',
            'jem_categories' => 'catname TEXT, alias TEXT, note TEXT, language TEXT, checked_out INTEGER,
                access INTEGER DEFAULT 1, created_user_id INTEGER, groupid INTEGER, article_category_id INTEGER,
                level INTEGER DEFAULT 1, lft INTEGER, rgt INTEGER, parent_id INTEGER DEFAULT 100,
                type_id INTEGER, published INTEGER DEFAULT 1',
            'jem_countries' => 'iso2 TEXT, name TEXT',
            'jem_types' => 'name TEXT, entity INTEGER DEFAULT 1, ordering INTEGER,
                access INTEGER DEFAULT 1, created_by INTEGER, published INTEGER DEFAULT 1',
            'jem_special_days' => 'title TEXT, description TEXT, access INTEGER DEFAULT 1, created_by INTEGER,
                published INTEGER DEFAULT 1, start_date TEXT, end_date TEXT, weekdays TEXT, ordering INTEGER',
            'jem_attachments' => 'file TEXT, name TEXT, description TEXT, object TEXT,
                access INTEGER DEFAULT 1, created_by INTEGER, frontend INTEGER DEFAULT 1, created TEXT',
            'jem_cats_event_relations' => 'itemid INTEGER, catid INTEGER',
            'jem_register' => 'uid INTEGER, event INTEGER DEFAULT 1, status INTEGER DEFAULT 1, waiting INTEGER DEFAULT 0',
        ];
        foreach ($tables as $name => $columns) {
            $this->db->setQuery('CREATE TABLE t_' . $name . ' (id INTEGER PRIMARY KEY, ' . $columns . ')')->execute();
        }
        foreach (['Hall', 'Hall0', 'Hall00', 'Alpha', 'A0B', 'Room10'] as $index => $text) {
            $id = $index + 1;
            foreach ([
                'users' => ['name' => $text, 'username' => $text, 'email' => 'example.invalid'],
                'jem_groups' => ['name' => $text],
                'jem_venues' => ['venue' => $text, 'alias' => $text, 'city' => $text, 'state' => $text, 'country' => $text],
                'jem_events' => ['title' => $text, 'alias' => $text, 'locid' => $id, 'created_by' => $id, 'type_id' => $id],
                'jem_categories' => ['catname' => $text, 'alias' => $text, 'note' => $text, 'created_user_id' => $id, 'lft' => $id * 2],
                'jem_countries' => ['iso2' => $text, 'name' => $text],
                'jem_types' => ['name' => $text, 'ordering' => $id],
                'jem_special_days' => ['title' => $text, 'description' => $text, 'start_date' => '2026-01-01', 'ordering' => $id],
                'jem_attachments' => ['file' => $text, 'name' => $text, 'object' => 'event' . $id],
                'jem_cats_event_relations' => ['itemid' => $id, 'catid' => $id],
                'jem_register' => ['uid' => $id],
                'categories' => ['title' => $text, 'extension' => 'com_contact', 'published' => 1],
                'contact_details' => ['name' => $text, 'address' => $text, 'suburb' => $text, 'state' => $text, 'ordering' => $id, 'catid' => $id],
            ] as $table => $values) {
                $row = (object) (['id' => $id] + $values);
                $this->db->insertObject('#__' . $table, $row);
            }
        }
    }

    private function model(string $name, array $request): object
    {
        Factory::$application = new AdminSearchApplication($request + ['eventid' => 1, 'filter_year' => 2026]);
        require_once JEM_TEST_ROOT . '/admin/models/' . $name . '.php';
        $class = 'JemModel' . ucfirst($name);
        $model = new $class();
        $model->getState(); // Exercise JEM and Joomla state population, including legacy filter sync.
        $model->setState('list.select', $name === 'attendees' ? 'r.id' : 'a.id');
        $model->setState('list.ordering', $name === 'attendees' ? 'r.id' : 'a.id');
        $model->setState('list.direction', 'ASC');
        return $model;
    }

    private function ids(object $model, string $method = 'getListQuery'): array
    {
        $query = (new ReflectionMethod($model, $method))->invoke($model);
        $ids = array_map('intval', $this->db->setQuery($query)->loadColumn());
        sort($ids);
        return $ids;
    }

    private static function searches(): array
    {
        return [
            '' => [1, 2, 3, 4, 5, 6],
            '0' => [2, 3, 5, 6],
            '   ' => [1, 2, 3, 4, 5, 6],
            '00' => [3],
            'Hall' => [1, 2, 3],
            'A0B' => [5],
            'missing' => [],
        ];
    }

    public static function lists(): iterable
    {
        foreach (['venues', 'events', 'categories', 'types', 'specialdays', 'groups', 'attachments'] as $name) {
            foreach (['flat', 'nested'] as $mode) {
                yield $name . ' ' . $mode => [$name, $mode];
            }
        }
    }

    #[DataProvider('lists')]
    public function testSearchToolsAndLegacyInputFilterActualRows(string $name, string $mode): void
    {
        foreach (self::searches() as $search => $expected) {
            $search = (string) $search;
            $request = $mode === 'flat' ? ['filter_search' => $search] : ['filter' => ['search' => $search]];
            $model = $this->model($name, $request);
            self::assertSame($search, $model->getState('filter_search'));
            self::assertSame($search, $model->getState('filter.search'));
            self::assertSame($expected, $this->ids($model), $name . ': ' . $search);
        }
    }

    public static function searchScopes(): iterable
    {
        foreach (['venues' => range(0, 5), 'events' => range(0, 8), 'attendees' => [1, 2]] as $name => $scopes) {
            foreach ($scopes as $scope) {
                yield $name . ' scope ' . $scope => [$name, $scope];
            }
        }
    }

    #[DataProvider('searchScopes')]
    public function testZeroIsAppliedToEverySearchScope(string $name, int $scope): void
    {
        $model = $this->model($name, ['filter_search' => '0', 'filter_type' => $scope]);
        self::assertSame([2, 3, 5, 6], $this->ids($model));
    }

    public function testPrefixedSearchAndPublicationFiltersArePreserved(): void
    {
        foreach (['venues', 'events', 'categories', 'groups', 'attachments'] as $name) {
            foreach (['id:0' => [], 'id:3' => [3]] as $search => $expected) {
                self::assertSame($expected, $this->ids($this->model($name, ['filter_search' => $search])));
            }
        }
        self::assertSame([2, 3, 5, 6], $this->ids($this->model('categories', ['filter_search' => 'author:0'])));
        $this->db->setQuery('UPDATE #__jem_venues SET published = 0 WHERE id = 2')->execute();
        $model = $this->model('venues', ['filter_search' => '0', 'filter_state' => '0']);
        self::assertSame([2], $this->ids($model));
    }

    public function testSearchCanBeClearedAndPagingIsReset(): void
    {
        $model = $this->model('venues', ['filter_search' => '0', 'limitstart' => 40]);
        self::assertSame(0, Factory::$application->input->getInt('limitstart'));
        self::assertSame([2, 3, 5, 6], $this->ids($model));
        Factory::$application->setRequest(['filter' => ['search' => '']]);
        $cleared = new JemModelVenues();
        $cleared->getState();
        $cleared->setState('list.select', 'a.id');
        self::assertSame('', $cleared->getState('filter_search'));
        self::assertSame([1, 2, 3, 4, 5, 6], $this->ids($cleared));
    }

    public function testLegacyUserAndContactPickersFilterZero(): void
    {
        foreach (['userelement', 'contactelement'] as $name) {
            $model = $this->model($name, ['filter_search' => '0', 'filter_type' => 1]);
            self::assertSame([2, 3, 5, 6], $this->ids($model, 'buildQuery'));
        }
        $model = $this->model('users', ['search' => '0']);
        $where = (new ReflectionMethod($model, '_buildContentWhere'))->invoke($model);
        self::assertSame([2, 3, 5, 6], array_map('intval', $this->db
            ->setQuery('SELECT u.id FROM #__users AS u' . $where . ' ORDER BY u.id')->loadColumn()));
    }

    public function testLegacyEventPickerFiltersZero(): void
    {
        $model = $this->model('eventelement', ['filter_search' => '0', 'filter_type' => 1]);
        self::assertSame([2, 3, 5, 6], $this->ids($model, '_buildQuery'));
    }

    public function testLegacyCategoryPickerFiltersZeroAfterTreeAssembly(): void
    {
        $this->db->setQuery("INSERT INTO #__jem_categories (id,catname,alias,parent_id,lft) VALUES (100,'Root','root',0,0)")->execute();
        $model = $this->model('categoryelement', ['filter_search' => '0']);
        self::assertSame([2, 3, 5, 6], array_map('intval', array_column($model->getData(), 'id')));
    }
}
