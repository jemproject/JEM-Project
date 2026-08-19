<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class EventPricingCapacityJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 after installing the package to verify Point 4D.');
        }
        self::bootJoomlaSite();
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/eventpricingcapacity.class.php';
    }

    public function testInstalledPoint4DCreatesSnapshotPoolsAndExactPriceDefinition(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
        $venueId = 0;
        $taxRateId = 0;
        $eventId = 0;

        try {
            $venue = (object) array(
                'venue' => 'PHPUnit Point 4D ' . $suffix,
                'alias' => 'phpunit-point-4d-' . $suffix,
                'capacity' => 100,
                'country' => 'ES',
                'published' => 0,
                'created' => gmdate('Y-m-d H:i:s'),
                'created_by' => 0,
            );
            $db->insertObject('#__jem_venues', $venue, 'id');
            $venueId = (int) $venue->id;

            $taxRate = (object) array(
                'code' => 'phpunit-es-standard-' . $suffix,
                'name' => 'PHPUnit ES standard',
                'tax_type' => 'standard',
                'rate' => '21.00',
                'country_code' => 'ES',
                'published' => 1,
                'created' => gmdate('Y-m-d H:i:s'),
                'created_by' => 0,
            );
            $db->insertObject('#__jem_tax_rates', $taxRate, 'id');
            $taxRateId = (int) $taxRate->id;

            $configuration = JemVenueCapacityService::normaliseFormData(array(
                'spaces' => array(array(
                    'space_name' => 'Main hall',
                    'space_code' => 'main-hall',
                    'space_color' => '#225588',
                    'layout_name' => 'General admission',
                    'layout_code' => 'general-admission',
                    'layout_color' => '#AA7722',
                    'layout_capacity' => 100,
                    'areas' => array(array(
                        'name' => 'Floor',
                        'code' => 'floor',
                        'color' => '#447744',
                        'capacity' => 100,
                        'published' => 1,
                    )),
                )),
            ), 100);
            $savedConfiguration = JemVenueCapacityService::saveDefaultConfiguration($venueId, $configuration);
            self::assertSame(2, (int) $savedConfiguration['profile_revision']);
            self::assertSame('Main', (string) $savedConfiguration['profile_name']);
            self::assertSame(100, (int) $savedConfiguration['profile_capacity']);

            $renamedConfiguration = JemVenueCapacityService::saveDefaultConfiguration(
                $venueId,
                $configuration,
                'Main auditorium profile'
            );
            self::assertSame('Main auditorium profile', (string) $renamedConfiguration['profile_name']);
            self::assertSame(2, (int) $renamedConfiguration['profile_revision']);

            $requirements = JemEventPricingCapacityService::getVenueRequirements($venueId);
            self::assertTrue($requirements['capacity_ready']);
            self::assertSame('ES', $requirements['country_code']);
            self::assertSame('EUR', $requirements['suggested_currency']);
            self::assertSame(100, $requirements['configured_capacity']);
            self::assertSame(100, $requirements['profile_capacity']);
            self::assertCount(1, $requirements['pool_candidates']);
            $poolCode = (string) $requirements['pool_candidates'][0]['code'];

            $eventData = array(
                'locid' => $venueId,
                'dates' => gmdate('Y-m-d'),
                'maxplaces' => 100,
                'venue_allocation_mode' => 'profile',
                'pricing_mode' => JemEventPricingCapacityService::MODE_SINGLE,
                'currency' => '',
                'prices_include_tax' => 1,
                'default_tax_rate_id' => $taxRateId,
                'management_fee_value' => '0.00',
                'management_fee_tax_rate_id' => null,
                'management_fee_refundable' => 0,
                'event_prices' => array(array(
                    'id' => 0,
                    'capacity_pool_id' => 'source:' . $poolCode,
                    'code' => 'general',
                    'name' => 'General admission',
                    'amount' => '12.00',
                    'tax_rate_id' => $taxRateId,
                    'min_quantity' => 1,
                    'published' => 1,
                )),
            );
            $context = JemEventPricingCapacityService::prepareEventData($eventData, 0);
            self::assertSame('EUR', $eventData['currency']);
            self::assertSame(1, $eventData['pricing_revision']);
            $snapshot = json_decode($eventData['venue_snapshot'], true);
            self::assertSame('jem-venue-capacity/v1', $snapshot['schema']);
            self::assertSame('ES', $snapshot['country_code']);
            self::assertSame(100, $snapshot['profile_capacity']);
            self::assertSame('#225588', $snapshot['spaces'][0]['color']);
            self::assertSame('#AA7722', $snapshot['spaces'][0]['layout']['color']);
            self::assertSame('#447744', $snapshot['spaces'][0]['capacity_areas'][0]['color']);
            self::assertGreaterThan(0, (int) $snapshot['spaces'][0]['profile_space_id']);
            self::assertSame(100, (int) $snapshot['selected_capacity']);

            $event = (object) array_merge($eventData, array(
                'title' => 'PHPUnit Point 4D ' . $suffix,
                'alias' => 'phpunit-point-4d-' . $suffix,
                'introtext' => '',
                'fulltext' => '',
                'created_by_alias' => '',
                'metadata' => '',
                'published' => 0,
                'created' => gmdate('Y-m-d H:i:s'),
                'created_by' => 0,
            ));
            unset($event->event_prices);
            $db->insertObject('#__jem_events', $event, 'id');
            $eventId = (int) $event->id;
            JemEventPricingCapacityService::saveChildren($eventId, $context);

            $db->setQuery('SELECT * FROM ' . $db->quoteName('#__jem_event_space_layouts') . ' WHERE event_id = ' . $eventId);
            $assignment = $db->loadAssoc();
            self::assertIsArray($assignment);
            self::assertSame((int) $snapshot['spaces'][0]['profile_space_id'], (int) $assignment['venue_profile_space_id']);
            self::assertSame((int) $snapshot['spaces'][0]['layout']['id'], (int) $assignment['venue_layout_id']);

            $db->setQuery('SELECT * FROM ' . $db->quoteName('#__jem_capacity_pools') . ' WHERE event_id = ' . $eventId);
            $pool = $db->loadAssoc();
            self::assertIsArray($pool);
            self::assertSame($poolCode, $pool['code']);
            self::assertSame(100, (int) $pool['capacity']);

            $db->setQuery('SELECT * FROM ' . $db->quoteName('#__jem_event_prices') . ' WHERE event_id = ' . $eventId);
            $price = $db->loadAssoc();
            self::assertIsArray($price);
            self::assertSame((int) $pool['id'], (int) $price['capacity_pool_id']);
            self::assertSame('12.00', (string) $price['amount']);
            self::assertSame($taxRateId, (int) $price['tax_rate_id']);

            $copyRows = JemEventPricingCapacityService::prepareCopiedPriceRows(array($price), $eventId);
            self::assertSame(0, (int) $copyRows[0]['id']);
            self::assertSame('source:' . $poolCode, $copyRows[0]['capacity_pool_id']);
        } finally {
            $this->deleteWhere($db, '#__jem_event_prices', 'event_id', $eventId);
            $this->deleteWhere($db, '#__jem_capacity_pools', 'event_id', $eventId);
            $this->deleteWhere($db, '#__jem_event_space_layouts', 'event_id', $eventId);
            $this->deleteWhere($db, '#__jem_events', 'id', $eventId);

            if ($venueId > 0) {
                $db->setQuery('SELECT id FROM ' . $db->quoteName('#__jem_venue_capacity_profiles') . ' WHERE venue_id = ' . $venueId);
                $profileIds = array_map('intval', (array) $db->loadColumn());
                $db->setQuery('SELECT id FROM ' . $db->quoteName('#__jem_venue_spaces') . ' WHERE venue_id = ' . $venueId);
                $spaceIds = array_map('intval', (array) $db->loadColumn());
                if ($profileIds) {
                    $this->deleteIn($db, '#__jem_venue_profile_spaces', 'venue_profile_id', $profileIds);
                }
                if ($spaceIds) {
                    $db->setQuery('SELECT id FROM ' . $db->quoteName('#__jem_venue_layouts') . ' WHERE venue_space_id IN (' . implode(',', $spaceIds) . ')');
                    $layoutIds = array_map('intval', (array) $db->loadColumn());
                    if ($layoutIds) {
                        $this->deleteIn($db, '#__jem_venue_capacity_areas', 'venue_layout_id', $layoutIds);
                    }
                    $this->deleteIn($db, '#__jem_venue_layouts', 'venue_space_id', $spaceIds);
                }
                $this->deleteWhere($db, '#__jem_venue_capacity_profiles', 'venue_id', $venueId);
                $this->deleteWhere($db, '#__jem_venue_spaces', 'venue_id', $venueId);
                $this->deleteWhere($db, '#__jem_venues', 'id', $venueId);
            }
            $this->deleteWhere($db, '#__jem_tax_rates', 'id', $taxRateId);
        }
    }

    private function deleteWhere(DatabaseDriver $db, string $table, string $column, int $value): void
    {
        if ($value < 1) {
            return;
        }
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->where($db->quoteName($column) . ' = ' . $value)
        )->execute();
    }

    /** @param int[] $values */
    private function deleteIn(DatabaseDriver $db, string $table, string $column, array $values): void
    {
        if (!$values) {
            return;
        }
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->where($db->quoteName($column) . ' IN (' . implode(',', array_map('intval', $values)) . ')')
        )->execute();
    }
}
