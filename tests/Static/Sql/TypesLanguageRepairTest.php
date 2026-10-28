<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TypesLanguageRepairTest extends TestCase
{
    public function testMissingTypesLanguageIsRepairedOnUpgradeAndReinstall(): void
    {
        $updateSql = file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $installer = file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString(
            "ALTER TABLE `#__jem_types` ADD COLUMN `language` CHAR(7) NOT NULL DEFAULT '*' /** CAN FAIL **/;",
            $updateSql
        );
        self::assertStringContainsString("'#__jem_types' => array(", $installer);
        self::assertStringContainsString("'language' => \"CHAR(7) NOT NULL DEFAULT '*'\"", $installer);
    }
}
