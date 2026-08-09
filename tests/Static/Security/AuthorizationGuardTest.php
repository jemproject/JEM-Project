<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthorizationGuardTest extends TestCase
{
    public function testFrontendEventAndVenueControllersUseJemPermissionChecks(): void
    {
        $event = (string) file_get_contents(JEM_TEST_ROOT . '/site/controllers/event.php');
        $venue = (string) file_get_contents(JEM_TEST_ROOT . '/site/controllers/venue.php');

        self::assertStringContainsString("JemFrontendAccess::canAdd(\$user, 'event'", $event);
        self::assertStringContainsString("JemFrontendAccess::canEdit(\$user, 'event', \$record)", $event);
        self::assertStringContainsString("JemFrontendAccess::canAdd(\$user, 'venue'", $venue);
        self::assertStringContainsString("JemFrontendAccess::canEdit(\$user, 'venue', \$record)", $venue);
    }

    public function testAdminDeleteControllersCheckTheResourceOrToolPermission(): void
    {
        $files = array(
            'admin/controllers/categories.php' => 'core.delete',
            'admin/controllers/groups.php' => 'core.delete',
            'admin/controllers/types.php' => 'core.delete',
            'admin/controllers/venues.php' => "JemHelperBackend::can('venue', 'delete')",
            'admin/controllers/imagehandler.php' => "canManage('jem.tools.manage')",
            'admin/controllers/housekeeping.php' => "canManage('jem.tools.manage')",
        );

        foreach ($files as $file => $permission) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $file);

            self::assertStringContainsString($permission, $contents, $file . ' should check ' . $permission);
        }
    }

    public function testModelPermissionMethodsGateEventVenueAndCategoryActions(): void
    {
        $contracts = array(
            'admin/models/event.php' => array("JemHelperBackend::can('event', 'delete'", "JemHelperBackend::can('event', 'edit.state'"),
            'admin/models/venue.php' => array("JemHelperBackend::can('venue', 'delete'", "JemHelperBackend::can('venue', 'edit.state'"),
            'admin/models/category.php' => array("authorise('core.delete'", "authorise('core.edit.state'"),
        );

        foreach ($contracts as $file => $needles) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $file);

            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $contents, $file . ' should contain ' . $needle);
            }
        }
    }
}
