<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real SQL and real relocation/writer services, confined to connection-local temporary tables. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RegistrationRelocationTransactionTest extends TestCase
{
    private object $db;
    private object $actor;
    private object $owner;

    /** @return void Build a two-area event from this branch's installation schema. */
    protected function setUp(): void
    {
        $joomla = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!$joomla || getenv('JEM_TEST_TEMP_TABLES') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_TEMP_TABLES=1.');
        }
        $loader = require $joomla . '/libraries/vendor/autoload.php';
        $loader->addPsr4('Joomla\\CMS\\', $joomla . '/libraries/src');
        require_once $joomla . '/configuration.php';
        require_once JEM_TEST_ROOT . '/site/classes/registrationrelocation.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/featurepolicy.class.php';
        $config = new JConfig();
        $this->db = (new DatabaseFactory())->getDriver('mysqli', ['host' => $config->host,
            'user' => $config->user, 'password' => $config->password, 'database' => $config->db,
            'prefix' => 'qmove_' . bin2hex(random_bytes(4)) . '_']);
        $schema = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        foreach (['events', 'register', 'config', 'register_history', 'register_capacity_allocations',
            'ticket_operations', 'register_items', 'event_prices', 'capacity_pools', 'tax_rates'] as $name) {
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_' . $name . '`\s*\(.*?\)\s*ENGINE=[^;]+;/s', $schema, $matches));
            $this->db->setQuery(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $matches[0]))->execute();
        }
        $this->insert('config', ['keyname' => 'registration_schema_ready', 'value' => '1']);
        $this->insert('config', ['keyname' => 'operating_profile', 'value' => 'ticketing']);
        $snapshot = ['schema' => 'jem-venue-capacity/v1', 'country_code' => 'ES', 'spaces' => [[
            'name' => 'Hall', 'code' => 'hall', 'layout' => ['id' => 1, 'revision' => 1, 'capacity' => 20],
            'capacity_areas' => [
                ['id' => 1, 'code' => 'A', 'name' => 'Area A', 'capacity' => 10, 'published' => 1],
                ['id' => 2, 'code' => 'B', 'name' => 'Area B', 'capacity' => 10, 'published' => 1],
            ],
        ]]];
        $this->insert('events', ['id' => 7, 'title' => 'Relocation test', 'alias' => 'relocation', 'dates' => '2099-12-01',
            'maxplaces' => 20, 'minbookeduser' => 1, 'maxbookeduser' => 5, 'published' => 1, 'waitinglist' => 1,
            'event_status' => 'scheduled', 'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '{}',
            'capacity_mode' => 'areas', 'venue_snapshot' => json_encode($snapshot), 'registra' => 1, 'access' => 1,
            'pricing_revision' => 1, 'currency' => 'EUR', 'pricing_country_code' => 'ES']);
        $this->insert('register', ['id' => 41, 'event' => 7, 'uid' => 15, 'places' => 1, 'status' => 1,
            'waiting' => 0, 'uregdate' => '2026-11-12 10:00:00', 'uip' => '', 'comment' => '',
            'reference' => JemRegistrationIdentity::generateRegistrationReference(), 'revision' => 1]);
        $this->insert('register_capacity_allocations', ['register_id' => 41, 'registration_revision' => 1,
            'event_id' => 7, 'venue_capacity_area_id' => 1,
            'venue_layout_id' => 1, 'venue_layout_revision' => 1, 'area_code' => 'A', 'area_name' => 'Area A',
            'space_code' => 'hall', 'space_name' => 'Hall', 'quantity' => 1]);
        $this->actor = new class {
            public int $id = 99;
            public bool $finance = true;
            public bool $manage = true;
            public function authorise($permission, $asset): bool { return $permission === 'jem.tickets.pay' ? $this->finance : $this->manage; }
        };
        $this->owner = $this->createMock(User::class);
        $this->owner->id = 15;
        $this->owner->method('getAuthorisedViewLevels')->willReturn([1]);
        $this->owner->method('getAuthorisedGroups')->willReturn([2]);
        $factory = $this->createMock(UserFactoryInterface::class);
        $factory->method('loadUserById')->willReturn($this->owner);
        $container = new Joomla\DI\Container();
        $container->set(UserFactoryInterface::class, $factory, true);
        $container->set('DatabaseDriver', $this->db, true);
        $property = new ReflectionProperty(Factory::class, 'container');
        $property->setValue(null, $container);
    }

    /** @return void Move and enlarge one free reservation without changing identity or inventing money. */
    public function testFreeMoveIsAtomicAuditedAndIdempotent(): void
    {
        $service = new JemRegistrationRelocation($this->db);
        $input = ['areas' => ['area:1' => 0, 'area:2' => 2], 'reason' => 'Holder requested two places together'];
        $before = $this->row();
        $review = $service->preview(41, $input, $this->actor);
        self::assertSame(2, $review['new_places']);
        self::assertSame(0, $review['financial']['refund']);
        self::assertEquals($before, $this->row());
        $operation = JemRegistrationIdentity::generateOperationReference();
        $result = $service->confirm(41, $input, $this->actor, $review['fingerprint'], $operation);
        self::assertSame(2, (int) $result->after->revision);
        self::assertSame(2, (int) $result->after->places);
        self::assertSame($before->reference, $result->after->reference);
        $history = $this->db->setQuery('SELECT * FROM #__jem_register_history')->loadObject();
        self::assertSame('relocated', $history->action);
        self::assertSame('99', (string) $history->actor_user_id);
        self::assertSame($review['reason'], json_decode($history->change_snapshot, true)['reason']);
        self::assertTrue($service->confirm(41, $input, $this->actor, $review['fingerprint'], $operation)->replayed);
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_ticket_operations')->loadResult());
    }

    public function testStaleAndFullDestinationLeaveEverythingUntouched(): void
    {
        $service = new JemRegistrationRelocation($this->db);
        $input = ['areas' => ['area:2' => 2], 'reason' => 'Move together'];
        $review = $service->preview(41, $input, $this->actor);
        $this->db->setQuery('UPDATE #__jem_register SET revision=2 WHERE id=41')->execute();
        $this->rejected(fn () => $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference()), 'COM_JEM_RELOCATION_REVIEW_CHANGED');
        $this->db->setQuery('UPDATE #__jem_register SET revision=1 WHERE id=41')->execute();
        $this->insert('register', ['id' => 42, 'event' => 7, 'uid' => 16, 'places' => 10, 'status' => 1,
            'waiting' => 0, 'uregdate' => '2026-11-12 10:00:00', 'uip' => '', 'comment' => '',
            'reference' => JemRegistrationIdentity::generateRegistrationReference(), 'revision' => 1]);
        $this->insert('register_capacity_allocations', ['register_id' => 42, 'registration_revision' => 1,
            'event_id' => 7, 'venue_capacity_area_id' => 2, 'venue_layout_id' => 1,
            'venue_layout_revision' => 1, 'area_code' => 'B', 'area_name' => 'Area B', 'space_code' => 'hall',
            'space_name' => 'Hall', 'quantity' => 10]);
        $before = $this->row();
        $this->rejected(fn () => $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference()), 'COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE');
        self::assertEquals($before, $this->row());
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
    }

    public function testEntryAndPermissionsBlockRelocation(): void
    {
        $service = new JemRegistrationRelocation($this->db);
        $input = ['areas' => ['area:2' => 1], 'reason' => 'Move'];
        $this->actor->manage = false;
        $this->rejected(fn () => $service->preview(41, $input, $this->actor), 'JERROR_ALERTNOAUTHOR');
        $this->actor->manage = true;
        $this->ledger('admission', null, 1);
        $this->rejected(fn () => $service->preview(41, $input, $this->actor), 'COM_JEM_RELOCATION_ALREADY_ENTERED');
    }

    public function testPaidPromotionPreservesMoneyAndAdjustedTerms(): void
    {
        $this->priced();
        $this->ledger('payment', '10.00');
        $service = new JemRegistrationRelocation($this->db);
        $input = ['admissions' => [2 => 2], 'reason' => 'Two seats together', 'choice' => 'promotion'];
        $this->actor->finance = false;
        $this->rejected(fn () => $service->preview(41, $input, $this->actor), 'COM_JEM_RELOCATION_FINANCE_PERMISSION');
        $this->actor->finance = true;
        $review = $service->preview(41, $input, $this->actor);
        self::assertSame(3000, $review['financial']['quoted']);
        self::assertSame(1000, $review['financial']['total']);
        $result = $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference());
        self::assertSame('10.00', $result->after->grand_total);
        self::assertSame('paid', $result->after->payment_state);
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_ticket_operations')->loadResult());
        $priced = new JemPricedRegistrationService($this->db);
        $quote = $priced->quote(7, [['event_price_id' => 2, 'quantity' => 2]], JemPricingQuoteContext::fromIdentity($this->owner, 1, 41));
        self::assertSame('10.00', $quote['grand_total']);
        self::assertCount(2, $priced->quoteItems($quote));
    }

    public function testPaidReductionCreatesPendingRefundNotARefundPayment(): void
    {
        $this->priced();
        $this->ledger('payment', '10.00');
        $this->db->setQuery("UPDATE #__jem_event_prices SET amount='5.00' WHERE id=2")->execute();
        $service = new JemRegistrationRelocation($this->db);
        $input = ['admissions' => [2 => 1], 'reason' => 'Cheaper area'];
        $review = $service->preview(41, $input, $this->actor);
        self::assertSame(500, $review['financial']['refund']);
        $result = $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference());
        self::assertSame('refund_pending', $result->after->payment_state);
        self::assertSame('5.00', $result->after->grand_total);
        self::assertSame('10.00', $this->db->setQuery('SELECT amount FROM #__jem_ticket_operations')->loadResult());
    }

    /** @return void Acceptance and an actual collected surplus are required before retaining money. */
    public function testAcceptedRetentionPreservesPaymentWithoutRefund(): void
    {
        $this->priced();
        $this->ledger('payment', '10.00');
        $this->db->setQuery("UPDATE #__jem_event_prices SET amount='5.00' WHERE id=2")->execute();
        $service = new JemRegistrationRelocation($this->db);
        $input = ['admissions' => [2 => 1], 'reason' => 'Holder agreed to consolidate', 'choice' => 'consolidation_refund'];
        $this->rejected(fn () => $service->preview(41, $input, $this->actor), 'COM_JEM_RELOCATION_ACCEPT_REQUIRED');
        $input['accepted'] = true;
        $review = $service->preview(41, $input, $this->actor);
        self::assertSame(500, $review['financial']['adjustment']);
        $result = $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference());
        self::assertSame('10.00', $result->after->grand_total);
        self::assertSame('paid', $result->after->payment_state);
        self::assertSame(0, $result->snapshot['financial']['refund']);
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_ticket_operations')->loadResult());
    }

    /** @return void A payment or a destination-price change invalidates an outstanding review. */
    public function testPaymentAndPriceChangesRequireFreshReview(): void
    {
        $this->priced();
        $service = new JemRegistrationRelocation($this->db);
        $input = ['admissions' => [2 => 1], 'reason' => 'Move'];
        $review = $service->preview(41, $input, $this->actor);
        $this->ledger('payment', '10.00');
        $this->rejected(fn () => $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference()), 'COM_JEM_RELOCATION_REVIEW_CHANGED');
        $review = $service->preview(41, $input, $this->actor);
        $this->db->setQuery("UPDATE #__jem_event_prices SET amount='16.00' WHERE id=2")->execute();
        $this->rejected(fn () => $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference()), 'COM_JEM_RELOCATION_REVIEW_CHANGED');
        self::assertSame(1, (int) $this->row()->revision);
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
    }

    /** @return void A failed history write must roll back the reservation, commercial revision and areas. */
    public function testAuditFailureRollsBackAndSchemaRepairIsRepeatable(): void
    {
        $this->priced();
        $service = new JemRegistrationRelocation($this->db);
        $input = ['admissions' => [2 => 2], 'reason' => 'Move together'];
        $review = $service->preview(41, $input, $this->actor);
        $before = $this->row();
        $this->db->setQuery("SET SESSION sql_mode='STRICT_ALL_TABLES'")->execute();
        $this->db->setQuery('ALTER TABLE #__jem_register_history MODIFY change_snapshot VARCHAR(1) NULL')->execute();
        try {
            $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference());
            self::fail('Failed audit write must prevent the commit');
        } catch (Joomla\Database\Exception\ExecutionFailureException $error) {
            self::assertStringContainsString('change_snapshot', $error->getMessage());
        }
        self::assertEquals($before, $this->row());
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_items')->loadResult());
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_capacity_allocations')->loadResult());
        $this->db->setQuery('ALTER TABLE #__jem_register_history DROP COLUMN change_snapshot')->execute();
        $this->rejected(fn () => $service->preview(41, $input, $this->actor), 'COM_JEM_RELOCATION_SCHEMA_REQUIRED');
        JemRegistrationRelocation::ensureSchema($this->db);
        JemRegistrationRelocation::ensureSchema($this->db);
        $result = $service->confirm(41, $input, $this->actor, $review['fingerprint'], JemRegistrationIdentity::generateOperationReference());
        self::assertSame(2, (int) $result->after->revision);
    }

    public function testOrdinarySaveRequiresReviewOnlyForAllocationChanges(): void
    {
        JemRegistrationRelocation::requireReview($this->db, $this->row(), 1, ['area:1' => 1, 'area:2' => 0]);
        JemRegistrationRelocation::requireReview($this->db, $this->row(), -1, []);
        $this->rejected(fn () => JemRegistrationRelocation::requireReview($this->db, $this->row(), 1, ['area:2' => 1]), 'COM_JEM_RELOCATION_REVIEW_REQUIRED');
    }

    /** @return void Add valid priced admissions to the existing test reservation. */
    private function priced(): void
    {
        $this->db->setQuery("UPDATE #__jem_events SET pricing_mode='multiple' WHERE id=7")->execute();
        $this->db->setQuery("UPDATE #__jem_register SET pricing_mode='multiple', currency='EUR', subtotal_net='10.00', tax_total='0.00', grand_total='10.00' WHERE id=41")->execute();
        $this->insert('tax_rates', ['id' => 1, 'code' => 'exempt', 'name' => 'Exempt', 'tax_type' => 'exempt', 'rate' => '0.00', 'country_code' => 'ES']);
        foreach ([1 => '10.00', 2 => '15.00'] as $id => $amount) {
            $this->insert('capacity_pools', ['id' => $id, 'event_id' => 7, 'venue_capacity_area_id' => $id,
                'venue_layout_id' => 1, 'code' => 'area-' . $id, 'name' => 'Area ' . $id, 'capacity' => 10]);
            $this->insert('event_prices', ['id' => $id, 'event_id' => 7, 'capacity_pool_id' => $id,
                'code' => 'price-' . $id, 'name' => 'Price ' . $id, 'amount' => $amount, 'tax_rate_id' => 1]);
        }
        $this->insert('register_items', ['register_id' => 41, 'registration_revision' => 1, 'line_number' => 1,
            'line_kind' => 'admission', 'event_price_id' => 1, 'capacity_pool_id' => 1, 'item_code' => 'price-1', 'item_name' => 'Price 1',
            'quantity' => 1, 'currency' => 'EUR', 'unit_net' => '10.00', 'unit_gross' => '10.00', 'line_net' => '10.00',
            'line_gross' => '10.00', 'tax_type' => 'exempt', 'tax_rate' => '0.00']);
    }

    /** @param string $action Ledger operation. @param string|null $amount Collected amount. @param int $quantity Admissions. @return void */
    private function ledger(string $action, ?string $amount, int $quantity = 0): void
    {
        $this->insert('ticket_operations', ['operation_reference' => JemRegistrationIdentity::generateOperationReference(),
            'registration_id' => 41, 'registration_reference' => $this->row()->reference, 'registration_revision' => 1,
            'event_id' => 7, 'event_title' => 'Relocation test', 'action' => $action, 'amount' => $amount,
            'currency' => $amount === null ? '' : 'EUR', 'quantity' => $quantity, 'allocations' => '{}',
            'fingerprint' => str_repeat('a', 64), 'actor_user_id' => 99, 'occurred' => '2026-11-12 10:00:00']);
    }

    /** @param callable $action Expected failing action. @param string $message Expected failure. @return void */
    private function rejected(callable $action, string $message): void
    {
        try { $action(); self::fail('Expected rejection'); } catch (DomainException $error) { self::assertSame($message, $error->getMessage()); }
    }

    /** @return object Current test reservation. */
    private function row(): object { return $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=41')->loadObject(); }
    /** @param string $table Test table suffix. @param array $data Fixture fields. @return void */
    private function insert(string $table, array $data): void { $row = (object) $data; $this->db->insertObject('#__jem_' . $table, $row); }
}

