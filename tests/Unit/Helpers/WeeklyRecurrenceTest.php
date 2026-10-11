<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/recurrencevalidator.class.php';

final class WeeklyRecurrenceTest extends TestCase
{
    public function testStorageIsExplicitCompactAndOrdered(): void
    {
        self::assertSame('W:1,3,5', JemRecurrenceValidator::encodeWeeklyDays('fr,MO,we,MO'));
        $all = JemRecurrenceValidator::encodeWeeklyDays('MO,TU,WE,TH,FR,SA,SU');
        self::assertLessThanOrEqual(20, strlen($all));
        self::assertSame(['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], JemRecurrenceValidator::weeklyDays($all));
        self::assertFalse(JemRecurrenceValidator::encodeWeeklyDays(''));
        self::assertFalse(JemRecurrenceValidator::encodeWeeklyDays('MO,XX'));
        self::assertFalse(JemRecurrenceValidator::encodeWeeklyDays([['MO']]));
    }

    #[DataProvider('legacyRules')]
    public function testLegacyValuesNeverActivateNewWeeklyRules(?string $rule): void
    {
        self::assertNull(JemRecurrenceValidator::weeklyDays($rule));
    }

    public static function legacyRules(): array
    {
        return [[null], [''], ['0'], ['MO'], ['MO,WE,FR'], ['SU']];
    }

    #[DataProvider('schedules')]
    public function testGeneratesExpectedCalendarDates(string $start, int $interval, string $days, array $expected): void
    {
        $row = ['dates' => $start, 'enddates' => null, 'recurrence_number' => $interval,
            'recurrence_byday' => JemRecurrenceValidator::encodeWeeklyDays($days), 'times' => '10:30:00'];
        foreach ($expected as $date) {
            $row = JemRecurrenceValidator::nextWeeklyOccurrence($row);
            self::assertSame($date, $row['dates']);
            self::assertNull($row['enddates']);
            self::assertSame('10:30:00', $row['times']);
        }
    }

    public static function schedules(): array
    {
        return [
            'seed Wednesday, no earlier Monday' => ['2026-10-14', 1, 'MO,WE,FR', ['2026-10-16', '2026-10-19', '2026-10-21']],
            'same Wednesday' => ['2026-10-14', 1, 'WE', ['2026-10-21', '2026-10-28']],
            'two week interval' => ['2026-10-14', 2, 'MO,WE,FR', ['2026-10-16', '2026-10-26', '2026-10-28', '2026-10-30', '2026-11-09']],
            'Sunday and Monday' => ['2026-10-11', 1, 'MO,SU', ['2026-10-12', '2026-10-18']],
            'two weeks Sunday Monday' => ['2026-10-11', 2, 'MO,SU', ['2026-10-19', '2026-10-25']],
            'start weekday removed' => ['2026-10-14', 1, 'MO', ['2026-10-19', '2026-10-26']],
            'year rollover' => ['2026-12-30', 1, 'MO,WE,FR', ['2027-01-01', '2027-01-04']],
            'leap year' => ['2028-02-28', 1, 'MO,TU', ['2028-02-29', '2028-03-06']],
            'daily week' => ['2026-10-11', 1, 'MO,TU,WE,TH,FR,SA,SU', ['2026-10-12', '2026-10-13', '2026-10-14']],
            'maximum interval' => ['2026-10-14', 52, 'WE', ['2027-10-13']],
        ];
    }

    public function testDurationSurvivesBothDstTransitions(): void
    {
        $original = date_default_timezone_get();
        try {
            foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $timezone) {
                date_default_timezone_set($timezone);
                foreach ([['2026-03-27', '2026-03-30', '2026-04-03', '2026-04-06'],
                    ['2026-10-23', '2026-10-26', '2026-10-30', '2026-11-02']] as $dates) {
                    $next = JemRecurrenceValidator::nextWeeklyOccurrence([
                        'dates' => $dates[0], 'enddates' => $dates[1],
                        'recurrence_number' => 1, 'recurrence_byday' => 'W:5',
                    ]);
                    self::assertSame($dates[2], $next['dates']);
                    self::assertSame($dates[3], $next['enddates']);
                }
            }
        } finally {
            date_default_timezone_set($original);
        }
    }

    #[DataProvider('invalidRules')]
    public function testRejectsMalformedRules(array $changes): void
    {
        self::assertFalse(JemRecurrenceValidator::nextWeeklyOccurrence(array_replace([
            'dates' => '2026-10-14', 'enddates' => null, 'recurrence_number' => 1, 'recurrence_byday' => 'W:1,3,5',
        ], $changes)));
    }

    public static function invalidRules(): array
    {
        return [
            [['recurrence_byday' => 'W:']], [['recurrence_byday' => 'W:0']], [['recurrence_byday' => 'W:8']],
            [['recurrence_byday' => 'W:1,']], [['recurrence_byday' => 'MO,FR']],
            [['recurrence_number' => 0]], [['recurrence_number' => 53]], [['recurrence_number' => -1]],
            [['recurrence_number' => '1.5']], [['dates' => '2026-02-30']], [['dates' => '']],
            [['enddates' => '2026-10-13']], [['enddates' => '2026-02-30']],
        ];
    }

    public function testEverySubsetKeepsCadenceAcrossRepeatedCleanupRuns(): void
    {
        $codes = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
        $anchor = new DateTimeImmutable('2026-10-12', new DateTimeZone('UTC'));
        for ($mask = 1; $mask < 128; $mask++) {
            $days = array_values(array_filter($codes, static fn ($index) => ($mask & (1 << $index)) !== 0, ARRAY_FILTER_USE_KEY));
            foreach ([1, 2, 3, 52] as $interval) {
                $row = ['dates' => '2026-10-14', 'recurrence_number' => $interval,
                    'recurrence_byday' => JemRecurrenceValidator::encodeWeeklyDays($days)];
                for ($i = 0; $i < 8; $i++) {
                    $previous = $row['dates'];
                    $row = JemRecurrenceValidator::nextWeeklyOccurrence($row);
                    $date = new DateTimeImmutable($row['dates'], new DateTimeZone('UTC'));
                    self::assertGreaterThan($previous, $row['dates']);
                    self::assertContains($codes[(int) $date->format('N') - 1], $days);
                    self::assertSame(0, intdiv((int) $anchor->diff($date)->days, 7) % $interval);
                }
            }
        }
    }

    public function testProductionSaveAndGenerationUseExplicitWeeklyContract(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        self::assertStringContainsString("\$jinput->exists('recurrence_weekly_days')", $model);
        self::assertStringContainsString("\$data['recurrence_byday'] = \$recurrencebyday;", $model);
        self::assertStringContainsString('JemRecurrenceValidator::nextWeeklyOccurrence($recurrence_row)', $helper);
        self::assertStringContainsString('$start_day = $start_day + ($recurrence_number * $week_time);', $helper);
        foreach (['admin/views/event/tmpl/edit.php', 'site/views/editevent/tmpl/edit_extended.php',
            'site/views/editevent/tmpl/responsive/edit_extended.php'] as $template) {
            self::assertStringContainsString('weeklyRecurrenceDayNames', file_get_contents(JEM_TEST_ROOT . '/' . $template));
        }
    }
}
