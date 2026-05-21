<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqlMigrationBaselineTest extends TestCase
{
    public function testNoSqlUpdateScriptsOlderThanJem450AreShipped(): void
    {
        $files = glob(JEM_TEST_ROOT . '/admin/sql/updates/mysql/*.sql') ?: array();
        $relative = array();

        foreach ($files as $path) {
            $version = basename($path, '.sql');

            if (version_compare($version, '4.5.0', 'lt')) {
                $relative[] = str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
            }
        }

        self::assertSame(array(), $relative, "JEM 5 migration requires JEM 4.5.0 or newer; older SQL scripts must not be shipped:\n" . implode("\n", $relative));
    }

    public function testInstallerRequiresJem450BeforeUpdate(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString("\$minUpgradeVersion = '4.5.0';", $script);
        self::assertStringContainsString("version_compare(\$this->oldRelease, \$minUpgradeVersion, 'lt')", $script);
        self::assertStringContainsString('COM_JEM_PREFLIGHT_UNSUPPORTED_UPGRADE_VERSION', $script);
    }
}
