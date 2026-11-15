<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';

final class OwnPlaceBreakdownTest extends TestCase
{
    public function testOtherUsersGuestsAndCancelledReservationsHaveNoBreakdown(): void
    {
        $db = new OwnPlaceBreakdownDatabase();
        $service = new JemRegistrationService($db);
        $row = $this->registration();
        self::assertSame([], $service->ownPlaceBreakdown($row, 0));
        self::assertSame([], $service->ownPlaceBreakdown($row, 99));
        $row->status = -1;
        self::assertSame([], $service->ownPlaceBreakdown($row, 42));
        self::assertSame([], $service->ownPlaceBreakdown(null, 42));
        self::assertSame([], $db->queries);
    }

    public function testAreaBreakdownUsesCurrentRevisionAndStoredNames(): void
    {
        $db = new OwnPlaceBreakdownDatabase();
        $db->areas = [$this->area(1), $this->area(3, 8, 'Students')];
        $rows = (new JemRegistrationService($db))->ownPlaceBreakdown($this->registration(), 42);
        self::assertSame([1, 3], array_column($rows, 'quantity'));
        self::assertSame(['General', 'Students'], array_column($rows, 'area'));
        self::assertSame(['Great Hall', 'Great Hall'], array_column($rows, 'space'));
        self::assertStringContainsString('registration_revision = 5', $db->queries[0]);
        self::assertStringContainsString('register_id = 17', $db->queries[0]);
    }

    public function testPricedAdmissionsKeepTicketTypesAndExcludeFees(): void
    {
        $db = new OwnPlaceBreakdownDatabase();
        $db->areas = [$this->area(4)];
        $db->pools = [9 => ['venue_capacity_area_id' => 7, 'venue_layout_id' => 2, 'name' => 'Current pool']];
        $db->items = [
            (object) ['line_kind' => 'admission', 'quantity' => 1, 'capacity_pool_id' => 9, 'item_name' => 'Adult'],
            (object) ['line_kind' => 'admission', 'quantity' => 3, 'capacity_pool_id' => 9, 'item_name' => 'Student'],
            (object) ['line_kind' => 'management_fee', 'quantity' => 1],
        ];
        $row = $this->registration();
        $row->pricing_mode = 'priced';
        $rows = (new JemRegistrationService($db))->ownPlaceBreakdown($row, 42);
        self::assertCount(2, $rows);
        self::assertSame(['Adult', 'Student'], array_column($rows, 'type'));
        self::assertSame([1, 3], array_column($rows, 'quantity'));
        self::assertSame(['General', 'General'], array_column($rows, 'area'));
    }

    public function testTicketWithoutPhysicalLocationDoesNotInventOne(): void
    {
        $db = new OwnPlaceBreakdownDatabase();
        $db->items = [(object) ['line_kind' => 'admission', 'quantity' => 2, 'capacity_pool_id' => null, 'item_name' => 'Online']];
        $row = $this->registration();
        $row->pricing_mode = 'single';
        $rows = (new JemRegistrationService($db))->ownPlaceBreakdown($row, 42);
        self::assertSame([['type' => 'Online', 'space' => '', 'area' => '', 'quantity' => 2]], $rows);
    }

    private function registration(): object
    {
        return (object) ['id' => 17, 'uid' => 42, 'event' => 20, 'revision' => 5, 'status' => 1, 'pricing_mode' => 'classic'];
    }

    private function area(int $quantity, int $id = 7, string $name = 'General'): array
    {
        return ['venue_capacity_area_id' => $id, 'venue_layout_id' => 2, 'space_name' => 'Great Hall', 'area_name' => $name, 'quantity' => $quantity];
    }
}

/** Read-only query double for current revision and privacy checks. */
final class OwnPlaceBreakdownDatabase
{
    public array $queries = [];
    public array $areas = [];
    public array $pools = [];
    public array $items = [];
    public function getQuery(bool $new): object { return new OwnPlaceBreakdownQuery(); }
    public function quoteName(string $name): string { return $name; }
    public function setQuery($query): self { $this->queries[] = (string) $query; return $this; }
    public function loadAssocList(?string $key = null): array { return $key === 'id' ? $this->pools : $this->areas; }
    public function loadObjectList(): array { return $this->items; }
}

final class OwnPlaceBreakdownQuery
{
    private array $parts = [];
    public function __call(string $name, array $args): self { $this->parts[] = $name . ' ' . $args[0]; return $this; }
    public function __toString(): string { return implode(' ', $this->parts); }
}
