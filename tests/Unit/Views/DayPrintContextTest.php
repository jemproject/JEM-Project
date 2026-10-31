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
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/day.php');
        $methods = '';
        foreach (['getOffsetDate', 'getDayParams', 'setDate', 'getDay', 'getDayEnd', 'populateState'] as $name) {
            $methods .= self::method($source, $name);
        }
        eval('namespace ' . self::NS . '; use \\DateTimeImmutable; class DayModel extends ModelBoundary {' . $methods . '}');

        $output = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        eval('namespace ' . self::NS . '; class JemOutput {' . self::method($output, 'buildPrintLink') . '}');
        $agenda = file_get_contents(JEM_TEST_ROOT . '/site/helpers/calendaragenda.php');
        eval('namespace ' . self::NS . '; class JemCalendarAgendaHelper {' . self::method($agenda, 'getDayMode') . '}');

        $view = file_get_contents(JEM_TEST_ROOT . '/site/views/day/view.html.php');
        self::assertSame(1, preg_match('/\$isHourGrid = .*?;/s', $view, $mode));
        self::assertSame(1, preg_match('/\$model->setDate\([^;]+;/', $view, $date));
        self::assertSame(1, preg_match('/\$isTimetableLayout = .*?(?=        \/\/ Get data from model)/s', $view, $limits));
        self::assertSame(1, preg_match('/\$print_link = \$this->buildDayPrintLink\([^;]+;/', $view, $call));
        eval('namespace ' . self::NS . '; class DayView extends ViewBoundary {'
            . self::method($view, 'buildDayPrintLink')
            . 'public function prepareModel($model, $params): void {'
            . '$requestDate = Factory::$app->input->getCmd("id", ""); $isVenueBoard = $this->getLayout() === "venueboard";'
            . $mode[0] . $date[0] . $limits[0] . '$model->getState(); }'
            . 'public function printLink($model, $params): string { $day = $model->getDay();'
            . $call[0] . 'return $print_link; }}');
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
            Factory::$app = new Application(['id' => '20261030', 'task' => 'archive', 'show_archived_events' => $showArchived],
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

