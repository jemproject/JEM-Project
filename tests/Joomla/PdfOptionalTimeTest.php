<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual PDF HTML methods and production time formatter; no TCPDF or installed-site writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PdfOptionalTimeTest extends TestCase
{
    private string $timezone = '';

    /** @return void Load native Joomla language services and the isolated configuration boundary. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Madrid');
        define('JPATH_BASE', JEM_TEST_ROOT);
        define('JPATH_ROOT', JEM_TEST_ROOT);
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        Factory::$language = new Language('en-GB', false);
        require_once JEM_TEST_ROOT . '/tests/Support/pdf_optional_time_environment.php';
        require_once JEM_TEST_ROOT . '/site/classes/pdfview.class.php';
    }

    /** @return void Restore the process timezone after each isolated test. */
    protected function tearDown(): void
    {
        if ($this->timezone !== '') {
            date_default_timezone_set($this->timezone);
        }
    }

    /** @return iterable<string, array{array, string, string, string, string}> Optional times and expected text. */
    public static function times(): iterable
    {
        $cases = [
            'missing' => [[], '', ''],
            'null' => [['times' => null, 'endtimes' => null], '', ''],
            'empty' => [['times' => '', 'endtimes' => ''], '', ''],
            'invalid' => [['times' => 'not-a-time', 'endtimes' => 'not-a-time'], '', ''],
            'start-only' => [['times' => '10:30:00'], '10:30', ''],
            'end-only' => [['endtimes' => '11:45:00'], '', '11:45'],
            'both' => [['times' => '10:30:00', 'endtimes' => '11:45:00'], '10:30', '11:45'],
            'midnight' => [['times' => '00:00:00', 'endtimes' => '01:00:00'], '00:00', '01:00'],
        ];
        foreach (['H:i', 'g:i a', '<H:i &>'] as $format) {
            foreach (['compact', 'details'] as $layout) {
                foreach ($cases as $name => [$values, $start, $end]) {
                    if ($format === 'g:i a') {
                        $labels = ['' => '', '10:30' => '10:30 am', '11:45' => '11:45 am', '00:00' => '12:00 am', '01:00' => '1:00 am'];
                        $start = $labels[$start];
                        $end = $labels[$end];
                    } elseif ($format === '<H:i &>') {
                        $start = $start === '' ? '' : '<' . $start . ' &>';
                        $end = $end === '' ? '' : '<' . $end . ' &>';
                    }
                    yield $name . '/' . $format . '/' . $layout => [$values, $format, $layout, $start, $end];
                }
            }
        }
    }

    /**
     * Verify optional times in Timeline HTML and the calendar/Agenda PDF formatter.
     *
     * @param array $values Event time properties.
     * @param string $format Configured clock format.
     * @param string $layout Timeline card layout.
     * @param string $start Expected formatted start.
     * @param string $end Expected formatted end.
     * @return void
     */
    #[DataProvider('times')]
    public function testOptionalTimes(array $values, string $format, string $layout, string $start, string $end): void
    {
        PdfOptionalTimeSettings::$format = $format;
        $row = (object) ($values + ['title' => 'Example & event', 'dates' => '2026-11-02']);
        $errorReporting = error_reporting(E_ALL);
        set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            throw new ErrorException($message, 0, $level, $file, $line);
        });
        try {
            $html = (new ReflectionMethod(JemPdfView::class, 'buildDayTimelineRow'))->invoke(
                null,
                $row,
                false, 'left', false, 'right', 20, 20, 20, 20,
                false, 'text', false, false, false, 100,
                false, 'link', 'custom', '#eeeeee', $layout, false
            );
            $calendar = (new ReflectionMethod(JemPdfView::class, 'getCalendarEventTime'))->invoke(null, $row);
        } finally {
            restore_error_handler();
            error_reporting($errorReporting);
        }
        self::assertSame(1, preg_match('/class="jem-pdf-time">(.*?)<\/td>/s', $html, $cell));
        $expectedCell = htmlspecialchars($start, ENT_COMPAT, 'UTF-8')
            . ($end !== '' ? '<br /><small>' . htmlspecialchars($end, ENT_COMPAT, 'UTF-8') . '</small>' : '');
        self::assertSame($expectedCell, $cell[1]);
        self::assertSame(implode(' - ', array_values(array_filter([$start, $end], static fn (string $time): bool => $time !== ''))), $calendar);
        self::assertStringNotContainsString('<small></small>', $html);
        self::assertStringContainsString('Example &amp; event', $html);
        self::assertStringNotContainsString('hours', $cell[1]);
        self::assertStringNotContainsString('hours', $calendar);
    }

    /** @return void The shared formatter still returns null, rather than inventing optional times. */
    public function testSharedFormatterContractIsUnchanged(): void
    {
        self::assertNull(JemOutput::formattime(null, '', false));
        self::assertNull(JemOutput::formattime('', '', false));
        self::assertNull(JemOutput::formattime('not-a-time', '', false));
        self::assertSame('00:00', JemOutput::formattime('00:00:00', '', false));
        self::assertSame('10:30 hours', JemOutput::formattime('10:30:00'));
    }
}
