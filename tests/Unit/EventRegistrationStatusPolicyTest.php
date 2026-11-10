<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationaccesspolicy.class.php';

/** Lifecycle restrictions independent of display, physical allocation and ticket labels. */
final class EventRegistrationStatusPolicyTest extends TestCase
{
    /** @return iterable<string,array{string,bool}> Every supported lifecycle value and legacy input. */
    public static function statuses(): iterable
    {
        foreach (['scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online', '', 'legacy', ' CANCELLED '] as $status) {
            yield $status ?: 'empty' => [$status, in_array(strtolower(trim($status)), ['cancelled', 'postponed'], true)];
        }
    }

    /**
     * @param string $status Stored lifecycle value.
     * @param bool $blocked Expected suspension.
     * @return void Reject positive answers even if the caller supplies a stale open flag.
     */
    #[DataProvider('statuses')]
    public function testEventStatusOverridesStaleRegistrationOpenFlag(string $status, bool $blocked): void
    {
        $event = (object) ['event_status' => $status, 'registra' => 1, 'params' => new class {
            /** @return bool The authenticated visitor can view this event. */
            public function get($key, $default = null): bool { return $key === 'access-view'; }
        }];
        $user = (object) ['id' => 42, 'guest' => false, 'block' => false];
        $settings = (object) ['showfroregistra' => 2];
        foreach ([false, (object) ['id' => 1, 'status' => -1], (object) ['id' => 1, 'status' => 1]] as $registration) {
            $decision = JemRegistrationAccessPolicy::decide($user, $event, $settings, $registration, 1, true, true, true);
            self::assertSame(!$blocked, $decision->isAllowed());
            if ($blocked) {
                self::assertSame(JemRegistrationAccessPolicy::eventStatusMessage($event), $decision->getMessageKey());
            }
        }
        self::assertSame(JemRegistrationAccessPolicy::eventStatusMessage($event), JemRegistrationAccessPolicy::eventStatusMessage((array) $event));
        foreach ([true, false] as $canCancel) {
            $cancel = JemRegistrationAccessPolicy::decide($user, $event, $settings,
                (object) ['id' => 1, 'status' => 1], -1, false, false, $canCancel);
            self::assertSame($canCancel, $cancel->isAllowed());
        }
    }

    /** @return iterable<string,array{?int,int,int,int,bool}> Before/after logical status and quantities. */
    public static function transitions(): iterable
    {
        yield 'new attendance' => [null, 1, 0, 1, true];
        yield 'new waiting request' => [null, 2, 0, 1, true];
        yield 'reactivate' => [-1, 1, 1, 1, true];
        yield 'accept invitation' => [0, 1, 1, 1, true];
        yield 'promote' => [2, 1, 1, 1, true];
        yield 'more confirmed places' => [1, 1, 1, 2, true];
        yield 'more waiting places' => [2, 2, 1, 2, true];
        yield 'comment' => [1, 1, 2, 2, false];
        yield 'waiting comment' => [2, 2, 2, 2, false];
        yield 'partial cancellation' => [1, 1, 2, 1, false];
        yield 'waiting reduction' => [2, 2, 2, 1, false];
        yield 'full cancellation' => [1, -1, 2, 0, false];
        yield 'waiting cancellation' => [2, -1, 2, 0, false];
        yield 'release to waiting' => [1, 2, 2, 2, false];
    }

    /**
     * @param int|null $old Previous logical status.
     * @param int $new New logical status.
     * @param int $oldPlaces Previous quantity.
     * @param int $newPlaces New quantity.
     * @param bool $requiresOpen Whether the operation assigns new places.
     * @return void Preserve cancellation and administrative notes while rejecting new allocations.
     */
    #[DataProvider('transitions')]
    public function testReservationChanges(int|null $old, int $new, int $oldPlaces, int $newPlaces, bool $requiresOpen): void
    {
        $before = $old === null ? null : (object) ['id' => 1, 'uid' => 42, 'status' => $old, 'places' => $oldPlaces];
        $after = (object) ['uid' => 42, 'status' => $new, 'places' => $newPlaces];
        self::assertSame($requiresOpen, JemRegistrationAccessPolicy::addsReservation($before, $after));
        if ($before && in_array($new, [1, 2], true)) {
            $after->uid = 43;
            self::assertTrue(JemRegistrationAccessPolicy::addsReservation($before, $after));
        }
    }

    /** @return void A new date never silently changes Postponed to Rescheduled. */
    public function testPostponedDateWarningRequiresAnActualScheduleChange(): void
    {
        $before = (object) ['id' => 1, 'event_status' => 'postponed', 'dates' => '2026-11-13',
            'enddates' => null, 'times' => '10:00:00', 'endtimes' => null];
        self::assertFalse(JemRegistrationAccessPolicy::postponedScheduleChanged($before, ['dates' => '2026-11-13']));
        self::assertFalse(JemRegistrationAccessPolicy::postponedScheduleChanged($before, ['enddates' => '']));
        self::assertFalse(JemRegistrationAccessPolicy::postponedScheduleChanged($before, ['times' => '10:00']));
        self::assertFalse(JemRegistrationAccessPolicy::postponedScheduleChanged($before, ['title' => 'Revised title']));
        foreach (['dates' => '2026-11-27', 'enddates' => '2026-11-28', 'times' => '11:00:00', 'endtimes' => '12:00:00'] as $field => $value) {
            self::assertTrue(JemRegistrationAccessPolicy::postponedScheduleChanged($before, [$field => $value]));
        }
        foreach (['scheduled', 'rescheduled', 'cancelled', 'moved_online'] as $status) {
            self::assertFalse(JemRegistrationAccessPolicy::postponedScheduleChanged($before,
                ['event_status' => $status, 'dates' => '2026-11-27']));
        }
        self::assertFalse(JemRegistrationAccessPolicy::postponedScheduleChanged(null, ['event_status' => 'postponed', 'dates' => '2026-11-27']));
        self::assertSame('postponed', $before->event_status);
        self::assertSame('2026-11-13', $before->dates);
    }
}
