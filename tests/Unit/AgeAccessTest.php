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

    public function testEffectiveMaximumUsesTheStricterEventOrVenueLevel(): void
    {
        self::assertNull(JemAgeAccess::effectiveMaxAge(null, null));
        self::assertSame(80, JemAgeAccess::effectiveMaxAge(99, 80));
        self::assertSame(99, JemAgeAccess::effectiveMaxAge(120, null));
        self::assertSame(30, JemAgeAccess::effectiveMaxAge(30, 99));
        self::assertSame(99, JemAgeAccess::effectiveMaxAge(null, 99));
    }

    public function testAgeRangeIsInclusive(): void
    {
        self::assertTrue(JemAgeAccess::isAgeWithinRange(65, 65, 99));
        self::assertTrue(JemAgeAccess::isAgeWithinRange(99, 65, 99));
        self::assertFalse(JemAgeAccess::isAgeWithinRange(64, 65, 99));
        self::assertFalse(JemAgeAccess::isAgeWithinRange(100, 65, 99));
        self::assertFalse(JemAgeAccess::isAgeWithinRange(18, 65, 18));
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
        self::assertNull($event->age_maximum);
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
            'event_age_maximum' => 99,
            'event_age_label' => '0+',
            'event_age_background' => '#247A3D',
            'event_age_text' => '#FFFFFF',
        );

        JemAgeAccess::decorateEvent($event, $guest);

        self::assertSame(0, $event->age_minimum);
        self::assertSame(99, $event->age_maximum);
        self::assertSame('0+', $event->age_badge_label);
        self::assertSame(JemAgeAccess::GUEST, $event->age_access_state);
    }

    public function testEventAndVenueRangesAreIntersected(): void
    {
        $guest = new AgeAccessValueStub(array('id' => 0, 'guest' => 1));
        $event = (object) array(
            'dates' => '2026-09-21',
            'event_age_id' => 5,
            'event_age_title' => 'Adults only',
            'event_age_minimum' => 18,
            'event_age_maximum' => 99,
            'event_age_label' => '18+',
            'venue_age_id' => 6,
            'venue_age_title' => 'Seniors',
            'venue_age_minimum' => 65,
            'venue_age_maximum' => 99,
            'venue_age_label' => '65+',
        );

        JemAgeAccess::decorateEvent($event, $guest);

        self::assertSame(65, $event->age_minimum);
        self::assertSame(99, $event->age_maximum);
        self::assertSame('65+', $event->age_badge_label);
        self::assertFalse($event->age_range_conflict);
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
