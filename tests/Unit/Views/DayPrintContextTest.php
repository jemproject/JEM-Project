<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use JemProject\Tests\DayPrint\Application;
use JemProject\Tests\DayPrint\DayModel;
use JemProject\Tests\DayPrint\DayView;
use JemProject\Tests\DayPrint\Factory;
use JemProject\Tests\DayPrint\JemHelper;
use JemProject\Tests\DayPrint\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute Day date/state preparation and print-link generation through a second request. */
final class DayPrintContextTest extends TestCase
{
    private const NS = 'JemProject\\Tests\\DayPrint';

    /** @param string $source PHP source. @param string $name Method. @return string Actual method declaration. */
    private static function method(string $source, string $name): string
    {
        self::assertSame(1, preg_match('/(?:public static|static public|protected|private|public) function '
            . $name . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms', $source, $match), $name);
        return $match[0];
    }

    /** @return void Load production methods with isolated Joomla and base-model services. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/day_print_environment.php';
        require_once JEM_TEST_ROOT . '/site/classes/listlimit.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/listorder.class.php';
        require_once JEM_TEST_ROOT . '/site/helpers/calendardaylink.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/day.php');
        $methods = '';
        foreach (['getOffsetDate', 'getDayParams', 'setDate', 'getDay', 'getDayEnd', 'populateState'] as $name) {
            $methods .= self::method($source, $name);
        }
        $policy = file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        foreach (['normaliseParamIds', 'normaliseParamStrings', 'applyMenuEventFilters'] as $name) {
            $methods .= self::method($policy, $name);
        }
        if (str_contains($policy, 'function applyMenuContactFilters(')) {
            $methods .= self::method($policy, 'applyMenuContactFilters');
        }
        // Only the database-backed descendant lookup is simulated, not the menu policy.
        $methods .= 'public function getListChildCat($id, $reset) { return [$id + 1]; }';
        $methods .= 'protected function getVenueTreeIds($id) { return [$id, $id + 1]; }';
        eval('namespace ' . self::NS . '; use \\DateTimeImmutable; use \\JemListOrder; use \\JemCalendarDayLink; class DayModel extends ModelBoundary {' . $methods . '}');

        $output = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        eval('namespace ' . self::NS . '; class JemOutput {' . self::method($output, 'buildPrintLink') . '}');
        $agenda = file_get_contents(JEM_TEST_ROOT . '/site/helpers/calendaragenda.php');
        eval('namespace ' . self::NS . '; class JemCalendarAgendaHelper {' . self::method($agenda, 'getDayMode') . '}');

        $view = file_get_contents(JEM_TEST_ROOT . '/site/views/day/view.html.php');
        self::assertSame(1, preg_match('/\$isHourGrid = .*?;/s', $view, $mode));
        self::assertSame(1, preg_match('/\$model->setDate\([^;]+;/', $view, $date));
        self::assertSame(1, preg_match('/\$isTimetableLayout = .*?(?=        \/\/ Get data from model)/s', $view, $limits));
        self::assertSame(1, preg_match('/\$print_link = \$this->buildDayPrintLink\([^;]+;/', $view, $call));
        self::assertSame(1, preg_match('/        foreach \(\$this->buildDayLinkQuery.*?(?=        \$this->)/s', $view, $paging));
        eval('namespace ' . self::NS . '; use \\JemCalendarDayLink; class DayView extends ViewBoundary {'
            . self::method($view, 'buildDayPrintLink')
            . self::method($view, 'buildDayLinkQuery')
            . 'public function pageContext($model, $params): array { $day = $model->getDay();'
            . '$pagination = new class { public array $values = []; public function setAdditionalUrlParam($k, $v) { $this->values[$k] = $v; }};'
            . $paging[0] . 'return $pagination->values; }'
            . 'public function prepareModel($model, $params): void {'
            . '$requestDate = Factory::$app->input->getCmd("id", ""); $isVenueBoard = $this->getLayout() === "venueboard";'
            . $mode[0] . $date[0] . $limits[0] . '$model->getState(); }'
            . 'public function printLink($model, $params): string { $day = $model->getDay();'
            . $call[0] . 'return $print_link; }}');
    }


    /** @return void Day uses the same predefined menu filters as its calendar source. */
    public function testDayRetainsCalendarMenuPolicy(): void
    {
        Factory::$app = new Application(['view' => 'day', 'id' => '20270115', 'task' => 'archive'], [
            'onlyfeatured' => 1, 'categoryswitch' => 0, 'categoryswitchcats' => [7],
            'includesubcategories' => 1, 'timeline_filter_venues' => [9],
            'timeline_filter_types' => [3], 'timeline_filter_countries' => ['ES'],
            'calendar_contacts' => [12], 'calendar_contact_category' => 4,
        ]);
        $state = (new DayModel())->getState();
        self::assertSame(1, $state['filter.featured'] ?? null);
        self::assertSame([7, 8], $state['filter.category_id']);
        self::assertFalse($state['filter.category_id.include']);
        self::assertSame([9], $state['filter.venue_id']);
        self::assertSame([3], $state['filter.type_id']);
        self::assertSame(['ES'], $state['filter.country_id']);
        self::assertSame(2, $state['filter.published']);
        if (str_contains(file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php'), 'function applyMenuContactFilters(')) {
            self::assertSame([12], $state['filter.contact_ids']);
            self::assertSame(4, $state['filter.contact_category_id']);
        }
    }


    /** @return void Source calendar scope is retained without affecting ordinary Day menus. */
    public function testCalendarSourceSpecificScope(): void
    {
        foreach (['calendar', 'weekcal', 'annualcalendar', 'category', 'venue', 'day'] as $source) {
            $app = new Application(['id' => '20270115', 'catid' => 7, 'locid' => 9,
                'calcats' => '3,3,4 OR 1=1', 'calvenues' => '12', 'calhide' => 1], ['show_only_start' => 1]);
            $app->menu->query = ['option' => 'com_jem', 'view' => $source, 'layout' => 'calendar'];
            Factory::$app = $app;
            $state = (new DayModel())->getState();
            self::assertSame($source !== 'day', $state['filter.calendar_startdayonly']);
            self::assertSame('3', $state['filter.calendar_excluded_categories']);
            self::assertSame('12', $state['filter.calendar_excluded_venues']);
            self::assertTrue($state['filter.calendar_hide_all']);
            if ($source === 'category') {
                self::assertNull($state['filter.req_catid']);
                self::assertSame(7, $state['filter.filter_catid']);
            }
            if ($source === 'venue' && str_contains(file_get_contents(JEM_TEST_ROOT . '/site/models/day.php'), 'function getVenueTreeIds(')) {
                self::assertNull($state['filter.req_venid']);
                self::assertNull($state['filter.filter_locid']);
                self::assertSame([9, 10], $state['filter.venue_id']);
            }
        }
    }

    /** @return void Pagination changes the slice, not the scope; print retains the same exclusions. */
    public function testPaginationAndPrintKeepLegendScope(): void
    {
        $app = new Application(['id' => '20270115', 'Itemid' => 101, 'task' => 'archive',
            'locid' => 9, 'topcat' => 7, 'calcats' => '2,3', 'calvenues' => '12',
            'jemcal' => 1, 'filter_search' => 'Music', 'filter_type' => 1, 'limit' => 10, 'limitstart' => 10], []);
        $app->menu->query = ['option' => 'com_jem', 'view' => 'calendar'];
        Factory::$app = $app;
        $model = new DayModel();
        $view = new DayView('default');
        $view->prepareModel($model, $app->params);
        $query = $view->pageContext($model, $app->params);
        foreach (['id' => '20270115', 'Itemid' => 101, 'task' => 'archive', 'locid' => 9,
            'topcat' => 7, 'calcats' => '2,3', 'calvenues' => '12', 'jemcal' => 1,
            'filter_search' => 'Music', 'filter_type' => 1, 'limit' => 10] as $key => $value) {
            self::assertSame($value, $query[$key], $key);
        }
        self::assertArrayNotHasKey('limitstart', $query);
        self::assertArrayNotHasKey('print', $query);
        self::assertArrayNotHasKey('tmpl', $query);
        parse_str(parse_url(html_entity_decode($view->printLink($model, $app->params)), PHP_URL_QUERY), $print);
        self::assertSame('2,3', $print['calcats']);
        self::assertSame('12', $print['calvenues']);
        self::assertSame('10', $print['limitstart']);
    }

    /** @return void Reset routing observations and the Joomla-local date. */
    protected function setUp(): void
    {
        Route::$calls = [];
        JemHelper::$today = '2026-10-30';
    }

    /** @return iterable<string, array{string, int, array, bool}> Supported layouts, request windows, filters and menus. */
    public static function contexts(): iterable
    {
        $layouts = ['default', 'timeline', 'timetable', 'custom_list'];
        if (is_file(JEM_TEST_ROOT . '/site/views/day/tmpl/venueboard.php')) {
            $layouts[] = 'venueboard';
        }
        foreach ($layouts as $layout) {
            foreach ([-1, 0, 7, 99] as $days) {
                foreach ([[], ['locid' => 10], ['locid' => 10, 'catid' => 2]] as $filters) {
                    foreach ([false, true] as $menu) {
                        yield "$layout:$days:" . json_encode($filters) . ':' . (int) $menu => [$layout, $days, $filters, $menu];
                    }
                }
            }
        }
    }

    /** @param string $layout Layout. @param int $days Requested interval. @param array $filters Filters. @param bool $menu Active menu. @return void */
    #[DataProvider('contexts')]
    public function testPrintRoundTripRetainsTheEffectiveDayContext(string $layout, int $days, array $filters, bool $menu): void
    {
        // id=0 simulates Today: model/session keys differ once the print link freezes the date.
        $request = $filters + ['id' => '0', 'timeline_days_to_show' => $days, 'limitstart' => 21,
            'locids' => '10,11', 'catids' => '2,3', 'pub' => '1,2', 'topcat' => 2, 'lang' => 'es-ES',
            'jem_timeline_layout' => 'compact'];
        if ($menu) {
            $request['Itemid'] = 101;
        }
        $settings = ['days' => 2, 'timeline_days_to_show' => 3, 'timeline_card_layout' => 'details',
            'day_display_mode' => 'list', 'show_archived_events' => 1];
        $app = new Application($request, $settings, $menu);
        $stateKey = 'com_jem.day.0:' . ($menu ? 101 : 0) . '.';
        $search = 'A&B + # " café';
        $app->session = [$stateKey . 'filter_search' => $search, $stateKey . 'filter_type' => 1,
            $stateKey . 'filter_order' => 'a.title', $stateKey . 'filter_order_Dir' => 'DESC',
            $stateKey . 'limit' => 10];
        Factory::$app = $app;
        $model = new DayModel();
        $view = new DayView($layout);
        $view->prepareModel($model, $app->params);
        $sourceState = $model->getState();
        $before = serialize([$app->input, $app->params, $app->session, $sourceState]);
        $link = $view->printLink($model, $app->params);
        self::assertSame($before, serialize([$app->input, $app->params, $app->session, $model->getState()]));
        parse_str(parse_url(html_entity_decode($link, ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY), $query);
        self::assertSame('com_jem', $query['option']);
        self::assertSame('day', $query['view']);
        self::assertSame('20261101', $query['id']);
        self::assertSame($layout, $query['layout'], 'Layout can come from the menu instead of the request.');
        self::assertSame((string) ($layout === 'venueboard' ? 1 : ($days > 0 ? min(30, $days) : 3)), $query['timeline_days_to_show']);
        self::assertSame('list', $query['mode']);
        self::assertSame('1', $query['print']);
        self::assertSame('component', $query['tmpl']);
        self::assertSame($search, $query['filter_search']);
        self::assertSame('a.title', $query['filter_order']);
        self::assertSame('DESC', $query['filter_order_Dir']);
        self::assertSame((string) $sourceState['list.limit'], $query['limit']);
        self::assertSame((string) $sourceState['list.start'], $query['limitstart']);
        self::assertSame($menu ? '101' : null, $query['Itemid'] ?? null);
        foreach ($filters as $key => $value) {
            self::assertSame((string) $value, $query[$key]);
        }
        foreach (['locids', 'catids', 'pub', 'topcat', 'lang'] as $key) {
            self::assertSame((string) $request[$key], $query[$key]);
        }
        self::assertSame($layout === 'timeline' ? 'compact' : null, $query['jem_timeline_layout'] ?? null);

        // Different date and empty session: only the link can preserve source state.
        JemHelper::$today = '2026-11-10';
        $printed = new Application($query, $settings, $menu);
        Factory::$app = $printed;
        $printModel = new DayModel();
        $printView = new DayView($query['layout']);
        $printView->prepareModel($printModel, $printed->params);
        $printState = $printModel->getState();
        unset($sourceState['params'], $printState['params']);
        self::assertSame($sourceState, $printState, 'Date-keyed search, filters, ordering and pagination must not change.');
        self::assertSame($model->getDay(), $printModel->getDay());
        self::assertSame($model->getDayEnd(), $printModel->getDayEnd());
        self::assertSame($link, $printView->printLink($printModel, $printed->params), 'Print controls must remain idempotent.');
    }

    /** @return iterable<string, array{array, array, string, string}> Mode/card defaults and explicit overrides. */
    public static function modes(): iterable
    {
        yield 'hourgrid override' => [['mode' => 'timetable'], [], 'timetable', 'compact'];
        yield 'list override' => [['mode' => 'list'], ['day_display_mode' => 'timetable'], 'list', 'compact'];
        yield 'menu hourgrid' => [[], ['day_display_mode' => 'timetable'], 'timetable', 'compact'];
        yield 'invalid mode' => [['mode' => 'bogus'], ['day_display_mode' => 'timetable'], 'timetable', 'compact'];
        yield 'details override' => [['jem_timeline_layout' => 'details'], [], 'list', 'details'];
        yield 'configured details' => [[], ['timeline_card_layout' => 'details'], 'list', 'details'];
        yield 'invalid card override' => [['jem_timeline_layout' => 'bogus'], ['timeline_card_layout' => 'details'], 'list', 'details'];
        yield 'invalid configured card' => [[], ['timeline_card_layout' => 'bogus'], 'list', 'details'];
    }

    /** @param array $input Request. @param array $settings Menu. @param string $mode Expected mode. @param string $card Expected card. @return void */
    #[DataProvider('modes')]
    public function testModeAndTimelineCardUseExistingValidation(array $input, array $settings, string $mode, string $card): void
    {
        foreach (['default', 'timeline'] as $layout) {
            Factory::$app = new Application($input + ['id' => '20261030'], $settings);
            $model = new DayModel();
            $view = new DayView($layout);
            $view->prepareModel($model, Factory::$app->params);
            $link = $view->printLink($model, Factory::$app->params);
            parse_str(parse_url(html_entity_decode($link), PHP_URL_QUERY), $query);
            self::assertSame($mode, $query['mode']);
            self::assertSame($layout === 'timeline' ? $card : null, $query['jem_timeline_layout'] ?? null);
            self::assertSame('101', $query['Itemid'], 'Preserve effective active menu even without an explicit Itemid.');
            if ($mode === 'timetable' || $layout === 'timeline') {
                self::assertSame('0', $query['limit']);
                self::assertSame('0', $query['limitstart']);
            }
        }
    }

    /** @return void Only recognized frontend display context reaches the print URL. */
    public function testUnexpectedInputCannotReplacePrintControlsOrBecomeAnAction(): void
    {
        $input = ['id' => '20260230', 'task' => 'event.remove', 'format' => 'raw', 'tmpl' => 'module',
            'print' => 0, 'token' => 'not-a-real-token', 'return' => 'https://example.invalid/',
            'jform' => ['title' => 'not part of the display'], 'random_parameter' => 'unused',
            'filter_search' => 'A&B # " + café', 'locid' => -10, 'catid' => -2, 'timeline_days_to_show' => PHP_INT_MAX];
        Factory::$app = new Application($input, []);
        $model = new DayModel();
        $view = new DayView('timeline');
        $view->prepareModel($model, Factory::$app->params);
        $link = $view->printLink($model, Factory::$app->params);
        parse_str(parse_url(html_entity_decode($link), PHP_URL_QUERY), $query);
        foreach (['task', 'format', 'token', 'return', 'jform', 'random_parameter'] as $key) {
            self::assertArrayNotHasKey($key, $query);
        }
        self::assertSame('20261030', $query['id'], 'Freeze the model fallback, not the invalid requested date.');
        self::assertSame('30', $query['timeline_days_to_show']);
        self::assertSame('-10', $query['locid'], 'Do not silently drop a filter and widen the printed selection.');
        self::assertSame('-2', $query['catid']);
        self::assertSame('1', $query['print']);
        self::assertSame('component', $query['tmpl']);
        self::assertSame($input['filter_search'], $query['filter_search']);
        self::assertStringNotContainsString('&amp;amp;', $link);
        self::assertCount(1, Factory::$app->messages);
    }

    /** @return void Archive publication context is retained without accepting arbitrary actions. */
    public function testArchiveTaskAndExplicitArchiveToggleArePreserved(): void
    {
        foreach ([0, 1] as $showArchived) {
            Factory::$app = new Application(['id' => '20261030', 'task' => 'archive', 'pub' => '1,2', 'show_archived_events' => $showArchived],
                ['show_archived_events' => 1]);
            $model = new DayModel();
            $view = new DayView('default');
            $view->prepareModel($model, Factory::$app->params);
            parse_str(parse_url(html_entity_decode($view->printLink($model, Factory::$app->params)), PHP_URL_QUERY), $query);
            self::assertSame('archive', $query['task']);
            self::assertSame((string) $showArchived, $query['show_archived_events']);
            self::assertSame(2, $model->getState('filter.published'));
        }
    }
}

