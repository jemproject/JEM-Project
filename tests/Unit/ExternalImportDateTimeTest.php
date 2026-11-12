<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual external-import normalisation and previews; category lookup/logging are fixture boundaries. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ExternalImportDateTimeTest extends TestCase
{
    private object $controller;
    private array $options;

    /** @return void Load checkout controller and local Joomla libraries without database writes. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to installed Joomla libraries.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        require_once JEM_TEST_ROOT . '/site/classes/config.class.php';
        $config = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(JemConfig::class, '_data'))->setValue($config, new Joomla\Registry\Registry(['csv_separator' => ';', 'csv_delimiter' => '"']));
        (new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $config);
        $this->controller = new class extends JemControllerImport {
            public function __construct() {}
            protected function addImportLogEntry($key, $message, $priority = Joomla\CMS\Log\Log::INFO, $normaliseHeader = true) {}
            protected function resolveExternalCategoryIds($value, $fallback, array &$notes, &$valid) { return (string) $fallback; }
        };
        $this->options = ['mode' => 'standard', 'published' => 0, 'publish_up' => '', 'locid' => 0,
            'type_id' => 0, 'language' => '*', 'catid' => 2, 'category_label' => 'Tests',
            'type_label' => '', 'venue_label' => '', 'language_label' => 'All'];
        // The machine timezone must not determine the result of parsing an explicit numeric offset.
        date_default_timezone_set('Pacific/Honolulu');
    }

    /** @param string $method Protected method. @param mixed ...$args Arguments. @return mixed Production method result. */
    private function invoke(string $method, ...$args)
    {
        return (new ReflectionMethod($this->controller, $method))->invoke($this->controller, ...$args);
    }

    /** @return iterable Accepted formats and values that must not roll over to another date. */
    public static function dates(): iterable
    {
        foreach (['05-12-26' => '2026-12-05', '5/12/26' => '2026-12-05', '05.12.26' => '2026-12-05',
            '3-12-2026' => '2026-12-03', '03.01.2027' => '2027-01-03', '2026-12-03' => '2026-12-03',
            '1970-01-01' => '1970-01-01', '29/02/2024' => '2024-02-29', '01-01-69' => '2069-01-01',
            '01-01-70' => '1970-01-01', '31-02-26' => null, '2026-02-31' => null,
            '29/02/2026' => null, '05/12-26' => null, 'tomorrow' => null, '' => null, 'NULL' => null,
            '0000-00-00' => null] as $input => $expected) {
            yield $input => [$input, $expected];
        }
    }

    /** @param string $input Source date. @param string|null $expected ISO date or invalid/empty. @return void */
    #[DataProvider('dates')]
    public function testDates(string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->invoke('normaliseExternalCsvDate', $input));
    }

    /** @return iterable Clocks, midnight, AM/PM and non-clock strings. */
    public static function times(): iterable
    {
        foreach (['00:00' => '00:00:00', '14.00' => '14:00:00', '9:05:06' => '09:05:06',
            '11:59 PM' => '23:59:00', '12 AM' => '00:00:00', '12 PM' => '12:00:00',
            '20u' => null, '24:00' => null, '12:60' => null, '12:00:60' => null,
            'today' => null, 'tomorrow' => null, '23 PM' => null, '12.30:20' => null,
            '' => null, 'NULL' => null] as $input => $expected) {
            yield $input => [$input, $expected];
        }
    }

    /** @param string $input Source time. @param string|null $expected Clock or invalid/empty. @return void */
    #[DataProvider('times')]
    public function testTimes(string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->invoke('normaliseExternalCsvTime', $input));
    }

    /** @return iterable Combined field validation and numeric timezone conversion. */
    public static function combined(): iterable
    {
        yield 'day first' => ['05-12-26 14:00', false, '2026-12-05', '14:00:00', true];
        yield 'ISO offset' => ['2026-12-03T00:30:00+02:00', false, '2026-12-02', '22:30:00', true];
        yield 'UTC epoch' => ['1970-01-01T00:00:00Z', false, '1970-01-01', '00:00:00', true];
        yield 'date only' => ['05.12.2026', false, '2026-12-05', null, true];
        yield 'AM PM' => ['05/12/26 2:00 PM', false, '2026-12-05', '14:00:00', true];
        yield 'invalid calendar date' => ['2026-02-31 10:00', false, null, null, false];
        yield 'invalid offset' => ['2026-12-03T10:00+25:00', false, null, null, false];
        yield 'invalid combined clock' => ['05-12-26 20u', false, null, null, false];
        yield 'open day ignores clock' => ['05-12-26 20u', true, '2026-12-05', null, true];
        yield 'open day validates date' => ['31-02-26 20u', true, null, null, false];
        yield '24 hours rejected' => ['2026-12-03 24:00', false, null, null, false];
    }

    /** @param string $input Combined field. @param bool $openDay Ignore clock intentionally. @param string|null $date Date. @param string|null $time Time. @param bool $valid Validity. @return void */
    #[DataProvider('combined')]
    public function testCombined(string $input, bool $openDay, ?string $date, ?string $time, bool $valid): void
    {
        self::assertSame(['date' => $date, 'time' => $time, 'valid' => $valid], $this->invoke('normaliseExternalCsvDateTime', $input, $openDay));
    }

    /** @return iterable Invalid supplied values must not be hidden by fallback fields. */
    public static function invalidRows(): iterable
    {
        yield 'start clock' => [['times' => '20u']];
        yield 'end clock' => [['times' => '10:00', 'endtimes' => '22u']];
        yield 'invalid end date' => [['enddates' => 'not-a-date', 'times' => '10:00', 'endtimes' => '11:00']];
        yield 'invalid start ISO' => [['dates' => '2026-02-31']];
        yield 'end without start' => [['endtimes' => '11:00']];
        yield 'invalid combined with fallback' => [['times' => '10:00', 'start_datetime' => 'invalid']];
        yield 'invalid separate with combined' => [['times' => '20u', 'start_datetime' => '2026-12-03 10:00']];
        yield '24:00' => [['times' => '24:00']];
        yield 'end before start' => [['times' => '20:00', 'endtimes' => '10:00']];
    }

    /** @param array $values Invalid source fields. @return void No invalid row is advertised as valid. */
    #[DataProvider('invalidRows')]
    public function testInvalidRows(array $values): void
    {
        $result = $this->invoke('normaliseExternalCsvRow', $values + ['title' => 'Test', 'dates' => '2026-12-03'], $this->options, 2);
        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['preview']['notes']);
    }

    /** @return void Blank/NULL remain absent, midnight remains a clock, and valid combined fields keep precedence. */
    public function testEmptyFieldsAndPrecedence(): void
    {
        foreach (['', 'NULL', '0000-00-00'] as $date) {
            $row = $this->invoke('normaliseExternalCsvRow', ['title' => 'Undated', 'dates' => $date, 'times' => 'NULL'], $this->options, 2);
            self::assertTrue($row['valid']);
        }
        $row = $this->invoke('normaliseExternalCsvRow', ['title' => 'Test', 'dates' => '2026-12-03', 'times' => '12:00', 'start_datetime' => '05-12-26 00:00'], $this->options, 2);
        self::assertTrue($row['valid']);
        self::assertSame('2026-12-05', $row['preview']['import_data']['dates']);
        self::assertSame('00:00:00', $row['preview']['import_data']['times']);
    }

    /** @return void Exercise BOM, quoted multiline CSV, Windows-1252, and intentional Open day behaviour. */
    public function testReportedFilePreview(): void
    {
        $csv = "title;dates;times;endtimes;introtext\r\nKlaverjassen;3-12-2026;20:00;23:00;Elke eerste donderdag\r\nSinterklaas;05-12-26;14.00;16.00;\"Met pepernoten; en een verrassing\r\nvoor alle kinderen\"\r\nCafé-avond;12/12/2026;20u;22u;Café & Co\r\nNieuwjaar;03.01.2027;16:00;18:00;Proosten\r\n";
        foreach (["\xEF\xBB\xBF" . $csv, iconv('UTF-8', 'Windows-1252', $csv)] as $content) {
            $file = tempnam(sys_get_temp_dir(), 'csv2517');
            file_put_contents($file, $content);
            try {
                foreach (['standard' => [3, 1], 'openday' => [4, 0]] as $mode => [$valid, $errors]) {
                    $preview = $this->invoke('buildExternalCsvPreview', ['tmp_name' => $file], array_replace($this->options, ['mode' => $mode]));
                    self::assertSame($valid, $preview['valid_count']);
                    self::assertSame($errors, $preview['error_count']);
                    self::assertCount($valid, $preview['records']);
                    self::assertSame('2026-12-05', $preview['rows'][1]['import_data']['dates']);
                    self::assertSame('Café-avond', $preview['rows'][2]['title']);
                }
            } finally {
                unlink($file);
            }
        }
    }

    /** @return void Structured external formats share the corrected event normalisation. */
    public function testStructuredPreview(): void
    {
        $preview = $this->invoke('buildExternalStructuredPreviewFromRecords', [
            ['title' => 'Valid', 'dates' => '05-12-26', 'times' => '14.00'],
            ['title' => 'Invalid', 'dates' => '05-12-26', 'times' => '20u'],
        ], $this->options);
        self::assertSame(1, $preview['valid_count']);
        self::assertSame(1, $preview['error_count']);
        self::assertCount(1, $preview['records']);
    }
}
