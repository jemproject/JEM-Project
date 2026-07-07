<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SampleDataOwnershipTest extends TestCase
{
    private const REASSIGNED_TABLES = array(
        '#__jem_events',
        '#__jem_venues',
        '#__jem_types',
        '#__jem_links',
        '#__jem_attachments',
    );

    public function testSampleDataOwnershipUsesCurrentUser(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');

        self::assertStringContainsString('assignCurrentUserId', $model);
        self::assertStringNotContainsString('name LIKE "Super User"', $model);

        foreach (self::REASSIGNED_TABLES as $table) {
            self::assertStringContainsString($table, $model, $table . ' must have sample ownership reassigned.');
        }
    }

    public function testSampleDataTablesUsingPlaceholderOwnerAreReassigned(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');

        $tables = array();

        preg_match_all('/INSERT INTO\s+`([^`]+)`\s*\(([^)]*`created_by`[^)]*)\)\s+VALUES\b.*?62\)/is', $sql, $insertMatches);
        $tables = array_merge($tables, $insertMatches[1]);

        preg_match_all('/UPDATE\s+`([^`]+)`\s+SET\b.*?created_by\s*=\s*62/is', $sql, $updateMatches);
        $tables = array_merge($tables, $updateMatches[1]);

        foreach (array('#__jem_events', '#__jem_venues') as $legacyValuesTable) {
            if (preg_match('/INSERT INTO\s+`' . preg_quote($legacyValuesTable, '/') . '`\s+VALUES\b/is', $sql)) {
                $tables[] = $legacyValuesTable;
            }
        }

        $tables = array_values(array_unique($tables));
        sort($tables);

        $missing = array_values(array_diff($tables, self::REASSIGNED_TABLES));

        self::assertSame(
            array(),
            $missing,
            "Sample data tables with created_by placeholder 62 must be reassigned:\n" . implode("\n", $missing)
        );
    }
}
