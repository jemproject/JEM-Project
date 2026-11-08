<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Run the current promotion service, registration writer and price selector against disposable SQL tables. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WaitingListOwnerEligibilityTest extends TestCase
{
    private ?mysqli $server = null;
    private ?WaitingOwnerDriver $db = null;
    private string $database = '';
    private bool $priced = false;

    /**
     * @return void Create an isolated database using source install schemas and synthetic accounts.
     */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_WRITABLE') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 and JEM_TEST_JOOMLA_ROOT for disposable SQL tests.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        require_once $root . '/configuration.php';
        require_once JEM_TEST_ROOT . '/tests/Support/waiting_list_owner_environment.php';
        define('JPATH_SITE', sys_get_temp_dir() . '/jem-wait-owner-unused');
        $config = new JConfig();
        self::assertContains($config->host, ['localhost', '127.0.0.1'], 'Local SQL server required.');
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_wait_owner_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new WaitingOwnerDriver(['host' => $config->host, 'user' => $config->user,
            'password' => $config->password, 'database' => $this->database, 'prefix' => 't_']);
        Factory::$db = $this->db;
        $this->priced = is_file(JEM_TEST_ROOT . '/site/classes/pricedregistration.class.php');
        $tables = ['jem_events', 'jem_register'];
        if ($this->priced) {
            $tables = array_merge($tables, ['jem_register_history', 'jem_register_items',
                'jem_register_capacity_allocations', 'jem_event_prices', 'jem_capacity_pools']);
            require_once JEM_TEST_ROOT . '/site/classes/pricedregistration.class.php';
        }
        require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/waitinglistpromotion.class.php';
        $install = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        foreach ($tables as $table) {
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__' . $table . '`.*?;/s', $install, $match));
            $this->sql($match[0]);
        }
        $this->sql('CREATE TABLE #__users (id INT PRIMARY KEY, block INT DEFAULT 0, activation VARCHAR(100) DEFAULT \'\') ENGINE=InnoDB');
        $this->sql('INSERT INTO #__users (id) VALUES (100),(101)');
        $event = (object) ['id' => 1, 'title' => 'Owner test', 'introtext' => '', 'fulltext' => '',
            'maxplaces' => 1, 'waitinglist' => 1, 'maxbookeduser' => 10, 'metadata' => '{}', 'attribs' => '{}',
            'created_by_alias' => ''];
        $this->db->insertObject('#__jem_events', $event);
    }

    /**
     * @return void Drop only this test's random database, including after a failed assertion.
     */
    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->failSql = '';
            $this->db->disconnect();
        }
        if ($this->server && preg_match('/^jem_wait_owner_[a-f0-9]{16}$/D', $this->database)) {
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
    }

    /**
     * @return iterable<string, array{string, int}> Queue strategy and orphan request size.
     */
    public static function strategies(): iterable
    {
        foreach (['strict', 'fill'] as $strategy) {
            foreach ([1, 3] as $places) {
                yield $strategy . ' orphan places ' . $places => [$strategy, $places];
            }
        }
    }

    /**
     * @param string $strategy Queue policy.
     * @param int $places Orphan request.
     * @return void
     */
    #[DataProvider('strategies')]
    public function testAutomaticPromotionSkipsOrphansAndKeepsStoredCounts(string $strategy, int $places): void
    {
        JemHelper::$strategy = $strategy;
        $this->registration(1, 999, $places);
        $this->registration(2, 100);
        $result = $this->promote(['notify' => true]);
        self::assertTrue($result->success, $result->reason);
        self::assertSame([2], $result->promotedIds);
        self::assertSame(2, $result->waitingBefore);
        self::assertSame(1, $result->waitingAfter);
        self::assertSame(1, $this->waiting(1));
        self::assertSame(0, $this->waiting(2));
        self::assertSame(1, JemWaitingListPromotion::availability(1)->waitingAfter);
        self::assertCount(1, $this->db->ownerQueries);
        self::assertSame('onUserOnOffWaitinglist', JemFactory::$notifications[0][0]);
        self::assertSame([2], JemFactory::$notifications[0][1]);
        foreach (JemFactory::$notifications as $notification) {
            self::assertSame(0, $notification[2], 'Notifications must occur after commit.');
        }
        if ($this->priced) {
            self::assertSame([2], array_map('intval', $this->db->setQuery('SELECT registration_id FROM #__jem_register_history')->loadColumn()));
        }
    }

    /**
     * @return iterable<string, array{array, bool}> Manual selections and force flag.
     */
    public static function manualSelections(): iterable
    {
        yield 'missing account' => [[1], false];
        yield 'mixed selection' => [[1, 2], false];
        yield 'forced missing account' => [[1], true];
        yield 'forced mixed selection' => [[1, 2], true];
    }

    /**
     * @param array $ids Selected rows.
     * @param bool $force Capacity override.
     * @return void
     */
    #[DataProvider('manualSelections')]
    public function testManualSelectionIsRejectedAtomically(array $ids, bool $force): void
    {
        $this->registration(1, 999);
        $this->registration(2, 100);
        $result = $this->promote(['mode' => 'manual', 'registrationIds' => $ids, 'force' => $force, 'notify' => true]);
        self::assertFalse($result->success);
        self::assertSame('selected_user_missing', $result->reason);
        self::assertSame([], $result->promotedIds);
        self::assertSame(1, $this->waiting(1));
        self::assertSame(1, $this->waiting(2));
        self::assertSame([], JemFactory::$notifications);
    }

    /**
     * @return void A manual valid selection remains possible while an older orphan stays waiting.
     */
    public function testManualValidSelectionPreservesOrphanAndCounts(): void
    {
        $this->registration(1, 999);
        $this->registration(2, 100);
        $result = $this->promote(['mode' => 'manual', 'registrationIds' => [2]]);
        self::assertTrue($result->success, $result->reason);
        self::assertSame([2], $result->promotedIds);
        self::assertSame(1, $result->waitingAfter);
        self::assertSame(1, $this->waiting(1));
    }

    /**
     * @return void Preserve existing uid=0 registrations without manufacturing a Joomla account.
     */
    public function testExistingGuestRemainsEligible(): void
    {
        $this->registration(1, 999);
        $this->registration(2, 0);
        $result = $this->promote();
        self::assertTrue($result->success, $result->reason);
        self::assertSame([2], $result->promotedIds);
        self::assertSame(1, $result->waitingAfter);
    }

    /**
     * @return void Do not query confirmed owners or release their existing capacity.
     */
    public function testConfirmedOrphanStillConsumesCapacity(): void
    {
        $this->registration(1, 999, 1, 0);
        $this->registration(2, 100);
        $result = $this->promote();
        self::assertTrue($result->success, $result->reason);
        self::assertSame([], $result->promotedIds);
        self::assertSame(0, $result->availableBefore);
        self::assertStringNotContainsString('999', implode(' ', $this->db->ownerQueries));
        self::assertSame(0, $this->waiting(1));
        self::assertSame(1, $this->waiting(2));
    }

    /**
     * @return void Keep FIFO ordering among legitimate requests and honour exclusions.
     */
    public function testValidQueueOrderAndExclusionsRemainUnchanged(): void
    {
        $this->registration(1, 999);
        $this->registration(2, 100, 2);
        $this->registration(3, 101);
        self::assertSame([], $this->promote()->promotedIds);
        self::assertSame([3], $this->promote(['excludeIds' => [2]])->promotedIds);
        self::assertSame(1, $this->waiting(1));
        self::assertSame(1, $this->waiting(2));
    }

    /**
     * @return void Return the full pending count when no owner can receive the free place.
     */
    public function testOnlyOrphansRemainVisibleInCounts(): void
    {
        $this->registration(1, 999);
        $result = $this->promote();
        self::assertTrue($result->success, $result->reason);
        self::assertSame('nothing_to_promote', $result->reason);
        self::assertSame(1, $result->waitingBefore);
        self::assertSame(1, $result->waitingAfter);
        self::assertSame(1, $result->availableAfter);
    }

    /**
     * @return iterable<string, array{string}> SQL stages whose failure must roll back.
     */
    public static function sqlFailures(): iterable
    {
        yield 'owner lookup' => ['FROM `#__users`'];
        yield 'registration update' => ['UPDATE `#__jem_register`'];
    }

    /**
     * @param string $sql Failure stage.
     * @return void No partial promotion or notification on SQL failure.
     */
    #[DataProvider('sqlFailures')]
    public function testDatabaseFailuresDoNotPromoteOrNotify(string $sql): void
    {
        $this->registration(1, 999);
        $this->registration(2, 100);
        $this->db->failSql = $sql;
        $result = $this->promote(['notify' => true]);
        $this->db->failSql = '';
        self::assertFalse($result->success);
        self::assertSame('Injected SQL failure.', $result->reason);
        self::assertSame(1, $this->waiting(1));
        self::assertSame(1, $this->waiting(2));
        self::assertSame([], JemFactory::$notifications);
    }

    /**
     * @return iterable<string, array{int, int}> Shared pool and ticket quota capacities.
     */
    public static function priceCapacities(): iterable
    {
        yield 'available' => [1, 1];
        yield 'pool full' => [0, 1];
        yield 'quota full' => [1, 0];
    }

    /**
     * @param int $pool Pool capacity.
     * @param int $quota Ticket quota.
     * @return void
     */
    #[DataProvider('priceCapacities')]
    public function testPricedPromotionUsesTheSameEligibleCandidates(int $pool, int $quota): void
    {
        if (!$this->priced) {
            self::markTestSkipped('Priced registrations belong to JEM 5.1.0.');
        }
        $this->registration(1, 999);
        $this->registration(2, 100);
        $this->sql("UPDATE #__jem_events SET pricing_mode='priced' WHERE id=1");
        $this->sql("UPDATE #__jem_register SET pricing_mode='priced', currency='EUR', subtotal_net=10, grand_total=10 WHERE event=1");
        $this->sql("INSERT INTO #__jem_capacity_pools (id,event_id,code,name,capacity) VALUES (1,1,'test','Test',$pool)");
        $this->sql("INSERT INTO #__jem_event_prices (id,event_id,code,name,quota) VALUES (1,1,'test','Test',$quota)");
        foreach ([1, 2] as $id) {
            $this->sql("INSERT INTO #__jem_register_items (register_id,registration_revision,line_number,line_kind,event_price_id,capacity_pool_id,item_code,item_name,currency,quantity,unit_net,unit_gross,line_net,line_tax,line_gross) VALUES ($id,1,1,'admission',1,1,'test','Test','EUR',1,10,10,10,0,10)");
        }
        $result = $this->promote();
        self::assertTrue($result->success, $result->reason);
        self::assertSame($pool && $quota ? [2] : [], $result->promotedIds);
        self::assertSame(1, $this->waiting(1));
        self::assertSame($pool && $quota ? 0 : 1, $this->waiting(2));
        self::assertSame($pool && $quota ? 1 : 2, $result->waitingAfter);
        self::assertSame(1, (int) $this->db->setQuery('SELECT revision FROM #__jem_register WHERE id=1')->loadResult());
        $revision = $pool && $quota ? 2 : 1;
        self::assertSame($revision, (int) $this->db->setQuery('SELECT revision FROM #__jem_register WHERE id=2')->loadResult());
        self::assertSame('10.00', $this->db->setQuery('SELECT grand_total FROM #__jem_register WHERE id=2')->loadResult());
        self::assertSame('10.00', $this->db->setQuery('SELECT line_gross FROM #__jem_register_items WHERE register_id=2 AND registration_revision=' . $revision)->loadResult());
    }

    /**
     * @param array $options Promotion overrides.
     * @return object Production service response.
     */
    private function promote(array $options = []): object
    {
        return JemWaitingListPromotion::promote(1, $options + ['notify' => false]);
    }

    /**
     * @param string $sql Statement against this test database only.
     * @return void
     */
    private function sql(string $sql): void
    {
        $this->db->setQuery($sql)->execute();
    }

    /**
     * @param int $id Registration id.
     * @return int Stored waiting state.
     */
    private function waiting(int $id): int
    {
        return (int) $this->db->setQuery('SELECT waiting FROM #__jem_register WHERE id=' . $id)->loadResult();
    }

    /**
     * @param int $id Registration id.
     * @param int $uid Owner.
     * @param int $places Quantity.
     * @param int $waiting State.
     * @return void
     */
    private function registration(int $id, int $uid, int $places = 1, int $waiting = 1): void
    {
        $row = (object) ['id' => $id, 'event' => 1, 'uid' => $uid, 'places' => $places, 'waiting' => $waiting,
            'status' => 1, 'uregdate' => sprintf('2031-11-%02d 10:00:00', $id)];
        if ($this->priced) {
            $row->reference = JemRegistrationIdentity::generateRegistrationReference();
        }
        $this->db->insertObject('#__jem_register', $row);
    }
}
