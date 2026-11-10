<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/reminderservice.class.php';

/** Check the eligibility gate shared by reminder scheduling and pending deliveries. */
final class EventStatusReminderTest extends TestCase
{
    /** @return iterable<string,array{string,bool}> Lifecycle states and eligibility. */
    public static function statuses(): iterable
    {
        foreach (['scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online'] as $status) {
            yield $status => [$status, !in_array($status, ['cancelled', 'postponed'], true)];
        }
    }

    /** @param string $status Lifecycle. @param bool $eligible Expected result. @return void No mail transport is used. */
    #[DataProvider('statuses')]
    public function testSuspendedEventsDoNotSendReminders(string $status, bool $eligible): void
    {
        $service = (new ReflectionClass(JemReminderService::class))->newInstanceWithoutConstructor();
        $registration = (object) ['status' => 1, 'waiting' => 0, 'user_block' => 0, 'user_activation' => '',
            'user_email' => 'attendee@example.invalid', 'dates' => '2099-12-01', 'event_status' => $status];
        self::assertSame($eligible, (new ReflectionMethod($service, 'isEligible'))->invoke($service, $registration));
    }
}
