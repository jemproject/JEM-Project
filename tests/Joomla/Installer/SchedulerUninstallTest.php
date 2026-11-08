<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Table\TaskTable;
use Joomla\Database\Mysqli\MysqliDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Run real Joomla TaskTable/ACL operations exclusively in a disposable database.
 * Requires JEM_TEST_JOOMLA_ROOT and JEM_TEST_SCHEDULER_DATABASES=1, including CREATE
 * DATABASE permission. No installed JEM data, files, tasks or assets are changed.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SchedulerUninstallTest extends TestCase
{
    private ?mysqli $server = null;
    private ?MysqliDriver $db = null;
    private string $database = '';
    private com_jemInstallerScript $installer;

    /** Boot Joomla's libraries and seed tasks/assets in an isolated schema. */
    protected function setUp(): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_SCHEDULER_DATABASES') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_SCHEDULER_DATABASES=1.');
        }
        define('JPATH_ROOT', $root);
        define('JPATH_BASE', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        define('JPATH_SITE', sys_get_temp_dir() . '/jem-uninstall-no-site-' . bin2hex(random_bytes(8)));
        self::assertDirectoryDoesNotExist(JPATH_SITE);
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once $root . '/configuration.php';
        $config = new JConfig();
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_scheduler_test_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new MysqliDriver([
            'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $this->database, 'prefix' => 't_',
        ]);
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$language = new class {
            public function load(...$arguments): bool
            {
                return true;
            }

            public function _($key, ...$arguments): string
            {
                static $strings;
                $strings ??= parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');
                return $strings[$key] ?? $key;
            }
        };
        Factory::$application = new class {
            public function getLanguage(): object
            {
                return Factory::$language;
            }

            public function getDispatcher(): Joomla\Event\Dispatcher
            {
                return new Joomla\Event\Dispatcher();
            }

            public function get($key, $default = null)
            {
                return $key === 'access' ? 1 : $default;
            }

            public function bootComponent($name): object
            {
                // The reinstall service receives a minimal model adapter backed by real TaskTable.
                return new class {
                    public function getMVCFactory(): object
                    {
                        return $this;
                    }

                    public function createModel(...$arguments): object
                    {
                        return new class {
                            public function save(array $data): bool
                            {
                                $data += ['created_by' => 1, 'created' => gmdate('Y-m-d H:i:s')];
                                $table = new TaskTable(Factory::getContainer()->get('DatabaseDriver'));
                                return $table->bind($data) && $table->check() && $table->store();
                            }
                        };
                    }
                };
            }
        };
        foreach (['assets', 'scheduler_tasks'] as $name) {
            $this->execute('CREATE TABLE t_' . $name . ' LIKE '
                . $this->db->quoteName($config->db) . '.' . $this->db->quoteName($config->dbprefix . $name));
        }
        $this->execute("INSERT INTO t_assets(id,parent_id,lft,rgt,level,name,title,rules) VALUES
            (1,0,0,3,0,'root.1','Root','{}'),(2,1,1,2,1,'com_scheduler','Scheduler','{}')");
        $this->execute('CREATE TABLE t_jem_config(keyname VARCHAR(255) PRIMARY KEY,value MEDIUMTEXT) ENGINE=InnoDB');
        $this->execute("INSERT INTO t_jem_config VALUES('globalattribs','{}')");
        $this->execute('CREATE TABLE t_menu(id INT PRIMARY KEY,client_id INT,published INT,link TEXT) ENGINE=InnoDB');
        // A sentinel history table verifies the uninstall never purges Scheduler history.
        $this->execute('CREATE TABLE t_scheduler_logs(id INT PRIMARY KEY,taskid INT,note TEXT) ENGINE=InnoDB');
        $this->execute("INSERT INTO t_scheduler_logs VALUES(1,1,'Preserve JEM history'),(2,4,'Preserve unrelated history')");
        require_once JPATH_ADMINISTRATOR . '/components/com_scheduler/src/Table/TaskTable.php';
        foreach ([['jem.notifications', 0], ['jem.notifications', 1], ['jem.notifications', -2],
            ['privacy.consent', 1], ['jem.notifications.other', 0]] as $index => [$type, $state]) {
            $table = new TaskTable($this->db);
            $data = [
                'title' => 'Renamed task ' . $index, 'type' => $type, 'state' => $state,
                'created' => gmdate('Y-m-d H:i:s'), 'created_by' => 1,
                'execution_rules' => ['rule-type' => 'manual'], 'cron_rules' => [], 'params' => [],
                'priority' => 0, 'times_executed' => 0, 'times_failed' => 0, 'last_exit_code' => 0,
            ];
            if (!$table->bind($data) || !$table->check() || !$table->store()) {
                throw new RuntimeException('Could not create isolated TaskTable fixture.');
            }
        }
        require_once JEM_TEST_ROOT . '/script.php';
        require_once JEM_TEST_ROOT . '/package/pkg_install.php';
        $this->installer = new com_jemInstallerScript();
    }

    /** Disconnect and drop only the uniquely named database created by this test. */
    protected function tearDown(): void
    {
        $this->db?->disconnect();
        if ($this->server && preg_match('/^jem_scheduler_test_[a-f0-9]{16}$/D', $this->database)) {
            $this->server->query('DROP DATABASE ' . $this->database);
        }
        $this->server?->close();
    }

    /** Execute fixture SQL only on the disposable schema. */
    private function execute(string $sql): void
    {
        $this->db->setQuery($sql)->execute();
    }

    /** Return stable row data for assertions, excluding incidental ACL coordinates. */
    private function snapshot(): array
    {
        return [
            'tasks' => $this->db->setQuery('SELECT * FROM t_scheduler_tasks ORDER BY id')->loadAssocList(),
            'assets' => $this->db->setQuery('SELECT id,name,parent_id,lft,rgt FROM t_assets ORDER BY id')->loadAssocList(),
            'logs' => $this->db->setQuery('SELECT * FROM t_scheduler_logs ORDER BY id')->loadAssocList(),
        ];
    }

    public static function uninstallModes(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    #[DataProvider('uninstallModes')]
    public function testPackageAndComponentRemoveAllOwnedTasksInBothDataModes(bool $package, bool $cleanup): void
    {
        $settings = json_encode(['global_cleanup_db_on_uninstall' => (int) $cleanup]);
        $this->execute('UPDATE t_jem_config SET value=' . $this->db->quote($settings));
        $before = $this->snapshot();
        $entry = $package ? new Pkg_JemInstallerScript() : $this->installer;
        self::assertTrue($entry->preflight('uninstall', null));
        // Joomla also calls component preflight after package preflight.
        self::assertTrue($this->installer->preflight('uninstall', null));
        ob_start();
        try {
            $this->installer->uninstall(null);
        } finally {
            ob_end_clean();
        }
        $after = $this->snapshot();
        self::assertSame(array_slice($before['tasks'], 3), $after['tasks']);
        self::assertSame(['root.1', 'com_scheduler', 'com_scheduler.task.4', 'com_scheduler.task.5'], array_column($after['assets'], 'name'));
        self::assertSame($before['logs'], $after['logs']);
        self::assertSame(!$cleanup, in_array('t_jem_config', $this->db->getTableList(), true));
        $coordinates = array_merge(array_column($after['assets'], 'lft'), array_column($after['assets'], 'rgt'));
        self::assertCount(count($coordinates), array_unique($coordinates));
    }

    public function testMissingTaskTableIsAnIdempotentNoOp(): void
    {
        $this->execute('DROP TABLE t_scheduler_tasks');
        self::assertTrue($this->installer->preflight('uninstall', null));
        self::assertTrue($this->installer->preflight('uninstall', null));
        self::assertContains('t_jem_config', $this->db->getTableList());
        self::assertSame(7, (int) $this->db->setQuery('SELECT COUNT(*) FROM t_assets')->loadResult());
    }

    public function testMissingJemConfigurationDoesNotLeaveTasksBehind(): void
    {
        $this->execute('DROP TABLE t_jem_config');
        self::assertTrue($this->installer->preflight('uninstall', null));
        self::assertCount(2, $this->snapshot()['tasks']);
    }

    public function testMissingAssetAndIncorrectAssetPointerDoNotDeleteForeignAssets(): void
    {
        $asset = new Joomla\CMS\Table\Asset($this->db);
        self::assertTrue($asset->delete(3));
        // Joomla identifies a task asset by its canonical name, not this stale pointer.
        $this->execute('UPDATE t_scheduler_tasks SET asset_id=6 WHERE id=2');
        self::assertTrue($this->installer->preflight('uninstall', null));
        self::assertSame(['root.1', 'com_scheduler', 'com_scheduler.task.4', 'com_scheduler.task.5'], array_column($this->snapshot()['assets'], 'name'));
    }

    public static function lockedStates(): array
    {
        return [[0], [1], [-2]];
    }

    #[DataProvider('lockedStates')]
    public function testLockedTasksStopPackagePreflightBeforeAnyTaskIsDeleted(int $state): void
    {
        $this->execute('UPDATE t_scheduler_tasks SET state=' . $state . ',locked=UTC_TIMESTAMP() WHERE id=2');
        $before = $this->snapshot();
        try {
            (new Pkg_JemInstallerScript())->preflight('uninstall', null);
            self::fail('A locked task must block package removal.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('running or locked', $error->getMessage());
        }
        self::assertSame($before, $this->snapshot());
        self::assertContains('t_jem_config', $this->db->getTableList());
    }

    public function testTaskAssetWithForeignDescendantBlocksDeletionWithoutAclRebuild(): void
    {
        $asset = new Joomla\CMS\Table\Asset($this->db);
        self::assertTrue($asset->load(6));
        $asset->setLocation(3, 'last-child');
        self::assertTrue($asset->store());
        $before = $this->snapshot();
        try {
            $this->installer->preflight('uninstall', null);
            self::fail('A task subtree must not be removed.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('non-leaf ACL asset', $error->getMessage());
        }
        self::assertSame($before, $this->snapshot());
    }

    public function testDeleteFailureStopsBeforeJemDataCleanupAndCanBeRetried(): void
    {
        $this->server->select_db($this->database);
        $this->server->query("CREATE TRIGGER fail_task_delete BEFORE DELETE ON t_scheduler_tasks FOR EACH ROW
            BEGIN IF OLD.id=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected delete failure'; END IF; END");
        try {
            (new Pkg_JemInstallerScript())->preflight('uninstall', null);
            self::fail('Deletion failure must reach package preflight.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Could not remove JEM task 2', $error->getMessage());
            self::assertStringContainsString('Injected delete failure', $error->getMessage());
        }
        self::assertContains('t_jem_config', $this->db->getTableList());
        self::assertSame([2, 3, 4, 5], array_map('intval', array_column($this->snapshot()['tasks'], 'id')));
        self::assertSame(0, (int) $this->snapshot()['tasks'][0]['state']);
        $this->server->query('DROP TRIGGER fail_task_delete');
        self::assertTrue((new Pkg_JemInstallerScript())->preflight('uninstall', null));
        self::assertCount(2, $this->snapshot()['tasks']);
        self::assertCount(4, $this->snapshot()['assets']);
    }

    public function testUnrelatedLockedTaskDoesNotBlockJemUninstall(): void
    {
        $this->execute('UPDATE t_scheduler_tasks SET locked=UTC_TIMESTAMP() WHERE id=4');
        $foreign = array_slice($this->snapshot()['tasks'], 3);
        self::assertTrue($this->installer->preflight('uninstall', null));
        self::assertSame($foreign, $this->snapshot()['tasks']);
    }

    public function testLockAcquiredDuringDisableIsDetectedBeforeDeletion(): void
    {
        $this->server->select_db($this->database);
        // Deterministically inject the state a runner can acquire between the two reads.
        $this->server->query("CREATE TRIGGER inject_task_lock BEFORE UPDATE ON t_scheduler_tasks FOR EACH ROW
            BEGIN IF OLD.id=2 AND OLD.state=1 AND NEW.state=0 THEN SET NEW.locked=UTC_TIMESTAMP(); END IF; END");
        $before = $this->snapshot();
        try {
            $this->installer->preflight('uninstall', null);
            self::fail('A newly acquired lock must block deletion.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('running or locked', $error->getMessage());
        }
        self::assertCount(5, $this->snapshot()['tasks']);
        self::assertSame($before['assets'], $this->snapshot()['assets']);
        self::assertContains('t_jem_config', $this->db->getTableList());
        $this->server->query('DROP TRIGGER inject_task_lock');
        $this->execute('UPDATE t_scheduler_tasks SET locked=NULL WHERE id=2');
        self::assertTrue($this->installer->preflight('uninstall', null));
        self::assertCount(2, $this->snapshot()['tasks']);
    }

    public function testAssetDeleteFailureRetainsTasksAndJemData(): void
    {
        $this->server->select_db($this->database);
        $this->server->query("CREATE TRIGGER fail_asset_delete BEFORE DELETE ON t_assets FOR EACH ROW
            BEGIN IF OLD.id=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected asset-delete failure'; END IF; END");
        $before = $this->snapshot();
        try {
            $this->installer->preflight('uninstall', null);
            self::fail('An ACL deletion error must stop preflight.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Injected asset-delete failure', $error->getMessage());
        }
        self::assertCount(5, $this->snapshot()['tasks']);
        self::assertSame($before['assets'], $this->snapshot()['assets']);
        self::assertContains('t_jem_config', $this->db->getTableList());
    }

    public function testDuplicateAssetCoordinatesNeverDeleteAnUnrelatedAsset(): void
    {
        $this->execute('UPDATE t_assets SET lft=2,rgt=3 WHERE id=6');
        $before = $this->snapshot();
        try {
            $this->installer->preflight('uninstall', null);
            self::fail('Overlapping asset coordinates must block deletion.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('ACL asset', $error->getMessage());
        }
        self::assertSame($before, $this->snapshot());
    }

    public static function installRoutes(): array
    {
        return [['install'], ['update'], ['discover_install']];
    }

    #[DataProvider('installRoutes')]
    public function testPackageInstallAndUpdateNeverRemoveTasks(string $route): void
    {
        $before = $this->snapshot();
        $package = new class extends Pkg_JemInstallerScript {
            public function checkRequirements()
            {
                return true;
            }
        };
        self::assertTrue($package->preflight($route, null));
        self::assertSame($before, $this->snapshot());
    }

    public static function reminderSettings(): array
    {
        return [[0], [1]];
    }

    #[DataProvider('reminderSettings')]
    public function testReinstallServiceRecreatesOnlyOneTaskFromRetainedSettings(int $enabled): void
    {
        $this->execute('CREATE TABLE t_extensions(extension_id INT PRIMARY KEY,type VARCHAR(32),folder VARCHAR(32),element VARCHAR(32),enabled INT)');
        $this->execute("INSERT INTO t_extensions VALUES(1,'plugin','task','jem',0)");
        $this->execute("INSERT INTO t_jem_config VALUES('reminders_enabled'," . $this->db->quote((string) $enabled) . ')');
        self::assertTrue($this->installer->preflight('uninstall', null));
        require_once JEM_TEST_ROOT . '/site/classes/reminderschedulerservice.class.php';
        $service = new JemReminderSchedulerService($this->db);
        $id = $service->syncFromConfig();
        self::assertGreaterThan(5, $id, 'Reinstall must allocate a new task ID.');
        self::assertSame($id, $service->syncFromConfig(), 'Repeated sync must reuse the new task.');
        $tasks = $this->snapshot()['tasks'];
        self::assertCount(3, $tasks);
        self::assertSame($enabled, (int) $tasks[2]['state']);
        self::assertSame(10, json_decode($tasks[2]['execution_rules'], true)['interval-minutes']);
    }
}
