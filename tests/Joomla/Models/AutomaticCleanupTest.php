<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Automatic cleanup against real Joomla SQL, Finder, JEM tables and disposable files. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AutomaticCleanupTest extends TestCase
{
    private string $root = '';
    private string $database = '';
    private ?mysqli $server = null;
    private ?AutomaticCleanupDriver $db = null;
    private ?Joomla\Database\Mysqli\MysqliDriver $observer = null;
    private bool $hierarchy = false;
    private array $notifications = [];

    /** @return void Create isolated libraries, database and synthetic expired/future events. */
    protected function setUp(): void
    {
        $joomla = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($joomla . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT; its site data is never modified.');
        }
        require_once $joomla . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($joomla): void {
            foreach (['Joomla\\CMS\\' => '/libraries/src/', 'Joomla\\Component\\Finder\\Administrator\\' => '/administrator/components/com_finder/src/'] as $prefix => $directory) {
                if (str_starts_with($class, $prefix)) {
                    $file = $joomla . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($file)) { require_once $file; }
                }
            }
        });
        require_once $joomla . '/configuration.php';
        $this->root = sys_get_temp_dir() . '/jem-auto-cleanup-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/components/com_jem/classes', 0777, true);
        define('JPATH_SITE', $this->root);
        define('JPATH_ROOT', $this->root);
        define('JPATH_BASE', $this->root);
        define('JPATH_ADMINISTRATOR', $this->root . '/administrator');
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        mkdir($this->root . '/language/en-GB', 0777, true);
        copy($joomla . '/language/en-GB/langmetadata.xml', $this->root . '/language/en-GB/langmetadata.xml');
        file_put_contents($this->root . '/components/com_jem/factory.php', '<?php // Isolated application facade.');
        foreach (['eventimagepath', 'venueimagepath', 'categoryimagepath', 'menuviewscope', 'cssfilepolicy', 'modulecssoverride', 'imagepublicationpolicy', 'imagefilenamepolicy', 'ageaccess', 'eventschedule', 'log'] as $file) {
            file_put_contents($this->root . '/components/com_jem/classes/' . $file . '.class.php', '<?php // Unused dependency.');
        }
        copy(JEM_TEST_ROOT . '/site/classes/deletedobjectcleanup.class.php', $this->root . '/components/com_jem/classes/deletedobjectcleanup.class.php');
        require_once JEM_TEST_ROOT . '/plugins/plg_finder_jem/jem.php';
        require_once JEM_TEST_ROOT . '/tests/Support/automatic_cleanup_environment.php';
        require_once JEM_TEST_ROOT . '/site/classes/config.class.php';
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/admin/tables/event.php';
        $this->hierarchy = is_file(JEM_TEST_ROOT . '/site/classes/registrationidentity.class.php');
        if ($this->hierarchy) {
            foreach (['registrationservice', 'registrationidentity', 'registrationtransition', 'notificationservice'] as $class) {
                require_once JEM_TEST_ROOT . '/site/classes/' . $class . '.class.php';
            }
        }
        $config = new JConfig();
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_auto_test_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $options = ['host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $this->database, 'prefix' => 't_'];
        $this->db = new AutomaticCleanupDriver($options);
        $this->observer = new Joomla\Database\Mysqli\MysqliDriver($options);
        $container = new Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
        $container->set(DispatcherInterface::class, new Dispatcher(), true);
        $container->set(Joomla\CMS\Language\LanguageFactoryInterface::class, new Joomla\CMS\Language\LanguageFactory());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Registry(['offset' => 'Europe/Madrid']);
        Factory::$application = new AutomaticCleanupApplication();
        (new ReflectionProperty(PluginHelper::class, 'plugins'))->setValue(null, [(object) ['type' => 'finder', 'name' => 'jem']]);
        (new ReflectionProperty(Joomla\CMS\Component\ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new Joomla\CMS\Component\ComponentRecord(['params' => '{}']),
        ]);
        foreach ([
            'jem_events' => 'id INT PRIMARY KEY, title VARCHAR(255) DEFAULT \'Test\', published INT DEFAULT 1, dates DATE, enddates DATE, parent_event_id INT DEFAULT 0, recurrence_first_id INT DEFAULT 0, recurrence_number INT DEFAULT 0, recurrence_type INT DEFAULT 0, recurrence_limit_date DATE, recurrence_limit INT DEFAULT 0, recurrence_byday VARCHAR(40), recurrence_bylastday VARCHAR(40), recurrence_counter INT DEFAULT 0, INDEX(parent_event_id)',
            'jem_config' => 'keyname VARCHAR(100) PRIMARY KEY, value TEXT',
            'jem_attachments' => 'id INT PRIMARY KEY, object VARCHAR(255), file VARCHAR(255)',
            'jem_links' => 'id INT PRIMARY KEY, event_id INT, url VARCHAR(255)',
            'jem_cats_event_relations' => 'itemid INT, catid INT',
            'jem_categories' => 'id INT PRIMARY KEY, published INT, access INT',
            'jem_register' => 'id INT PRIMARY KEY, event INT, uid INT DEFAULT 7, places INT DEFAULT 2, status INT DEFAULT 1, waiting INT DEFAULT 0, revision INT DEFAULT 1, reference VARCHAR(28) DEFAULT \'test\'',
            'jem_reminders' => 'event_id INT',
            'jem_event_prices' => 'event_id INT',
            'jem_capacity_pools' => 'event_id INT',
            'jem_event_space_layouts' => 'event_id INT',
            'jem_notifications' => 'event_id INT, notification_type VARCHAR(30), state VARCHAR(30), cancelled_at DATETIME, next_attempt_at DATETIME',
            'finder_links' => 'link_id INT PRIMARY KEY, url VARCHAR(255), state INT',
            'finder_terms' => 'term_id INT PRIMARY KEY, links INT',
            'finder_links_terms' => 'link_id INT, term_id INT',
            'finder_taxonomy_map' => 'link_id INT, node_id INT',
            'finder_taxonomy' => 'id INT PRIMARY KEY, parent_id INT, lft INT, rgt INT, level INT, path VARCHAR(255), alias VARCHAR(255)',
        ] as $name => $columns) {
            $this->sql('CREATE TABLE #__' . $name . ' (' . $columns . ') ENGINE=InnoDB');
        }
        if ($this->hierarchy) {
            preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_register_history`.*?;\s/s', file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql'), $match);
            $this->sql($match[0]);
        }
        $this->sql("INSERT INTO #__jem_config VALUES ('lastupdate','0'),('runningupdate','0'),('weekdaystart','1'),('minus','1'),('oldevent','1'),('attachments_path','files'),('unrelated','original')");
        $this->sql('INSERT INTO #__jem_categories VALUES (8,1,1)');
        $this->event(1, '2001-01-01');
        $this->event(2, '2099-12-01');
        $this->sql('INSERT INTO #__jem_register (id,event) VALUES (1,1),(2,2)');
        Factory::$application->finder = new AutomaticCleanupFinder($this->db);
        foreach (['onFinderAfterDelete', 'onFinderChangeState'] as $name) {
            $container->get(DispatcherInterface::class)->addListener($name, function ($event) use ($name): void {
                $this->notifications[] = [$name, (int) $this->db->setQuery('SELECT @@in_transaction')->loadResult()];
            }, 100);
        }
    }

    /** @return void Delete only the randomly named test database and temporary files. */
    protected function tearDown(): void
    {
        if ($this->db) { $this->db->failSql = ''; $this->db->disconnect(); }
        $this->observer?->disconnect();
        if ($this->server && preg_match('/^jem_auto_test_[a-f0-9]{16}$/D', $this->database)) {
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
        if ($this->root !== '' && is_dir($this->root) && str_starts_with($this->root, sys_get_temp_dir() . '/jem-auto-cleanup-')) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
    }

    /** @param string $sql Fixture statement. @return void */
    private function sql(string $sql): void { $this->db->setQuery($sql)->execute(); }
    /** @param string $sql Scalar query. @return mixed */
    private function value(string $sql) { return $this->db->setQuery($sql)->loadResult(); }
    /** @param int $id Identity. @param string $date Start date. @param int $parent Optional parent. @return void */
    private function event(int $id, string $date, int $parent = 0): void
    {
        $this->sql("INSERT INTO #__jem_events (id,dates,parent_event_id) VALUES ($id,'$date',$parent)");
        $this->sql("INSERT INTO #__jem_cats_event_relations VALUES ($id,8)");
        $this->sql("INSERT INTO #__jem_links VALUES ($id,$id,'https://example.org/shared')");
        $this->sql("INSERT INTO #__finder_links VALUES ($id,'index.php?option=com_jem&view=event&id=$id',1)");
        $this->sql("INSERT INTO #__jem_attachments VALUES ($id,'event$id','test.pdf')");
        mkdir($this->root . '/files/event' . $id, 0777, true);
        file_put_contents($this->root . '/files/event' . $id . '/test.pdf', 'Test');
    }

    /** @return void Permanent cleanup removes Finder and owned resources, preserving future records. */
    public function testDailyDeleteUsesTablesAndRealFinder(): void
    {
        JemHelper::cleanup();
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM #__jem_events WHERE id=1'));
        foreach (['finder_links' => 'link_id', 'jem_links' => 'event_id', 'jem_cats_event_relations' => 'itemid', 'jem_register' => 'event'] as $table => $column) {
            self::assertSame([2], array_map('intval', $this->db->setQuery('SELECT ' . $column . ' FROM #__' . $table)->loadColumn()));
        }
        self::assertFileDoesNotExist($this->root . '/files/event1/test.pdf');
        self::assertFileExists($this->root . '/files/event2/test.pdf');
        self::assertSame([['onFinderAfterDelete', 0]], $this->notifications);
        self::assertNotEmpty($this->value("SELECT value FROM #__jem_config WHERE keyname='lastupdate'"));
        self::assertSame('0', $this->value("SELECT value FROM #__jem_config WHERE keyname='runningupdate'"));
        self::assertSame([], JemLog::$messages ? array_values(array_filter(JemLog::$messages, static fn ($message) => str_contains($message, 'failed'))) : []);
        if ($this->hierarchy) {
            self::assertSame(['actor_user_id' => 0, 'source' => 'system.cleanup', 'reason_code' => 'outdated_event_deleted'], $this->db->setQuery('SELECT actor_user_id,source,reason_code FROM #__jem_register_history')->loadAssoc());
        }
    }

    /** @return array Cleanup modes and established Joomla Finder state. */
    public static function stateModes(): array { return [[2,2,1], [3,-2,0], [4,0,0]]; }

    /** @param int $mode Mode. @param int $state Event state. @param int $finderState Native translated state. @return void */
    #[DataProvider('stateModes')]
    public function testStateChangesNotifyFinderAndKeepResources(int $mode, int $state, int $finderState): void
    {
        $this->sql("UPDATE #__jem_config SET value='$mode' WHERE keyname='oldevent'");
        JemHelper::cleanup();
        self::assertSame($state, (int) $this->value('SELECT published FROM #__jem_events WHERE id=1'));
        self::assertSame($finderState, (int) $this->value('SELECT state FROM #__finder_links WHERE link_id=1'));
        self::assertSame(1, (int) $this->value('SELECT state FROM #__finder_links WHERE link_id=2'));
        self::assertFileExists($this->root . '/files/event1/test.pdf');
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__jem_register'));
        self::assertSame([['onFinderChangeState', 0]], $this->notifications);
    }

    /** @return void A failed relation delete rolls back the event and does not touch Finder or files. */
    public function testDeleteFailureRollsBackAndLeavesRetryBackoff(): void
    {
        $this->db->failSql = 'DELETE FROM `#__jem_links`';
        JemHelper::cleanup();
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__jem_events'));
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__jem_cats_event_relations'));
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__jem_register'));
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__finder_links'));
        self::assertFileExists($this->root . '/files/event1/test.pdf');
        self::assertSame([], $this->notifications);
        self::assertSame('0', $this->value("SELECT value FROM #__jem_config WHERE keyname='lastupdate'"));
        self::assertNotEmpty($this->value("SELECT value FROM #__jem_config WHERE keyname='runningupdate'"));
        if ($this->hierarchy) {
            self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM #__jem_register_history'));
        }
        $this->db->failSql = '';
        JemHelper::cleanup();
        self::assertSame([], $this->notifications);
        $this->sql("UPDATE #__jem_config SET value='0' WHERE keyname='runningupdate'");
        JemHelper::config()->runningupdate = 0;
        JemHelper::cleanup();
        self::assertSame(1, (int) $this->value('SELECT COUNT(*) FROM #__jem_events'));
    }

    /** @return void An index outage cannot undo the already committed event deletion. */
    public function testFinderFailureIsLoggedAfterCommit(): void
    {
        $this->db->failSql = 'DELETE FROM `#__finder_links_terms`';
        JemHelper::cleanup();
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM #__jem_events WHERE id=1'));
        self::assertFileDoesNotExist($this->root . '/files/event1/test.pdf');
        self::assertSame(1, (int) $this->value('SELECT COUNT(*) FROM #__finder_links WHERE link_id=1'));
        self::assertStringContainsString('rebuild the Smart Search index', implode('\n', JemLog::$messages));
        self::assertSame([['onFinderAfterDelete', 0]], $this->notifications);
    }

    /** @return void A disabled Finder plugin does not block deletion or synthesize notifications. */
    public function testDisabledFinderIsOptional(): void
    {
        (new ReflectionProperty(PluginHelper::class, 'plugins'))->setValue(null, []);
        JemHelper::cleanup();
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM #__jem_events WHERE id=1'));
        self::assertSame([], $this->notifications);
    }

    /** @return void A future child preserves its parent; a wholly expired subtree deletes child first. */
    public function testHierarchyDoesNotCreateOrphans(): void
    {
        if (!$this->hierarchy) { self::assertTrue(true); return; }
        $this->sql('UPDATE #__jem_events SET parent_event_id=1 WHERE id=2');
        $this->event(3, '2001-01-01', 1);
        $this->event(10, '2001-01-01');
        $this->event(11, '2001-01-01', 10);
        $this->event(12, '2001-01-01', 11);
        JemHelper::cleanup();
        self::assertSame([1,2], array_map('intval', $this->db->setQuery('SELECT id FROM #__jem_events ORDER BY id')->loadColumn()));
        self::assertFileExists($this->root . '/files/event1/test.pdf');
        self::assertSame([1,2], array_map('intval', $this->db->setQuery('SELECT link_id FROM #__finder_links ORDER BY link_id')->loadColumn()));
    }

    /** @return void Recheck a selected event's date using a committed concurrent reschedule. */
    public function testRescheduledEventIsNotDeleted(): void
    {
        $this->db->beforeLock = function (): void {
            $this->observer->setQuery("UPDATE #__jem_events SET dates='2099-12-02' WHERE id=1")->execute();
        };
        JemHelper::cleanup();
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__jem_events'));
        self::assertSame([], $this->notifications);
        self::assertFileExists($this->root . '/files/event1/test.pdf');
    }

    /** @return void Scoped recurrence saves never run global cleanup or complete its daily marker. */
    public function testScopedRecurrencePreservesUnrelatedEventsAndClaim(): void
    {
        $this->db->transactionStart();
        JemHelper::cleanup(2, 2);
        self::assertSame(1, (int) $this->value('SELECT @@in_transaction'));
        $this->db->transactionRollback();
        self::assertSame(2, (int) $this->value('SELECT COUNT(*) FROM #__jem_events'));
        self::assertSame('0', $this->value("SELECT value FROM #__jem_config WHERE keyname='runningupdate'"));
        self::assertSame([], $this->notifications);
    }

    /** @return void Only one connection can claim a daily snapshot; stale claims cannot complete another run. */
    public function testClaimOwnershipAndUnrelatedSettingsArePreserved(): void
    {
        $settings = JemHelper::config();
        $other = clone $settings;
        $now = time();
        self::assertTrue(JemAutomaticCleanup::claim($this->db, $settings, $now, 300));
        self::assertFalse(JemAutomaticCleanup::claim($this->observer, $other, $now, 300));
        $this->observer->setQuery("UPDATE #__jem_config SET value='edited by administrator' WHERE keyname='unrelated'")->execute();
        JemAutomaticCleanup::complete($this->db, $settings, $now);
        self::assertSame('edited by administrator', $this->value("SELECT value FROM #__jem_config WHERE keyname='unrelated'"));
        self::assertFalse(JemAutomaticCleanup::claim($this->observer, $other, $now + 1, 300));
        $this->sql("UPDATE #__jem_config SET value='" . ($now + 600) . "' WHERE keyname='runningupdate'");
        JemAutomaticCleanup::complete($this->db, $settings, $now);
        self::assertSame((string) ($now + 600), $this->value("SELECT value FROM #__jem_config WHERE keyname='runningupdate'"));
    }

    /** @return void Global cleanup cannot commit a caller's open transaction. */
    public function testGlobalCleanupWaitsOutsideCallerTransaction(): void
    {
        $this->db->transactionStart();
        JemHelper::cleanup(2);
        self::assertSame(1, (int) $this->value('SELECT @@in_transaction'));
        self::assertSame([], $this->notifications);
        $this->db->transactionRollback();
        self::assertSame('0', $this->value("SELECT value FROM #__jem_config WHERE keyname='runningupdate'"));
    }

    /** @return void A state SQL failure preserves publication and never notifies the index. */
    public function testStateFailurePreservesIndexAndRetriesLater(): void
    {
        $this->sql("UPDATE #__jem_config SET value='3' WHERE keyname='oldevent'");
        $this->db->failSql = 'UPDATE #__jem_events SET published';
        JemHelper::cleanup();
        self::assertSame(1, (int) $this->value('SELECT published FROM #__jem_events WHERE id=1'));
        self::assertSame(1, (int) $this->value('SELECT state FROM #__finder_links WHERE link_id=1'));
        self::assertSame([], $this->notifications);
        self::assertSame('0', $this->value("SELECT value FROM #__jem_config WHERE keyname='lastupdate'"));
        self::assertSame(0, (int) $this->value('SELECT @@in_transaction'));
    }

    /** @return void A concurrent unpublish is not overwritten by an archive selected earlier. */
    public function testChangedPublicationIsRecheckedUnderLock(): void
    {
        $this->sql("UPDATE #__jem_config SET value='2' WHERE keyname='oldevent'");
        $this->db->beforeLock = function (): void {
            $this->observer->setQuery('UPDATE #__jem_events SET published=0 WHERE id=1')->execute();
        };
        JemHelper::cleanup();
        self::assertSame(0, (int) $this->value('SELECT published FROM #__jem_events WHERE id=1'));
        self::assertSame([], $this->notifications);
    }

    /** @return void Joomla timezone cutoff, end date and missing dates retain their established meaning. */
    public function testDateBoundariesAndNullDates(): void
    {
        $yesterday = JemHelper::getJoomlaDate(-1);
        $today = JemHelper::getJoomlaDate();
        $this->sql("UPDATE #__jem_events SET enddates='$today' WHERE id=1");
        $this->event(3, $yesterday);
        $this->event(4, $today);
        $this->event(5, '2001-01-01');
        $this->sql('UPDATE #__jem_events SET dates=NULL WHERE id=5');
        JemHelper::cleanup();
        self::assertSame([1,2,4,5], array_map('intval', $this->db->setQuery('SELECT id FROM #__jem_events ORDER BY id')->loadColumn()));
        self::assertSame([['onFinderAfterDelete', 0]], $this->notifications);
    }

    /** @return void A daily exception before event mutations retains retry bookkeeping. */
    public function testDailyQueryExceptionDoesNotBreakFrontend(): void
    {
        $this->db->failSql = 'MAX(dates)';
        JemHelper::cleanup();
        self::assertSame('0', $this->value("SELECT value FROM #__jem_config WHERE keyname='lastupdate'"));
        self::assertNotEmpty($this->value("SELECT value FROM #__jem_config WHERE keyname='runningupdate'"));
        self::assertSame([], $this->notifications);
        self::assertStringContainsString('Daily event cleanup failed', implode('\n', JemLog::$messages));
    }

    /** @return void The audit context remains the administrator's on normal table deletion. */
    public function testManualDeletionRetainsItsAuditContext(): void
    {
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->delete(1));
        if ($this->hierarchy) {
            self::assertSame(['actor_user_id' => 7, 'source' => 'administrator.event.delete'], $this->db->setQuery('SELECT actor_user_id,source FROM #__jem_register_history')->loadAssoc());
        }
        self::assertSame([], $this->notifications);
    }

    /** @return void Housekeeping can force cleanup after the daily job has already completed. */
    public function testForcedCleanupRunsAfterCompletedDay(): void
    {
        JemHelper::cleanup();
        $this->event(3, '2001-01-01');
        JemHelper::cleanup();
        self::assertSame(1, (int) $this->value('SELECT COUNT(*) FROM #__jem_events WHERE id=3'));
        JemHelper::cleanup(1);
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM #__jem_events WHERE id=3'));
        self::assertSame([['onFinderAfterDelete', 0], ['onFinderAfterDelete', 0]], $this->notifications);
    }
}
