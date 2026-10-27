<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class HousekeepingDiagnosticsJoomlaIntegrationTest extends JoomlaTestCase
{
    public function testDetectedOverridePathsResolveWithoutDeletingFiles(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/housekeepingoverrides.class.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/housekeepingoverrides.class.php';
        require_once $path;

        $overrides = new JemHousekeepingOverrides();
        $scanner = $overrides->scan();
        $validator = new ReflectionMethod(JemHousekeepingOverrides::class, 'validatedPath');
        foreach ($scanner['templates'] as $item) {
            self::assertFileExists($validator->invoke($overrides, 'template', $item['winner']));
        }
        foreach ($scanner['languages'] as $item) {
            self::assertFileExists($validator->invoke($overrides, 'language', $item['path']));
        }
        self::assertNotSame(
            JemHousekeepingOverrides::selectionId('template', 'site/templates/example/html/com_jem/event/default.php'),
            JemHousekeepingOverrides::selectionId('language', 'site/templates/example/html/com_jem/event/default.php')
        );
        self::assertNotSame(
            JemHousekeepingOverrides::selectionId('language', 'language/overrides/en-GB.override.ini', array('COM_JEM_ONE')),
            JemHousekeepingOverrides::selectionId('language', 'language/overrides/en-GB.override.ini', array('COM_JEM_ONE', 'COM_JEM_TWO'))
        );
    }

    public function testRemovingJemLanguageKeysPreservesOtherOverrides(): void
    {
        self::bootJoomlaSite();
        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/housekeepingoverrides.class.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/housekeepingoverrides.class.php';
        require_once $path;

        $temporary = tempnam(sys_get_temp_dir(), 'jem-override-test-');
        self::assertNotFalse($temporary);
        try {
            file_put_contents(
                $temporary,
                "COM_JEM_EXAMPLE=\"JEM\"\r\nCOM_CONTENT_EXAMPLE=\"Keep\"\r\nMOD_JEM_EXAMPLE=\"JEM\"\r\n"
            );
            $overrides = new JemHousekeepingOverrides();
            $remove = new ReflectionMethod(JemHousekeepingOverrides::class, 'removeLanguageKeys');
            self::assertSame(
                2,
                $remove->invoke($overrides, $temporary, array('COM_JEM_EXAMPLE', 'MOD_JEM_EXAMPLE'))
            );
            self::assertSame("COM_CONTENT_EXAMPLE=\"Keep\"\r\n", file_get_contents($temporary));
        } finally {
            unlink($temporary);
        }
    }

    public function testFullAuditCountsAllStoredContentWithoutWriting(): void
    {
        self::bootJoomlaSite();

        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/housekeepingdiagnostics.class.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/housekeepingdiagnostics.class.php';
        self::assertFileExists($path);
        require_once $path;

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $assetsBefore = $db->setQuery('SELECT id, parent_id, lft, rgt, level, name FROM #__assets ORDER BY id')->loadAssocList();
        $report = (new JemHousekeepingDiagnostics())->run();
        $assetsAfter = $db->setQuery('SELECT id, parent_id, lft, rgt, level, name FROM #__assets ORDER BY id')->loadAssocList();
        self::assertSame($assetsBefore, $assetsAfter);

        foreach (array('events', 'categories', 'venues', 'types', 'special_days', 'attachments') as $kind) {
            $query = $db->getQuery(true)
                ->select('COUNT(*) AS ' . $db->quoteName('total'))
                ->select('COALESCE(MAX(' . $db->quoteName('id') . '), 0) AS ' . $db->quoteName('last_id'))
                ->from($db->quoteName('#__jem_' . $kind));
            $db->setQuery($query);
            $counts = $db->loadAssoc();
            self::assertSame((int) $counts['total'], $report['inventory'][$kind]['total']);
            self::assertSame((int) $counts['last_id'], $report['inventory'][$kind]['last_id']);
            if ($kind !== 'attachments') {
                $query = $db->getQuery(true)
                    ->select('COALESCE(SUM(' . $db->quoteName('published') . ' = 2), 0) AS ' . $db->quoteName('archived'))
                    ->select('COALESCE(SUM(' . $db->quoteName('published') . ' = -2), 0) AS ' . $db->quoteName('trashed'))
                    ->select('COALESCE(SUM(' . $db->quoteName('published') . ' IN (1, 2, -2)), 0) AS ' . $db->quoteName('shown_count'))
                    ->from($db->quoteName('#__jem_' . $kind));
                $db->setQuery($query);
                $states = $db->loadAssoc();
                self::assertSame((int) $states['archived'], $report['inventory'][$kind]['archived']);
                self::assertSame((int) $states['trashed'], $report['inventory'][$kind]['trashed']);
                self::assertSame(
                    (int) $states['shown_count'],
                    $report['inventory'][$kind]['active'] + $report['inventory'][$kind]['archived'] + $report['inventory'][$kind]['trashed']
                );
            }
            if (in_array($kind, array('events', 'categories', 'venues', 'special_days'), true)) {
                self::assertSame(
                    $report['inventory'][$kind]['active'],
                    $report['inventory'][$kind]['public'] + $report['inventory'][$kind]['private']
                );
            }
        }
        self::assertArrayNotHasKey('archived', $report['inventory']['attachments']);
        self::assertArrayNotHasKey('trashed', $report['inventory']['attachments']);
        $parentIds = array();
        foreach (array('event' => 'events', 'venue' => 'venues', 'category' => 'categories') as $type => $table) {
            $db->setQuery('SELECT id FROM #__jem_' . $table);
            $parentIds[$type] = array_fill_keys(array_map('intval', $db->loadColumn()), true);
        }
        $db->setQuery('SELECT object FROM #__jem_attachments');
        $orphaned = 0;
        foreach ($db->loadColumn() as $object) {
            if (!preg_match('/^(event|venue|category)([0-9]+)$/iD', (string) $object, $matches)
                || !isset($parentIds[strtolower($matches[1])][(int) $matches[2]])) {
                $orphaned++;
            }
        }
        self::assertSame($orphaned, $report['inventory']['attachments']['orphaned']);
        self::assertSame($orphaned, count(array_filter(
            $report['lines'],
            static fn(array $line): bool => $line['code'] === 'COM_JEM_TOOLS_ATTACHMENT_ORPHANED'
                && $line['severity'] === 'error'
        )));

        self::assertSame(array(
            'component_schema', 'menu', 'published', 'publication_window', 'event_dates',
            'event_access', 'category_access', 'venue_access', 'type_access', 'filters_overrides',
        ), array_column($report['frontend_checks'], 'code'));
        foreach ($report['frontend_checks'] as $check) {
            self::assertContains($check['status'], array('ok', 'warning', 'error', 'review', 'skip'));
            self::assertNotEmpty($check['label']);
            self::assertNotEmpty($check['detail']);
            self::assertStringStartsWith('index.php?option=', $check['action_url']);
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        self::assertSame((int) $db->loadResult(), $report['inventory']['types']['active']);
        self::assertSame($report['inventory']['types']['total'], $report['types']['total']);
        self::assertSame($report['inventory']['types']['active'], $report['types']['active']);
        $typeSources = array(
            1 => array('#__jem_events', 'type_id'),
            2 => array('#__jem_categories', 'type_id'),
            3 => array('#__jem_venues', 'type_id'),
            4 => array('#__jem_special_days', 'day_type_id'),
        );
        foreach ($report['types']['items'] as $type) {
            self::assertGreaterThan(0, $type['id']);
            if (!isset($typeSources[$type['entity']])) {
                continue;
            }
            [$table, $column] = $typeSources[$type['entity']];
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($table))
                ->where($db->quoteName($column) . ' = ' . (int) $type['id']);
            $db->setQuery($query);
            self::assertSame((int) $db->loadResult(), $type['count']);
        }

        self::assertGreaterThan(0, $report['component']['id']);
        self::assertNotEmpty($report['component']['version']);
        self::assertGreaterThanOrEqual(1, $report['languages']['total']);
        foreach ($report['languages']['items'] as $language) {
            self::assertArrayHasKey('jem_installed', $language);
            self::assertArrayHasKey('jem_version', $language);
            if ($language['code'] === 'en-GB') {
                self::assertSame($report['component']['version'], $language['jem_version']);
            }
        }
        foreach ($report['menu_views']['items'] as $view) {
            self::assertCount($view['total'], $view['menu_items']);
            self::assertSame(
                $view['active'],
                count(array_filter($view['menu_items'], static fn($item) => $item['published']))
            );
            foreach ($view['menu_items'] as $menuItem) {
                self::assertGreaterThan(0, $menuItem['id']);
                self::assertArrayHasKey('title', $menuItem);
                self::assertNotEmpty($menuItem['layout']);
                self::assertArrayHasKey('mode', $menuItem);
            }
        }
        self::assertArrayHasKey('templates', $report['overrides']);
        self::assertArrayHasKey('languages', $report['overrides']);
        self::assertArrayHasKey('css', $report['overrides']);
        self::assertSame((string) Factory::getApplication()->get('offset', 'UTC'), $report['component']['timezone']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $report['component']['version_date']);
        if ($report['component']['installed_at'] !== '') {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $report['component']['installed_at']);
        }
        self::assertSame(
            count($report['lines']),
            $report['notice'] + $report['warning'] + $report['error']
        );
        self::assertSame(0, $report['notice']);
        foreach ($report['lines'] as $line) {
            self::assertFalse(str_contains($line['message'], 'COM_JEM_TOOLS_CHECK_FAILED'));
            self::assertFalse(str_contains($line['message'], 'A check could not be completed'));
            self::assertNotSame('COM_JEM_TOOLS_CHECK_AREA_FAILED', $line['code']);
            self::assertNotEmpty($line['subject']);
        }
    }

    public function testStoredJsonReportCanHideNamesWithoutLosingFindingCodes(): void
    {
        self::bootJoomlaSite();

        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/housekeepingreports.class.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/housekeepingreports.class.php';
        require_once $path;

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $db->transactionStart();

        try {
            $reports = new JemHousekeepingReports($db);
            $id = $reports->save(array(
                'module_items' => array(array('name' => 'Private module title')),
                'plugin_items' => array(array('name' => 'Private plugin title')),
                'languages' => array('items' => array(array('name' => 'English'))),
                'notice' => 1,
                'warning' => 1,
                'error' => 0,
                'lines' => array(
                    array(
                        'subject' => 'JEM',
                        'severity' => 'notice',
                        'message' => 'Routine summary with no action',
                        'code' => 'COM_JEM_TOOLS_MODULE_COUNT',
                        'recommendation' => '',
                    ),
                    array(
                        'subject' => 'Event #25',
                        'severity' => 'warning',
                        'message' => 'Private event title has no category',
                        'code' => 'COM_JEM_TOOLS_PUBLISHED_EVENT_NO_CATEGORY',
                        'recommendation' => 'Review the event.',
                        'action_url' => 'index.php?option=com_jem&view=event&id=25',
                        'action_label' => 'Open event',
                    ),
                ),
            ), 0);
            $stored = $reports->load($id);
            self::assertNotNull($stored);
            self::assertSame($id, $stored['id']);
            self::assertCount(2, $stored['data']['report']['lines']);

            $full = $reports->export($stored, true, 'Support observation');
            $redacted = $reports->export($stored, false);
            $exported = json_decode($full, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(0, $exported['report']['notice']);
            self::assertCount(1, $exported['report']['lines']);
            self::assertSame('warning', $exported['report']['lines'][0]['severity']);
            self::assertStringContainsString('Private event title', $full);
            self::assertStringContainsString('Support observation', $full);
            self::assertStringNotContainsString('Routine summary with no action', $full);
            self::assertStringNotContainsString('Routine summary with no action', $redacted);
            self::assertStringNotContainsString('Private event title', $redacted);
            self::assertStringNotContainsString('Private module title', $redacted);
            self::assertStringContainsString('COM_JEM_TOOLS_PUBLISHED_EVENT_NO_CATEGORY', $redacted);
            self::assertStringNotContainsString('action_url', $redacted);
            self::assertFalse($reports->delete(0));
            self::assertTrue($reports->delete($id));
            self::assertNull($reports->load($id));
        } finally {
            $db->transactionRollback();
        }
    }

    public function testFuturePublicationWindowIsIdentifiedWithoutPersistingChanges(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/housekeepingdiagnostics.class.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/housekeepingdiagnostics.class.php';
        require_once $path;

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        if ((int) $db->loadResult() === 0) {
            self::markTestSkipped('The fixture has no published JEM events.');
        }

        $db->transactionStart();
        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_events'))
                ->set($db->quoteName('publish_up') . ' = ' . $db->quote('2099-01-01 00:00:00'))
                ->set($db->quoteName('publish_down') . ' = NULL')
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($query)->execute();

            $report = (new JemHousekeepingDiagnostics($db))->run();
            $checks = array_column($report['frontend_checks'], null, 'code');
            self::assertSame('warning', $checks['publication_window']['status']);
            self::assertSame('skip', $checks['event_dates']['status']);
            self::assertSame('skip', $checks['event_access']['status']);
            self::assertContains('COM_JEM_TOOLS_FE_PUBLICATION_WINDOW', array_column($report['lines'], 'code'));
        } finally {
            $db->transactionRollback();
        }
    }

    public function testGuestAccessChecksIdentifyCategoryVenueAndTypeBlockers(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/housekeepingdiagnostics.class.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/housekeepingdiagnostics.class.php';
        require_once $path;

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $baseline = (new JemHousekeepingDiagnostics($db))->run();
        $baselineChecks = array_column($baseline['frontend_checks'], null, 'code');
        if (($baselineChecks['category_access']['status'] ?? '') !== 'ok') {
            self::markTestSkipped('The fixture has no guest-visible event/category candidate.');
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query, 0, 1);
        $venueId = (int) $db->loadResult();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = 1')
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query, 0, 1);
        $typeId = (int) $db->loadResult();

        foreach (array(
            'events' => 'event_access',
            'categories' => 'category_access',
            'venues' => 'venue_access',
            'types' => 'type_access',
        ) as $table => $checkCode) {
            if (($table === 'venues' && !$venueId) || ($table === 'types' && !$typeId)) {
                continue;
            }
            $db->transactionStart();
            try {
                if ($table === 'venues' || $table === 'types') {
                    $field = $table === 'venues' ? 'locid' : 'type_id';
                    $id = $table === 'venues' ? $venueId : $typeId;
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__jem_events'))
                        ->set($db->quoteName($field) . ' = ' . $id)
                        ->where($db->quoteName('published') . ' = 1');
                    $db->setQuery($query)->execute();
                }
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__jem_' . $table))
                    ->set($db->quoteName('access') . ' = 999999');
                $db->setQuery($query)->execute();

                $report = (new JemHousekeepingDiagnostics($db))->run();
                $checks = array_column($report['frontend_checks'], null, 'code');
                self::assertSame('warning', $checks[$checkCode]['status'], $table);
                self::assertContains('COM_JEM_TOOLS_FE_' . strtoupper($checkCode), array_column($report['lines'], 'code'));
            } finally {
                $db->transactionRollback();
            }
        }
    }

    public function testSelectedSettingsAndLanguageFilesAreExportedWithPathsAndRedaction(): void
    {
        self::bootJoomlaSite();

        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        $root = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/classes/'
            : JPATH_ADMINISTRATOR . '/components/com_jem/classes/';
        require_once $root . 'housekeepingdiagnostics.class.php';
        require_once $root . 'housekeepingreports.class.php';

        $report = (new JemHousekeepingDiagnostics())->run();
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $db->transactionStart();
        try {
            $reports = new JemHousekeepingReports($db);
            $id = $reports->save($report, 0);
            $stored = $reports->load($id);
            self::assertNotNull($stored);
            $view = array_values(array_filter(
                $report['menu_views']['items'],
                static fn($item): bool => $item['active'] > 0
            ));
            $selection = array(
                'settings' => true,
                'component' => true,
                'inventory' => true,
                'override_templates' => true,
                'override_languages' => true,
                'override_css' => true,
                'types' => $report['types']['items'] ? array($report['types']['items'][0]['id']) : array(),
                'view_types' => $view ? array($view[0]['name']) : array(),
                'languages' => array('en-GB'),
            );
            $export = json_decode($reports->export($stored, true, '', $selection), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($export['settings']['component']);
            self::assertArrayHasKey('inventory', $export['report']);
            self::assertArrayHasKey('overrides', $export['report']);
            self::assertArrayHasKey('templates', $export['report']['overrides']);
            self::assertArrayHasKey('languages', $export['report']['overrides']);
            self::assertArrayHasKey('css', $export['report']['overrides']);
            self::assertCount($report['types']['items'] ? 1 : 0, $export['report']['types']['items']);
            self::assertNotEmpty($export['language_files']);
            self::assertSame('en-GB', $export['language_files'][0]['language']);
            self::assertStringStartsWith('administrator/language/en-GB/', $export['language_files'][0]['path']);
            self::assertArrayNotHasKey('content', $export['language_files'][0]);
            foreach ($export['settings']['component'] as $key => $value) {
                if (preg_match('/(?:token|api_?key|secret|path|email)/i', $key)) {
                    self::assertSame('[redacted]', $value);
                }
            }
            if ($view) {
                self::assertNotEmpty($export['settings']['views']);
            }
            $withoutSettings = json_decode(
                $reports->export($stored, true, '', array_merge($selection, array('settings' => false))),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertFalse($withoutSettings['include_selected_settings']);
            self::assertSame(array(), $withoutSettings['settings']);
            self::assertNotEmpty($withoutSettings['language_files']);
            $minimal = json_decode($reports->export($stored, true), true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('inventory', $minimal['report']);
            self::assertArrayNotHasKey('overrides', $minimal['report']);
            self::assertSame(array(), $minimal['report']['types']['items']);
            self::assertSame(array(), $minimal['report']['module_items']);
            self::assertSame(array(), $minimal['report']['plugin_items']);
            self::assertSame(array(), $minimal['report']['menu_views']['items']);
            self::assertSame(array(), $minimal['report']['languages']['items']);
        } finally {
            $db->transactionRollback();
        }
    }
}
