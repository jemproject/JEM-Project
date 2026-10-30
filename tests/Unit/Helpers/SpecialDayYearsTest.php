<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/specialdayyears.class.php';

final class SpecialDayYearsTest extends TestCase
{
    public static function intervals(): array
    {
        return [
            'empty' => [[], 1, []],
            'single day' => [[['start_date' => '2026-05-01', 'end_date' => '2026-05-01']], 1, [2026]],
            'leap day' => [[['start_date' => '2028-02-29', 'end_date' => '2028-02-29']], 1, [2028]],
            'cross year' => [[['start_date' => '2025-12-31', 'end_date' => '2026-01-01']], 1, [2025, 2026]],
            'long range' => [[['start_date' => '1900-01-01', 'end_date' => '2100-12-31']], 1, range(1900, 2100)],
            'January in September period' => [[['start_date' => '2027-01-01', 'end_date' => '2027-01-31']], 9, [2026]],
            'annual boundary' => [[['start_date' => '2026-08-31', 'end_date' => '2026-09-01']], 9, [2025, 2026]],
            'before annual boundary' => [[['start_date' => '2026-08-31', 'end_date' => '2026-08-31']], 9, [2025]],
            'unbounded Sunday' => [[['weekdays' => '0']], 1, range(2024, 2028)],
            'unbounded fiscal rule' => [[['weekdays' => '0']], 12, range(2023, 2027)],
            'open end' => [[['start_date' => '1900-01-01', 'weekdays' => '0,6']], 1, [1900, 2024, 2025, 2026, 2027, 2028]],
            'open start' => [[['end_date' => '2100-12-31', 'weekdays' => '0,6']], 1, [2024, 2025, 2026, 2027, 2028, 2100]],
            'future open end' => [[['start_date' => '2040-01-01', 'weekdays' => '0,6']], 1, [2040]],
            'past open start' => [[['end_date' => '2000-12-31', 'weekdays' => '0,6']], 1, [2000]],
            'near window clipped' => [[['start_date' => '2027-01-01']], 1, [2027, 2028]],
            'overlaps and gaps' => [[
                ['start_date' => '2025-01-01', 'end_date' => '2027-12-31'],
                ['start_date' => '2023-01-01', 'end_date' => '2025-12-31'],
                ['start_date' => '2024-01-01', 'end_date' => '2024-12-31'],
                ['start_date' => '2030-01-01', 'end_date' => '2030-12-31'],
            ], 1, [2023, 2024, 2025, 2026, 2027, 2030]],
            'no start or recurrence' => [[['end_date' => '2026-12-31']], 1, []],
            'reversed' => [[['start_date' => '2027-12-31', 'end_date' => '2026-01-01']], 1, []],
            'invalid day' => [[['start_date' => '2026-02-30', 'end_date' => '2026-12-31']], 1, []],
            'invalid end' => [[['start_date' => '2026-01-01', 'end_date' => '2026-02-30']], 1, []],
            'zero start is not unbounded' => [[['start_date' => '0000-00-00', 'end_date' => '2026-12-31', 'weekdays' => '0']], 1, []],
            'zero end is not unbounded' => [[['start_date' => '2026-01-01', 'end_date' => '0000-00-00']], 1, []],
            'relative date rejected' => [[['start_date' => 'today', 'end_date' => 'tomorrow']], 1, []],
            'invalid month clamps low' => [[['start_date' => '2026-01-01', 'end_date' => '2026-12-31']], 0, [2026]],
            'invalid month clamps high' => [[['start_date' => '2026-01-01', 'end_date' => '2026-12-31']], 13, [2025, 2026]],
        ];
    }

    #[DataProvider('intervals')]
    public function testAvailableYears(array $rows, int $startMonth, array $expected): void
    {
        $rows = array_map(static fn (array $row): object => (object) $row, $rows);
        $snapshot = serialize($rows);
        $years = JemSpecialDayYears::availableYears($rows, $startMonth, new DateTimeImmutable('2026-10-30'));
        self::assertSame(array_combine($expected, $expected), $years);
        self::assertSame($snapshot, serialize($rows));
    }

    public function testManyLongRulesProduceUniqueSortedYears(): void
    {
        $rows = array_fill(0, 10000, (object) ['start_date' => '1000-01-01', 'end_date' => '9999-12-31']);
        $years = JemSpecialDayYears::availableYears($rows, 1, new DateTimeImmutable('2026-10-30'));
        self::assertCount(9000, $years);
        self::assertSame(range(1000, 9999), array_values($years));
    }

    public function testCurrentPeriodChangesOnTheConfiguredStartDate(): void
    {
        self::assertSame(2025, JemSpecialDayYears::periodYear(new DateTimeImmutable('2026-08-31'), 9));
        self::assertSame(2026, JemSpecialDayYears::periodYear(new DateTimeImmutable('2026-09-01'), 9));
        self::assertSame(2026, JemSpecialDayYears::periodYear(new DateTimeImmutable('2027-01-01'), 9));
    }
}
