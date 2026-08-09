<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class SchemaOrgDateTimeTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();

        if (!class_exists('JemHelper')) {
            require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        }

        require_once JEM_TEST_ROOT . '/site/classes/output.class.php';
    }

    public function testOpenDateDoesNotGenerateTemporalMetadata(): void
    {
        self::assertSame('', $this->format('', '19:00', '', '21:00'));
    }

    public function testDatedEventGeneratesIndependentValuesInItsTimezone(): void
    {
        self::assertSame(
            '<meta itemprop="startDate" content="2026-08-04T19:00+02:00" />'
            . '<meta itemprop="endDate" content="2026-08-05T21:00+02:00" />',
            $this->format('2026-08-04', '19:00', '2026-08-05', '21:00')
        );
    }

    public function testMissingEndDateUsesStartDateForAValidEndTime(): void
    {
        self::assertSame(
            '<meta itemprop="startDate" content="2026-08-04T19:00+02:00" />'
            . '<meta itemprop="endDate" content="2026-08-04T21:00+02:00" />',
            $this->format('2026-08-04', '19:00', '', '21:00')
        );
    }

    public function testDateOnlyEventKeepsDateValuesWithoutAnArtificialTime(): void
    {
        self::assertSame(
            '<meta itemprop="startDate" content="2026-08-04" />'
            . '<meta itemprop="endDate" content="2026-08-05" />',
            $this->format('2026-08-04', '', '2026-08-05', '')
        );
    }

    public function testEachDateUsesTheCorrectDaylightSavingOffset(): void
    {
        self::assertSame(
            '<meta itemprop="startDate" content="2026-10-24T19:00+02:00" />'
            . '<meta itemprop="endDate" content="2026-10-25T21:00+01:00" />',
            $this->format('2026-10-24', '19:00', '2026-10-25', '21:00')
        );
    }

    public function testVenueTimezoneIsResolvedFromTheEventConfiguration(): void
    {
        $event = (object) array(
            'timezone_mode' => 'venue',
            'venue_timezone' => 'America/New_York',
        );

        self::assertSame(
            '<meta itemprop="startDate" content="2026-08-04T19:00-04:00" />'
            . '<meta itemprop="endDate" content="2026-08-04T21:00-04:00" />',
            JemOutput::formatSchemaOrgDateTime('2026-08-04', '19:00', '', '21:00', true, $event)
        );
    }

    private function format(string $dateStart, string $timeStart, string $dateEnd, string $timeEnd): string
    {
        $event = (object) array(
            'timezone_mode' => 'custom',
            'timezone' => 'Europe/Madrid',
        );

        return JemOutput::formatSchemaOrgDateTime(
            $dateStart,
            $timeStart,
            $dateEnd,
            $timeEnd,
            true,
            $event
        );
    }
}
