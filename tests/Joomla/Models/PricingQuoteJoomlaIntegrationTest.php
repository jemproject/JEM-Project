<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class PricingQuoteJoomlaIntegrationTest extends JoomlaTestCase
{
    private DatabaseDriver $db;

    /** @var array<string,int[]> */
    private array $ids = array(
        'events' => array(),
        'venues' => array(),
        'taxes' => array(),
        'registers' => array(),
    );

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 after installing the package to verify Point 4E quotes.');
        }
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/classes/pricingquote.class.php';
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            foreach (array_reverse($this->ids['registers']) as $registerId) {
                $this->deleteWhere('#__jem_register_items', 'register_id', $registerId);
                $this->deleteWhere('#__jem_register_history', 'registration_id', $registerId);
                $this->deleteWhere('#__jem_register', 'id', $registerId);
            }
            foreach (array_reverse($this->ids['events']) as $eventId) {
                $this->deleteWhere('#__jem_event_prices', 'event_id', $eventId);
                $this->deleteWhere('#__jem_capacity_pools', 'event_id', $eventId);
                $this->deleteWhere('#__jem_events', 'id', $eventId);
            }
            foreach (array_reverse($this->ids['venues']) as $venueId) {
                $this->deleteWhere('#__jem_venues', 'id', $venueId);
            }
            foreach (array_reverse($this->ids['taxes']) as $taxId) {
                $this->deleteWhere('#__jem_tax_rates', 'id', $taxId);
            }
        }

        parent::tearDown();
    }

    public function testQuoteRecalculatesMixedTaxesAndExactRemainingCapacity(): void
    {
        $fixture = $this->createFixture();
        $service = new JemPricingQuoteService($this->db);
        $quote = $service->quote(
            $fixture['event_id'],
            array(
                array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2, 'amount' => '0.01'),
                array('event_price_id' => $fixture['child_price_id'], 'quantity' => 1, 'amount' => '9999.00'),
            ),
            $this->context($fixture)
        );

        self::assertSame(JemPricingQuoteService::SCHEMA, $quote['schema']);
        self::assertSame(2, $quote['pricing_revision']);
        self::assertSame('EUR', $quote['currency']);
        self::assertSame(3, $quote['quantity']);
        self::assertSame(6, $quote['event_used']);
        self::assertSame(11, $quote['event_remaining']);
        self::assertSame('25.28', $quote['subtotal_net']);
        self::assertSame('4.72', $quote['tax_total']);
        self::assertSame('30.00', $quote['grand_total']);
        self::assertCount(2, $quote['lines']);

        $lines = array_column($quote['lines'], null, 'code');
        self::assertSame('12.00', $lines['adult']['unit_gross']);
        self::assertSame('24.00', $lines['adult']['line_gross']);
        self::assertSame('21.00', $lines['adult']['tax_rate']);
        self::assertSame(4, $lines['adult']['pool_remaining']);
        self::assertSame(4, $lines['adult']['quota_remaining']);
        self::assertSame(18, $lines['adult']['conditions']['min_age']);

        self::assertSame('6.00', $lines['child']['unit_gross']);
        self::assertSame('6.00', $lines['child']['line_gross']);
        self::assertSame('10.00', $lines['child']['tax_rate']);
        self::assertSame(4, $lines['child']['pool_remaining']);
        self::assertSame(3, $lines['child']['quota_remaining']);
    }

    public function testQuoteRejectsStaleEligibilityWindowQuantityAndInventoryFailures(): void
    {
        $fixture = $this->createFixture();
        $service = new JemPricingQuoteService($this->db);
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2));

        $stale = $this->context($fixture);
        $stale['expectedPricingRevision'] = 1;
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $stale), 'stale revision');

        $this->assertRejected(
            fn () => $service->quote(
                $fixture['event_id'],
                array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => '1.5')),
                $this->context($fixture)
            ),
            'fractional quantity'
        );

        $withoutAccess = $this->context($fixture);
        $withoutAccess['accessLevels'] = array();
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $withoutAccess), 'access level');

        $withoutGroup = $this->context($fixture);
        $withoutGroup['userGroups'] = array();
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $withoutGroup), 'user group');

        $outsideWindow = $this->context($fixture);
        $outsideWindow['now'] = '2026-10-01 00:00:00';
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $outsideWindow), 'sale window');

        $this->assertRejected(
            fn () => $service->quote(
                $fixture['event_id'],
                array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 5)),
                $this->context($fixture)
            ),
            'quantity maximum'
        );

        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 3);
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $this->context($fixture)), 'price quota');
        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 8);

        $this->updateValue('#__jem_capacity_pools', $fixture['pool_id'], 'capacity', 4);
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $this->context($fixture)), 'pool capacity');
        $this->updateValue('#__jem_capacity_pools', $fixture['pool_id'], 'capacity', 10);

        $this->updateValue('#__jem_events', $fixture['event_id'], 'maxplaces', 6);
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $this->context($fixture)), 'event capacity');
    }

    public function testQuoteCalculatesTaxExcludedPricesFromStoredDefinitions(): void
    {
        $fixture = $this->createFixture();
        $this->updateValue('#__jem_events', $fixture['event_id'], 'prices_include_tax', 0);
        $service = new JemPricingQuoteService($this->db);
        $quote = $service->quote(
            $fixture['event_id'],
            array(
                array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2, 'grand_total' => '0.00'),
                array('event_price_id' => $fixture['child_price_id'], 'quantity' => 1, 'grand_total' => '0.00'),
            ),
            $this->context($fixture)
        );

        self::assertSame(0, $quote['prices_include_tax']);
        self::assertSame('30.00', $quote['subtotal_net']);
        self::assertSame('5.64', $quote['tax_total']);
        self::assertSame('35.64', $quote['grand_total']);
        $lines = array_column($quote['lines'], null, 'code');
        self::assertSame('14.52', $lines['adult']['unit_gross']);
        self::assertSame('29.04', $lines['adult']['line_gross']);
        self::assertSame('6.60', $lines['child']['line_gross']);
    }

    public function testLockedQuoteProvidesAnIdempotentAtomicBoundary(): void
    {
        $fixture = $this->createFixture();
        $service = new JemPricingQuoteService($this->db);
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1));
        $stored = array();
        $writes = 0;
        $operationReference = JemRegistrationIdentity::generateOperationReference();
        $lookup = static function (string $reference) use (&$stored): ?array {
            return $stored[$reference] ?? null;
        };
        $operation = static function (array $quote, string $reference) use (&$stored, &$writes): array {
            $writes++;
            $stored[$reference] = array(
                'operation_reference' => $reference,
                'pricing_revision' => $quote['pricing_revision'],
                'grand_total' => $quote['grand_total'],
            );

            return $stored[$reference];
        };

        $first = $service->withLockedQuote(
            $fixture['event_id'],
            $selection,
            $this->context($fixture),
            $operationReference,
            $operation,
            $lookup
        );
        $retry = $service->withLockedQuote(
            $fixture['event_id'],
            $selection,
            $this->context($fixture),
            $operationReference,
            $operation,
            $lookup
        );

        self::assertSame(1, $writes);
        self::assertSame($first, $retry);
        self::assertSame('12.00', $first['grand_total']);
        self::assertSame(2, $first['pricing_revision']);
    }

    public function testConcurrentQuotesCannotOversellPricePoolOrEventInventory(): void
    {
        $fixture = $this->createFixture();

        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 3);
        $this->assertConcurrentLimit($fixture, 'quota');
        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 8);

        $this->updateValue('#__jem_capacity_pools', $fixture['pool_id'], 'capacity', 4);
        $this->assertConcurrentLimit($fixture, 'pool');
        $this->updateValue('#__jem_capacity_pools', $fixture['pool_id'], 'capacity', 10);

        $this->updateValue('#__jem_events', $fixture['event_id'], 'maxplaces', 7);
        $this->assertConcurrentLimit($fixture, 'Event capacity');
    }

    /**
     * @return array{event_id:int,pool_id:int,adult_price_id:int,child_price_id:int,access_level_id:int,user_group_id:int}
     */
    private function createFixture(): array
    {
        $suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        $venue = (object) array(
            'venue' => 'PHPUnit quote venue ' . $suffix,
            'alias' => 'phpunit-quote-venue-' . $suffix,
            'country' => 'ES',
            'capacity' => 20,
            'published' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_venues', $venue, 'id');
        $venueId = (int) $venue->id;
        $this->ids['venues'][] = $venueId;

        $event = (object) array(
            'title' => 'PHPUnit quote event ' . $suffix,
            'alias' => 'phpunit-quote-event-' . $suffix,
            'introtext' => '',
            'fulltext' => '',
            'created_by_alias' => '',
            'metadata' => '',
            'dates' => '2026-10-15',
            'locid' => $venueId,
            'maxplaces' => 20,
            'reservedplaces' => 2,
            'pricing_mode' => 'multiple',
            'pricing_revision' => 2,
            'currency' => 'EUR',
            'prices_include_tax' => 1,
            'published' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_events', $event, 'id');
        $eventId = (int) $event->id;
        $this->ids['events'][] = $eventId;

        $standardTaxId = $this->insertTax($suffix . '-standard', 'standard', '21.00');
        $reducedTaxId = $this->insertTax($suffix . '-reduced', 'reduced', '10.00');

        $pool = (object) array(
            'event_id' => $eventId,
            'code' => 'general',
            'name' => 'General admission',
            'capacity' => 10,
            'published' => 1,
            'ordering' => 1,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_capacity_pools', $pool, 'id');
        $poolId = (int) $pool->id;
        $accessLevelId = $this->minimumId('#__viewlevels');
        $userGroupId = $this->minimumId('#__usergroups');

        $adultPriceId = $this->insertPrice(array(
            'event_id' => $eventId,
            'capacity_pool_id' => $poolId,
            'code' => 'adult',
            'name' => 'Adult',
            'amount' => '12.00',
            'tax_rate_id' => $standardTaxId,
            'quota' => 8,
            'min_quantity' => 1,
            'max_quantity' => 4,
            'available_from' => '2026-09-01 00:00:00',
            'available_until' => '2026-09-30 23:59:59',
            'min_age' => 18,
            'access_level_id' => $accessLevelId,
            'user_group_id' => $userGroupId,
            'verification_mode' => 'manual',
            'ordering' => 1,
        ));
        $childPriceId = $this->insertPrice(array(
            'event_id' => $eventId,
            'capacity_pool_id' => $poolId,
            'code' => 'child',
            'name' => 'Child',
            'amount' => '6.00',
            'tax_rate_id' => $reducedTaxId,
            'quota' => 5,
            'min_quantity' => 1,
            'max_quantity' => 3,
            'min_age' => 0,
            'max_age' => 17,
            'verification_mode' => 'declaration',
            'ordering' => 2,
        ));

        $registrationId = $this->insertRegistration($eventId, 3, 1, 0);
        $this->insertItem($registrationId, 1, 1, $adultPriceId, $poolId, 'adult', 2);
        $this->insertItem($registrationId, 1, 2, $childPriceId, $poolId, 'child', 1);
        $this->insertItem($registrationId, 2, 1, $adultPriceId, $poolId, 'adult-history', 20);

        $waitingId = $this->insertRegistration($eventId, 10, 1, 1);
        $this->insertItem($waitingId, 1, 1, $adultPriceId, $poolId, 'adult-waiting', 10);

        // Compatibility: an active pre-pricing registration still consumes
        // event capacity even though it has no commercial item rows.
        $this->insertRegistration($eventId, 1, 1, 0);

        return array(
            'event_id' => $eventId,
            'pool_id' => $poolId,
            'adult_price_id' => $adultPriceId,
            'child_price_id' => $childPriceId,
            'access_level_id' => $accessLevelId,
            'user_group_id' => $userGroupId,
        );
    }

    private function insertTax(string $code, string $type, string $rate): int
    {
        $tax = (object) array(
            'code' => 'phpunit-' . $code,
            'name' => 'PHPUnit ' . $type,
            'tax_type' => $type,
            'rate' => $rate,
            'country_code' => 'ES',
            'published' => 1,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_tax_rates', $tax, 'id');
        $taxId = (int) $tax->id;
        $this->ids['taxes'][] = $taxId;

        return $taxId;
    }

    private function insertPrice(array $values): int
    {
        $price = (object) array_merge(array(
            'description' => '',
            'quota' => null,
            'min_quantity' => 1,
            'max_quantity' => null,
            'available_from' => null,
            'available_until' => null,
            'min_age' => null,
            'max_age' => null,
            'access_level_id' => null,
            'user_group_id' => null,
            'verification_mode' => 'none',
            'published' => 1,
            'ordering' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        ), $values);
        $this->db->insertObject('#__jem_event_prices', $price, 'id');

        return (int) $price->id;
    }

    private function insertRegistration(int $eventId, int $places, int $status, int $waiting): int
    {
        $reference = JemRegistrationIdentity::generateRegistrationReference();
        $registration = (object) array(
            'event' => $eventId,
            'uid' => -random_int(1000, 999999999),
            'places' => $places,
            'uregdate' => gmdate('Y-m-d H:i:s'),
            'uip' => '127.0.0.1',
            'waiting' => $waiting,
            'status' => $status,
            'comment' => '',
            'reference' => $reference,
            'created' => gmdate('Y-m-d H:i:s'),
            'modified' => gmdate('Y-m-d H:i:s'),
            'revision' => 1,
            'pricing_mode' => 'multiple',
            'currency' => 'EUR',
        );
        $this->db->insertObject('#__jem_register', $registration, 'id');
        $registrationId = (int) $registration->id;
        $this->ids['registers'][] = $registrationId;

        return $registrationId;
    }

    private function insertItem(
        int $registerId,
        int $revision,
        int $lineNumber,
        int $priceId,
        int $poolId,
        string $code,
        int $quantity
    ): void {
        $item = (object) array(
            'register_id' => $registerId,
            'registration_revision' => $revision,
            'line_number' => $lineNumber,
            'line_kind' => 'admission',
            'event_price_id' => $priceId,
            'capacity_pool_id' => $poolId,
            'item_code' => $code,
            'item_name' => $code,
            'quantity' => $quantity,
            'currency' => 'EUR',
            'price_includes_tax' => 1,
            'unit_net' => '0.00',
            'unit_tax' => '0.00',
            'unit_gross' => '0.00',
            'line_net' => '0.00',
            'line_tax' => '0.00',
            'line_gross' => '0.00',
            'tax_code' => '',
            'tax_name' => '',
            'tax_type' => '',
            'tax_rate' => '0.00',
            'calculation_mode' => '',
            'calculation_basis' => '',
            'created' => gmdate('Y-m-d H:i:s'),
        );
        $this->db->insertObject('#__jem_register_items', $item);
    }

    private function context(array $fixture): array
    {
        return array(
            'expectedPricingRevision' => 2,
            'accessLevels' => array($fixture['access_level_id']),
            'userGroups' => array($fixture['user_group_id']),
            'now' => '2026-09-15 12:00:00',
        );
    }

    private function assertConcurrentLimit(array $fixture, string $expectedMessage): void
    {
        $worker = JEM_TEST_ROOT . '/tests/Support/pricing_quote_concurrent_worker.php';
        self::assertFileExists($worker);
        $command = array(
            PHP_BINARY,
            $worker,
            self::joomlaRoot(),
            (string) $fixture['event_id'],
            (string) $fixture['adult_price_id'],
            (string) $fixture['access_level_id'],
            (string) $fixture['user_group_id'],
            '2026-09-15 12:00:00',
        );
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $workers = array();
        for ($index = 0; $index < 2; $index++) {
            $process = proc_open($command, $descriptors, $pipes, JEM_TEST_ROOT, null, array('bypass_shell' => true));
            self::assertIsResource($process);
            fclose($pipes[0]);
            $workers[] = array($process, $pipes[1], $pipes[2]);
        }

        $results = array();
        foreach ($workers as [$process, $stdoutPipe, $stderrPipe]) {
            $stdout = stream_get_contents($stdoutPipe);
            $stderr = stream_get_contents($stderrPipe);
            fclose($stdoutPipe);
            fclose($stderrPipe);
            $exitCode = proc_close($process);
            self::assertSame(0, $exitCode, $stderr ?: $stdout);
            self::assertMatchesRegularExpression('/(\{[^\r\n]+\})\s*$/', $stdout);
            preg_match('/(\{[^\r\n]+\})\s*$/', $stdout, $matches);
            $result = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            $results[] = $result;
            if (($result['status'] ?? '') === 'confirmed') {
                $this->ids['registers'][] = (int) $result['register_id'];
            }
        }

        $statuses = array_count_values(array_column($results, 'status'));
        self::assertSame(1, $statuses['confirmed'] ?? 0, 'Exactly one concurrent quote must be confirmed.');
        self::assertSame(1, $statuses['rejected'] ?? 0, 'Exactly one concurrent quote must be rejected.');
        $rejected = current(array_filter($results, static fn (array $result): bool => $result['status'] === 'rejected'));
        self::assertStringContainsString(strtolower($expectedMessage), strtolower((string) $rejected['message']));

        foreach ($results as $result) {
            if (($result['status'] ?? '') !== 'confirmed') {
                continue;
            }
            $registerId = (int) $result['register_id'];
            $this->deleteWhere('#__jem_register_items', 'register_id', $registerId);
            $this->deleteWhere('#__jem_register', 'id', $registerId);
            $this->ids['registers'] = array_values(array_diff($this->ids['registers'], array($registerId)));
        }
    }

    private function assertRejected(callable $operation, string $case): void
    {
        try {
            $operation();
            self::fail($case . ' must be rejected.');
        } catch (InvalidArgumentException|RuntimeException $error) {
            self::assertNotSame('', trim($error->getMessage()), $case);
        }
    }

    private function updateValue(string $table, int $id, string $column, int $value): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName($table))
            ->set($this->db->quoteName($column) . ' = ' . $value)
            ->where($this->db->quoteName('id') . ' = ' . $id);
        $this->db->setQuery($query)->execute();
    }

    private function minimumId(string $table): int
    {
        $this->db->setQuery('SELECT MIN(' . $this->db->quoteName('id') . ') FROM ' . $this->db->quoteName($table));
        $id = (int) $this->db->loadResult();
        self::assertGreaterThan(0, $id);

        return $id;
    }

    private function deleteWhere(string $table, string $column, int $value): void
    {
        if ($value < 1) {
            return;
        }
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName($table))
            ->where($this->db->quoteName($column) . ' = ' . $value);
        $this->db->setQuery($query)->execute();
    }
}
