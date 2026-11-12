<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';

final class RegistrationAreaChangeTest extends TestCase
{
    /** @return iterable<string,array> Changes within and between two capacity areas. */
    public static function areaCases(): iterable
    {
        // Old allocations, new allocations, used by others, capacity per area, old status, expected status/error.
        yield 'increase in full area' => [[1, 0], [2, 0], [1, 0], 2, 1, null];
        yield 'increase with room' => [[1, 0], [2, 0], [0, 0], 2, 1, 1];
        yield 'same total moved into full area' => [[2, 0], [0, 2], [0, 2], 2, 1, null];
        yield 'smaller total moved into full area' => [[2, 0], [0, 1], [0, 2], 2, 1, null];
        yield 'same total moved with room' => [[2, 0], [0, 2], [0, 0], 2, 1, 1];
        yield 'unchanged overcapacity' => [[2, 0], [2, 0], [2, 0], 3, 1, 1];
        yield 'reduced overcapacity' => [[3, 0], [2, 0], [2, 0], 3, 1, 1];
        yield 'retain overfull area while adding elsewhere' => [[2, 0], [2, 1], [2, 0], 3, 1, 1];
        yield 'new full-area registration waits' => [[0, 0], [1, 0], [2, 0], 2, 0, 2];
        yield 'waiting update remains waiting' => [[1, 0], [2, 0], [2, 0], 2, 2, 2];
    }

    /**
     * A failed area revision preserves all confirmed allocations, even at equal totals.
     * @param array $old Stored per-area quantities.
     * @param array $new Proposed per-area quantities.
     * @param array $used Quantities held by other attendees.
     * @param int $capacity Capacity of each area.
     * @param int $status Original status, zero for no registration.
     * @param int|null $expected Resulting status, null for rejection.
     * @return void
     */
    #[DataProvider('areaCases')]
    public function testAreaRevision(array $old, array $new, array $used, int $capacity, int $status, ?int $expected): void
    {
        $db = new RegistrationAreaChangeDatabase($old, $used, $capacity);
        $before = $status ? (object) ['id' => 41, 'uid' => 15, 'event' => 7, 'revision' => 1, 'places' => array_sum($old)] : null;
        if ($before) {
            JemRegistrationTransition::applyLogicalStatus($before, $status);
        }
        $snapshot = $before ? clone $before : null;
        $after = (object) ['id' => $before ? 41 : 0, 'uid' => 15, 'event' => 7, 'places' => array_sum($new)];
        JemRegistrationTransition::applyLogicalStatus($after, $status === 2 ? 2 : 1);
        $options = ['respectPlaces' => true, 'allowWaiting' => true, 'capacityAllocations' => ['area:20' => $new[0], 'area:21' => $new[1]]];
        $service = new JemRegistrationService($db);
        try {
            $after = (new ReflectionMethod($service, 'applyCapacityPolicy'))->invoke($service, $before, $after, $options);
            $allocations = (new ReflectionMethod($service, 'prepareCapacityAllocations'))->invoke($service, $before, $after, $options);
            self::assertNotNull($expected, 'A confirmed booking must not be moved to waiting.');
            self::assertSame($expected, JemRegistrationTransition::logicalStatus($after));
            self::assertSame(array_sum($new), array_sum(array_column($allocations['rows'], 'quantity')));
        } catch (RuntimeException $exception) {
            self::assertNull($expected, $exception->getMessage());
            self::assertSame(JemRegistrationService::CHANGE_UNAVAILABLE, $exception->getMessage());
        }
        self::assertEquals($snapshot, $before);
    }
}

/** Read-only query boundary; the production service performs all policy decisions. */
final class RegistrationAreaChangeDatabase
{
    private object $event;
    private array $previous = [];
    private array $used = [];

    /** @param array $old Stored allocation. @param array $used Other bookings. @param int $capacity Per-area limit. */
    public function __construct(array $old, array $used, int $capacity)
    {
        $areas = [];
        foreach ([20, 21] as $i => $id) {
            $areas[] = ['id' => $id, 'name' => 'Area ' . $id, 'published' => 1, 'capacity' => $capacity];
            if ($old[$i] > 0) {
                $this->previous[] = ['venue_capacity_area_id' => $id, 'venue_layout_id' => 10, 'quantity' => $old[$i]];
            }
            $this->used[] = (object) ['venue_capacity_area_id' => $id, 'venue_layout_id' => 10, 'quantity' => $used[$i]];
        }
        $this->event = (object) ['id' => 7, 'maxplaces' => 100, 'reservedplaces' => 0, 'waitinglist' => 1, 'capacity_mode' => 'areas',
            'venue_snapshot' => json_encode(['schema' => 'jem-venue-capacity/v1', 'spaces' => [[
                'layout' => ['id' => 10, 'revision' => 1], 'capacity_areas' => $areas,
            ]]]),
        ];
    }
    public function getQuery($new = true): object { return new RegistrationAreaChangeQuery(); }
    public function quoteName($name, $alias = null) { return $name; }
    public function setQuery($query): self { return $this; }
    public function loadObject(): object { return clone $this->event; }
    public function loadResult(): int { return array_sum(array_column($this->used, 'quantity')); }
    public function loadAssocList(): array { return $this->previous; }
    public function loadObjectList(): array { return $this->used; }
}

final class RegistrationAreaChangeQuery
{
    public function __call($name, $arguments): self { return $this; }
    public function __toString(): string { return 'READ ONLY AREA FIXTURE'; }
}
