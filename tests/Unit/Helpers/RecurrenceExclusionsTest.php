<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/recurrenceexclusions.class.php';

final class RecurrenceExclusionsTest extends TestCase
{
    public function testSelectionRoundTripAndClearing(): void
    {
        $tokens = JemRecurrenceExclusions::normalise(['type:2', '', 'day:7', 'day:7']);
        self::assertSame(['day:7', 'type:2'], $tokens);
        self::assertSame($tokens, JemRecurrenceExclusions::fromAttribs(json_encode(['recurrence_exclusions' => $tokens])));
        self::assertSame([], JemRecurrenceExclusions::normalise(['']));
        self::assertSame([], JemRecurrenceExclusions::fromAttribs('{}'));
        self::assertSame([], JemRecurrenceExclusions::fromAttribs(''));
    }

    #[DataProvider('invalidSelections')]
    public function testInvalidSelectionIsRejected($selection): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRecurrenceExclusions::normalise($selection);
    }

    public static function invalidSelections(): array
    {
        return [['day:1'], [['day:0']], [['day:-1']], [['type:1 OR 1=1']], [[['day:1']]],
            [['day:2147483648']], [['type:01']], [[4]], [array_fill(0, 201, 'day:1')]];
    }

    #[DataProvider('ranges')]
    public function testDateMatching(array $row, string $start, ?string $end, bool $expected): void
    {
        self::assertSame($expected, JemRecurrenceExclusions::matches([$row], $start, $end));
    }

    public static function ranges(): array
    {
        $holiday = ['start_date' => '2026-10-16'];
        $range = ['start_date' => '2026-10-16', 'end_date' => '2026-10-18'];
        return [
            'single day' => [$holiday, '2026-10-16', null, true],
            'day before' => [$holiday, '2026-10-15', null, false],
            'day after' => [$holiday, '2026-10-17', null, false],
            'multiday crossing' => [$holiday, '2026-10-15', '2026-10-17', true],
            'end inclusive' => [$range, '2026-10-18', null, true],
            'start inclusive' => [$range, '2026-10-15', '2026-10-16', true],
            'after range' => [$range, '2026-10-19', null, false],
            'unbounded Wednesdays' => [['weekdays' => '3'], '2026-10-14', null, true],
            'non Wednesday' => [['weekdays' => '3'], '2026-10-16', null, false],
            'range AND weekday' => [$range + ['weekdays' => '3'], '2026-10-14', '2026-10-20', false],
            'range AND Friday' => [$range + ['weekdays' => '5'], '2026-10-14', '2026-10-20', true],
            'start-bounded weekday' => [$holiday + ['weekdays' => '5'], '2026-10-23', null, true],
            'not annual implicit' => [$holiday, '2027-10-16', null, false],
            'empty rule' => [[], '2026-10-16', null, false],
            'long event weekday' => [['weekdays' => '0'], '2026-10-14', '2027-10-14', true],
            'DST calendar date' => [['start_date' => '2026-10-25'], '2026-10-24', '2026-10-25', true],
            'leap day' => [['start_date' => '2028-02-29'], '2028-02-28', '2028-03-01', true],
        ];
    }

    public function testCorruptStoredPolicyDoesNotBecomeAnEmptySelection(): void
    {
        $this->expectException(JsonException::class);
        JemRecurrenceExclusions::fromAttribs('{');
    }

    public function testInvalidDateFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRecurrenceExclusions::matches([['start_date' => '2026-02-30']], '2026-10-16');
    }

    public function testQueryIsActorIndependentAndLoadsOnce(): void
    {
        $db = new ExclusionQuerySpy();
        self::assertSame([], JemRecurrenceExclusions::loadRows($db, []));
        self::assertSame(0, $db->loads);
        $db->rows = [['start_date' => '2026-10-16']];
        $rows = JemRecurrenceExclusions::loadRows($db, ['day:1', 'type:2']);
        self::assertSame(1, $db->loads);
        self::assertStringContainsString('d.id IN (1)', $db->sql);
        self::assertStringContainsString('d.day_type_id IN (2)', $db->sql);
        self::assertStringContainsString('t.entity = 4', $db->sql);
        self::assertStringContainsString('d.published = 1', $db->sql);
        self::assertStringNotContainsString('access', $db->sql);
        foreach (range(1, 31) as $day) {
            JemRecurrenceExclusions::matches($rows, sprintf('2026-10-%02d', $day));
        }
        self::assertSame(1, $db->loads);
    }

    public function testDatabaseFailureIsNotTreatedAsNoExclusions(): void
    {
        $db = new ExclusionQuerySpy();
        $db->fail = true;
        $this->expectException(RuntimeException::class);
        JemRecurrenceExclusions::loadRows($db, ['day:1']);
    }
}

final class ExclusionQuerySpy
{
    public string $sql = '';
    public int $loads = 0;
    public array $rows = [];
    public bool $fail = false;

    public function getQuery($new)
    {
        $this->sql = '';
        return $this;
    }

    public function quoteName($name, $alias = null)
    {
        return $name . ($alias ? ' AS ' . $alias : '');
    }

    public function __call($name, $args)
    {
        $this->sql .= $name . ' ' . json_encode($args) . ' ';
        return $this;
    }

    public function loadAssocList(): array
    {
        $this->loads++;
        if ($this->fail) {
            throw new RuntimeException('Database unavailable');
        }
        return $this->rows;
    }
}
