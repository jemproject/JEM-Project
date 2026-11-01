<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\EventResponse;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Execute raw dispatch and pre-render guards, not HTTP or PDF rendering. */
final class EventMissingResourceTest extends TestCase
{
    /** @return iterable<string, array{string, string}> Export paths and resource/configuration states. */
    public static function cases(): iterable
    {
        foreach (['pdf', 'pdf_print', 'ical'] as $path) {
            $states = ['missing_null', 'missing_false', 'missing_array', 'valid', 'denied'];
            $states = array_merge($states, $path === 'ical'
                ? ['disabled_global', 'disabled_event'] : ['pdf_unavailable']);
            foreach ($states as $state) {
                yield $path . ':' . $state => [$path, $state];
            }
        }
    }

    /**
     * Load production dispatch and PDF guards with isolated external services.
     *
     * @return string Fixture class; rendering after the guards is replaced by a sentinel.
     */
    private static function viewClass(): string
    {
        $class = __NAMESPACE__ . '\\RawEventFixture';
        if (!class_exists($class, false)) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/event/view.raw.php');
            self::assertSame(1, preg_match('/public function display\(\$tpl = null\).*?^    \}/ms', $source, $display));
            self::assertSame(1, preg_match('/private function renderPdf\(\): void.*?(?=        \$row->categories)/s', $source, $pdf));
            // Resolve the explicit global class-name string to our namespaced PDF availability spy.
            $pdfCode = str_replace("class_exists('JemPdf', false)", 'class_exists(JemPdf::class, false)', $pdf[0]);
            eval('namespace ' . __NAMESPACE__ . '; use \\Exception; class RawEventFixture extends ViewBoundary {'
                . $display[0] . $pdfCode . '$this->pdfReached = true; }}');
        }

        return $class;
    }

    /**
     * Missing exports must signal 404 without changing access or disabled-export behavior.
     *
     * @param string $path Export path, including print dispatch.
     * @param string $state Resource and option state.
     * @return void
     */
    #[DataProvider('cases')]
    public function testMissingResourceAndExportDecisions(string $path, string $state): void
    {
        $class = self::viewClass();
        $app = new Application($path);
        Factory::$app = $app;
        JemHelper::$settings = new Params(['global_show_ical_icon' => $state !== 'disabled_global']);
        JemHelper::$calendars = [];
        JemPdf::$available = $state !== 'pdf_unavailable';
        $row = match ($state) {
            'missing_null' => null,
            'missing_false' => false,
            'missing_array' => [],
            default => (object) [
                'did' => 7,
                'alias' => 'example',
                'params' => new Params([
                    'access-view' => $state !== 'denied',
                    'event_show_ical_icon' => $state !== 'disabled_event',
                ]),
            ],
        };
        $view = new $class($row);
        $error = null;
        try {
            $view->display();
        } catch (Throwable $caught) {
            $error = $caught;
        }

        if (str_starts_with($state, 'missing')) {
            self::assertNotNull($error, 'A missing event must not produce a successful empty export.');
            self::assertSame(404, $error->getCode());
            self::assertSame('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND', $error->getMessage());
            self::assertFalse($app->closed);
            self::assertSame([], $app->access);
            self::assertSame(['Item'], $view->reads);
            self::assertFalse($view->pdfReached);
            self::assertSame([], JemHelper::$calendars);
            return;
        }

        self::assertNull($error);
        self::assertSame($state === 'pdf_unavailable', $app->closed);
        self::assertSame($state === 'valid' && $path !== 'ical', $view->pdfReached);
        self::assertSame($state === 'valid' && $path === 'ical' ? ['event7.ics'] : [], JemHelper::$calendars);
        self::assertSame(match ($state) {
            'disabled_global', 'pdf_unavailable' => [],
            'denied' => [false],
            default => [true],
        }, $app->access);
        self::assertSame(match ($state) {
            'disabled_global', 'pdf_unavailable' => [],
            'denied' => ['Item'],
            default => $path === 'ical' ? ['Item', 'Categories'] : ['Item'],
        }, $view->reads);
    }
}

/** Minimal parameter bag for export and access options. */
final class Params
{
    /** @param array $values Parameter values. */
    public function __construct(private array $values = [])
    {
    }

    /** @param string $key Parameter. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

/** Capture application effects; input includes the real PDF print flag. */
final class Application
{
    public bool $closed = false;
    public array $access = [];
    public object $input;

    /** @param string $path Requested export path. */
    public function __construct(string $path)
    {
        $this->input = new class ($path) {
            /** @param string $path Requested export path. */
            public function __construct(private string $path)
            {
            }

            /** @param string $key Input name. @param string $default Fallback. @return string Value. */
            public function getCmd(string $key, string $default = ''): string
            {
                return $key === 'layout' ? ($this->path === 'ical' ? '' : 'pdf') : $default;
            }

            /** @param string $key Input name. @param bool $default Fallback. @return bool Value. */
            public function getBool(string $key, bool $default = false): bool
            {
                return $key === 'print' ? $this->path === 'pdf_print' : $default;
            }
        };
    }

    /** @return void Record an empty-response exit without terminating PHPUnit. */
    public function close(): void
    {
        $this->closed = true;
    }
}

/** Application boundary. */
final class Factory
{
    public static Application $app;

    /** @return Application Isolated application. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

/** Translation boundary preserving stable language keys. */
final class Text
{
    /** @param string $key Language key. @return string Untranslated key. */
    public static function _(string $key): string
    {
        return $key;
    }
}

/** PDF availability boundary; document rendering is not tested here. */
final class JemPdf
{
    public static bool $available = true;

    /** @return bool Whether the renderer is available. */
    public static function isAvailable(): bool
    {
        return self::$available;
    }
}

/** Verify access-helper delegation, not its internal authorization policy. */
final class JemFrontendAccess
{
    /** @param bool $allowed Access decision. @param Application $app Spy. @return bool Decision. */
    public static function enforceViewAccess(bool $allowed, Application $app): bool
    {
        $app->access[] = $allowed;
        return $allowed;
    }
}

/** Calendar service boundary; no file is downloaded. */
final class JemHelper
{
    public static Params $settings;
    public static array $calendars = [];

    /** @return Params Global export configuration. */
    public static function globalattribs(): Params
    {
        return self::$settings;
    }

    /** @return object Empty calendar payload. */
    public static function getCalendarTool(): object
    {
        return (object) [];
    }

    /** @param object $calendar Calendar payload. @param object $row Event. @return void */
    public static function icalAddEvent(object $calendar, object $row): void
    {
        $calendar->event = $row->did;
    }

    /** @param object $calendar Calendar payload. @param string $name Filename. @return void */
    public static function sendCalendar(object $calendar, string $name): void
    {
        self::$calendars[] = $name;
    }
}

/** Model boundary recording reads before rendering. */
class ViewBoundary
{
    public bool $pdfReached = false;
    public array $reads = [];

    /** @param mixed $row Resolved model item. */
    public function __construct(private mixed $row)
    {
    }

    /** @param string $key Model property. @return mixed Item or empty related records. */
    public function get(string $key): mixed
    {
        $this->reads[] = $key;
        return $key === 'Item' ? $this->row : [];
    }
}
