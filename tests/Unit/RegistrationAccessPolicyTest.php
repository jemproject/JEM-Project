<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationaccesspolicy.class.php';

final class RegistrationAccessPolicyTest extends TestCase
{
    public function testPublishedVisibleRegistrationRemainsAllowed(): void
    {
        $decision = $this->decide();

        self::assertTrue($decision->isAllowed());
        self::assertSame(JemRegistrationAccessPolicy::ALLOWED, $decision->getReason());
        self::assertSame('', $decision->getMessageKey());
    }

    public function testRegistrationAlwaysClosesWhenTheEventStarts(): void
    {
        self::assertSame('open', JemRegistrationAccessPolicy::registrationWindowState(1, 200, 0, 0, 199));
        self::assertSame('closed', JemRegistrationAccessPolicy::registrationWindowState(1, 200, 0, 0, 200));
        self::assertSame('closed', JemRegistrationAccessPolicy::registrationWindowState(2, 200, 100, 300, 200));
    }

    public function testConfiguredRegistrationBoundariesCanOnlyShortenTheWindow(): void
    {
        self::assertSame('not_started', JemRegistrationAccessPolicy::registrationWindowState(2, 300, 150, 250, 149));
        self::assertSame('open', JemRegistrationAccessPolicy::registrationWindowState(2, 300, 150, 250, 150));
        self::assertSame('closed', JemRegistrationAccessPolicy::registrationWindowState(2, 300, 150, 250, 250));
    }

    public function testCancellationAlwaysClosesAtTheEarliestBoundary(): void
    {
        self::assertSame('open', JemRegistrationAccessPolicy::unregistrationWindowState(1, 200, 0, 199));
        self::assertSame('closed', JemRegistrationAccessPolicy::unregistrationWindowState(1, 200, 0, 200));
        self::assertSame('open', JemRegistrationAccessPolicy::unregistrationWindowState(2, 300, 200, 199));
        self::assertSame('closed', JemRegistrationAccessPolicy::unregistrationWindowState(2, 300, 200, 200));
        self::assertSame('closed', JemRegistrationAccessPolicy::unregistrationWindowState(2, 200, 300, 200));
    }

    #[DataProvider('authenticationProvider')]
    public function testInactiveIdentitiesCannotWrite(array $identity): void
    {
        $decision = $this->decide(array('user' => new RegistrationAccessValueStub($identity)));

        self::assertFalse($decision->isAllowed());
        self::assertSame(JemRegistrationAccessPolicy::AUTHENTICATION_REQUIRED, $decision->getReason());
    }

    public static function authenticationProvider(): array
    {
        return array(
            'guest' => array(array('id' => 0, 'guest' => 1, 'block' => 0)),
            'blocked account' => array(array('id' => 42, 'guest' => 0, 'block' => 1)),
        );
    }

    public function testHiddenOrUnpublishedEventsCannotReceiveReservations(): void
    {
        $hidden = $this->decide(array('event' => $this->event(false)));
        $unpublished = $this->decide(array('publishedNow' => false));

        self::assertSame(JemRegistrationAccessPolicy::EVENT_NOT_VIEWABLE, $hidden->getReason());
        self::assertSame(JemRegistrationAccessPolicy::EVENT_NOT_VIEWABLE, $unpublished->getReason());
        self::assertSame('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND', $unpublished->getMessageKey());
    }

    public function testGlobalAndOptionalEventSettingsAreAuthoritative(): void
    {
        $disabled = $this->decide(array(
            'settings' => new RegistrationAccessValueStub(array('showfroregistra' => 0, 'regallowinvitation' => 1)),
        ));
        $optionalOff = $this->decide(array(
            'settings' => new RegistrationAccessValueStub(array('showfroregistra' => 2, 'regallowinvitation' => 1)),
            'event' => $this->event(true, array('registra' => 0)),
        ));

        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_DISABLED, $disabled->getReason());
        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_DISABLED, $optionalOff->getReason());
    }

    public function testClosedRegistrationWindowIsRejected(): void
    {
        $decision = $this->decide(array('registrationOpen' => false));

        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_CLOSED, $decision->getReason());
        self::assertSame('COM_JEM_EVENT_REGISTRATION_CLOSED', $decision->getMessageKey());
    }

    public function testInvitationMustComeFromAnExistingOwnedRow(): void
    {
        $event = $this->event(true, array('reginvitedonly' => 1));
        $missing = $this->decide(array('event' => $event, 'registration' => false));
        $invited = $this->decide(array(
            'event' => $event,
            'registration' => new RegistrationAccessValueStub(array('id' => 91, 'status' => 0)),
        ));

        self::assertSame(JemRegistrationAccessPolicy::INVITATION_REQUIRED, $missing->getReason());
        self::assertSame('COM_JEM_NOT_INVITED', $missing->getMessageKey());
        self::assertTrue($invited->isAllowed());
    }

    public function testCancellationRequiresAnExistingOwnedRegistration(): void
    {
        $decision = $this->decide(array('status' => -1, 'registration' => false));

        self::assertSame(JemRegistrationAccessPolicy::REGISTRATION_NOT_FOUND, $decision->getReason());
        self::assertSame('COM_JEM_REGISTRATION_NOT_FOUND', $decision->getMessageKey());
    }

    public function testExistingRegistrationCanBeReleasedAfterVisibilityChanges(): void
    {
        $decision = $this->decide(array(
            'status' => -1,
            'publishedNow' => false,
            'event' => $this->event(false),
            'settings' => new RegistrationAccessValueStub(array('showfroregistra' => 0, 'regallowinvitation' => 1)),
            'registration' => new RegistrationAccessValueStub(array('id' => 91, 'status' => 1)),
        ));

        self::assertTrue($decision->isAllowed());
    }

    public function testActiveRegistrationStillHonoursCancellationWindow(): void
    {
        $decision = $this->decide(array(
            'status' => -1,
            'unregistrationOpen' => false,
            'registration' => new RegistrationAccessValueStub(array('id' => 91, 'status' => 2)),
        ));

        self::assertSame(JemRegistrationAccessPolicy::CANCELLATION_CLOSED, $decision->getReason());
        self::assertSame('COM_JEM_ERROR_ANNULATION_NOT_ALLOWED', $decision->getMessageKey());
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
