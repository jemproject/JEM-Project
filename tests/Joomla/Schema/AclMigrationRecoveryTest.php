<?php

declare(strict_types=1);

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Asset;
use Joomla\Database\DatabaseFactory;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Disposable database only: never migrates or uninstalls the user's Joomla. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AclMigrationRecoveryTest extends TestCase
{
    private $db;
    private ?mysqli $server = null;
    private string $database = '';
    private string $root;

    protected function setUp(): void
    {
        if (getenv('JEM_TEST_ACL_MIGRATION') !== '1') {
            self::markTestSkipped('Set JEM_TEST_ACL_MIGRATION=1 to create an isolated temporary test database.');
        }
        $this->root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!defined('JDEBUG')) {
            define('JDEBUG', false);
        }
        self::assertFileExists($this->root . '/configuration.php');
        require_once $this->root . '/includes/defines.php';
        require_once $this->root . '/libraries/vendor/autoload.php';
        spl_autoload_register(function (string $class): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $this->root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once $this->root . '/configuration.php';
        $config = new JConfig();
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_issue2358_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
        $this->server->query('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4');
        $this->db = (new DatabaseFactory())->getDriver('mysqli', [
            'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $this->database, 'prefix' => 'test_',
        ]);
        Factory::$container = new Container();
        Factory::$container->set('DatabaseDriver', $this->db, true);
        Factory::$container->alias(\Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
        Factory::$container->set(\Joomla\CMS\Language\LanguageFactoryInterface::class, new \Joomla\CMS\Language\LanguageFactory(), true);
        Factory::$container->set(\Joomla\CMS\HTML\Registry::class, new \Joomla\CMS\HTML\Registry(), true);
        Factory::$container->set('webassetregistry', new \Joomla\CMS\WebAsset\WebAssetRegistry(), true);
        Factory::$document = new \Joomla\CMS\Document\HtmlDocument();
        Factory::$application = new class {
            private Dispatcher $dispatcher;
            public $identity;
            public function __construct() { $this->dispatcher = new Dispatcher(); }
            public function getDispatcher() { return $this->dispatcher; }
            public function getLanguage() { return \Joomla\CMS\Language\Language::getInstance('en-GB'); }
            public function getConfig() { return new \Joomla\Registry\Registry(['offset' => 'UTC']); }
            public function getDocument() { return Factory::$document; }
            public function getIdentity() { return $this->identity; }
            public function getInput() { return new \Joomla\Input\Input(['option' => 'com_jem']); }
            public function isClient($client) { return $client === 'administrator'; }
            public function getTemplate($params = false) { return $params ? (object) ['template' => 'atum', 'parent' => ''] : 'atum'; }
            public function get($key, $default = null) { return $default; }
        };
        require_once JEM_TEST_ROOT . '/admin/classes/aclmigration.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/aclmigrationstatus.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/venueaccess.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/frontendcategoryaccess.class.php';
        $this->sql('CREATE TABLE #__assets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parent_id INT NOT NULL, lft INT NOT NULL, rgt INT NOT NULL, level INT NOT NULL, name VARCHAR(50) NOT NULL UNIQUE, title VARCHAR(100) NOT NULL, rules VARCHAR(5120) NOT NULL, KEY idx_lft_rgt(lft,rgt), KEY idx_parent_id(parent_id)) ENGINE=InnoDB');
        $this->sql('CREATE TABLE #__jem_categories (id INT PRIMARY KEY, parent_id INT NOT NULL, lft INT NOT NULL, catname VARCHAR(255) NOT NULL, asset_id INT NOT NULL DEFAULT 0, KEY idx_asset_id(asset_id)) ENGINE=InnoDB');
        $this->sql('CREATE TABLE #__jem_venues (id INT PRIMARY KEY, venue VARCHAR(255) NOT NULL, asset_id INT NOT NULL DEFAULT 0, KEY idx_asset_id(asset_id)) ENGINE=InnoDB');
        $this->sql('CREATE TABLE #__jem_config (keyname VARCHAR(100) PRIMARY KEY, value TEXT) ENGINE=InnoDB');
        $this->sql('CREATE TABLE #__jem_countries (iso2 VARCHAR(2) PRIMARY KEY, currency VARCHAR(3) NOT NULL DEFAULT "") ENGINE=InnoDB');
        $this->sql("INSERT INTO #__assets VALUES (1,0,0,9,0,'root.1','Root','{}'),(2,1,1,2,1,'com_content','Articles','{}'),(3,1,3,6,1,'com_jem','JEM','{}'),(4,3,4,5,2,'com_jem.field.7','Retained field','{\"core.edit\":{\"2\":false}}'),(5,1,7,8,1,'com_users','Users','{}')");
    }

    protected function tearDown(): void
    {
        if ($this->server && preg_match('/^jem_issue2358_test_[0-9]+_[a-f0-9]{8}$/D', $this->database)) {
            $this->server->query('DROP DATABASE `' . $this->database . '`');
            $this->server->close();
        }
    }

    private function sql(string $sql): void { $this->db->setQuery($sql)->execute(); }

    private function seed(int $count, bool $deep = false): void
    {
        $values = [];
        for ($id = 2; $id <= $count + 1; ++$id) {
            $parent = $deep ? $id - 1 : 1;
            $values[] = '(' . $id . ',' . $parent . ',' . ($id * 2) . ',' . $this->db->quote('Category ' . $id) . ',0)';
        }
        $this->sql('INSERT INTO #__jem_categories VALUES ' . implode(',', $values));
    }

    private function snapshot(): array
    {
        return (array) $this->db->setQuery('SELECT * FROM #__assets ORDER BY id')->loadAssocList('id');
    }

    private function assertLinks(): void
    {
        $assets = $this->snapshot();
        JemAclAssetMigration::validateTree($assets);
        $names = array_column($assets, null, 'name');
        foreach ($this->db->setQuery('SELECT * FROM #__jem_categories')->loadAssocList() as $row) {
            $asset = $names['com_jem.category.' . $row['id']];
            self::assertSame((int) $asset['id'], (int) $row['asset_id']);
            $parent = (int) $row['parent_id'] === 1 ? 3 : (int) $names['com_jem.category.' . $row['parent_id']]['id'];
            self::assertSame($parent, (int) $asset['parent_id']);
        }
    }

    public function testFirstMigrationOf1101CategoriesAndVenuesAndNoOpRetry(): void
    {
        $this->seed(1101);
        $this->sql("INSERT INTO #__jem_venues VALUES (1,'Hall',0),(2,'Studio',0)");
        $before = $this->snapshot();
        $start = microtime(true);
        $result = JemAclMigration::run($this->db, 60);
        self::assertTrue($result['complete'], $result['error']);
        self::assertLessThan(30.0, microtime(true) - $start, '1101-category migration must fit comfortably inside a 120-second installer limit on the test host.');
        $this->assertLinks();
        foreach ($before as $id => $asset) {
            $after = $this->snapshot()[$id];
            foreach (['id', 'name', 'title', 'rules', 'parent_id', 'level'] as $field) {
                self::assertSame($asset[$field], $after[$field]);
            }
        }
        $complete = $this->snapshot();
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        self::assertSame($complete, $this->snapshot());
        self::assertFalse(JemAclMigrationStatus::pending());
    }

    public function testDeepTreeCrossesBatchBoundaries(): void
    {
        $this->seed(250, true);
        self::assertSame([], (new JemAclAssetMigration($this->db, 60, 17))->repair('category'));
        $this->assertLinks();
    }

    public function testPendingMigrationCanResumeAndBlocksResourcePermissions(): void
    {
        $this->seed(25);
        $result = JemAclMigration::run($this->db, 0);
        self::assertFalse($result['complete']);
        self::assertSame(25, $result['remaining']);
        self::assertTrue(JemAclMigrationStatus::pending());
        $user = new class {
            public int $id = 42;
            public function authorise(...$args) { return true; }
        };
        self::assertFalse(JemVenueAccess::canUse($user, 1));
        self::assertFalse(JemFrontendCategoryAccess::canCreate($user, 1));
        self::assertFalse(JemFrontendCategoryAccess::canEdit($user, (object) ['id' => 2]));
        self::assertFalse(JemFrontendCategoryAccess::canEditState($user));
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        self::assertFalse(JemAclMigrationStatus::pending());
        $this->assertLinks();
    }

    public function testFailedBatchRollsBackCoordinatesRowsAndLinksThenResumes(): void
    {
        $this->seed(20);
        $before = $this->snapshot();
        $this->db->getConnection()->query("CREATE TRIGGER fail_asset BEFORE INSERT ON test_assets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected insert failure'");
        $result = JemAclMigration::run($this->db, 60);
        self::assertFalse($result['complete']);
        self::assertStringContainsString('Injected insert failure', $result['error']);
        self::assertSame($before, $this->snapshot());
        self::assertSame(0, (int) $this->db->setQuery('SELECT SUM(asset_id) FROM #__jem_categories')->loadResult());
        $this->db->getConnection()->query('DROP TRIGGER fail_asset');
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $this->assertLinks();
    }

    public function testExistingRulesIdsAndParentRepairsArePreserved(): void
    {
        $this->seed(3, true);
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $id = (int) $this->db->setQuery("SELECT id FROM #__assets WHERE name='com_jem.category.4'")->loadResult();
        $rules = '{"jem.events.create":{"2":false,"8":true}}';
        $asset = new Asset($this->db);
        $asset->load($id);
        $asset->rules = $rules;
        $asset->setLocation(3, 'last-child');
        self::assertTrue($asset->store());
        $this->sql("UPDATE #__jem_categories SET asset_id=0,catname='Renamed' WHERE id=4");
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $this->assertLinks();
        self::assertSame($rules, $this->snapshot()[$id]['rules']);
        self::assertSame('Renamed', $this->snapshot()[$id]['title']);
        $asset->load($id);
        self::assertFalse((new \Joomla\CMS\Access\Rules($asset->rules))->allow('jem.events.create', [2]));
        self::assertTrue((new \Joomla\CMS\Access\Rules($asset->rules))->allow('jem.events.create', [8]));
    }

    public function testInvalidTreeIsReportedWithoutRebuildingOtherAssets(): void
    {
        $this->seed(5);
        $this->sql('UPDATE #__assets SET rgt=6 WHERE id=4');
        $before = $this->snapshot();
        $result = JemAclMigration::run($this->db, 60);
        self::assertFalse($result['complete']);
        self::assertNotEmpty($result['error']);
        self::assertSame($before, $this->snapshot());
    }

    public function testForeignParentMismatchDoesNotBlockJemMigration(): void
    {
        $this->seed(3);
        $this->sql("UPDATE #__assets SET rgt=13 WHERE id=1");
        $this->sql("UPDATE #__assets SET name='com_contact',rgt=6 WHERE id=2");
        $this->sql('UPDATE #__assets SET lft=lft+4,rgt=rgt+4 WHERE id IN (3,4,5)');
        $this->sql("INSERT INTO #__assets VALUES (6,2,2,5,2,'com_contact.category.38','Contact category','{}'),"
            . "(7,2,3,4,4,'com_contact.contact.7','Contact','{}')");

        $before = $this->snapshot();
        try {
            JemAclAssetMigration::validateTree($before);
            self::fail('The global diagnostic must identify the foreign ACL mismatch.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('com_contact.contact.7', $error->getMessage());
        }

        $result = JemAclMigration::run($this->db, 60);
        self::assertTrue($result['complete'], $result['error']);
        self::assertFalse(JemAclMigrationStatus::pending());
        $after = $this->snapshot();
        foreach ([2, 6, 7] as $id) {
            foreach (['id', 'parent_id', 'level', 'name', 'title', 'rules'] as $field) {
                self::assertSame($before[$id][$field], $after[$id][$field]);
            }
        }
        JemAclAssetMigration::validateForJemMigration($after);
        $names = array_column($after, null, 'name');
        foreach ($this->db->setQuery('SELECT id,asset_id FROM #__jem_categories')->loadAssocList() as $row) {
            $asset = $names['com_jem.category.' . $row['id']];
            self::assertSame((int) $asset['id'], (int) $row['asset_id']);
            self::assertSame(3, (int) $asset['parent_id']);
        }

        $this->sql("UPDATE #__jem_categories SET catname='Renamed' WHERE id=2");
        $retry = JemAclMigration::run($this->db, 60);
        self::assertTrue($retry['complete'], $retry['error']);
        $updated = $this->snapshot();
        self::assertSame('Renamed', array_column($updated, null, 'name')['com_jem.category.2']['title']);
        foreach ([2, 6, 7] as $id) {
            foreach (['id', 'parent_id', 'level', 'name', 'title', 'rules'] as $field) {
                self::assertSame($before[$id][$field], $updated[$id][$field]);
            }
        }
    }

    public function testCurrencyFieldRemainsSelectableWithEmptyCatalogue(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/money.class.php';
        require_once JEM_TEST_ROOT . '/admin/models/fields/currencyoptions.php';
        $field = new class extends JFormFieldCurrencyOptions {
            public function options(): array { return $this->getOptions(); }
            protected function getRenderer($layoutId = 'default') {
                return new \Joomla\CMS\Layout\FileLayout($layoutId, null, ['component' => 'none']);
            }
        };
        self::assertTrue($field->setup(new SimpleXMLElement('<field name="defaultCurrency" type="currencyoptions" required="true" default="EUR" />'), 'USD'));
        $values = array_map(static fn($o) => $o->value, $field->options());
        self::assertContains('EUR', $values);
        self::assertContains('USD', $values);
        self::assertSame('USD', $field->value);
        self::assertStringContainsString('selected', $field->input);
    }

    public function testCommittedBatchesSurviveALaterFailure(): void
    {
        $this->seed(30);
        $this->db->getConnection()->query("CREATE TRIGGER fail_later BEFORE INSERT ON test_assets FOR EACH ROW BEGIN IF NEW.name='com_jem.category.22' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected later failure'; END IF; END");
        try {
            (new JemAclAssetMigration($this->db, 60, 10))->repair('category');
            self::fail('The third batch should fail.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Injected later failure', $error->getMessage());
        }
        self::assertSame(20, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_categories WHERE asset_id>0')->loadResult());
        $before = $this->snapshot();
        JemAclAssetMigration::validateTree($before);
        $this->db->getConnection()->query('DROP TRIGGER fail_later');
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        foreach ($before as $id => $asset) {
            self::assertSame($asset['name'], $this->snapshot()[$id]['name']);
        }
        $this->assertLinks();
    }

    public function testNonTransactionalLegacyResourceUsesNativeWriter(): void
    {
        $this->seed(5, true);
        $this->sql('ALTER TABLE #__jem_categories ENGINE=MyISAM');
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $this->assertLinks();
    }

    public function testSecondRunnerCannotChangePendingState(): void
    {
        $config = new JConfig();
        $other = new mysqli($config->host, $config->user, $config->password, $this->database);
        $lock = 'jem.acl.' . substr(hash('sha256', $this->database . 'test_'), 0, 40);
        $other->query("SELECT GET_LOCK('" . $lock . "',0)");
        try {
            $result = JemAclMigration::run($this->db, 60);
            self::assertFalse($result['complete']);
            self::assertStringContainsString('Another ACL migration', $result['error']);
            self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_config')->loadResult());
        } finally {
            $other->query("SELECT RELEASE_LOCK('" . $lock . "')");
            $other->close();
        }
    }

    public function testSettingsCannotOverwriteMigrationState(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/config.class.php';
        $this->seed(5);
        JemAclMigration::run($this->db, 0);
        $config = JemConfig::getInstance();
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $config->bind(['defaultCurrency' => 'USD', 'acl_migration_state' => '{"complete":false}']);
        self::assertTrue($config->store());
        JemAclMigrationStatus::reset();
        self::assertFalse(JemAclMigrationStatus::pending());
        self::assertSame('USD', $this->db->setQuery("SELECT value FROM #__jem_config WHERE keyname='defaultCurrency'")->loadResult());
    }

    public function testInheritedAllowAndDenyUseTheMigratedTree(): void
    {
        // Supply the installed-component catalogue; permission calculation remains native Joomla.
        $components = new ReflectionProperty(\Joomla\CMS\Component\ComponentHelper::class, 'components');
        $components->setValue(null, ['com_jem' => (object) ['option' => 'com_jem', 'enabled' => 1]]);
        $this->seed(8, true);
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $rules = '{"jem.events.create":{"2":false,"8":true}}';
        $this->sql('UPDATE #__assets SET rules=' . $this->db->quote($rules) . " WHERE name='com_jem.category.2'");
        Access::clearStatics();
        $rules = Access::getAssetRules('com_jem.category.9', true, true);
        self::assertFalse($rules->allow('jem.events.create', [2]));
        self::assertTrue($rules->allow('jem.events.create', [8]));
    }

    public function testFailureWhenLinkingResourcesRollsBackInsertedAssetsToo(): void
    {
        $this->seed(20);
        $before = $this->snapshot();
        $this->db->getConnection()->query("CREATE TRIGGER fail_link BEFORE UPDATE ON test_jem_categories FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected link failure'");
        $result = JemAclMigration::run($this->db, 60);
        self::assertFalse($result['complete']);
        self::assertStringContainsString('Injected link failure', $result['error']);
        self::assertSame($before, $this->snapshot());
        self::assertSame(0, (int) $this->db->setQuery('SELECT SUM(asset_id) FROM #__jem_categories')->loadResult());
        $this->db->getConnection()->query('DROP TRIGGER fail_link');
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $this->assertLinks();
    }

    public function testMissingCategoryParentCannotFallBackToComponentPermissions(): void
    {
        $this->seed(3);
        $this->sql('UPDATE #__jem_categories SET parent_id=999 WHERE id=2');
        $before = $this->snapshot();
        $result = JemAclMigration::run($this->db, 60);
        self::assertFalse($result['complete']);
        self::assertStringContainsString('missing parent', $result['error']);
        self::assertSame($before, $this->snapshot());
        self::assertTrue(JemAclMigrationStatus::pending());
    }

    public function testReadCommittedHostKeepsItsIsolationSettingAfterMigration(): void
    {
        $this->seed(20);
        $this->sql('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $this->assertLinks();
        self::assertSame('READ-COMMITTED', $this->db->setQuery('SELECT @@SESSION.tx_isolation')->loadResult());
    }

    public function testNativeJoomlaWriterWaitsUntilBulkTransactionCommits(): void
    {
        $this->seed(20);
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', JEM_TEST_ROOT . '/tests/Support/acl_native_writer.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 10);
            fwrite($pipes[0], json_encode(['root' => $this->root, 'database' => $this->database], JSON_THROW_ON_ERROR) . "\n");
            self::assertSame("READY\n", fgets($pipes[1]));
            $monitor = new class ($pipes, $process) implements \Joomla\Database\QueryMonitorInterface {
                public bool $started = false;
                private array $pipes;
                private $process;
                private string $sql = '';
                public function __construct(array $pipes, $process) { $this->pipes = $pipes; $this->process = $process; }
                public function startQuery(string $sql, ?array $boundParams = null): void { $this->sql = $sql; }
                public function stopQuery(): void
                {
                    if ($this->started || !str_contains($this->sql, 'FROM test_assets ORDER BY id FOR UPDATE')) {
                        return;
                    }
                    $this->started = true;
                    fwrite($this->pipes[0], "GO\n");
                    TestCase::assertSame("STARTED\n", fgets($this->pipes[1]));
                    usleep(150000);
                    TestCase::assertTrue(proc_get_status($this->process)['running'], 'Native writer must not finish while migration holds the tree lock.');
                }
            };
            $this->db->setMonitor($monitor);
            $result = JemAclMigration::run($this->db, 60);
            $this->db->setMonitor(null);
            self::assertTrue($result['complete'], $result['error']);
            self::assertTrue($monitor->started);
            self::assertSame("DONE\n", fgets($pipes[1]));
            $this->assertLinks();
            self::assertSame(1, (int) $this->db->setQuery("SELECT COUNT(*) FROM #__assets WHERE name='com_content.article.99'")->loadResult());
        } finally {
            $this->db->setMonitor(null);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
    }

    public function testRecoveryControllerRequiresTokenAndPermissionAndCanResume(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/acl_recovery_controller_environment.php';
        require_once JEM_TEST_ROOT . '/admin/controllers/aclmigration.php';
        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        }
        $controller = (new ReflectionClass(JemControllerAclmigration::class))->newInstanceWithoutConstructor();
        Factory::getApplication()->identity = new class {
            public bool $allowed = false;
            public function authorise($action, $asset) { return $this->allowed && $action === 'core.admin' && $asset === 'com_jem'; }
        };
        try {
            $controller->resume();
            self::fail('Missing token accepted.');
        } catch (RuntimeException $error) {
            self::assertSame('Invalid token', $error->getMessage());
        }
        JemHelper::$token = true;
        try {
            $controller->resume();
            self::fail('Missing permission accepted.');
        } catch (RuntimeException $error) {
            self::assertSame(403, $error->getCode());
        }
        $this->seed(10);
        JemAclMigration::run($this->db, 0);
        Factory::getApplication()->identity->allowed = true;
        $controller->resume();
        self::assertFalse(JemAclMigrationStatus::pending());
        $redirect = new ReflectionProperty(\Joomla\CMS\MVC\Controller\BaseController::class, 'redirect');
        self::assertSame('index.php?option=com_jem', $redirect->getValue($controller));
        $this->assertLinks();
    }

    public function testBenchmarkNativeAndBulkMigrationOnSame1101Categories(): void
    {
        if (getenv('JEM_TEST_ACL_BENCHMARK') !== '1') {
            self::markTestSkipped('Set JEM_TEST_ACL_BENCHMARK=1 for the native-versus-bulk timing comparison.');
        }
        $this->seed(1101);
        $initial = $this->snapshot();
        $rows = $this->db->setQuery('SELECT * FROM #__jem_categories ORDER BY lft')->loadObjectList();
        $start = microtime(true);
        $queries = $this->db->getCount();
        foreach ($rows as $row) {
            $asset = new Asset($this->db);
            $asset->loadByName('com_jem.category.' . $row->id);
            $asset->name = 'com_jem.category.' . $row->id;
            $asset->title = $row->catname;
            $asset->rules = '{}';
            $asset->setLocation(3, 'last-child');
            self::assertTrue($asset->check() && $asset->store());
            $this->sql('UPDATE #__jem_categories SET asset_id=' . (int) $asset->id . ' WHERE id=' . (int) $row->id);
        }
        $nativeSeconds = microtime(true) - $start;
        $nativeQueries = $this->db->getCount() - $queries;
        $this->sql('DELETE FROM #__assets WHERE id>5');
        foreach ($initial as $id => $row) {
            $this->sql('UPDATE #__assets SET lft=' . (int) $row['lft'] . ',rgt=' . (int) $row['rgt'] . ' WHERE id=' . $id);
        }
        $this->sql('UPDATE #__jem_categories SET asset_id=0');
        $start = microtime(true);
        $queries = $this->db->getCount();
        self::assertTrue(JemAclMigration::run($this->db, 60)['complete']);
        $bulkSeconds = microtime(true) - $start;
        $bulkQueries = $this->db->getCount() - $queries;
        self::assertLessThan($nativeQueries, $bulkQueries);
        $this->assertLinks();
        echo json_encode(['categories' => 1101, 'database' => $this->db->getVersion(), 'joomla' => (new \Joomla\CMS\Version())->getShortVersion(),
            'native_seconds' => round($nativeSeconds, 3), 'bulk_seconds' => round($bulkSeconds, 3),
            'native_queries' => $nativeQueries, 'bulk_queries' => $bulkQueries], JSON_THROW_ON_ERROR) . "\n";
    }
}
