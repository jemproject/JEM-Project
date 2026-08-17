<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousekeepingDataResetTest extends TestCase
{
    public function testAllContentAndDependentTablesAreReset(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/housekeeping.php');

        foreach (array(
            'notifications_attempts',
            'notifications',
            'register_items',
            'register_history',
            'register',
            'event_prices',
            'capacity_pools',
            'event_space_layouts',
            'venue_capacity_areas',
            'venue_profile_spaces',
            'venue_layouts',
            'venue_spaces',
            'venue_capacity_profiles',
            'cats_event_relations',
            'event_series',
            'attachments',
            'links',
            'events',
            'groupmembers',
            'groups',
            'special_days',
            'categories',
            'venues',
            'types',
        ) as $table) {
            self::assertMatchesRegularExpression(
                "/['\"]" . preg_quote($table, '/') . "['\"]/",
                $code,
                '#__jem_' . $table . ' must be reset by Housekeeping.'
            );
        }

        self::assertStringContainsString('DELETE FROM #__jem_reminders WHERE event_id <> 0', $code);
        self::assertStringContainsString("\$db->quote('sample_showcase_catalog')", $code);
        self::assertStringNotContainsString("'tax_rates',", $code);
        self::assertStringNotContainsString("'countries',", $code);
        self::assertStringNotContainsString("'config',", $code);
    }
}
