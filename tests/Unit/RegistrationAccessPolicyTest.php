<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationaccesspolicy.class.php';

final class RegistrationAccessPolicyTest extends TestCase
{
    public function testPublishedVisibleRegistrationRemainsAllowed(): void
    {
        self::assertTrue($this->decide()->isAllowed());
    }

    public function testGuestBlockedHiddenAndUnpublishedRequestsFailClosed(): void
    {
        $guest = $this->decide(array('user' => new RegistrationAccessValueStub(array('id' => 0, 'guest' => 1))));
        $blocked = $this->decide(array('user' => new RegistrationAccessValueStub(array('id' => 42, 'block' => 1))));
        $hidden = $this->decide(array('event' => $this->event(false)));
        $unpublished = $this->decide(array('publishedNow' => false));

        self::assertSame(JemRegistrationAccessPolicy::AUTHENTICATION_REQUIRED, $guest->getReason());
        self::assertSame(JemRegistrationAccessPolicy::AUTHENTICATION_REQUIRED, $blocked->getReason());
        self::assertSame(JemRegistrationAccessPolicy::EVENT_NOT_VIEWABLE, $hidden->getReason());
        self::assertSame(JemRegistrationAccessPolicy::EVENT_NOT_VIEWABLE, $unpublished->getReason());
    }

    public function testGlobalEventAndInvitationRulesAreAuthoritative(): void
    {
        $disabled = $this->decide(array(
            'settings' => new RegistrationAccessValueStub(array('showfroregistra' => 0, 'regallowinvitation' => 1)),
        ));
        $optionalOff = $this->decide(array(
            'settings' => new RegistrationAccessValueStub(array('showfroregistra' => 2, 'regallowinvitation' => 1)),
            'event' => $this->event(true, array('registra' => 0)),
        ));
        $invitedOnly = $this->decide(array(
            'event' => $this->event(true, array('reginvitedonly' => 1)),
        ));

        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_DISABLED, $disabled->getReason());
        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_DISABLED, $optionalOff->getReason());
        self::assertSame(JemRegistrationAccessPolicy::INVITATION_REQUIRED, $invitedOnly->getReason());
    }

    public function testPersistedInvitationAllowsAResponse(): void
    {
        $decision = $this->decide(array(
            'event' => $this->event(true, array('reginvitedonly' => 1)),
            'registration' => new RegistrationAccessValueStub(array('id' => 91, 'status' => 0)),
        ));

        self::assertTrue($decision->isAllowed());
    }

    public function testCancellationRequiresAnOwnedRowAndItsWindow(): void
    {
        $missing = $this->decide(array('status' => -1));
        $closed = $this->decide(array(
            'status' => -1,
            'unregistrationOpen' => false,
            'registration' => new RegistrationAccessValueStub(array('id' => 91, 'status' => 1)),
        ));

        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_NOT_FOUND, $missing->getReason());
        self::assertSame(JemRegistrationAccessPolicy::CANCELLATION_CLOSED, $closed->getReason());
    }

    public function testExistingRowCanBeReleasedAfterVisibilityChanges(): void
    {
        $decision = $this->decide(array(
            'status' => -1,
            'publishedNow' => false,
            'event' => $this->event(false),
            'registration' => new RegistrationAccessValueStub(array('id' => 91, 'status' => 1)),
        ));

        self::assertTrue($decision->isAllowed());
    }

    public function testRegistrationAndCancellationCloseAtTheEventStart(): void
    {
        self::assertSame('open', JemRegistrationAccessPolicy::registrationWindowState(1, 200, 0, 0, 199));
        self::assertSame('closed', JemRegistrationAccessPolicy::registrationWindowState(1, 200, 0, 0, 200));
        self::assertSame('closed', JemRegistrationAccessPolicy::registrationWindowState(2, 200, 100, 300, 200));
        self::assertSame('open', JemRegistrationAccessPolicy::unregistrationWindowState(1, 200, 0, 199));
        self::assertSame('closed', JemRegistrationAccessPolicy::unregistrationWindowState(1, 200, 0, 200));
        self::assertSame('closed', JemRegistrationAccessPolicy::unregistrationWindowState(2, 300, 200, 200));
    }

    private function decide(array $overrides = array()): JemRegistrationAccessDecision
    {
        $values = array_replace(array(
            'user' => new RegistrationAccessValueStub(array('id' => 42, 'guest' => 0, 'block' => 0)),
            'event' => $this->event(),
            'settings' => new RegistrationAccessValueStub(array('showfroregistra' => 1, 'regallowinvitation' => 1)),
            'registration' => false,
            'status' => 1,
            'publishedNow' => true,
            'registrationOpen' => true,
            'unregistrationOpen' => true,
        ), $overrides);

        return JemRegistrationAccessPolicy::decide(
            $values['user'],
            $values['event'],
            $values['settings'],
            $values['registration'],
            $values['status'],
            $values['publishedNow'],
            $values['registrationOpen'],
            $values['unregistrationOpen']
        );
    }

    private function event(bool $accessView = true, array $overrides = array()): RegistrationAccessValueStub
    {
        return new RegistrationAccessValueStub(array_replace(array(
            'id' => 13,
            'registra' => 1,
            'reginvitedonly' => 0,
            'params' => new RegistrationAccessValueStub(array('access-view' => $accessView)),
        ), $overrides));
    }
}

final class RegistrationAccessValueStub
{
    public function __construct(private array $values)
    {
    }

    public function get($name, $default = null)
    {
        return $this->values[(string) $name] ?? $default;
    }
}
