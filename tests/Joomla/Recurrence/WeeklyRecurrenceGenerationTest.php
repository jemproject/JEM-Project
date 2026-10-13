<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Executes the production helper with Joomla classes, without booting a site or writing a database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WeeklyRecurrenceGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to Joomla (no installation/database changes required).');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ROOT', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        date_default_timezone_set('Europe/Madrid');
    }

    public function testLegacyWeeklyIgnoresStaleWeekdays(): void
    {
        foreach ([null, '', '0', 'MO,FR'] as $stored) {
            $row = JemHelper::calculate_recurrence([
                'dates' => '2026-10-14', 'enddates' => '2026-10-15',
                'recurrence_type' => 2, 'recurrence_number' => 2, 'recurrence_byday' => $stored,
            ]);
            self::assertSame('2026-10-28', $row['dates']);
            self::assertSame('2026-10-29', $row['enddates']);
        }
    }

    public function testProductionHelperUsesSelectedDaysAndKeepsRuleAcrossRuns(): void
    {
        $row = ['dates' => '2026-10-14', 'enddates' => '2026-10-15',
            'recurrence_type' => 2, 'recurrence_number' => 2, 'recurrence_byday' => 'W:1,3,5'];
        foreach (['2026-10-16', '2026-10-26', '2026-10-28', '2026-10-30'] as $expected) {
            $row = JemHelper::calculate_recurrence($row);
            self::assertSame($expected, $row['dates']);
            self::assertSame((new DateTimeImmutable($expected))->modify('+1 day')->format('Y-m-d'), $row['enddates']);
            self::assertSame('W:1,3,5', $row['recurrence_byday']);
        }
    }

    public function testMonthlyWeekdaysAreStillMonthly(): void
    {
        $row = JemHelper::calculate_recurrence([
            'dates' => '2026-10-14', 'enddates' => null, 'weekstart' => 1,
            'recurrence_type' => 4, 'recurrence_number' => 1, 'recurrence_byday' => 'MO,FR',
        ]);
        self::assertSame('2026-11-02', $row['dates']);
    }

    public function testSelectedSpecialDaysSkipOccurrencesWithoutMovingThem(): void
    {
        $row = ['dates' => '2026-10-14', 'enddates' => null, 'recurrence_type' => 2,
            'recurrence_number' => 1, 'recurrence_byday' => 'W:3,5', 'counter' => 0];
        $days = [['start_date' => '2026-10-16'], ['start_date' => '2026-10-23']];
        $created = [$row['dates']]; // The manually created initial event remains.
        while (($row = JemHelper::calculate_recurrence($row)) !== false && $row['dates'] <= '2026-10-30') {
            if (!JemRecurrenceExclusions::matches($days, $row['dates'], $row['enddates'])) {
                $created[] = $row['dates'];
                $row['counter']++;
            }
        }
        self::assertSame(['2026-10-14', '2026-10-21', '2026-10-28', '2026-10-30'], $created);
        self::assertSame(3, $row['counter']);
    }

    public function testAllCandidatesCanBeExcludedWithinTheGenerationHorizon(): void
    {
        $row = ['dates' => '2026-10-14', 'enddates' => null, 'recurrence_type' => 2,
            'recurrence_number' => 1, 'recurrence_byday' => 'W:3,5'];
        $excluded = 0;
        while (($row = JemHelper::calculate_recurrence($row)) !== false && $row['dates'] <= '2026-10-30') {
            self::assertTrue(JemRecurrenceExclusions::matches([['weekdays' => '3,5']], $row['dates']));
            $excluded++;
        }
        self::assertSame(5, $excluded);
        self::assertSame('2026-11-04', $row['dates']);
    }

    public function testThreeDayWeeklyEventKeepsItsDurationAndExcludesTheWholeOccurrence(): void
    {
        foreach ([null, 'W:3'] as $weekdays) {
            $row = ['dates' => '2026-10-14', 'enddates' => '2026-10-16', 'recurrence_type' => 2,
                'recurrence_number' => 1, 'recurrence_byday' => $weekdays];
            $row = JemHelper::calculate_recurrence($row);
            self::assertSame('2026-10-21', $row['dates']);
            self::assertSame('2026-10-23', $row['enddates']);
            // A closure on Thursday skips Wednesday-to-Friday, not just Thursday.
            self::assertTrue(JemRecurrenceExclusions::matches(
                [['start_date' => '2026-10-22']], $row['dates'], $row['enddates']
            ));
            $row = JemHelper::calculate_recurrence($row);
            self::assertSame('2026-10-28', $row['dates']);
            self::assertSame('2026-10-30', $row['enddates']);
        }
    }
}
