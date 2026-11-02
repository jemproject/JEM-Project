<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Bundled recurrence definitions and real date calculation, without importing sample data. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SampleRecurrenceCutoffTest extends TestCase
{
    private string $timezone = '';

    /** @return void Load the actual recurrence helper through a read-only checkout stream. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ROOT', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        $this->timezone = date_default_timezone_get();
    }

    /** @return void Restore the process timezone. */
    protected function tearDown(): void
    {
        if ($this->timezone !== '') {
            date_default_timezone_set($this->timezone);
        }
    }

    /** @return iterable<string, array{string, string}> Year, leap-day and DST boundary coverage. */
    public static function dates(): iterable
    {
        foreach (['2026-11-02', '2026-12-20', '2028-02-20', '2026-03-01', '2026-10-01'] as $base) {
            foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $zone) {
                yield $base . '/' . $zone => [$base, $zone];
            }
        }
    }

    /**
     * Parse a VALUES list while retaining quoted commas and nested SQL expressions.
     *
     * @param string $input Contents of the single-row VALUES parentheses.
     * @return array SQL expressions in column order, not evaluated SQL values.
     */
    private function splitValues(string $input): array
    {
        $values = [];
        $start = $depth = 0;
        $quoted = false;
        for ($i = 0, $length = strlen($input); $i < $length; ++$i) {
            $char = $input[$i];
            if ($quoted) {
                if ($char === '\\') {
                    ++$i;
                } elseif ($char === "'") {
                    if (($input[$i + 1] ?? '') === "'") {
                        ++$i;
                    } else {
                        $quoted = false;
                    }
                }
            } elseif ($char === "'") {
                $quoted = true;
            } elseif ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
                --$depth;
            } elseif ($char === ',' && $depth === 0) {
                $values[] = trim(substr($input, $start, $i - $start));
                $start = $i + 1;
            }
        }
        self::assertFalse($quoted, 'Sample values have balanced quotes.');
        self::assertSame(0, $depth, 'Sample values have balanced parentheses.');
        $values[] = trim(substr($input, $start));

        return $values;
    }

    /**
     * Resolve the sample's explicit relative-day date expressions, not arbitrary SQL.
     *
     * @param string $expression CURDATE plus an interval of days.
     * @param string $base Simulated import date.
     * @return string Resolved civil date.
     */
    private function sampleDate(string $expression, string $base): string
    {
        self::assertSame(1, preg_match('/^CURDATE\(\)\+ INTERVAL (\d+) DAY$/', $expression, $match));

        return (new DateTimeImmutable($base))->modify('+' . $match[1] . ' days')->format('Y-m-d');
    }

    /**
     * Verify the four sample starts fit the inclusive cutoff and match the real recurrence calculation.
     *
     * @param string $base Simulated sample import date.
     * @param string $zone PHP timezone used by the production recurrence helper.
     * @return void
     */
    #[DataProvider('dates')]
    public function testSampleSeriesFitsItsCutoff(string $base, string $zone): void
    {
        date_default_timezone_set($zone);
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
        $lines = array_values(array_filter(explode("\n", $source), static fn (string $line): bool =>
            str_starts_with($line, 'INSERT INTO `#__jem_events`') && str_contains($line, "'Balkan Beatz'")
        ));
        self::assertCount(4, $lines);
        self::assertStringContainsString(
            "UPDATE `#__jem_events` SET `recurrence_first_id` = 3 WHERE `id` IN (8, 9, 10) AND `title` = 'Balkan Beatz' AND `recurrence_first_id` = 2;",
            $source
        );
        $rows = [];
        foreach ($lines as $index => $line) {
            self::assertSame(1, preg_match('/^INSERT INTO `#__jem_events` \((.*?)\) VALUES \((.*)\);\s*$/', $line, $match));
            $fields = array_map(static fn (string $field): string => trim($field, " `"), explode(',', $match[1]));
            $values = $this->splitValues($match[2]);
            self::assertCount(count($fields), $values);
            $row = array_combine($fields, $values);
            self::assertSame('CURDATE()+ INTERVAL 44 DAY', $row['recurrence_limit_date']);
            self::assertSame([3, 8, 9, 10][$index], (int) $row['id']);
            self::assertSame($index === 0 ? 0 : 2, (int) $row['recurrence_first_id']);
            self::assertSame($index, (int) $row['recurrence_counter']);
            self::assertSame('2', $row['recurrence_type']);
            self::assertSame('2', $row['recurrence_number']);
            self::assertSame("''", $row['recurrence_byday']);
            $row['recurrence_byday'] = '';
            foreach (['dates', 'enddates', 'recurrence_limit_date'] as $field) {
                $row[$field] = $this->sampleDate($row[$field], $base);
            }
            self::assertLessThanOrEqual($row['recurrence_limit_date'], $row['dates']);
            self::assertSame(4, (new DateTimeImmutable($row['dates']))->diff(new DateTimeImmutable($row['enddates']))->days);
            $rows[] = $row;
        }
        $expected = array_map(static fn (int $offset): string =>
            (new DateTimeImmutable($base))->modify('+' . $offset . ' days')->format('Y-m-d'), [2, 16, 30, 44]
        );
        self::assertSame($expected, array_column($rows, 'dates'));
        self::assertSame($rows[3]['dates'], $rows[0]['recurrence_limit_date']);

        $next = $rows[0];
        foreach (array_slice($rows, 1) as $occurrence) {
            $next = JemHelper::calculate_recurrence($next);
            self::assertIsArray($next);
            self::assertSame($occurrence['dates'], $next['dates']);
            self::assertSame($occurrence['enddates'], $next['enddates']);
        }
        $next = JemHelper::calculate_recurrence($next);
        self::assertIsArray($next);
        self::assertGreaterThan($rows[0]['recurrence_limit_date'], $next['dates']);
    }
}
