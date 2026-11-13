<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Actual filtered SQL against rollback-only fixtures in a configured local Joomla site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CalendarNavigationJoomlaTest extends JoomlaTestCase
{
    private object $db;
    private array $ids;

    /** @return void Isolate dates, ACL and publication states without retaining fixture changes. */
    protected function setUp(): void
    {
        self::bootJoomlaSite();
        $app = Factory::getApplication();
        $app->setSession(Factory::getContainer()->get('session.cli'));
        $app->loadIdentity(new User(0));
        $app->getSession()->set('user', $app->getIdentity());
        $app->loadLanguage(new Language('en-GB'));
        Factory::$language = $app->getLanguage();
        $app->loadDocument();
        $app->input->set('option', 'com_jem');
        $app->input->set('view', 'calendar');
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/site/helpers/calendarnavigation.php';
        require_once JEM_TEST_ROOT . '/site/models/calendar.php';
        require_once JEM_TEST_ROOT . '/site/models/day.php';
        JemHelper::config()->lastupdate = time();
        JemHelper::config()->calendar_empty_months = 3;
        JemHelper::config()->access_level_locked_events = '["1"]';
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->db->transactionStart();
        $this->ids = array_map('intval', $this->db->setQuery('SELECT DISTINCT e.id FROM #__jem_events e'
            . ' JOIN #__jem_cats_event_relations r ON r.itemid=e.id'
            . ' JOIN #__jem_categories c ON c.id=r.catid WHERE c.published=1 AND c.access=1 ORDER BY e.id', 0, 4)->loadColumn());
        self::assertCount(4, $this->ids, 'Needs four sample events in public categories.');
        $this->db->setQuery('UPDATE #__jem_events SET published=-2')->execute();
        foreach ($this->ids as $index => $id) {
            $date = ['2026-09-15', '2027-03-12', '1980-01-01', '2050-06-05'][$index];
            $this->db->setQuery('UPDATE #__jem_events SET published=' . ($index === 3 ? 2 : 1)
                . ', access=' . ($index === 2 ? 2 : 1) . ', dates=' . $this->db->quote($date)
                . ', enddates=NULL, publish_up=NULL, publish_down=NULL, locid=0, type_id=NULL,'
                . ' age_level_id=NULL, parent_event_id=NULL, recurrence_first_id=0, show_in_calendar=1'
                . ' WHERE id=' . $id)->execute();
        }
    }

    /** @return void Leave the local events exactly as they were before the test. */
    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->transactionRollback();
        }
    }

    /** @return JemModelCalendar Model with the same public calendar policy used by the frontend. */
    private function model(): JemModelCalendar
    {
        $model = new JemModelCalendar();
        $model->setDate('2026-11-01');
        $model->getState();
        $model->setState('filter.unpublished', null);
        $model->setState('params', new Registry(['calendar_empty_months' => 3]));

        return $model;
    }

    /** @return void Bounds ignore date/pagination limits while retaining access/publication predicates. */
    public function testRangeUsesVisibleEventsWithoutChangingTheItemsQuery(): void
    {
        $model = $this->model();
        $dateFilter = $model->getState('filter.calendar_from');
        $model->setState('list.limit', 1);
        self::assertSame(['start' => '2026-09-01', 'end' => '2027-03-31'], $model->getCalendarNavigationRange());
        self::assertSame($dateFilter, $model->getState('filter.calendar_from'));
        self::assertSame(1, $model->getState('list.limit'));
        self::assertSame([], $model->getItems());
    }

    /** @return void The same query honours venue filters and applies the configured empty window. */
    public function testEmptyScopeAndConfiguredWindow(): void
    {
        $model = $this->model();
        $model->setState('filter.venue_id', 2147483647);
        $model->setState('filter.venue_id.include', true);
        $params = $model->getState('params');
        $params->set('calendar_empty_months', 1); // A menu value cannot override the global setting.
        JemHelper::config()->calendar_empty_months = 12;
        self::assertSame(JemCalendarNavigation::bounds(null, null, JemHelper::getJoomlaDate(), 12), $model->getCalendarNavigationRange());
    }

    /** @return void Archive browsing has its own interval instead of extending the active calendar. */
    public function testArchiveAndStartOnlyEndDate(): void
    {
        $model = $this->model();
        $model->setState('filter.published', 2);
        self::assertSame(['start' => '2050-06-01', 'end' => '2050-06-30'], $model->getCalendarNavigationRange());
        $model->setState('filter.published', 1);
        $this->db->setQuery("UPDATE #__jem_events SET enddates='2027-05-10' WHERE id=" . $this->ids[1])->execute();
        self::assertSame('2027-05-31', $model->getCalendarNavigationRange()['end']);
        $model->setState('filter.calendar_startdayonly', true);
        self::assertSame('2027-03-31', $model->getCalendarNavigationRange()['end']);
    }

    /** @return void Day navigation preserves its venue scope while dropping only its selected day. */
    public function testDayBoundsRespectVenueAndDoNotKeepSelectedDate(): void
    {
        Factory::getApplication()->input->set('view', 'day');
        $model = new JemModelDay();
        $model->setDate('20261113');
        $model->getState();
        $model->setState('filter.unpublished', null);
        self::assertSame(['start' => '2026-09-01', 'end' => '2027-03-31'], $model->getCalendarNavigationRange());
        $model->setState('filter.req_venid', 2147483647);
        self::assertSame(JemCalendarNavigation::bounds(null, null, JemHelper::getJoomlaDate()), $model->getCalendarNavigationRange());
    }

    /** @return void Category filtering and publication dates also constrain the interval. */
    public function testCategoryAndScheduledPublication(): void
    {
        $model = $this->model();
        $this->db->setQuery("UPDATE #__jem_events SET publish_up='2099-01-01 00:00:00' WHERE id=" . $this->ids[1])->execute();
        self::assertSame(['start' => '2026-09-01', 'end' => '2026-09-30'], $model->getCalendarNavigationRange());
        $model->setState('filter.category_id', 2147483647);
        $model->setState('filter.category_id.include', true);
        self::assertSame(JemCalendarNavigation::bounds(null, null, JemHelper::getJoomlaDate()), $model->getCalendarNavigationRange());
    }

    /** @return void An invalid year cannot use the old current-month fallback to bypass the guard. */
    public function testFallbackCannotBypassExplicitDateValidation(): void
    {
        $app = Factory::getApplication();
        $app->input->set('yearID', 2101);
        $app->input->set('monthID', 11);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);
        $this->model()->getItems();
    }

    /** @return void A URL outside the scope is rejected before the event item query. */
    public function testOutOfRangeRequestAndBoundaryMessage(): void
    {
        $app = Factory::getApplication();
        $app->input->set('yearID', 1973);
        $app->input->set('monthID', 1);
        $model = $this->model();
        $model->setDate('1973-01-01');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);
        $model->getItems();
    }

    /** @return iterable Calendar models sharing the same visibility policy. */
    public static function calendarScopes(): iterable
    {
        foreach (['Calendar', 'Annualcalendar', 'Weekcal', 'Day', 'Categorycal', 'Venuecal'] as $view) {
            foreach (['public', 'archive', 'editor'] as $scope) {
                yield $view . '-' . $scope => [$view, $scope];
            }
        }
    }

    /**
     * Verify actual publication/permission setup, rather than forcing a state predicate.
     *
     * @param string $view Calendar model name.
     * @param string $scope Public, archive or editor visibility.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('calendarScopes')]
    public function testEveryCalendarUsesItsActualPublicationScope(string $view, string $scope): void
    {
        $app = Factory::getApplication();
        $app->input->set('view', strtolower($view));
        $app->input->set('task', $scope === 'archive' ? 'archive' : '');
        $this->db->setQuery('UPDATE #__jem_events SET published=0, access=1 WHERE id=' . $this->ids[2])->execute();
        if ($scope === 'editor') {
            $factory = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class);
            foreach ($this->db->setQuery('SELECT id FROM #__users WHERE block=0')->loadColumn() as $id) {
                $user = $factory->loadUserById((int) $id);
                if ($user->authorise('core.admin')) {
                    $app->loadIdentity($user);
                    $app->getSession()->set('user', $user);
                    break;
                }
            }
            self::assertFalse((bool) $app->getIdentity()->guest, 'Needs a local administrator.');
        }
        if ($view === 'Categorycal') {
            $category = (int) $this->db->setQuery('SELECT id FROM #__jem_categories WHERE published=1 AND access=1 ORDER BY id', 0, 1)->loadResult();
            self::assertGreaterThan(0, $category);
            $this->db->setQuery('DELETE FROM #__jem_cats_event_relations WHERE itemid IN (' . implode(',', $this->ids) . ')')->execute();
            foreach ($this->ids as $id) {
                $this->db->setQuery('INSERT INTO #__jem_cats_event_relations (catid,itemid) VALUES (' . $category . ',' . $id . ')')->execute();
            }
            $app->input->set('id', $category);
        } elseif ($view === 'Venuecal') {
            $venue = (int) $this->db->setQuery('SELECT id FROM #__jem_venues WHERE published=1 AND access=1 ORDER BY id', 0, 1)->loadResult();
            self::assertGreaterThan(0, $venue);
            $this->db->setQuery('UPDATE #__jem_events SET locid=' . $venue . ' WHERE id IN (' . implode(',', $this->ids) . ')')->execute();
            $app->input->set('id', $venue);
        }
        require_once JEM_TEST_ROOT . '/site/models/' . strtolower($view) . '.php';
        $class = 'JemModel' . $view;
        $model = new $class();
        if (in_array($view, ['Calendar', 'Categorycal', 'Venuecal'], true)) {
            $model->setDate('2026-11-01');
        } elseif ($view === 'Day') {
            $model->setDate('20261113');
        }
        $expected = $scope === 'archive'
            ? ['start' => '2050-06-01', 'end' => '2050-06-30']
            : ['start' => $scope === 'editor' ? '1980-01-01' : '2026-09-01', 'end' => '2027-03-31'];
        self::assertSame($expected, $model->getCalendarNavigationRange());
        if ($view === 'Calendar' && $scope === 'editor') {
            $model = new JemModelCalendar();
            $model->setDate('1980-01-01');
            self::assertContains($this->ids[2], array_map('intval', array_column($model->getItems(), 'id')));
        }

    }

    /** @return void Module filters, archived-only mode and global empty-window policy match item scope. */
    public function testModuleScopeAndGlobalWindow(): void
    {
        require_once JEM_TEST_ROOT . '/modules/mod_jem_cal/helper.php';
        $params = new Registry(['CurrentEvents' => 1, 'ArchivedEvents' => 0]);
        self::assertSame(['start' => '2026-09-01', 'end' => '2027-03-31'], ModJemCalHelper::getNavigationRange($params));
        $params->set('CurrentEvents', 0);
        $params->set('ArchivedEvents', 1);
        self::assertSame(['start' => '2050-06-01', 'end' => '2050-06-30'], ModJemCalHelper::getNavigationRange($params));
        $params->set('venid', '2147483647');
        $params->set('calendar_empty_months', 1);
        JemHelper::config()->calendar_empty_months = 12;
        self::assertSame(JemCalendarNavigation::bounds(null, null, JemHelper::getJoomlaDate(), 12), ModJemCalHelper::getNavigationRange($params));
        $params->set('venid', '');
        $params->set('catid', '2147483647');
        self::assertSame(JemCalendarNavigation::bounds(null, null, JemHelper::getJoomlaDate(), 12), ModJemCalHelper::getNavigationRange($params));
    }

    /** @return void Module bounds are independent of page bounds, and apply after Month_offset. */
    public function testModuleNavigationOffsetsAndRememberedDates(): void
    {
        require_once JEM_TEST_ROOT . '/modules/mod_jem_cal/helper.php';
        $range = ['start' => '2026-11-01', 'end' => '2027-01-31'];
        JemCalendarNavigation::setRange(['start' => '2000-01-01', 'end' => '2000-01-31']);
        self::assertSame('2026-11-01', ModJemCalHelper::navigationMonth(1973, 1, 0, $range, false)->format('Y-m-d'));
        self::assertSame('2027-01-01', ModJemCalHelper::navigationMonth(2079, 1, 0, $range, false)->format('Y-m-d'));
        self::assertSame('2026-11-01', ModJemCalHelper::navigationMonth(2024, 11, 24, $range, true)->format('Y-m-d'));
        self::assertFalse(ModJemCalHelper::allowsMonth(2024, 10, 24, $range));
        self::assertFalse(ModJemCalHelper::allowsMonth(2027, 2, 0, $range));
        self::assertTrue(ModJemCalHelper::allowsMonth(2027, 1, 0, $range));
        self::assertTrue(JemCalendarNavigation::allows('2000-01-15'));
        self::assertFalse(JemCalendarNavigation::allows('2026-11-15'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);
        ModJemCalHelper::navigationMonth(2079, 1, 0, $range, true);
    }

    /** @return void All native module layouts render disabled HTML and AJAX controls without links. */
    public function testNativeModuleLayoutsHaveAccessibleBoundaries(): void
    {
        require_once JEM_TEST_ROOT . '/modules/mod_jem_cal/helper.php';
        Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE);
        $params = new Registry();
        $module = (object) ['id' => 2538, 'in_ajax_call' => true];
        $use_ajax = true;
        $offset_year = $today_year = $prev_month_year = $next_month_year = 2026;
        $offset_month = $today_month = 11;
        $prev_month = 10;
        $next_month = 12;
        $prev_year = 2025;
        $next_year = 2027;
        $today = 13;
        $first_day = 1;
        $Year_length = 1;
        $Month_length = 0;
        $day_name_length = 2;
        $Show_Tooltips = $Show_Tooltips_Title = $tooltips_max_events = 0;
        $days = [];
        $props_prev = $props_next = $props_home = $props_prev_year = $props_next_year = '';
        $disabledNavigation = JemCalendarNavigation::disabledAttributes();
        foreach (['default', 'darkblue', 'grid'] as $layout) {
            ob_start();
            try {
                include JEM_TEST_ROOT . '/modules/mod_jem_cal/tmpl/' . $layout . '.php';
                $html = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertStringContainsString('aria-disabled="true"', $html, $layout);
            self::assertStringContainsString('title="No more events"', $html, $layout);
            self::assertStringNotContainsString('<a ', $html, $layout);
        }
    }


    /** @return void Hidden annual markers cannot extend bounds; visible locked monthly announcements can. */
    public function testAnnualCalendarMirrorsItsLockedAnnouncementPolicy(): void
    {
        require_once JEM_TEST_ROOT . '/site/models/annualcalendar.php';
        JemHelper::config()->access_level_locked_events = '["1","2"]';
        self::assertSame('1980-01-01', $this->model()->getCalendarNavigationRange()['start']);
        Factory::getApplication()->input->set('view', 'annualcalendar');
        self::assertSame(['start' => '2026-09-01', 'end' => '2027-03-31'], (new JemModelAnnualcalendar())->getCalendarNavigationRange());
    }
}
