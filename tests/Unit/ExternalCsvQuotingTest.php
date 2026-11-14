<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise checkout CSV previews with Joomla libraries, without database writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ExternalCsvQuotingTest extends TestCase
{
    private object $controller;
    private array $options;

    /** Load actual parsers; isolate category lookup and import logging. @return void */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to installed Joomla libraries.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        require_once JEM_TEST_ROOT . '/site/classes/config.class.php';
        Joomla\CMS\Factory::$language->load('com_jem', JEM_TEST_ROOT . '/admin', 'en-GB', true);
        $this->configure(';');
        $this->controller = new class extends JemControllerImport {
            public function __construct() {}
            protected function addImportLogEntry($key, $message, $priority = Joomla\CMS\Log\Log::INFO, $normaliseHeader = true) {}
            protected function resolveExternalCategoryIds($value, $fallback, array &$notes, &$valid) { return (string) $fallback; }
        };
        $this->options = ['mode' => 'standard', 'published' => 0, 'publish_up' => '', 'locid' => 0,
            'type_id' => 0, 'language' => '*', 'catid' => 2, 'category_label' => 'Tests',
            'type_label' => '', 'venue_label' => '', 'language_label' => 'All',
            'title' => 'Special Days', 'day_type' => 'Holiday', 'day_type_id' => 1, 'replace' => 0];
    }

    /** Set configured separator. @param string $separator CSV separator. @return void */
    private function configure(string $separator): void
    {
        $config = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(JemConfig::class, '_data'))->setValue($config, new Joomla\Registry\Registry(['csv_separator' => $separator, 'csv_delimiter' => '"']));
        (new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $config);
    }

    /** Define real preview inputs. @param string $kind Entity. @return array Method, headers and values. */
    private function definition(string $kind): array
    {
        return match ($kind) {
            'events' => ['buildExternalCsvPreview', ['title', 'dates', 'description'], ['Workshop', '2026-12-05', '']],
            'venues' => ['buildExternalCsvVenuePreview', ['venue', 'city', 'locdescription'], ['Workshop', 'Madrid', '']],
            'specialdays' => ['buildSpecialDaysCsvPreview', ['title', 'start_date', 'description'], ['Workshop', '2026-12-05', '']],
        };
    }

    /** Preview an actual CSV file, cleaning it afterwards. @param string $kind Entity. @param string $csv File contents. @return array Preview. */
    private function preview(string $kind, string $csv): array
    {
        $file = tempnam(sys_get_temp_dir(), 'jem2541');
        file_put_contents($file, $csv);
        try {
            return (new ReflectionMethod($this->controller, $this->definition($kind)[0]))->invoke($this->controller, ['tmp_name' => $file], $this->options);
        } finally {
            unlink($file);
        }
    }

    /** Cover configured separators, automatic comma detection and BOM. @return iterable */
    public static function validFiles(): iterable
    {
        foreach (['events', 'venues', 'specialdays'] as $kind) {
            foreach ([';', ',', "\t"] as $separator) {
                foreach ([false, true] as $bom) {
                    yield "$kind " . bin2hex($separator) . ' bom=' . (int) $bom => [$kind, $separator, $bom];
                }
            }
        }
    }

    /** Verify record boundaries and exact source content. @param string $kind Entity. @param string $separator Separator. @param bool $bom Add BOM. @return void */
    #[DataProvider('validFiles')]
    public function testValidQuotedFields(string $kind, string $separator, bool $bom): void
    {
        $this->configure($separator === ',' ? ';' : $separator);
        [, $header, $base] = $this->definition($kind);
        $descriptions = ['Shared path ' . chr(92) . 'nas' . chr(92) . 'share' . chr(92), "Two lines\nwith a \"quote\"; and, separators", ''];
        $stream = fopen('php://temp', 'w+');
        if ($bom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }
        fputcsv($stream, $header, $separator, '"', '', "\r\n");
        foreach ($descriptions as $index => $description) {
            $row = $base;
            $row[0] .= (string) $index;
            $row[2] = $description;
            fputcsv($stream, $row, $separator, '"', '', "\r\n");
            fwrite($stream, "\r\n");
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $preview = $this->preview($kind, $csv);
        self::assertSame(3, $preview['valid_count']);
        self::assertSame(0, $preview['error_count']);
        self::assertCount(3, $preview['records']);
        self::assertSame($descriptions, array_column($preview['source_records'], $header[2]));
        self::assertSame(['Workshop0', 'Workshop1', 'Workshop2'], array_column($preview['source_records'], $header[0]));
    }

    /** Exercise missing and surplus columns in every preview. @return iterable */
    public static function invalidFiles(): iterable
    {
        foreach (['events', 'venues', 'specialdays'] as $kind) {
            foreach ([2, 4] as $columns) {
                yield "$kind $columns columns" => [$kind, $columns];
            }
        }
    }

    /** Reject malformed records before mapping can drop data. @param string $kind Entity. @param int $columns Actual width. @return void */
    #[DataProvider('invalidFiles')]
    public function testColumnMismatch(string $kind, int $columns): void
    {
        [, $header] = $this->definition($kind);
        $csv = implode(';', $header) . "\n" . implode(';', array_fill(0, $columns, 'value')) . "\n";
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("CSV record 2: expected 3 columns, found $columns.");
        $this->preview($kind, $csv);
    }

    /** Headers ending in literal slashes must not consume the first record. @return void */
    public function testSeparatorDetectionWithQuotedHeader(): void
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "venue,\"extra path\\\"\nRoom,Value\n");
        rewind($stream);
        try {
            [$separator, $header] = (new ReflectionMethod($this->controller, 'detectExternalCsvSeparator'))->invoke($this->controller, $stream, 0, ';', '"');
            self::assertSame(',', $separator);
            self::assertSame(['venue', 'extra path\\'], $header);
            self::assertSame(['Room', 'Value'], fgetcsv($stream, 0, $separator, '"', ''));
        } finally {
            fclose($stream);
        }
    }

    /** Exercise both restore dialects without database persistence. @return iterable */
    public static function restoreFormats(): iterable
    {
        yield 'legacy' => [false];
        yield 'RFC metadata' => [true];
    }

    /** Execute the restore header policy and row reader from the controller. @param bool $standard Metadata present. @return void */
    #[DataProvider('restoreFormats')]
    public function testRestoreEscapePolicyIsPreserved(bool $standard): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        $start = strpos($source, '            $fields = array();', strpos($source, '// get fields, on first row'));
        $end = strpos($source, '            // If there is no validated fields', $start);
        $headerCode = str_replace('Text::', '\\Joomla\\CMS\\Language\\Text::', substr($source, $start, $end - $start));
        preg_match('/while \(\(\$data = (fgetcsv\([^\n]+?\))\) !== FALSE\)/', substr($source, $end), $match);
        self::assertNotEmpty($match);
        $read = eval('return function ($handle) { $separator = ";"; $delimiter = chr(34); $hasBom = true; $object_fields = ["title" => true]; ' . $headerCode . '$rows = []; while (($row = ' . $match[1] . ') !== false) { $rows[] = $row; } return [$csvEscape, $rows]; };');
        $escape = $standard ? '' : chr(92);
        $header = $standard ? ['title', 'jem_csv_format'] : ['title'];
        $value = $standard ? 'Path ' . chr(92) : 'Path ' . chr(92) . '"quoted"';
        $row = $standard ? [$value, 'rfc4180'] : [$value];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $header, ';', '"', $escape);
        fputcsv($stream, $row, ';', '"', $escape);
        fputcsv($stream, $standard ? ['Second', 'rfc4180'] : ['Second'], ';', '"', $escape);
        rewind($stream);
        try {
            [$actualEscape, $rows] = $read($stream);
            self::assertSame($escape, $actualEscape);
            self::assertCount(2, $rows);
            self::assertSame($row, $rows[0]);
            self::assertSame('Second', $rows[1][0]);
        } finally {
            fclose($stream);
        }
    }

    /** Execute the direct Special Days parsing loop, replacing only database persistence. @return void */
    public function testDirectSpecialDaysReaderSkipsMalformedRecordWithWarning(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/specialdays.php');
        $start = strpos($source, '        while (($row = fgetcsv(');
        $persist = strpos($source, '            $data = $this->normaliseSpecialDayCsvRow($data);', $start);
        $loop = substr($source, $start, $persist - $start) . '$captured[] = $data;' . "\n}\n";
        $loop = str_replace(['Text::', 'Log::'], ['\\Joomla\\CMS\\Language\\Text::', '\\Joomla\\CMS\\Log\\Log::'], $loop);
        $runner = eval('return function ($handle, $app) { $header = $fields = ["title", "description"]; $separator = ";"; $delimiter = chr(34); $rowNumber = 1; $totalValueBytes = 0; $result = ["error" => 0]; $captured = []; ' . $loop . 'return [$captured, $result]; };');
        $logger = new class {
            public array $messages = [];
            public function addSpecialDaysImportLog($message, $priority): void { $this->messages[] = $message; }
        };
        $app = new class {
            public array $messages = [];
            public function enqueueMessage($message, $type): void { $this->messages[] = [$message, $type]; }
        };
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "First;\"path \\\"\nBad;row;extra\nSecond;Normal\n");
        rewind($stream);
        try {
            [$rows, $result] = $runner->call($logger, $stream, $app);
            self::assertSame(['First', 'Second'], array_column($rows, 'title'));
            self::assertSame('path \\', $rows[0]['description']);
            self::assertSame(1, $result['error']);
            self::assertCount(1, $logger->messages);
            self::assertSame([['CSV record 3: expected 2 columns, found 3. Check the separators and quoted fields.', 'warning']], $app->messages);
        } finally {
            fclose($stream);
        }
    }
}
