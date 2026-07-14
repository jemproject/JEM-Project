<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqlMigrationBaselineTest extends TestCase
{
    public function testJem440MigrationBaselineIsShipped(): void
    {
        $files = glob(JEM_TEST_ROOT . '/admin/sql/updates/mysql/*.sql') ?: array();
        $relative = array();

        foreach ($files as $path) {
            $version = basename($path, '.sql');

            if (version_compare($version, '4.4.1', 'lt')) {
                $relative[] = str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
            }
        }

        self::assertSame(array(), $relative, "JEM 5 supports upgrades from JEM 4.4.0 and ships the required SQL steps starting at 4.4.1; older SQL scripts must not be shipped:\n" . implode("\n", $relative));

        foreach (array('4.4.1.sql', '4.4.2.sql', '4.5.0.sql', '5.0.0.sql') as $file) {
            self::assertFileExists(JEM_TEST_ROOT . '/admin/sql/updates/mysql/' . $file);
        }

        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/admin/sql/updates/mysql/4.4.0.sql');
    }

    public function testInstallerAllowsJem440BeforeUpdate(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString("\$minUpgradeVersion = '4.4.0';", $script);
        self::assertStringContainsString("version_compare(\$this->oldRelease, \$minUpgradeVersion, 'lt')", $script);
        self::assertStringContainsString('COM_JEM_PREFLIGHT_UNSUPPORTED_UPGRADE_VERSION', $script);
        self::assertStringContainsString("version_compare(\$version, '4.4.1', 'lt')", $script);
    }
}
