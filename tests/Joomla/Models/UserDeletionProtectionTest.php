<?php

declare(strict_types=1);

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Event\User\BeforeDeleteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Input\Input;
use Joomla\Plugin\User\Jem\Extension\Jem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the real user plugin and SQL protection query in a disposable database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UserDeletionProtectionTest extends TestCase
{
    private ?mysqli $server = null;
    private ?UserDeletionDatabase $db = null;
    private string $database = '';
    private Input $input;
    private AdministratorApplication $app;
    private Jem $plugin;
    private array $messages = [];
    private array $redirects = [];

    /**
     * Prepare isolated SQL fixtures and a native CMSPlugin application dependency.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_WRITABLE') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_WRITABLE=1 for disposable SQL tests.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        require_once $root . '/configuration.php';
        require_once JEM_TEST_ROOT . '/tests/Support/user_deletion_environment.php';
        require_once JEM_TEST_ROOT . '/plugins/plg_user_jem/src/Extension/Jem.php';

        $config = new JConfig();
        self::assertContains($config->host, ['localhost', '127.0.0.1']);
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_user_delete_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new UserDeletionDatabase([
            'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $this->database, 'prefix' => 't_',
        ]);
        $this->sql('CREATE TABLE #__users (id INT PRIMARY KEY) ENGINE=InnoDB');
        $this->sql('CREATE TABLE #__jem_events (id INT PRIMARY KEY, end_utc DATETIME NULL, start_utc DATETIME NULL, enddates DATE NULL, dates DATE NULL) ENGINE=InnoDB');
        $this->sql('CREATE TABLE #__jem_register (id INT PRIMARY KEY, uid INT, event INT, status INT, waiting INT DEFAULT 0) ENGINE=InnoDB');
        $this->sql('INSERT INTO #__users VALUES (10),(11),(12),(13)');
        $this->sql("INSERT INTO #__jem_events (id, end_utc) VALUES (1, '2999-01-01 12:00:00'),(2, '2000-01-01 12:00:00')");
        $this->sql('INSERT INTO #__jem_register (id,uid,event,status) VALUES (1,10,1,1),(2,11,1,-1),(3,12,2,1)');

        $languageClass = new ReflectionClass(Language::class);
        $language = $languageClass->newInstanceWithoutConstructor();
        $languageClass->getProperty('strings')->setValue($language, parse_ini_file(
            JEM_TEST_ROOT . '/plugins/plg_user_jem/language/en-GB/plg_user_jem.ini', false, INI_SCANNER_RAW
        ));
        $this->input = new Input(['option' => 'com_users', 'task' => 'users.delete', 'cid' => []]);
        $this->app = $this->createMock(AdministratorApplication::class);
        $this->app->method('getInput')->willReturn($this->input);
        $this->app->method('getLanguage')->willReturn($language);
        $this->app->method('get')->willReturnCallback(static fn ($key, $default = null) => $default);
        $this->app->method('enqueueMessage')->willReturnCallback(function ($message, $type): void {
            $this->messages[] = [$message, $type];
        });
        Factory::$application = $this->app;
        Factory::$language = $language;
        $this->plugin = (new ReflectionClass(Jem::class))->newInstanceWithoutConstructor();
        $this->plugin->setApplication($this->app);
        $this->plugin->setDatabase($this->db);
    }

    /**
     * Remove only the random database created by this test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->failTables = false;
            $this->db->failCount = false;
            $this->db->disconnect();
        }
        if ($this->server && preg_match('/^jem_user_delete_[a-f0-9]{16}$/D', $this->database)) {
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
    }

    /**
     * Supply native administrator selections and expected survivors.
     *
     * @return iterable<string, array{array, array, int, bool}>
     */
    public static function selections(): iterable
    {
        yield 'protected alone' => [[10], [10, 11, 12, 13], 1, true];
        yield 'protected first' => [[10, 11], [10, 11, 12, 13], 1, true];
        yield 'protected last' => [[11, 10], [10, 11, 12, 13], 1, true];
        yield 'waiting registration' => [[11, 10], [10, 11, 12, 13], 1, true];
        yield 'cancelled registration' => [[11], [10, 12, 13], 1, false];
        yield 'past event' => [[12], [10, 11, 13], 1, false];
        yield 'three allowed accounts' => [[11, 12, 13], [10], 3, false];
    }

    /**
     * Execute the native sequential event-before-delete order with real SQL rows.
     *
     * @param array $ids Selected user IDs in deletion order.
     * @param array $survivors Expected surviving accounts.
     * @param int $queries Expected number of protection queries.
     * @param bool $blocked Whether the selection must be rejected.
     * @return void
     */
    #[DataProvider('selections')]
    public function testAdministratorSelections(array $ids, array $survivors, int $queries, bool $blocked): void
    {
        $this->administrator();
        if (str_contains($this->dataName(), 'waiting')) {
            $this->sql('UPDATE #__jem_register SET waiting=1 WHERE id=1');
        }
        $this->input->set('cid', $ids);
        try {
            foreach ($ids as $id) {
                $this->beforeDelete($id);
                $this->sql('DELETE FROM #__users WHERE id=' . $id);
            }
            self::assertFalse($blocked);
        } catch (UserDeletionRedirect $e) {
            self::assertTrue($blocked);
            self::assertStringContainsString('active registration', $this->messages[0][0]);
            if (count($ids) > 1) {
                self::assertStringContainsString('None of the selected users were deleted', $this->messages[0][0]);
            }
        }
        self::assertSame($survivors, $this->users());
        self::assertCount($queries, $this->db->countQueries);
        self::assertCount($blocked ? 1 : 0, $this->redirects);
        if ($blocked) {
            self::assertSame(['index.php?option=com_users&view=users', 303], $this->redirects[0]);
            self::assertSame('error', $this->messages[0][1]);
        }
    }

    /**
     * Supply query failures for single and batch requests.
     *
     * @return iterable<string, array{bool, bool}>
     */
    public static function failures(): iterable
    {
        yield 'single table lookup' => [true, false];
        yield 'batch table lookup' => [true, true];
        yield 'single registration query' => [false, false];
        yield 'batch registration query' => [false, true];
    }

    /**
     * Fail closed with a readable message before processing any selected account.
     *
     * @param bool $tables Fail the table-list query instead of the registration query.
     * @param bool $batch Use a mixed selection.
     * @return void
     */
    #[DataProvider('failures')]
    public function testDatabaseFailureStopsDeletion(bool $tables, bool $batch): void
    {
        $this->administrator();
        $this->input->set('cid', $batch ? [11, 10] : [10]);
        $this->db->failTables = $tables;
        $this->db->failCount = !$tables;
        try {
            $this->beforeDelete($batch ? 11 : 10);
            self::fail('The failed protection check must stop deletion.');
        } catch (UserDeletionRedirect $e) {
            self::assertStringContainsString('registration check failed', $this->messages[0][0]);
            self::assertStringNotContainsString('Injected', $this->messages[0][0]);
        }
        self::assertSame([10, 11, 12, 13], $this->users());
        self::assertCount(1, $this->redirects);
    }

    /**
     * Supply non-native deletion contexts that must retain an exception veto.
     *
     * @return iterable<string, array{bool, string, string, array}>
     */
    public static function externalContexts(): iterable
    {
        yield 'CLI or API' => [false, 'com_users', 'users.delete', [10]];
        yield 'other component' => [true, 'com_other', 'users.delete', [10]];
        yield 'other task' => [true, 'com_users', 'users.save', [10]];
        yield 'user outside selection' => [true, 'com_users', 'users.delete', [11, 12]];
        yield 'no selection' => [true, 'com_users', 'users.delete', []];
    }

    /**
     * Preserve reliable vetoes without redirecting unrelated callers.
     *
     * @param bool $admin Whether the caller is an administrator application.
     * @param string $option Component name.
     * @param string $task Requested task.
     * @param array $ids Request selection.
     * @return void
     */
    #[DataProvider('externalContexts')]
    public function testExternalCallersKeepExceptionVeto(bool $admin, string $option, string $task, array $ids): void
    {
        $this->app->method('isClient')->willReturn($admin);
        $this->input->set('option', $option);
        $this->input->set('task', $task);
        $this->input->set('cid', $ids);
        try {
            $this->beforeDelete(10);
            self::fail('Protected account must not be deleted.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('active registration', $e->getMessage());
        }
        self::assertSame([], $this->messages);
        self::assertSame([], $this->redirects);
        self::assertSame([10, 11, 12, 13], $this->users());
    }

    /**
     * Do not reuse successful prevalidation for a different selection.
     *
     * @return void
     */
    public function testChangedSelectionIsCheckedAgain(): void
    {
        $this->administrator();
        $this->input->set('cid', [11, 12]);
        $this->beforeDelete(11);
        $this->input->set('cid', [13, 10]);
        try {
            $this->beforeDelete(13);
            self::fail('The second selection must be checked.');
        } catch (UserDeletionRedirect $e) {
            self::assertCount(2, $this->db->countQueries);
        }
        self::assertSame([10, 11, 12, 13], $this->users());
    }

    /**
     * Recheck each later account even after its selection passed prevalidation.
     *
     * @return void
     */
    public function testNewRegistrationAfterPrevalidationStillBlocksItsOwner(): void
    {
        $this->administrator();
        $this->input->set('cid', [11, 12]);
        $this->beforeDelete(11);
        $this->sql('DELETE FROM #__users WHERE id=11');
        $this->sql('INSERT INTO #__jem_register (id,uid,event,status) VALUES (4,12,1,1)');
        try {
            $this->beforeDelete(12);
            self::fail('The later account must be checked again.');
        } catch (UserDeletionRedirect $e) {
            self::assertStringContainsString('This user cannot be deleted', $this->messages[0][0]);
            self::assertStringNotContainsString('None of the selected users', $this->messages[0][0]);
        }
        self::assertSame([10, 12, 13], $this->users());
    }

    /**
     * Keep com_users available when the JEM tables have already been removed.
     *
     * @return void
     */
    public function testAbsentJemTablesDoNotBlockDeletion(): void
    {
        $this->administrator();
        $this->input->set('cid', [10]);
        $this->sql('DROP TABLE #__jem_register');
        $this->beforeDelete(10);
        self::assertSame([], $this->messages);
        self::assertSame([], $this->db->countQueries);
    }

    /**
     * Never allow deletion if an application redirect unexpectedly returns.
     *
     * @return void
     */
    public function testReturningRedirectStillThrows(): void
    {
        $this->app->method('isClient')->willReturn(true);
        $this->input->set('cid', [10]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active registration');
        $this->beforeDelete(10);
    }

    /**
     * Match the native legacy task while rejecting malformed selection entries.
     *
     * @return void
     */
    public function testLegacyTaskNormalisesSelectionWithoutLosingProtectedUsers(): void
    {
        $this->administrator();
        $this->input->set('task', 'delete');
        $this->input->set('cid', [11, 0, -7, '10', 10, [12]]);
        try {
            $this->beforeDelete(11);
            self::fail('The protected member must reject the selection.');
        } catch (UserDeletionRedirect $e) {
            self::assertStringContainsString('None of the selected users', $this->messages[0][0]);
        }
        self::assertCount(1, $this->db->countQueries);
        self::assertSame([10, 11, 12, 13], $this->users());
    }

    /**
     * Preserve database failures as exception causes outside the administrator UI.
     *
     * @return void
     */
    public function testExternalDatabaseFailuresRetainTheirCause(): void
    {
        $this->app->method('isClient')->willReturn(false);
        foreach ([true, false] as $tables) {
            $this->db->failTables = $tables;
            $this->db->failCount = !$tables;
            try {
                $this->beforeDelete(10);
                self::fail('A failed check must veto deletion.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('registration check failed', $e->getMessage());
                self::assertInstanceOf(RuntimeException::class, $e->getPrevious());
                self::assertStringContainsString('Injected', $e->getPrevious()->getMessage());
            }
        }
        self::assertSame([], $this->messages);
        self::assertSame([], $this->redirects);
        self::assertSame([10, 11, 12, 13], $this->users());
    }

    /**
     * Simulate the terminating administrator redirect outside the SQL catch block.
     *
     * @return void
     */
    private function administrator(): void
    {
        $this->app->method('isClient')->willReturn(true);
        $this->app->method('redirect')->willReturnCallback(function ($url, $status): never {
            $this->redirects[] = [$url, $status];
            throw new UserDeletionRedirect('Administrator redirect');
        });
    }

    /**
     * Dispatch the native before-delete event directly to the real plugin.
     *
     * @param int $id Account about to be deleted.
     * @return void
     */
    private function beforeDelete(int $id): void
    {
        $this->plugin->onUserBeforeDelete(new BeforeDeleteEvent('onUserBeforeDelete', [
            'subject' => ['id' => $id, 'username' => 'test-' . $id],
        ]));
    }

    /**
     * Read the surviving test accounts.
     *
     * @return int[] User IDs in ascending order.
     */
    private function users(): array
    {
        return array_map('intval', $this->db->setQuery('SELECT id FROM #__users ORDER BY id')->loadColumn());
    }

    /**
     * Execute a fixture statement in the disposable database.
     *
     * @param string $query SQL using the fixture prefix.
     * @return void
     */
    private function sql(string $query): void
    {
        $this->db->setQuery($query)->execute();
    }
}
