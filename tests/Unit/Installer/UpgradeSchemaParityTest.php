<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UpgradeSchemaParityTest extends TestCase
{
    public function testVenueCapacityProfileDefaultIndexMatchesFreshInstallation(): void
    {
        $installSql = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $upgradeSql = file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        $expected = '`venue_id`,`is_default`,`published`,`ordering`';

        self::assertSame($expected, $this->venueCapacityProfileDefaultIndex($installSql));
        self::assertSame($expected, $this->venueCapacityProfileDefaultIndex($upgradeSql));
    }

    private function venueCapacityProfileDefaultIndex(string $sql): string
    {
        self::assertSame(1, preg_match(
            '/CREATE TABLE IF NOT EXISTS `#__jem_venue_capacity_profiles` \((.*?)\) ENGINE=InnoDB;/s',
            $sql,
            $table
        ));
        self::assertMatchesRegularExpression('/`ordering`\s+INT\(11\)\s+NOT NULL/i', $table[1]);
        self::assertSame(1, preg_match(
            '/KEY `idx_venue_capacity_profile_default` \(([^)]+)\)/',
            $table[1],
            $index
        ));

        return preg_replace('/\s+/', '', $index[1]);
    }
}
