<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\CategoryCalendarFilename;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute the actual raw view with model/serialization boundaries isolated, not HTTP. */
final class CategoryIcalFilenameTest extends TestCase
{
    /** @return iterable<string, array{?string, string, string, bool}> Names, layout and menu fallback cases. */
    public static function filenames(): iterable
    {
        $names = [
            'normal' => ['Music', 'Music'],
            'missing' => [null, 'category-2'],
            'empty' => ['', 'category-2'],
            'whitespace' => ['   ', 'category-2'],
            'unicode' => ['Música y café', 'Música y café'],
            'slash' => ['Music / Jazz', 'Music - Jazz'],
            'backslash' => ['Live\\Music', 'Live-Music'],
            'quotes' => ['The "Jazz": Club', 'The -Jazz- Club'],
            'controls' => ["Music\r\nJazz\tLive", 'Music-Jazz-Live'],
            'reserved' => ['Music<>|?*Live', 'Music-Live'],
            'invalid_utf8' => ["Music\xFF", 'category-2'],
        ];
        foreach ($names as $label => [$name, $expected]) {
            foreach (['', 'ics'] as $layout) {
                foreach ([false, true] as $menuFallback) {
                    yield $label . ':' . $layout . ':' . (int) $menuFallback =>
                        [$name, $expected, $layout, $menuFallback];
                }
            }
        }
    }

    /**
     * Load full production display and the shared sender's filename sanitation.
     *
     * @return string Isolated production view class.
     */
    private static function viewClass(): string
    {
        $class = __NAMESPACE__ . '\\JemViewCategory';
        if (!class_exists($class, false)) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/category/view.raw.php');
            $source = preg_replace('/^use [^;]+;\R/m', '', substr($source, 5));
            eval('namespace ' . __NAMESPACE__ . ';' . $source);
            $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
            self::assertSame(1, preg_match(
                '/static public function sendCalendar\(\$calendartool, \$filename\)\s*\{(.*?)(?=\s*\$output =)/s',
                $helper,
                $sender
            ));
            JemHelper::$cleanFilename = eval(
                'return static function ($filename) {' . $sender[1] . 'return $filename;};'
            );
        }
        return $class;
    }

    /**
     * Reset boundaries and construct separate category and calendar models.
     *
     * @param ?object $category Requested category or missing record.
     * @param array $input Request values.
     * @param array $rows Synthetic event rows passed unchanged to the serializer.
     * @return object Production raw view using isolated services.
     */
    private static function view(?object $category, array $input, array $rows = []): object
    {
        $class = self::viewClass();
        Factory::$app = new Application(new Params($input));
        JemHelper::$enabled = true;
        JemHelper::$filename = null;
        JemHelper::$ids = [];
        JemFrontendAccess::$calls = [];
        JemPdfView::$called = false;
        return new $class(new Model($category), new Model(null, $rows));
    }

    /**
     * Use the authorized category, never an event-category relation with a coincident ID.
     *
     * @param ?string $name Category name; null omits the property.
     * @param string $expected Expected safe filename component.
     * @param string $layout Explicit or implicit iCalendar dispatch.
     * @param bool $menuFallback Resolve ID from menu and export an empty result.
     * @return void
     */
    #[DataProvider('filenames')]
    public function testFilenameAndExportSelection(?string $name, string $expected, string $layout, bool $menuFallback): void
    {
        $category = (object) ['user_has_access_category' => true];
        if ($name !== null) {
            $category->catname = $name;
        }
        $rows = $menuFallback ? [] : [(object) ['id' => 101], (object) ['id' => 202]];
        $input = ['layout' => $layout, 'id' => $menuFallback ? 0 : 2];
        if (!$menuFallback) {
            $input += ['yearID' => 2027, 'monthID' => 3];
        }
        $view = self::view($category, $input, $rows);
        // Undefined array/property warnings must fail rather than contaminate the download.
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $view->display();
        } finally {
            restore_error_handler();
        }
        self::assertSame('events_category_' . $expected . ($menuFallback ? '_202611.ics' : '_202703.ics'), JemHelper::$filename);
        self::assertSame(array_column($rows, 'id'), JemHelper::$ids);
        self::assertSame([true], JemFrontendAccess::$calls);
        self::assertSame(2, $view->categoryModel->id);
        self::assertSame(2, $view->calendarModel->id);
        self::assertSame($menuFallback ? '2026-11-01' : '2027-03-01', $view->calendarModel->date);
        self::assertSame([
            'list.start' => 0,
            'list.limit' => 100,
            'filter.calendar_multiday' => false,
        ], $view->calendarModel->state);
        self::assertSame(0, $view->calendarModel->relationLookups);
        self::assertSame(['Category', 'CategoryCal'], $view->modelReads);
        self::assertFalse(JemPdfView::$called);
    }

    /** @return iterable<string, array{string}> Independent dispatch/access controls. */
    public static function controls(): iterable
    {
        foreach (['missing', 'denied', 'disabled', 'pdf'] as $case) {
            yield $case => [$case];
        }
    }

    /**
     * Keep 404, access denial, the global export switch and PDF dispatch unchanged.
     *
     * @param string $case Control scenario.
     * @return void
     */
    #[DataProvider('controls')]
    public function testExportGuards(string $case): void
    {
        $category = $case === 'missing' ? null : (object) [
            'catname' => 'Music',
            'user_has_access_category' => $case !== 'denied',
        ];
        $view = self::view($category, ['id' => 2, 'layout' => $case === 'pdf' ? 'pdf' : 'ics']);
        JemHelper::$enabled = $case !== 'disabled';
        $error = null;
        try {
            $view->display();
        } catch (\Throwable $caught) {
            $error = $caught;
        }
        self::assertSame($case === 'missing' ? 404 : null, $error?->getCode());
        self::assertNull(JemHelper::$filename);
        self::assertSame([], JemHelper::$ids);
        self::assertSame(0, $view->calendarModel->relationLookups);
        self::assertSame($case === 'pdf', JemPdfView::$called);
        self::assertSame($case === 'disabled' ? [] : ['Category'], $view->modelReads);
        self::assertSame(match ($case) {
            'denied' => [false],
            'pdf' => [true],
            default => [],
        }, JemFrontendAccess::$calls);
    }
}

/** Request/parameter boundary. */
final class Params
{
    /** @param array $values Input values. */
    public function __construct(private array $values = [])
    {
    }

    /** @param string $key Key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Key. @param int $default Fallback. @return int Integer value. */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /** @param string $key Key. @param string $default Fallback. @return string Command value. */
    public function getCmd(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }
}

/** Menu fallback boundary. */
final class Application
{
    /** @param Params $input Request. */
    public function __construct(public Params $input)
    {
    }

    /** @return Params Active-menu category ID. */
    public function getParams(): Params
    {
        return new Params(['id' => 2]);
    }
}

/** Joomla application boundary. */
final class Factory
{
    public static Application $app;

    /** @return Application Current fixture application. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

/** Translation boundary. */
final class Text
{
    /** @param string $key Message key. @return string Untranslated key. */
    public static function _(string $key): string
    {
        return $key;
    }
}

/** Record delegation to the existing access guard, not its internal ACL policy. */
final class JemFrontendAccess
{
    public static array $calls = [];

    /** @param bool $allowed Category access. @param Application $app Application. @return bool Decision. */
    public static function enforceViewAccess(bool $allowed, Application $app): bool
    {
        self::$calls[] = $allowed;
        return $allowed;
    }
}

/** PDF rendering sentinel. */
final class JemPdfView
{
    public static bool $called = false;

    /** @param mixed ...$args Rendering arguments. @return void */
    public static function renderCategoryDetail(mixed ...$args): void
    {
        self::$called = true;
    }
}

/** Separate record/query boundaries; the relation lookup deliberately returns an unrelated category. */
final class Model
{
    public int $id = 0;
    public string $date = '';
    public array $state = [];
    public int $relationLookups = 0;

    /** @param ?object $category Category result. @param array $rows Event results. */
    public function __construct(private ?object $category, private array $rows = [])
    {
    }

    /** @param int $id Category ID. @return void */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /** @param string $date Calendar month. @return void */
    public function setDate(string $date): void
    {
        $this->date = $date;
    }

    /** @param string $key State key. @param mixed $value State value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** @return array Recorded state. */
    public function getState(): array
    {
        return $this->state;
    }

    /** @return ?object Resolved category. */
    public function getCategory(): ?object
    {
        return $this->category;
    }

    /** @return array Synthetic events. */
    public function getItems(): array
    {
        return $this->rows;
    }

    /** @return array Empty child categories for PDF dispatch. */
    public function getChildren(): array
    {
        return [];
    }

    /** @param int $id Event ID, not category ID. @return array Unrelated event-category result. */
    public function getCategories(int $id): array
    {
        $this->relationLookups++;
        return $this->rows === [] ? [] : [(object) ['catname' => 'Wrong event category']];
    }
}

/** View/model lookup boundary. */
class HtmlView
{
    public array $modelReads = [];

    /** @param Model $categoryModel Category record. @param Model $calendarModel Calendar events. */
    public function __construct(public Model $categoryModel, public Model $calendarModel)
    {
    }

    /** @param string $name Model name. @return Model Corresponding fixture. */
    public function getModel(string $name): Model
    {
        $this->modelReads[] = $name;
        return $name === 'Category' ? $this->categoryModel : $this->calendarModel;
    }
}

/** Configuration, time and serialization boundaries; filename cleanup uses production code. */
final class JemHelper
{
    public static bool $enabled = true;
    public static ?string $filename = null;
    public static array $ids = [];
    public static \Closure $cleanFilename;

    /** @return object Export cap. */
    public static function config(): object
    {
        return (object) ['ical_max_items' => 100];
    }

    /** @return Params Export configuration. */
    public static function globalattribs(): Params
    {
        return new Params(['global_show_ical_icon' => self::$enabled]);
    }

    /** @return string Stable site date. */
    public static function getJoomlaDate(): string
    {
        return '2026-11-01';
    }

    /** @param int $year Year. @param int $month Month. @return array Valid test month unchanged. */
    public static function normaliseCalendarMonth(int $year, int $month): array
    {
        return [$year, $month];
    }

    /** @return object Calendar sentinel. */
    public static function getCalendarTool(): object
    {
        return new \stdClass();
    }

    /** @param object $calendar Calendar sentinel. @param object $row Event. @return void */
    public static function icalAddEvent(object $calendar, object $row): void
    {
        self::$ids[] = $row->id;
    }

    /** @param object $calendar Calendar sentinel. @param string $filename Download filename. @return void */
    public static function sendCalendar(object $calendar, string $filename): void
    {
        self::$filename = (self::$cleanFilename)($filename);
    }
}
