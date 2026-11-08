<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise real JEM table hooks, Joomla SQL and physical files in disposable fixtures. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DeletedObjectCleanupTest extends TestCase
{
    private string $root = '';
    private string $database = '';
    private ?mysqli $server = null;
    private ?DeletedResourceDriver $db = null;

    /** @return void Bootstrap isolated Joomla services, native JEM tables and synthetic records. */
    protected function setUp(): void
    {
        $joomla = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($joomla . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to supply Joomla libraries; site data is never modified.');
        }
        require_once $joomla . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($joomla): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $joomla . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) { require_once $file; }
            }
        });
        require_once $joomla . '/configuration.php';
        require_once JEM_TEST_ROOT . '/tests/Support/deleted_resources_environment.php';
        $this->root = sys_get_temp_dir() . '/jem-deleted-resources-' . bin2hex(random_bytes(8));
        mkdir($this->root);
        define('JPATH_SITE', $this->root);
        define('JPATH_ROOT', $this->root);
        define('JPATH_BASE', $this->root);
        define('JPATH_ADMINISTRATOR', $this->root . '/administrator');
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        mkdir($this->root . '/components/com_jem/classes', 0777, true);
        foreach (['eventimagepath', 'venueimagepath', 'imagepublicationpolicy', 'imagefilenamepolicy', 'ageaccess', 'eventschedule', 'log'] as $file) {
            file_put_contents($this->root . '/components/com_jem/classes/' . $file . '.class.php', '<?php // Unused table dependencies.');
        }
        copy(JEM_TEST_ROOT . '/site/classes/deletedobjectcleanup.class.php', $this->root . '/components/com_jem/classes/deletedobjectcleanup.class.php');
        require_once JEM_TEST_ROOT . '/admin/tables/event.php';
        require_once JEM_TEST_ROOT . '/admin/tables/venue.php';
        require_once JEM_TEST_ROOT . '/admin/controllers/housekeeping.php';
        $config = new JConfig();
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_resource_test_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new DeletedResourceDriver([
            'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $this->database, 'prefix' => 't_',
        ]);
        $container = new Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->set(DispatcherInterface::class, new Dispatcher(), true);
        $container->set(LanguageFactoryInterface::class, new LanguageFactory());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$application = new DeletedResourceApplication();
        foreach ([
            'jem_events' => 'id INT PRIMARY KEY, published INT DEFAULT 1, datimage VARCHAR(255), locid INT DEFAULT 1',
            'jem_venues' => 'id INT PRIMARY KEY, published INT DEFAULT 1, locimage VARCHAR(255)',
            'jem_attachments' => 'id INT PRIMARY KEY, object VARCHAR(255), file VARCHAR(255)',
            'jem_links' => 'id INT PRIMARY KEY, event_id INT, url VARCHAR(255)',
            'jem_cats_event_relations' => 'itemid INT, catid INT',
            'jem_register' => 'event INT',
            'jem_reminders' => 'event_id INT',
            'jem_event_prices' => 'event_id INT',
            'jem_capacity_pools' => 'event_id INT',
            'jem_event_space_layouts' => 'event_id INT',
        ] as $table => $columns) {
            $this->sql('CREATE TABLE #__' . $table . ' (' . $columns . ') ENGINE=InnoDB');
        }
        $this->sql("INSERT INTO #__jem_events VALUES (1,1,'shared.jpg',1),(2,1,'shared.jpg',1)");
        $this->sql("INSERT INTO #__jem_venues VALUES (1,1,'shared.jpg'),(2,1,'shared.jpg')");
        $this->sql("INSERT INTO #__jem_links VALUES (1,1,'https://example.org/shared'),(2,2,'https://example.org/shared')");
        $this->sql('INSERT INTO #__jem_cats_event_relations VALUES (1,8),(2,8)');
        $this->sql('INSERT INTO #__jem_register VALUES (1),(2)');
        $this->attach(1, 'event1');
        $this->attach(2, 'event2');
        $this->attach(3, 'venue1');
        $this->put('images/jem/events/shared.jpg', 'shared image');
    }

    /** @return void Drop the uniquely named fixture DB and only files under this fixture root. */
    protected function tearDown(): void
    {
        $this->db?->disconnect();
        if ($this->server && preg_match('/^jem_resource_test_[a-f0-9]{16}$/D', $this->database)) {
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
        if ($this->root !== '' && is_dir($this->root)
            && str_starts_with($this->root, sys_get_temp_dir() . '/jem-deleted-resources-')) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                if ($entry->isLink()) {
                    if (is_dir($entry->getPathname()) && PHP_OS_FAMILY === 'Windows') { rmdir($entry->getPathname()); }
                    else { unlink($entry->getPathname()); }
                }
                elseif (!$entry->isDir()) { unlink($entry->getPathname()); }
                else { rmdir($entry->getPathname()); }
            }
            rmdir($this->root);
        }
    }

    /** @return void Same-name attachments, venue resources and shared images survive event deletion. */
    public function testPermanentEventDeletionOnlyRemovesOwnedResources(): void
    {
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->delete(1));
        self::assertSame(0, $this->rowCount('jem_events', 'id=1'));
        self::assertSame(0, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertSame(0, $this->rowCount('jem_links', 'event_id=1'));
        self::assertSame(1, $this->rowCount('jem_links', 'event_id=2'));
        self::assertFileDoesNotExist($this->root . '/files/event1/programme.pdf');
        self::assertFileExists($this->root . '/files/event2/programme.pdf');
        self::assertFileExists($this->root . '/files/venue1/programme.pdf');
        self::assertFileExists($this->root . '/images/jem/events/shared.jpg');
    }

    /** @return void The explicit key is used even if another row was loaded into the table. */
    public function testExplicitDeletionKeyDoesNotCleanTheLoadedEventsResources(): void
    {
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->load(2));
        self::assertTrue($table->delete(1));
        self::assertSame(1, $this->rowCount('jem_cats_event_relations', 'itemid=2'));
        self::assertSame(0, $this->rowCount('jem_cats_event_relations', 'itemid=1'));
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event2'"));
    }

    /** @return void Venue deletion cleans its own uploads, never event resources or images. */
    public function testVenueDeletionKeepsOtherOwnersResources(): void
    {
        $this->sql('UPDATE #__jem_events SET locid=2');
        $table = new JemTableVenue($this->db);
        self::assertTrue($table->delete(1));
        self::assertFileDoesNotExist($this->root . '/files/venue1/programme.pdf');
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
        self::assertSame(2, $this->rowCount('jem_links'));
    }

    /** @return void Trash and restore do not remove resources and are excluded from recovery. */
    public function testTrashAndRestorePreserveFilesAndRelations(): void
    {
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->publish([1], -2));
        self::assertSame('parent', JemDeletedObjectCleanup::purge($this->db, 'event1'));
        self::assertSame([], JemDeletedObjectCleanup::preview($this->db)['entries']);
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
        self::assertSame(1, $this->rowCount('jem_links', 'event_id=1'));
        self::assertTrue($table->publish([1], 1));
    }

    /** @return iterable<string, array{bool}> Commit and rollback of an outer owner. */
    public static function transactionOutcomes(): iterable
    {
        yield 'commit' => [true];
        yield 'rollback' => [false];
    }

    /** @param bool $commit Outer transaction decision. @return void */
    #[DataProvider('transactionOutcomes')]
    public function testOuterTransactionControlsPhysicalCleanup(bool $commit): void
    {
        $this->db->transactionStart();
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->delete(1));
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
        if ($commit) { $this->db->transactionCommit(); } else { $this->db->transactionRollback(); }
        JemDeletedObjectCleanup::flush($this->db);
        self::assertSame(!$commit, is_file($this->root . '/files/event1/programme.pdf'));
        self::assertSame($commit ? 0 : 1, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertSame($commit ? 0 : 1, $this->rowCount('jem_events', 'id=1'));
        self::assertSame($commit ? 0 : 1, $this->rowCount('jem_links', 'event_id=1'));
    }

    /** @return iterable<string, array{string}> Parent/relation SQL failure stages. */
    public static function deletionFailures(): iterable
    {
        yield 'parent' => ['jem_events'];
        yield 'relations' => ['jem_links'];
    }

    /** @param string $table Injected SQL failure table. @return void */
    #[DataProvider('deletionFailures')]
    public function testParentDeletionFailurePreservesResources(string $table): void
    {
        $this->db->failTable = $table;
        $event = new JemTableEvent($this->db);
        self::assertFalse($event->delete(1));
        self::assertSame(1, $this->rowCount('jem_events', 'id=1'));
        self::assertSame(1, $this->rowCount('jem_links', 'event_id=1'));
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
    }

    /** @return void Multiple owned records sharing a file are removed together without affecting others. */
    public function testDuplicateOwnedFileReferencesAndMissingFiles(): void
    {
        $this->sql("INSERT INTO #__jem_attachments VALUES (4,'event1','programme.pdf'),(5,'event1','missing.pdf')");
        $event = new JemTableEvent($this->db);
        self::assertTrue($event->delete(1));
        self::assertSame(0, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertFileExists($this->root . '/files/event2/programme.pdf');
    }

    /** @return void Unknown directory contents block cleanup and remain retryable after review. */
    public function testUnknownFilesArePreservedAndCleanupCanBeRetried(): void
    {
        $this->put('files/event1/unknown.txt', 'keep');
        self::assertTrue((new JemTableEvent($this->db))->delete(1));
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
        self::assertFileExists($this->root . '/files/event1/unknown.txt');
        self::assertNotEmpty(Factory::$application->messages);
        unlink($this->root . '/files/event1/unknown.txt');
        self::assertSame('done', JemDeletedObjectCleanup::purge($this->db, 'event1'));
        self::assertSame(0, $this->rowCount('jem_attachments', "object='event1'"));
    }

    /** @return void Legacy cross-folder references protect a file still used by another owner. */
    public function testCrossOwnerLegacyReferenceKeepsThePhysicalFile(): void
    {
        $this->sql("INSERT INTO #__jem_attachments VALUES (4,'event2','../event1/programme.pdf')");
        self::assertTrue((new JemTableEvent($this->db))->delete(1));
        self::assertSame('referenced', JemDeletedObjectCleanup::inspect($this->db, 'event1')['status']);
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
    }

    /** @return void A DB cleanup failure retains the recovery manifest even after files were removed. */
    public function testAttachmentSqlFailureCanBeRetriedWithoutDataLossToOtherOwners(): void
    {
        $this->db->failTable = 'jem_attachments';
        self::assertTrue((new JemTableEvent($this->db))->delete(1));
        self::assertSame(0, $this->rowCount('jem_events', 'id=1'));
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertFileExists($this->root . '/files/event2/programme.pdf');
        $this->db->failTable = '';
        self::assertSame('done', JemDeletedObjectCleanup::purge($this->db, 'event1'));
        self::assertSame(0, $this->rowCount('jem_attachments', "object='event1'"));
    }

    /** @return void More than 100 live objects cannot hide orphans; orphan pages are traversable. */
    public function testPreviewFiltersLiveObjectsBeforePagination(): void
    {
        for ($id = 1000; $id < 1101; ++$id) {
            $this->sql("INSERT INTO #__jem_events (id) VALUES ($id)");
            $this->sql("INSERT INTO #__jem_attachments VALUES ($id,'event$id','missing.pdf')");
        }
        for ($id = 9000; $id < 9051; ++$id) {
            $this->sql("INSERT INTO #__jem_attachments VALUES ($id,'event$id','missing.pdf')");
        }
        $first = JemDeletedObjectCleanup::preview($this->db);
        $next = JemDeletedObjectCleanup::preview($this->db, 2);
        self::assertCount(50, $first['entries']);
        self::assertTrue($first['more']);
        self::assertCount(1, $next['entries']);
        self::assertFalse($next['more']);
        self::assertArrayHasKey('event9050', $next['entries']);
    }

    /** @return void Old orphan links can be cleaned without deleting their URL target. */
    public function testLinkOnlyOrphanRecovery(): void
    {
        $this->sql("INSERT INTO #__jem_links VALUES (9,99,'https://example.org/shared')");
        $entries = JemDeletedObjectCleanup::preview($this->db)['entries'];
        self::assertArrayHasKey('event99', $entries);
        self::assertSame(['event99' => 'done'], JemDeletedObjectCleanup::recover($this->db, ['event99'], $entries));
        self::assertSame(2, $this->rowCount('jem_links'));
    }

    /** @return void Preview changes and restored parents invalidate destructive recovery. */
    public function testStalePreviewAndRecreatedParentArePreserved(): void
    {
        $this->sql('DELETE FROM #__jem_events WHERE id=1');
        $entries = JemDeletedObjectCleanup::preview($this->db)['entries'];
        $this->sql("INSERT INTO #__jem_attachments VALUES (4,'event1','missing.pdf')");
        self::assertSame(['event1' => 'stale'], JemDeletedObjectCleanup::recover($this->db, ['event1'], $entries));
        $this->sql('INSERT INTO #__jem_events (id) VALUES (1)');
        self::assertSame(['event1' => 'parent'], JemDeletedObjectCleanup::recover($this->db, ['event1'], $entries));
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
    }

    /** @return void Unreviewed selections cannot trigger any deletion. */
    public function testInvalidRecoverySelectionIsRejectedBeforeDeleting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemDeletedObjectCleanup::recover($this->db, ['event1'], []);
    }

    /** @return void Unsafe file names cannot escape into another event's directory. */
    public function testUnsafeOwnedPathDoesNotDeleteAnotherOwnersFile(): void
    {
        $this->sql("INSERT INTO #__jem_attachments VALUES (4,'event1','../event2/programme.pdf')");
        self::assertTrue((new JemTableEvent($this->db))->delete(1));
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
        self::assertFileExists($this->root . '/files/event2/programme.pdf');
        self::assertSame(2, $this->rowCount('jem_attachments', "object='event1'"));
    }

    /** @return void An OS-level file lock leaves the orphan manifest intact for a later retry. */
    public function testPhysicalDeletionFailureCanBeRetried(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('This fixture uses Windows file-sharing locks.');
        }
        $path = str_replace("'", "''", $this->root . '/files/event1/programme.pdf');
        $script = '$f = [IO.File]::Open(\'' . $path . '\', [IO.FileMode]::Open, [IO.FileAccess]::Read, [IO.FileShare]::Read); '
            . 'try { [Console]::WriteLine("READY"); [Console]::Out.Flush(); [Console]::ReadLine() | Out-Null } finally { $f.Dispose() }';
        $process = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand',
            base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 10);
            self::assertSame('READY', trim((string) fgets($pipes[1])));
            self::assertTrue((new JemTableEvent($this->db))->delete(1));
            self::assertSame(0, $this->rowCount('jem_events', 'id=1'));
            self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
            self::assertFileExists($this->root . '/files/event1/programme.pdf');
            self::assertNotEmpty(Factory::$application->messages);
        } finally {
            fwrite($pipes[0], "\n");
            foreach ($pipes as $pipe) { fclose($pipe); }
            proc_close($process);
        }
        self::assertSame('done', JemDeletedObjectCleanup::purge($this->db, 'event1'));
        self::assertSame(0, $this->rowCount('jem_attachments', "object='event1'"));
    }

    /** @return void A directory link must not redirect cleanup to another owner's files. */
    public function testLinkedObjectDirectoryIsNotFollowed(): void
    {
        unlink($this->root . '/files/event1/programme.pdf');
        rmdir($this->root . '/files/event1');
        $linked = @symlink($this->root . '/files/event2', $this->root . '/files/event1');
        if (!$linked && PHP_OS_FAMILY === 'Windows') {
            $path = str_replace("'", "''", $this->root . '/files/event1');
            $target = str_replace("'", "''", $this->root . '/files/event2');
            $script = "New-Item -ItemType Junction -Path '$path' -Target '$target' -ErrorAction Stop | Out-Null";
            $process = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand',
                base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            foreach ($pipes as $pipe) { fclose($pipe); }
            $linked = proc_close($process) === 0;
        }
        if (!$linked) {
            self::markTestSkipped('Directory symlink/junction creation is unavailable.');
        }
        try {
            self::assertTrue((new JemTableEvent($this->db))->delete(1));
            self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
            self::assertFileExists($this->root . '/files/event2/programme.pdf');
        } finally {
            // Remove the known directory link before its target is cleaned by teardown.
            if (PHP_OS_FAMILY === 'Windows') { rmdir($this->root . '/files/event1'); }
            else { unlink($this->root . '/files/event1'); }
        }
    }

    /** @return iterable<string, array{string}> Every administrative boundary is checked before deletion. */
    public static function deniedRecoveryRequests(): iterable
    {
        foreach (['token', 'tools', 'admin', 'nonce', 'expired', 'other-user', 'unreviewed', 'too-many'] as $case) {
            yield $case => [$case];
        }
    }

    /** @param string $case Rejected request condition. @return void */
    #[DataProvider('deniedRecoveryRequests')]
    public function testRecoveryControllerRejectsInvalidRequests(string $case): void
    {
        $this->sql('DELETE FROM #__jem_events WHERE id=1');
        $app = Factory::$application;
        $app->state['com_jem.deleted_resources_preview'] = [
            'user' => $case === 'other-user' ? 99 : 7,
            'time' => time() - ($case === 'expired' ? 901 : 0),
            'entries' => JemDeletedObjectCleanup::preview($this->db)['entries'],
        ];
        JemHelper::$token = $case !== 'token';
        JemHelper::$nonce = $case !== 'nonce';
        JemHelperBackend::$allowed = $case !== 'tools';
        $app->admin = $case !== 'admin';
        $selected = $case === 'too-many' ? array_fill(0, 11, 'event1') : [$case === 'unreviewed' ? 'event2' : 'event1'];
        $app->input->post->set('objects', $selected);
        $app->input->post->set('cleanup_nonce', 'fixture');
        $controller = new class extends JemControllerHousekeeping {
            public string $destination = '';
            public function __construct() {}
            public function setRedirect($url, $msg = null, $type = null) { $this->destination = $url; return $this; }
        };
        $rejected = false;
        try {
            $controller->recoverDeletedResources();
            $rejected = $controller->destination !== '';
        } catch (Exception $error) {
            $rejected = $error->getCode() === 403;
        }
        self::assertTrue($rejected);
        self::assertSame(1, $this->rowCount('jem_attachments', "object='event1'"));
        self::assertFileExists($this->root . '/files/event1/programme.pdf');
    }

    /** @return void Model and administrative wiring retain transaction and access boundaries. */
    public function testRecoveryAndOuterSaveIntegrationContracts(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertStringContainsString("JemDeletedObjectCleanup::flush(Factory::getContainer()->get('DatabaseDriver'))", $model);
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/housekeeping.php');
        $start = strpos($controller, 'public function recoverDeletedResources()');
        $end = strpos($controller, "\n    /**", $start);
        $method = substr($controller, $start, $end - $start);
        self::assertStringContainsString('JemHelper::requirePostToken()', $method);
        self::assertStringContainsString('$this->allowHousekeeping()', $method);
        self::assertStringContainsString("authorise('core.admin')", $method);
        self::assertStringContainsString('JemHelper::consumeActionNonce', $method);
        self::assertStringContainsString('time() - 900', $method);
    }

    /** @param string $query SQL for the isolated database. @return void */
    private function sql(string $query): void { $this->db->setQuery($query)->execute(); }

    /** @param string $table Table suffix. @param string $where Predicate. @return int Row count. */
    private function rowCount(string $table, string $where = '1=1'): int
    {
        return (int) $this->db->setQuery('SELECT COUNT(*) FROM #__' . $table . ' WHERE ' . $where)->loadResult();
    }

    /** @param int $id Row ID. @param string $object Owner. @return void */
    private function attach(int $id, string $object): void
    {
        $this->sql("INSERT INTO #__jem_attachments VALUES ($id,'$object','programme.pdf')");
        $this->put('files/' . $object . '/programme.pdf', 'fixture ' . $id);
    }

    /** @param string $path Relative fixture path. @param string $bytes Contents. @return void */
    private function put(string $path, string $bytes): void
    {
        $path = $this->root . '/' . $path;
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0777, true); }
        file_put_contents($path, $bytes);
    }
}
