<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenuePricingCapacitySchemaTest extends TestCase
{
    private const VENUE_TABLES = array(
        '#__jem_venue_capacity_profiles',
        '#__jem_venue_spaces',
        '#__jem_venue_layouts',
        '#__jem_venue_profile_spaces',
        '#__jem_venue_capacity_areas',
    );

    public function testFreshAndUpgradeSchemasContainVersionedVenueCapacityModel(): void
    {
        $install = $this->read('/admin/sql/install.mysql.utf8.sql');
        $update = $this->read('/admin/sql/updates/mysql/5.1.0.sql');

        foreach (self::VENUE_TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $install);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $update);
        }

        foreach (array('venue_profile_id', 'venue_profile_revision', 'venue_snapshot') as $column) {
            self::assertStringContainsString('`' . $column . '`', $install);
            self::assertStringContainsString('`' . $column . '`', $update);
        }
        self::assertStringNotContainsString('venue_configuration_snapshot', $install . $update);

        foreach (array('venue_capacity_area_id', 'venue_layout_id', 'venue_layout_revision', 'allocation_mode') as $column) {
            self::assertMatchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS `#__jem_capacity_pools`[\s\S]+?`' . $column . '`/i',
                $install
            );
            self::assertStringContainsString('ADD COLUMN `' . $column . '`', $update);
        }

        self::assertStringContainsString('idx_venue_layout_revision', $install);
        self::assertStringContainsString('idx_venue_profile_space', $install);
        self::assertStringContainsString('idx_venue_capacity_area_code', $install);
        self::assertStringContainsString('INSERT IGNORE INTO `#__jem_venue_capacity_profiles`', $update);
        self::assertStringContainsString("'default','Default configuration'", $update);
    }

    public function testInstallerRepairsCapacitySchemaAndDefaultProfilesIdempotently(): void
    {
        $script = $this->read('/script.php');

        foreach (self::VENUE_TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $script);
            self::assertStringContainsString("'" . $table . "'", $script);
        }

        self::assertStringContainsString('installDefaultVenueCapacityProfiles($db)', $script);
        self::assertStringContainsString("'INSERT IGNORE INTO '", $script);
        self::assertStringContainsString("'venue_snapshot'", $script);
        self::assertStringNotContainsString('venue_configuration_snapshot', $script);
    }

    public function testEventAndVenueServicesKeepPhysicalAndCommercialDataSeparate(): void
    {
        $venueService = $this->read('/admin/classes/venuecapacity.class.php');
        $eventService = $this->read('/admin/classes/eventpricingcapacity.class.php');

        self::assertStringContainsString("'schema'             => 'jem-venue-capacity/v1'", $venueService);
        self::assertStringContainsString("'spaces'", $venueService);
        self::assertStringContainsString("'capacity_areas'", $venueService);
        self::assertStringContainsString('layoutFingerprint', $venueService);
        self::assertStringContainsString('saveDefaultConfiguration', $venueService);

        self::assertStringContainsString('$data[\'venue_snapshot\']', $eventService);
        self::assertStringContainsString('buildPoolRowsFromSnapshot', $eventService);
        self::assertStringContainsString('normalisePriceRows', $eventService);
        self::assertStringContainsString('prepareCopiedPriceRows', $eventService);
        self::assertStringContainsString('JemMoney::fromDecimal', $eventService);
        self::assertStringContainsString('hasCommercialRegistrations', $eventService);
        self::assertStringContainsString('COM_JEM_EVENT_PRICING_ERROR_PRICE_TAX', $eventService);
        self::assertStringContainsString("MODE_SINGLE = 'single'", $eventService);
        self::assertStringContainsString("MODE_MULTIPLE = 'multiple'", $eventService);
        self::assertStringContainsString('validatePriceCapacityLimits', $eventService);
        self::assertStringContainsString('validateEligibilityIds', $eventService);
        self::assertStringContainsString('COM_JEM_EVENT_PRICING_ERROR_TAX_VALIDITY', $eventService);
        self::assertStringContainsString("str_starts_with(\$poolReference, 'source:')", $eventService);
        self::assertStringContainsString("\$price['_capacity_pool_code']", $eventService);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
