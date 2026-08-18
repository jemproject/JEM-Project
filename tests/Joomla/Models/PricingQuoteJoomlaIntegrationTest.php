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
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JPATH_SITE . '/components/com_jem/classes/pricingquote.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/pricedregistration.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/waitinglistpromotion.class.php';
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
        $service = $this->service();
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
        self::assertSame('available', $quote['inventory_state']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $quote['quote_fingerprint']);
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
        $service = $this->service();
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2));

        $stale = $this->context($fixture, 1);
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $stale), 'stale revision');

        $this->assertRejected(
            fn () => $service->quote(
                $fixture['event_id'],
                array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => '1.5')),
                $this->context($fixture)
            ),
            'fractional quantity'
        );

        $withoutAccess = $this->context($fixture, 2, false, true);
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $withoutAccess), 'access level');

        $withoutGroup = $this->context($fixture, 2, true, false);
        $this->assertRejected(fn () => $service->quote($fixture['event_id'], $selection, $withoutGroup), 'user group');

        $this->assertRejected(
            fn () => $this->service('2026-10-01 00:00:00')->quote(
                $fixture['event_id'],
                $selection,
                $this->context($fixture)
            ),
            'sale window'
        );

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

    public function testQuoteEnforcesEventWindowBookingLimitAndWaitingOutcome(): void
    {
        $fixture = $this->createFixture();
        $service = $this->service();

        $this->updateValue('#__jem_events', $fixture['event_id'], 'published', 0);
        $this->assertRejected(
            fn () => $service->quote(
                $fixture['event_id'],
                array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1)),
                $this->context($fixture)
            ),
            'unpublished event'
        );
        $this->updateValue('#__jem_events', $fixture['event_id'], 'published', 1);

        $this->updateValue('#__jem_events', $fixture['event_id'], 'registra', 0);
        $this->assertRejected(
            fn () => $service->quote(
                $fixture['event_id'],
                array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1)),
                $this->context($fixture)
            ),
            'closed registration'
        );
        $this->updateValue('#__jem_events', $fixture['event_id'], 'registra', 1);

        $this->assertRejected(
            fn () => $service->quote(
                $fixture['event_id'],
                array(
                    array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 3),
                    array('event_price_id' => $fixture['child_price_id'], 'quantity' => 2),
                ),
                $this->context($fixture)
            ),
            'event booking maximum'
        );

        $this->updateValue('#__jem_events', $fixture['event_id'], 'waitinglist', 1);
        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 3);
        $quote = $service->quote(
            $fixture['event_id'],
            array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2)),
            $this->context($fixture)
        );

        self::assertSame('waiting_list', $quote['inventory_state']);
        self::assertContains('price_quota', $quote['inventory_reasons']);
        self::assertSame(14, $quote['event_remaining']);
        self::assertSame(7, $quote['lines'][0]['pool_remaining']);
        self::assertSame(1, $quote['lines'][0]['quota_remaining']);
    }

    public function testQuoteCalculatesTaxExcludedPricesFromStoredDefinitions(): void
    {
        $fixture = $this->createFixture();
        $this->updateValue('#__jem_events', $fixture['event_id'], 'prices_include_tax', 0);
        $service = $this->service();
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

    public function testQuoteUsesSnapshottedCountryAfterVenueCountryChanges(): void
    {
        $fixture = $this->createFixture();
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jem_venues'))
                ->set($this->db->quoteName('country') . ' = ' . $this->db->quote('FR'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $fixture['venue_id'])
        )->execute();

        $quote = $this->service()->quote(
            $fixture['event_id'],
            array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1)),
            $this->context($fixture)
        );

        self::assertSame('12.00', $quote['grand_total']);
        self::assertSame('21.00', $quote['lines'][0]['tax_rate']);
    }

    public function testQuoteCanReplaceOneLockedRegistrationWithoutDoubleCountingIt(): void
    {
        $fixture = $this->createFixture();
        $context = $this->context($fixture, 2, true, true, $fixture['active_register_id']);
        $quote = $this->service()->quote(
            $fixture['event_id'],
            array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2)),
            $context
        );

        self::assertSame(3, $quote['event_used']);
        self::assertSame(15, $quote['event_remaining']);
        self::assertSame(8, $quote['lines'][0]['pool_remaining']);
        self::assertSame(6, $quote['lines'][0]['quota_remaining']);
    }

    public function testLockedQuoteProvidesAnIdempotentAtomicBoundary(): void
    {
        $fixture = $this->createFixture();
        $service = $this->service();
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1));
        $preview = $service->quote($fixture['event_id'], $selection, $this->context($fixture));
        $stored = array();
        $writes = 0;
        $operationReference = JemRegistrationIdentity::generateOperationReference();
        $lookup = static function (string $reference, int $eventId, int $userId) use (&$stored, $fixture): ?array {
            self::assertSame($fixture['event_id'], $eventId);
            self::assertSame(999999, $userId);

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
            $preview['quote_fingerprint'],
            $operationReference,
            $lookup,
            $operation
        );
        $retry = $service->withLockedQuote(
            $fixture['event_id'],
            $selection,
            $this->context($fixture),
            $preview['quote_fingerprint'],
            $operationReference,
            $lookup,
            $operation
        );

        self::assertSame(1, $writes);
        self::assertSame($first, $retry);
        self::assertSame('12.00', $first['grand_total']);
        self::assertSame(2, $first['pricing_revision']);
    }

    public function testPricedOrderKeepsModificationTermsAndRepricesOnlyReactivation(): void
    {
        $fixture = $this->createFixture();
        $registerId = $fixture['active_register_id'];
        $userId = $this->activeUserId();
        $this->db->setQuery(
            'DELETE FROM ' . $this->db->quoteName('#__jem_register_items')
            . ' WHERE register_id = ' . $registerId . ' AND registration_revision > 1'
        )->execute();
        $this->db->setQuery(
            'UPDATE ' . $this->db->quoteName('#__jem_register')
            . ' SET uid = ' . $userId . ', price_locked_at = ' . $this->db->quote('2026-09-01 09:00:00')
            . ' WHERE id = ' . $registerId
        )->execute();
        $this->db->setQuery(
            'UPDATE ' . $this->db->quoteName('#__jem_register_items')
            . " SET unit_net = '9.92', unit_tax = '2.08', unit_gross = '12.00',"
            . " line_net = '19.84', line_tax = '4.16', line_gross = '24.00',"
            . " tax_code = 'locked', tax_name = 'Locked tax', tax_type = 'standard', tax_rate = '21.00'"
            . ' WHERE register_id = ' . $registerId
            . ' AND registration_revision = 1 AND event_price_id = ' . $fixture['adult_price_id']
        )->execute();
        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'amount', 20);

        $service = new JemPricedRegistrationService($this->db, static fn (): string => '2026-09-15 12:00:00');
        $context = $this->context($fixture, 2, true, true, $registerId, $userId);
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 2));
        $preview = $service->quote($fixture['event_id'], $selection, $context);

        self::assertSame('12.00', $preview['lines'][0]['unit_gross']);
        self::assertSame('24.00', $preview['grand_total']);

        $operationReference = JemRegistrationIdentity::generateOperationReference();
        $modified = $service->confirm(
            $fixture['event_id'],
            $selection,
            $context,
            $preview['quote_fingerprint'],
            $operationReference,
            array(),
            array('expectedRevision' => 1, 'source' => 'phpunit')
        );
        self::assertTrue($modified->changed);
        self::assertSame(2, (int) $modified->after->revision);
        self::assertSame('24.00', (string) $modified->after->grand_total);
        self::assertSame('2026-09-01 09:00:00', (string) $modified->after->price_locked_at);

        $retry = $service->confirm(
            $fixture['event_id'],
            $selection,
            $context,
            $preview['quote_fingerprint'],
            $operationReference,
            array(),
            array('expectedRevision' => 1, 'source' => 'phpunit')
        );
        self::assertFalse($retry->changed);
        self::assertSame(2, (int) $retry->after->revision);

        $registrationService = new JemRegistrationService($this->db);
        $cancelled = $registrationService->cancelByIds(array($registerId), $fixture['event_id'], array(
            'actorId' => $userId,
            'source' => 'phpunit',
        ));
        self::assertSame(3, (int) $cancelled[0]->after->revision);
        self::assertSame('24.00', (string) $cancelled[0]->after->grand_total);
        self::assertSame(1, $this->itemCount($registerId, 3));

        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'amount', 30);
        $reactivationContext = $this->context($fixture, 2, true, true, $registerId, $userId);
        $reactivationPreview = $service->quote($fixture['event_id'], $selection, $reactivationContext);
        self::assertSame('30.00', $reactivationPreview['lines'][0]['unit_gross']);
        $reactivated = $service->confirm(
            $fixture['event_id'],
            $selection,
            $reactivationContext,
            $reactivationPreview['quote_fingerprint'],
            JemRegistrationIdentity::generateOperationReference(),
            array(),
            array('expectedRevision' => 3, 'source' => 'phpunit')
        );

        self::assertSame(4, (int) $reactivated->after->revision);
        self::assertSame('60.00', (string) $reactivated->after->grand_total);
        self::assertSame('2026-09-15 12:00:00', (string) $reactivated->after->price_locked_at);
        self::assertSame(1, $this->itemCount($registerId, 4));
    }

    public function testPricedOrderCreationPersistsOneAtomicWaitingSnapshot(): void
    {
        $fixture = $this->createFixture();
        $userId = $this->activeUserId();
        $this->updateValue('#__jem_events', $fixture['event_id'], 'waitinglist', 1);
        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 2);
        $service = new JemPricedRegistrationService($this->db, static fn (): string => '2026-09-15 12:00:00');
        $context = $this->context($fixture, 2, true, true, 0, $userId);
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1));
        $preview = $service->quote($fixture['event_id'], $selection, $context);

        self::assertSame('waiting_list', $preview['inventory_state']);
        $operationReference = JemRegistrationIdentity::generateOperationReference();
        $created = $service->confirm(
            $fixture['event_id'],
            $selection,
            $context,
            $preview['quote_fingerprint'],
            $operationReference,
            array('comment' => 'Atomic waiting order'),
            array('actorId' => $userId, 'source' => 'phpunit')
        );
        $registerId = (int) $created->after->id;
        $this->ids['registers'][] = $registerId;

        self::assertSame(1, (int) $created->after->revision);
        self::assertSame(1, (int) $created->after->waiting);
        self::assertSame(1, (int) $created->after->places);
        self::assertSame('12.00', (string) $created->after->grand_total);
        self::assertSame(1, $this->itemCount($registerId, 1));

        $retry = $service->confirm(
            $fixture['event_id'],
            $selection,
            $context,
            $preview['quote_fingerprint'],
            $operationReference,
            array('comment' => 'Atomic waiting order'),
            array('actorId' => $userId, 'source' => 'phpunit')
        );
        self::assertFalse($retry->changed);
        self::assertSame($registerId, (int) $retry->after->id);
        self::assertSame(1, $this->itemCount($registerId, 1));
    }

    public function testPricedWaitingPromotionHonoursPoolAndQuotaEvenWhenForced(): void
    {
        $fixture = $this->createFixture();
        $this->updateValue('#__jem_events', $fixture['event_id'], 'waitinglist', 1);
        $blocked = JemWaitingListPromotion::promote($fixture['event_id'], array(
            'mode' => JemWaitingListPromotion::MODE_MANUAL,
            'registrationIds' => array($fixture['waiting_register_id']),
            'force' => true,
            'notify' => false,
            'actorId' => $this->activeUserId(),
            'source' => 'phpunit',
        ));

        self::assertFalse($blocked->success);
        self::assertSame('capacity_exceeded', $blocked->reason);
        self::assertFalse($blocked->forced);

        $this->updateValue('#__jem_capacity_pools', $fixture['pool_id'], 'capacity', 20);
        $this->updateValue('#__jem_event_prices', $fixture['adult_price_id'], 'quota', 20);
        $promoted = JemWaitingListPromotion::promote($fixture['event_id'], array(
            'mode' => JemWaitingListPromotion::MODE_MANUAL,
            'registrationIds' => array($fixture['waiting_register_id']),
            'notify' => false,
            'actorId' => $this->activeUserId(),
            'source' => 'phpunit',
        ));

        self::assertTrue($promoted->success, $promoted->reason);
        self::assertSame(array($fixture['waiting_register_id']), $promoted->promotedIds);
        self::assertSame(1, $this->itemCount($fixture['waiting_register_id'], 2));
    }

    public function testLockedQuoteRejectsChangedTaxFingerprint(): void
    {
        $fixture = $this->createFixture();
        $service = $this->service();
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1));
        $context = $this->context($fixture);
        $preview = $service->quote($fixture['event_id'], $selection, $context);
        $this->updateValue('#__jem_tax_rates', $fixture['standard_tax_id'], 'rate', 22);

        try {
            $service->withLockedQuote(
                $fixture['event_id'],
                $selection,
                $context,
                $preview['quote_fingerprint'],
                JemRegistrationIdentity::generateOperationReference(),
                static fn () => null,
                static fn (): array => array('unexpected' => true)
            );
            self::fail('A changed tax calculation must invalidate the reviewed quote.');
        } catch (JemPricingQuoteException $error) {
            self::assertSame('quote_changed', $error->getReasonCode());
        }
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
     * @return array{venue_id:int,event_id:int,pool_id:int,standard_tax_id:int,adult_price_id:int,child_price_id:int,active_register_id:int,waiting_register_id:int,access_level_id:int,user_group_id:int}
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
        $accessLevelId = $this->minimumId('#__viewlevels');
        $userGroupId = $this->minimumId('#__usergroups');

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
            'minbookeduser' => 1,
            'maxbookeduser' => 4,
            'waitinglist' => 0,
            'registra' => 1,
            'reginvitedonly' => 0,
            'pricing_mode' => 'multiple',
            'pricing_revision' => 2,
            'currency' => 'EUR',
            'prices_include_tax' => 1,
            'venue_snapshot' => json_encode(array(
                'schema' => 'jem-venue-capacity/v1',
                'venue_id' => $venueId,
                'country_code' => 'ES',
            ), JSON_UNESCAPED_SLASHES),
            'published' => 1,
            'access' => 0,
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

        $registrationId = $this->insertRegistration($eventId, 3, 1, 0, 999999);
        $this->insertItem($registrationId, 1, 1, $adultPriceId, $poolId, 'adult', 2);
        $this->insertItem($registrationId, 1, 2, $childPriceId, $poolId, 'child', 1);
        $this->insertItem($registrationId, 2, 1, $adultPriceId, $poolId, 'adult-history', 20);

        $waitingId = $this->insertRegistration($eventId, 10, 1, 1);
        $this->insertItem($waitingId, 1, 1, $adultPriceId, $poolId, 'adult-waiting', 10);

        // Compatibility: an active pre-pricing registration still consumes
        // event capacity even though it has no commercial item rows.
        $this->insertRegistration($eventId, 1, 1, 0);

        return array(
            'venue_id' => $venueId,
            'event_id' => $eventId,
            'pool_id' => $poolId,
            'standard_tax_id' => $standardTaxId,
            'adult_price_id' => $adultPriceId,
            'child_price_id' => $childPriceId,
            'active_register_id' => $registrationId,
            'waiting_register_id' => $waitingId,
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

    private function insertRegistration(
        int $eventId,
        int $places,
        int $status,
        int $waiting,
        ?int $userId = null
    ): int
    {
        $reference = JemRegistrationIdentity::generateRegistrationReference();
        $registration = (object) array(
            'event' => $eventId,
            'uid' => $userId ?? -random_int(1000, 999999999),
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

    private function service(string $now = '2026-09-15 12:00:00'): JemPricingQuoteService
    {
        return new JemPricingQuoteService(
            $this->db,
            static fn (): string => $now
        );
    }

    private function context(
        array $fixture,
        int $pricingRevision = 2,
        bool $withAccess = true,
        bool $withGroup = true,
        int $excludedRegisterId = 0,
        int $userId = 999999
    ): JemPricingQuoteContext {
        $identity = new PricingQuoteIdentityStub(
            $userId,
            $withAccess ? array($fixture['access_level_id']) : array(),
            $withGroup ? array($fixture['user_group_id']) : array()
        );

        return JemPricingQuoteContext::fromIdentity($identity, $pricingRevision, $excludedRegisterId);
    }

    private function assertConcurrentLimit(array $fixture, string $expectedMessage): void
    {
        $worker = JEM_TEST_ROOT . '/tests/Support/pricing_quote_concurrent_worker.php';
        self::assertFileExists($worker);
        $selection = array(array('event_price_id' => $fixture['adult_price_id'], 'quantity' => 1));
        $preview = $this->service()->quote(
            $fixture['event_id'],
            $selection,
            $this->context($fixture)
        );
        $command = array(
            PHP_BINARY,
            $worker,
            self::joomlaRoot(),
            (string) $fixture['event_id'],
            (string) $fixture['adult_price_id'],
            (string) $fixture['access_level_id'],
            (string) $fixture['user_group_id'],
            '2026-09-15 12:00:00',
            $preview['quote_fingerprint'],
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

    private function activeUserId(): int
    {
        $this->db->setQuery(
            'SELECT MIN(id) FROM ' . $this->db->quoteName('#__users')
            . " WHERE block = 0 AND (activation = '' OR activation = '0')"
        );
        $id = (int) $this->db->loadResult();
        self::assertGreaterThan(0, $id);

        return $id;
    }

    private function itemCount(int $registerId, int $revision): int
    {
        $this->db->setQuery(
            'SELECT COUNT(*) FROM ' . $this->db->quoteName('#__jem_register_items')
            . ' WHERE register_id = ' . $registerId
            . ' AND registration_revision = ' . $revision
        );

        return (int) $this->db->loadResult();
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

final class PricingQuoteIdentityStub
{
    public function __construct(
        public readonly int $id,
        private readonly array $levels,
        private readonly array $groups
    ) {
    }

    public function getAuthorisedViewLevels(): array
    {
        return $this->levels;
    }

    public function getAuthorisedGroups(): array
    {
        return $this->groups;
    }
}
