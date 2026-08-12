<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class PricingFoundationJoomlaIntegrationTest extends JoomlaTestCase
{
    private const PRICING_TABLES = array(
        '#__jem_tax_rates',
        '#__jem_capacity_pools',
        '#__jem_event_prices',
        '#__jem_register_items',
    );

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 to run the additive pricing-schema test.');
        }
        self::bootJoomlaSite();
        require_once JEM_TEST_ROOT . '/script.php';
    }

    public function testPricingSchemaRepairIsAdditiveAndIdempotent(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $eventColumnsBefore = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_events'), false),
            CASE_LOWER
        );
        $hadPricingSchema = isset($eventColumnsBefore['pricing_mode']);
        $legacyFingerprint = $this->legacyFingerprint($db);
        $countsBefore = $this->pricingTableCounts($db);

        $installer = new com_jemInstallerScript();
        $repair = new ReflectionMethod(com_jemInstallerScript::class, 'repair510PricingSchema');
        $repair->invoke($installer);

        self::assertSame($legacyFingerprint, $this->legacyFingerprint($db));
        self::assertSame($countsBefore, $this->pricingTableCounts($db));
        $this->assertPricingSchema($db);

        if (!$hadPricingSchema) {
            $db->setQuery("SELECT COUNT(*) FROM `#__jem_events` WHERE `pricing_mode` <> 'classic' OR `pricing_revision` <> 1 OR `currency` <> '' OR `prices_include_tax` <> 1 OR `management_fee_mode` <> 'fixed_per_ticket' OR `management_fee_value` <> 0.00 OR `management_fee_basis` <> 'gross' OR `management_fee_refundable` <> 0");
            self::assertSame(0, (int) $db->loadResult());
            $db->setQuery('SELECT COUNT(*) FROM `#__jem_register` WHERE `subtotal_net` IS NOT NULL OR `discount_total` IS NOT NULL OR `tax_total` IS NOT NULL OR `management_fee_net` IS NOT NULL OR `management_fee_tax` IS NOT NULL OR `management_fee_gross` IS NOT NULL OR `grand_total` IS NOT NULL OR `payment_state` IS NOT NULL OR `price_locked_at` IS NOT NULL OR `external_payment_reference` IS NOT NULL');
            self::assertSame(0, (int) $db->loadResult());
        }

        $countsAfterFirstRepair = $this->pricingTableCounts($db);
        $repair->invoke($installer);
        self::assertSame($legacyFingerprint, $this->legacyFingerprint($db));
        self::assertSame($countsAfterFirstRepair, $this->pricingTableCounts($db));
        $this->assertPricingSchema($db);
    }

    private function assertPricingSchema(DatabaseDriver $db): void
    {
        $tableList = $db->getTableList();
        foreach (self::PRICING_TABLES as $table) {
            self::assertContains($db->replacePrefix($table), $tableList);
        }

        $eventColumns = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_events'), false),
            CASE_LOWER
        );
        foreach (array('pricing_mode', 'pricing_revision', 'currency', 'default_tax_rate_id', 'prices_include_tax', 'management_fee_mode', 'management_fee_value', 'management_fee_basis', 'management_fee_tax_rate_id', 'management_fee_refundable') as $column) {
            self::assertArrayHasKey($column, $eventColumns);
        }

        $registerColumns = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_register'), false),
            CASE_LOWER
        );
        foreach (array('pricing_mode', 'currency', 'subtotal_net', 'discount_total', 'tax_total', 'management_fee_net', 'management_fee_tax', 'management_fee_gross', 'grand_total', 'payment_state', 'price_locked_at', 'external_payment_reference') as $column) {
            self::assertArrayHasKey($column, $registerColumns);
        }

        $taxRateColumns = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_tax_rates'), false),
            CASE_LOWER
        );
        foreach (array('checked_out', 'checked_out_time') as $column) {
            self::assertArrayHasKey($column, $taxRateColumns);
        }

        $db->setQuery("SELECT `value` FROM `#__jem_config` WHERE `keyname` = 'pricing_schema_ready'");
        self::assertSame('1', (string) $db->loadResult());
    }

    /**
     * @return array<string, int>
     */
    private function pricingTableCounts(DatabaseDriver $db): array
    {
        $tableList = $db->getTableList();
        $counts = array();
        foreach (self::PRICING_TABLES as $table) {
            if (!in_array($db->replacePrefix($table), $tableList, true)) {
                $counts[$table] = 0;
                continue;
            }
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName($table));
            $counts[$table] = (int) $db->loadResult();
        }

        return $counts;
    }

    private function legacyFingerprint(DatabaseDriver $db): string
    {
        $parts = array();
        foreach (array(
            '#__jem_events' => array('id', 'locid', 'dates', 'enddates', 'times', 'endtimes', 'title', 'registra', 'maxplaces', 'reservedplaces', 'waitinglist', 'published'),
            '#__jem_register' => array('id', 'event', 'uid', 'places', 'uregdate', 'uip', 'waiting', 'status', 'comment', 'reference', 'created', 'modified', 'revision'),
        ) as $table => $columns) {
            $query = $db->getQuery(true)
                ->select(array_map(array($db, 'quoteName'), $columns))
                ->from($db->quoteName($table))
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $parts[] = json_encode($db->loadAssocList(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return hash('sha256', implode("\n", $parts));
    }
}
