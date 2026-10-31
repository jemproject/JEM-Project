<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Guard portable timestamp defaults without breaking Joomla's schema checker.
 */
final class SqlTimestampDefaultTest extends TestCase
{
    /**
     * Reject the MariaDB-only bare expression in every shipped update script.
     *
     * @return void
     */
    public function testAlterColumnTimestampExpressionsAreParenthesized(): void
    {
        $files = glob(JEM_TEST_ROOT . '/admin/sql/updates/mysql/*.sql') ?: [];
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/\bALTER\s+(?:COLUMN\s+)?(?:`[^`]+`|\w+)\s+SET\s+DEFAULT\s+(?:CURRENT_TIMESTAMP\b|NOW\s*\()/i',
                (string) file_get_contents($file),
                basename($file) . ' must use a parenthesized default expression on MySQL.'
            );
        }
    }

    /**
     * Keep null history and the expression separate from MODIFY default checks.
     *
     * @return void
     */
    public function test502RetainsNullableHistoryAndPortableExpression(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.2.sql');
        self::assertStringContainsString(
            'ALTER TABLE `#__jem_special_days` MODIFY `created` DATETIME NULL;',
            $sql
        );
        self::assertStringContainsString(
            'ALTER TABLE `#__jem_special_days` ALTER COLUMN `created` SET DEFAULT (CURRENT_TIMESTAMP);',
            $sql
        );
    }
}
