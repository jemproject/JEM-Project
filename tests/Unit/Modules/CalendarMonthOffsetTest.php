<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Support/calendar_month_offset_probe.php';

/** Regression tests for Calendar month offsets, using actual module and layout source. */
final class CalendarMonthOffsetTest extends TestCase
{
    /** @return iterable<string, array{mixed, int, int}> Stored value and expected year/month. */
    public static function offsets(): iterable
    {
        foreach ([
            [0, 2026, 11], [1, 2026, 12], [-1, 2026, 10], [2, 2027, 1],
            [12, 2027, 11], [-12, 2025, 11], [24, 2028, 11], [-24, 2024, 11],
            [120, 2036, 11], [-120, 2016, 11], [121, 2036, 12], [-121, 2016, 10],
            ['+24', 2028, 11], ['024', 2028, 11], ['-024', 2024, 11], [' 24 ', 2028, 11],
            ['abc', 2026, 11], ['2abc', 2026, 11], ['1.5', 2026, 11], ['1e2', 2026, 11],
            ['', 2026, 11], [null, 2026, 11], [[], 2026, 11], [true, 2026, 11], [1.5, 2026, 11],
            [PHP_INT_MAX, 2026, 11], [PHP_INT_MIN, 2026, 11], [str_repeat('9', 100), 2026, 11],
            [-12322, 1000, 1], [-12323, 2026, 11], [95677, 9999, 12], [95678, 2026, 11],
        ] as $index => $case) {
            yield 'offset-' . $index => $case;
        }
    }

    /** Verify complete year normalization and safe fallback without truncating malformed values. */
    #[DataProvider('offsets')]
    public function testStoredOffset(mixed $raw, int $year, int $month): void
    {
        $result = calendarMonthOffsetProbe(JEM_TEST_ROOT, $raw);
        self::assertSame($year, $result['offset_year']);
        self::assertSame($month, $result['offset_month']);
    }

    /** @return iterable<string, array{string, int, string}> Layout, offset and expected title. */
    public static function layouts(): iterable
    {
        foreach (['default', 'darkblue', 'grid'] as $layout) {
            foreach ([24 => 'November&nbsp;2028', -24 => 'November&nbsp;2024', 0 => 'November&nbsp;2026'] as $offset => $title) {
                yield $layout . '/' . $offset => [$layout, $offset, $title];
            }
        }
    }

    /** Confirm all native layouts receive a valid month index and show the intended year. */
    #[DataProvider('layouts')]
    public function testNativeCaption(string $layout, int $offset, string $title): void
    {
        self::assertSame($title, calendarMonthOffsetCaption(JEM_TEST_ROOT, $layout, calendarMonthOffsetProbe(JEM_TEST_ROOT, $offset)));
    }

    /** Follow the original links as regular or AJAX requests, preserving base-date session semantics. */
    public function testNavigationAndRememberedMonthApplyOffsetOnce(): void
    {
        $initial = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24);
        parse_str(ltrim($initial['props_next'], '&'), $input);
        $input['modjemcal_id'] = 9;
        $next = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24, $input);
        self::assertSame(2028, $next['offset_year']);
        self::assertSame(12, $next['offset_month']);
        $ajax = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24, $input + ['option' => 'com_ajax']);
        self::assertSame($next, $ajax);
        $remembered = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24, [], $next['state']);
        self::assertSame($next, $remembered);
        parse_str(ltrim($next['props_next'], '&'), $input);
        $january = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24, $input + ['modjemcal_id' => 9]);
        self::assertSame(2029, $january['offset_year']);
        self::assertSame(1, $january['offset_month']);
        $reset = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24, [], $next['state'], 0);
        self::assertSame(2028, $reset['offset_year']);
        self::assertSame(11, $reset['offset_month']);
        $other = calendarMonthOffsetProbe(JEM_TEST_ROOT, 24, ['modjemcal_id' => 99, 'modjemcal_month' => 1, 'modjemcal_year' => 2030]);
        self::assertSame(2028, $other['offset_year']);
        self::assertSame(11, $other['offset_month']);
    }

    /** Check validation with native Joomla rules, without loading or modifying an installed site. */
    public function testJoomlaRejectsNonIntegerInput(): void
    {
        $joomla = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$joomla) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to read native Joomla validation classes.');
        }
        $process = proc_open(
            [PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/calendar_month_offset_probe.php', 'validate-joomla', $joomla, JEM_TEST_ROOT],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error . $output);
        self::assertSame('', $error);
        foreach (json_decode($output, true, 512, JSON_THROW_ON_ERROR) as $row) {
            self::assertSame(in_array($row['value'], ['0', '1', '-1', '24', '-24', '121', '-121', '+24', '024'], true), $row['accepted'], $row['value']);
        }
    }
}
