<?php

declare(strict_types=1);

use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Test source-menu routing using native Joomla rules with isolated menu fixtures. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CalendarSourceRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if ($root === '' || !is_file($root . '/libraries/src/Component/Router/RouterView.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to Joomla 5/6; no database is required.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/day_router_environment.php';
        require_once JEM_TEST_ROOT . '/site/router.php';
    }

    /** Create competing Calendar/Day menus with deliberately different display options. */
    private function menu(string $view = 'calendar', bool $explicitDayLayout = false): DayRouterMenu
    {
        $items = [];
        foreach ([101 => $view, 202 => 'day'] as $id => $menuView) {
            $query = ['option' => 'com_jem', 'view' => $menuView];
            if (in_array($menuView, ['category', 'venue'], true)) {
                $query += ['layout' => 'calendar', 'id' => 7];
            } elseif ($id === 202 && $explicitDayLayout) {
                $query['layout'] = 'default';
            }
            $items[$id] = (object) [
                'id' => $id, 'component_id' => 42, 'component' => 'com_jem', 'language' => '*',
                'query' => $query,
                'params' => new Registry(['show_only_start' => (int) ($id === 101), 'page_heading' => 'Menu ' . $id]),
            ];
        }
        $menu = new DayRouterMenu($items);
        $menu->activeId = 101;

        return $menu;
    }

    /** Generate the real Calendar-to-Day link rather than hand-coding its marker. */
    private function query(string $task = ''): array
    {
        $link = JemCalendarDayLink::build(
            'index.php?option=com_jem&view=day&id=20261105&catid=7&locid=9', 101, $task
        );
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return $query;
    }

    public static function calendarMenus(): iterable
    {
        foreach (['calendar', 'weekcal', 'annualcalendar', 'category', 'venue'] as $view) {
            foreach ([false, true] as $strict) {
                foreach ([false, true] as $explicitLayout) {
                    yield "$view strict=$strict explicitDefault=$explicitLayout" => [$view, $strict, $explicitLayout];
                }
            }
        }
    }

    #[DataProvider('calendarMenus')]
    public function testCalendarDrillDownPreservesSourceOptionsAndExportContext(string $view, bool $strict, bool $explicitLayout): void
    {
        PluginHelper::$strictRouting = $strict;
        foreach (['', 'archive'] as $task) {
            $menu = $this->menu($view, $explicitLayout);
            $router = new JemRouter(new DayRouterApplication(), $menu);
            $query = $this->query($task);
            // Navigation and export links carry the same source, scope and archive selection.
            $query += ['calcats' => '3,4', 'calvenues' => '6', 'limitstart' => 0];
            foreach ([[], ['print' => 1, 'tmpl' => 'component'], ['format' => 'pdf']] as $export) {
                $request = $router->preprocess($query + $export);
                // preprocess also runs when SEF is disabled.
                self::assertSame(101, (int) $request['Itemid']);
                $segments = $router->build($request);
                self::assertSame(101, (int) $request['Itemid']);
                $menu->activeId = (int) $request['Itemid'];
                $parsed = array_merge($router->parse($segments), $request);
                self::assertSame('day', $parsed['view']);
                self::assertSame('20261105', $parsed['id']);
                self::assertSame('default', $parsed['layout']);
                self::assertSame('1', $parsed['jemcal']);
                self::assertSame($task ?: null, $parsed['task'] ?? null);
                self::assertSame('7', $parsed['catid']);
                self::assertSame('9', $parsed['locid']);
                self::assertSame('3,4', $parsed['calcats']);
                self::assertSame('6', $parsed['calvenues']);
                self::assertSame(1, $menu->getActive()->params->get('show_only_start'));
                self::assertSame(0, $menu->items[202]->params->get('show_only_start'));
                foreach ($export as $key => $value) {
                    self::assertSame($value, $parsed[$key]);
                }
            }
        }
    }

    public static function invalidMarkers(): iterable
    {
        foreach ([null, '', 0, '0', 2, 'yes', true, ['1']] as $index => $marker) {
            yield (string) $index => [$marker];
        }
    }

    #[DataProvider('invalidMarkers')]
    public function testInvalidOrMissingCalendarMarkerUsesNormalMenuSelection(mixed $marker): void
    {
        $menu = $this->menu();
        $router = new JemRouter(new DayRouterApplication(), $menu);
        $query = $this->query();
        $query['jemcal'] = $marker;
        $result = $router->preprocess($query);
        self::assertSame(202, (int) $result['Itemid']);
    }

    public function testIntegerMarkerIsAccepted(): void
    {
        $router = new JemRouter(new DayRouterApplication(), $this->menu());
        $query = $this->query();
        $query['jemcal'] = 1;
        self::assertSame(101, (int) $router->preprocess($query)['Itemid']);
    }

    public function testUnrelatedMenusDoNotBecomeCalendarSources(): void
    {
        foreach (['eventslist', 'category', 'venue'] as $view) {
            $menu = $this->menu($view);
            unset($menu->items[101]->query['layout']);
            $router = new JemRouter(new DayRouterApplication(), $menu);
            self::assertSame(202, (int) $router->preprocess($this->query())['Itemid']);
        }
        $menu = $this->menu();
        $menu->items[101]->query['option'] = 'com_content';
        $router = new JemRouter(new DayRouterApplication(), $menu);
        self::assertSame(202, (int) $router->preprocess($this->query())['Itemid']);
    }

    public function testCalendarMenuLanguageMustMatchTheRequestedLanguage(): void
    {
        Multilanguage::$enabled = true;
        $menu = $this->menu();
        $menu->items[101]->language = 'en-GB';
        $menu->items[202]->language = 'de-DE';
        $router = new JemRouter(new DayRouterApplication(), $menu);
        self::assertSame(101, (int) $router->preprocess($this->query() + ['lang' => 'en-GB'])['Itemid']);
        self::assertSame(202, (int) $router->preprocess($this->query() + ['lang' => 'de-DE'])['Itemid']);
    }

    public function testExplicitDayMenuStillWinsWithoutACalendarMarker(): void
    {
        $menu = $this->menu('day');
        $router = new JemRouter(new DayRouterApplication(), $menu);
        $query = $this->query();
        unset($query['jemcal']);
        $query['Itemid'] = 202;
        $query['layout'] = 'timeline';
        self::assertSame(202, (int) $router->preprocess($query)['Itemid']);
    }

    public function testArrayScopeInputsAreIgnoredWithoutPhpWarnings(): void
    {
        set_error_handler(static function ($severity, $message, $file, $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            foreach (['catids', 'locids', 'pub', 'calcats', 'calvenues'] as $key) {
                foreach ([['7', '9'], [['7']], []] as $value) {
                    self::assertSame([], JemCalendarDayLink::context(new Input([$key => $value])));
                }
                self::assertSame([$key => '7,9'], JemCalendarDayLink::context(new Input([$key => '7,9,7,-1,bad'])));
            }
        } finally {
            restore_error_handler();
        }
    }
}
