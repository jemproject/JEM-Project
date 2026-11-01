<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real templates and URL expressions, native routing, isolated menus and formatting services. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FrontendLinkEscapingTest extends TestCase
{
    /** @return void Bootstrap Joomla libraries without an installed application or database. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires Joomla libraries; no installed site is changed.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function ($class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/day_router_environment.php';
        require_once JEM_TEST_ROOT . '/tests/Support/frontend_link_escaping_environment.php';
        require_once JEM_TEST_ROOT . '/site/router.php';
        Factory::$application = new class {
            /** @return string Routing client. */
            public function getName(): string
            {
                return 'site';
            }
        };
        Factory::$language = new class {
            /** @param string $key Translation key. @param mixed ...$args Options. @return string Key. */
            public function _($key, ...$args): string
            {
                return $key;
            }
        };
    }

    /** @return iterable Base path, SEF, menu and extra query combinations. */
    public static function routes(): iterable
    {
        foreach (['', '/joomla'] as $base) {
            foreach ([false, true] as $sef) {
                foreach ([false, true] as $menu) {
                    foreach ([false, true] as $extra) {
                        yield json_encode([$base, $sef, $menu, $extra]) => [$base, $sef, $menu, $extra];
                    }
                }
            }
        }
    }

    /**
     * Configure real SiteRouter and component router with synthetic menu data.
     * @param string $base Installation path.
     * @param bool $sef SEF flag.
     * @param bool $linked Explicit menu.
     * @param bool $extra Extra language/text query.
     * @return void
     */
    private function configure(string $base, bool $sef, bool $linked, bool $extra): void
    {
        $menu = new DayRouterMenu($linked ? [77 => (object) [
            'id' => 77, 'component_id' => 42, 'component' => 'com_jem', 'language' => '*',
            'query' => ['option' => 'com_jem', 'view' => 'eventslist'], 'route' => 'events', 'home' => 0,
        ]] : []);
        $router = (new ReflectionClass(SiteRouter::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($router, 'menu'))->setValue($router, $menu);
        $router->setComponentRouter('com_jem', new JemRouter(new DayRouterApplication(), $menu));
        $router->attachBuildRule([$router, 'buildInit'], Router::PROCESS_BEFORE);
        $router->attachBuildRule([$router, 'buildComponentPreprocess'], Router::PROCESS_BEFORE);
        if ($sef) {
            $router->attachBuildRule([$router, 'buildSefRoute'], Router::PROCESS_DURING);
        }
        $router->attachBuildRule([$router, 'buildBase'], Router::PROCESS_AFTER);
        $router->setVars(['option' => 'com_jem', 'view' => 'eventslist']);
        foreach (['root', 'base'] as $property) {
            (new ReflectionProperty(Uri::class, $property))->setValue(null, [
                'prefix' => 'https://example.test', 'path' => $base,
            ]);
        }
        (new ReflectionProperty(Route::class, '_router'))->setValue(null, ['site' => $router]);
        JemHelperRoute::$extra = ($linked ? '&Itemid=77' : '')
            . ($extra ? '&lang=es&note=' . rawurlencode('A&B "quote" <tag>') : '');
    }

    /** @param string $html Rendered fragment. @return DOMXPath Parsed once, as HTML attributes are. */
    private function document(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        self::assertSame(0, $dom->getElementsByTagName('script')->length);
        return new DOMXPath($dom);
    }


    /**
     * Check the branch-specific board or structured-data URL producer.
     * @param string $base Path. @param bool $sef SEF. @param bool $menu Menu. @param bool $extra Extra query.
     * @return void
     */
    #[DataProvider('routes')]
    public function testBranchSpecificLinks(string $base, bool $sef, bool $menu, bool $extra): void
    {
        $this->configure($base, $sef, $menu, $extra);
        $board = JEM_TEST_ROOT . '/site/views/day/tmpl/venueboard.php';
        $event = (object) ['slug'=>'4:test'];
        $view = new LinkEscapingView();
        if (is_file($board)) {
            $source = file_get_contents($board);
            self::assertSame(1, preg_match('~\\$eventUrl = (Route::_\\(JemHelperRoute::getEventRoute.*?);~', $source, $match));
            $eventUrl = eval('use Joomla\\CMS\\Router\\Route; return ' . $match[1] . ';');
            self::assertSame(1, preg_match('~href="<\\?php echo (\\$this->escape\\(\\$eventUrl\\));~', $source, $attribute));
            $expression = $attribute[1];
            $html = (function () use ($expression, $eventUrl) {
                return eval('return ' . $expression . ';');
            })->call($view);
            $this->assertUrls($this->document('<a href="' . $html . '">Event</a>'),
                [Route::_(JemHelperRoute::getEventRoute('4:test'), false)]);
            self::assertSame(1, preg_match('~\\$venueUrl = (.*?);~s', $source, $match));
            $location = (object) ['id'=>2, 'alias'=>'venue'];
            $mode = 'venues';
            $venueUrl = eval('use Joomla\\CMS\\Router\\Route; return ' . $match[1] . ';');
            self::assertSame(1, preg_match('~href="<\\?php echo (\\$this->escape\\(\\$venueUrl\\));~', $source, $attribute));
            $expression = $attribute[1];
            $html = (function () use ($expression, $venueUrl) {
                return eval('return ' . $expression . ';');
            })->call($view);
            $this->assertUrls($this->document('<a href="' . $html . '">Venue</a>'),
                [Route::_(JemHelperRoute::getVenueRoute('2:venue'), false)]);
        } else {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
            self::assertSame(1, preg_match('~\\$eventUrl = (!empty\\(\\$event->slug\\).*?);~', $source, $match));
            $eventUrl = eval('use Joomla\\CMS\\Router\\Route; return ' . $match[1] . ';');
            self::assertSame(1, preg_match('~<link itemprop="url" href="\' \\. (htmlspecialchars\\(\\$eventUrl,.*?\\)) \\.~', $source, $attribute));
            $html = eval('return ' . $attribute[1] . ';');
            $xpath = $this->document('<link itemprop="url" href="' . $html . '" />');
            self::assertSame(Route::_(JemHelperRoute::getEventRoute('4:test'), false),
                $xpath->query('//link/@href')->item(0)->value);
        }
    }

    /** @param DOMXPath $xpath Parsed output. @param array $expected Expected URLs in DOM order. @return void */
    private function assertUrls(DOMXPath $xpath, array $expected): void
    {
        $actual = [];
        foreach ($xpath->query('//a/@href | //@data-jem-event-url | //@data-href') as $attribute) {
            $actual[] = $attribute->value;
        }
        self::assertSame($expected, $actual);
    }

    /**
     * Render the complete shared list item, including clickable, hidden-title and Safari variants.
     * @param string $base Path. @param bool $sef SEF. @param bool $menu Menu. @param bool $extra Extra query.
     * @return void
     */
    #[DataProvider('routes')]
    public function testResponsiveItems(string $base, bool $sef, bool $menu, bool $extra): void
    {
        $this->configure($base, $sef, $menu, $extra);
        foreach ([[1,1,0,false,0], [1,1,1,false,0], [1,1,1,false,1], [0,1,0,false,0],
            [1,1,0,true,0], [1,0,0,false,0]] as [$title, $details, $popup, $safari, $featured]) {
            $settings = (object) ['showlocate'=>1, 'showlinkvenue'=>1, 'showcity'=>0, 'showstate'=>0,
                'showcat'=>0, 'showdetails'=>$details, 'showtitle'=>$title, 'showeventimage'=>0,
                'showatte'=>0, 'showtime'=>0, 'gddisabled'=>$popup];
            $row = (object) ['id'=>4,'slug'=>'4:test','title'=>'Title & "quote" <script>',
                'locid'=>2,'venueslug'=>'2:venue','venue'=>'Venue & "quote"','odd'=>0,'featured'=>$featured,
                'user_has_access_event'=>1,'user_has_access_venue'=>1,'fulltext'=>'Text',
                'dates'=>'2026-11-01','enddates'=>null,'times'=>null,'endtimes'=>null];
            $displayData = ['row'=>$row, 'params'=>new Registry(['show_introtext_events'=>1]),
                'jemsettings'=>$settings,'settings'=>new Registry(['event_show_readmore'=>1]),
                'isSafari'=>$safari,'showIconsInEventTitle'=>false,'showIconsInEventData'=>false,'showAvailabilityText'=>false];
            ob_start();
            require JEM_TEST_ROOT . '/site/common/views/tmpl/responsive/default_jem_eventslist_item.php';
            $html = ob_get_clean();
            $event = Route::_(JemHelperRoute::getEventRoute($row->slug), false);
            $venue = Route::_(JemHelperRoute::getVenueRoute($row->venueslug), false);
            $expected = [];
            if ($details && !$safari) {
                $expected[] = $event;
            }
            if ($details) {
                $expected[] = $event;
            }
            $expected[] = $venue;
            $expected[] = $event; // Existing read-more visibility is intentionally unchanged.
            $this->assertUrls($this->document($html), $expected);
            self::assertStringContainsString('Title &amp; &quot;quote&quot; &lt;script&gt;', $html);
        }
    }

    /**
     * Render both Types module modes and verify every event/type destination.
     * @param string $base Path. @param bool $sef SEF. @param bool $menu Menu. @param bool $extra Extra query.
     * @return void
     */
    #[DataProvider('routes')]
    public function testTypesModule(string $base, bool $sef, bool $menu, bool $extra): void
    {
        $this->configure($base, $sef, $menu, $extra);
        foreach (['summary','topn'] as $mode) {
            $params = new Registry(['display_mode'=>$mode]);
            $data = [(object) ['id'=>3,'name'=>'Type & "quote"','event_count'=>1,
                'events'=>[(object) ['id'=>4,'slug'=>'4:test','title'=>'Event <script>',
                'dates'=>'2026-11-01','enddates'=>null,'times'=>null,'endtimes'=>null]]]];
            ob_start();
            require JEM_TEST_ROOT . '/modules/mod_jem_types/tmpl/default.php';
            $html = ob_get_clean();
            $type = Route::_(JemHelperRoute::getTypeeventsRoute(3), false);
            $event = Route::_(JemHelper::applyEventRouteLayout(JemHelperRoute::getEventRoute('4:test'), $params), false);
            $this->assertUrls($this->document($html), $mode === 'summary' ? [$type] : [$type,$event,$type]);
        }
    }

    /**
     * Execute precise related URL expressions; larger view/model setup is outside this test.
     * @param string $base Path. @param bool $sef SEF. @param bool $menu Menu. @param bool $extra Extra query.
     * @return void
     */
    #[DataProvider('routes')]
    public function testRelatedUrlExpressions(string $base, bool $sef, bool $menu, bool $extra): void
    {
        $this->configure($base, $sef, $menu, $extra);
        $view = new LinkEscapingView();
        $view->item = (object) ['venueslug'=>'2:venue', 'venue'=>'Venue & "quote"'];
        foreach (['default.php','responsive/default.php'] as $layout) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/event/tmpl/' . $layout);
            self::assertSame(1, preg_match('~echo (\$this->escape\(Route::_\(JemHelperRoute::getVenueRoute\(\$this->item->venueslug\).*?);~', $source, $match));
            $expression = $match[1];
            $html = (function () use ($expression) {
                return eval('use Joomla\\CMS\\Router\\Route; return ' . $expression . ';');
            })->call($view);
            $this->assertUrls($this->document('<a href="' . $html . '">Venue</a>'),
                [Route::_(JemHelperRoute::getVenueRoute('2:venue'), false)]);
            // Directly emitted routes already have one HTML escape pass; keep them that way.
            self::assertSame(2, preg_match_all('~\\$venueName = (\'<a href=.*?);~', $source, $direct));
            foreach ($direct[1] as $expression) {
                $html = (function () use ($expression) {
                    return eval('use Joomla\\CMS\\Router\\Route; return ' . $expression . ';');
                })->call($view);
                $this->assertUrls($this->document($html), [Route::_(JemHelperRoute::getVenueRoute('2:venue'), false)]);
            }
        }
        foreach (['default_venues.php','responsive/default_venues.php'] as $layout) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/tmpl/' . $layout);
            self::assertSame(1, preg_match('~\$link = (htmlspecialchars\(Route::_\(JemHelperRoute::getTypevenuesRoute.*?);~', $source, $match));
            $row = (object) ['type_id'=>3];
            $html = eval('use Joomla\\CMS\\Router\\Route; return ' . $match[1] . ';');
            $this->assertUrls($this->document('<a href="' . $html . '">Type</a>'),
                [Route::_(JemHelperRoute::getTypevenuesRoute(3), false)]);
        }
        foreach (['timeline.php','responsive/timeline.php'] as $layout) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/' . $layout);
            self::assertSame(2, preg_match_all('~\$eventUrl = (Route::_\(JemHelperRoute::getEventRoute.*?);~', $source, $assignments));
            self::assertSame(2, preg_match_all('~data-href="<\?php echo (.*?); \?>"~', $source, $attributes));
            $row = (object) ['slug'=>'4:test'];
            foreach ($assignments[1] as $index => $assignment) {
                $eventUrl = eval('use Joomla\\CMS\\Router\\Route; return ' . $assignment . ';');
                $expression = $attributes[1][$index];
                $html = (function () use ($expression, $eventUrl) {
                    return eval('return ' . $expression . ';');
                })->call($view);
                $this->assertUrls($this->document('<a href="' . $eventUrl . '">Title</a><div data-href="' . $html . '"></div>'),
                    array_fill(0, 2, Route::_(JemHelperRoute::getEventRoute('4:test'), false)));
            }
        }
    }
}
