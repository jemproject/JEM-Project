<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importvenue.php';

final class ImportVenueTest extends TestCase
{
    public function testSplitsLatitudeFirstCoordinates(): void
    {
        self::assertSame(
            array('latitude' => '40.487657', 'longitude' => '-3.355224'),
            JemImportVenueHelper::normaliseCoordinatePair('40.487657, -3.355224')
        );
    }

    public function testSupportsDecimalCommasWithSemicolonSeparator(): void
    {
        self::assertSame(
            array('latitude' => '40.487657', 'longitude' => '-3.355224'),
            JemImportVenueHelper::normaliseCoordinatePair('40,487657; -3,355224')
        );
    }

    public function testDetectsLongitudeFirstWhenFirstValueExceedsLatitudeRange(): void
    {
        self::assertSame(
            array('latitude' => '40.487657', 'longitude' => '-120.355224'),
            JemImportVenueHelper::normaliseCoordinatePair('-120.355224, 40.487657')
        );
    }

    public function testRejectsOutOfRangeOrIncompleteCoordinates(): void
    {
        self::assertNull(JemImportVenueHelper::normaliseCoordinatePair('95.0, 190.0'));
        self::assertNull(JemImportVenueHelper::normaliseCoordinatePair('40.4'));
        self::assertNull(JemImportVenueHelper::normaliseCoordinatePair('0, 0'));
    }
}
