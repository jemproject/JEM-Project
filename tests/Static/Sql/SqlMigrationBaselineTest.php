<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqlMigrationBaselineTest extends TestCase
{
    public function testImageAsDefaultIsIntroducedOnlyByTheJem500Migration(): void
    {
        $files = glob(JEM_TEST_ROOT . '/admin/sql/updates/mysql/*.sql') ?: array();
        usort($files, static function (string $left, string $right): int {
            return version_compare(basename($left, '.sql'), basename($right, '.sql'));
        });

        $additions = array();

        foreach ($files as $path) {
            $sql = (string) file_get_contents($path);
            preg_match_all(
                '/ALTER\s+TABLE\s+`?(#__jem_categories)`?\s+ADD\s+COLUMN\s+`?(image_as_default)`?[^;]*;/i',
                $sql,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $additions[] = basename($path) . ': ' . trim($match[0]);
            }
        }

        self::assertSame(
            array(
                '5.0.0.sql: ALTER TABLE `#__jem_categories` ADD COLUMN `image_as_default` tinyint(1) NOT NULL DEFAULT 0;',
            ),
            $additions,
            'image_as_default must be introduced by the JEM 5.0.0 migration.'
        );
    }

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

        foreach (array('4.4.1.sql', '4.4.2.sql', '4.5.0.sql', '5.0.0.sql', '5.0.1.sql', '5.1.0.sql') as $file) {
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
