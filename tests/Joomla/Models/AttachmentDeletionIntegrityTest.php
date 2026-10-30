<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real removal/SQL/file operations in disposable fixtures, never an installed site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttachmentDeletionIntegrityTest extends TestCase
{
    private string $directory = '';
    private array $fixtureFiles = [];
    private array $fixtureDirectories = [];
    private ?mysqli $server = null;
    private string $database = '';
    private $db;
    private AttachmentIntegrityModel $model;
    private AttachmentIntegrityDispatcher $dispatcher;

    /** @return void Bootstrap Joomla libraries and isolated attachment storage. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT; installed sites are not modified.');
        }
        $mysql = getenv('JEM_TEST_ATTACHMENT_DB') === 'mysqli';
        if (!$mysql && !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('PDO SQLite is required for the default in-memory fixture.');
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
        $this->directory = sys_get_temp_dir() . '/jem-attachment-delete-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        define('JPATH_SITE', $this->directory);
        define('JPATH_ROOT', $this->directory);
        define('JPATH_BASE', $this->directory);
        $this->fixtureDirectories = ['/components', '/components/com_jem', '/components/com_jem/classes', '/files', '/files/event1'];
        foreach ($this->fixtureDirectories as $directory) {
            mkdir($this->directory . $directory);
        }
        foreach (['/components/com_jem/factory.php', '/components/com_jem/classes/log.class.php',
            '/components/com_jem/classes/attachmentdeletion.class.php'] as $file) {
            $this->fixtureFiles[] = $file;
            file_put_contents($this->directory . $file, '<?php // Bootstrapped from the checkout by this test.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/attachmentdeletion.class.php';
        require_once JEM_TEST_ROOT . '/admin/models/attachment.php';
        require_once JEM_TEST_ROOT . '/admin/tables/jem_attachments.php';
        require_once JEM_TEST_ROOT . '/site/classes/attachment.class.php';
        require_once JEM_TEST_ROOT . '/tests/Support/attachment_deletion_environment.php';

        if ($mysql) {
            $options = ['host' => '127.0.0.1', 'user' => getenv('JEM_TEST_MYSQL_USER') ?: 'root',
                'password' => getenv('JEM_TEST_MYSQL_PASSWORD') ?: '', 'prefix' => 't_'];
            $this->server = new mysqli($options['host'], $options['user'], $options['password']);
            $this->database = 'jem_attachment_test_' . bin2hex(random_bytes(8));
            $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
            $this->db = new AttachmentIntegrityMysqli($options + ['database' => $this->database]);
        } else {
            $this->db = new AttachmentIntegritySqlite(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        }
        $this->dispatcher = new AttachmentIntegrityDispatcher();
        Factory::$application = new class ($this->dispatcher) {
            public array $messages = [];
            private ?Joomla\Registry\Registry $session = null;
            public function __construct(private $dispatcher)
            {
            }
            public function get($key, $default = null)
            {
                return $key === 'access' ? 1 : $default;
            }
            public function getConfig()
            {
                return new Joomla\Registry\Registry();
            }
            public function getDispatcher()
            {
                return $this->dispatcher;
            }
            public function isClient($client)
            {
                return $client === 'administrator';
            }
            public function enqueueMessage($message, $type = 'message')
            {
                $this->messages[] = [$message, $type];
            }

            /** @return array Messages awaiting display. */
            public function getMessageQueue()
            {
                return $this->messages;
            }

            /** @return Joomla\Registry\Registry Isolated session-like storage. */
            public function getSession()
            {
                return $this->session ??= new Joomla\Registry\Registry();
            }
        };
        $container = new Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->set(LanguageFactoryInterface::class, new LanguageFactory());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        (new ReflectionProperty(PluginHelper::class, 'plugins'))->setValue(null, []);
        $engine = $mysql ? ' ENGINE=InnoDB' : '';
        $this->db->setQuery('CREATE TABLE t_jem_attachments (id INTEGER PRIMARY KEY, object VARCHAR(255), file VARCHAR(255),
            access INTEGER, created_by INTEGER)' . $engine)->execute();
        $this->db->setQuery('CREATE TABLE t_jem_events (id INTEGER PRIMARY KEY, access INTEGER, created_by INTEGER)' . $engine)->execute();
        $this->db->setQuery('INSERT INTO t_jem_events VALUES (1,1,7)')->execute();
        foreach ([1, 2] as $id) {
            $file = '/files/event1/test' . $id . '.txt';
            $this->fixtureFiles[] = $file;
            file_put_contents($this->directory . $file, 'synthetic attachment ' . $id);
            $this->db->setQuery("INSERT INTO t_jem_attachments VALUES ($id,'event1','test$id.txt',1,7)")->execute();
        }
        $this->model = new AttachmentIntegrityModel($this->db, $this->dispatcher);
    }

    /** @return void Remove only the unique fixture database and known synthetic files. */
    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->disconnect();
        }
        if ($this->server && preg_match('/^jem_attachment_test_[a-f0-9]{16}$/D', $this->database)) {
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
        if ($this->directory !== '') {
            foreach ($this->fixtureFiles as $file) {
                if (is_file($this->directory . $file)) {
                    unlink($this->directory . $file);
                }
            }
            foreach (array_reverse($this->fixtureDirectories) as $directory) {
                rmdir($this->directory . $directory);
            }
            rmdir($this->directory);
        }
    }

    /** @return iterable<string, array{string}> Both production entry points. */
    public static function entryPoints(): iterable
    {
        yield 'bulk admin' => ['admin'];
        yield 'shared form remover' => ['form'];
    }

    /**
     * @param string $entry Entry point.
     * @return void
     */
    #[DataProvider('entryPoints')]
    public function testNormalDeletionPreservesUnselectedContent(string $entry): void
    {
        self::assertTrue($this->remove($entry));
        self::assertSame([2], $this->ids());
        self::assertFileDoesNotExist($this->path(1));
        self::assertSame('synthetic attachment 2', file_get_contents($this->path(2)));
        if ($entry === 'admin') {
            self::assertSame([1], $this->model->getDeleteFileResult()->deleted);
            self::assertSame(1, $this->model->getDeleteFileResult()->files);
        } else {
            self::assertSame(['onJemAfterAttachmentDelete'], $this->dispatcher->actionLogs);
        }
    }

    /**
     * @param string $entry Entry point.
     * @return void
     */
    #[DataProvider('entryPoints')]
    public function testDatabaseFailureKeepsRowsAndBytes(string $entry): void
    {
        $this->failDelete(1);
        self::assertFalse($this->remove($entry));
        $this->assertIntact();
        self::assertSame([], $this->dispatcher->actionLogs);
        self::assertNotEmpty(JemHelper::$logs);
    }

    /** @return void A later SQL failure rolls back the owned batch before any cleanup. */
    public function testSecondRecordFailurePreservesEntireBatch(): void
    {
        $this->failDelete(2);
        self::assertFalse($this->model->deleteWithFiles([1, 2]));
        $this->assertIntact();
    }

    /** @return void Joomla plugin vetoes must happen before any physical deletion. */
    public function testPluginVetoOnSecondRecordRollsBackBatch(): void
    {
        $this->dispatcher->addListener('onContentBeforeDelete', static function ($event): void {
            $table = $event->getArgument('subject');
            if ((int) $table->id === 2) {
                $table->setError('synthetic veto');
                $event->addResult(false);
            }
        });
        self::assertFalse($this->model->deleteWithFiles([1, 2]));
        self::assertSame('synthetic veto', $this->model->getError());
        $this->assertIntact();
    }

    /** @return void A failing after-delete plugin also prevents physical cleanup. */
    public function testAfterDeletePluginFailurePreservesBytes(): void
    {
        $this->dispatcher->addListener('onContentAfterDelete', static function (): void {
            throw new RuntimeException('Synthetic plugin failure.');
        });
        self::assertFalse($this->model->deleteWithFiles([1]));
        $this->assertIntact();
    }

    /**
     * @param string $entry Entry point.
     * @return void
     */
    #[DataProvider('entryPoints')]
    public function testDeniedPermissionDoesNotChangeAnything(string $entry): void
    {
        JemHelperBackend::$allowed = false;
        self::assertFalse($this->remove($entry));
        $this->assertIntact();
    }

    /**
     * @param string $entry Entry point.
     * @return void
     */
    #[DataProvider('entryPoints')]
    public function testSharedPhysicalFileIsRetained(string $entry): void
    {
        $this->db->setQuery("UPDATE t_jem_attachments SET file='test1.txt' WHERE id=2")->execute();
        self::assertTrue($this->remove($entry));
        self::assertSame([2], $this->ids());
        self::assertSame('synthetic attachment 1', file_get_contents($this->path(1)));
        if ($entry === 'admin') {
            self::assertSame([1], $this->model->getDeleteFileResult()->retained);
            JemAttachmentDeletion::notifyCleanup($this->model->getDeleteFileResult());
        } else {
            self::assertSame(Factory::$application->messages, Factory::$application->getSession()->get('application.queue'));
        }
        self::assertNotEmpty(Factory::$application->messages);
    }

    /** @return void Duplicate selected IDs must not inflate confirmed counts. */
    public function testBulkDeduplicatesIdsAndDeletesAllSelectedRows(): void
    {
        self::assertTrue($this->model->deleteWithFiles([1, '1', 2, 0, -1]));
        self::assertSame([], $this->ids());
        self::assertSame([1, 2], $this->model->getDeleteFileResult()->deleted);
        self::assertSame(2, $this->model->getDeleteFileResult()->files);
    }

    /** @return void An invalid selection cannot delete any valid selected file. */
    public function testMissingRecordAbortsBeforeDeletion(): void
    {
        self::assertFalse($this->model->deleteWithFiles([1, 99]));
        $this->assertIntact();
    }

    /** @return iterable<string, array{string, string, string}> Unsafe persisted path values. */
    public static function unsafePaths(): iterable
    {
        foreach (['admin', 'form'] as $entry) {
            yield $entry . ' object' => [$entry, 'object', '../event1'];
            yield $entry . ' filename' => [$entry, 'file', '../test1.txt'];
        }
    }

    /**
     * Reject unsafe persisted paths before changing records or files.
     *
     * @param string $entry Entry point.
     * @param string $column Unsafe storage field.
     * @param string $value Invalid value.
     * @return void
     */
    #[DataProvider('unsafePaths')]
    public function testUnsafePathCannotDeleteAnything(string $entry, string $column, string $value): void
    {
        $this->db->setQuery('UPDATE t_jem_attachments SET ' . $this->db->quoteName($column)
            . ' = ' . $this->db->quote($value) . ' WHERE id=1')->execute();
        self::assertFalse($this->remove($entry));
        $this->assertIntact();
    }

    /** @return void Shared bytes are removed only once when every reference is selected. */
    public function testSelectedSharedFileReportsOnePhysicalDeletion(): void
    {
        $this->db->setQuery("UPDATE t_jem_attachments SET file='test1.txt' WHERE id=2")->execute();
        self::assertTrue($this->model->deleteWithFiles([1, 2]));
        self::assertSame([], $this->ids());
        self::assertSame([1, 2], $this->model->getDeleteFileResult()->deleted);
        self::assertSame(1, $this->model->getDeleteFileResult()->files);
        self::assertFileDoesNotExist($this->path(1));
        self::assertFileExists($this->path(2));
    }

    /** @return void Existing parent view-access checks remain independent of edit permission. */
    public function testFormRemovalCannotBypassParentAccess(): void
    {
        $this->db->setQuery('UPDATE t_jem_events SET access=2 WHERE id=1')->execute();
        self::assertFalse(JemAttachment::remove(1));
        $this->assertIntact();
    }

    /** @return void Missing files are a normal record-cleanup case, not data loss. */
    public function testAlreadyMissingFileCanBeRemovedFromInventory(): void
    {
        unlink($this->path(1));
        self::assertTrue($this->model->deleteWithFiles([1]));
        self::assertSame(0, $this->model->getDeleteFileResult()->files);
        self::assertSame([2], $this->ids());
    }

    /** @return void The separate record-only action must keep physical content. */
    public function testRecordOnlyDeletionRemainsUnchanged(): void
    {
        $ids = [1];
        self::assertTrue($this->model->delete($ids));
        self::assertSame([2], $this->ids());
        self::assertFileExists($this->path(1));
    }

    /**
     * @param string $entry Entry point.
     * @return void
     */
    #[DataProvider('entryPoints')]
    public function testOuterTransactionIsNeverCommittedOrRolledBack(string $entry): void
    {
        $this->db->transactionStart();
        $this->db->setQuery("INSERT INTO t_jem_attachments VALUES (3,'event1','marker.txt',1,7)")->execute();
        self::assertFalse($this->remove($entry));
        self::assertSame([1, 2, 3], $this->ids());
        self::assertFileExists($this->path(1));
        $this->db->transactionRollback();
        self::assertSame([1, 2], $this->ids());
    }

    /** @return iterable<string, array{string, string}> Driver failures across both entry points. */
    public static function driverFailures(): iterable
    {
        foreach (['admin', 'form'] as $entry) {
            foreach (['delete-false', 'verify-null', 'commit-before', 'commit-silent', 'commit-after'] as $fault) {
                yield $entry . ' ' . $fault => [$entry, $fault];
            }
        }
    }

    /**
     * @param string $entry Entry point.
     * @param string $fault Driver failure.
     * @return void
     */
    #[DataProvider('driverFailures')]
    public function testUnconfirmedDeletionNeverRemovesBytes(string $entry, string $fault): void
    {
        $this->db->fault = $fault;
        self::assertFalse($this->remove($entry));
        self::assertSame('synthetic attachment 1', file_get_contents($this->path(1)));
        self::assertSame('synthetic attachment 2', file_get_contents($this->path(2)));
        self::assertSame($fault === 'commit-after' ? [2] : [1, 2], $this->ids());
    }

    /** @return void Success from a partial callback is not sufficient to clean files. */
    public function testIncompleteSuccessfulCallbackIsRejected(): void
    {
        $result = JemAttachmentDeletion::execute($this->db, $this->snapshots(), function () {
            $this->db->setQuery('DELETE FROM t_jem_attachments WHERE id=1')->execute();
            return true;
        });
        self::assertFalse($result->success);
        $this->assertIntact();
    }

    /** @return void A reference query failure leaves recoverable bytes after commit. */
    public function testReferenceQueryFailureReportsPendingCleanup(): void
    {
        $this->db->fault = 'reference-error';
        self::assertTrue($this->model->deleteWithFiles([1, 2]));
        self::assertSame([], $this->ids());
        self::assertSame([1, 2], $this->model->getDeleteFileResult()->pending);
        self::assertFileExists($this->path(1));
        self::assertFileExists($this->path(2));
        JemAttachmentDeletion::notifyCleanup($this->model->getDeleteFileResult());
        self::assertNotEmpty(Factory::$application->messages);
    }

    /** @return void A null count cannot be interpreted as zero remaining references. */
    public function testUncertainReferencesPreserveFileAndAjaxSuccess(): void
    {
        $this->db->fault = 'reference-null';
        self::assertTrue(JemAttachment::remove(1));
        self::assertSame([2], $this->ids());
        self::assertFileExists($this->path(1));
        self::assertNotEmpty(Factory::$application->messages);
        self::assertSame(Factory::$application->messages, Factory::$application->getSession()->get('application.queue'));
    }

    /** @return void Filesystem errors report a partial cleanup without restoring deleted rows. */
    public function testSecondFileFailurePreservesItsBytesAndReportsActualCounts(): void
    {
        stream_wrapper_register('jemattachmentfail', AttachmentIntegrityFailingStream::class);
        AttachmentIntegrityFailingStream::$file = $this->path(2);
        $snapshots = $this->snapshots();
        $snapshots[2]->path = 'jemattachmentfail://file.txt';
        $result = JemAttachmentDeletion::execute($this->db, $snapshots, function () {
            $ids = [1, 2];
            return $this->model->delete($ids);
        });
        self::assertTrue($result->success);
        self::assertSame([], $this->ids());
        self::assertSame(1, $result->files);
        self::assertSame([2], $result->pending);
        self::assertFileDoesNotExist($this->path(1));
        self::assertSame('synthetic attachment 2', file_get_contents($this->path(2)));
        stream_wrapper_unregister('jemattachmentfail');
    }

    /** @return void An incompatible driver must fail before mutation, not assume no transaction. */
    public function testUnknownTransactionStateFailsClosed(): void
    {
        $db = $this->createMock(Joomla\Database\DatabaseInterface::class);
        $db->expects(self::never())->method('transactionStart');
        $result = JemAttachmentDeletion::execute($db, $this->snapshots(), static function () {
            throw new LogicException('Must not invoke the writer.');
        });
        self::assertFalse($result->success);
        $this->assertIntact();
    }

    /**
     * @param string $entry Entry point.
     * @return bool Production deletion result.
     */
    private function remove(string $entry): bool
    {
        return $entry === 'admin' ? $this->model->deleteWithFiles([1]) : JemAttachment::remove(1);
    }

    /**
     * @param int $id Synthetic attachment ID.
     * @return string Its physical fixture path.
     */
    private function path(int $id): string
    {
        return $this->directory . '/files/event1/test' . $id . '.txt';
    }

    /** @return array<int> Persisted IDs, using actual SQL rather than a mock. */
    private function ids(): array
    {
        return array_map('intval', $this->db->setQuery('SELECT id FROM t_jem_attachments ORDER BY id')->loadColumn());
    }

    /** @return array<object> Selected path snapshots for direct service fault cases. */
    private function snapshots(): array
    {
        return [
            1 => (object) ['object' => 'event1', 'file' => 'test1.txt', 'path' => $this->path(1)],
            2 => (object) ['object' => 'event1', 'file' => 'test2.txt', 'path' => $this->path(2)],
        ];
    }

    /** @return void Verify preservation of both rows and exact synthetic bytes. */
    private function assertIntact(): void
    {
        self::assertSame([1, 2], $this->ids());
        self::assertSame('synthetic attachment 1', file_get_contents($this->path(1)));
        self::assertSame('synthetic attachment 2', file_get_contents($this->path(2)));
    }

    /**
     * @param int $id Row whose DELETE must fail.
     * @return void
     */
    private function failDelete(int $id): void
    {
        if ($this->server) {
            $sql = "CREATE TRIGGER fail_delete BEFORE DELETE ON t_jem_attachments FOR EACH ROW
                BEGIN IF OLD.id=$id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic DELETE failure'; END IF; END";
            $this->db->getConnection()->query($sql);
        } else {
            $sql = "CREATE TRIGGER fail_delete BEFORE DELETE ON t_jem_attachments WHEN OLD.id=$id
                BEGIN SELECT RAISE(ABORT, 'Synthetic DELETE failure'); END";
            $this->db->setQuery($sql)->execute();
        }
    }
}
