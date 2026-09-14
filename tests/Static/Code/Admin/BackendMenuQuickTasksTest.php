<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendMenuQuickTasksTest extends TestCase
{
    public function testManifestKeepsOneEventsManagerEntry(): void
    {
        $manifest = (string) file_get_contents(JEM_TEST_ROOT . '/jem.xml');

        self::assertSame(1, substr_count($manifest, '>COM_JEM_MENU_EVENTS</menu>'));
        self::assertStringNotContainsString('COM_JEM_MENU_FEATURED_EVENTS', $manifest);
    }

    public function testInstallerAddsQuickCreateLinksToSupportedManagers(): void
    {
        $installer = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString('$this->repairAdminMenuQuickTasks();', $installer);

        foreach (array('event', 'venue', 'category', 'type') as $entity) {
            self::assertStringContainsString("index.php?option=com_jem&task=$entity.add", $installer);
        }

        self::assertStringContainsString("\$params->set('menu-quicktask-icon', 'plus');", $installer);
    }

    public function testInstallerDefinesQuickTaskTitles(): void
    {
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini');

        foreach (array('EVENT', 'VENUE', 'CATEGORY', 'TYPE') as $entity) {
            self::assertStringContainsString("COM_JEM_MENU_ADD_$entity=", $language);
        }
    }
}
