<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Native Joomla translations with actual Agenda/PDF HTML builders; no browser or PDF engine. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CalendarAgendaLocalizationTest extends TestCase
{
    private string $scratch = '';
    private string $timezone = '';
    private const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    private const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /** @return void Bootstrap libraries with disposable language files and cache. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        $this->timezone = date_default_timezone_get();
        $this->scratch = sys_get_temp_dir() . '/jem-agenda-language-' . bin2hex(random_bytes(8));
        mkdir($this->scratch, 0777, true);
        define('JPATH_BASE', $this->scratch);
        define('JPATH_ROOT', $this->scratch);
        define('JPATH_CACHE', $this->scratch . '/cache');
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/calendar_agenda_localization_environment.php';
        require_once JEM_TEST_ROOT . '/site/helpers/calendaragenda.php';
        require_once JEM_TEST_ROOT . '/site/classes/pdfview.class.php';
        Factory::$application = new class {
            public Input $input;

            /** @return void Supply an empty request to the actual PDF layout selector. */
            public function __construct()
            {
                $this->input = new Input([]);
            }
        };
    }

    /** @return void Restore timezone and remove only this test's generated fixture directory. */
    protected function tearDown(): void
    {
        if ($this->timezone !== '') {
            date_default_timezone_set($this->timezone);
        }
        if ($this->scratch === '' || !is_dir($this->scratch)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->scratch);
    }

    /** @return iterable<string, array{string, string}> Locale and process timezone combinations. */
    public static function locales(): iterable
    {
        foreach (['en-GB', 'nl-NL', 'es-ES', 'xx-XX'] as $locale) {
            foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $zone) {
                yield $locale . '/' . $zone => [$locale, $zone];
            }
        }
    }

    /**
     * Load small translation fixtures through Joomla, including an HTML-like override case.
     *
     * @param string $locale Locale.
     * @param string $zone Process timezone.
     * @return array{array<string>, array<string>} Expected weekdays and months.
     */
    private function language(string $locale, string $zone): array
    {
        date_default_timezone_set($zone);
        $days = match ($locale) {
            'nl-NL' => ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'],
            'es-ES' => ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'],
            'xx-XX' => array_map(static fn ($day) => '<b>&' . $day . '</b>', self::DAYS),
            default => self::DAYS,
        };
        $months = match ($locale) {
            'nl-NL' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni',
                'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
            'es-ES' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            'xx-XX' => array_map(static fn ($month) => '<b>&' . $month . '</b>', self::MONTHS),
            default => self::MONTHS,
        };
        $ini = '';
        foreach ([array_combine(self::DAYS, $days), array_combine(self::MONTHS, $months)] as $values) {
            foreach ($values as $key => $value) {
                $ini .= strtoupper($key) . '="' . $value . '"' . PHP_EOL;
            }
        }
        mkdir($this->scratch . '/language/' . $locale, 0777, true);
        file_put_contents($this->scratch . '/language/' . $locale . '/joomla.ini', $ini);
        $language = new Language($locale, false);
        self::assertTrue($language->load('joomla', $this->scratch, $locale, true, false));
        Factory::$language = $language;
        return [$days, $months];
    }

    /** @return array<object> Seven consecutive civil days and an invalid row to exclude. */
    private function rows(): array
    {
        $rows = [];
        for ($day = 1; $day <= 7; ++$day) {
            $rows[] = (object) [
                'id' => $day, 'dates' => '2026-11-0' . $day,
                'title' => 'Event ' . $day, 'times' => '12:00', 'endtimes' => '13:00',
            ];
        }
        $rows[] = (object) ['dates' => 'invalid', 'title' => 'Invalid'];
        return $rows;
    }

    /** @param string $html Rendered markup. @return DOMXPath Parsed HTML. */
    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($document);
    }

    /** @param DOMXPath $xpath HTML. @param string $class CSS class. @param array $expected Texts. @return void */
    private function assertLabels(DOMXPath $xpath, string $class, array $expected): void
    {
        $labels = $xpath->query('//*[@class="' . $class . '"]');
        self::assertSame($expected, array_map(static fn ($node) => $node->textContent, iterator_to_array($labels)));
        foreach ($labels as $label) {
            self::assertSame(0, $label->childElementCount, 'Translation must remain plain text.');
        }
    }

    /** @param string $locale Locale. @param string $zone Process timezone. @return void */
    #[DataProvider('locales')]
    public function testAgendaWeekdaysAndDateBoundaries(string $locale, string $zone): void
    {
        [$days] = $this->language($locale, $zone);
        $rows = $this->rows();
        $html = JemCalendarAgendaHelper::renderAgenda($rows, '2026-11-01', '2026-11-07');
        $xpath = $this->document($html);
        $this->assertLabels($xpath, 'jem-calendar-agenda-weekday', $days);
        $this->assertLabels($xpath, 'jem-calendar-agenda-day-number', ['1', '2', '3', '4', '5', '6', '7']);
        self::assertCount(7, $xpath->query('//a[@class="jem-calendar-agenda-title"]'));
        $filtered = $this->document(JemCalendarAgendaHelper::renderAgenda($rows, '2026-11-02', '2026-11-03'));
        $this->assertLabels($filtered, 'jem-calendar-agenda-weekday', array_slice($days, 1, 2));
        self::assertStringContainsString('jem-calendar-agenda-empty', JemCalendarAgendaHelper::renderAgenda([]));

        $pdf = (new ReflectionMethod(JemPdfView::class, 'buildMonthlyCalendarHtml'))->invoke(
            null, 'Calendar', $rows, 2026, 11, new Registry(['calendar_display_mode' => 'agenda']), 'A4'
        );
        $pdfXpath = $this->document($pdf);
        $this->assertLabels($pdfXpath, 'jem-pdf-agenda-weekday', $days);
        $this->assertLabels($pdfXpath, 'jem-pdf-agenda-date', ['1', '2', '3', '4', '5', '6', '7']);
        self::assertCount(7, $pdfXpath->query('//td[@class="jem-pdf-agenda-time"]'));
    }

    /** @param string $locale Locale. @param string $zone Process timezone. @return void */
    #[DataProvider('locales')]
    public function testMonthlySubtitlesInTemplatesAndPdf(string $locale, string $zone): void
    {
        [, $months] = $this->language($locale, $zone);
        $rows = [];
        foreach ($months as $index => $month) {
            $agendaStartDate = sprintf('2028-%02d-01', $index + 1);
            $agendaEndDate = date('Y-m-t', strtotime($agendaStartDate));
            foreach (['default.php', 'responsive/default.php'] as $layout) {
                $source = file_get_contents(JEM_TEST_ROOT . '/site/views/calendar/tmpl/' . $layout);
                self::assertSame(1, preg_match('/echo (JemCalendarAgendaHelper::renderAgenda\\([^;]+);/', $source, $match));
                // Execute the production call including its subtitle expression, not the full Joomla template.
                $expression = str_replace('$this->rows', '$rows', $match[1]);
                $html = eval('use Joomla\\CMS\\Language\\Text; return ' . $expression . ';');
                $this->assertLabels($this->document($html), 'jem-calendar-agenda-subtitle', [$month . ' 2028']);
            }
            foreach (['agenda' => 'jem-pdf-agenda-subtitle', 'calendar' => 'jem-pdf-calendar-title'] as $mode => $class) {
                $pdf = (new ReflectionMethod(JemPdfView::class, 'buildMonthlyCalendarHtml'))->invoke(
                    null, 'Calendar', [], 2028, $index + 1, new Registry(['calendar_display_mode' => $mode]), 'A4'
                );
                $xpath = $this->document($pdf);
                if ($mode === 'calendar') {
                    $titles = $xpath->query('//table[contains(@class, "jem-pdf-calendar")]/tr/td[@colspan="7"]');
                    self::assertCount(1, $titles);
                    self::assertSame($month . ' 2028', $titles->item(0)->textContent);
                    self::assertSame(0, $titles->item(0)->childElementCount);
                } else {
                    $this->assertLabels($xpath, $class, [$month . ' 2028']);
                }
            }
        }
    }
}
