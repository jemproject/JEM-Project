<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\DayPdfContext;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute production export-link and raw-view methods with isolated Joomla/model/rendering boundaries. */
final class DayPdfLayoutContextTest extends TestCase
{
    /**
     * Extract a complete production method without replacing its decisions.
     *
     * @param string $source PHP source.
     * @param string $name Method name.
     * @return string Method declaration and body.
     */
    private static function method(string $source, string $name): string
    {
        self::assertSame(1, preg_match(
            '/(?:static protected|public static|public) function ' . $name
            . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms',
            $source,
            $match
        ));
        return $match[0];
    }

    /** @return void Load production methods into a private fixture namespace. */
    public static function setUpBeforeClass(): void
    {
        $output = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        $raw = file_get_contents(JEM_TEST_ROOT . '/site/views/day/view.raw.php');
        eval('namespace ' . __NAMESPACE__ . '; class OutputFixture {'
            . self::method($output, 'buildCurrentPdfLink')
            . self::method($output, 'prepareExportLink')
            . ' public static function link() { return self::buildCurrentPdfLink(); }}');
        eval('namespace ' . __NAMESPACE__ . '; class RawViewFixture extends ViewBoundary {'
            . self::method($raw, 'display') . '}');
    }

    /** @return iterable<string, array{?string, string, bool, int, string}> Layout/menu/URI/range cases. */
    public static function contexts(): iterable
    {
        $cases = [
            'direct timeline' => ['timeline', 'none', 'timeline'],
            'timeline menu' => [null, 'timeline', 'timeline'],
            'explicit timeline over regular menu' => ['timeline', 'default', 'timeline'],
            'explicit default over timeline menu' => ['default', 'timeline', 'default'],
            'explicit timetable over timeline menu' => ['timetable', 'timeline', 'default'],
            'unrelated view' => [null, 'other_view', 'default'],
            'unrelated component' => [null, 'other_component', 'default'],
            'regular day' => ['default', 'none', 'default'],
            'timetable' => ['timetable', 'none', 'default'],
            'custom layout' => ['custom_list', 'timeline', 'default'],
        ];
        foreach ($cases as $name => [$layout, $menu, $expected]) {
            foreach ([true, false] as $queryFields) {
                foreach ([1, 3] as $days) {
                    yield "$name:query=" . (int) $queryFields . ":days=$days"
                        => [$layout, $menu, $queryFields, $days, $expected];
                }
            }
        }
    }

    /**
     * Follow the generated URL through the raw view, retaining the source selection and event rows.
     *
     * @param ?string $layout Resolved source layout, or menu fallback.
     * @param string $menuKind Active menu context.
     * @param bool $queryFields Whether the visible URI contains route fields.
     * @param int $days Selected interval.
     * @param string $expected Expected PDF renderer.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testRoundTrip(string|null $layout, string $menuKind, bool $queryFields, int $days, string $expected): void
    {
        $input = [
            'option' => 'com_jem', 'view' => 'day', 'id' => '20261101',
            'timeline_days_to_show' => $days, 'jem_timeline_layout' => 'details',
            'filter_search' => 'A&B + #', 'filter_order' => 'a.title',
            'calcats' => '2,3', 'calvenues' => '9', 'jemcal' => 1,
            'filter_order_Dir' => 'DESC', 'locid' => 7, 'catid' => 4,
            'Itemid' => 101, 'print' => 1, 'tmpl' => 'component',
            // A stale/injected marker must never override the actual source layout.
            'day_pdf_layout' => $expected === 'timeline' ? 'default' : 'timeline',
        ];
        if ($layout !== null) {
            $input['layout'] = $layout;
        }
        $menu = MenuItem::forKind($menuKind);
        Factory::$app = new Application($input, $menu);
        Uri::$current = new Uri($queryFields ? $input : [], $queryFields ? 'index.php' : '/events/today');
        $before = serialize([Uri::$current, Factory::$app]);
        $url = OutputFixture::link();
        self::assertSame($before, serialize([Uri::$current, Factory::$app]), 'Link generation must not mutate the source.');
        self::assertSame($queryFields ? 'index.php' : '/events/today', parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame($expected, $query['day_pdf_layout'] ?? null);
        self::assertSame('raw', $query['format']);
        self::assertSame('pdf', $query['layout']);
        self::assertArrayNotHasKey('print', $query);
        self::assertArrayNotHasKey('tmpl', $query);
        foreach (['id', 'timeline_days_to_show', 'jem_timeline_layout', 'filter_search',
            'filter_order', 'filter_order_Dir', 'locid', 'catid', 'Itemid', 'calcats', 'calvenues', 'jemcal'] as $key) {
            self::assertSame((string) $input[$key], $query[$key]);
        }

        Factory::$app = new Application($query, $menu);
        JemPdfView::$calls = [];
        $view = new RawViewFixture();
        $view->display();
        self::assertCount(1, JemPdfView::$calls);
        [$renderer, $args] = JemPdfView::$calls[0];
        self::assertSame($expected, $renderer);
        self::assertSame($expected === 'timeline' ? 'COM_JEM_DAY_VIEW_TIMELINE_TITLE' : 'COM_JEM_DAY_VIEW', $args[0]);
        self::assertSame($expected === 'timeline' ? 'jem-day-timeline.pdf' : 'jem-day.pdf', $args[2]);
        self::assertSame('20261101', $args[$expected === 'timeline' ? 3 : 4]);
        self::assertSame([7, 8], array_column($args[1], 'id'));
        self::assertSame('20261101', $view->model->date);
        self::assertSame(['list.start' => 0, 'list.limit' => 0], $view->model->state);
        self::assertFalse(Factory::$app->closed);
        $matching = in_array($menuKind, ['timeline', 'default'], true);
        self::assertSame($matching ? 'menu' : 'global', Factory::$app->params->get('origin'));
        if ($expected === 'timeline') {
            self::assertSame(Factory::$app->params, $args[4]);
        }
    }

    /** @return iterable<string, array{string, ?string, string}> Legacy/raw marker fallback cases. */
    public static function rawContexts(): iterable
    {
        foreach (['none', 'timeline', 'default', 'other_view', 'other_component'] as $menu) {
            foreach ([null, 'timeline', 'default', 'invalid'] as $marker) {
                $expected = in_array($marker, ['timeline', 'default'], true)
                    ? $marker : ($menu === 'timeline' ? 'timeline' : 'default');
                yield "$menu:" . ($marker ?? 'absent') => [$menu, $marker, $expected];
            }
        }
    }

    /**
     * Keep old menu-only export URLs working and reject unsupported source-layout values.
     *
     * @param string $menu Menu context.
     * @param ?string $marker Requested source layout.
     * @param string $expected Expected renderer.
     * @return void
     */
    #[DataProvider('rawContexts')]
    public function testRawMenuFallback(string $menu, ?string $marker, string $expected): void
    {
        $input = ['layout' => 'pdf', 'id' => '20261101'];
        if ($marker !== null) {
            $input['day_pdf_layout'] = $marker;
        }
        Factory::$app = new Application($input, MenuItem::forKind($menu));
        JemPdfView::$calls = [];
        (new RawViewFixture())->display();
        self::assertCount(1, JemPdfView::$calls);
        self::assertSame($expected, JemPdfView::$calls[0][0]);
    }

    /** @return void Other views must not acquire the Day-only source marker. */
    public function testOtherViewLinkIsUnchanged(): void
    {
        $query = ['option' => 'com_jem', 'view' => 'event', 'layout' => 'timeline', 'id' => '7'];
        Factory::$app = new Application($query, MenuItem::forKind('timeline'));
        Uri::$current = new Uri($query);
        parse_str(parse_url(OutputFixture::link(), PHP_URL_QUERY), $result);
        self::assertArrayNotHasKey('day_pdf_layout', $result);
        self::assertSame('event', $result['view']);
        self::assertSame('7', $result['id']);
    }

    /** @return void Non-PDF raw requests keep their existing exit behavior. */
    public function testNonPdfRequestDoesNotRender(): void
    {
        Factory::$app = new Application(['layout' => 'timeline'], null);
        JemPdfView::$calls = [];
        $view = new RawViewFixture();
        $view->display();
        self::assertTrue(Factory::$app->closed);
        self::assertSame([], JemPdfView::$calls);
        self::assertSame([], $view->model->state);
    }
}

/** Minimal input/parameter storage; values are already typed by these fixtures. */
final class Bag
{
    /** @param array $values Initial values. */
    public function __construct(public array $values = [])
    {
    }

    /** @param string $key Key. @param mixed $default Fallback. @param string $type Filter. @return mixed Value. */
    public function get(string $key, mixed $default = null, string $type = ''): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Key. @param string $default Fallback. @return string Command. */
    public function getCmd(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /** @param string $key Key. @param int $default Fallback. @return int Integer. */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /** @param string $key Key. @return bool Whether the key exists. */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /** @param string $key Key. @param mixed $value Value. @return void */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /** @return array Parameter values. */
    public function toArray(): array
    {
        return $this->values;
    }
}

/** Menu fixture including conflicting parameters to verify menu ownership checks. */
final class MenuItem
{
    /** @param array $query Menu route. */
    public function __construct(public array $query)
    {
    }

    /** @param string $kind Scenario. @return ?self Corresponding menu. */
    public static function forKind(string $kind): ?self
    {
        return $kind === 'none' ? null : new self([
            'option' => $kind === 'other_component' ? 'com_other' : 'com_jem',
            'view' => $kind === 'other_view' ? 'other' : 'day',
            'layout' => $kind === 'default' ? 'default' : 'timeline',
        ]);
    }

    /** @return Bag Menu-specific settings. */
    public function getParams(): Bag
    {
        return new Bag(['origin' => 'menu', 'timeline_card_layout' => 'compact']);
    }
}

/** Application boundary without real Joomla routing or headers. */
final class Application
{
    public Bag $input;
    public Bag $params;
    public bool $closed = false;

    /** @param array $input Request. @param ?MenuItem $menu Active menu. */
    public function __construct(array $input, public ?MenuItem $menu)
    {
        $this->input = new Bag($input);
        $this->params = new Bag(['origin' => 'global']);
    }

    /** @param string $component Component name. @return Bag Parameters. */
    public function getParams(string $component = ''): Bag
    {
        return $this->params;
    }

    /** @return self Minimal menu manager. */
    public function getMenu(): self
    {
        return $this;
    }

    /** @return ?MenuItem Active menu. */
    public function getActive(): ?MenuItem
    {
        return $this->menu;
    }

    /** @return void Capture an early application exit. */
    public function close(): void
    {
        $this->closed = true;
    }
}

/** Application lookup boundary. */
final class Factory
{
    public static Application $app;

    /** @return Application Current request. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

/** Shared URI boundary allows detection of unwanted source-request mutation. */
final class Uri
{
    public static self $current;

    /** @param array $query Visible query fields. @param string $path Visible path. */
    public function __construct(public array $query, public string $path = 'index.php')
    {
    }

    /** @return self Shared source URI. */
    public static function getInstance(): self
    {
        return self::$current;
    }

    /** @param bool $array Array flag. @return array Visible query. */
    public function getQuery(bool $array): array
    {
        return $this->query;
    }

    /** @param array $query New query. @return void */
    public function setQuery(array $query): void
    {
        $this->query = $query;
    }

    /** @param array $parts Requested URL parts. @return string URL. */
    public function toString(array $parts): string
    {
        return $this->path . '?' . http_build_query($this->query);
    }
}

/** Translation boundary. */
final class Text
{
    /** @param string $key Language key. @return string Stable untranslated key. */
    public static function _(string $key): string
    {
        return $key;
    }
}

/** Capture renderer choice and arguments, without generating a PDF. */
final class JemPdfView
{
    public static array $calls = [];

    /** @param mixed ...$args Renderer arguments. @return void */
    public static function renderDayTimeline(mixed ...$args): void
    {
        self::$calls[] = ['timeline', $args];
    }

    /** @param mixed ...$args Renderer arguments. @return void */
    public static function renderEventList(mixed ...$args): void
    {
        self::$calls[] = ['default', $args];
    }
}

/** Model spy: tests dispatch/data transport, not database querying or ACL implementation. */
final class Model
{
    public array $state = [];
    public string $date = '';

    /** @param string $date Requested day. @return void */
    public function setDate(string $date): void
    {
        $this->date = $date;
    }

    /** @param string $key State key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** @return array Event fixtures. */
    public function getItems(): array
    {
        return [(object) ['id' => 7], (object) ['id' => 8]];
    }
}

/** View model boundary for the complete raw display method. */
class ViewBoundary
{
    public Model $model;

    /** Initialise the isolated model. */
    public function __construct()
    {
        $this->model = new Model();
    }

    /** @return Model Model spy. */
    public function getModel(): Model
    {
        return $this->model;
    }

    /** @param string $name Requested model property. @return string Resolved day. */
    public function get(string $name): string
    {
        return $this->model->date;
    }
}
