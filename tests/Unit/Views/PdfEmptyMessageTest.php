<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\PdfEmptyMessage;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Capture production HTML before TCPDF output; no HTTP, database or visual PDF rendering is simulated. */
final class PdfEmptyMessageTest extends TestCase
{
    /** @return void Load actual helper and registration view with private service boundaries. */
    public static function setUpBeforeClass(): void
    {
        foreach (['site/classes/pdfview.class.php', 'site/views/attendeeregistrations/view.raw.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            $source = preg_replace('/^use [^;]+;\R/m', '', substr($source, 5));
            $source = str_replace('__DIR__', var_export(dirname(JEM_TEST_ROOT . '/' . $file), true), $source);
            // Only redirect the explicit class-name availability check to our namespaced service.
            $source = str_replace("class_exists('JemPdf', false)", 'class_exists(JemPdf::class, false)', $source);
            eval('namespace ' . __NAMESPACE__ . '; ' . $source);
        }
    }

    /** @return void Reset translation overrides and the authenticated registration fixture. */
    protected function setUp(): void
    {
        Text::$overrides = [];
        Factory::$app = new Application();
        HtmlView::$rows = [];
    }

    /** @return iterable<string, array{string, string}> Entity-specific messages and event controls. */
    public static function emptyCases(): iterable
    {
        foreach ([
            'venues' => 'COM_JEM_NO_VENUES',
            'typevenues' => 'COM_JEM_NO_VENUES',
            'venuesmap' => 'COM_JEM_NO_VENUES',
            'specialdays' => 'JGLOBAL_NO_MATCHING_RESULTS',
            'attendeeregistrations' => 'COM_JEM_ATTENDEE_REGISTRATIONS_EMPTY',
            'day' => 'COM_JEM_NO_EVENTS',
            'eventsmap' => 'COM_JEM_NO_EVENTS',
            'categories' => 'COM_JEM_NO_CATEGORY',
            'generic' => 'COM_JEM_NO_EVENTS',
        ] as $view => $key) {
            yield $view => [$view, $key];
        }
    }

    /**
     * Verify empty output without changing event defaults, type headings or registration filters.
     *
     * @param string $view Output entry point.
     * @param string $key Expected translation.
     * @return void
     */
    #[DataProvider('emptyCases')]
    public function testEntityMessage(string $view, string $key): void
    {
        $html = self::html($view);
        self::assertStringContainsString(Text::_($key), $html);
        if ($key !== 'COM_JEM_NO_EVENTS') {
            self::assertStringNotContainsString(Text::_('COM_JEM_NO_EVENTS'), $html);
        }
        if ($view === 'typevenues') {
            self::assertStringContainsString('Example type', $html);
        }
        if ($view === 'attendeeregistrations') {
            self::assertStringContainsString('jem-pdf-filter-summary', $html);
            self::assertStringContainsString('A&amp;B', $html);
        }
    }

    /** @return iterable<string, array{string, string}> Generic-table paths supporting literal translated text. */
    public static function overrideCases(): iterable
    {
        foreach (self::emptyCases() as $view => $case) {
            if (!in_array($view, ['venuesmap', 'categories'], true)) {
                yield $view => $case;
            }
        }
    }

    /**
     * Translation overrides are text, not trusted markup.
     *
     * @param string $view Output entry point.
     * @param string $key Translation key to override.
     * @return void
     */
    #[DataProvider('overrideCases')]
    public function testTranslationIsEscaped(string $view, string $key): void
    {
        Text::$overrides[$key] = 'No <b>items</b> & "rooms"';
        $html = self::html($view);
        self::assertStringContainsString('No &lt;b&gt;items&lt;/b&gt; &amp; &quot;rooms&quot;', $html);
        self::assertStringNotContainsString('No <b>items</b>', $html);
    }

    /** @return iterable<string, array{string}> Populated non-event tables and backward-compatible generic table. */
    public static function populatedCases(): iterable
    {
        foreach (['venues', 'typevenues', 'specialdays', 'attendeeregistrations', 'generic'] as $view) {
            yield $view => [$view];
        }
    }

    /**
     * Populated output must retain rows and must not show any empty-result message.
     *
     * @param string $view Output entry point.
     * @return void
     */
    #[DataProvider('populatedCases')]
    public function testPopulatedTables(string $view): void
    {
        $html = self::html($view, true);
        self::assertStringContainsString('Example A &amp; B', $html);
        foreach (['COM_JEM_NO_EVENTS', 'COM_JEM_NO_VENUES', 'JGLOBAL_NO_MATCHING_RESULTS',
            'COM_JEM_ATTENDEE_REGISTRATIONS_EMPTY'] as $key) {
            self::assertStringNotContainsString(Text::_($key), $html);
        }
    }

    /** @return void Existing venue views must continue to share the corrected wrapper. */
    public function testVenueCallSitesUseSharedRenderer(): void
    {
        foreach (['myvenues', 'venues', 'venueslist', 'venuesmap'] as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.raw.php');
            self::assertStringContainsString('JemPdfView::renderVenueList(', $source);
        }
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/typevenues/view.raw.php');
        self::assertStringContainsString('JemPdfView::renderTypeVenueList(', $source);
    }

    /**
     * Invoke production entry points with representative empty/populated rows.
     *
     * @param string $view Renderer selection.
     * @param bool $populated Whether to supply one record.
     * @return string HTML submitted to the PDF library.
     */
    private static function html(string $view, bool $populated = false): string
    {
        $venue = (object) ['id' => 7, 'slug' => '7:example', 'venue' => 'Example A & B', 'city' => 'Madrid'];
        $type = (object) ['name' => 'Example type'];
        $rows = $populated ? [$venue] : [];
        try {
            switch ($view) {
                case 'venues':
                    JemPdfView::renderVenueList('Venues', $rows, 'venues.pdf');
                    break;
                case 'typevenues':
                    JemPdfView::renderTypeVenueList('Type', $type, $rows, 'types.pdf');
                    break;
                case 'venuesmap':
                    JemPdfView::renderVenueList('Map', [], 'map.pdf', 'map');
                    break;
                case 'specialdays':
                    $days = $populated ? [(object) ['title' => 'Example A & B', 'start_date' => '2026-11-01', 'published' => 1]] : [];
                    JemPdfView::renderSpecialDays('Special days', $days, 'days.pdf');
                    break;
                case 'attendeeregistrations':
                    HtmlView::$rows = $populated ? [(object) ['name' => 'Example A & B']] : [];
                    (new JemViewAttendeeregistrations())->display();
                    break;
                case 'day':
                    JemPdfView::renderEventList('Day', [], 'day.pdf', 'calendar');
                    break;
                case 'eventsmap':
                    JemPdfView::renderEventsMapList('Events map', [], 'events.pdf');
                    break;
                case 'categories':
                    JemPdfView::renderCategoryList('Categories', [], [], 'categories.pdf');
                    break;
                default:
                    JemPdfView::renderTable('Generic', ['Name'], $populated ? [['Example A & B']] : [], 'table.pdf');
            }
        } catch (CapturedHtml $captured) {
            return $captured->getMessage();
        }
        self::fail('Expected the PDF library boundary to capture generated HTML.');
    }
}

/** Stop at writeHTML, before output buffering or download headers are changed. */
final class CapturedHtml extends RuntimeException
{
}

/** PDF-library spy; rendering internals are intentionally excluded. */
final class PdfDocument
{
    /** @param string $method Library setup method. @param array $arguments Parameters. @return void */
    public function __call(string $method, array $arguments): void
    {
    }

    /** @param string $html Generated HTML. @param mixed ...$args PDF-library flags. @return never */
    public function writeHTML(string $html, mixed ...$args): never
    {
        throw new CapturedHtml($html);
    }
}

/** Available PDF service with deterministic sizing. */
final class JemPdf
{
    /** @return bool Renderer available. */
    public static function isAvailable(): bool
    {
        return true;
    }

    /** @param string $paper Paper size. @return bool No single-page fitting in this fixture. */
    public static function prefersSinglePage(string $paper): bool
    {
        return false;
    }

    /** @param string $paper Paper size. @return float Scale. */
    public static function getPosterScale(string $paper): float
    {
        return 1.0;
    }

    /** @param array $margins Margins. @param string $paper Paper size. @return array Unchanged margins. */
    public static function fitSinglePageMargins(array $margins, string $paper): array
    {
        return $margins;
    }

    /** @param mixed ...$args Document arguments. @return PdfDocument Capture boundary. */
    public static function createDocument(mixed ...$args): PdfDocument
    {
        return new PdfDocument();
    }
}

/** Global configuration boundary; optional intro/footer and calendar badges are excluded. */
final class JemHelper
{
    /** @return object PDF settings. */
    public static function config(): object
    {
        return (object) ['pdf_include_view_text' => 0];
    }

    /** @param string $day Day argument. @return bool No date badges for the empty Day control. */
    public static function isValidDate(string $day): bool
    {
        return false;
    }
}

/** Stable translations including overridable text. */
final class Text
{
    public static array $overrides = [];

    /** @param string $key Language key. @return string Translation. */
    public static function _(string $key): string
    {
        return self::$overrides[$key] ?? match ($key) {
            'COM_JEM_NO_EVENTS' => 'Currently, no events are available.',
            'COM_JEM_NO_VENUES' => 'No Venues',
            'COM_JEM_NO_CATEGORY' => 'No category',
            'COM_JEM_ATTENDEE_REGISTRATIONS_EMPTY' => 'No attendee registrations found.',
            'JGLOBAL_NO_MATCHING_RESULTS' => 'No Matching Results',
            default => $key,
        };
    }
}

/** String length boundary used for table widths. */
final class StringHelper
{
    /** @param string $value Text. @return int Character count. */
    public static function strlen(string $value): int
    {
        return mb_strlen($value);
    }
}

/** Routing boundary; no SEF behavior is simulated. */
final class Route
{
    /** @param string $url URL. @param mixed ...$args Routing flags. @return string Unchanged URL. */
    public static function _(string $url, mixed ...$args): string
    {
        return $url;
    }
}

/** Absolute URL base for links in populated tables. */
final class Uri
{
    /** @return string Test site base. */
    public static function root(): string
    {
        return 'https://example.test/';
    }
}

/** Venue routing boundary. */
final class JemHelperRoute
{
    /** @param string $id Venue slug. @return string Relative venue URL. */
    public static function getVenueRoute(string $id): string
    {
        return 'index.php?view=venue&id=' . $id;
    }
}

/** Input and parameter bag for the actual registration display method. */
final class Bag
{
    /** @param array $values Values. */
    public function __construct(private array $values)
    {
    }

    /** @param string $key Key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $name Typed getter. @param array $args Getter arguments. @return mixed Value. */
    public function __call(string $name, array $args): mixed
    {
        return $this->get($args[0], $args[1] ?? null);
    }
}

/** Authenticated application fixture. */
final class Application
{
    public Bag $input;

    /** Initialise PDF input and a filter summary with escapable text. */
    public function __construct()
    {
        $this->input = new Bag(['layout' => 'pdf', 'filter_search' => 'A&B']);
    }

    /** @return Bag Only the name column is needed for the populated registration control. */
    public function getParams(): Bag
    {
        return new Bag(['registration_columns' => ['name']]);
    }
}

/** Application lookup boundary. */
final class Factory
{
    public static Application $app;

    /** @return Application Current application. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

/** Authorized user boundary; this suite does not test permission policy. */
final class JemFactory
{
    /** @return self User spy. */
    public static function getUser(): self
    {
        return new self();
    }

    /** @param string $key User property. @return int Logged-in user ID. */
    public function get(string $key): int
    {
        return 7;
    }

    /** @param string $permission Permission. @param string $component Component. @return bool Allowed. */
    public function authorise(string $permission, string $component): bool
    {
        return true;
    }
}

/** Registration view/model boundary with configurable records. */
class HtmlView
{
    public static array $rows = [];

    /** @return self Model spy. */
    public function getModel(): self
    {
        return $this;
    }

    /** @param string $key State key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
    }

    /** @return array Fixture records. */
    public function getData(): array
    {
        return self::$rows;
    }
}
