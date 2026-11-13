<?php

declare(strict_types=1);

namespace JemProject\Tests\WeekPdf;

use DateTimeImmutable;

/** Load production classes into an isolated namespace for focused export tests. */
final class Environment
{
    /** @param string $root Branch root. @return void */
    public static function load(string $root): void
    {
        $files = ['site/classes/eventschedule.class.php', 'site/classes/dayschedule.class.php',
            'site/helpers/calendarfilter.php', 'site/helpers/calendarhourgrid.php',
            'site/helpers/calendaragenda.php', 'site/classes/pdfweektimetable.class.php',
            'site/classes/pdfview.class.php', 'site/views/weekcal/view.raw.php'];
        foreach ($files as $file) {
            if (!is_file($root . '/' . $file)) {
                continue;
            }
            $source = substr(file_get_contents($root . '/' . $file), 5);
            $source = preg_replace('/^use [^;]+;\R/m', '', $source);
            $source = preg_replace('/^[ \t]*require_once [^;]+;\R/m', '', $source);
            // External output-buffer cleanup must not remove PHPUnit's own capture buffers.
            $source = preg_replace('/while \(ob_get_level\(\) > 0\) \{\s*ob_end_clean\(\);\s*\}/', '', $source);
            $source = str_replace("class_exists('JemPdf', false)", 'class_exists(JemPdf::class, false)', $source);
            $source = str_replace('Joomla\CMS\Language\Text::', 'Text::', $source);
            eval('namespace ' . __NAMESPACE__ . '; use \DateTimeImmutable; use \DateTimeZone; use \InvalidArgumentException; ' . $source);
        }
        Factory::$app = new Application();
    }

    /** @param int $week ISO week. @param int $first First weekday (0 Sunday/1 Monday). @param int $weeks Length. @return array Period. */
    public static function period(int $week = 44, int $first = 1, int $weeks = 1): array
    {
        $iso = (new DateTimeImmutable('2026-01-01'))->setISODate(2026, $week);
        $start = $first === 0 ? $iso->modify('-1 day') : $iso;
        return ['year' => 2026, 'week' => $week, 'first_weekday' => $first, 'iso_start' => $iso,
            'start' => $start, 'end' => $start->modify('+' . ($weeks * 7 - 1) . ' days'), 'weeks' => $weeks];
    }

    /** @return array Representative visible records including overlapping and overnight sessions. */
    public static function rows(): array
    {
        $rows = [];
        foreach ([
            [1, 'Opening & welcome', '09:00:00', '10:00:00', '2026-10-26'],
            [2, 'Parallel session', '09:30:00', '11:00:00', '2026-10-26'],
            [3, 'Unknown finish', '14:00:00', null, '2026-10-26'],
            [4, 'All day', null, null, '2026-10-26'],
            [5, 'Night session', '23:30:00', '01:30:00', '2026-10-27'],
            [6, 'One minute', '11:00:00', '11:01:00', '2026-10-26'],
        ] as [$id, $title, $times, $endtimes, $enddates]) {
            $rows[] = (object) compact('id', 'title', 'times', 'endtimes', 'enddates');
        }
        foreach ($rows as $row) {
            $row->dates = '2026-10-26';
            $row->slug = $row->id . ':example';
            $row->venue = 'Hall A';
            $row->categories = [];
        }
        return $rows;
    }
}

/** Parameter and typed-input boundary. */
final class Bag
{
    /** @param array $values Values. */
    public function __construct(public array $values = [])
    {
    }
    /** @param string $key Key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
    /** @param string $method Getter. @param array $args Arguments. @return mixed Value. */
    public function __call(string $method, array $args): mixed
    {
        return $this->get($args[0], $args[1] ?? null);
    }
}

/** Application boundary; native output is captured rather than sent. */
final class Application
{
    public Bag $input;
    public Bag $params;
    public bool $closed = false;
    /** Initialise request. */
    public function __construct()
    {
        $this->input = new Bag(['layout' => 'pdf', 'mode' => 'timetable']);
        $this->params = new Bag();
    }
    /** @return Bag Settings. */
    public function getParams(): Bag { return $this->params; }
    /** @return self Menu manager. */
    public function getMenu(): self { return $this; }
    /** @return null No active menu in this fixture. */
    public function getActive(): ?object { return null; }
    /** @return void Exit marker. */
    public function close(): void { $this->closed = true; }
}
final class Factory
{
    public static Application $app;
    /** @return Application Request. */
    public static function getApplication(): Application { return self::$app; }
}
final class JemHelper
{
    /** @return object Settings. */
    public static function config(): object { return (object) ['pdf_include_view_text' => 0]; }
    /** @return Bag Export options. */
    public static function globalattribs(): Bag { return new Bag(); }
    /** @param string $date Date. @return bool Valid calendar date. */
    public static function isValidCalendarDate(string $date): bool { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date); }
    /** @param string $date Date. @return bool Valid date. */
    public static function isValidDate(string $date): bool { return self::isValidCalendarDate($date); }
    /** @return string Site timezone. */
    public static function getJoomlaTimeZoneName(): string { return 'Europe/Madrid'; }
    /** @param object $event Event. @return string Event timezone. */
    public static function getEventTimeZoneName(object $event): string { return $event->timezone ?? 'Europe/Madrid'; }
    /** @param string $start Start. @param string $end End. @return array Special days. */
    public static function calendarSpecialDays(string $start, string $end): array { return []; }
    /** @param string $date Date. @param array $days Special days. @return string Badge HTML. */
    public static function renderCalendarSpecialDayBadges(string $date, array $days): string { return ''; }
}
final class JemOutput
{
    /** @param object $event Row. @return string Neutral indicator in this unrelated rendering fixture. */
    public static function eventStatusLabel($event): string { return ''; }

    /** @param array $items Rows. @param object $params Options. @param string $view View. @param string $layout Layout. @return void No decoration in this fixture. */
    public static function prepareViewEventStatuses(array $items, $params, string $view, string $layout = 'default'): void {}

    /** @param string $date Date. @param string $format Format. @return string Formatted date. */
    public static function formatdate(string $date, string $format = 'Y-m-d'): string { return (new DateTimeImmutable($date))->format($format); }
}
final class Text
{
    /** @param string $key Key. @return string Translation. */
    public static function _(string $key): string
    {
        return match ($key) {
            'COM_JEM_CALENDAR_MODE_TIMETABLE' => 'Timetable',
            'COM_JEM_TIMETABLE_ALL_DAY' => 'All day',
            'COM_JEM_NO_EVENTS' => 'No events',
            default => $key,
        };
    }
    /** @param string $key Key. @param mixed ...$args Values. @return string Caption. */
    public static function sprintf(string $key, mixed ...$args): string { return $key . ' ' . implode(' ', $args); }
}
final class Route
{
    /** @param string $url URL. @param bool $xhtml Flag. @return string URL. */
    public static function _(string $url, bool $xhtml = true): string { return $url; }
}
final class Uri
{
    /** @return string Site base. */
    public static function root(): string { return 'https://example.test/'; }
}
final class JemHelperRoute
{
    /** @param string $slug Event slug. @return string Relative URL. */
    public static function getEventRoute(string $slug): string { return 'events/' . $slug; }
}
final class JemPdf
{
    public static ?Document $document = null;
    /** @return bool Available. */
    public static function isAvailable(): bool { return true; }
    /** @param string $paper Paper. @return bool Single-page policy. */
    public static function prefersSinglePage(string $paper): bool { return false; }
    /** @param string $paper Paper. @return float Scale. */
    public static function getPosterScale(string $paper): float { return 1.0; }
    /** @param array $margins Margins. @param string $paper Paper. @return array Margins. */
    public static function fitSinglePageMargins(array $margins, string $paper): array { return $margins; }
    /** @param mixed ...$args Settings. @return Document Spy. */
    public static function createDocument(mixed ...$args): Document { return self::$document = new Document(); }
}
/** Native-drawing spy; PDF generation itself is exercised by a separate TCPDF smoke test. */
final class Document
{
    public int $pages = 0;
    public array $text = [];
    public array $rectangles = [];
    public array $html = [];
    /** @param string $method Setup operation. @param array $args Arguments. @return void */
    public function __call(string $method, array $args): void {}
    /** @return array Page margins. */
    public function getMargins(): array { return ['left' => 14, 'right' => 14, 'top' => 14, 'bottom' => 14]; }
    /** @return float Page width. */
    public function getPageWidth(): float { return 297; }
    /** @return float Page height. */
    public function getPageHeight(): float { return 210; }
    /** @return void New page. */
    public function AddPage(): void { $this->pages++; }
    /** @param mixed ...$args Rectangle arguments. @return void */
    public function Rect(mixed ...$args): void { $this->rectangles[] = [$this->pages, $args]; }
    /** @param mixed ...$args Text arguments. @return void */
    public function MultiCell(mixed ...$args): void { $this->text[] = [$this->pages, $args]; }
    /** @param string $html HTML. @param mixed ...$args Flags. @return void */
    public function writeHTML(string $html, mixed ...$args): void { $this->html[] = $html; }
    /** @param mixed ...$args Output parameters. @return never Stop before application exit. */
    public function Output(mixed ...$args): never { throw new OutputCaptured(); }
}
final class OutputCaptured extends \RuntimeException {}
/** Raw view/model spy records whether multi-day splitting was disabled before reading events. */
class HtmlView
{
    public static array $rows = [];
    public static array $reads = [];
    public array $state = [];
    /** @return self Model. */
    public function getModel(): self { return $this; }
    /** @return array Period. */
    public function getCalendarPeriod(): array { return Environment::period(); }
    /** @param string $key State. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void { $this->state[$key] = $value; }
    /** @param string $key Property. @return array Records. */
    public function get(string $key): array { self::$reads[] = $this->state; return self::$rows; }
}
