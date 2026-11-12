<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';

final class RegistrationCapacityPolicyTest extends TestCase
{
    /** @return iterable<string,array> Existing, new and explicit status transitions. */
    public static function capacityCases(): iterable
    {
        // Old quantity/status, new quantity/status, capacity, other places, waiting enabled, expected status/error.
        yield 'full confirmed increase' => [1, 1, 2, 1, 6, 5, true, null];
        yield 'increase without waiting list' => [1, 1, 2, 1, 6, 5, false, null];
        yield 'increase with room' => [1, 1, 2, 1, 6, 4, true, 1];
        yield 'unchanged overcapacity' => [2, 1, 2, 1, 6, 6, true, 1];
        yield 'reduced overcapacity' => [3, 1, 2, 1, 6, 6, true, 1];
        yield 'reduced without waiting list' => [3, 1, 2, 1, 6, 6, false, 1];
        yield 'unlimited' => [1, 1, 2, 1, 0, 99, true, 1];
        yield 'new full booking' => [0, 0, 1, 1, 6, 6, true, 2];
        yield 'already waiting' => [1, 2, 2, 2, 6, 6, true, 2];
        yield 'promotion without space stays waiting' => [1, 2, 1, 1, 6, 6, true, 2];
        yield 'reactivation without space waits' => [1, -1, 1, 1, 6, 6, true, 2];
        yield 'explicit waiting transition retained' => [1, 1, 1, 2, 6, 6, true, 2];
        yield 'cancellation' => [1, 1, 1, -1, 6, 6, true, -1];
    }

    /**
     * Capacity fallback must never replace confirmed places with a waiting request.
     * @param int $oldPlaces Stored quantity, or zero for a new registration.
     * @param int $oldStatus Stored logical status.
     * @param int $newPlaces Requested total quantity.
     * @param int $newStatus Requested logical status.
     * @param int $capacity Event capacity, zero means unlimited.
     * @param int $others Places held by other registrations.
     * @param bool $waiting Whether a waiting list is available.
     * @param int|null $expected Expected status, null means reject the change.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('capacityCases')]
    public function testCapacityChange(int $oldPlaces, int $oldStatus, int $newPlaces, int $newStatus, int $capacity, int $others, bool $waiting, ?int $expected): void
    {
        $db = new RegistrationCapacityDatabaseStub($capacity, $waiting, 0, $others);
        $before = $oldPlaces ? $this->registration(41, $oldPlaces, $oldStatus) : null;
        $snapshot = $before ? clone $before : null;
        $after = $this->registration($before ? 41 : 0, $newPlaces, $newStatus);
        try {
            $result = $this->apply($db, $before, $after);
            self::assertNotNull($expected, 'An unavailable increase must be rejected.');
            self::assertSame($expected, JemRegistrationTransition::logicalStatus($result));
            self::assertSame($newPlaces, $result->places);
        } catch (RuntimeException $exception) {
            self::assertNull($expected, $exception->getMessage());
            self::assertSame(JemRegistrationService::CHANGE_UNAVAILABLE, $exception->getMessage());
        }
        self::assertEquals($snapshot, $before, 'Stored reservation must not be mutated.');
    }

    /** @return void The revised total replaces, rather than adds to, the old quantity. */
    public function testExistingRegistrationIncreaseUsesItsReplacementQuantity(): void
    {
        $db = new RegistrationCapacityDatabaseStub(3, false, 0, 1);
        $result = $this->apply($db, $this->registration(41, 1), $this->registration(41, 2));
        self::assertSame(2, $result->places);
        self::assertSame(0, $result->waiting);
        self::assertStringContainsString('`id` <> 41', $db->lastCapacityQuery);
    }

    /** @return void Organiser-held capacity is not available for additional public places. */
    public function testReservedPlacesPreventAnUnavailableIncrease(): void
    {
        $this->expectExceptionMessage(JemRegistrationService::CHANGE_UNAVAILABLE);
        $this->apply(new RegistrationCapacityDatabaseStub(6, true, 2, 3), $this->registration(41, 1), $this->registration(41, 2));
    }

    /** @return void An explicitly authorised capacity bypass keeps its established semantics. */
    public function testExplicitCapacityOverrideRemainsAvailable(): void
    {
        $result = $this->apply(new RegistrationCapacityDatabaseStub(1, true, 0, 99), $this->registration(41, 1), $this->registration(41, 2), false);
        self::assertSame(1, JemRegistrationTransition::logicalStatus($result));
    }

    /** @param RegistrationCapacityDatabaseStub $db Read-only fixture. @param object|null $before Stored row. @param object $after Proposed row. @param bool $respect Capacity policy. @return object Validated row. */
    private function apply(RegistrationCapacityDatabaseStub $db, ?object $before, object $after, bool $respect = true): object
    {
        $service = new JemRegistrationService($db);
        return (new ReflectionMethod($service, 'applyCapacityPolicy'))->invoke($service, $before, $after, [
            'respectPlaces' => $respect, 'allowWaiting' => true,
        ]);
    }

    /** @param int $id Registration ID. @param int $places Quantity. @param int $status Logical state. @return object Fixture row. */
    private function registration(int $id, int $places, int $status = 1): object
    {
        $row = (object) ['id' => $id, 'event' => 7, 'uid' => 15, 'places' => $places];
        JemRegistrationTransition::applyLogicalStatus($row, $status);
        return $row;
    }
}

final class RegistrationCapacityDatabaseStub
{
    public string $lastCapacityQuery = '';
    private object $event;
    private int $usedPlaces;
    private ?RegistrationCapacityQueryStub $query = null;

    public function __construct(int $maxPlaces, bool $waitingList, int $reservedPlaces, int $usedPlaces)
    {
        $this->event = (object) array(
            'maxplaces' => $maxPlaces,
            'waitinglist' => $waitingList ? 1 : 0,
            'reservedplaces' => $reservedPlaces,
        );
        $this->usedPlaces = $usedPlaces;
    }

    public function getQuery(bool $new = true): RegistrationCapacityQueryStub
    {
        return new RegistrationCapacityQueryStub();
    }

    public function quoteName(string $name): string
    {
        return '`' . $name . '`';
    }

    public function setQuery($query): void
    {
        $this->query = $query;
    }

    public function loadObject(): object
    {
        return clone $this->event;
    }

    public function loadResult(): int
    {
        $this->lastCapacityQuery = (string) $this->query;

        return $this->usedPlaces;
    }
}

final class RegistrationCapacityQueryStub
{
    private string $select = '';
    private string $from = '';
    private array $where = array();

    public function select($columns): self
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : (string) $columns;

        return $this;
    }

    public function from(string $table): self
    {
        $this->from = $table;

        return $this;
    }

    public function where(string $condition): self
    {
        $this->where[] = $condition;

        return $this;
    }

    public function __toString(): string
    {
        return 'SELECT ' . $this->select . ' FROM ' . $this->from
            . ($this->where ? ' WHERE ' . implode(' AND ', $this->where) : '');
    }
}
