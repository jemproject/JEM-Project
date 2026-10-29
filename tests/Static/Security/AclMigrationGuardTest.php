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

    public function testAssetTreeValidationNamesTheFirstBadAsset(): void
    {
        require_once JEM_TEST_ROOT . '/admin/classes/aclassetmigration.class.php';

        $assets = array(
            array('id' => 1, 'name' => 'root.1', 'parent_id' => 0, 'level' => 0, 'lft' => 0, 'rgt' => 5),
            array('id' => 2, 'name' => 'com_jem', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'rgt' => 4),
            array('id' => 3, 'name' => 'com_jem.category.3', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'rgt' => 3),
        );
        JemAclAssetMigration::validateTree($assets);

        $assets[2]['parent_id'] = 9999;
        try {
            JemAclAssetMigration::validateTree($assets);
            self::fail('An invalid parent must be reported.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('asset #3 "com_jem.category.3"', $error->getMessage());
            self::assertStringContainsString('parent_id 9999', $error->getMessage());
        }

        $assets[2]['parent_id'] = 2;
        $assets[2]['lft'] = 3;
        $assets[2]['rgt'] = 2;
        try {
            JemAclAssetMigration::validateTree($assets);
            self::fail('Invalid coordinates must be reported.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('asset #3 "com_jem.category.3"', $error->getMessage());
            self::assertStringContainsString('invalid lft/rgt interval', $error->getMessage());
        }
    }

    public function testMigrationIgnoresOnlyForeignParentMetadataErrors(): void
    {
        require_once JEM_TEST_ROOT . '/admin/classes/aclassetmigration.class.php';

        $assets = [
            ['id' => 1, 'name' => 'root.1', 'parent_id' => 0, 'level' => 0, 'lft' => 0, 'rgt' => 11],
            ['id' => 2, 'name' => 'com_contact', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'rgt' => 6],
            ['id' => 3, 'name' => 'com_contact.category.38', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'rgt' => 5],
            ['id' => 4, 'name' => 'com_contact.contact.7', 'parent_id' => 2, 'level' => 4, 'lft' => 3, 'rgt' => 4],
            ['id' => 5, 'name' => 'com_jem', 'parent_id' => 1, 'level' => 1, 'lft' => 7, 'rgt' => 10],
            ['id' => 6, 'name' => 'com_jem.category.6', 'parent_id' => 5, 'level' => 2, 'lft' => 8, 'rgt' => 9],
        ];

        JemAclAssetMigration::validateForJemMigration($assets);
        try {
            JemAclAssetMigration::validateTree($assets);
            self::fail('Diagnostics must still report the foreign asset error.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('com_contact.contact.7', $error->getMessage());
        }

        $assets[5]['parent_id'] = 3;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('com_jem.category.6');
        JemAclAssetMigration::validateForJemMigration($assets);
    }

    public function testMigrationStillRejectsForeignCoordinateErrors(): void
    {
        require_once JEM_TEST_ROOT . '/admin/classes/aclassetmigration.class.php';

        $assets = [
            ['id' => 1, 'name' => 'root.1', 'parent_id' => 0, 'level' => 0, 'lft' => 0, 'rgt' => 7],
            ['id' => 2, 'name' => 'com_contact', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'rgt' => 4],
            ['id' => 3, 'name' => 'com_contact.category.38', 'parent_id' => 99, 'level' => 4, 'lft' => 2, 'rgt' => 5],
            ['id' => 4, 'name' => 'com_jem', 'parent_id' => 1, 'level' => 1, 'lft' => 5, 'rgt' => 6],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('overlaps asset #2');
        JemAclAssetMigration::validateForJemMigration($assets);
    }

    public function testDiagnosticsOnlyInspectsPermissionMigrationAndAssetTree(): void
    {
        $diagnostics = file_get_contents(JEM_TEST_ROOT . '/admin/classes/housekeepingdiagnostics.class.php');
        $start = strpos($diagnostics, 'private function checkPermissionMigration(');
        $end = strpos($diagnostics, 'private function checkComponent(', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $check = substr($diagnostics, $start, $end - $start);

        self::assertStringContainsString("'permissions' => 'checkPermissionMigration'", $diagnostics);
        self::assertStringContainsString('JemAclAssetMigration::validateTree($assets)', $check);
        self::assertStringContainsString("'COM_JEM_TOOLS_ACL_MIGRATION_PENDING'", $check);
        self::assertStringNotContainsString('JemAclMigration::run(', $check);
        self::assertDoesNotMatchRegularExpression('/\\b(?:UPDATE|INSERT|DELETE|REPLACE)\\s+(?:INTO\\s+|FROM\\s+)?#__assets\\b/i', $check);
    }
}
