<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthorizationGuardTest extends TestCase
{
    public function testFrontendEventAndVenueControllersUseJemPermissionChecks(): void
    {
        $event = (string) file_get_contents(JEM_TEST_ROOT . '/site/controllers/event.php');
        $venue = (string) file_get_contents(JEM_TEST_ROOT . '/site/controllers/venue.php');

        self::assertStringContainsString("\$user->can('add', 'event'", $event);
        self::assertStringContainsString("\$user->can('edit', 'event', \$recordId, \$created_by)", $event);
        self::assertStringContainsString("\$user->can('add', 'venue')", $venue);
        self::assertStringContainsString("\$user->can('edit', 'venue', \$recordId, \$created_by)", $venue);
    }

    public function testAdminDeleteControllersCheckDeleteOrManagePermission(): void
    {
        $files = array(
            'admin/controllers/categories.php' => 'core.delete',
            'admin/controllers/groups.php' => 'core.delete',
            'admin/controllers/types.php' => 'core.delete',
            'admin/controllers/venues.php' => 'core.delete',
            'admin/controllers/imagehandler.php' => 'core.manage',
            'admin/controllers/housekeeping.php' => 'core.manage',
        );

        foreach ($files as $file => $permission) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $file);

            self::assertStringContainsString($permission, $contents, $file . ' should check ' . $permission);
        }
    }

    public function testModelPermissionMethodsGateEventVenueAndCategoryActions(): void
    {
        $contracts = array(
            'admin/models/event.php' => array("can('delete', 'event'", "can('publish', 'event'"),
            'admin/models/venue.php' => array("authorise('core.delete'", "authorise('core.edit.state'"),
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
