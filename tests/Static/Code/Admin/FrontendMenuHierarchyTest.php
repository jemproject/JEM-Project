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
                "createMenuItem(\$menutype, '" . $group[0] . "', '" . $group[1] . "', '#', \$rootId, 'url', 0",
                $controller
            );
        }

        self::assertStringContainsString("'parent_id'    => (int) \$parentId", $controller);
        self::assertStringContainsString("setLocation((int) \$parentId, 'last-child')", $controller);
    }
}
