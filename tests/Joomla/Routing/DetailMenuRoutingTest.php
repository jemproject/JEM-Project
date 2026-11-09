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
final class DetailMenuRoutingTest extends TestCase
{
    /** @return void Load native Joomla routing/menu classes without booting or modifying a site. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if ($root === '' || !is_file($root . '/libraries/src/Component/Router/RouterView.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to Joomla 5/6 (no database required).');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/day_router_environment.php';
        require_once JEM_TEST_ROOT . '/tests/Support/detail_router_environment.php';
        require_once JEM_TEST_ROOT . '/site/router.php';
    }

    /** @return array<int, object> Distinct records, layouts, languages and access levels. */
    private function items(): array
    {
        $items = [];
        foreach ([
            [100, 'eventslist', null, null, '*', 1],
            [101, 'categories', null, null, '*', 1],
            [102, 'venues', null, null, '*', 1],
            [201, 'event', '7:alpha', null, '*', 1],
            [202, 'category', '3:arts', 'calendar', '*', 1],
            [203, 'venue', '9:room', null, '*', 1],
            [204, 'category', '4:music', null, '*', 1],
            [205, 'event', '7:alpha', null, 'en-GB', 1],
            [206, 'event', '7:alpha', null, 'de-DE', 1],
            [207, 'event', '7:alpha', null, '*', 3],
            [208, 'event', '7:alpha', 'custom', '*', 1],
            [209, 'event', '7wrong', null, '*', 1],
            [210, 'category', '3:arts', null, '*', 1],
            [211, 'calendar', null, null, '*', 1],
            [212, 'day', null, 'timeline', '*', 1],
            [213, 'typeevents', '2:type', null, '*', 1],
            [214, 'typevenues', '2:type', null, '*', 1],
            [215, 'myevents', null, null, '*', 1],
        ] as [$id, $view, $record, $layout, $language, $access]) {
            $query = ['option' => 'com_jem', 'view' => $view];
            if ($record !== null) {
                $query['id'] = $record;
            }
            if ($layout !== null) {
                $query['layout'] = $layout;
            }
            $items[$id] = (object) ['id' => $id, 'component_id' => 42, 'component' => 'com_jem',
                'language' => $language, 'access' => $access, 'home' => 0, 'title' => 'Menu ' . $id,
                'query' => $query, 'params' => new Registry(['page_heading' => 'Heading ' . $id])];
        }

        return $items;
    }

    /** @return iterable<string, array{int, array, int|null}> Independent route expectations. */
    public static function routes(): iterable
    {
        yield 'Event B from list' => [100, ['view' => 'event', 'id' => '8:beta'], 100];
        yield 'Event B from Event A' => [201, ['view' => 'event', 'id' => '8:beta'], 100];
        yield 'category from another calendar' => [202, ['view' => 'category', 'id' => '8:other'], 101];
        yield 'venue from another venue' => [203, ['view' => 'venue', 'id' => '11:other'], 102];
        yield 'exact event' => [100, ['view' => 'event', 'id' => '7:alpha'], 201];
        yield 'exact venue' => [100, ['view' => 'venue', 'id' => '9:room'], 203];
        yield 'exact category' => [101, ['view' => 'category', 'id' => '4:music'], 204];
        yield 'explicit exact event' => [100, ['view' => 'event', 'id' => 7, 'Itemid' => 201], 201];
        yield 'explicit list' => [201, ['view' => 'event', 'id' => 7, 'Itemid' => 100], 100];
        yield 'explicit wrong record' => [201, ['view' => 'event', 'id' => 8, 'Itemid' => 201], 100];
        yield 'explicit inaccessible menu' => [100, ['view' => 'event', 'id' => 7, 'Itemid' => 207], 201];
        yield 'inaccessible active menu' => [207, ['view' => 'event', 'id' => 7], 201];
        yield 'explicit wrong language' => [100, ['view' => 'event', 'id' => 7, 'Itemid' => 206], 201];
        yield 'German route' => [100, ['view' => 'event', 'id' => 7, 'lang' => 'de-DE'], 206];
        yield 'exact custom layout' => [201, ['view' => 'event', 'id' => 7, 'layout' => 'custom'], 208];
        yield 'other layout is not exact' => [201, ['view' => 'event', 'id' => 7, 'layout' => 'other'], 100];
        yield 'calendar layout' => [101, ['view' => 'category', 'id' => 3, 'layout' => 'calendar'], 202];
        yield 'default category layout' => [202, ['view' => 'category', 'id' => 3], 210];
        yield 'malformed request' => [201, ['view' => 'event', 'id' => '7wrong'], null];
        yield 'no active menu' => [0, ['view' => 'event', 'id' => 7], 201];
        yield 'known category origin' => [202, ['view' => 'event', 'id' => 8, 'catid' => 3], 202];
        yield 'different category origin' => [202, ['view' => 'event', 'id' => 8, 'catid' => 4], 100];
        yield 'known venue origin' => [203, ['view' => 'event', 'id' => 8, 'locid' => 9], 203];
        yield 'events by type origin' => [213, ['view' => 'event', 'id' => 8], 213];
        yield 'venues by type origin' => [214, ['view' => 'venue', 'id' => 11], 214];
        yield 'my events origin' => [215, ['view' => 'event', 'id' => 8], 215];
        yield 'eventslist unchanged' => [201, ['view' => 'eventslist'], 100];
        yield 'categories unchanged' => [201, ['view' => 'categories'], 101];
    }

    /**
     * Exercise preprocessing (also used without SEF) and native build/parse in both SEF modes.
     * @param int $active Active fixture menu. @param array $input Route. @param int|null $expected Selected menu.
     * @return void
     */
    #[DataProvider('routes')]
    public function testMenuSelectionAndRoundTrip(int $active, array $input, ?int $expected): void
    {
        foreach ([false, true] as $strict) {
            PluginHelper::$strictRouting = $strict;
            $menu = new DetailRouterMenu($this->items());
            $menu->setActive($active);
            $router = new JemRouter(new DetailRouterApplication(), $menu);
            $extra = ['task' => 'archive', 'filter_search' => 'piano', 'limitstart' => 10,
                'catid' => 4, 'tmpl' => 'component', 'print' => 1];
            $query = $router->preprocess(['option' => 'com_jem'] + $input + $extra);
            self::assertSame($expected, $query['Itemid'] ?? null);
            foreach ($extra as $key => $value) {
                self::assertSame($input[$key] ?? $value, $query[$key]);
            }
            $segments = $router->build($query);
            if (in_array($input['view'], ['event', 'venue', 'category'], true)) {
                self::assertSame($expected, $query['Itemid'] ?? null);
                if ($expected !== null && JemDetailMenu::matches($menu->getItem($expected)->query, $input)) {
                    self::assertSame([], $segments);
                } else {
                    self::assertSame([$input['view'], $input['id']], $segments);
                }
                $menu->setActive($expected ?? 0);
                $parsed = array_merge($router->parse($segments), $query);
                self::assertSame($input['view'], $parsed['view']);
                self::assertSame(JemDetailMenu::id($input['id']), JemDetailMenu::id($parsed['id']));
                self::assertSame($input['layout'] ?? 'default', $parsed['layout'] ?? 'default');
                foreach ($extra as $key => $value) {
                    self::assertSame($input[$key] ?? $value, $parsed[$key]);
                }
            }
        }
    }

    /** @return void A menu-less route must not inherit an unrelated component's home or entity. */
    public function testNoCompatibleMenuAndMalformedStoredIds(): void
    {
        $items = $this->items();
        $menu = new DetailRouterMenu([201 => $items[201], 209 => $items[209]]);
        $menu->setActive(201);
        $router = new JemRouter(new DetailRouterApplication(), $menu);
        $query = $router->preprocess(['option' => 'com_jem', 'view' => 'event', 'id' => 8]);
        self::assertArrayNotHasKey('Itemid', $query);
        self::assertSame(['event', 8], $router->build($query));
        $menu = new DetailRouterMenu([209 => $items[209]]);
        $router = new JemRouter(new DetailRouterApplication(), $menu);
        $query = $router->preprocess(['option' => 'com_jem', 'view' => 'event', 'id' => 7]);
        self::assertArrayNotHasKey('Itemid', $query);
    }

    /** @return void Native SiteMenu applies language and access eligibility before explicit selection. */
    public function testLanguageAndAuthorisedViewingLevels(): void
    {
        Multilanguage::$enabled = true;
        $menu = new DetailRouterMenu($this->items());
        $router = new JemRouter(new DetailRouterApplication(), $menu);
        $input = ['option' => 'com_jem', 'view' => 'event', 'id' => 7];
        self::assertSame(205, $router->preprocess($input)['Itemid']);
        self::assertSame(205, $router->preprocess($input + ['Itemid' => 207])['Itemid']);
        $menu = new DetailRouterMenu($this->items(), [1, 3]);
        $router = new JemRouter(new DetailRouterApplication(), $menu);
        self::assertSame(207, $router->preprocess($input + ['Itemid' => 207])['Itemid']);
    }

    /** @return void Existing Calendar -> Day filters and direct Day layouts survive this detail-only change. */
    public function testCalendarDayAndDirectDayControls(): void
    {
        foreach ([211, 202, 212] as $origin) {
            $menu = new DetailRouterMenu($this->items());
            $menu->setActive($origin);
            $router = new JemRouter(new DetailRouterApplication(), $menu);
            $input = ['option' => 'com_jem', 'view' => 'day', 'id' => '20270115',
                'Itemid' => $origin, 'jemcal' => 1, 'catid' => 3, 'task' => 'archive', 'layout' => 'timeline'];
            $query = $router->preprocess($input);
            self::assertSame($origin, $query['Itemid']);
            $segments = $router->build($query);
            self::assertSame($origin, $query['Itemid']);
            $parsed = array_merge($router->parse($segments), $query);
            foreach (['view', 'id', 'catid', 'task', 'layout'] as $key) {
                self::assertSame($input[$key], $parsed[$key]);
            }
        }
    }

    /** @return void Numeric and legacy slug menu records produce the same unambiguous URL. */
    public function testNumericMenusAndLegacyAliasesAreEquivalent(): void
    {
        foreach (['event' => [201, 7], 'venue' => [203, 9], 'category' => [204, 4]] as $view => [$menuId, $id]) {
            foreach ([$id, (string) $id, $id . ':old-alias'] as $stored) {
                $items = $this->items();
                $items[$menuId]->query['id'] = $stored;
                $menu = new DetailRouterMenu($items);
                $router = new JemRouter(new DetailRouterApplication(), $menu);
                $query = $router->preprocess(['option' => 'com_jem', 'view' => $view, 'id' => $id . ':new-alias']);
                self::assertSame($menuId, $query['Itemid']);
                self::assertSame([], $router->build($query));
                self::assertSame($stored, $menu->getItem($menuId)->query['id'], 'Do not rewrite stored menu links');
            }
        }
    }
}
