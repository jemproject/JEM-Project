<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';

final class RegistrationTransitionTest extends TestCase
{
    public function testOnlySupportedLogicalStatusesAreAccepted(): void
    {
        foreach (array(-1, 0, 1, 2, '-1', '2') as $status) {
            self::assertTrue(JemRegistrationTransition::isValidStatus($status));
        }

        foreach (array(-2, 3, 99, null, false, 'invalid', '1.0') as $status) {
            self::assertFalse(JemRegistrationTransition::isValidStatus($status));
        }
    }

    public function testWaitingListUsesTheStoredAttendingCombination(): void
    {
        $registration = (object) array('status' => 0, 'waiting' => 0);

        self::assertTrue(JemRegistrationTransition::applyLogicalStatus($registration, 2));
        self::assertSame(1, $registration->status);
        self::assertSame(1, $registration->waiting);
        self::assertSame(2, JemRegistrationTransition::logicalStatus($registration));
    }

    public function testTransitionContainsStableAuditContext(): void
    {
        $before = $this->registration(41, 7, 15, 1, 0);
        $after = $this->registration(41, 7, 15, -1, 0);
        $transition = JemRegistrationTransition::create($before, $after, 9, 'frontend manager!');

        self::assertTrue($transition->changed);
        self::assertSame(1, $transition->oldStatus);
        self::assertSame(-1, $transition->newStatus);
        self::assertSame(9, $transition->actorId);
        self::assertSame('frontendmanager', $transition->source);
    }

    public function testNotAttendingUsesStatusMailAndDeletionUsesUnregistrationMail(): void
    {
        $dispatcher = new RegistrationTransitionDispatcherStub();
        $before = $this->registration(41, 7, 15, 1, 0);
        $after = $this->registration(41, 7, 15, -1, 0);
        $transition = JemRegistrationTransition::create($before, $after, 9, 'backend.batch');

        self::assertTrue(JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition));
        self::assertSame('onEventUserRegistered', $dispatcher->events[0][0]);

        self::assertTrue(JemRegistrationTransition::dispatchDeletionMail($dispatcher, $after));
        self::assertSame('onEventUserUnregistered', $dispatcher->events[1][0]);
    }

    public function testWaitingListTransitionsUseTheWaitingListMail(): void
    {
        $dispatcher = new RegistrationTransitionDispatcherStub();
        $before = $this->registration(41, 7, 15, 1, 0);
        $after = $this->registration(41, 7, 15, 1, 1);
        $transition = JemRegistrationTransition::create($before, $after, 9, 'frontend.batch');

        JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition);

        self::assertSame('onUserOnOffWaitinglist', $dispatcher->events[0][0]);
    }

    public function testNoOpIsSilentUnlessRenotifyIsExplicit(): void
    {
        $dispatcher = new RegistrationTransitionDispatcherStub();
        $registration = $this->registration(41, 7, 15, -1, 0);
        $transition = JemRegistrationTransition::create($registration, $registration, 9, 'backend.renotify');

        self::assertFalse(JemRegistrationTransition::dispatchStatusMail($dispatcher, $registration, $transition));
        self::assertTrue(JemRegistrationTransition::dispatchStatusMail($dispatcher, $registration, $transition, true, true));
        self::assertCount(1, $dispatcher->events);
    }

    public function testCapacityReleaseIncludesStatusAndPlaceReductions(): void
    {
        $attending = $this->registration(41, 7, 15, 1, 0);
        $attending->places = 3;
        $reduced = clone $attending;
        $reduced->places = 1;
        $notAttending = clone $attending;
        $notAttending->status = -1;

        self::assertTrue(JemRegistrationTransition::releasesCapacity($attending, $reduced));
        self::assertTrue(JemRegistrationTransition::releasesCapacity($attending, $notAttending));
        self::assertFalse(JemRegistrationTransition::releasesCapacity($reduced, $attending));
        self::assertFalse(JemRegistrationTransition::releasesCapacity($attending, $attending));
    }

    private function registration(int $id, int $event, int $uid, int $status, int $waiting): object
    {
        return (object) array(
            'id' => $id,
            'event' => $event,
            'uid' => $uid,
            'status' => $status,
            'waiting' => $waiting,
            'places' => 1,
        );
    }
}

final class RegistrationTransitionDispatcherStub
{
    public $events = array();

    public function triggerEvent($name, $arguments = array())
    {
        $this->events[] = array($name, $arguments);

        return array(true);
    }
}
