<?php
declare(strict_types=1);

use Jem\Calendar\RecurrenceRule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** The recurrence kernel needs PHP only, without a Joomla application or language loader. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class IcsRecurrenceLibraryTest extends TestCase
{
    /** @return void Load the standalone JEM library directly from the checkout. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/icalendar/recurrence.php';
    }

    /** @return void Pure PHP callers can expand a bounded rule without Joomla. */
    public function testStandaloneCalendar(): void
    {
        $visited = 0;
        $dates = RecurrenceRule::dates(['FREQ' => 'MONTHLY', 'BYDAY' => '1MO'],
            new DateTimeImmutable('2026-01-05 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-03-31 23:59:59', new DateTimeZone('UTC')),
            static fn (DateTimeImmutable $date): bool => true, $visited);
        self::assertSame(['2026-01-05', '2026-02-02', '2026-03-02'],
            array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), iterator_to_array($dates)));
        self::assertGreaterThan(0, $visited);
        self::assertFalse(class_exists('Joomla\\CMS\\Language\\Text', false));
    }

    /** @return void Explicit imported instants disambiguate repeated hours; edits invalidate old hints. */
    public function testLocalDateTimeResolution(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/icalendar/datetime.php';
        $zone = new DateTimeZone('Europe/Madrid');
        $utc = new DateTimeZone('UTC');
        self::assertSame('00:30:00', \Jem\Calendar\DateTimeValue::local('20261025T023000', $zone)->setTimezone($utc)->format('H:i:s'));
        self::assertSame('01:30:00', \Jem\Calendar\DateTimeValue::local('20261025T023000', $zone, '20261025T013000Z')->setTimezone($utc)->format('H:i:s'));
        self::assertSame('02:30:00', \Jem\Calendar\DateTimeValue::local('20261025T033000', $zone, '20261025T013000Z')->setTimezone($utc)->format('H:i:s'));
        self::assertNull(\Jem\Calendar\DateTimeValue::local('20260329T023000', $zone));
        self::assertNull(\Jem\Calendar\DateTimeValue::local('20260230T100000', $zone));
    }

    /** @return void Applications can translate invalid-rule exceptions at their own boundary. */
    public function testInvalidRuleUsesStandardPhpException(): void
    {
        $visited = 0;
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array(RecurrenceRule::dates(['FREQ' => 'YEARLY', 'BYMONTH' => '13'],
            new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'),
            static fn (DateTimeImmutable $date): bool => true, $visited));
    }

    /** @return void The caller's shared budget prevents unbounded work across several source events. */
    public function testSharedLimitUsesStandardPhpException(): void
    {
        $visited = 50000;
        $this->expectException(OverflowException::class);
        iterator_to_array(RecurrenceRule::dates(['FREQ' => 'DAILY'],
            new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'),
            static fn (DateTimeImmutable $date): bool => true, $visited));
    }
}
