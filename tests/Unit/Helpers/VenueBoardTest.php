<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/venueboard.class.php';

final class VenueBoardTest extends TestCase
{
    private function event(int $id, int $venueId, int $start, bool $allDay = false): object
    {
        return (object) array(
            'id' => $id,
            'locid' => $venueId,
            'venue' => 'Venue ' . $venueId,
            'title' => 'Event ' . $id,
            'day_schedule' => array('start' => $start, 'all_day' => $allDay),
        );
    }

    public function testSelectedVenueIdsAreNormalised(): void
    {
        self::assertSame(array(4, 8), JemVenueBoard::selectedIds(array('4', '8', '4', '0')));
        self::assertSame(array(4, 8), JemVenueBoard::selectedIds('4,8,4'));
    }

    public function testVenueColumnsKeepEmptyChildrenAndUnselectedParentEvents(): void
    {
        $locations = array(
            11 => (object) array('id' => 11, 'name' => 'Child A'),
            12 => (object) array('id' => 12, 'name' => 'Child B'),
        );
        $columns = JemVenueBoard::group(array($this->event(2, 10, 100), $this->event(3, 11, 200)), $locations);

        self::assertSame(array(11, 12, 10), array_keys($columns));
        self::assertSame(array(), $columns[12]['events']);
        self::assertSame(2, $columns[10]['events'][0]->id);
    }

    public function testSpaceColumnsDuplicateMultipleAssignmentsAndRetainUnassignedEvents(): void
    {
        $locations = array(
            31 => (object) array('id' => 31, 'name' => 'Room A'),
            32 => (object) array('id' => 32, 'name' => 'Room B'),
        );
        $rows = array($this->event(2, 7, 200), $this->event(3, 7, 100), $this->event(4, 7, 50));
        $columns = JemVenueBoard::group($rows, $locations, array(2 => array(31, 32), 3 => array(99)), 'spaces');

        self::assertSame(array(31, 32, 0), array_keys($columns));
        self::assertSame(array(2), array_map(static fn ($event) => $event->id, $columns[31]['events']));
        self::assertSame(array(2), array_map(static fn ($event) => $event->id, $columns[32]['events']));
        self::assertSame(array(4, 3), array_map(static fn ($event) => $event->id, $columns[0]['events']));
    }

    public function testAllDaySessionsSortBeforeTimedSessions(): void
    {
        $columns = JemVenueBoard::group(array(
            $this->event(1, 7, 100),
            $this->event(2, 7, 200, true),
        ), array());

        self::assertSame(array(2, 1), array_map(static fn ($event) => $event->id, $columns[7]['events']));
    }
}
