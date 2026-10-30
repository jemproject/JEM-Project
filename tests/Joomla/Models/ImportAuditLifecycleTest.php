<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real tables, model, SQL and log writer; never modifies an installed Joomla database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ImportAuditLifecycleTest extends TestCase
{
    private ?mysqli $server = null;
    private string $database = '';
    private string $logDirectory = '';
    private $db;
    private JemModelImport $model;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_IMPORT_AUDIT') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Opt in with JEM_TEST_IMPORT_AUDIT=1 and JEM_TEST_JOOMLA_ROOT; disposable database only.');
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
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        define('JPATH_ROOT', $root);
        define('JPATH_BASE', $root);
        $this->logDirectory = sys_get_temp_dir() . '/jem_import_audit_' . bin2hex(random_bytes(8));
        mkdir($this->logDirectory);
        Factory::$language = (new ReflectionClass(Joomla\CMS\Language\Language::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Factory::$language, 'metadata'))->setValue(Factory::$language, ['tag' => 'en-GB']);
        (new ReflectionProperty(Factory::$language, 'strings'))->setValue(Factory::$language, []);
        $actor = $this->createMock(Joomla\CMS\User\User::class);
        $actor->id = 99;
        $actor->guest = 0;
        $actor->method('authorise')->willReturn(true);
        Factory::$application = new class ($actor, $this->logDirectory) {
            private $dispatcher;
            public function __construct(private $actor, private string $logPath) {}
            public function getIdentity() { return $this->actor; }
            public function getLanguage() { return Factory::$language; }
            public function getDispatcher() { return $this->dispatcher ??= new Joomla\Event\Dispatcher(); }
            public function get($key, $default = null)
            {
                return ['log_path' => $this->logPath, 'offset' => 'UTC', 'unicodeslugs' => false][$key] ?? $default;
            }
        };
        $options = ['host' => '127.0.0.1', 'user' => getenv('JEM_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('JEM_TEST_MYSQL_PASSWORD') ?: '', 'prefix' => 't_'];
        $this->server = new mysqli($options['host'], $options['user'], $options['password']);
        $this->database = 'jem_importaudit_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new Joomla\Database\Mysqli\MysqliDriver($options + ['database' => $this->database]);
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
        $userFactory = $this->createMock(Joomla\CMS\User\UserFactoryInterface::class);
        $author = clone $actor;
        $author->id = 42;
        $userFactory->method('loadUserById')->willReturn($author);
        $container->set(Joomla\CMS\User\UserFactoryInterface::class, $userFactory);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        $this->db->setQuery("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'")->execute();
        foreach (Joomla\Database\DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql')) as $sql) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS .#__jem_(types|attachments)./', $sql)) {
                $this->db->setQuery($sql)->execute();
            }
        }
        require_once JEM_TEST_ROOT . '/admin/models/import.php';
        require_once JEM_TEST_ROOT . '/admin/controllers/import.php';
        Table::addIncludePath(JEM_TEST_ROOT . '/admin/tables');
        $this->model = (new ReflectionClass(JemModelImport::class))->newInstanceWithoutConstructor();
        $this->model->setDatabase($this->db);
        $this->model->setImportAuthor('types', 42);
    }

    protected function tearDown(): void
    {
        if ($this->server && preg_match('/^jem_importaudit_[a-f0-9]{16}$/D', $this->database)) {
            $this->db?->disconnect();
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
        if (preg_match('/jem_import_audit_[a-f0-9]{16}$/D', $this->logDirectory) && is_dir($this->logDirectory)) {
            foreach (glob($this->logDirectory . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->logDirectory);
        }
    }

    private function import(string $kind, array $records): array
    {
        $fields = array_keys($records[0]);
        $rows = [];
        foreach ($records as $index => $record) {
            $row = array_values($record);
            $row['_jem_source_line'] = $index + 2;
            $rows[] = $row;
        }
        ob_start();
        try {
            return $this->model->{$kind . 'import'}($fields, $rows, true);
        } finally {
            ob_end_clean();
        }
    }

    private function row(string $table, int $id): object
    {
        return $this->db->setQuery('SELECT * FROM #__jem_' . $table . ' WHERE id=' . $id)->loadObject();
    }

    private function writeJournal(bool $rollback, ?Throwable $failure = null): string
    {
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod($controller, 'writeImportRows'))->invoke($controller, 'jem_types', $this->model, $rollback, $failure);
        return file_get_contents($this->logDirectory . '/jem-import-types.log.php');
    }

    public function testTypeImportAssignsLocalAuthorAndLogsEachLocalId(): void
    {
        $record = ['id' => 71, 'name' => 'Imported', 'created' => '2012-03-04 05:06:07', 'created_by' => 777, 'modified_by' => 888];
        $result = $this->import('types', [$record]);
        self::assertSame(1, $result['added']);
        $stored = $this->row('types', 71);
        self::assertSame(42, (int) $stored->created_by);
        self::assertSame('2012-03-04 05:06:07', $stored->created);
        self::assertNull($stored->modified);
        self::assertSame(0, (int) $stored->modified_by);
        $log = $this->writeJournal(false);
        self::assertStringContainsString('user_id=99', $log);
        self::assertStringContainsString("<?php die('Forbidden.'); ?>", $log);
        self::assertStringContainsString('row=2 source_id=71 id=71 outcome=ADDED', $log);
        self::assertMatchesRegularExpression('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $log);

        $record['created_by'] = 999;
        $record['created'] = '2020-01-01 00:00:00';
        self::assertSame(1, $this->import('types', [$record])['updated']);
        $updated = $this->row('types', 71);
        self::assertSame($stored->created, $updated->created);
        self::assertSame(42, (int) $updated->created_by);
        self::assertSame(99, (int) $updated->modified_by);
        self::assertNotNull($updated->modified);
        $this->db->setQuery('UPDATE #__jem_types SET created_by=0 WHERE id=71')->execute();
        $this->import('types', [$record]);
        self::assertSame(0, (int) $this->row('types', 71)->created_by);
    }

    public function testAttachmentAuditOnInsertUpdateAndUnchangedManualSave(): void
    {
        $record = ['id' => 0, 'object' => 'event21', 'file' => 'example.pdf', 'name' => 'Example',
            'created_by' => 888, 'created' => '2012-03-04 05:06:07'];
        self::assertSame(1, $this->import('attachments', [$record])['added']);
        $stored = $this->row('attachments', 1);
        self::assertSame(42, (int) $stored->created_by);
        self::assertSame($record['created'], $stored->created);
        self::assertNull($stored->modified);
        $record['id'] = 1;
        $record['name'] = 'Changed';
        self::assertSame(1, $this->import('attachments', [$record])['updated']);
        $changed = $this->row('attachments', 1);
        self::assertSame(99, (int) $changed->modified_by);
        self::assertNotNull($changed->modified);
        $table = Table::getInstance('jem_attachments', '');
        self::assertTrue($table->load(1));
        $table->created_by = 1234;
        $table->modified_by = 1234;
        self::assertTrue($table->store());
        $unchanged = $this->row('attachments', 1);
        self::assertSame($changed->modified, $unchanged->modified);
        self::assertSame(99, (int) $unchanged->modified_by);
        self::assertSame(42, (int) $unchanged->created_by);
        $new = Table::getInstance('jem_attachments', '');
        $new->bind(['object' => 'event21', 'file' => 'new.pdf', 'created' => '2000-01-01 00:00:00',
            'created_by' => 1234, 'modified_by' => 1234, '_importAudit' => true]);
        self::assertTrue($new->check());
        self::assertTrue($new->store());
        $uploaded = $this->row('attachments', (int) $new->id);
        self::assertSame(99, (int) $uploaded->created_by);
        self::assertNotSame('2000-01-01 00:00:00', $uploaded->created);
        self::assertNull($uploaded->modified);
        self::assertSame(0, (int) $uploaded->modified_by);
    }

    public function testRollbackLogDoesNotClaimThatAnInsertedTypeSurvived(): void
    {
        $this->db->transactionStart();
        $result = $this->import('types', [['id' => 81, 'name' => 'Valid'], ['id' => 82, 'name' => '']]);
        self::assertSame(1, $result['error']);
        $this->db->transactionRollback();
        self::assertSame('0', (string) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_types')->loadResult());
        $log = $this->writeJournal(true);
        self::assertStringContainsString('outcome=ROLLED_BACK_ADDED', $log);
        self::assertStringContainsString('row=3 source_id=82 id=- outcome=ERROR', $log);
        self::assertStringNotContainsString('outcome=ADDED', $log);
    }

    public function testSqlErrorAndAllValidationErrorsReachTheLog(): void
    {
        $records = [];
        for ($id = 1; $id <= 5; $id++) {
            $records[] = ['id' => $id, 'name' => ''];
        }
        $result = $this->import('types', $records);
        self::assertSame(5, $result['error']);
        self::assertSame(5, substr_count($this->writeJournal(false), 'outcome=ERROR'));
        $this->db->setQuery("ALTER TABLE #__jem_types ADD CONSTRAINT reject_import CHECK (name <> 'SQL failure')")->execute();
        self::assertSame(1, $this->import('types', [['id' => 91, 'name' => 'SQL failure']])['error']);
        $log = $this->writeJournal(false);
        self::assertStringContainsString('reject_import', $log);
        self::assertStringContainsString('row=2 source_id=91', $log);
    }

    public function testAttachmentUpgradeAndSameVersionFallbackAreIdempotent(): void
    {
        require_once JEM_TEST_ROOT . '/script.php';
        $this->db->setQuery("INSERT INTO #__jem_attachments (object, file, created, created_by)
            VALUES ('event1', 'legacy.pdf', '2010-01-02 03:04:05', 17)")->execute();
        $this->db->setQuery('ALTER TABLE #__jem_attachments DROP COLUMN modified, DROP COLUMN modified_by')->execute();
        foreach (Joomla\Database\DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.2.sql')) as $sql) {
            if (str_contains($sql, 'ALTER TABLE `#__jem_attachments`')) {
                $this->db->setQuery($sql)->execute();
            }
        }
        $columns = $this->db->getTableColumns('#__jem_attachments');
        self::assertArrayHasKey('modified', $columns);
        self::assertArrayHasKey('modified_by', $columns);
        $legacy = $this->row('attachments', 1);
        self::assertSame('2010-01-02 03:04:05', $legacy->created);
        self::assertSame(17, (int) $legacy->created_by);
        self::assertNull($legacy->modified);
        self::assertSame(0, (int) $legacy->modified_by);
        $this->db->setQuery('ALTER TABLE #__jem_attachments DROP COLUMN modified, DROP COLUMN modified_by')->execute();
        $installer = new com_jemInstallerScript();
        $repair = new ReflectionMethod($installer, 'repair501SchemaFallback');
        $repair->invoke($installer);
        $repair->invoke($installer);
        $columns = $this->db->getTableColumns('#__jem_attachments');
        self::assertArrayHasKey('modified', $columns);
        self::assertArrayHasKey('modified_by', $columns);
    }

    public function testInvalidExternalEventsAndSkippedIcsDuplicatesAreLogged(): void
    {
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        $normalise = new ReflectionMethod($controller, 'normaliseExternalIcsEvent');
        $options = ['mode' => 'standard', 'published' => 1, 'publish_up' => '', 'type_id' => 0,
            'locid' => 0, 'language' => '*', 'catid' => 1];
        $event = [
            'DTSTART' => [['value' => '20261030T100000', 'raw' => '20261030T100000', 'params' => []]],
            'UID' => [['value' => 'audit-example', 'raw' => 'audit-example', 'params' => []]],
        ];
        $invalid = $normalise->invoke($controller, $event, $options, 7);
        self::assertFalse($invalid['valid']);
        self::assertStringContainsString('row=7 phase=validation', file_get_contents($this->logDirectory . '/jem-import-external-ics.log.php'));
        $event['SUMMARY'] = [['value' => 'Example', 'raw' => 'Example', 'params' => []]];
        $valid = $normalise->invoke($controller, $event, $options, 8);
        self::assertTrue($valid['valid']);
        self::assertSame(8, $valid['record']['_jem_source_line']);
        $known = [];
        $skipped = 0;
        $method = new ReflectionMethod($controller, 'filterImportedIcsRecords');
        $arguments = [[$valid['record'], $valid['record']], &$known, &$skipped];
        self::assertCount(1, $method->invokeArgs($controller, $arguments));
        self::assertSame(1, $skipped);
        self::assertStringContainsString('row=8 outcome=SKIPPED', file_get_contents($this->logDirectory . '/jem-import-external-ics.log.php'));
    }
}
