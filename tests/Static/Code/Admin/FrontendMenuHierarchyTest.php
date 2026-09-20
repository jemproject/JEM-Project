<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontendMenuHierarchyTest extends TestCase
{
    public function testGeneratedGroupsUseParentLinksThatMenuTemplatesCanNest(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        foreach (array(
            array('Events', 'events'),
            array('Calendars', 'calendars'),
            array('Venues', 'venues'),
            array('Categories', 'categories'),
            array('Types', 'types'),
            array('Management', 'management'),
            array('User Area', 'user-area'),
        ) as $group) {
            self::assertStringContainsString(
                "'" . $group[0] . "', '" . $group[1] . "', '#', \$rootId, 'url', 0",
                $controller
            );
        }

        self::assertStringContainsString("'parent_id'    => (int) \$parentId", $controller);
        self::assertStringContainsString("setLocation((int) \$parentId, 'last-child')", $controller);
    }

    public function testGeneratedManagementGroupLinksToJoomlaFieldsAndFieldGroups(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini');

        self::assertStringContainsString('use Joomla\\CMS\\Uri\\Uri;', $controller);
        self::assertStringContainsString(
            "\$administratorUrl = rtrim(Uri::root(true), '/') . '/administrator/index.php';",
            $controller
        );
        self::assertStringContainsString(
            "'Fields', 'fields', \$administratorUrl . '?option=com_fields&view=fields&context=com_jem.event', \$groups['management'], 'url', 0",
            $controller
        );
        self::assertStringContainsString(
            "'Field Groups', 'field-groups', \$administratorUrl . '?option=com_fields&view=groups&context=com_jem.event', \$groups['management'], 'url', 0",
            $controller
        );
        self::assertStringContainsString('COM_JEM_FRONTEND_MENU_FIELDS="Fields"', $language);
        self::assertStringContainsString('COM_JEM_FRONTEND_MENU_FIELD_GROUPS="Field Groups"', $language);
    }
}
