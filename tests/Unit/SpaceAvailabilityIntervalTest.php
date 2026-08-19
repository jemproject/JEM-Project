<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/classes/spaceavailability.class.php';

final class SpaceAvailabilityIntervalTest extends TestCase
{
    /** @dataProvider overlapProvider */
    public function testHalfOpenUtcIntervals(string $aStart, string $aEnd, string $bStart, string $bEnd, bool $expected): void
    {
        self::assertSame($expected, JemSpaceAvailabilityService::intervalsOverlap($aStart, $aEnd, $bStart, $bEnd));
    }

    public static function overlapProvider(): iterable
    {
        yield 'exact boundary is free' => array('2030-01-01 10:00:00', '2030-01-01 11:00:00', '2030-01-01 11:00:00', '2030-01-01 12:00:00', false);
        yield 'partial overlap' => array('2030-01-01 10:00:00', '2030-01-01 11:00:00', '2030-01-01 10:30:00', '2030-01-01 12:00:00', true);
        yield 'contained' => array('2030-01-01 10:00:00', '2030-01-01 14:00:00', '2030-01-01 11:00:00', '2030-01-01 12:00:00', true);
        yield 'separate' => array('2030-01-01 10:00:00', '2030-01-01 11:00:00', '2030-01-01 12:00:00', '2030-01-01 13:00:00', false);
    }
}
