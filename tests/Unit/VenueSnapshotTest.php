<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/venuesnapshot.class.php';

final class VenueSnapshotTest extends TestCase
{
    public function testInvalidOrUnknownSnapshotsFailClosed(): void
    {
        self::assertSame(array(), JemVenueSnapshot::decode(''));
        self::assertSame(array(), JemVenueSnapshot::decode('{invalid'));
        self::assertSame(array(), JemVenueSnapshot::decode('{"schema":"other"}'));
        self::assertSame('', JemVenueSnapshot::summary('{invalid'));
    }

    public function testSummaryUsesImmutableSpaceLayoutAndPublishedAreas(): void
    {
        $event = (object) array('venue_snapshot' => json_encode(array(
            'schema' => 'jem-venue-capacity/v1',
            'spaces' => array(array(
                'name' => 'Great Hall',
                'layout' => array('name' => 'Theatre', 'capacity' => 300),
                'capacity_areas' => array(
                    array('name' => 'General', 'capacity' => 220, 'published' => 1),
                    array('name' => 'Hidden', 'capacity' => 80, 'published' => 0),
                ),
            )),
        )));

        self::assertSame('Great Hall - Theatre (General)', JemVenueSnapshot::summary($event));
        self::assertSame(array(array(
            'space' => 'Great Hall',
            'layout' => 'Theatre',
            'capacity' => 300,
            'areas' => array(array('name' => 'General', 'capacity' => 220)),
        )), JemVenueSnapshot::lines($event));
    }
}
