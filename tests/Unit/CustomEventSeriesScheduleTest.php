<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/eventseries.class.php';

final class CustomEventSeriesScheduleTest extends TestCase
{
    public function testScheduleIsValidatedNormalisedAndSorted(): void
    {
        $rows = JemEventSeriesSchedule::parse(json_encode(array(
            array('event_id' => '8', 'date' => '2026-10-03', 'time' => '18:30', 'end_date' => '', 'end_time' => '20:30'),
            array('event_id' => 0, 'date' => '2026-08-08', 'time' => '18:00', 'end_date' => '2026-08-08', 'end_time' => '20:00'),
        )));

        self::assertSame('2026-08-08', $rows[0]['date']);
        self::assertSame(8, $rows[1]['event_id']);
        self::assertSame('18:30', $rows[1]['time']);
    }

    public function testOneAdditionalOccurrenceIsAccepted(): void
    {
        $rows = JemEventSeriesSchedule::parse('[{"date":"2026-08-08","time":"18:00"}]');

        self::assertCount(1, $rows);
        self::assertSame('2026-08-08', $rows[0]['date']);
    }

    public function testAtLeastOneAdditionalOccurrenceIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minimum');
        JemEventSeriesSchedule::parse('[]');
    }

    public function testPrimaryOccurrenceIsCombinedWithAdditionalDates(): void
    {
        $rows = JemEventSeriesSchedule::combine(
            array('event_id' => 10, 'date' => '2026-08-10', 'time' => '19:00', 'end_date' => '', 'end_time' => ''),
            array(array('event_id' => 0, 'date' => '2026-08-17', 'time' => '19:00', 'end_date' => '', 'end_time' => '')),
            true
        );

        self::assertCount(2, $rows);
        self::assertSame(10, $rows[0]['event_id']);
        self::assertSame('2026-08-17', $rows[1]['date']);
    }

    public function testNewSeriesRejectsAnAdditionalDateBeforeThePrimaryEvent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('before_primary');

        JemEventSeriesSchedule::combine(
            array('event_id' => 10, 'date' => '2026-08-10', 'time' => '19:00'),
            array(array('event_id' => 0, 'date' => '2026-08-09', 'time' => '19:00')),
            true
        );
    }

    public function testDuplicateOccurrencesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate');
        JemEventSeriesSchedule::parse('[{"date":"2026-08-08","time":"18:00"},{"date":"2026-08-08","time":"18:00"}]');
    }

    public function testAnExistingOccurrenceCannotAppearTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate');
        JemEventSeriesSchedule::parse('[{"event_id":12,"date":"2026-08-08"},{"event_id":12,"date":"2026-09-15"}]');
    }

    public function testOptionalBlankScheduleAndMaximumSizeAreHandled(): void
    {
        self::assertSame(array(), JemEventSeriesSchedule::parse('', false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid');
        JemEventSeriesSchedule::parse(json_encode(array_fill(0, 251, array('date' => '2026-08-08'))));
    }

    public function testInvalidCalendarDatesAndTimesAreRejected(): void
    {
        foreach (array(
            '[{"date":"2026-02-30"},{"date":"2026-03-01"}]',
            '[{"date":"2026-02-28","time":"25:00"},{"date":"2026-03-01"}]',
        ) as $json) {
            try {
                JemEventSeriesSchedule::parse($json);
                self::fail('Invalid schedule was accepted.');
            } catch (InvalidArgumentException $error) {
                self::assertSame('invalid', $error->getMessage());
            }
        }
    }

    public function testEndCannotBeBeforeStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('end_before_start');
        JemEventSeriesSchedule::parse('[{"date":"2026-08-08","time":"20:00","end_time":"18:00"},{"date":"2026-09-15","time":"19:00"}]');
    }

    public function testApplyingAnOccurrencePreservesIndependentEventFields(): void
    {
        $event = array('title' => 'Workshop', 'locid' => 4, 'dates' => null, 'times' => null);
        JemEventSeriesSchedule::apply($event, array(
            'date' => '2026-09-15', 'time' => '19:00', 'end_date' => '', 'end_time' => '21:00'
        ));

        self::assertSame('Workshop', $event['title']);
        self::assertSame(4, $event['locid']);
        self::assertSame('2026-09-15', $event['dates']);
        self::assertSame('19:00', $event['times']);
        self::assertNull($event['enddates']);
        self::assertSame('21:00', $event['endtimes']);
    }
}
