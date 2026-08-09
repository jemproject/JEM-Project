<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventVenueHierarchyContractsTest extends TestCase
{
    public function testInstallAndUpgradeSchemasContainHierarchyColumns(): void
    {
        $install = $this->read('admin/sql/install.mysql.utf8.sql');
        $upgrade = $this->read('admin/sql/updates/mysql/5.1.0.sql');

        foreach (array('parent_event_id', 'event_tree_order', 'show_in_calendar') as $column) {
            self::assertStringContainsString('`' . $column . '`', $install);
            self::assertStringContainsString('`' . $column . '`', $upgrade);
        }

        foreach (array('parent_venue_id', 'venue_tree_order') as $column) {
            self::assertStringContainsString('`' . $column . '`', $install);
            self::assertStringContainsString('`' . $column . '`', $upgrade);
        }

        $installer = $this->read('script.php');
        self::assertStringContainsString('repair510HierarchySchemaFallback', $installer);
        self::assertStringContainsString("'idx_parent_event'", $installer);
        self::assertStringContainsString("'idx_parent_venue'", $installer);
    }

    public function testBackendFormsExposeParentAndOrderingFields(): void
    {
        $event = $this->read('admin/models/forms/event.xml');
        $venue = $this->read('admin/models/forms/venue.xml');

        self::assertStringContainsString('name="parent_event_id"', $event);
        self::assertStringContainsString('name="event_tree_order"', $event);
        self::assertStringContainsString('name="show_in_calendar"', $event);
        self::assertStringContainsString('name="parent_venue_id"', $venue);
        self::assertStringContainsString('name="venue_tree_order"', $venue);
    }

    public function testTablesRejectSelfReferencesAndCycles(): void
    {
        $event = $this->read('admin/tables/event.php');
        $venue = $this->read('admin/tables/venue.php');

        self::assertStringContainsString('parent_event_id', $event);
        self::assertStringContainsString('isHierarchyDescendant', $event);
        self::assertStringContainsString('parent_venue_id', $venue);
        self::assertStringContainsString('isHierarchyDescendant', $venue);
        self::assertStringContainsString('COM_JEM_EVENT_ERROR_PARENT_IS_SUBEVENT', $event);
        self::assertStringContainsString('COM_JEM_EVENT_ERROR_PARENT_HAS_PROGRAMME', $event);
        self::assertStringContainsString('childrenRemainInsideParent', $event);
        self::assertStringContainsString('empty($parent->dates) || empty($child->dates)', $event);
        self::assertStringContainsString('getScheduleBounds($child, false)', $event);
        self::assertStringContainsString('!$isParent && empty($event->enddates) && !empty($event->times)', $event);
        self::assertStringContainsString('synchroniseChildVenueTimezones', $venue);
    }

    public function testSharedListModelSupportsParentChildVisibility(): void
    {
        $model = $this->read('site/models/eventslist.php');

        self::assertStringContainsString('filter.event_tree', $model);
        self::assertStringContainsString('a.parent_event_id', $model);
        self::assertStringContainsString('a.show_in_calendar', $model);
        self::assertStringContainsString('getEventParentVisibilityWhere', $model);
        self::assertStringContainsString('getVisibleVenueHierarchyIds', $model);
        self::assertStringContainsString('visibleUserVenueList', $model);
    }

    public function testEventAndVenueDetailModelsLoadTheirTrees(): void
    {
        $event = $this->read('site/models/event.php');
        $venue = $this->read('site/models/venue.php');

        self::assertStringContainsString('getChildEvents', $event);
        self::assertStringContainsString('parent_event_id', $event);
        self::assertStringContainsString('getChildVenues', $venue);
        self::assertStringContainsString('parent_venue_id', $venue);
        self::assertStringContainsString('canViewEventAncestors', $event);
        self::assertStringContainsString('canViewHierarchyVenue', $event);
        self::assertStringContainsString('canViewVenueAncestors', $venue);

        $eventView = $this->read('site/views/event/view.html.php');
        $venueView = $this->read('site/views/venue/view.html.php');
        self::assertStringContainsString('eventHierarchy', $eventView);
        self::assertStringContainsString('venueHierarchy', $venueView);
        self::assertStringContainsString("COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND'), 404", $venueView);
    }

    public function testTemplatesAndRawExportsExposeTheProgramme(): void
    {
        foreach (array(
            'site/views/event/tmpl/default.php',
            'site/views/event/tmpl/responsive/default.php',
        ) as $template) {
            self::assertStringContainsString('common/hierarchy/event_programme.php', $this->read($template), $template);
        }

        foreach (array(
            'site/views/venue/tmpl/default.php',
            'site/views/venue/tmpl/responsive/default.php',
        ) as $template) {
            self::assertStringContainsString('common/hierarchy/venue_tree.php', $this->read($template), $template);
        }

        $raw = $this->read('site/views/event/view.raw.php');
        self::assertStringContainsString('buildPdfProgrammeHtml', $raw);
        self::assertStringContainsString('JemHelper::icalAddEvent($vcal, $child)', $raw);
        self::assertStringContainsString("Text::_('COM_JEM_PARENT_EVENT')", $raw);
        self::assertStringContainsString("COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'), 404", $raw);

        $venueRaw = $this->read('site/views/venue/view.raw.php');
        self::assertStringContainsString("COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND'), 404", $venueRaw);
    }

    public function testAllEventModulesOfferTheSameHierarchyModes(): void
    {
        foreach (array('jem', 'jem_banner', 'jem_cal', 'jem_jubilee', 'jem_map', 'jem_teaser', 'jem_types', 'jem_wide') as $module) {
            $manifest = $this->read('modules/mod_' . $module . '/mod_' . $module . '.xml');
            self::assertStringContainsString('name="event_tree_mode"', $manifest, $module);
            self::assertStringContainsString('value="calendar"', $manifest, $module);
            self::assertStringContainsString('value="parents"', $manifest, $module);
            self::assertStringContainsString('value="children"', $manifest, $module);
            self::assertStringContainsString('value="all"', $manifest, $module);
        }
    }

    public function testAllPublicEventViewsOfferTheSameHierarchyModes(): void
    {
        foreach (array(
            'eventslist/tmpl/default.xml',
            'calendar/tmpl/default.xml',
            'weekcal/tmpl/default.xml',
            'annualcalendar/tmpl/default.xml',
            'day/tmpl/default.xml',
            'day/tmpl/timeline.xml',
            'day/tmpl/timetable.xml',
            'category/tmpl/default.xml',
            'category/tmpl/calendar.xml',
            'venue/tmpl/default.xml',
            'venue/tmpl/calendar.xml',
            'eventsmap/tmpl/default.xml',
            'typeevents/tmpl/default.xml',
            'search/tmpl/default.xml',
        ) as $view) {
            $manifest = $this->read('site/views/' . $view);
            self::assertStringContainsString('name="event_tree_mode"', $manifest, $view);
            self::assertStringContainsString('value="calendar"', $manifest, $view);
            self::assertStringContainsString('value="parents"', $manifest, $view);
            self::assertStringContainsString('value="children"', $manifest, $view);
            self::assertStringContainsString('value="all"', $manifest, $view);
        }

        $venueCalendar = $this->read('site/models/venuecal.php');
        self::assertStringContainsString("setState('filter.venue_id', \$this->getVenueTreeIds", $venueCalendar);
        self::assertStringContainsString('parent_venue_id', $venueCalendar);
        self::assertGreaterThanOrEqual(2, substr_count($venueCalendar, 'applyCalendarDateState()'));
        self::assertStringContainsString("date('Y-m-d', (int) \$this->_date)", $venueCalendar);

        $search = $this->read('site/models/search.php');
        self::assertStringContainsString('getEventParentVisibilityWhere', $search);
        self::assertStringContainsString('getVenueHierarchyVisibilityWhere', $search);
        self::assertStringContainsString("params->get('event_tree_mode', 'calendar')", $search);

        foreach (array('day.php', 'category.php', 'venue.php') as $modelFile) {
            self::assertStringContainsString(
                "setState('filter.event_tree'",
                $this->read('site/models/' . $modelFile),
                $modelFile
            );
        }
    }

    public function testHierarchyRoundTripsThroughImportExportAndIntegrations(): void
    {
        $export = $this->read('admin/models/export.php');
        $import = $this->read('admin/controllers/import.php');
        $security = $this->read('admin/helpers/importsecurity.php');
        $finder = $this->read('plugins/plg_finder_jem/jem.php');
        $mailer = $this->read('plugins/plg_jem_mailer/mailer.php');

        foreach (array('parent_event_id', 'event_tree_order', 'show_in_calendar', 'parent_venue_id', 'venue_tree_order') as $field) {
            self::assertTrue(
                str_contains($export, $field) || str_contains($import, $field),
                'Missing hierarchy import/export field: ' . $field
            );
        }

        self::assertStringContainsString('parent_event_id', $security);
        self::assertStringContainsString('parent_venue_id', $security);
        self::assertStringContainsString('parent_event_title', $finder);
        self::assertStringContainsString('$identity !== null ? $identity->getAuthorisedViewLevels() : array(1)', $finder);
        self::assertStringContainsString('method_exists($application, \'getMenu\')', $finder);
        self::assertStringContainsString('_appendParentEventContext', $mailer);

        $helper = $this->read('site/helpers/helper.php');
        $map = $this->read('site/helpers/map.php');
        $types = $this->read('modules/mod_jem_types/helper.php');
        self::assertStringContainsString('getVenueHierarchyVisibilityWhere', $helper);
        self::assertStringContainsString('getVisibleVenueHierarchyIds', $map);
        self::assertStringContainsString('getVenueHierarchyVisibilityWhere', $types);
    }

    public function testVenueSelectorsExcludeDescendantsBeforeSubmit(): void
    {
        foreach (array('admin/models/fields/venueparent.php', 'site/models/fields/venueparent.php') as $field) {
            $source = $this->read($field);
            self::assertStringContainsString('getDescendantIds', $source, $field);
            self::assertStringContainsString('NOT IN', $source, $field);
        }
    }

    public function testBulkVenueMovesRunTheEventHierarchyValidation(): void
    {
        $model = $this->read('admin/models/event.php');

        self::assertStringContainsString('public function moveToVenue($pks, $venueId)', $model);
        self::assertStringContainsString('$event->locid = $venueId;', $model);
        self::assertStringContainsString('if (!$event->check())', $model);
        self::assertStringContainsString("return \$this->updateEventsField(\$pks, 'locid', \$venueId);", $model);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
