<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** #2389: real model queries against an isolated database; no installed site is changed. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttendeesStatusFilterTest extends TestCase
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
        $container->alias(\Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
        $container->set('AdministratorRouter', new stdClass());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        foreach ([
            'jem_events' => 'id INTEGER PRIMARY KEY, waitinglist INTEGER, created_by INTEGER,
                access INTEGER DEFAULT 1, published INTEGER DEFAULT 1',
            'jem_register' => 'id INTEGER PRIMARY KEY, event INTEGER, uid INTEGER, status INTEGER DEFAULT 1,
                waiting INTEGER NOT NULL DEFAULT 0, places INTEGER DEFAULT 1, uregdate TEXT',
            'users' => 'id INTEGER PRIMARY KEY, name TEXT, username TEXT, email TEXT',
            'jem_categories' => 'id INTEGER, catname TEXT, published INTEGER DEFAULT 1, access INTEGER DEFAULT 1',
            'jem_cats_event_relations' => 'itemid INTEGER, catid INTEGER',
        ] as $name => $columns) {
            $this->db->setQuery('CREATE TABLE t_' . $name . ' (' . $columns . ')')->execute();
        }
        foreach ([1 => 0, 2 => 1] as $eventId => $enabled) {
            $event = (object) ['id' => $eventId, 'waitinglist' => $enabled];
            $category = (object) ['id' => $eventId, 'catname' => 'Category'];
            $relation = (object) ['itemid' => $eventId, 'catid' => $eventId];
            $this->db->insertObject('#__jem_events', $event);
            $this->db->insertObject('#__jem_categories', $category);
            $this->db->insertObject('#__jem_cats_event_relations', $relation);
            foreach ([[1, 0], [1, 1], [0, 1], [-1, 1], [1, null]] as $index => [$status, $waiting]) {
                $id = $eventId * 10 + $index + 1;
                $user = (object) ['id' => $id, 'name' => 'Attendee ' . $id, 'username' => 'user' . $id];
                $this->db->insertObject('#__users', $user);
                $row = (object) ['id' => $id, 'event' => $eventId, 'uid' => $id, 'status' => $status,
                    'uregdate' => '2026-10-30 12:00:' . sprintf('%02d', $id)];
                // Omitted waiting flag tests the stored default used by older registrations.
                if ($waiting !== null) {
                    $row->waiting = $waiting;
                }
                $this->db->insertObject('#__jem_register', $row);
            }
        }
    }

    private function model(string $client, int $eventId, int $status, array $request = []): JemModelAttendees
    {
        Factory::$application = new AdminSearchApplication($request + [
            'eventid' => $eventId, 'id' => $eventId, 'filter_status' => $status,
        ]);
        require_once JEM_TEST_ROOT . '/' . $client . '/models/attendees.php';
        if ($client === 'admin') {
            $model = new JemModelAttendees(['dbo' => $this->db]);
        } else {
            // Authorisation is a test double; exercise the production constructor,
            // status/search/access predicates, list loading, totals and pagination.
            $model = new class extends JemModelAttendees {
                public bool $mayManage = true;

                public function canManageAttendees($user = null)
                {
                    return $this->mayManage;
                }
            };
        }
        $model->getState();
        return $model;
    }

    private function ids(string $client, JemModelAttendees $model): array
    {
        $rows = $client === 'admin' ? $model->getItems() : $model->getData();
        self::assertIsArray($rows);
        return array_map(static fn (object $row): int => (int) $row->id, $rows);
    }

    public static function statuses(): iterable
    {
        foreach (['admin', 'site'] as $client) {
            foreach ([1, 2] as $eventId) {
                foreach ([-2 => [1, 2, 3, 4, 5], -1 => [4], 0 => [3], 1 => [1, 5], 2 => [2]] as $status => $ids) {
                    yield "$client event $eventId status $status" => [
                        $client, $eventId, $status,
                        array_map(static fn (int $id): int => $eventId * 10 + $id, $ids),
                    ];
                }
            }
        }
    }

    #[DataProvider('statuses')]
    public function testStatusFiltersAndTotals(string $client, int $eventId, int $status, array $expected): void
    {
        $before = $this->db->setQuery('SELECT * FROM t_jem_register ORDER BY id')->loadAssocList();
        $model = $this->model($client, $eventId, $status);
        self::assertSame($expected, $this->ids($client, $model));
        self::assertSame(count($expected), $model->getTotal(), (string) $model->getError());
        self::assertSame(count($expected), $model->getPagination()->total);
        self::assertSame($before, $this->db->setQuery('SELECT * FROM t_jem_register ORDER BY id')->loadAssocList());
    }

    public static function clients(): array
    {
        return [['admin'], ['site']];
    }

    #[DataProvider('clients')]
    public function testChangingEventSettingDoesNotReclassifyRegistrations(string $client): void
    {
        foreach ([0, 1, 0] as $enabled) {
            $this->db->setQuery('UPDATE t_jem_events SET waitinglist = ' . $enabled . ' WHERE id = 1')->execute();
            self::assertSame([11, 15], $this->ids($client, $this->model($client, 1, 1)));
            self::assertSame([12], $this->ids($client, $this->model($client, 1, 2)));
        }
    }

    #[DataProvider('clients')]
    public function testPaginationUsesFilteredRegistrationCount(string $client): void
    {
        foreach ([0 => 11, 1 => 15] as $offset => $id) {
            $model = $this->model($client, 1, 1, ['limit' => 1, 'limitstart' => $offset]);
            self::assertSame([$id], $this->ids($client, $model));
            self::assertSame(2, $model->getTotal());
            self::assertSame(2, $model->getPagination()->pagesTotal);
        }
        $model = $this->model($client, 1, 2, ['limit' => 1]);
        self::assertSame([12], $this->ids($client, $model));
        self::assertSame(1, $model->getTotal());
        self::assertSame(1, $model->getPagination()->pagesTotal);
    }

    #[DataProvider('clients')]
    public function testConfirmedOnlyEventHasNoWaitingResults(string $client): void
    {
        $this->db->setQuery('DELETE FROM t_jem_register WHERE event = 1 AND id <> 11')->execute();
        self::assertSame([11], $this->ids($client, $this->model($client, 1, 1)));
        $model = $this->model($client, 1, 2);
        self::assertSame([], $this->ids($client, $model));
        self::assertSame(0, $model->getTotal());
        self::assertSame(0, $model->getPagination()->total);
    }

    #[DataProvider('clients')]
    public function testSearchAndPrintExportQueriesKeepTheStatusFilter(string $client): void
    {
        foreach (['', 'print', 'export'] as $task) {
            $model = $this->model($client, 1, 2, ['task' => $task]);
            self::assertSame([12], $this->ids($client, $model));
            self::assertSame(1, $model->getTotal());
        }
        foreach (['Attendee 12' => [12], 'Attendee 11' => []] as $search => $expected) {
            $model = $this->model($client, 1, 2, ['filter_search' => $search, 'filter_type' => 1, 'filter' => 1]);
            self::assertSame($expected, $this->ids($client, $model));
            self::assertSame(count($expected), $model->getTotal());
        }
    }

    public function testFrontendPermissionAndViewAccessFiltersRemainEffective(): void
    {
        $model = $this->model('site', 1, 2);
        $model->mayManage = false;
        self::assertSame([], $this->ids('site', $model));
        self::assertSame(0, $model->getTotal());
        $this->db->setQuery('UPDATE t_jem_events SET access = 99 WHERE id = 1')->execute();
        self::assertSame([], $this->ids('site', $this->model('site', 1, 2)));
        $this->db->setQuery('UPDATE t_jem_events SET access = 1 WHERE id = 1')->execute();
        $this->db->setQuery('UPDATE t_jem_categories SET published = 0 WHERE id = 1')->execute();
        self::assertSame([], $this->ids('site', $this->model('site', 1, 2)));
        $this->db->setQuery('UPDATE t_jem_categories SET published = 1, access = 99 WHERE id = 1')->execute();
        self::assertSame([], $this->ids('site', $this->model('site', 1, 2)));
    }

    public function testFrontendCategoryJoinsDoNotDuplicateFilteredAttendees(): void
    {
        $relation = (object) ['itemid' => 1, 'catid' => 2];
        $this->db->insertObject('#__jem_cats_event_relations', $relation);
        $model = $this->model('site', 1, 1);
        self::assertSame([11, 15], $this->ids('site', $model));
        self::assertSame(2, $model->getTotal());
    }
}
