<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class EventPricingCapacityCombinationsJoomlaIntegrationTest extends JoomlaTestCase
{
    private DatabaseDriver $db;

    /** @var int[] */
    private array $eventIds = array();

    /** @var int[] */
    private array $venueIds = array();

    /** @var int[] */
    private array $taxRateIds = array();

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 after installing the package to verify Point 4D price combinations.');
        }
        self::bootJoomlaSite();
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/eventpricingcapacity.class.php';
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            foreach (array_reverse($this->eventIds) as $eventId) {
                $this->deleteWhere('#__jem_event_prices', 'event_id', $eventId);
                $this->deleteWhere('#__jem_capacity_pools', 'event_id', $eventId);
                $this->deleteWhere('#__jem_event_space_layouts', 'event_id', $eventId);
                $this->deleteWhere('#__jem_events', 'id', $eventId);
            }
            foreach (array_reverse($this->venueIds) as $venueId) {
                $this->deleteVenue($venueId);
            }
            foreach (array_reverse($this->taxRateIds) as $taxRateId) {
                $this->deleteWhere('#__jem_tax_rates', 'id', $taxRateId);
            }
        }

        parent::tearDown();
    }

    public function testClassicModeRequiresNeitherVenueNorPrices(): void
    {
        $data = array(
            'pricing_mode' => JemEventPricingCapacityService::MODE_CLASSIC,
            'currency' => '',
            'prices_include_tax' => 1,
            'management_fee_value' => '0.00',
        );

        $context = JemEventPricingCapacityService::prepareEventData($data, 0);

        self::assertFalse($context['active']);
        self::assertSame(JemEventPricingCapacityService::MODE_CLASSIC, $data['pricing_mode']);
        self::assertSame('', $data['currency']);
        self::assertSame(1, $data['pricing_revision']);
        self::assertSame(array(), $context['pools']);
        self::assertSame(array(), $context['prices']);
    }

    public function testVenueConfigurationPresetsAndCustomThresholdFilterSnapshots(): void
    {
        $threeSpaceVenue = $this->createMultiSpaceVenue(3);
        $three = JemEventPricingCapacityService::getVenueRequirements($threeSpaceVenue);
        self::assertTrue($three['capacity_ready']);
        self::assertFalse($three['configuration_custom_required']);
        self::assertCount(3, $three['configuration_assignments']);
        self::assertCount(7, $three['configuration_options']);

        $selectedId = (int) $three['configuration_assignments'][1]['id'];
        $snapshot = JemVenueCapacityService::buildEventSnapshot($threeSpaceVenue, array($selectedId));
        self::assertCount(1, $snapshot['spaces']);
        self::assertSame($selectedId, (int) $snapshot['spaces'][0]['profile_space_id']);
        self::assertSame(20, (int) $snapshot['selected_capacity']);

        try {
            JemVenueCapacityService::buildEventSnapshot($threeSpaceVenue, array(PHP_INT_MAX));
            self::fail('A foreign profile-space assignment must be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertNotSame('', trim($error->getMessage()));
        }

        $fourSpaceVenue = $this->createMultiSpaceVenue(4);
        $four = JemEventPricingCapacityService::getVenueRequirements($fourSpaceVenue);
        self::assertTrue($four['configuration_custom_required']);
        self::assertLessThanOrEqual(11, count($four['configuration_options']));
    }

    public function testSingleTaxExcludedPriceIsNormalisedExactly(): void
    {
        $fixture = $this->createVenueFixture();
        $reducedTaxId = $this->createTaxRate('ES', 'reduced', '10.00');
        $data = $this->singlePriceData($fixture, $reducedTaxId);
        $data['prices_include_tax'] = 0;
        $data['event_prices'][0]['amount'] = '10';

        $context = JemEventPricingCapacityService::prepareEventData($data, 0);

        self::assertTrue($context['active']);
        self::assertSame(JemEventPricingCapacityService::MODE_SINGLE, $data['pricing_mode']);
        self::assertSame('EUR', $data['currency']);
        self::assertSame(0, $data['prices_include_tax']);
        self::assertCount(1, $context['prices']);
        self::assertSame('10.00', $context['prices'][0]['amount']);
        self::assertSame($reducedTaxId, $context['prices'][0]['tax_rate_id']);
    }

    public function testMultiplePricesPersistTaxesPoolsConditionsAndRevisionChanges(): void
    {
        $fixture = $this->createVenueFixture();
        $standardTaxId = $this->createTaxRate('ES', 'standard', '21.00');
        $reducedTaxId = $this->createTaxRate('ES', 'reduced', '10.00');
        $zeroTaxId = $this->createTaxRate('ES', 'zero', '0.00');
        $accessLevelId = $this->minimumId('#__viewlevels');
        $userGroupId = $this->minimumId('#__usergroups');
        $data = $this->multiplePriceData(
            $fixture,
            $standardTaxId,
            $reducedTaxId,
            $zeroTaxId,
            $accessLevelId,
            $userGroupId
        );

        $context = JemEventPricingCapacityService::prepareEventData($data, 0);
        self::assertSame(1, $data['pricing_revision']);
        self::assertCount(2, $context['pools']);
        self::assertCount(3, $context['prices']);

        $eventId = $this->insertEvent($data);
        JemEventPricingCapacityService::saveChildren($eventId, $context);

        $pools = $this->loadRows('#__jem_capacity_pools', $eventId);
        $prices = $this->loadRows('#__jem_event_prices', $eventId);
        self::assertCount(2, $pools);
        self::assertCount(3, $prices);

        $pricesByCode = array_column($prices, null, 'code');
        self::assertSame('12.00', $pricesByCode['adult']['amount']);
        self::assertSame($standardTaxId, (int) $pricesByCode['adult']['tax_rate_id']);
        self::assertSame(60, (int) $pricesByCode['adult']['quota']);
        self::assertSame(18, (int) $pricesByCode['adult']['min_age']);
        self::assertSame(4, (int) $pricesByCode['adult']['max_quantity']);

        self::assertSame('6.00', $pricesByCode['child']['amount']);
        self::assertSame($reducedTaxId, (int) $pricesByCode['child']['tax_rate_id']);
        self::assertSame(17, (int) $pricesByCode['child']['max_age']);
        self::assertSame('declaration', $pricesByCode['child']['verification_mode']);

        self::assertSame('8.00', $pricesByCode['member']['amount']);
        self::assertSame($zeroTaxId, (int) $pricesByCode['member']['tax_rate_id']);
        self::assertSame($accessLevelId, (int) $pricesByCode['member']['access_level_id']);
        self::assertSame($userGroupId, (int) $pricesByCode['member']['user_group_id']);
        self::assertSame('manual', $pricesByCode['member']['verification_mode']);
        self::assertSame('2026-09-01 08:00:00', $pricesByCode['member']['available_from']);
        self::assertSame('2026-09-30 22:00:00', $pricesByCode['member']['available_until']);
        self::assertNotSame(
            (int) $pricesByCode['adult']['capacity_pool_id'],
            (int) $pricesByCode['member']['capacity_pool_id']
        );

        $unchanged = $this->persistedPricingData($fixture['venue_id'], $standardTaxId, $pools, $prices);
        JemEventPricingCapacityService::prepareEventData($unchanged, $eventId);
        self::assertSame(1, $unchanged['pricing_revision']);

        $changed = $this->persistedPricingData($fixture['venue_id'], $standardTaxId, $pools, $prices);
        $changed['event_prices'][0]['amount'] = '13.00';
        $changedContext = JemEventPricingCapacityService::prepareEventData($changed, $eventId);
        self::assertSame(2, $changed['pricing_revision']);
        self::assertSame('13.00', $changedContext['prices'][0]['amount']);
    }

    public function testInvalidPriceCombinationsAreRejectedAuthoritatively(): void
    {
        $fixture = $this->createVenueFixture();
        $standardTaxId = $this->createTaxRate('ES', 'standard', '21.00');
        $foreignTaxId = $this->createTaxRate('FR', 'standard', '20.00');
        $base = $this->singlePriceData($fixture, $standardTaxId);

        $missingPrices = $base;
        unset($missingPrices['event_prices']);

        $twoSinglePrices = $base;
        $twoSinglePrices['event_prices'][] = array_merge(
            $twoSinglePrices['event_prices'][0],
            array('code' => 'second', 'name' => 'Second price')
        );

        $oneMultiplePrice = $base;
        $oneMultiplePrice['pricing_mode'] = JemEventPricingCapacityService::MODE_MULTIPLE;

        $wrongCountry = $base;
        $wrongCountry['event_prices'][0]['tax_rate_id'] = $foreignTaxId;

        $excessiveQuota = $base;
        $excessiveQuota['event_prices'][0]['quota'] = 81;

        $invalidQuantity = $base;
        $invalidQuantity['event_prices'][0]['min_quantity'] = 3;
        $invalidQuantity['event_prices'][0]['max_quantity'] = 2;

        $invalidAge = $base;
        $invalidAge['event_prices'][0]['min_age'] = 18;
        $invalidAge['event_prices'][0]['max_age'] = 17;

        $invalidWindow = $base;
        $invalidWindow['event_prices'][0]['available_from'] = '2026-10-02 10:00:00';
        $invalidWindow['event_prices'][0]['available_until'] = '2026-10-01 10:00:00';

        $excessiveEventCapacity = $base;
        $excessiveEventCapacity['maxplaces'] = 121;

        foreach (array(
            'missing price rows' => $missingPrices,
            'two prices in single mode' => $twoSinglePrices,
            'one price in multiple mode' => $oneMultiplePrice,
            'foreign tax rate' => $wrongCountry,
            'quota above pool' => $excessiveQuota,
            'inverted quantity range' => $invalidQuantity,
            'inverted age range' => $invalidAge,
            'inverted availability range' => $invalidWindow,
            'event capacity above venue' => $excessiveEventCapacity,
        ) as $case => $invalidData) {
            try {
                JemEventPricingCapacityService::prepareEventData($invalidData, 0);
                self::fail($case . ' must be rejected.');
            } catch (InvalidArgumentException $error) {
                self::assertNotSame('', trim($error->getMessage()), $case);
            }
        }
    }

    /** @return array{venue_id:int, floor_code:string, balcony_code:string} */
    private function createVenueFixture(): array
    {
        $suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        $venue = (object) array(
            'venue' => 'PHPUnit price combinations ' . $suffix,
            'alias' => 'phpunit-price-combinations-' . $suffix,
            'capacity' => 120,
            'country' => 'ES',
            'published' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_venues', $venue, 'id');
        $venueId = (int) $venue->id;
        $this->venueIds[] = $venueId;

        $configuration = JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(array(
                'space_name' => 'Main hall',
                'space_code' => 'main-hall',
                'layout_name' => 'General admission',
                'layout_code' => 'general-admission',
                'layout_capacity' => 120,
                'areas' => array(
                    array('name' => 'Floor', 'code' => 'floor', 'capacity' => 80, 'published' => 1),
                    array('name' => 'Balcony', 'code' => 'balcony', 'capacity' => 40, 'published' => 1),
                ),
            )),
        ), 120);
        JemVenueCapacityService::saveDefaultConfiguration($venueId, $configuration);
        $requirements = JemEventPricingCapacityService::getVenueRequirements($venueId);
        self::assertTrue($requirements['capacity_ready']);
        self::assertCount(2, $requirements['pool_candidates']);

        $codes = array_column($requirements['pool_candidates'], 'code');
        $floorCode = (string) current(array_values(array_filter($codes, static fn (string $code): bool => str_contains($code, 'floor'))));
        $balconyCode = (string) current(array_values(array_filter($codes, static fn (string $code): bool => str_contains($code, 'balcony'))));
        self::assertNotSame('', $floorCode);
        self::assertNotSame('', $balconyCode);

        return array('venue_id' => $venueId, 'floor_code' => $floorCode, 'balcony_code' => $balconyCode);
    }

    private function createTaxRate(string $country, string $type, string $rate): int
    {
        $suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        $tax = (object) array(
            'code' => 'phpunit-' . strtolower($country) . '-' . $type . '-' . $suffix,
            'name' => 'PHPUnit ' . $country . ' ' . $type,
            'tax_type' => $type,
            'rate' => $rate,
            'country_code' => $country,
            'published' => 1,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_tax_rates', $tax, 'id');
        $taxRateId = (int) $tax->id;
        $this->taxRateIds[] = $taxRateId;

        return $taxRateId;
    }

    private function createMultiSpaceVenue(int $spaceCount): int
    {
        $suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        $capacity = 0;
        $spaces = array();
        for ($index = 1; $index <= $spaceCount; $index++) {
            $spaceCapacity = $index * 10;
            $capacity += $spaceCapacity;
            $spaces[] = array(
                'space_name' => 'Room ' . $index,
                'space_code' => 'room-' . $index,
                'layout_name' => 'Layout ' . $index,
                'layout_code' => 'layout-' . $index,
                'layout_capacity' => $spaceCapacity,
                'areas' => array(),
            );
        }

        $venue = (object) array(
            'venue' => 'PHPUnit multi space ' . $suffix,
            'alias' => 'phpunit-multi-space-' . $suffix,
            'capacity' => $capacity,
            'country' => 'ES',
            'published' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_venues', $venue, 'id');
        $venueId = (int) $venue->id;
        $this->venueIds[] = $venueId;
        $configuration = JemVenueCapacityService::normaliseFormData(array('spaces' => $spaces), $capacity);
        JemVenueCapacityService::saveDefaultConfiguration($venueId, $configuration);

        return $venueId;
    }

    /** @param array{venue_id:int, floor_code:string, balcony_code:string} $fixture */
    private function singlePriceData(array $fixture, int $taxRateId): array
    {
        return array(
            'locid' => $fixture['venue_id'],
            'dates' => '2026-10-15',
            'maxplaces' => 100,
            'pricing_mode' => JemEventPricingCapacityService::MODE_SINGLE,
            'currency' => '',
            'prices_include_tax' => 1,
            'default_tax_rate_id' => $taxRateId,
            'management_fee_value' => '0.00',
            'management_fee_tax_rate_id' => null,
            'management_fee_refundable' => 0,
            'event_prices' => array(array(
                'id' => 0,
                'capacity_pool_id' => 'source:' . $fixture['floor_code'],
                'code' => 'general',
                'name' => 'General admission',
                'amount' => '12.00',
                'tax_rate_id' => $taxRateId,
                'quota' => 80,
                'min_quantity' => 1,
                'published' => 1,
            )),
        );
    }

    /** @param array{venue_id:int, floor_code:string, balcony_code:string} $fixture */
    private function multiplePriceData(
        array $fixture,
        int $standardTaxId,
        int $reducedTaxId,
        int $zeroTaxId,
        int $accessLevelId,
        int $userGroupId
    ): array {
        return array(
            'locid' => $fixture['venue_id'],
            'dates' => '2026-10-15',
            'maxplaces' => 100,
            'pricing_mode' => JemEventPricingCapacityService::MODE_MULTIPLE,
            'currency' => 'EUR',
            'prices_include_tax' => 1,
            'default_tax_rate_id' => $standardTaxId,
            'management_fee_value' => '0.00',
            'management_fee_tax_rate_id' => null,
            'management_fee_refundable' => 0,
            'event_prices' => array(
                array(
                    'id' => 0,
                    'capacity_pool_id' => 'source:' . $fixture['floor_code'],
                    'code' => 'adult',
                    'name' => 'Adult',
                    'amount' => '12.00',
                    'tax_rate_id' => $standardTaxId,
                    'quota' => 60,
                    'min_quantity' => 1,
                    'max_quantity' => 4,
                    'min_age' => 18,
                    'max_age' => 65,
                    'published' => 1,
                ),
                array(
                    'id' => 0,
                    'capacity_pool_id' => 'source:' . $fixture['floor_code'],
                    'code' => 'child',
                    'name' => 'Child',
                    'amount' => '6.00',
                    'tax_rate_id' => $reducedTaxId,
                    'quota' => 20,
                    'min_quantity' => 1,
                    'max_quantity' => 3,
                    'min_age' => 0,
                    'max_age' => 17,
                    'verification_mode' => 'declaration',
                    'published' => 1,
                ),
                array(
                    'id' => 0,
                    'capacity_pool_id' => 'source:' . $fixture['balcony_code'],
                    'code' => 'member',
                    'name' => 'Member',
                    'amount' => '8.00',
                    'tax_rate_id' => $zeroTaxId,
                    'quota' => 20,
                    'min_quantity' => 1,
                    'max_quantity' => 2,
                    'available_from' => '2026-09-01 08:00:00',
                    'available_until' => '2026-09-30 22:00:00',
                    'access_level_id' => $accessLevelId,
                    'user_group_id' => $userGroupId,
                    'verification_mode' => 'manual',
                    'published' => 1,
                ),
            ),
        );
    }

    private function persistedPricingData(int $venueId, int $defaultTaxRateId, array $pools, array $prices): array
    {
        return array(
            'locid' => $venueId,
            'dates' => '2026-10-15',
            'maxplaces' => 100,
            'pricing_mode' => JemEventPricingCapacityService::MODE_MULTIPLE,
            'currency' => 'EUR',
            'prices_include_tax' => 1,
            'default_tax_rate_id' => $defaultTaxRateId,
            'management_fee_value' => '0.00',
            'management_fee_tax_rate_id' => null,
            'management_fee_refundable' => 0,
            'capacity_pools' => $pools,
            'event_prices' => $prices,
        );
    }

    private function insertEvent(array $data): int
    {
        $suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        $event = (object) array_merge($data, array(
            'title' => 'PHPUnit multiple prices ' . $suffix,
            'alias' => 'phpunit-multiple-prices-' . $suffix,
            'introtext' => '',
            'fulltext' => '',
            'created_by_alias' => '',
            'metadata' => '',
            'published' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        ));
        unset($event->capacity_pools, $event->event_prices);
        $this->db->insertObject('#__jem_events', $event, 'id');
        $eventId = (int) $event->id;
        $this->eventIds[] = $eventId;

        return $eventId;
    }

    private function loadRows(string $table, int $eventId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId)
            ->order($this->db->quoteName('ordering') . ' ASC, ' . $this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query);

        return (array) $this->db->loadAssocList();
    }

    private function minimumId(string $table): int
    {
        $this->db->setQuery('SELECT MIN(' . $this->db->quoteName('id') . ') FROM ' . $this->db->quoteName($table));
        $id = (int) $this->db->loadResult();
        self::assertGreaterThan(0, $id, $table . ' must contain a testable row.');

        return $id;
    }

    private function deleteVenue(int $venueId): void
    {
        $this->db->setQuery('SELECT id FROM ' . $this->db->quoteName('#__jem_venue_capacity_profiles') . ' WHERE venue_id = ' . $venueId);
        $profileIds = array_map('intval', (array) $this->db->loadColumn());
        $this->db->setQuery('SELECT id FROM ' . $this->db->quoteName('#__jem_venue_spaces') . ' WHERE venue_id = ' . $venueId);
        $spaceIds = array_map('intval', (array) $this->db->loadColumn());
        if ($profileIds) {
            $this->deleteIn('#__jem_venue_profile_spaces', 'venue_profile_id', $profileIds);
        }
        if ($spaceIds) {
            $this->db->setQuery('SELECT id FROM ' . $this->db->quoteName('#__jem_venue_layouts') . ' WHERE venue_space_id IN (' . implode(',', $spaceIds) . ')');
            $layoutIds = array_map('intval', (array) $this->db->loadColumn());
            if ($layoutIds) {
                $this->deleteIn('#__jem_venue_capacity_areas', 'venue_layout_id', $layoutIds);
            }
            $this->deleteIn('#__jem_venue_layouts', 'venue_space_id', $spaceIds);
        }
        $this->deleteWhere('#__jem_venue_capacity_profiles', 'venue_id', $venueId);
        $this->deleteWhere('#__jem_venue_spaces', 'venue_id', $venueId);
        $this->deleteWhere('#__jem_venues', 'id', $venueId);
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

    /** @param int[] $values */
    private function deleteIn(string $table, string $column, array $values): void
    {
        if (!$values) {
            return;
        }
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName($table))
            ->where($this->db->quoteName($column) . ' IN (' . implode(',', array_map('intval', $values)) . ')');
        $this->db->setQuery($query)->execute();
    }
}
