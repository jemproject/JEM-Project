<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real imports and Joomla nested/asset tables in a disposable MariaDB database only. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CategoryImportLifecycleTest extends TestCase
{
    private ?mysqli $server = null;
    private string $database = '';
    private $db;
    private JemModelImport $model;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_CATEGORY_IMPORT') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Opt in with JEM_TEST_CATEGORY_IMPORT=1 and JEM_TEST_JOOMLA_ROOT. Disposable database only.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once __DIR__ . '/../../Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        define('JPATH_ROOT', $root);
        define('JPATH_BASE', $root);
        require __DIR__ . '/../../Support/category_import_environment.php';
        Factory::$application = new CategoryImportApplication();
        $language = (new ReflectionClass(Joomla\CMS\Language\Language::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($language, 'strings'))->setValue($language, array_merge(
            parse_ini_file($root . '/language/en-GB/joomla.ini', false, INI_SCANNER_RAW),
            parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW)
        ));
        Factory::$language = $language;

        $options = ['host' => '127.0.0.1', 'user' => getenv('JEM_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('JEM_TEST_MYSQL_PASSWORD') ?: '', 'prefix' => 't_'];
        $this->server = new mysqli($options['host'], $options['user'], $options['password']);
        $this->database = 'jem_categorytest_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new Joomla\Database\Mysqli\MysqliDriver($options + ['database' => $this->database]);
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
        $container->set(Joomla\CMS\User\UserFactoryInterface::class, $this->createMock(Joomla\CMS\User\UserFactoryInterface::class));
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        $this->db->setQuery("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'")->execute();
        foreach (Joomla\Database\DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql')) as $sql) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS .#__jem_(categories|cats_event_relations)./', $sql)) {
                $this->db->setQuery($sql)->execute();
            }
        }
        $this->db->setQuery("CREATE TABLE #__assets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL, lft INT NOT NULL, rgt INT NOT NULL, level INT NOT NULL,
            name VARCHAR(50) NOT NULL UNIQUE, title VARCHAR(100) NOT NULL, rules VARCHAR(5120) NOT NULL,
            KEY idx_lft_rgt(lft,rgt), KEY idx_parent_id(parent_id)) ENGINE=InnoDB")->execute();
        $this->db->setQuery("INSERT INTO #__assets VALUES
            (1,0,0,7,0,'root.1','Root','{}'),(2,1,1,2,1,'com_jem','JEM','{}'),
            (3,1,3,6,1,'com_content','Articles','{}'),
            (4,3,4,5,2,'com_content.article.1','Keep me','{\"core.edit\":{\"2\":false}}')")->execute();
        $this->db->setQuery("INSERT INTO #__jem_categories
            (id,parent_id,lft,rgt,level,catname,alias,title,published,access)
            VALUES (1,0,0,1,0,'root','root','root',1,1)")->execute();
        require_once JEM_TEST_ROOT . '/admin/tables/category.php';
        require_once JEM_TEST_ROOT . '/admin/models/import.php';
        Table::addIncludePath(JEM_TEST_ROOT . '/admin/tables');
        $this->model = (new ReflectionClass(JemModelImport::class))->newInstanceWithoutConstructor();
        $this->model->setDatabase($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->server && preg_match('/^jem_categorytest_[a-f0-9]{16}$/D', $this->database)) {
            if ($this->db) {
                $this->db->disconnect();
            }
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
    }

    private function import(array $records, bool $replace = true): array
    {
        $fields = array_keys($records[0]);
        $rows = array_map('array_values', $records);
        ob_start();
        try {
            return $this->model->categoriesimport($fields, $rows, $replace);
        } finally {
            ob_end_clean();
        }
    }

    private function record(string $name, int $id = 0, int $parent = 1): array
    {
        return ['id' => $id, 'catname' => $name, 'title' => $name, 'alias' => strtolower($name),
            'parent_id' => $parent, 'published' => 1, 'access' => 1];
    }

    private function rows(string $table): array
    {
        return $this->db->setQuery('SELECT * FROM #__' . $table . ' ORDER BY id')->loadAssocList('id');
    }


    private function assertTrees(): void
    {
        foreach (['jem_categories', 'assets'] as $name) {
            $rows = $this->rows($name);
            $coordinates = [];
            foreach ($rows as $id => $row) {
                $left = (int) $row['lft'];
                $right = (int) $row['rgt'];
                self::assertLessThan($right, $left);
                $coordinates[] = $left;
                $coordinates[] = $right;
                if ((int) $row['parent_id'] > 0) {
                    $parent = $rows[(int) $row['parent_id']];
                    self::assertGreaterThan((int) $parent['lft'], $left);
                    self::assertLessThan((int) $parent['rgt'], $right);
                    self::assertSame((int) $parent['level'] + 1, (int) $row['level']);
                }
            }
            self::assertCount(count($coordinates), array_unique($coordinates));
        }
        $assets = $this->rows('assets');
        foreach ($this->rows('jem_categories') as $id => $row) {
            if ($id === 1) {
                continue;
            }
            self::assertArrayHasKey((int) $row['asset_id'], $assets);
            self::assertSame('com_jem.category.' . $id, $assets[(int) $row['asset_id']]['name']);
        }
    }

    public function testChildBeforeParentKeepsExplicitIdsAndCanDeleteWholeSubtree(): void
    {
        $result = $this->import([$this->record('Child', 301, 300), $this->record('Parent', 300)]);
        self::assertSame(2, $result['added'], json_encode($result));
        self::assertSame(0, $result['error']);
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        self::assertSame('parent/child', $categories[301]['path']);
        self::assertSame((int) $categories[300]['asset_id'], (int) $assets[$categories[301]['asset_id']]['parent_id']);
        $this->assertTrees();
        $table = new JemTableCategory($this->db);
        self::assertTrue($table->load(300));
        self::assertTrue($table->publish([300], -2, 99));
        self::assertTrue($table->delete(300, true));
        self::assertCount(1, $this->rows('jem_categories'));
        self::assertCount(4, $this->rows('assets'));
    }

    public function testUpdatesPreserveRulesAssetIdsAndMoveExistingDescendants(): void
    {
        self::assertSame(3, $this->import([
            $this->record('Parent', 300), $this->record('Child', 301, 300), $this->record('Destination', 302),
        ])['added']);
        $categories = $this->rows('jem_categories');
        $assetId = (int) $categories[300]['asset_id'];
        $rules = '{"core.edit":{"2":false},"core.delete":{"8":true}}';
        $this->db->setQuery('UPDATE #__assets SET rules=' . $this->db->quote($rules) . ' WHERE id=' . $assetId)->execute();
        $foreign = $this->rows('assets')[4];
        $updated = $this->record('Moved', 300, 302) + ['asset_id' => 4];
        $result = $this->import([$updated]);
        self::assertSame(1, $result['updated'], json_encode($result));
        self::assertSame(0, $result['error']);
        $after = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        self::assertSame($assetId, (int) $after[300]['asset_id']);
        self::assertSame($categories[301]['asset_id'], $after[301]['asset_id']);
        self::assertSame($rules, $assets[$assetId]['rules']);
        self::assertSame('destination/moved/child', $after[301]['path']);
        foreach (['id', 'name', 'title', 'parent_id', 'rules'] as $key) {
            self::assertSame($foreign[$key], $assets[4][$key]);
        }
        $this->assertTrees();
    }

    public function testForeignAssetIdIsNotImportedForNewCategories(): void
    {
        $before = $this->rows('assets')[4];
        $result = $this->import([$this->record('New', 500) + ['asset_id' => 4]]);
        self::assertSame(1, $result['added']);
        self::assertNotSame(4, (int) $this->rows('jem_categories')[500]['asset_id']);
        $after = $this->rows('assets')[4];
        foreach (['id', 'name', 'title', 'parent_id', 'rules'] as $key) {
            self::assertSame($before[$key], $after[$key]);
        }
        $this->assertTrees();
    }

    public function testMissingParentsAndCyclesRollBackTheFile(): void
    {
        foreach ([
            [$this->record('Orphan', 50, 999)],
            [$this->record('Self', 50, 50)],
            [$this->record('CycleA', 50, 51), $this->record('CycleB', 51, 50)],
        ] as $records) {
            $categories = $this->rows('jem_categories');
            $assets = $this->rows('assets');
            $result = $this->import($records);
            self::assertGreaterThan(0, $result['error']);
            self::assertSame(0, $result['added']);
            self::assertNotEmpty($result['error_details']);
            self::assertSame($categories, $this->rows('jem_categories'));
            self::assertSame($assets, $this->rows('assets'));
        }
    }

    public function testMissingUnimportedParentAssetIsReportedWithoutRepairingIt(): void
    {
        $this->db->setQuery("INSERT INTO #__jem_categories
            (id,parent_id,catname,title,alias) VALUES(100,1,'Legacy','Legacy','legacy')")->execute();
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        $result = $this->import([$this->record('Child', 101, 100)]);
        self::assertSame(1, $result['error']);
        self::assertStringContainsString('Missing parent asset', $result['error_details'][0]['reason']);
        self::assertSame($categories, $this->rows('jem_categories'));
        self::assertSame($assets, $this->rows('assets'));
    }

    public function testTreeRebuildSqlFailureRollsBackRows(): void
    {
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        $this->db->getConnection()->query("CREATE TRIGGER fail_rebuild BEFORE UPDATE ON t_jem_categories FOR EACH ROW
            BEGIN IF NEW.lft <> OLD.lft THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected rebuild failure'; END IF; END");
        $result = $this->import([$this->record('First', 50), $this->record('Second', 51)]);
        self::assertSame(2, $result['error']);
        self::assertSame(0, $result['added']);
        self::assertStringContainsString('Injected rebuild failure', $result['error_details'][0]['reason']);
        self::assertSame($categories, $this->rows('jem_categories'));
        self::assertSame($assets, $this->rows('assets'));
    }

    public function testLaterAssetFailureAlsoRollsBackEarlierAssetInserts(): void
    {
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        $this->db->getConnection()->query("CREATE TRIGGER fail_asset BEFORE INSERT ON t_assets FOR EACH ROW
            BEGIN IF NEW.name='com_jem.category.51' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected asset failure'; END IF; END");
        $result = $this->import([$this->record('First', 50), $this->record('Second', 51)]);
        self::assertSame(2, $result['error']);
        self::assertSame(0, $result['added']);
        self::assertSame($categories, $this->rows('jem_categories'));
        self::assertSame($assets, $this->rows('assets'), 'No implicit commit from Joomla asset table locking');
    }

    public function testLinkFailureRollsBackRowsAssetsAndCoordinates(): void
    {
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        $this->db->getConnection()->query("CREATE TRIGGER fail_link BEFORE UPDATE ON t_jem_categories FOR EACH ROW
            BEGIN IF NEW.id=51 AND NEW.asset_id > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected link failure'; END IF; END");
        $result = $this->import([$this->record('First', 50), $this->record('Second', 51)]);
        self::assertSame(2, $result['error']);
        self::assertSame($categories, $this->rows('jem_categories'));
        self::assertSame($assets, $this->rows('assets'));
    }

    public function testNonTransactionalTablesAreRejectedBeforeWriting(): void
    {
        $this->db->setQuery('ALTER TABLE #__jem_categories ENGINE=MyISAM')->execute();
        $categories = $this->rows('jem_categories');
        $result = $this->import([$this->record('Unsafe', 50)]);
        self::assertSame(1, $result['error']);
        self::assertStringContainsString('InnoDB', $result['error_details'][0]['reason']);
        self::assertSame($categories, $this->rows('jem_categories'));
    }

    public function testRootRowIsIgnoredAndUnchanged(): void
    {
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        $result = $this->import([$this->record('root', 1)]);
        self::assertSame(1, $result['ignored']);
        self::assertSame(0, $result['error']);
        self::assertSame($categories, $this->rows('jem_categories'));
        self::assertSame($assets, $this->rows('assets'));
    }

    public function testFailedUpdateRestoresExistingMetadataAndRules(): void
    {
        self::assertSame(1, $this->import([$this->record('Original', 50)])['added']);
        $categories = $this->rows('jem_categories');
        $assets = $this->rows('assets');
        $this->db->getConnection()->query("CREATE TRIGGER fail_updated_asset BEFORE UPDATE ON t_assets FOR EACH ROW
            BEGIN IF NEW.name='com_jem.category.50' AND NEW.title='Changed'
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected update failure'; END IF; END");
        $result = $this->import([$this->record('Changed', 50)]);
        self::assertSame(1, $result['error']);
        self::assertSame(0, $result['updated']);
        self::assertSame($categories, $this->rows('jem_categories'));
        self::assertSame($assets, $this->rows('assets'));
    }

    public function testMultipleAutomaticIdsDoNotReuseThePreviousCategoryAsset(): void
    {
        $first = $this->record('First');
        $second = $this->record('Second');
        unset($first['id'], $second['id']);
        $result = $this->import([$first, $second], false);
        self::assertSame(2, $result['added']);
        self::assertSame(0, $result['error']);
        $this->assertTrees();
        $categories = array_values(array_filter($this->rows('jem_categories'), static fn ($row) => (int) $row['id'] !== 1));
        self::assertNotSame($categories[0]['asset_id'], $categories[1]['asset_id']);
    }

    public function testControllerKeepsCategoryTransactionSeparateAndDisplaysDeleteFailure(): void
    {
        $import = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        self::assertMatchesRegularExpression(
            '/if \(\$type !== \'categories\'\) \{\s*\$db->transactionStart\(\);\s*\$transactionStarted = true;/',
            $import
        );
        self::assertMatchesRegularExpression('/if \(\$transactionStarted\) \{\s*\$db->transactionCommit\(\);/', $import);
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/categories.php');
        self::assertMatchesRegularExpression('/if \(\$msg === false\) \{.*?COM_JEM_CATEGORIES_DELETE_FAILED.*?\'error\'.*?return;/s', $controller);
    }


    public function testFalseRebuildIsNotIgnored(): void
    {
        $table = $this->getMockBuilder(JemTableCategory::class)->disableOriginalConstructor()
            ->onlyMethods(['getRootId', 'rebuild'])->getMock();
        $table->method('getRootId')->willReturn(1);
        $table->method('rebuild')->willReturn(false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be rebuilt');
        JemCategoryImportHelper::complete($table, $this->db, [50]);
    }


    public function testImportedCategoryCanBeTrashedAndDeletedWithoutManualSave(): void
    {
        $record = $this->record('Imported');
        unset($record['id']);
        $result = $this->import([$record], false);
        self::assertSame(1, $result['added']);
        self::assertSame(0, $result['error']);
        $rows = $this->rows('jem_categories');
        $id = (int) array_key_last($rows);
        $table = new JemTableCategory($this->db);
        self::assertTrue($table->load($id));
        self::assertTrue($table->publish([$id], -2, 99));
        self::assertTrue($table->delete($id, true), 'Imported category has no deletable asset: ' . (string) $table->getError());
        self::assertCount(1, $this->rows('jem_categories'));
        self::assertCount(4, $this->rows('assets'));
    }
}

