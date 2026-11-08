<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Run JEM's real deferral/plugin against disposable tables and Joomla's native SQL driver. */
final class FinderTransactionTest extends TestCase
{
    private static ?mysqli $server = null;
    private static ?object $db = null;
    private static string $database = '';
    private static string $finderCleanup = '';
    private static array $indexTransactionStates = [];

    /** Create an isolated database; never modify the installed Joomla site's tables. */
    public static function setUpBeforeClass(): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || !is_file($root . '/configuration.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT for disposable database integration tests.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        require_once $root . '/configuration.php';
        require_once JEM_TEST_ROOT . '/site/classes/finderdeferral.class.php';
        require_once JEM_TEST_ROOT . '/tests/Support/finder_deferral_environment.php';
        $config = new JConfig();
        self::$database = 'jem_finder_test_' . bin2hex(random_bytes(7));
        self::$server = new mysqli($config->host, $config->user, $config->password);
        self::$server->query('CREATE DATABASE ' . self::$database . ' CHARACTER SET utf8mb4');
        try {
            self::$db = new Joomla\Database\Mysqli\MysqliDriver([
                'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
                'database' => self::$database, 'prefix' => 't_',
            ]);
            foreach ([
                'CREATE TABLE #__jem_events (id INT PRIMARY KEY, dates DATE NOT NULL, access INT NOT NULL) ENGINE=InnoDB',
                'CREATE TABLE #__jem_event_space_layouts (event_id INT PRIMARY KEY, layout_id INT NOT NULL) ENGINE=InnoDB',
                'CREATE TABLE #__finder_links (event_id INT PRIMARY KEY, event_date DATE NOT NULL) ENGINE=InnoDB',
                'CREATE TABLE #__finder_tokens (id INT PRIMARY KEY) ENGINE=MEMORY',
                'CREATE TABLE #__finder_tokens_aggregate (id INT PRIMARY KEY) ENGINE=MEMORY',
            ] as $sql) {
                self::$db->setQuery($sql)->execute();
            }
            $source = file_get_contents($root . '/administrator/components/com_finder/src/Indexer/Indexer.php');
            if (!preg_match_all('/\$db->truncateTable\(\x27#__finder_tokens(?:_aggregate)?\x27\);/', $source, $matches)) {
                throw new RuntimeException('Native Joomla Finder cleanup calls not found.');
            }
            self::$finderCleanup = implode("\n", array_unique($matches[0]));
        } catch (Throwable $error) {
            self::tearDownAfterClass();
            throw $error;
        }
    }

    /** Remove only the random database created by this test class. */
    public static function tearDownAfterClass(): void
    {
        self::$db?->disconnect();
        if (self::$server !== null && preg_match('/^jem_finder_test_[0-9a-f]{14}$/', self::$database)) {
            self::$server->query('DROP DATABASE IF EXISTS ' . self::$database);
            self::$server->close();
        }
        self::$server = null;
        self::$db = null;
    }

    /** Reset synthetic events/index and install the native Finder-cleanup callback. */
    protected function setUp(): void
    {
        self::$db->transactionRollback();
        for ($depth = 0; $depth < 3; ++$depth) {
            JemFinderDeferral::end(false);
        }
        foreach (['jem_events', 'jem_event_space_layouts', 'finder_links'] as $table) {
            self::$db->setQuery('DELETE FROM #__' . $table)->execute();
        }
        self::$db->setQuery("INSERT INTO #__jem_events VALUES (10,'2026-12-20',1),(11,'2026-12-21',1)")->execute();
        self::$db->setQuery('INSERT INTO #__jem_event_space_layouts VALUES (10,1),(11,1)')->execute();
        self::$db->setQuery('INSERT INTO #__finder_links SELECT id,dates FROM #__jem_events')->execute();
        Adapter::$calls = [];
        Adapter::$storedAccess = [10 => 1, 11 => 1];
        Factory::$messages = [];
        Factory::$failWarning = false;
        Log::$entries = [];
        Log::$fail = false;
        self::$indexTransactionStates = [];
        Adapter::$indexCallback = static function (int $id): void {
            $db = self::$db;
            self::$indexTransactionStates[] = (int) $db->setQuery('SELECT @@in_transaction')->loadResult();
            $db->setQuery('REPLACE INTO #__finder_links SELECT id,dates FROM #__jem_events WHERE id=' . $id)->execute();
            // Execute the actual cleanup statements found in this Joomla installation.
            eval(self::$finderCleanup);
        };
    }

    /** Show that the original unguarded timing really prevents a later rollback. */
    public function testUnguardedControlDemonstratesThePrematureCommit(): void
    {
        self::$db->transactionStart();
        $this->saveDate(10, '2026-12-15');
        self::assertSame(0, $this->transactionActive());
        self::$db->transactionRollback();
        self::assertSame('2026-12-15', $this->eventDate(10));
        self::assertSame('2026-12-15', $this->indexDate(10));
        self::assertSame([1], self::$indexTransactionStates);
    }

    /** @return iterable<string, array{string}> Both supported save contexts. */
    public static function contexts(): iterable
    {
        yield 'backend' => ['com_jem.event'];
        yield 'frontend' => ['com_jem.editevent'];
    }

    /**
     * A rejected save retains the previous date, assignment and existing index.
     * @param string $context Frontend or backend save notification.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testRejectedSavePreservesCommittedState(string $context): void
    {
        self::$db->transactionStart();
        JemFinderDeferral::begin();
        $this->saveDate(10, '2026-12-15', $context);
        self::$db->setQuery('UPDATE #__jem_event_space_layouts SET layout_id=2 WHERE event_id=10')->execute();
        self::assertSame(1, $this->transactionActive());
        self::assertSame([], Adapter::$calls);
        self::$db->transactionRollback();
        JemFinderDeferral::end(false);
        self::assertSame('2026-12-20', $this->eventDate(10));
        self::assertSame('2026-12-20', $this->indexDate(10));
        self::assertSame(1, (int) self::$db->setQuery('SELECT layout_id FROM #__jem_event_space_layouts WHERE event_id=10')->loadResult());
        self::assertSame([], self::$indexTransactionStates);
    }

    /** Index final committed rows only after all series writes have succeeded. */
    public function testSuccessfulSeriesIndexesOnlyAfterCommit(): void
    {
        self::$db->transactionStart();
        JemFinderDeferral::begin();
        $this->saveDate(10, '2026-12-22');
        $this->saveDate(11, '2026-12-23');
        self::$db->setQuery('UPDATE #__jem_event_space_layouts SET layout_id=2')->execute();
        self::assertSame([], Adapter::$calls);
        self::$db->transactionCommit();
        JemFinderDeferral::end(true);
        self::assertSame(['2026-12-22', '2026-12-23'], [$this->eventDate(10), $this->eventDate(11)]);
        self::assertSame(['2026-12-22', '2026-12-23'], [$this->indexDate(10), $this->indexDate(11)]);
        self::assertSame([0, 0], self::$indexTransactionStates);
        self::assertSame([], Factory::$messages);
    }

    /** A later occurrence failure must roll back earlier events and index work. */
    public function testLaterSeriesFailureRollsBackEarlierWrites(): void
    {
        self::$db->transactionStart();
        JemFinderDeferral::begin();
        $this->saveDate(10, '2026-12-22');
        $this->saveDate(11, '2026-12-23');
        self::$db->transactionRollback();
        JemFinderDeferral::end(false);
        self::assertSame(['2026-12-20', '2026-12-21'], [$this->eventDate(10), $this->eventDate(11)]);
        self::assertSame(['2026-12-20', '2026-12-21'], [$this->indexDate(10), $this->indexDate(11)]);
        self::assertSame([], Adapter::$calls);
    }

    /** A native SQL error after commit must warn without undoing the saved event. */
    public function testNativeIndexSqlErrorPreservesTheSuccessfulSave(): void
    {
        Adapter::$indexCallback = static function (): void {
            self::$db->setQuery('SELECT missing_column FROM #__finder_links')->loadResult();
        };
        self::$db->transactionStart();
        JemFinderDeferral::begin();
        $this->saveDate(10, '2026-12-22');
        self::$db->transactionCommit();
        JemFinderDeferral::end(true);
        self::assertSame('2026-12-22', $this->eventDate(10));
        self::assertSame('2026-12-20', $this->indexDate(10));
        self::assertSame([['COM_JEM_FINDER_INDEX_FAILED_AFTER_SAVE', 'warning']], Factory::$messages);
        self::assertCount(1, Log::$entries);
        self::assertStringContainsString('missing_column', Log::$entries[0][0]);
    }

    /** Disabled Finder means no plugin notification and no queued side effects. */
    public function testDisabledFinderDoesNotAffectTheEventCommit(): void
    {
        self::$db->transactionStart();
        JemFinderDeferral::begin();
        self::$db->setQuery("UPDATE #__jem_events SET dates='2026-12-22' WHERE id=10")->execute();
        self::$db->transactionCommit();
        JemFinderDeferral::end(true);
        self::assertSame('2026-12-22', $this->eventDate(10));
        self::assertSame('2026-12-20', $this->indexDate(10));
        self::assertSame([], Adapter::$calls);
    }

    /**
     * Write a fixture event and send the real JEM plugin its normal notifications.
     * @param int $id Event ID.
     * @param string $date New event date.
     * @param string $context Save context.
     * @return void
     */
    private function saveDate(int $id, string $date, string $context = 'com_jem.event'): void
    {
        $plugin = new FinderDeferralPluginProbe();
        $row = (object) ['id' => $id, 'access' => 1];
        $plugin->onFinderBeforeSave($context, $row, false);
        self::$db->setQuery('UPDATE #__jem_events SET dates=' . self::$db->quote($date) . ' WHERE id=' . $id)->execute();
        $plugin->onFinderAfterSave($context, $row, false);
    }

    /** @return int Native MariaDB transaction status. */
    private function transactionActive(): int
    {
        return (int) self::$db->setQuery('SELECT @@in_transaction')->loadResult();
    }

    /** @param int $id Event ID. @return string Stored event date. */
    private function eventDate(int $id): string
    {
        return (string) self::$db->setQuery('SELECT dates FROM #__jem_events WHERE id=' . $id)->loadResult();
    }

    /** @param int $id Event ID. @return string Date currently held by the synthetic index. */
    private function indexDate(int $id): string
    {
        return (string) self::$db->setQuery('SELECT event_date FROM #__finder_links WHERE event_id=' . $id)->loadResult();
    }
}
