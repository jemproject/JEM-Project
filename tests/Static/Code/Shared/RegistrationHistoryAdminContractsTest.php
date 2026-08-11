<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationHistoryAdminContractsTest extends TestCase
{
    public function testDedicatedHistoryPermissionAndControlPanelEntryExist(): void
    {
        $access = $this->read('/admin/access.xml');
        $helper = $this->read('/admin/helpers/helper.php');
        $dashboard = $this->read('/admin/views/main/tmpl/default.php');

        self::assertStringContainsString('jem.registrations.history', $access);
        self::assertStringContainsString("canManage('jem.registrations.history')", $helper);
        self::assertStringContainsString('view=registrationhistory', $dashboard);
    }

    public function testHistoryViewIsReadOnlyAndProvidesRequiredFilters(): void
    {
        $model = $this->read('/admin/models/registrationhistory.php');
        $template = $this->read('/admin/views/registrationhistory/tmpl/default.php');

        foreach (array('filter_search', 'filter_action', 'filter_source', 'filter_status', 'filter_event_id', 'filter_actor_id', 'filter_orphaned', 'filter_begin', 'filter_end') as $filter) {
            self::assertStringContainsString($filter, $model);
            self::assertStringContainsString($filter, $template);
        }

        self::assertStringNotContainsString('Joomla.checkAll', $template);
        self::assertStringNotContainsString('name="cid[]"', $template);
        self::assertStringContainsString('view=registrationhistoryentry', $template);
    }

    public function testUserPluginGuardsActiveFutureBookingsAndIsPackaged(): void
    {
        $plugin = $this->read('/plugins/plg_user_jem/src/Extension/Jem.php');
        $package = $this->read('/package/pkg_jem.xml');
        $installer = $this->read('/package/pkg_install.php');
        $builder = $this->read('/scripts/build-packages.php');

        self::assertStringContainsString("'onUserBeforeDelete'", $plugin);
        self::assertStringContainsString("quoteName('r.status') . ' = 1'", $plugin);
        self::assertStringContainsString('throw new RuntimeException', $plugin);
        self::assertStringContainsString('plg_user_jem.zip', $package);
        self::assertStringContainsString("enablePlugin('user', 'jem')", $installer);
        self::assertStringContainsString("'plugins/plg_user_jem'", $builder);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $path);
        self::assertNotFalse($contents, $path . ' must be readable.');

        return $contents;
    }
}
