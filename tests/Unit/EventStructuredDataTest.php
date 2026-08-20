<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/eventstructureddata.class.php';

final class EventStructuredDataTest extends TestCase
{
    public function testBuildsGoogleEligiblePhysicalEventWithTimezoneAndAddress(): void
    {
        $analysis = JemEventStructuredData::analyse((object) array(
            'title' => 'Spring Conference',
            'dates' => '2026-03-29',
            'times' => '10:00:00',
            'enddates' => '',
            'endtimes' => '12:30:00',
            'event_status' => 'scheduled',
            'venue' => 'Main Hall',
            'street' => '1 Example Street',
            'postalCode' => '28001',
            'city' => 'Madrid',
            'state' => 'Madrid',
            'country' => 'ES',
            'reginvitedonly' => 0,
        ), $this->context(array(
            'timezone' => 'Europe/Madrid',
            'physical_location_visible' => true,
            'physical_address_visible' => true,
            'description' => '<p>A public <strong>conference</strong>.</p>',
            'image_urls' => array('/images/events/spring.jpg'),
        )));

        self::assertTrue($analysis['schema_valid']);
        self::assertTrue($analysis['google_eligible']);
        self::assertSame(array(), $analysis['reasons']);
        self::assertSame('2026-03-29T10:00+02:00', $analysis['data']['startDate']);
        self::assertSame('2026-03-29T12:30+02:00', $analysis['data']['endDate']);
        self::assertSame('https://schema.org/OfflineEventAttendanceMode', $analysis['data']['eventAttendanceMode']);
        self::assertSame('ES', $analysis['data']['location']['address']['addressCountry']);
        self::assertSame('A public conference.', $analysis['data']['description']);
        self::assertSame(array('https://example.test/images/events/spring.jpg'), $analysis['data']['image']);
    }

    public function testPreservesAllDayDatesWithoutInventingTimes(): void
    {
        $data = JemEventStructuredData::build((object) array(
            'title' => 'All-day exhibition',
            'dates' => '2026-10-02',
            'enddates' => '2026-10-04',
            'venue' => 'Gallery',
        ), $this->context(array(
            'timezone' => 'Europe/Madrid',
            'physical_location_visible' => true,
        )));

        self::assertSame('2026-10-02', $data['startDate']);
        self::assertSame('2026-10-04', $data['endDate']);
    }

    public function testOmitsUnsafeEntityWhenARequiredPublicFactIsMissing(): void
    {
        $analysis = JemEventStructuredData::analyse((object) array(
            'title' => 'Date to be announced',
            'dates' => '0000-00-00',
            'venue' => 'Main Hall',
        ), $this->context(array('physical_location_visible' => true)));

        self::assertFalse($analysis['schema_valid']);
        self::assertFalse($analysis['google_eligible']);
        self::assertSame(array(), $analysis['data']);
        self::assertContains('start_date', $analysis['reasons']);
    }

    public function testVenueWithoutPublicAddressRemainsSchemaValidButNotGoogleEligible(): void
    {
        $analysis = JemEventStructuredData::analyse((object) array(
            'title' => 'Public lecture',
            'dates' => '2026-09-10',
            'venue' => 'Auditorium',
            'street' => 'Secret Street',
        ), $this->context(array(
            'physical_location_visible' => true,
            'physical_address_visible' => false,
        )));

        self::assertTrue($analysis['schema_valid']);
        self::assertFalse($analysis['google_eligible']);
        self::assertArrayNotHasKey('address', $analysis['data']['location']);
        self::assertContains('google_requires_public_address', $analysis['reasons']);
    }

    public function testBuildsOnlineEventWithoutClaimingGoogleEligibility(): void
    {
        $analysis = JemEventStructuredData::analyse((object) array(
            'title' => 'Online workshop',
            'dates' => '2026-11-08',
            'times' => '18:00:00',
            'online_meeting_label' => 'Public stream',
        ), $this->context(array(
            'timezone' => 'UTC',
            'virtual_location_visible' => true,
            'virtual_location_url' => 'https://stream.example.test/watch/42',
        )));

        self::assertTrue($analysis['schema_valid']);
        self::assertFalse($analysis['google_eligible']);
        self::assertSame('https://schema.org/OnlineEventAttendanceMode', $analysis['data']['eventAttendanceMode']);
        self::assertSame('VirtualLocation', $analysis['data']['location']['@type']);
        self::assertSame('Public stream', $analysis['data']['location']['name']);
        self::assertContains('google_requires_physical_location', $analysis['reasons']);
    }

    public function testBuildsHybridEventFromBothPublicLocations(): void
    {
        $data = JemEventStructuredData::build((object) array(
            'title' => 'Hybrid seminar',
            'dates' => '2026-12-01',
            'venue' => 'Room 4',
        ), $this->context(array(
            'physical_location_visible' => true,
            'virtual_location_visible' => true,
            'virtual_location_url' => '/live/seminar',
        )));

        self::assertSame('https://schema.org/MixedEventAttendanceMode', $data['eventAttendanceMode']);
        self::assertSame('Place', $data['location'][0]['@type']);
        self::assertSame('VirtualLocation', $data['location'][1]['@type']);
    }

    public function testMovedOnlineStatusUsesOnlyThePublicVirtualLocation(): void
    {
        $data = JemEventStructuredData::build((object) array(
            'title' => 'Moved workshop',
            'dates' => '2026-12-02',
            'event_status' => 'moved_online',
            'venue' => 'Old room',
        ), $this->context(array(
            'physical_location_visible' => true,
            'virtual_location_visible' => true,
            'virtual_location_url' => 'https://video.example.test/moved',
        )));

        self::assertSame('https://schema.org/EventMovedOnline', $data['eventStatus']);
        self::assertSame('https://schema.org/OnlineEventAttendanceMode', $data['eventAttendanceMode']);
        self::assertSame('VirtualLocation', $data['location']['@type']);
    }

    public function testMovedOnlineEventWithoutPublicVirtualLocationFailsClosed(): void
    {
        $analysis = JemEventStructuredData::analyse((object) array(
            'title' => 'Moved workshop without link',
            'dates' => '2026-12-02',
            'event_status' => 'moved_online',
            'venue' => 'Old room',
        ), $this->context(array('physical_location_visible' => true)));

        self::assertFalse($analysis['schema_valid']);
        self::assertSame(array(), $analysis['data']);
        self::assertContains('location', $analysis['reasons']);
    }

    public function testPrivateRegistrationDoesNotSuppressTruthfulSchemaButFailsGoogleEligibility(): void
    {
        $analysis = JemEventStructuredData::analyse((object) array(
            'title' => 'Invited guests',
            'dates' => '2026-12-03',
            'venue' => 'Private room',
            'street' => '1 Private Road',
            'city' => 'Madrid',
            'reginvitedonly' => 1,
        ), $this->context(array(
            'physical_location_visible' => true,
            'physical_address_visible' => true,
        )));

        self::assertTrue($analysis['schema_valid']);
        self::assertFalse($analysis['google_eligible']);
        self::assertContains('google_requires_general_public_attendance', $analysis['reasons']);
    }

    public function testRejectsInvalidAndCredentialBearingUrls(): void
    {
        self::assertSame('', JemEventStructuredData::absoluteUrl('javascript:alert(1)', 'https://example.test/'));
        self::assertSame('', JemEventStructuredData::absoluteUrl('https://user:secret@example.test/event'));
        self::assertSame('https://example.test/jem/event/42', JemEventStructuredData::absoluteUrl('/jem/event/42', 'https://example.test/subdir/'));
    }

    public function testRenderCannotCloseTheJsonLdScriptElement(): void
    {
        $html = JemEventStructuredData::render(array(
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => '</script><script>alert("x")</script>',
        ));

        self::assertStringNotContainsString('</script><script>', $html);
        self::assertStringContainsString('\\u003C/script\\u003E', $html);
        self::assertMatchesRegularExpression('#^<script type="application/ld\\+json">(.+)</script>$#', $html);

        $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
        self::assertSame('</script><script>alert("x")</script>', json_decode($json, true, 512, JSON_THROW_ON_ERROR)['name']);
    }

    public function testNormalisesEditorHtmlAndTruncatesWithoutBrokenEncoding(): void
    {
        $text = JemEventStructuredData::normaliseText(
            '<p>Concert &amp; conversation</p><script>alert("hidden")</script><p>afterwards</p>',
            24
        );

        self::assertSame('Concert & conversation…', $text);
        self::assertStringNotContainsString('hidden', $text);
        self::assertSame(1, preg_match('//u', $text));
    }

    public function testStoredCommercialFieldsDoNotCreateAnIncompleteOffer(): void
    {
        $data = JemEventStructuredData::build((object) array(
            'title' => 'Priced event',
            'dates' => '2026-12-04',
            'venue' => 'Theatre',
            'currency' => 'EUR',
            'ticket_availability' => 'instock',
            'pricing_mode' => 'paid',
        ), $this->context(array('physical_location_visible' => true)));

        self::assertArrayNotHasKey('offers', $data);
    }

    private function context(array $overrides = array()): array
    {
        return array_replace(array(
            'canonical_url' => '/events/example',
            'base_url' => 'https://example.test/',
            'general_public' => true,
        ), $overrides);
    }
}
