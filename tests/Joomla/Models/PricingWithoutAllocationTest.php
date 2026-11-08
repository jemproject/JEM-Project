<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify independent pricing and physical capacity using real services and disposable SQL data. */
final class PricingWithoutAllocationTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Service results from an isolated database removed by the probe. */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $joomla = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$joomla || getenv('JEM_TEST_PRICING_ALLOCATION') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_PRICING_ALLOCATION=1 for disposable database tests.');
        }
        $process = proc_open(
            [PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/pricing_without_allocation_probe.php', $joomla, JEM_TEST_ROOT],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error . $output);
        self::assertSame('', $error);

        return self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{string, string}> Physical setup and pricing mode. */
    public static function bookings(): iterable
    {
        foreach (['no_venue', 'venue_only', 'profile', 'custom_areas'] as $layout) {
            foreach (['classic', 'single', 'multiple'] as $mode) {
                yield $layout . '/' . $mode => [$layout, $mode];
            }
        }
    }

    /** Check price persistence, booked quantities and calculated totals for every supported setup. */
    #[DataProvider('bookings')]
    public function testBooking(string $layout, string $mode): void
    {
        $result = self::report()['results'][$layout . '_' . $mode];
        self::assertSame(['classic' => 0, 'single' => 1, 'multiple' => 2][$mode], $result['prices']);
        self::assertSame(2, $result['places']);
        self::assertSame($mode === 'classic' ? null : '25.00', $result['amount']);
    }

    /** @return iterable<string, array{string}> Invalid policies which must preserve existing data. */
    public static function invalidPolicies(): iterable
    {
        foreach (['missing_country', 'invalid_country', 'missing_prices', 'foreign_tax', 'foreign_pool',
            'remove_booked_allocation', 'nonpositive_capacity', 'missing_area'] as $case) {
            yield $case => [$case];
        }
    }

    /** Validate rejection before persistence, including a booked physical configuration. */
    #[DataProvider('invalidPolicies')]
    public function testInvalidPolicy(string $case): void
    {
        $result = self::report()['results'][$case];
        self::assertTrue($result['rejected']);
        self::assertNotSame('', $result['error']);
    }

    /** @return iterable<string, array{string}> Edits, copies, country changes and area allocation. */
    public static function preservedPolicies(): iterable
    {
        foreach (['edit_preserves_prices', 'country_precedence_and_snapshot', 'mixed_area_booking',
            'copy_without_venue', 'remove_unbooked_allocation', 'single_area_auto_assignment'] as $case) {
            yield $case => [$case];
        }
    }

    /** Check the probe's detailed assertions on stable prices, countries and physical quantities. */
    #[DataProvider('preservedPolicies')]
    public function testPreservedPolicy(string $case): void
    {
        self::assertTrue(self::report()['results'][$case]['passed']);
    }
}
