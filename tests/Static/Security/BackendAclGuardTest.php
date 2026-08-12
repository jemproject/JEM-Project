<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendAclGuardTest extends TestCase
{
    public function testAccessXmlDeclaresIndependentEventVenueAndAdministrationActions(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/access.xml');
        self::assertNotFalse($xml);

        $actions = array();
        foreach ($xml->section->action as $action) {
            $actions[] = (string) $action['name'];
        }

        $expected = array(
            'core.options',
            'jem.events.access',
            'jem.events.create',
            'jem.events.delete',
            'jem.events.edit',
            'jem.events.edit.state',
            'jem.events.edit.own',
            'jem.events.edit.created',
            'jem.venues.access',
            'jem.venues.create',
            'jem.venues.delete',
            'jem.venues.edit',
            'jem.venues.edit.state',
            'jem.venues.edit.own',
            'jem.venues.edit.created',
            'jem.attendees.manage',
            'jem.tools.manage',
        );

        foreach ($expected as $action) {
            self::assertContains($action, $actions);
        }
    }

    public function testBackendEventAndVenueMutationsUseTheCentralPolicy(): void
    {
        $contracts = array(
            'admin/controllers/event.php' => array("can('event', 'create')", "can('event', 'edit', \$record)"),
            'admin/controllers/venue.php' => array("can('venue', 'create')", "can('venue', 'edit', \$record)"),
            'admin/models/event.php' => array("can('event', 'delete'", "can('event', 'edit.state'", "can('event', 'edit.created'"),
            'admin/models/venue.php' => array("can('venue', 'delete'", "can('venue', 'edit.state'", "can('venue', 'edit.created'"),
            'admin/views/events/view.html.php' => array("can('event', 'access')"),
            'admin/views/venues/view.html.php' => array("can('venue', 'access')"),
        );

        foreach ($contracts as $file => $needles) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $file);

            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $contents, $file);
            }
        }
    }

    public function testBackendResourcePolicyDoesNotDelegateToFrontendJemUserCan(): void
    {
        $files = array(
            'admin/controllers/event.php',
            'admin/controllers/events.php',
            'admin/controllers/venue.php',
            'admin/controllers/venues.php',
            'admin/models/event.php',
            'admin/models/venue.php',
            'admin/views/event/view.html.php',
            'admin/views/events/view.html.php',
            'admin/views/venue/view.html.php',
            'admin/views/venues/view.html.php',
        );

        foreach ($files as $file) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $file);
            self::assertStringNotContainsString('->can(', $contents, $file);
        }
    }

    public function testUpdateInstallerPreservesLegacyManagerGroupsWithoutOverwritingExplicitRules(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString("Access::checkGroup(\$groupId, 'core.manage', 'com_jem')", $script);
        self::assertStringContainsString('if ($existing !== null)', $script);
        self::assertStringContainsString("'jem.events.access'", $script);
        self::assertStringContainsString("'jem.venues.access'", $script);
        self::assertStringContainsString("'jem.attendees.manage'", $script);
        self::assertStringContainsString("'jem.tools.manage'", $script);
        self::assertStringContainsString("'jem.events.edit.created' => 'core.edit'", $script);
        self::assertStringContainsString("'jem.venues.edit.created' => 'core.edit'", $script);
    }

    public function testEventAndVenueFormsGateTheAuthorFieldWithTheDedicatedPermissionOnly(): void
    {
        $files = array(
            'admin/models/event.php' => "JemHelperBackend::can('event', 'edit.created')",
            'admin/models/venue.php' => "JemHelperBackend::can('venue', 'edit.created')",
        );

        foreach ($files as $file => $needle) {
            $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $file);

            self::assertStringContainsString($needle, $contents, $file);
            self::assertStringNotContainsString("authorise('core.manage', 'com_users')", $contents, $file);
        }
    }
}
