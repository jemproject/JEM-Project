<?php

declare(strict_types=1);

use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DayMenuRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if ($root === '' || !is_file($root . '/libraries/src/Component/Router/RouterView.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 installation (no database required).');
        }

        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/day_router_environment.php';
        require_once JEM_TEST_ROOT . '/site/router.php';
    }

    private function menu(string $layout, bool $legacyDate = false): DayRouterMenu
    {
        $items = [];
        foreach ([101 => 'Red', 202 => 'Green'] as $id => $title) {
            $query = ['option' => 'com_jem', 'view' => 'day', 'layout' => $layout];
            if ($legacyDate) {
                $query['id'] = '0';
            }
            $items[$id] = (object) [
                'id' => $id,
                'component_id' => 42,
                'component' => 'com_jem',
                'language' => '*',
                'title' => $title,
                'query' => $query,
                'params' => new Registry([
                    'page_heading' => $title,
                    'categoryswitchcats' => [$id],
                    'timeline_filter_venues' => [$id + 1],
                    'timeline_filter_types' => [$id + 2],
                    'timeline_filter_countries' => ['DE'],
                    'timeline_filter_contacts' => [$id + 3],
                ]),
            ];
        }

        return new DayRouterMenu($items);
    }

    #[DataProvider('navigationProvider')]
    public function testNavigationKeepsTheSelectedMenu(
        string $layout,
        int $menuId,
        string $date,
        bool $legacyDate,
        bool $strict,
        array $extra
    ): void {
        PluginHelper::$strictRouting = $strict;
        $menu = $this->menu($layout, $legacyDate);
        $menu->activeId = $menuId;
        $originalQuery = $menu->getActive()->query;
        $originalParams = $menu->getActive()->params->toArray();
        $router = new JemRouter(new DayRouterApplication(), $menu);
        // Registering the router must not turn the menu into a fixed-date entity.
        self::assertSame($originalQuery, $menu->getActive()->query);

        $query = array_merge([
            'option' => 'com_jem', 'view' => 'day', 'layout' => $layout,
            'id' => $date, 'Itemid' => $menuId, 'catid' => 8, 'locid' => 9,
        ], $extra);
        $query = $router->preprocess($query);
        // This stage also runs with SEF disabled.
        self::assertSame($menuId, $query['Itemid']);
        self::assertSame($date, $query['id']);

        $segments = $router->build($query);
        self::assertSame($menuId, $query['Itemid'] ?? null);
        self::assertSame($date, $query['id'] ?? null);
        self::assertSame([], $segments);

        // Simulate Joomla selecting the menu from its path, then parsing the component URL.
        $menu->activeId = (int) $query['Itemid'];
        $parsed = array_merge($router->parse($segments), $query);
        self::assertSame('day', $parsed['view']);
        self::assertSame($layout, $parsed['layout']);
        self::assertSame($date, $parsed['id']);
        self::assertSame(8, $parsed['catid']);
        self::assertSame(9, $parsed['locid']);
        self::assertSame($originalParams, $menu->getActive()->params->toArray());
        self::assertSame($menuId === 101 ? 'Red' : 'Green', $menu->getActive()->title);
        foreach ($extra as $key => $value) {
            self::assertSame($value, $parsed[$key]);
        }
    }

    public static function navigationProvider(): iterable
    {
        foreach ([false, true] as $strict) {
            // Print context must survive both preprocess (non-SEF) and build/parse.
            $printLayouts = ['default', 'timeline', 'timetable'];
            if (is_file(JEM_TEST_ROOT . '/site/views/day/tmpl/venueboard.php')) {
                $printLayouts[] = 'venueboard';
            }
            foreach ($printLayouts as $printLayout) {
                yield "print $printLayout strict=$strict" => [$printLayout, 202, '20261101', true, $strict, [
                    'print' => 1, 'tmpl' => 'component', 'mode' => 'timetable',
                    'timeline_days_to_show' => $printLayout === 'venueboard' ? 1 : 7,
                    'jem_timeline_layout' => 'details', 'show_archived_events' => 1,
                    'locids' => '9,10', 'catids' => '8,11', 'pub' => '1,2',
                    'filter_search' => 'A&B + # café', 'filter_type' => 1,
                    'filter_order' => 'a.title', 'filter_order_Dir' => 'DESC',
                    'limit' => 10, 'limitstart' => 20,
                ]];
            }
            foreach (['timeline', 'timetable', 'default'] as $layout) {
                foreach ([101, 202] as $menuId) {
                    foreach ([false, true] as $legacy) {
                        $name = "$layout menu $menuId legacy=$legacy strict=$strict";
                        yield $name => [$layout, $menuId, '20261012', $legacy, $strict, []];
                    }
                }
            }
            foreach (['today' => '20261011', 'previous day' => '20261010',
                'previous week' => '20261004', 'next week' => '20261018'] as $name => $date) {
                yield "$name strict=$strict" => ['timeline', 202, $date, false, $strict, [
                    'timeline_days_to_show' => 7, 'jem_timeline_layout' => 'details',
                ]];
            }
        }
    }

    public function testLinkToAnotherDayMenuHonoursTheExplicitItemid(): void
    {
        $menu = $this->menu('timeline');
        $menu->activeId = 101;
        $router = new JemRouter(new DayRouterApplication(), $menu);
        $query = $router->preprocess([
            'option' => 'com_jem', 'view' => 'day', 'layout' => 'timeline', 'id' => '20261012', 'Itemid' => 202,
        ]);
        $router->build($query);
        self::assertSame(202, $query['Itemid'] ?? null);
    }

    public function testMenuLookupWithoutExplicitItemidReturnsAScalar(): void
    {
        $router = new JemRouter(new DayRouterApplication(), $this->menu('timeline'));
        $query = $router->preprocess(['option' => 'com_jem', 'view' => 'day', 'layout' => 'timeline', 'id' => '20261012']);
        self::assertSame(101, $query['Itemid']);
        $router->build($query);
        self::assertSame(101, $query['Itemid'] ?? null);
        self::assertSame('20261012', $query['id'] ?? null);
    }

    public function testChangingLayoutKeepsAnExplicitDayMenu(): void
    {
        $menu = $this->menu('timeline');
        $menu->activeId = 202;
        $router = new JemRouter(new DayRouterApplication(), $menu);
        $query = $router->preprocess([
            'option' => 'com_jem', 'view' => 'day', 'layout' => 'timetable', 'id' => '20261012', 'Itemid' => 202,
        ]);
        self::assertSame(202, $query['Itemid']);
        self::assertSame([], $router->build($query));
        self::assertSame(202, $query['Itemid']);
        self::assertSame('timetable', $query['layout']);
    }

    public function testLanguageSpecificDayMenuIsPreserved(): void
    {
        Multilanguage::$enabled = true;
        $menu = $this->menu('timeline');
        $menu->items[101]->language = 'en-GB';
        $menu->items[202]->language = 'de-DE';
        $menu->activeId = 202;
        $router = new JemRouter(new DayRouterApplication(), $menu);
        $query = $router->preprocess([
            'option' => 'com_jem', 'view' => 'day', 'layout' => 'timeline',
            'id' => '20261012', 'Itemid' => 202, 'lang' => 'de-DE',
        ]);
        $router->build($query);
        self::assertSame(202, $query['Itemid']);
        self::assertSame('de-DE', $query['lang']);
        self::assertSame('20261012', $query['id']);
    }

    public function testLegacyMenuLessDayDateRouteStillRoundTrips(): void
    {
        $router = new JemRouter(new DayRouterApplication(), new DayRouterMenu([]));
        $query = ['option' => 'com_jem', 'view' => 'day', 'id' => '20261012'];
        $segments = $router->build($query);
        self::assertSame(['day', '20261012'], $segments);
        self::assertSame(['view' => 'day', 'id' => '20261012'], $router->parse($segments));
        self::assertSame([], $segments);
    }

    public function testOldDaySegmentsBelowAMenuStillParse(): void
    {
        $menu = $this->menu('timeline');
        $menu->activeId = 202;
        $router = new JemRouter(new DayRouterApplication(), $menu);
        $segments = ['day', '20261012'];
        $parsed = $router->parse($segments);
        self::assertSame('day', $parsed['view']);
        self::assertSame('timeline', $parsed['layout']);
        self::assertSame('20261012', $parsed['id']);
        self::assertSame([], $segments);
    }

    public function testAnEventLinkStillBuildsAndParsesAsAnEvent(): void
    {
        $router = new JemRouter(new DayRouterApplication(), new DayRouterMenu([]));
        $query = ['option' => 'com_jem', 'view' => 'event', 'id' => '15:example'];
        $segments = $router->build($query);
        self::assertSame(['event', '15:example'], $segments);
        self::assertSame(['id' => '15', 'view' => 'event'], $router->parse($segments));
    }
}
