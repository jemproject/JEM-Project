<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UninstallSchemaParityTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/uninstall_environment.php';
        require_once JEM_TEST_ROOT . '/script.php';
        define('JPATH_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        // Deliberately nonexistent fixture path, never the user's Joomla installation.
        define('JPATH_SITE', JEM_TEST_ROOT . '/tests/fixtures/uninstall-site-not-present');
        Factory::$database = new UninstallDatabase();
    }

    private function installedTables(): array
    {
        $sql = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`(#__jem_[a-z0-9_]+)`/i', $sql, $matches);
        self::assertNotEmpty($matches[1]);

        return array_values(array_unique($matches[1]));
    }

    private function cleanup(): array
    {
        $method = new ReflectionMethod(com_jemInstallerScript::class, 'removeAllJemTables');
        $method->invoke(new com_jemInstallerScript());

        return Factory::$database->dropped;
    }

    public function testFullCleanupCoversEveryFreshInstallTable(): void
    {
        $tables = $this->installedTables();
        $dropped = $this->cleanup();
        self::assertSame([], array_values(array_diff($tables, $dropped)), 'Installed JEM tables missing from full cleanup.');
        self::assertSame($dropped, array_values(array_unique($dropped)), 'Duplicate cleanup targets.');
    }

    public function testCleanupCoversTablesCreatedByUpdatesAndRepairMethods(): void
    {
        $sources = glob(JEM_TEST_ROOT . '/admin/sql/updates/mysql/*.sql');
        $sources[] = JEM_TEST_ROOT . '/script.php';
        $created = [];
        foreach ($sources as $source) {
            $code = file_get_contents($source);
            // Direct SQL and PHP SQL concatenated through $db->quoteName('...').
            $pattern = <<<'REGEX'
~CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`(#__jem_[a-z0-9_]+)`|['"]\s*\.\s*\$db->quoteName\(['"](#__jem_[a-z0-9_]+)['"]\))~i
REGEX;
            preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $created[] = $match[1] !== '' ? $match[1] : $match[2];
            }
        }
        self::assertNotEmpty($created);
        self::assertContains('#__jem_event_series', $created, 'Must inspect repair SQL, not only the installation schema.');
        self::assertSame([], array_values(array_diff(array_unique($created), $this->cleanup())));
    }

    #[DataProvider('preservationSettings')]
    public function testUninstallPreservesTablesUnlessCleanupIsEnabled(string $settings): void
    {
        $tables = array_fill_keys($this->installedTables(), true);
        Factory::$database->tables = $tables;
        Factory::$database->settings = $settings;
        ob_start();
        try {
            (new com_jemInstallerScript())->uninstall(null);
        } finally {
            ob_end_clean();
        }
        self::assertSame([], Factory::$database->dropped);
        self::assertSame($tables, Factory::$database->tables);
    }

    public static function preservationSettings(): array
    {
        return [['{}'], ['{"global_cleanup_db_on_uninstall":0}'], ['{"global_cleanup_db_on_uninstall":"0"}']];
    }

    public function testOptedInUninstallRemovesOwnedTablesButNotThirdPartyTables(): void
    {
        $foreign = ['#__users' => true, '#__content' => true, '#__jem_thirdparty_data' => true];
        Factory::$database->tables = array_fill_keys($this->installedTables(), true) + $foreign;
        Factory::$database->settings = '{"global_cleanup_db_on_uninstall":1}';
        ob_start();
        try {
            (new com_jemInstallerScript())->uninstall(null);
        } finally {
            ob_end_clean();
        }
        self::assertSame($foreign, Factory::$database->tables);
    }

    public function testPartialInstallationAndRepeatedCleanupAreSafe(): void
    {
        Factory::$database->tables = ['#__jem_event_series' => true];
        if (in_array('#__jem_age_levels', $this->installedTables(), true)) {
            Factory::$database->tables['#__jem_age_levels'] = true;
        }
        $this->cleanup();
        self::assertSame([], Factory::$database->tables);
        $this->cleanup();
        self::assertSame([], Factory::$database->tables);
    }

    public function testOneDropFailureDoesNotPreventRemainingCleanup(): void
    {
        Factory::$database->tables = array_fill_keys($this->installedTables(), true);
        Factory::$database->failOn = '#__jem_attachments';
        $this->cleanup();
        self::assertSame(['#__jem_attachments' => true], Factory::$database->tables);
    }
}
