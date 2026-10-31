<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use JemProject\Tests\AnnualCalendar\Application;
use JemProject\Tests\AnnualCalendar\Clock;
use JemProject\Tests\AnnualCalendar\Factory;
use JemProject\Tests\AnnualCalendar\HtmlPreparation;
use JemProject\Tests\AnnualCalendar\JemHelper;
use JemProject\Tests\AnnualCalendar\AnnualModel;
use JemProject\Tests\AnnualCalendar\RawView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute production date policy, model initialization, view preparation and raw dispatch. */
final class AnnualCalendarPeriodTest extends TestCase
{
    private string $serverZone;
    private static string $navigation;
    private const NS = 'JemProject\\Tests\\AnnualCalendar';

    /** @param string $source Source. @param string $name Method. @return string Actual method body and signature. */
    private static function method(string $source, string $name): string
    {
        self::assertSame(1, preg_match('/(?:static public|public static|protected|public) function '
            . $name . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms', $source, $match), $name);
        return $match[0];
    }

    /** @return void Load isolated boundaries and actual production methods. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/annual_calendar_environment.php';
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $methods = self::method($helper, 'getJoomlaTimeZoneName') . self::method($helper, 'getJoomlaDate');
        // Freeze only "now", keeping the actual configured-zone validation and local-date conversion.
        $methods = str_replace('new \\DateTimeImmutable(', 'new FrozenDateTime(', $methods);
        eval('namespace ' . self::NS . '; class JemHelper extends HelperBoundary {' . $methods . '}');

        $model = file_get_contents(JEM_TEST_ROOT . '/site/models/annualcalendar.php');
        self::assertSame(1, preg_match('/private \?array \$calendarPeriod = null;/', $model, $property));
        eval('namespace ' . self::NS . '; use \\DateTimeImmutable; class AnnualModel extends ModelBoundary {'
            . $property[0] . self::method($model, 'getCalendarPeriod') . self::method($model, 'populateState') . '}');

        $html = file_get_contents(JEM_TEST_ROOT . '/site/views/annualcalendar/view.html.php');
        self::assertSame(1, preg_match('/\$model = \$this->getModel\(\);.*?(?=\$db =)/s', $html, $period));
        self::assertSame(1, preg_match('/\$itemid = .*?(?=        if \(\$task == \'archive\'\))/s', $html, $links));
        self::assertSame(1, preg_match('/\$periodLabel = .*?;/s', $html, $label));
        self::assertSame(1, preg_match('/\$this->year\s*=.*?(?=\s*parent::display)/s', $html, $assignments));
        eval('namespace ' . self::NS . '; class HtmlPreparation extends ViewBoundary {'
            . 'public function prepare(string $displayMode, string $task): void {'
            . '$jinput = Factory::$app->input;' . $period[0] . $links[0] . $label[0] . $assignments[0]
            . '$this->print_link = $print_link; $this->pdf_link = $pdf_link; $this->ical_link = $ical_link; }}');

        $raw = file_get_contents(JEM_TEST_ROOT . '/site/views/annualcalendar/view.raw.php');
        eval('namespace ' . self::NS . '; class RawView extends ViewBoundary {' . self::method($raw, 'display') . '}');

        $template = file_get_contents(JEM_TEST_ROOT . '/site/views/annualcalendar/tmpl/default.php');
        self::assertSame(1, preg_match('/<div class="jem-annual-nav">.*?(?=\s*<\?php if \(in_array\(\$displayLegend)/s', $template, $nav));
        self::$navigation = $nav[0];
        self::assertStringContainsString('$today = $this->today;', $template);
    }

    /** @return void Isolate clock and restore PHP timezone after each test. */
    protected function setUp(): void
    {
        $this->serverZone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        Clock::$now = '2026-12-31 23:30:00';
        Clock::$zone = 'UTC';
        Clock::$reads = 0;
        JemHelper::$filename = null;
    }

    /** @return void Restore original process timezone. */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverZone);
    }

    /** @param int|null $year Requested year. @param int $month Start month. @param array $extra Other input. @return AnnualModel Model. */
    private function model(?int $year, int $month, array $extra = []): AnnualModel
    {
        Factory::$app = new Application(($year === null ? [] : ['yearID' => $year]) + $extra, $month);
        return new AnnualModel();
    }

    /** @return iterable<string, array{int|null, int}> Invalid, historical, leap and boundary years with clamped months. */
    public static function periods(): iterable
    {
        foreach ([null, -10, -1, 0, 1, 1970, 2024, 2026, 2100, 9998, 9999, 10000, PHP_INT_MAX] as $year) {
            foreach ([0, 1, 9, 13] as $month) {
                yield ($year ?? 'default') . ':' . $month => [$year, $month];
            }
        }
    }

    /** @param int|null $year Year. @param int $month Month. @return void */
    #[DataProvider('periods')]
    public function testPeriodAndModelFiltersAgree(?int $year, int $month): void
    {
        $model = $this->model($year, $month, ['task' => 'archive']);
        $period = $model->getCalendarPeriod();
        $month = max(1, min(12, $month));
        $lastYear = $month === 1 ? 9999 : 9998;
        $expectedYear = $year !== null && $year >= 1 && $year <= $lastYear ? $year : 2026;
        self::assertSame($expectedYear, $period['year']);
        self::assertSame($month, $period['start_month']);
        self::assertSame(sprintf('%04d-%02d-01', $expectedYear, $month), $period['start']->format('Y-m-d'));
        self::assertSame(10, strlen($period['end']->format('Y-m-d')));
        self::assertSame($month === 1 ? $expectedYear : $expectedYear + 1, (int) $period['end']->format('Y'));
        self::assertContains((int) $period['start']->diff($period['end'])->days + 1, [365, 366]);
        self::assertSame($expectedYear > 1 ? $expectedYear - 1 : null, $period['previous_year']);
        self::assertSame($expectedYear < $lastYear ? $expectedYear + 1 : null, $period['next_year']);
        self::assertSame($period['start']->format('Y-m-d'), $model->getState('filter.date.from'));
        self::assertSame($period['end']->format('Y-m-d'), $model->getState('filter.date.to'));
        self::assertStringContainsString("'" . $period['start']->format('Y-m-d') . "'", $model->getState('filter.calendar_from'));
        self::assertStringContainsString("'" . $period['end']->format('Y-m-d') . "'", $model->getState('filter.calendar_to'));
        self::assertSame(2, $model->getState('filter.published'));
        self::assertSame(1, $model->menuFilterCalls);
        self::assertSame(1, Clock::$reads);
        self::assertSame('UTC', date_default_timezone_get());
        self::assertSame($year, Factory::$app->input->get('yearID'), 'Do not rewrite the request.');
    }

    /** @return iterable<string, array{string, string, string}> Server/site zones and UTC midnight instants. */
    public static function timezones(): iterable
    {
        foreach (['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati'] as $server) {
            foreach (['UTC', 'Europe/Madrid', 'America/Adak', 'Pacific/Kiritimati', 'invalid/timezone'] as $site) {
                foreach (['2026-12-31 23:30:00', '2027-01-01 00:30:00'] as $now) {
                    yield "$server:$site:$now" => [$server, $site, $now];
                }
            }
        }
    }

    /** @param string $server Server zone. @param string $site Joomla zone. @param string $now UTC instant. @return void */
    #[DataProvider('timezones')]
    public function testDefaultYearAndTodayFollowSiteTimezone(string $server, string $site, string $now): void
    {
        date_default_timezone_set($server);
        Clock::$zone = $site;
        Clock::$now = $now;
        $expected = (new DateTimeImmutable($now, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($site === 'invalid/timezone' ? 'UTC' : $site))->format('Y-m-d');
        $model = $this->model(null, 1);
        $view = new HtmlPreparation($model);
        $view->prepare('calendar', '');
        self::assertSame($expected, $view->today);
        self::assertSame((int) substr($expected, 0, 4), $view->year);
        self::assertSame($view->periodStart->format('Y-m-d'), $model->getState('filter.date.from'));
        self::assertSame(1, Clock::$reads);
        self::assertSame($server, date_default_timezone_get());
    }

    /** @return iterable<string, array{int|null, int, string, string}> Views, exports, archive and period boundaries. */
    public static function workflows(): iterable
    {
        foreach ([null, -1, 2024, 1, 9999, 10000] as $year) {
            foreach ([1, 9] as $month) {
                foreach (['', 'archive'] as $task) {
                    foreach (['calendar', 'agenda', 'print', 'pdf', 'ical'] as $mode) {
                        yield ($year ?? 'default') . ":$month:$task:$mode" => [$year, $month, $task, $mode];
                    }
                }
            }
        }
    }

    /** @param int|null $year Year. @param int $month Month. @param string $task Context. @param string $mode Output. @return void */
    #[DataProvider('workflows')]
    public function testViewAndExportUseTheModelPeriod(?int $year, int $month, string $task, string $mode): void
    {
        $model = $this->model($year, $month, [
            'task' => $task, 'layout' => $mode === 'pdf' ? 'pdf' : '', 'Itemid' => 101, 'print' => $mode === 'print',
        ]);
        $period = $model->getCalendarPeriod();
        if ($mode === 'pdf' || $mode === 'ical') {
            $view = new RawView($model);
            $view->display();
            if ($mode === 'pdf') {
                self::assertSame([$period['start'], $period['end']], $view->pdfPeriod);
            } else {
                self::assertSame('jem_events_year_' . $period['start']->format('Ymd') . '_'
                    . $period['end']->format('Ymd') . '.ics', JemHelper::$filename);
                self::assertFalse($model->getState('filter.calendar_multiday'));
                self::assertSame(100, $model->getState('list.limit'));
            }
        } else {
            $view = new HtmlPreparation($model);
            $view->prepare($mode === 'agenda' ? 'agenda' : 'calendar', $task);
            self::assertSame($period['start'], $view->periodStart);
            self::assertSame($period['end'], $view->periodEnd);
            self::assertSame($period['today'], $view->today);
            self::assertStringContainsString('&yearID=' . $period['year'], $view->print_link);
            self::assertStringContainsString('&yearID=' . $period['year'], $view->pdf_link);
            self::assertSame('&yearID=' . $period['year'], $view->ical_link);
            foreach (['previous', 'next'] as $direction) {
                $neighbor = $period[$direction . '_year'];
                $link = $view->{$direction . '_link'};
                if ($neighbor === null) {
                    self::assertNull($link);
                } else {
                    parse_str(parse_url($link, PHP_URL_QUERY), $query);
                    self::assertSame((string) $neighbor, $query['yearID']);
                    self::assertSame('101', $query['Itemid']);
                    self::assertSame($task, $query['task'] ?? '');
                    self::assertSame($mode === 'agenda' ? 'agenda' : 'calendar', $query['mode'] ?? 'calendar');
                }
            }
            $render = eval('namespace ' . self::NS . '; return function () { ?>' . self::$navigation . '<?php };');
            ob_start();
            try {
                $render->call($view);
                $markup = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            $dom = new DOMDocument();
            $dom->loadHTML($markup, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);
            foreach (['previous' => 'prev', 'next' => 'next'] as $key => $class) {
                $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " jem-annual-nav-' . $class . ' ")]');
                self::assertCount(1, $nodes);
                $node = $nodes->item(0);
                self::assertSame($period[$key . '_year'] === null ? 'span' : 'a', $node->nodeName);
                if ($period[$key . '_year'] === null) {
                    self::assertSame('true', $node->getAttribute('aria-disabled'));
                    self::assertFalse($node->hasAttribute('href'));
                }
            }
        }
        self::assertSame($period['start']->format('Y-m-d'), $model->getState('filter.date.from'));
        self::assertSame($period['end']->format('Y-m-d'), $model->getState('filter.date.to'));
        self::assertSame(1, Clock::$reads);
    }

    /** @return void A request crossing midnight keeps one consistent date snapshot. */
    public function testPeriodSnapshotSurvivesMidnight(): void
    {
        $model = $this->model(null, 9);
        $before = $model->getCalendarPeriod();
        Clock::$now = '2027-01-01 00:30:00';
        $model->getState();
        $after = $model->getCalendarPeriod();
        self::assertSame($before, $after);
        self::assertSame('2026-12-31', $after['today']);
        self::assertSame('2026-09-01', $model->getState('filter.date.from'));
        self::assertSame('2027-08-31', $model->getState('filter.date.to'));
        self::assertSame(1, Clock::$reads);
        self::assertSame('2027-01-01', (new AnnualModel())->getCalendarPeriod()['today']);
    }
}
