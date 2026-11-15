<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/attendeeeditoraccess.class.php';

final class AttendeeEditorAccessTest extends TestCase
{
    /** @return void Guests are denied before querying private reservation data. */
    public function testGuestDoesNotQueryReservations(): void
    {
        $db = new AttendeeEditorAccessDatabase();
        try {
            JemAttendeeEditorAccess::assertReservation($db, (object) ['id' => 0], 1);
            self::fail('Expected access denial');
        } catch (DomainException $error) {
            self::assertSame(403, $error->getCode());
            self::assertSame([], $db->queries);
        }
    }

    /** @return void Only the authoritative reservation event is passed to the ACL check. */
    public function testReservationResolvesItsAuthoritativeEvent(): void
    {
        $db = new AttendeeEditorAccessDatabase();
        $actor = new class {
            public int $id = 9;
            public array $checks = [];
            public function can($action, $type, $id, $owner): bool
            {
                $this->checks[] = [$action, $type, $id, $owner];
                return $id === 7 && $owner === 9;
            }
        };
        self::assertSame(7, JemAttendeeEditorAccess::assertReservation($db, $actor, 41));
        self::assertSame([['edit', 'event', 7, 9]], $actor->checks);
        $db->event = 8;
        $this->expectException(DomainException::class);
        JemAttendeeEditorAccess::assertReservation($db, $actor, 41);
    }
}

final class AttendeeEditorAccessDatabase
{
    public array $queries = [];
    public int $event = 7;
    public function setQuery($query): self { $this->queries[] = $query; return $this; }
    public function loadResult(): int { return $this->event; }
    public function loadObject(): object { return (object) ['id' => $this->event, 'created_by' => 9]; }
}
