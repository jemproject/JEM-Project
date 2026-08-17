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
        '#__jem_event_space_layouts',
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
        self::assertStringContainsString('idx_event_space_layout_assignment', $install);
        self::assertStringContainsString('idx_venue_capacity_area_code', $install);
        self::assertMatchesRegularExpression(
            '/CREATE TABLE IF NOT EXISTS `#__jem_venue_capacity_profiles`[\s\S]+?`capacity` int\(10\) unsigned/i',
            $install
        );
        self::assertStringContainsString('ADD COLUMN `capacity`', $update);
        foreach (array('#__jem_venue_spaces', '#__jem_venue_layouts', '#__jem_venue_capacity_areas') as $colourTable) {
            self::assertMatchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS `' . preg_quote($colourTable, '/') . '`[\s\S]+?`color` char\(7\)/i',
                $install
            );
        }
        self::assertSame(3, substr_count($update, 'ADD COLUMN `color`'));
        self::assertStringNotContainsString('INSERT IGNORE INTO `#__jem_venue_capacity_profiles`', $update);
        self::assertStringContainsString('DELETE FROM `#__jem_venue_capacity_profiles`', $update);
        self::assertStringContainsString('NOT EXISTS (SELECT 1 FROM `#__jem_venue_profile_spaces`', $update);
        self::assertStringContainsString("`name` = 'Main'", $update);
        self::assertStringContainsString("`name` = 'Default configuration'", $update);
        self::assertStringContainsString("'venue_profile_main_label_migrated','1'", $update);
        self::assertStringContainsString("'venue_profile_capacity_migrated','1'", $update);
        foreach (array('#2F6F9F', '#B78324', '#8A6D3B') as $defaultColour) {
            self::assertStringContainsString("DEFAULT '" . $defaultColour . "'", $install);
            self::assertStringContainsString("DEFAULT '" . $defaultColour . "'", $update);
        }
    }

    public function testInstallerRepairsCapacitySchemaWithoutInventingProfiles(): void
    {
        $script = $this->read('/script.php');

        foreach (self::VENUE_TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . $table . '`', $script);
            self::assertStringContainsString("'" . $table . "'", $script);
        }

        self::assertStringContainsString('migrateExistingVenueCapacityProfiles($db)', $script);
        self::assertStringNotContainsString('installDefaultVenueCapacityProfiles($db)', $script);
        self::assertStringContainsString("'DELETE FROM ' . \$db->quoteName('#__jem_venue_capacity_profiles')", $script);
        self::assertStringContainsString("\$db->quoteName('#__jem_event_space_layouts')", $script);
        self::assertStringContainsString('backfillEventSpaceLayouts($db)', $script);
        self::assertStringContainsString("'#__jem_event_space_layouts'", $script);
        self::assertStringContainsString('$db->quote(\'Main\')', $script);
        self::assertStringContainsString('$db->quote(\'Default configuration\')', $script);
        self::assertStringContainsString('$migrationKey = \'venue_profile_main_label_migrated\'', $script);
        self::assertStringContainsString("\$capacityMigrationKey = 'venue_profile_capacity_migrated'", $script);
        self::assertStringContainsString("isset(\$profileColumns['capacity'])", $script);
        self::assertStringContainsString("isset(\$columns['color'])", $script);
        self::assertStringContainsString("'venue_snapshot'", $script);
        self::assertStringNotContainsString('venue_configuration_snapshot', $script);
    }

    public function testEventAndVenueServicesKeepPhysicalAndCommercialDataSeparate(): void
    {
        $venueService = $this->read('/admin/classes/venuecapacity.class.php');
        $eventService = $this->read('/admin/classes/eventpricingcapacity.class.php');

        self::assertStringContainsString("'schema'             => 'jem-venue-capacity/v1'", $venueService);
        self::assertStringContainsString("'profile_capacity'", $venueService);
        self::assertStringContainsString('DEFAULT_SPACE_COLOR', $venueService);
        self::assertStringContainsString('normaliseColor', $venueService);
        self::assertStringContainsString("'spaces'", $venueService);
        self::assertStringContainsString("'capacity_areas'", $venueService);
        self::assertStringContainsString('layoutFingerprint', $venueService);
        self::assertStringContainsString('saveDefaultConfiguration', $venueService);
        self::assertStringContainsString('getEventConfigurationOptions', $venueService);
        self::assertStringContainsString('selected_capacity', $venueService);
        self::assertStringContainsString("'country_code'", $venueService);
        self::assertStringContainsString('profile_space_id', $venueService);

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
        self::assertStringContainsString('saveEventAssignments', $eventService);
        self::assertStringContainsString('#__jem_event_space_layouts', $eventService);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
