<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontendMenuHierarchyTest extends TestCase
{
    public function testGeneratorNestsOnlyViewsWithMultipleLayoutsAndPreservesExistingAliases(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

        self::assertStringContainsString("'day-venue-board', 'index.php?option=com_jem&view=day&layout=venueboard&id=0'", $controller);
        self::assertStringContainsString("\$viewLayouts[\$group . ':' . \$view][(string) (\$parts['layout'] ?? 'default')] = true;", $controller);
        self::assertStringContainsString('if (count($viewLayouts[$key]) > 1)', $controller);
        self::assertStringContainsString("'view-' . \$group . '-' . \$view", $controller);
        self::assertStringContainsString("\$parentId = \$viewHeadings[\$key];", $controller);
        self::assertStringContainsString("'heading',", $controller);
        self::assertStringContainsString("\$this->createMenuItem(\$menutype, \$item[0], \$item[1], \$item[2], \$item[3], \$parentId", $controller);
        self::assertStringContainsString("\$singleLayoutHeadings[\$key] = array(\$item[4], 'view-' . \$group . '-' . \$view);", $controller);
        self::assertStringContainsString('unpublishEmptyGeneratedViewHeading($menutype, $groupId, $alias)', $controller);
        self::assertStringContainsString("\$parentId === \$groups['calendars'] ? \$menuItemId : \$parentId", $controller);
    }

    public function testOldSingleLayoutHeadingIsHiddenOnlyWhenItHasNoPublishedChildren(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');
        $method = substr(
            $controller,
            strpos($controller, 'protected function unpublishEmptyGeneratedViewHeading('),
            strpos($controller, 'protected function unpublishGeneratedMenuItems(')
                - strpos($controller, 'protected function unpublishEmptyGeneratedViewHeading(')
        );

        self::assertStringContainsString("->where(\$db->quoteName('parent_id') . ' = ' . \$groupId)", $method);
        self::assertStringContainsString("->where(\$db->quoteName('type') . ' = ' . \$db->quote('heading'))", $method);
        self::assertStringContainsString("->where(\$db->quoteName('link') . ' = ' . \$db->quote('#'))", $method);
        self::assertStringContainsString("->where(\$db->quoteName('published') . ' = 1')", $method);
        self::assertStringContainsString('if ((int) $db->loadResult() > 0)', $method);
        self::assertStringContainsString("->set(\$db->quoteName('published') . ' = 0')", $method);
    }

    public function testGeneratedModuleLayoutsAreIsolatedToTheirOwnPublishedMenuItems(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/modulepreview/view.html.php');
        $metadata = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/modulepreview/metadata.xml');

        self::assertStringContainsString("'modules'    => \$this->createMenuItem", $controller);
        foreach (['mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee', 'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide'] as $module) {
            self::assertStringContainsString("'" . $module . "'", $controller);
        }
        self::assertStringContainsString("'jem-menu-preview'", $controller);
        self::assertStringContainsString("\$moduleAlias = str_replace('_', '-', \$module);", $controller);
        self::assertStringContainsString("'module-' . \$moduleAlias", $controller);
        self::assertStringContainsString("'layout-' . \$moduleAlias", $controller);
        self::assertStringContainsString("'published'", $controller);
        self::assertStringContainsString("\$this->assignGeneratedModuleToMenu(\$moduleId, \$menuItemId)", $controller);
        self::assertStringContainsString("->where(\$db->quoteName('moduleid') . ' = ' . \$moduleId)", $controller);
        self::assertStringContainsString("->values(\$moduleId . ',' . \$menuItemId)", $controller);
        self::assertStringContainsString("ModuleHelper::getModules('jem-menu-preview')", $view);
        self::assertStringContainsString("\$moduleId !== \$menuModuleId", $view);
        self::assertStringContainsString('hidden="true"', $metadata);
    }

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
