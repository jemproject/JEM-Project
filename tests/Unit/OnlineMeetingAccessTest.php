<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Contract tests use the real policy, including the external booking capability. */
final class OnlineMeetingAccessTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/site/classes/registrationaccesspolicy.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/onlinemeeting.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/seriesinheritance.class.php';
    }

    private function event(array $attributes = []): object
    {
        return (object) ['id' => 10, 'published' => 1, 'event_status' => 'scheduled', 'attribs' => $attributes];
    }

    private function user(int $id, array $levels): object
    {
        return new class ($id, $levels) {
            public int $block = 0;
            public string $activation = '';
            public function __construct(public int $id, private array $levels) {}
            public function getAuthorisedViewLevels(): array { return $this->levels; }
        };
    }

    private function booking(int $uid = 7): object
    {
        return (object) ['id' => 4, 'event' => 10, 'uid' => $uid, 'status' => 1, 'waiting' => 0,
            'places' => 2, 'revision' => 3, 'invitation_key' => 'synthetic-invitation', 'reference' => 'TEST-BOOKING'];
    }

    public static function accessCases(): iterable
    {
        foreach ([1, 2, 9] as $level) {
            foreach ([0, 1] as $require) {
                foreach ([0, 7] as $id) {
                    foreach (['none', 'invited', 'waiting', 'confirmed', 'cancelled', 'other-event', 'other-user', 'zero'] as $state) {
                        yield "$level/$require/$id/$state" => [$level, $require, $id, $state];
                    }
                }
            }
        }
    }

    #[DataProvider('accessCases')]
    public function testBothRequirementsAreCumulative(int $level, int $require, int $id, string $state): void
    {
        $row = $state === 'none' ? null : $this->booking();
        if ($row) {
            $row->status = match ($state) { 'invited' => 0, 'cancelled' => -1, default => 1 };
            $row->waiting = (int) ($state === 'waiting');
            $row->places = $state === 'zero' ? 0 : 2;
            $row->event = $state === 'other-event' ? 11 : 10;
            $row->uid = $state === 'other-user' ? 8 : 7;
        }
        $levels = $id ? [1, 2] : [1];
        $expected = in_array($level, $levels, true) && (!$require || ($id === 7 && $state === 'confirmed'));
        self::assertSame($expected, JemOnlineMeeting::allowed($this->event([
            'online_meeting_access' => $level, 'online_meeting_registered' => $require,
        ]), $this->user($id, $levels), $row, true));
    }

    public function testLegacyDefaultsAndPublicExports(): void
    {
        self::assertFalse(JemOnlineMeeting::restricted($this->event()));
        self::assertFalse(JemOnlineMeeting::restricted((object) ['attribs' => '{"online_meeting_access":"1","online_meeting_registered":"0"}']));
        $event = $this->event(['online_meeting_registered' => 1]);
        $links = [(object) ['type' => 'online', 'url' => 'https://example.test/private'], (object) ['type' => 'info']];
        self::assertSame([$links[1]], JemOnlineMeeting::publicLinks($event, $links));
        self::assertSame($links, JemOnlineMeeting::publicLinks($this->event(), $links));
    }

    public function testVisibilityPublicationAndExceptionalStatesRemainRequired(): void
    {
        $event = $this->event(['online_meeting_registered' => 1]);
        $user = $this->user(7, [1]);
        self::assertFalse(JemOnlineMeeting::allowed($event, $user, $this->booking(), false));
        foreach (['cancelled', 'postponed'] as $status) {
            $event->event_status = $status;
            self::assertFalse(JemOnlineMeeting::allowed($event, $user, $this->booking(), true));
        }
        foreach (['scheduled', 'rescheduled', 'moved_online'] as $status) {
            $event->event_status = $status;
            self::assertTrue(JemOnlineMeeting::allowed($event, $user, $this->booking(), true));
        }
        $event->publish_up = '2999-01-01 00:00:00';
        self::assertFalse(JemOnlineMeeting::allowed($event, $user, $this->booking(), true));
        $event->publish_up = null;
        $event->published = 0;
        self::assertFalse(JemOnlineMeeting::allowed($event, $user, $this->booking(), true));
    }

    public function testPaymentIsIndependentAndPartialCancellationRetainsAccess(): void
    {
        $event = $this->event(['online_meeting_registered' => 1]);
        $row = $this->booking();
        $row->places = 1;
        foreach (['unpaid', 'paid', 'free', 'refund_pending'] as $payment) {
            $row->payment_state = $payment;
            self::assertTrue(JemOnlineMeeting::allowed($event, $this->user(7, [1]), $row, true));
        }
        $row->places = 0;
        self::assertFalse(JemOnlineMeeting::allowed($event, $this->user(7, [1]), $row, true));
    }

    public function testInvalidSettingsNeverBecomePublic(): void
    {
        foreach (['oops', [], -1, 0] as $level) {
            $event = $this->event(['online_meeting_access' => $level]);
            self::assertTrue(JemOnlineMeeting::restricted($event));
            self::assertFalse(JemOnlineMeeting::allowed($event, $this->user(7, [1, 2]), $this->booking(), true));
        }
        $event = $this->event(['online_meeting_registered' => 'bad']);
        self::assertFalse(JemOnlineMeeting::allowed($event, $this->user(7, [1]), $this->booking(), true));
    }

    public function testExternalProofCannotGrantRegisteredAccessOrFollowAnotherBooking(): void
    {
        $row = $this->booking(0);
        $key = JemOnlineMeeting::guestKey($row, 'synthetic-secret');
        self::assertSame(64, strlen($key));
        self::assertTrue(JemOnlineMeeting::verifyGuest($row, $key, 'synthetic-secret'));
        self::assertFalse(JemOnlineMeeting::verifyGuest($row, $key, 'different-secret'));
        $guest = $this->user(0, [1]);
        self::assertTrue(JemOnlineMeeting::allowed($this->event(['online_meeting_registered' => 1]), $guest, $row, true, true));
        self::assertFalse(JemOnlineMeeting::allowed($this->event(['online_meeting_access' => 2]), $guest, $row, true, true));
        foreach (['id', 'event', 'revision', 'uid'] as $field) {
            $changed = clone $row;
            ++$changed->$field;
            self::assertFalse(JemOnlineMeeting::verifyGuest($changed, $key, 'synthetic-secret'));
        }
        $row->status = -1;
        self::assertFalse(JemOnlineMeeting::verifyGuest($row, $key, 'synthetic-secret'));
    }

    public function testPolicyFollowsOnlineInheritanceAndCanBeRestored(): void
    {
        $root = ['id' => 10, 'inherit' => 1, 'online_meeting_url' => 'https://example.test/meeting',
            'attribs' => ['online_meeting_access' => 2, 'online_meeting_registered' => 1, 'series_inheritance' => ['overrides' => []]]];
        $child = $root;
        $child['id'] = 11;
        $samePolicy = JemSeriesInheritance::submission(['attribs' => ['online_meeting_access' => '2',
            'online_meeting_registered' => '1']], $child, $root);
        self::assertNotContains('online', JemSeriesInheritance::policy($samePolicy)['overrides']);
        $partial = JemSeriesInheritance::propagate($child, $root, ['attribs' => ['registration_intro' => 'New notice']]);
        self::assertSame(2, $partial['attribs']['online_meeting_access']);
        self::assertSame(1, $partial['attribs']['online_meeting_registered']);
        $updated = JemSeriesInheritance::propagate($child, $root, ['attribs' => [
            'online_meeting_access' => 9, 'online_meeting_registered' => 0]]);
        self::assertSame(9, $updated['attribs']['online_meeting_access']);
        $independent = JemSeriesInheritance::submission(['attribs' => ['online_meeting_access' => 1,
            'online_meeting_registered' => 1]], $child, $root);
        self::assertContains('online', JemSeriesInheritance::policy($independent)['overrides']);
        $restored = JemSeriesInheritance::submission(['series_inheritance_present' => 1, 'series_inherit' => 1,
            'series_inherit_fields' => array_keys(JemSeriesInheritance::GROUPS)], $independent, $root);
        self::assertSame(2, $restored['attribs']['online_meeting_access']);
        self::assertSame(1, $restored['attribs']['online_meeting_registered']);
        self::assertNotContains('online', JemSeriesInheritance::policy($restored)['overrides']);
    }
}
