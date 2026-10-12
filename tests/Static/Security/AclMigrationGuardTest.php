<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AclMigrationGuardTest extends TestCase
{
    public function testRecoveryIsAnExplicitProtectedPostAction(): void
    {
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/aclmigration.php');
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $view = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php');
        self::assertStringContainsString('JemHelper::requirePostToken();', $controller);
        self::assertStringContainsString("authorise('core.admin', 'com_jem')", $controller);
        self::assertLessThan(strpos($controller, 'JemAclMigration::run'), strpos($controller, 'requirePostToken'));
        self::assertStringContainsString("\$method !== 'POST'", $helper);
        self::assertStringContainsString("Session::checkToken('post')", $helper);
        self::assertStringContainsString('method="post"', $view);
        self::assertStringContainsString('name="task" value="aclmigration.resume"', $view);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $view);
    }

    public function testEssentialSettingsCatalogueIsInstalledBeforeAclMigration(): void
    {
        $script = file_get_contents(JEM_TEST_ROOT . '/script.php');
        self::assertLessThan(strpos($script, '$this->repair510ResourceAcl();'), strpos($script, '$this->installCountryCurrencyCatalogue();'));
        $field = file_get_contents(JEM_TEST_ROOT . '/admin/models/fields/currencyoptions.php');
        self::assertStringContainsString('country-currencies.json', $field);
        self::assertStringContainsString('$currencies[$selected] = true;', $field);
    }

    public function testPendingStateGuardsFrontendAndBackendResourceRequests(): void
    {
        foreach (['site/jem.php', 'admin/jem.php', 'admin/helpers/helper.php'] as $path) {
            self::assertStringContainsString('JemAclMigrationStatus::pending()', file_get_contents(JEM_TEST_ROOT . '/' . $path));
        }
        $config = file_get_contents(JEM_TEST_ROOT . '/site/classes/config.class.php');
        self::assertStringContainsString("\$k === 'acl_migration_state'", $config);
        $runner = file_get_contents(JEM_TEST_ROOT . '/admin/classes/aclassetmigration.class.php');
        self::assertStringContainsString('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', $runner);
        self::assertStringNotContainsString('LOCK TABLES', $runner);
        self::assertStringContainsString('$db->transactionRollback();', $runner);
    }
}
