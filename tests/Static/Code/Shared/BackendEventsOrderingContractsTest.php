<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendEventsOrderingContractsTest extends TestCase
{
    public function testSettingsExposeBackendEventOrderingBeforeCategoryOrdering(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');

        self::assertStringContainsString('name="backend_events_order"', $form);
        self::assertStringContainsString('name="backend_events_direction"', $form);
        self::assertLessThan(
            strpos($form, 'name="categories_order"'),
            strpos($form, 'name="backend_events_order"')
        );
        self::assertStringContainsString('<option value="a.dates">COM_JEM_DATE</option>', $form);
        self::assertStringContainsString('<option value="ASC">COM_JEM_ORDER_ASCENDING</option>', $form);
        self::assertStringContainsString('<option value="DESC">COM_JEM_ORDER_DESCENDING</option>', $form);
    }

    public function testMainAndSelectorEventListsUseValidatedConfiguredDefaults(): void
    {
        $events = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/events.php');
        $selector = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/eventelement.php');

        foreach (array($events, $selector) as $source) {
            self::assertStringContainsString("get('backend_events_order', 'a.dates')", $source);
            self::assertStringContainsString("get('backend_events_direction', 'ASC')", $source);
            self::assertStringContainsString("array('ASC', 'DESC')", $source);
        }

        self::assertStringContainsString('parent::populateState($defaultOrdering, $defaultDirection)', $events);
        self::assertStringContainsString("array('a.dates', 'a.times', 'a.title', 'loc.venue')", $selector);
    }

    public function testFreshInstallAndUpgradeDefineCompatibleDefaults(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/jem.xml');
        self::assertNotFalse($manifest);
        self::assertSame(1, preg_match('/^(\d+\.\d+\.\d+)/', (string) $manifest->version, $matches));

        $update = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/sql/updates/mysql/' . $matches[1] . '.sql'
        );

        foreach (array($install, $update) as $sql) {
            self::assertStringContainsString("('backend_events_order', 'a.dates')", $sql);
            self::assertStringContainsString("('backend_events_direction', 'ASC')", $sql);
        }
    }
}
