<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Verify event suspension, waiting lists and booking writes against disposable SQL tables. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventRegistrationStatusTest extends TestCase
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
        $tables = ['jem_events', 'jem_register', 'jem_config'];
        if ($this->priced) {
            $tables = array_merge($tables, ['jem_register_history', 'jem_register_items',
                'jem_register_capacity_allocations', 'jem_event_prices', 'jem_capacity_pools']);
            require_once JEM_TEST_ROOT . '/site/classes/pricedregistration.class.php';
        }
        require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/waitinglistpromotion.class.php';
        $install = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        foreach ($tables as $table) {
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__' . $table . '`.*?;/s', $install, $match));
            $this->sql($match[0]);
        }
        $this->sql('CREATE TABLE #__users (id INT PRIMARY KEY, block INT DEFAULT 0, activation VARCHAR(100) DEFAULT \'\') ENGINE=InnoDB');
        $this->sql('INSERT INTO #__users (id) VALUES (100),(101)');
        $this->sql("INSERT INTO #__jem_config (keyname,value) VALUES ('registration_schema_ready','1')");
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


    /** @return iterable<string,array{string,string,bool}> All statuses, promotion modes and force settings. */
    public static function promotionCases(): iterable
    {
        foreach (['scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online'] as $status) {
            foreach (['automatic', 'manual'] as $mode) {
                foreach ([false, true] as $force) {
                    yield "$status/$mode/" . (int) $force => [$status, $mode, $force];
                }
            }
        }
    }

    /**
     * @param string $status Event lifecycle.
     * @param string $mode Promotion source.
     * @param bool $force Capacity override must never override event suspension.
     * @return void Check database writes, preserved queues and post-commit notification dispatch.
     */
    #[DataProvider('promotionCases')]
    public function testPromotionsRespectEventLifecycle(string $status, string $mode, bool $force): void
    {
        $this->sql("UPDATE #__jem_events SET event_status='$status' WHERE id=1");
        $this->registration(1, 100);
        $result = $this->promote(['mode' => $mode, 'registrationIds' => [1], 'force' => $force, 'notify' => true]);
        $blocked = in_array($status, ['cancelled', 'postponed'], true);
        self::assertSame($mode === 'automatic' || !$blocked, $result->success, $result->reason);
        self::assertSame($blocked ? [] : [1], $result->promotedIds);
        self::assertSame($blocked ? 1 : 0, $this->waiting(1));
        self::assertSame($blocked, empty(JemFactory::$notifications));
        if ($blocked) {
            self::assertSame('event_' . $status, $result->reason);
            self::assertSame(1, $result->waitingAfter);
        }
        self::assertSame(0, (int) $this->db->setQuery('SELECT @@in_transaction')->loadResult());
    }

    /** @return iterable<string,array{string,string}> Suspended statuses and ways to add a booking. */
    public static function blockedChanges(): iterable
    {
        foreach (['cancelled', 'postponed'] as $status) {
            foreach (['new', 'new_waiting', 'increase', 'reactivate', 'promote'] as $change) {
                yield "$status/$change" => [$status, $change];
            }
        }
    }

    /**
     * @param string $status Event lifecycle.
     * @param string $change Attempted allocation.
     * @return void Exercise the shared writer used by FE, BE and atomic series bookings.
     */
    #[DataProvider('blockedChanges')]
    public function testWriterRejectsNewPlacesWithoutChangingExistingData(string $status, string $change): void
    {
        $this->sql("UPDATE #__jem_events SET event_status='$status', maxplaces=10 WHERE id=1");
        $data = (object) ['event' => 1, 'uid' => 100, 'status' => 1, 'waiting' => 0, 'places' => 2];
        if (!in_array($change, ['new', 'new_waiting'], true)) {
            $this->registration(1, 100, 2, $change === 'promote' ? 1 : 0);
            $data->id = 1;
        }
        if ($change === 'new_waiting') { $data->waiting = 1; }
        if ($change === 'increase') { $data->places = 3; }
        if ($change === 'reactivate') { $this->sql('UPDATE #__jem_register SET status=-1 WHERE id=1'); }
        $before = $this->db->setQuery('SELECT * FROM #__jem_register')->loadAssocList();
        try {
            (new JemRegistrationService($this->db))->save($data, ['respectPlaces' => true]);
            self::fail('A suspended event accepted a booking.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Event ID 1:', $e->getMessage());
            self::assertStringContainsString($status, $e->getMessage());
        }
        self::assertSame($before, $this->db->setQuery('SELECT * FROM #__jem_register')->loadAssocList());
        self::assertSame([], JemFactory::$notifications);
        self::assertSame(0, (int) $this->db->setQuery('SELECT @@in_transaction')->loadResult());
    }

    /** @return iterable<string,array{string}> States that suspend new reservations. */
    public static function suspendedStatuses(): iterable
    {
        yield 'cancelled' => ['cancelled'];
        yield 'postponed' => ['postponed'];
    }

    /**
     * @param string $status Event lifecycle.
     * @return void Notes, partial releases and full cancellation preserve identity and history.
     */
    #[DataProvider('suspendedStatuses')]
    public function testExistingBookingCanBeAnnotatedReducedAndCancelled(string $status): void
    {
        $this->sql("UPDATE #__jem_events SET event_status='$status', maxplaces=10 WHERE id=1");
        $this->registration(1, 100, 3, 0);
        $service = new JemRegistrationService($this->db);
        $note = $service->save((object) ['id' => 1, 'event' => 1, 'comment' => 'Organiser note']);
        self::assertSame(3, (int) $note->after->places);
        $release = $service->save((object) ['id' => 1, 'event' => 1, 'places' => 1]);
        self::assertSame(1, (int) $release->after->places);
        $cancel = $service->save((object) ['id' => 1, 'event' => 1, 'status' => -1]);
        self::assertSame(-1, (int) $cancel->after->status);
        self::assertSame('Organiser note', $cancel->after->comment);
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
        if ($this->priced) {
            self::assertSame(3, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
        }
    }

    /**
     * @param string $status Suspended occurrence lifecycle.
     * @return void A rejected occurrence rolls back the whole series without a partial booking.
     */
    #[DataProvider('suspendedStatuses')]
    public function testSeriesRollbackWhenAnotherOccurrenceIsSuspended(string $status): void
    {
        $event = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=1')->loadObject();
        $event->id = 2;
        $event->event_status = $status;
        $this->db->insertObject('#__jem_events', $event);
        $rows = array_map(static fn ($id) => (object) ['event' => $id, 'uid' => 100, 'status' => 1, 'places' => 1], [1, 2]);
        try {
            (new JemRegistrationService($this->db))->saveMany($rows);
            self::fail('A mixed series was partially accepted.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Event ID 2:', $e->getMessage());
        }
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
        if ($this->priced) {
            self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
        }
    }

    /**
     * @param string $status Event lifecycle.
     * @return void Real priced quotes load the status from SQL and reject suspended events.
     */
    #[DataProvider('suspendedStatuses')]
    public function testPricedQuoteRejectsSuspendedEvent(string $status): void
    {
        if (!$this->priced) { self::markTestSkipped('Pricing belongs to JEM 5.1.0.'); }
        $this->sql("UPDATE #__jem_events SET event_status='$status', pricing_mode='priced', pricing_revision=1, currency='EUR', pricing_country_code='ES', published=1, access=0, registra=1 WHERE id=1");
        $context = JemPricingQuoteContext::fromIdentity((object) ['id' => 100], 1);
        try {
            (new JemPricedRegistrationService($this->db))->quote(1, [['event_price_id' => 1, 'quantity' => 1]], $context);
            self::fail('A suspended event received a priced quote.');
        } catch (JemPricingQuoteException $e) {
            self::assertSame('registration_closed', $e->getReasonCode());
            self::assertStringContainsString($status, $e->getMessage());
        }
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
    }


    /**
     * @param string $status Suspended lifecycle value.
     * @return void Execute the actual BE bulk model; a mixed selection must not promote a waiting booking.
     */
    #[DataProvider('suspendedStatuses')]
    public function testBackendBulkStatusCannotPromoteButCanCancel(string $status): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/event_registration_status_environment.php';
        require_once JEM_TEST_ROOT . '/admin/models/attendee.php';
        $this->sql("UPDATE #__jem_events SET event_status='$status' WHERE id=1");
        $this->registration(1, 100, 1, 0);
        $this->registration(2, 101, 1, 1);
        $model = (new ReflectionClass(JemModelAttendee::class))->newInstanceWithoutConstructor();
        self::assertFalse($model->setStatus([1, 2], 1, 1));
        self::assertStringContainsString($status, $model->getError());
        self::assertSame(0, $this->waiting(1));
        self::assertSame(1, $this->waiting(2));
        self::assertTrue($model->setStatus([1, 2], -1, 1));
        self::assertSame([-1, -1], array_map('intval', $this->db->setQuery('SELECT status FROM #__jem_register ORDER BY id')->loadColumn()));
        self::assertSame(0, (int) $this->db->setQuery('SELECT @@in_transaction')->loadResult());
    }

    /**
     * @param string $status Suspended lifecycle value.
     * @return void Retain priced orders and allow a BE comment without issuing a new quote or changing admissions.
     */
    #[DataProvider('suspendedStatuses')]
    public function testPricedCommentRemainsEditableAndExtraAdmissionsAreRejected(string $status): void
    {
        if (!$this->priced) { self::markTestSkipped('Pricing belongs to JEM 5.1.0.'); }
        require_once JEM_TEST_ROOT . '/tests/Support/event_registration_status_environment.php';
        require_once JEM_TEST_ROOT . '/admin/models/attendee.php';
        $this->sql("UPDATE #__jem_events SET event_status='$status', maxplaces=10, pricing_mode='priced', pricing_revision=1, pricing_country_code='ES', currency='EUR', published=1, access=0, registra=1 WHERE id=1");
        $this->registration(1, 100, 2, 0);
        $this->sql("UPDATE #__jem_register SET pricing_mode='priced', currency='EUR', subtotal_net=20, grand_total=20 WHERE id=1");
        $this->sql("INSERT INTO #__jem_register_items (register_id,registration_revision,line_number,line_kind,event_price_id,item_code,item_name,currency,quantity,unit_net,unit_gross,line_net,line_tax,line_gross) VALUES (1,1,1,'admission',1,'standard','Standard','EUR',2,10,10,20,0,20)");
        $model = (new ReflectionClass(JemModelAttendee::class))->newInstanceWithoutConstructor();
        $data = ['id' => 1, 'event' => 1, 'uid' => 100, 'status' => 1, 'admissions' => [1 => 2], 'comment' => 'Keep booking until a new date is confirmed'];
        $result = $model->store($data);
        self::assertIsObject($result, $model->getError());
        self::assertSame($data['comment'], $result->comment);
        self::assertSame(2, (int) $result->places);
        self::assertSame('20.00', (string) $result->grand_total);
        $items = (new JemRegistrationService($this->db))->commercialItems(1, (int) $result->revision);
        self::assertCount(1, $items);
        self::assertSame(2, (int) $items[0]->quantity);
        $data['admissions'] = [1 => 3];
        self::assertFalse($model->store($data));
        self::assertStringContainsString($status, $model->getError());
        self::assertSame(2, (int) $this->db->setQuery('SELECT places FROM #__jem_register WHERE id=1')->loadResult());
    }

    /**
     * @param string $status Suspended lifecycle value.
     * @return void Suppress priced promotion, then resume the same waiting order after explicit rescheduling.
     */
    #[DataProvider('suspendedStatuses')]
    public function testPricedWaitingOrderSurvivesSuspensionAndRescheduling(string $status): void
    {
        if (!$this->priced) { self::markTestSkipped('Pricing belongs to JEM 5.1.0.'); }
        $this->sql("UPDATE #__jem_events SET event_status='$status', pricing_mode='priced' WHERE id=1");
        $this->registration(1, 100);
        $this->sql("UPDATE #__jem_register SET pricing_mode='priced', currency='EUR', subtotal_net=10, grand_total=10 WHERE id=1");
        $this->sql("INSERT INTO #__jem_capacity_pools (id,event_id,code,name,capacity) VALUES (1,1,'test','Test',1)");
        $this->sql("INSERT INTO #__jem_event_prices (id,event_id,code,name,quota) VALUES (1,1,'test','Test',1)");
        $this->sql("INSERT INTO #__jem_register_items (register_id,registration_revision,line_number,line_kind,event_price_id,capacity_pool_id,item_code,item_name,currency,quantity,unit_net,unit_gross,line_net,line_tax,line_gross) VALUES (1,1,1,'admission',1,1,'test','Test','EUR',1,10,10,10,0,10)");
        self::assertSame([], $this->promote(['notify' => true])->promotedIds);
        self::assertSame([], JemFactory::$notifications);
        self::assertSame(1, $this->waiting(1));
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
        $this->sql("UPDATE #__jem_events SET event_status='rescheduled' WHERE id=1");
        self::assertSame([1], $this->promote(['notify' => true])->promotedIds);
        self::assertSame(0, $this->waiting(1));
        self::assertNotEmpty(JemFactory::$notifications);
        self::assertSame('10.00', $this->db->setQuery('SELECT grand_total FROM #__jem_register WHERE id=1')->loadResult());
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
