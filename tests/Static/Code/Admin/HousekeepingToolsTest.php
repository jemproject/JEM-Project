<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousekeepingToolsTest extends TestCase
{
    public function testZombieRelationTileCountsOnlyBrokenReferences(): void
    {
        $model = $this->read('admin/models/housekeeping.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/default.php');
        $language = $this->read('admin/language/en-GB/com_jem.ini');
        $method = substr($model, strpos($model, 'public function getCountcats()'));

        self::assertStringContainsString('SELECT COUNT(*) FROM #__jem_cats_event_relations AS rel', $method);
        self::assertStringContainsString('LEFT JOIN #__jem_events AS e ON e.id = rel.itemid', $method);
        self::assertStringContainsString('LEFT JOIN #__jem_categories AS c ON c.id = rel.catid', $method);
        self::assertStringContainsString('WHERE e.id IS NULL OR c.id IS NULL', $method);
        self::assertStringContainsString('return (int) $db->loadResult();', $method);
        self::assertStringContainsString('COM_JEM_HOUSEKEEPING_BROKEN_CATSEVENT_RELS', $layout);
        self::assertStringContainsString('COM_JEM_HOUSEKEEPING_BROKEN_CATSEVENT_RELS="Broken references: %d"', $language);
    }

    public function testDiagnosticsScanAllContentWithoutContextFilters(): void
    {
        $diagnostics = $this->read('admin/classes/housekeepingdiagnostics.class.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');

        self::assertStringContainsString("'notice' => 0", $diagnostics);
        self::assertStringNotContainsString("'notice',", $diagnostics);
        self::assertStringContainsString("'warning' => 0", $diagnostics);
        self::assertStringContainsString("'error' => 0", $diagnostics);
        self::assertStringContainsString('module_items', $diagnostics);
        self::assertStringContainsString('plugin_items', $diagnostics);
        self::assertStringContainsString('menu_views', $diagnostics);
        self::assertStringContainsString("'menus' => 'checkFrontendMenus'", $diagnostics);
        self::assertStringContainsString("'recommendation' => \$recommendation", $diagnostics);
        self::assertStringContainsString("'action_url' => \$actionUrl", $diagnostics);
        self::assertStringContainsString("'content' => 'checkContent'", $diagnostics);
        self::assertStringContainsString('foreach ($events as $event)', $diagnostics);
        self::assertStringContainsString('foreach ($categories as $category)', $diagnostics);
        self::assertStringContainsString('foreach ($venues as $venue)', $diagnostics);
        self::assertStringContainsString('foreach ($relations as $relation)', $diagnostics);
        self::assertStringContainsString("'languages' => 'checkLanguages'", $diagnostics);
        self::assertStringNotContainsString('function checkEvent(', $diagnostics);
        self::assertStringNotContainsString('->execute()', $diagnostics);
        self::assertStringContainsString("array('notice' => 'info', 'warning' => 'warning', 'error' => 'danger')", $layout);
        self::assertStringContainsString("'icon-publish' : 'icon-unpublish'", $layout);
        self::assertStringContainsString('class="visually-hidden"', $layout);
        self::assertStringContainsString("'COM_JEM_PUBLISHED'", $layout);
        self::assertStringContainsString("<td class=\"text-end\" rowspan=\"<?php echo \$rowspan; ?>\"><?php echo (int) \$view['active']; ?></td>", $layout);
        self::assertStringNotContainsString('COM_JEM_TOOLS_MENU_VIEW_ITEM_COUNT', $layout);
        self::assertStringNotContainsString('COM_JEM_TOOLS_PUBLISHED_TOTAL', $layout);
        self::assertStringContainsString("if (\$line['subject'] !== 'JEM')", $layout);
        self::assertStringContainsString('jem-tools-extension-table', $layout);
        self::assertStringNotContainsString('moduleTypeCounts', $layout);
        self::assertStringContainsString("if (\$kind === 'modules')", $layout);
        self::assertStringNotContainsString('COM_JEM_TOOLS_TYPE_INSTANCES', $layout);
        self::assertStringContainsString('jem-tools-diagnostics-table', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_MENU_VIEWS', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_VERSION_DATE', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_SITE_TIMEZONE', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_REPORT_HEADING', $layout);
        self::assertStringContainsString('task=housekeeping.deleteDiagnosticReport', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_CONTENT_INVENTORY', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_LANGUAGES', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_JEM_LANGUAGE_VERSION', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_RERUN_LANGUAGE_VERSIONS', $layout);
        self::assertGreaterThan(
            strpos($layout, "Text::_('COM_JEM_TOOLS_CONTENT_INVENTORY')"),
            strpos($layout, "Text::_('COM_JEM_TOOLS_LANGUAGES')")
        );
        self::assertStringNotContainsString('name="eventid"', $layout);
        self::assertStringNotContainsString('name="menuid"', $layout);
        self::assertStringNotContainsString('name="userid"', $layout);
        self::assertGreaterThan(
            strpos($layout, "Text::_('COM_JEM_TOOLS_CONSOLE')"),
            strpos($layout, "array('notice' => 'info'")
        );
        self::assertStringNotContainsString('<div class="form-text">', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_FE_CHECKS_TITLE', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_PUBLIC_PRIVATE_NOTE', $layout);
    }

    public function testVerificationRulesAppearBeforeChecksAndActions(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $rules = strpos($layout, 'id="jem-diagnostic-verification-rules"');
        $frontendChecks = strpos($layout, "Text::_('COM_JEM_TOOLS_FE_CHECKS_TITLE')");
        $results = strpos($layout, 'id="jem-diagnostic-results"');

        self::assertNotFalse($rules);
        self::assertNotFalse($frontendChecks);
        self::assertNotFalse($results);
        self::assertTrue($rules < $frontendChecks && $frontendChecks < $results);
        self::assertSame(1, substr_count($layout, "Text::_('COM_JEM_TOOLS_FE_CHECKS_TITLE')"));
    }

    public function testDiagnosticLinksAreEscapedOnlyOnce(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');

        self::assertStringContainsString("(int) \$recent->id, false)); ?>", $layout);
        self::assertStringContainsString("Route::_(\$line['action_url'], false)", $layout);
        self::assertStringNotContainsString("diagnostics&report=' . (int) \$recent->id)); ?>", $layout);
        self::assertStringNotContainsString("Route::_(\$line['action_url'])", $layout);
    }

    public function testRecentReportsShowEightNewestBesideRunChecks(): void
    {
        $view = $this->read('admin/views/housekeeping/view.html.php');
        $reports = $this->read('admin/classes/housekeepingreports.class.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');

        self::assertStringContainsString('$reports->recent(8)', $view);
        self::assertStringContainsString("->order(\$this->db->quoteName('created') . ' DESC')", $reports);
        self::assertStringContainsString("->order(\$this->db->quoteName('id') . ' DESC')", $reports);
        self::assertStringContainsString('d-flex flex-column flex-lg-row align-items-lg-center', $layout);
        self::assertStringContainsString('flex-grow-1 overflow-auto', $layout);
        self::assertLessThan(
            strpos($layout, 'task=housekeeping.runDiagnostics'),
            strpos($layout, "Text::_('COM_JEM_TOOLS_RECENT_REPORTS')")
        );
    }

    public function testDiagnosticSummaryKeepsPublishedArchiveAndTrashCountsSeparate(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $start = strpos($layout, "Text::_('COM_JEM_TOOLS_CONTENT_INVENTORY')");
        $componentEnd = strpos($layout, "Text::_('COM_JEM_TOOLS_TYPES_BLOCK')", $start);
        $menuStart = strpos($layout, "Text::_('COM_JEM_TOOLS_MENU_VIEWS')", $componentEnd);
        $menuEnd = strpos($layout, '<div class="row g-3 mb-4">', $menuStart);

        self::assertNotFalse($start);
        self::assertNotFalse($componentEnd);
        self::assertNotFalse($menuStart);
        self::assertNotFalse($menuEnd);
        $component = substr($layout, $start, $componentEnd - $start);
        $menus = substr($layout, $menuStart, $menuEnd - $menuStart);
        self::assertSame(1, substr_count($component, "Text::_('COM_JEM_TOOLS_CONTENT_INVENTORY')"));
        self::assertSame(1, substr_count($component, '<table class="table table-sm mb-0" data-jem-sort-table data-jem-default-sort="id">'));
        $nameHeader = strpos($component, "Text::_('COM_JEM_TOOLS_NAME')");
        $statusHeader = strpos($component, "Text::_('COM_JEM_TOOLS_STATUS')");
        $idHeader = strpos($component, "Text::_('COM_JEM_TOOLS_LAST_ID')");
        self::assertNotFalse($nameHeader);
        self::assertNotFalse($statusHeader);
        self::assertNotFalse($idHeader);
        self::assertTrue($nameHeader < $statusHeader && $statusHeader < $idHeader);
        self::assertStringContainsString('icon-file text-secondary', $component);
        self::assertStringContainsString('icon-publish text-success', $component);
        self::assertStringContainsString('<div class="table-responsive">', $component);
        self::assertStringContainsString("Text::_('JARCHIVED')", $component);
        self::assertStringContainsString("Text::_('JTRASHED')", $component);
        self::assertStringContainsString("Text::_('COM_JEM_ATTACHMENT_ORPHANED')", $component);
        self::assertStringContainsString("\$inventory['archived']", $component);
        self::assertStringContainsString("\$inventory['trashed']", $component);
        self::assertStringContainsString("\$inventory['orphaned']", $component);
        self::assertStringContainsString("' text-danger fw-bold'", $component);
        self::assertStringContainsString("(int) \$inventory['active'] + (int) \$inventory['archived'] + (int) \$inventory['trashed']", $component);
        self::assertStringContainsString("isset(\$inventory['active'], \$inventory['archived'], \$inventory['trashed'])", $component);
        self::assertStringContainsString('<td colspan="8"', $component);
        $header = substr($layout, 0, $start);
        self::assertStringContainsString('<p class="mb-0 mt-2">', $header);
        self::assertSame(4, substr_count($header, '&ndash;'));
        self::assertSame(6, substr_count($header, '<span class="text-nowrap">'));
        self::assertStringNotContainsString("diagnosticReport['component']['active']", $component);
        self::assertSame(1, substr_count($menus, '<table class="table table-sm mb-0" data-jem-sort-table data-jem-sort-groups data-jem-default-sort="id">'));
        self::assertStringContainsString('foreach ($menuItems ?: array(null) as $menuIndex => $menuItem)', $menus);
        self::assertStringContainsString('<tbody data-jem-sort-id=', $menus);
        self::assertStringContainsString('rowspan="<?php echo $rowspan; ?>"', $menus);
        self::assertSame(2, substr_count(
            substr($layout, $componentEnd, $menuStart - $componentEnd),
            '<div class="col-12 col-xl-6"><div class="card h-100">'
        ));
        self::assertStringContainsString("'types' => 'COM_JEM_TYPES'", $component);
        self::assertStringContainsString("'special_days' => 'COM_JEM_SPECIAL_DAYS'", $component);
        self::assertStringContainsString("'attachments' => 'COM_JEM_ATTACHMENTS'", $component);
        self::assertStringContainsString('COM_JEM_TOOLS_LAST_ID', $component);
        self::assertStringContainsString("'categories' => 'category'", $component);
        self::assertStringContainsString("\$inventory['total']", $component);
        self::assertStringNotContainsString("\$inventory[\$kind === 'attachments' ? 'total' : 'active']", $component);
        self::assertStringContainsString("['total']", $menus);
        self::assertStringContainsString('COM_JEM_TOOLS_MENU_ITEM_IDS', $menus);
        self::assertStringContainsString('COM_JEM_TOOLS_CONFIGURED_LAYOUT', $menus);
        self::assertStringContainsString("array_values((array) (\$view['menu_items'] ?? array()))", $menus);
        self::assertStringContainsString("\$menuItem['layout']", $menus);
        self::assertStringContainsString("\$menuItem['mode']", $menus);
        self::assertStringContainsString('COM_JEM_TOOLS_RERUN_MENU_IDS', $menus);
        self::assertStringContainsString("\$this->diagnosticReport['menu_views']['active']", $menus);
        self::assertStringContainsString("\$menuItem['title'] ?? ''", $menus);
        self::assertStringContainsString("\$menuItem['published'] ? 'icon-publish' : 'icon-unpublish'", $menus);
        $overridesStart = strpos($layout, "Text::_('COM_JEM_TOOLS_LANGUAGE_OVERRIDES')");
        $cssStart = strpos($layout, "Text::_('COM_JEM_TOOLS_CSS_OVERRIDES')", $overridesStart);
        $languageOverrides = substr($layout, $overridesStart, $cssStart - $overridesStart);
        self::assertStringContainsString("count(\$override['keys'])", $languageOverrides);
        self::assertStringContainsString('colspan="6"', $languageOverrides);
        self::assertStringContainsString('task=type.edit&id=', $layout);
        self::assertStringContainsString('task=module.edit&id=', $layout);
        self::assertStringContainsString('task=plugin.edit&extension_id=', $layout);
        self::assertStringContainsString('task=language.edit&lang_id=', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_TYPES_BLOCK', $layout);
    }

    public function testDiagnosticTablesSortByIdWithoutSortingReportCheckboxes(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $diagnostics = $this->read('admin/classes/housekeepingdiagnostics.class.php');
        $sorting = $this->read('media/js/housekeeping-diagnostics-sort.js');

        self::assertSame(5, substr_count($layout, 'data-jem-default-sort="id"'));
        self::assertSame(5, substr_count($layout, 'data-jem-sort-id-column'));
        self::assertStringContainsString('data-jem-sort-value="<?php echo $escape($item[\'name\']); ?>"', $layout);
        self::assertStringNotContainsString('data-jem-sort="checkbox"', $layout);
        self::assertStringContainsString("->order(\$this->db->quoteName('lang_id') . ' ASC')", $diagnostics);
        self::assertStringContainsString("->order(\$this->db->quoteName('extension_id') . ' ASC')", $diagnostics);
        self::assertStringContainsString("uasort(\$report['menu_views']['items']", $diagnostics);
        self::assertStringContainsString("table.dataset.jemDefaultSort === 'id'", $sorting);
        self::assertStringContainsString('body.append(...rows)', $sorting);
        self::assertStringContainsString('table.append(...rows)', $sorting);
        self::assertStringContainsString("table.hasAttribute('data-jem-sort-groups')", $sorting);
        self::assertStringContainsString('const row = grouped ? item.rows[0] : item;', $sorting);
        self::assertStringContainsString("header.setAttribute('aria-sort', direction)", $sorting);
        self::assertStringNotContainsString('housekeeping.sendDiagnostics', $layout);
        self::assertStringNotContainsString('jem-diagnostic-consent', $sorting);
    }

    public function testModuleTableShowsNameTypeAndLinkedInstanceId(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $start = strpos($layout, "foreach (array('modules' => 'module_items', 'plugins' => 'plugin_items')");
        $end = strpos($layout, "Text::_('COM_JEM_TOOLS_MENU_VIEWS')", $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $table = substr($layout, $start, $end - $start);
        $name = strpos($table, "Text::_('COM_JEM_TOOLS_NAME')");
        $type = strpos($table, "'COM_JEM_TOOLS_TYPE' : 'COM_JEM_TOOLS_EXTENSION'");
        $id = strpos($table, "Text::_('COM_JEM_TOOLS_ID')");

        self::assertNotFalse($name);
        self::assertNotFalse($type);
        self::assertNotFalse($id);
        self::assertTrue($name < $type && $type < $id);
        self::assertStringContainsString('><?php echo $escape($item[\'name\']); ?></a>', $table);
        self::assertStringContainsString('>#<?php echo (int) $item[\'id\']; ?></a>', $table);
        self::assertStringNotContainsString('moduleTypeCounts', $table);
        self::assertStringNotContainsString('COM_JEM_TOOLS_TYPE_INSTANCES', $table);
    }

    public function testDiagnosticSeverityCardsFilterStoredFindingsAndPreservePagination(): void
    {
        $view = $this->read('admin/views/housekeeping/view.html.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');

        self::assertStringContainsString("getCmd('severity', '')", $view);
        self::assertStringContainsString("in_array(\$severity, array('notice', 'warning', 'error'), true)", $view);
        self::assertStringContainsString("'&severity=' . \$level", $layout);
        self::assertStringContainsString('aria-current="true"', $layout);
        self::assertStringContainsString('array_values(array_filter(', $layout);
        self::assertStringContainsString("\$line['severity'] === \$this->diagnosticSeverity", $layout);
        self::assertStringContainsString('count($filteredLines)', $layout);
        self::assertStringContainsString('array_slice($filteredLines,', $layout);
        self::assertStringContainsString("'&severity=' . \$this->diagnosticSeverity", $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_SHOW_ALL_FINDINGS', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_SHOWING_SEVERITY', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_NO_FINDINGS', $layout);
        self::assertStringContainsString('id="jem-diagnostic-results"', $layout);
    }

    public function testDefaultsRequirePreviewAndExcludeSensitiveOrAclFields(): void
    {
        $defaults = $this->read('admin/classes/housekeepingdefaults.class.php');
        $controller = $this->read('admin/controllers/housekeeping.php');

        self::assertStringContainsString("'core.options'", $controller);
        self::assertStringContainsString("authorise('core.admin')", $controller);
        self::assertStringContainsString('JemHelper::requirePostToken()', $controller);
        self::assertStringContainsString('consumeActionNonce', $controller);
        self::assertStringContainsString("'fingerprint' => hash('sha256'", $defaults);
        self::assertStringContainsString('hash_equals(', $defaults);
        self::assertStringContainsString('transactionStart()', $defaults);
        self::assertStringContainsString("'accesslevel'", $defaults);
        self::assertStringContainsString('password|secret|token', $defaults);
        self::assertStringContainsString('validateComponentImageBounds', $defaults);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $defaults);

        $form = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        self::assertNotFalse($form);
        self::assertGreaterThan(50, count($form->xpath('//fieldset/field')));
        self::assertCount(1, $form->xpath('//field[@name="event_associated_article_access"]'));
    }

    public function testRestoreExplainsWhyAbsentStoredValuesAreRestorable(): void
    {
        $defaults = $this->read('admin/classes/housekeepingdefaults.class.php');
        $language = $this->read('admin/language/en-GB/com_jem.ini');
        $banner = simplexml_load_file(JEM_TEST_ROOT . '/modules/mod_jem_banner/mod_jem_banner.xml');

        self::assertNotFalse($banner);
        self::assertSame('5', (string) $banner->xpath('//field[@name="count"]')[0]['default']);
        self::assertStringContainsString("\$params->get('count', '2')", $this->read('modules/mod_jem_banner/helper.php'));
        self::assertStringContainsString("'changed' => \$current === null ||", $defaults);
        self::assertStringContainsString('Not stored counts as different:', $language);
        self::assertStringContainsString('writing that default may change behaviour', $language);
        self::assertStringContainsString('COM_JEM_TOOLS_CURRENT="Stored value"', $language);
    }

    public function testImageAuditCountsEntitiesAndToolGridIsResponsive(): void
    {
        $model = $this->read('admin/models/housekeeping.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/default.php');
        $css = $this->read('media/css/backend.css');

        foreach (array('event', 'category', 'venue') as $entity) {
            self::assertStringContainsString("'" . $entity . "' => array('total' => 0", $model);
        }

        self::assertStringContainsString("['not_normalised']++", $model);
        self::assertStringContainsString('getImageProfileAssignments($jemsettings)', $model);
        self::assertStringContainsString("\$this->tool === 'images'", $layout);
        self::assertStringContainsString('class="table housekeeping"', $layout);
        self::assertStringNotContainsString('table-striped housekeeping', $layout);
        self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $css);
        self::assertStringContainsString('@media (max-width: 900px)', $css);
    }

    public function testToolPagesUseJoomlaLayoutNamesInsteadOfTemplateSuffixes(): void
    {
        $view = $this->read('admin/views/housekeeping/view.html.php');

        foreach (array('diagnostics', 'restore', 'images') as $tool) {
            self::assertStringContainsString("\$this->setLayout('" . $tool . "')", $view);
            self::assertStringNotContainsString("\$tpl = '" . $tool . "'", $view);
            self::assertFileExists(JEM_TEST_ROOT . '/admin/views/housekeeping/tmpl/' . $tool . '.php');
        }

        self::assertStringContainsString("\$this->tool === '' ? 'index.php?option=com_jem&view=main'", $view);
        foreach (array('diagnostics', 'restore', 'default') as $layoutName) {
            $layout = $this->read('admin/views/housekeeping/tmpl/' . $layoutName . '.php');
            self::assertStringNotContainsString("Text::_('COM_JEM_TOOLS_BACK')", $layout);
        }
    }

    public function testRestoreFilterUsesCatalogComparisonWithoutChangingSelections(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/restore.php');
        $script = $this->read('media/js/housekeeping-restore-filter.js');

        self::assertStringContainsString("HTMLHelper::_('script', 'com_jem/housekeeping-restore-filter.js'", $layout);
        self::assertStringContainsString("array('all' => 'icon-list', 'same' => 'icon-check', 'different' => 'icon-warning')", $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_RESTORE_FILTER_BUTTON_', $layout);
        self::assertStringContainsString('aria-hidden="true"', $layout);
        self::assertStringContainsString("\$field['changed'] ? 'different' : 'same'", $layout);
        self::assertStringContainsString('aria-pressed=', $layout);
        self::assertSame(1, substr_count($layout, 'COM_JEM_TOOLS_CURRENT'));
        self::assertStringContainsString('<tbody data-jem-restore-group>', $layout);
        self::assertStringContainsString('data-jem-restore-expand="all"', $layout);
        self::assertStringContainsString('data-jem-restore-expand="none"', $layout);
        self::assertStringContainsString("row.dataset.jemRestoreStatus === mode", $script);
        self::assertStringContainsString("row.classList.toggle('d-none', !visible || !expanded)", $script);
        self::assertStringContainsString("group.classList.toggle('d-none', visibleInGroup === 0)", $script);
        self::assertStringContainsString("group.dataset.jemExpanded = expanded ? 'true' : 'false'", $script);
        self::assertStringNotContainsString('.checked =', $script);
    }

    public function testDiagnosticReportsUseLocalDownloadsAndReadOnlyPreviews(): void
    {
        $reports = $this->read('admin/classes/housekeepingreports.class.php');
        $controller = $this->read('admin/controllers/housekeeping.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $installer = $this->read('script.php');
        $installSql = $this->read('admin/sql/install.mysql.utf8.sql');
        $updateSql = $this->read('admin/sql/updates/mysql/5.1.0.sql');

        self::assertStringContainsString('jem-diagnostics/v1', $reports);
        self::assertStringContainsString('JSON_INVALID_UTF8_SUBSTITUTE', $reports);
        self::assertStringContainsString("(\$line['severity'] ?? '') !== 'notice'", $reports);
        self::assertStringContainsString("'name'] = ''", $reports);
        self::assertStringContainsString('function runDiagnostics()', $controller);
        self::assertStringContainsString('function downloadDiagnostics()', $controller);
        self::assertStringContainsString('function downloadDiagnosticsZip()', $controller);
        self::assertStringContainsString('function previewDiagnostics()', $controller);
        self::assertStringNotContainsString('function sendDiagnostics()', $controller);
        self::assertStringNotContainsString('Factory::getMailer()', $controller);
        self::assertStringContainsString('str_replace(array("\\r\\n", "\\r"), "\\n", $value)', $controller);
        self::assertLessThan(
            strpos($controller, "mb_strlen(\$value, 'UTF-8') > 2000"),
            strpos($controller, 'str_replace(array("\\r\\n", "\\r"), "\\n", $value)')
        );
        self::assertStringContainsString('JemHelper::requirePostToken()', $controller);
        self::assertStringContainsString('JemLog::getConfiguredLevel() !== 5', $controller);
        self::assertStringContainsString('is_uploaded_file(', $controller);
        self::assertStringContainsString('JemHousekeepingReports::setRequestPreview($preview)', $controller);
        self::assertStringContainsString('function previewUpload(', $reports);
        self::assertStringContainsString('function consumeRequestPreview()', $reports);
        self::assertStringContainsString("return 'jem-diagnostics-' . \$timestamp . '.json';", $controller);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $layout);
        self::assertStringContainsString('name="include_names" value="1" checked', $layout);
        self::assertStringContainsString('value="housekeeping.downloadDiagnostics"', $layout);
        self::assertStringContainsString('value="housekeeping.downloadDiagnosticsZip"', $layout);
        self::assertStringContainsString('task=housekeeping.previewDiagnostics', $layout);
        self::assertStringNotContainsString('housekeeping.sendDiagnostics', $layout);
        self::assertStringNotContainsString('name="send_consent"', $layout);
        self::assertStringContainsString('repair510DiagnosticReportsSchema()', $installer);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_diagnostic_reports`', $installSql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_diagnostic_reports`', $updateSql);
    }

    public function testSupportExportSelectionsAndOverrideInventoryAreScoped(): void
    {
        $reports = $this->read('admin/classes/housekeepingreports.class.php');
        $overrides = $this->read('admin/classes/housekeepingoverrides.class.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $controller = $this->read('admin/controllers/housekeeping.php');

        self::assertStringContainsString("'component' => \$post->getInt('include_component_settings'", $controller);
        self::assertStringContainsString("'settings' => \$post->getInt('include_selected_settings'", $controller);
        self::assertStringContainsString("'inventory' => \$post->getInt('include_inventory'", $controller);
        self::assertStringContainsString("'override_templates' => \$post->getInt('include_template_overrides'", $controller);
        self::assertStringContainsString("'override_languages' => \$post->getInt('include_language_overrides'", $controller);
        self::assertStringContainsString("'override_css' => \$post->getInt('include_css_overrides'", $controller);
        self::assertStringContainsString("'types' => \$post->get('include_type_items'", $controller);
        self::assertStringContainsString("'view_types' => \$post->get('include_view_types'", $controller);
        self::assertStringContainsString("'languages' => \$post->get('include_language_ini'", $controller);
        self::assertStringContainsString("'language_files'", $reports);
        self::assertStringContainsString("'sha256'", $reports);
        self::assertStringContainsString("'#__jem_config'", $reports);
        self::assertStringContainsString("self::REDACTED", $reports);
        self::assertStringContainsString('redactedTemplateOverrides(', $reports);
        self::assertStringContainsString("#(/templates/)[^/]+/#", $reports);
        self::assertStringContainsString('selectedReportSections', $reports);
        self::assertStringContainsString("JPATH_ADMINISTRATOR", $reports);
        self::assertStringContainsString("'administrator' => JPATH_ADMINISTRATOR", $overrides);
        self::assertStringContainsString("'layouts/com_jem'", $overrides);
        self::assertStringContainsString("'/language/overrides/*.override.ini'", $overrides);
        self::assertStringContainsString('form="jem-diagnostic-support-form"', $layout);
        self::assertStringContainsString('name="include_selected_settings" value="1" checked', $layout);
        self::assertStringContainsString('name="include_component_settings" value="1" checked', $layout);
        self::assertStringContainsString('name="include_view_types[]"', $layout);
        self::assertStringContainsString('name="include_language_ini[]"', $layout);
        self::assertStringContainsString('name="include_type_items[]"', $layout);
        self::assertStringContainsString('name="include_inventory" value="1"', $layout);
        self::assertStringContainsString('name="include_template_overrides" value="1"', $layout);
        self::assertStringContainsString('name="include_language_overrides" value="1"', $layout);
        self::assertStringContainsString('name="include_css_overrides" value="1"', $layout);
        self::assertStringNotContainsString('COM_JEM_TOOLS_MODULE_ITEM', $layout);
        self::assertStringContainsString('class="d-flex justify-content-end flex-shrink-0 ms-lg-auto" method="post"', $layout);
        self::assertStringContainsString('<div class="col-12"><div class="card h-100"><div class="card-body">', $layout);
        self::assertLessThan(strpos($layout, "Text::_('COM_JEM_TOOLS_OVERRIDES')"), strpos($layout, "Text::_('COM_JEM_TOOLS_LANGUAGES')"));
    }

    public function testOverrideDeletionIsScopedAndReportTablesOfferSelectAll(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $controller = $this->read('admin/controllers/housekeeping.php');
        $overrides = $this->read('admin/classes/housekeepingoverrides.class.php');
        $script = $this->read('media/js/housekeeping-diagnostics-sort.js');

        self::assertSame(4, substr_count($layout, 'data-jem-select-all="report"'));
        self::assertStringContainsString('data-jem-select-all="overrides"', $layout);
        self::assertStringContainsString('class="form-check-input jem-diagnostic-override-item"', $layout);
        self::assertStringContainsString('class="btn btn-sm btn-outline-danger jem-diagnostic-delete-one"', $layout);
        self::assertStringContainsString('form="jem-diagnostic-delete-one-form"', $layout);
        self::assertStringContainsString('form="jem-diagnostic-delete-template-form"', $layout);
        self::assertStringContainsString('form="jem-diagnostic-delete-language-form"', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_LANGUAGE_OVERRIDES', $layout);
        self::assertStringContainsString('COM_JEM_TOOLS_CSS_OVERRIDES', $layout);
        self::assertStringContainsString("Text::_('COM_JEM_TOOLS_OVERRIDE_FOUND_AT')", $layout);
        self::assertStringNotContainsString("Text::_('COM_JEM_TOOLS_OVERRIDE_PRIORITY')", $layout);
        self::assertStringContainsString('function deleteOverrides(): void', $controller);
        self::assertStringContainsString("authorise('core.admin')", $controller);
        self::assertStringContainsString("consumeActionNonce('housekeeping.deleteOverrides'", $controller);
        self::assertStringContainsString('function deleteSelected(array $selected): array', $overrides);
        self::assertStringContainsString("str_starts_with(\$local, 'site/')", $overrides);
        self::assertStringContainsString('removeLanguageKeys', $overrides);
        self::assertStringContainsString("!window.confirm(deleteForm.dataset.jemConfirm)", $script);
        self::assertStringContainsString("!window.confirm(deleteOneForm.dataset.jemConfirm + '\\n' + path)", $script);
    }

    public function testSavedReportsCanBeOpenedAndIndividuallyDeletedByAdministrators(): void
    {
        $layout = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $view = $this->read('admin/views/housekeeping/view.html.php');
        $controller = $this->read('admin/controllers/housekeeping.php');
        $reports = $this->read('admin/classes/housekeepingreports.class.php');

        self::assertStringContainsString("\$reports->load(\$this->diagnosticReportId)", $view);
        self::assertStringContainsString("\$this->diagnosticStored['data']['report']", $view);
        self::assertStringContainsString('COM_JEM_TOOLS_REPORT_HEADING', $layout);
        self::assertStringContainsString("\$this->diagnosticStored['created']", $layout);
        self::assertStringContainsString('name="return_report"', $layout);
        self::assertStringContainsString('name="delete_report_nonce"', $layout);
        self::assertStringContainsString('icon-times', $layout);
        self::assertStringContainsString('function deleteDiagnosticReport(): void', $controller);
        self::assertStringContainsString("consumeActionNonce('housekeeping.deleteDiagnosticReport'", $controller);
        self::assertStringContainsString('function delete(int $id): bool', $reports);
        self::assertStringContainsString("->where(\$this->db->quoteName('id') . ' = ' . \$id)", $reports);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
    }
}
