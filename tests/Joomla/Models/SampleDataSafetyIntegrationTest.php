<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Opt-in real MariaDB + sample SQL/media. Never connects to an installed site's database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SampleDataSafetyIntegrationTest extends TestCase
{
    private ?mysqli $server = null;
    private string $database = '';
    private string $directory = '';
    private $db;
    private JemModelSampledata $model;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_SAMPLE_MYSQL') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Opt in with JEM_TEST_SAMPLE_MYSQL=1 and JEM_TEST_JOOMLA_ROOT; creates disposable databases only.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function ($class) use ($root) {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        $this->directory = str_replace('\\', '/', sys_get_temp_dir()) . '/jem-sample-integration-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        define('JPATH_ROOT', $this->directory);
        define('JPATH_SITE', $this->directory);
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        foreach (['tmp', 'images/jem', 'private', 'components/com_jem/classes'] as $folder) {
            mkdir($this->directory . '/' . $folder, 0777, true);
        }
        foreach (['Zebra_Image.php', 'imageresourcepolicy.class.php', 'imageprofilepolicy.class.php', 'imagefolderpolicy.class.php',
            'eventimagepath.class.php', 'venueimagepath.class.php', 'categoryimagepath.class.php'] as $file) {
            if (is_file(JEM_TEST_ROOT . '/site/classes/' . $file)) {
                copy(JEM_TEST_ROOT . '/site/classes/' . $file, $this->directory . '/components/com_jem/classes/' . $file);
            }
        }
        require_once JEM_TEST_ROOT . '/tests/Support/sampledata_integration_environment.php';
        // JemConfig is supplied by the test environment; satisfy the model's require_once.
        file_put_contents($this->directory . '/components/com_jem/classes/config.class.php', '<?php');
        require_once JEM_TEST_ROOT . '/admin/models/sampledata.php';
        require_once JEM_TEST_ROOT . '/site/classes/image.class.php';
        Factory::$application = new SampleDataTestApplication();
        if (property_exists(Factory::class, 'language')) {
            Factory::$language = Factory::$application->getLanguage();
        }
        JemHelper::$settings = (object) ['attachments_path' => 'private/attachments', 'gddisabled' => 1, 'imagewidth' => 100, 'imagehight' => 100];

        $options = ['driver' => 'mysqli', 'host' => '127.0.0.1', 'user' => getenv('JEM_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('JEM_TEST_MYSQL_PASSWORD') ?: '', 'prefix' => 't_'];
        $this->server = new mysqli($options['host'], $options['user'], $options['password']);
        $this->database = 'jem_sampletest_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4');
        $options['database'] = $this->database;
        $this->db = new \Joomla\Database\Mysqli\MysqliDriver($options);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        $this->db->setQuery("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'")->execute();
        foreach (\Joomla\Database\DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql')) as $sql) {
            if (trim($sql) !== '') {
                $this->db->setQuery($sql)->execute();
            }
        }
        $this->model = (new ReflectionClass(JemModelSampledata::class))->newInstanceWithoutConstructor();
        $this->model->setDatabase($this->db);
        (new ReflectionProperty(JemModelSampledata::class, 'sampleDataDir'))->setValue($this->model, JEM_TEST_ROOT . '/admin/assets/');
    }

    protected function tearDown(): void
    {
        if ($this->server && preg_match('/^jem_sampletest_[a-f0-9]{16}$/D', $this->database)) {
            if ($this->db) {
                $this->db->disconnect();
            }
            $this->server->query('DROP DATABASE `' . $this->database . '`');
            $this->server->close();
        }
        if ($this->directory && is_dir($this->directory) && str_contains($this->directory, '/jem-sample-integration-')) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->directory);
        }
    }

    private function scalar(string $sql)
    {
        return $this->db->setQuery($sql)->loadResult();
    }

    private function existingSettings(): string
    {
        $value = '{"event":{"custom1":{"type":"number","labels":{"en-GB":"Ticket price"}}}}';
        $this->db->setQuery("INSERT INTO #__jem_types (name,alias,entity,created_by,description) VALUES ('My concert','concert',1,62,'Keep me')")->execute();
        $this->db->setQuery("INSERT INTO #__jem_config (keyname,value) VALUES ('custom_fields_config'," . $this->db->quote($value) . ') ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute();
        return $value;
    }

    public function testPreflightIsReadOnlyAndFailedLoadRestoresCategoryAndSettings(): void
    {
        $config = $this->existingSettings();
        $categories = $this->scalar('SELECT COUNT(*) FROM #__jem_categories');
        $types = $this->scalar('SELECT COUNT(*) FROM #__jem_types');
        self::assertFalse((new ReflectionMethod(JemModelSampledata::class, 'checkForJemData'))->invoke($this->model));
        self::assertEquals($categories, $this->scalar('SELECT COUNT(*) FROM #__jem_categories'));
        // Images (and generated thumbnails in 5.1) are already copied when attachments fail.
        file_put_contents($this->directory . '/private/attachments', 'blocking file');
        self::assertFalse($this->model->loadData());
        self::assertEquals($categories, $this->scalar('SELECT COUNT(*) FROM #__jem_categories'));
        self::assertEquals(0, $this->scalar('SELECT COUNT(*) FROM #__jem_events'));
        self::assertEquals($types, $this->scalar('SELECT COUNT(*) FROM #__jem_types'));
        self::assertSame($config, $this->scalar("SELECT value FROM #__jem_config WHERE keyname='custom_fields_config'"));
        self::assertSame([], array_values(array_diff(scandir($this->directory . '/images/jem'), ['.', '..'])));
        self::assertSame('blocking file', file_get_contents($this->directory . '/private/attachments'));
        self::assertSame([], glob($this->directory . '/tmp/sample_*'));
        self::assertStringContainsString('FAILED_REASON', $this->model->getError());
        // Prove that this was a media-stage failure, not an earlier SQL/config error.
        self::assertStringContainsString('blocks the sample media folder', implode('\n', JemHelper::$logs));
        unlink($this->directory . '/private/attachments');
        self::assertTrue($this->model->loadData(), implode('\n', JemHelper::$logs));
    }

    public function testSuccessfulImportReusesTypesPreservesSettingsAndRefusesSecondLoad(): void
    {
        $config = $this->existingSettings();
        $type = (int) $this->scalar("SELECT id FROM #__jem_types WHERE alias='concert'");
        self::assertTrue($this->model->loadData(), implode('\n', JemHelper::$logs));
        self::assertSame($config, $this->scalar("SELECT value FROM #__jem_config WHERE keyname='custom_fields_config'"));
        self::assertEquals(1, $this->scalar("SELECT COUNT(*) FROM #__jem_types WHERE alias='concert' AND entity=1"));
        self::assertEquals(62, $this->scalar('SELECT created_by FROM #__jem_types WHERE id=' . $type));
        self::assertSame('Keep me', $this->scalar('SELECT description FROM #__jem_types WHERE id=' . $type));
        self::assertEquals($type, $this->scalar('SELECT type_id FROM #__jem_events WHERE id=2'));
        self::assertEquals(0, $this->scalar("SELECT COUNT(*) FROM #__jem_events WHERE custom1 <> '' OR custom10 <> ''"));
        self::assertEquals(0, $this->scalar("SELECT COUNT(*) FROM #__jem_venues WHERE custom1 <> '' OR custom10 <> ''"));
        self::assertContains(['notice', 'COM_JEM_SAMPLEDATA_FIELD_CONFIG_KEPT'], Factory::$application->messages);
        $count = $this->scalar('SELECT COUNT(*) FROM #__jem_events');
        self::assertFalse($this->model->loadData());
        self::assertEquals($count, $this->scalar('SELECT COUNT(*) FROM #__jem_events'));
        self::assertSame('COM_JEM_SAMPLEDATA_DATA_ALREADY_INSTALLED', $this->model->getError());
    }

    public function testFreshImportStillProvidesDemoFields(): void
    {
        JemHelper::$settings->gddisabled = 0;
        self::assertTrue($this->model->loadData(), implode('\n', JemHelper::$logs));
        self::assertSame('All ages', $this->scalar('SELECT custom1 FROM #__jem_events WHERE id=1'));
        self::assertStringContainsString('Audience', $this->scalar("SELECT value FROM #__jem_config WHERE keyname='custom_fields_config'"));
        self::assertEquals(0, $this->scalar('SELECT COUNT(*) FROM #__jem_types WHERE created_by=62'));
        self::assertSame([], glob($this->directory . '/tmp/sample_*'));
    }

    public function testConflictingMediaIsPreservedAndReported(): void
    {
        $zip = new ZipArchive();
        $zip->open(JEM_TEST_ROOT . '/admin/assets/sampledata.zip');
        $file = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^event[^\/]*\.(?:webp|jpg|png)$/', $name)) {
                $file = $name;
                break;
            }
        }
        $zip->close();
        self::assertNotNull($file);
        mkdir($this->directory . '/images/jem/events');
        $target = $this->directory . '/images/jem/events/' . $file;
        file_put_contents($target, 'Existing private image');
        self::assertFalse($this->model->loadData());
        self::assertSame('Existing private image', file_get_contents($target));
        self::assertEquals(0, $this->scalar('SELECT COUNT(*) FROM #__jem_events'));
        self::assertStringContainsString('conflicts', implode('\n', JemHelper::$logs));
    }
}
