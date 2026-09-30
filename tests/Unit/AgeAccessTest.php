<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/ageaccess.class.php';

final class AgeAccessTest extends TestCase
{
    public function testEffectiveMinimumUsesTheStricterEventOrVenueLevel(): void
    {
        self::assertNull(JemAgeAccess::effectiveMinAge(null, null));
        self::assertSame(12, JemAgeAccess::effectiveMinAge(12, null));
        self::assertSame(16, JemAgeAccess::effectiveMinAge(null, 16));
        self::assertSame(16, JemAgeAccess::effectiveMinAge(12, 16));
        self::assertSame(18, JemAgeAccess::effectiveMinAge(18, 12));
        self::assertSame(0, JemAgeAccess::effectiveMinAge(0, null));
    }

    public function testAgeIsCalculatedOnTheEventDate(): void
    {
        self::assertSame(17, JemAgeAccess::ageOnDate('2008-09-21', '2026-09-20'));
        self::assertSame(18, JemAgeAccess::ageOnDate('2008-09-21', '2026-09-21'));
        self::assertSame(18, JemAgeAccess::ageOnDate('2008-09-21', '2026-09-22'));
    }

    public function testInvalidOrFutureDatesDoNotProduceAnAge(): void
    {
        self::assertNull(JemAgeAccess::ageOnDate('not-a-date', '2026-09-21'));
        self::assertNull(JemAgeAccess::ageOnDate('2026-09-22', '2026-09-21'));
        self::assertNull(JemAgeAccess::ageOnDate('2000-02-30', '2026-09-21'));
    }

    public function testGuestsCanSeeClassifiedEventsAndUnrestrictedEventsNeedNoProfile(): void
    {
        $guest = new AgeAccessValueStub(array('id' => 0, 'guest' => 1));
        $user = new AgeAccessValueStub(array('id' => 42, 'guest' => 0));

        self::assertSame(JemAgeAccess::GUEST, JemAgeAccess::assess($guest, 18, '2026-09-21'));
        self::assertSame(JemAgeAccess::UNRESTRICTED, JemAgeAccess::assess($user, null, '2026-09-21'));
        self::assertSame(JemAgeAccess::ELIGIBLE, JemAgeAccess::assess($user, 0, '2026-09-21'));
    }

    public function testUndefinedClassificationProducesNoBadgeMetadata(): void
    {
        $guest = new AgeAccessValueStub(array('id' => 0, 'guest' => 1));
        $event = (object) array('dates' => '2026-09-21');

        JemAgeAccess::decorateEvent($event, $guest);

        self::assertNull($event->age_minimum);
        self::assertSame('', $event->age_badge_label);
        self::assertSame(JemAgeAccess::UNRESTRICTED, $event->age_access_state);
    }

    public function testExplicitAllAgesClassificationRemainsVisible(): void
    {
        $guest = new AgeAccessValueStub(array('id' => 0, 'guest' => 1));
        $event = (object) array(
            'dates' => '2026-09-21',
            'event_age_id' => 1,
            'event_age_title' => 'All ages',
            'event_age_minimum' => 0,
            'event_age_background' => '#247A3D',
            'event_age_text' => '#FFFFFF',
        );

        JemAgeAccess::decorateEvent($event, $guest);

        self::assertSame(0, $event->age_minimum);
        self::assertSame('0+', $event->age_badge_label);
        self::assertSame(JemAgeAccess::GUEST, $event->age_access_state);
    }

    public function testEditorBypassAffectsVisibilityWithoutBypassingRegistrationAgeState(): void
    {
        $event = (object) array(
            'age_access_state' => JemAgeAccess::RESTRICTED,
            'age_visibility_bypass' => false,
        );

        self::assertFalse(JemAgeAccess::canViewEvent($event));

        $event->age_visibility_bypass = true;
        self::assertTrue(JemAgeAccess::canViewEvent($event));
        self::assertSame(JemAgeAccess::RESTRICTED, $event->age_access_state);
    }
}

final class AgeAccessValueStub
{
    public function __construct(private array $values)
    {
    }

    public function get($name, $default = null)
    {
        return $this->values[(string) $name] ?? $default;
    }
}
