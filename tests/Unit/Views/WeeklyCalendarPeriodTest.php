<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use DateTimeImmutable;
use DateTimeZone;
use JemProject\Tests\AnnualCalendar\Clock;
use JemProject\Tests\WeeklyCalendar\ActiveCalendarWeek;
use JemProject\Tests\WeeklyCalendar\Application;
use JemProject\Tests\WeeklyCalendar\Factory;
use JemProject\Tests\WeeklyCalendar\HtmlPreparation;
use JemProject\Tests\WeeklyCalendar\JemHelper;
use JemProject\Tests\WeeklyCalendar\JemPdfView;
use JemProject\Tests\WeeklyCalendar\RawView;
use JemProject\Tests\WeeklyCalendar\WeekModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute production ISO policy, SQL date boundaries, calendar renderer and export dispatch. */
final class WeeklyCalendarPeriodTest extends TestCase
{
    private string $serverZone;
    private const NS = 'JemProject\\Tests\\WeeklyCalendar';

    /** @param string $source Source. @param string $name Method. @return string Production method. */
    private static function method(string $source, string $name): string
    {
        self::assertSame(1, preg_match('/(?:static public|public static|private static|protected|public) function '
            . $name . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms', $source, $match), $name);
        return $match[0];
    }

    /** @return void Isolate Joomla services, but execute actual model/view/calendar methods. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/annual_calendar_environment.php';
        require_once JEM_TEST_ROOT . '/tests/Support/weekly_calendar_environment.php';
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $methods = self::method($helper, 'getJoomlaTimeZoneName') . self::method($helper, 'getJoomlaDate');
        $methods = str_replace('new \\DateTimeImmutable(', 'new \\JemProject\\Tests\\AnnualCalendar\\FrozenDateTime(', $methods);
        eval('namespace ' . self::NS . '; class JemHelper extends \\JemProject\\Tests\\AnnualCalendar\\HelperBoundary {'
            . $methods . ' public static function calendarSpecialDays($start, $end): array { return []; } }');

        $model = file_get_contents(JEM_TEST_ROOT . '/site/models/weekcal.php');
        self::assertSame(1, preg_match('/private \?array \$calendarPeriod = null;/', $model, $property));
        eval('namespace ' . self::NS . '; use \\DateTimeImmutable; class WeekModel extends \\JemProject\\Tests\\AnnualCalendar\\ModelBoundary {'
            . 'protected $_currentweek = null;' . $property[0] . self::method($model, 'getCalendarPeriod')
            . self::method($model, 'populateState') . self::method($model, 'getCurrentweek') . '}');

        foreach (['calendar.class.php', 'activecalendarweek.php'] as $file) {
            $code = file_get_contents(JEM_TEST_ROOT . '/site/classes/' . $file);
            $code = preg_replace('/^<\?php|\?>\s*$/', '', $code);
            $code = preg_replace('/^require_once.*?;\s*$/m', '', $code);
            $code = str_replace(['use Joomla\\CMS\\Language\\Text;', 'use Joomla\\CMS\\Router\\Route;'],
                ['', 'use \\JemProject\\Tests\\AnnualCalendar\\Route;'], $code);
            $code = str_replace('class JemCalendar', '#[\\AllowDynamicProperties] class JemCalendar', $code);
            eval('namespace ' . self::NS . ';' . $code);
        }

        $html = file_get_contents(JEM_TEST_ROOT . '/site/views/weekcal/view.html.php');
        self::assertStringContainsString('public array $calendarPeriod = [];', $html);
        self::assertStringContainsString("public string \$weekTitle = '';", $html);
        self::assertSame(1, preg_match('/\$model = \$this->getModel\(\);.*?(?=        if \(JemCalendarAgendaHelper)/s', $html, $period));
        self::assertSame(1, preg_match('/\$itemid  = .*?(?=        \$this->rows)/s', $html, $navigation));
        $navigation[0] = str_replace('JemCalendarAgendaHelper::getWeekMode($params)', '$params->get("mode", "calendar")', $navigation[0]);
        eval('namespace ' . self::NS . '; use \\JemProject\\Tests\\AnnualCalendar\\Route; class HtmlPreparation extends ViewBoundary {'
            . 'public function prepare(): void { $params = Factory::$app->params; $jinput = Factory::$app->input; $this->param_topcat = "";'
            . $period[0] . $navigation[0] . '$this->cal = $cal; $this->weekTitle = $weekTitle; $this->period = $period;'
            . '$this->print_link = $print_link; $this->ical_link = $partDate; }}');

        $raw = file_get_contents(JEM_TEST_ROOT . '/site/views/weekcal/view.raw.php');
        eval('namespace ' . self::NS . '; class RawView extends ViewBoundary {' . self::method($raw, 'display') . '}');
        $pdf = file_get_contents(JEM_TEST_ROOT . '/site/classes/pdfview.class.php');
        eval('namespace ' . self::NS . '; use \\DateTimeImmutable; class JemPdfView extends PdfBoundary {'
            . str_replace('private static', 'public static', self::method($pdf, 'buildWeeklyCalendarHtml')) . '}');
    }

    /** @return void Reset services and remember the actual process timezone. */
    protected function setUp(): void
    {
        $this->serverZone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        Clock::$now = '2027-01-03 23:30:00';
        Clock::$zone = 'UTC';
        Clock::$reads = 0;
        JemHelper::$filename = null;
        JemPdfView::$captured = [];
    }

    /** @return void Leave the PHP process timezone unchanged. */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverZone);
    }

    /** @param array $input Request. @param array $params Menu settings. @return WeekModel Model. */
    private function model(array $input = [], array $params = []): WeekModel
    {
        Factory::$app = new Application($input, $params);
        return new WeekModel();
    }

    /** @return iterable<string, array{int, int, int, int}> ISO boundaries, overflow and display variants. */
    public static function periods(): iterable
    {
        foreach ([[2030, 1], [2019, 1], [2025, 53], [2026, 54], [2026, 0], [2026, -1],
            [2026, 12], [2026, 53], [2029, 52], [2029, 53], [2000, 1]] as [$year, $week]) {
            foreach ([0, 1] as $first) {
                foreach ([0, 1, 2] as $weeks) {
                    yield "$year:$week:$first:$weeks" => [$year, $week, $first, $weeks];
                }
            }
        }
    }

    /** @param int $year ISO year. @param int $week ISO week. @param int $first First weekday. @param int $weeks Count. @return void */
    #[DataProvider('periods')]
    public function testModelTitleGridAndExportsAgree(int $year, int $week, int $first, int $weeks): void
    {
        $input = ['yearID' => $year, 'weekID' => $week, 'task' => 'archive', 'Itemid' => 101];
        $model = $this->model($input, ['nrweeks' => $weeks, 'firstweekday' => $first]);
        $period = $model->getCalendarPeriod();
        $iso = (new DateTimeImmutable('2027-01-03'))->setISODate($year, $week, 1);
        $start = $first === 0 ? $iso->modify('-1 day') : $iso;
        $weeks = max(1, $weeks);
        $end = $start->modify('+' . ($weeks * 7 - 1) . ' days');
        self::assertSame($iso->format('o-W'), sprintf('%04d-%02d', $period['year'], $period['week']));
        self::assertSame($start->format('Y-m-d'), $period['start']->format('Y-m-d'));
        self::assertSame($end->format('Y-m-d'), $period['end']->format('Y-m-d'));
        self::assertSame($start->format('Y-m-d'), $model->getState('filter.date.from'));
        self::assertSame($end->format('Y-m-d'), $model->getState('filter.date.to'));
        self::assertStringContainsString("'" . $start->format('Y-m-d') . "'", $model->getState('filter.calendar_from'));
        self::assertStringContainsString("'" . $end->format('Y-m-d') . "'", $model->getState('filter.calendar_to'));
        self::assertSame(2, $model->getState('filter.published'));
        self::assertSame(1, $model->menuFilterCalls);
        self::assertSame('00:00:00', $period['start']->format('H:i:s'));

        $view = new HtmlPreparation($model);
        $view->prepare();
        $timestamp = $view->cal->getFirstDayTimeOfWeek($period['week']);
        self::assertSame($start->format('Y-m-d') . ' 00:00:01', date('Y-m-d H:i:s', $timestamp));
        self::assertStringContainsString((string) $period['year'], $view->weekTitle);
        if ($weeks === 1) {
            self::assertSame('Week ' . $period['week'] . ', ' . $period['year'], $view->weekTitle);
        }
        foreach ([$view->print_link, $view->ical_link] as $link) {
            self::assertStringContainsString('&yearID=' . $period['year'], $link);
            self::assertStringContainsString('&weekID=' . $period['week'], $link);
        }
        $view->cal->enableNewEventLinks('<span data-test-date="{date}"></span>');
        $markup = $view->cal->showWeeksByID($period['week'], $period['weeks']);
        preg_match_all('/data-test-date="([\d-]+)"/', $markup, $days);
        $actual = array_map(static fn ($day) => str_replace('-', '', $day), $days[1]);
        $expected = [];
        for ($day = 0; $day < $weeks * 7; ++$day) {
            $expected[] = $start->modify("+$day days")->format('Ymd');
        }
        self::assertSame($expected, $actual, 'Every day must agree, not only the first cell.');

        $raw = new RawView($model);
        $raw->display();
        self::assertSame('events_week_' . $iso->format('oW') . '.ics', JemHelper::$filename);
        self::assertFalse($model->getState('filter.calendar_multiday'));
        Factory::$app->input->set('layout', 'pdf');
        $raw->display();
        self::assertSame($period, JemPdfView::$captured[6]);
        self::assertSame('jem-week-' . $iso->format('oW') . '.pdf', JemPdfView::$captured[2]);

        foreach (['calendar', 'agenda'] as $mode) {
            Factory::$app->params->set('pdf_layout', $mode);
            $pdf = json_decode(JemPdfView::buildWeeklyCalendarHtml('Week', [], $year, $week, Factory::$app->params, 'A4', $period), true);
            self::assertSame($start->format('Y-m-d'), $pdf['start']);
            self::assertSame($end->format('Y-m-d'), $pdf['end']);
            self::assertSame($view->weekTitle, $pdf['subtitle']);
            self::assertSame('00:00:00', $pdf['time']);
            if ($mode === 'calendar') {
                self::assertSame($weeks * 7, $pdf['cells']);
            }
        }
        self::assertSame($year, Factory::$app->input->getInt('yearID'));
        self::assertSame($week, Factory::$app->input->getInt('weekID'));
        self::assertSame(1, Clock::$reads);
    }

    /** @return iterable<string, array{string, string, string}> Server/site and ISO rollover instants. */
    public static function timezones(): iterable
    {
        foreach (['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati'] as $server) {
            foreach (['UTC', 'Europe/Madrid', 'America/Adak', 'invalid/timezone'] as $site) {
                foreach (['2026-12-31 23:30:00', '2027-01-03 23:30:00', '2027-01-04 00:30:00', '2029-12-31 00:30:00'] as $now) {
                    yield "$server:$site:$now" => [$server, $site, $now];
                }
            }
        }
    }

    /** @param string $server PHP zone. @param string $site Joomla zone. @param string $now UTC instant. @return void */
    #[DataProvider('timezones')]
    public function testDefaultsUseOneJoomlaIsoClock(string $server, string $site, string $now): void
    {
        date_default_timezone_set($server);
        Clock::$zone = $site;
        Clock::$now = $now;
        $expected = (new DateTimeImmutable($now, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($site === 'invalid/timezone' ? 'UTC' : $site));
        $model = $this->model();
        $period = $model->getCalendarPeriod();
        self::assertSame($expected->format('Y-m-d'), $period['today']);
        self::assertSame((int) $expected->format('o'), $period['year']);
        self::assertSame((int) $expected->format('W'), $period['week']);
        $view = new HtmlPreparation($model);
        $view->prepare();
        self::assertSame('Week ' . $period['week'] . ', ' . $period['year'], $view->weekTitle);
        self::assertSame($period['start']->format('Y-m-d'), $model->getState('filter.date.from'));
        self::assertSame($period['start']->format('Y-m-d'), date('Y-m-d', $view->cal->getFirstDayTimeOfWeek($period['week'])));
        self::assertSame(1, Clock::$reads);
        self::assertSame($server, date_default_timezone_get());
        self::assertSame((int) $expected->format('W'), $model->getCurrentweek());
        self::assertSame((int) $expected->format('W'), $model->getCurrentweek());
        self::assertSame(2, Clock::$reads, 'Compatibility getter must not query MySQL.');
        self::assertSame(1, $model->getState('filter.published'));
    }

    /** @return void Out-of-range values cannot overflow ISO date arithmetic. */
    public function testMalformedRangesAndStableSnapshot(): void
    {
        foreach ([['yearID' => PHP_INT_MAX], ['yearID' => 0], ['yearID' => -1], ['yearID' => 10000],
            ['weekID' => PHP_INT_MAX], ['weekID' => PHP_INT_MIN], ['yearID' => 9999, 'weekID' => 53]] as $input) {
            $model = $this->model($input);
            $period = $model->getCalendarPeriod();
            self::assertSame(2026, $period['year']);
            self::assertSame(53, $period['week']);
            self::assertSame('2026-12-28', $period['start']->format('Y-m-d'));
        }
        $model = $this->model([], ['nrweeks' => PHP_INT_MAX]);
        self::assertSame(1, $model->getCalendarPeriod()['weeks']);
        $before = $model->getCalendarPeriod();
        Clock::$now = '2027-01-04 00:30:00';
        self::assertSame($before, $model->getCalendarPeriod());
        self::assertSame('2026-12-28', $model->getState('filter.date.from'));
        self::assertSame(1, (new WeekModel())->getCalendarPeriod()['week']);
    }

    /** @return void Both templates consume the model range in all three display modes. */
    public function testTemplateRangeContractsAndLegacyPdfSignature(): void
    {
        foreach (['default.php', 'responsive/default.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/weekcal/tmpl/' . $file);
            self::assertSame(3, substr_count($source, "\$this->calendarPeriod['start']->format('Y-m-d')"));
            self::assertSame(3, substr_count($source, "\$this->calendarPeriod['end']->format('Y-m-d')"));
            self::assertStringContainsString('$agendaSubtitle = $this->weekTitle;', $source);
            self::assertStringNotContainsString('getFirstDayTimeOfWeek(', $source);
        }
        $model = $this->model(['yearID' => 2030, 'weekID' => 1], ['firstweekday' => 0, 'nrweeks' => 2]);
        $period = $model->getCalendarPeriod();
        $pdf = json_decode(JemPdfView::buildWeeklyCalendarHtml('Week', [], 2030, 1, Factory::$app->params, 'A4'), true);
        self::assertSame($period['start']->format('Y-m-d'), $pdf['start']);
        self::assertSame($period['end']->format('Y-m-d'), $pdf['end']);
        self::assertSame(14, $pdf['cells']);
        self::assertSame('Weeks 1-2, 2030', $pdf['subtitle']);
    }
}

