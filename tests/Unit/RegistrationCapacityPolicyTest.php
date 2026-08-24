<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';

final class RegistrationCapacityPolicyTest extends TestCase
{
    public function testExistingRegistrationIncreaseUsesItsReplacementQuantity(): void
    {
        $db = new RegistrationCapacityDatabaseStub(3, false, 0, 1);
        $before = $this->registration(41, 1);
        $after = $this->registration(41, 2);

        $result = $this->apply($db, $before, $after);

        self::assertSame(2, $result->places);
        self::assertSame(0, $result->waiting);
        self::assertStringContainsString("`id` <> 41", $db->lastCapacityQuery);
    }

    public function testExistingRegistrationIncreaseCannotExceedCapacity(): void
    {
        $db = new RegistrationCapacityDatabaseStub(2, false, 0, 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event capacity would be exceeded.');
        $this->apply($db, $this->registration(41, 1), $this->registration(41, 2));
    }

    public function testExistingRegistrationMovesToWaitingListWhenCapacityIsUnavailable(): void
    {
        $db = new RegistrationCapacityDatabaseStub(2, true, 0, 1);
        $result = $this->apply($db, $this->registration(41, 1), $this->registration(41, 2));

        self::assertSame(JemRegistrationTransition::WAITING_LIST, JemRegistrationTransition::logicalStatus($result));
        self::assertSame(2, $result->places);
    }

    private function apply(RegistrationCapacityDatabaseStub $db, object $before, object $after): object
    {
        $service = new JemRegistrationService($db);
        $method = new ReflectionMethod(JemRegistrationService::class, 'applyCapacityPolicy');

        return $method->invoke($service, $before, $after, array(
            'respectPlaces' => true,
            'allowWaiting' => true,
        ));
    }

    private function registration(int $id, int $places): object
    {
        return (object) array(
            'id' => $id,
            'event' => 7,
            'uid' => 15,
            'status' => JemRegistrationTransition::ATTENDING,
            'waiting' => 0,
            'places' => $places,
        );
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
