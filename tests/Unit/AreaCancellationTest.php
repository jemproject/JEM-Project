<?php
declare(strict_types=1);
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once JEM_TEST_ROOT . '/site/classes/registrationcancellation.class.php';

final class AreaCancellationTest extends TestCase
{
    private function event(string $mode = 'areas', int $minimum = 1): object
    {
        return (object) ['capacity_mode' => $mode, 'minbookeduser' => $minimum, 'maxbookeduser' => 10];
    }

    private function registration(int $places = 4, int $status = 1, int $waiting = 0): object
    {
        return (object) ['id' => 21, 'places' => $places, 'status' => $status, 'waiting' => $waiting];
    }

    #[DataProvider('partialCases')]
    public function testPartialCancellationPreservesRemainingPlacesAndStatus(string $mode, int $status, int $waiting, array $requested, array $current): void
    {
        $before = $this->registration(4, $status, $waiting);
        $result = JemRegistrationCancellation::reduce($this->event($mode), $before, 2, $requested, $current);
        self::assertSame(2, $result['remaining']);
        self::assertSame(2, $result['places']);
        self::assertSame($waiting || $status === 2 ? 2 : $status, $result['status']);
        self::assertSame(4, $before->places);
        self::assertSame($mode === 'areas' ? 2 : 0, array_sum($result['allocations']));
    }

    public static function partialCases(): array
    {
        return [
            ['classic', 1, 0, [], []], ['classic', 1, 1, [], []],
            ['areas', 1, 0, [], ['area:1' => 4]], ['areas', 1, 1, [], ['area:1' => 4]],
            ['areas', 2, 0, ['area:1' => 1, 'area:2' => 1], ['area:1' => 2, 'area:2' => 2]],
            ['areas', 1, 0, ['area:1' => 2, 'area:2' => 0], ['area:1' => 2, 'area:2' => 2]],
        ];
    }

    #[DataProvider('invalidCases')]
    public function testInvalidReductionsCannotProduceAResponse(int $quantity, array $requested, array $current, int $minimum = 1): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRegistrationCancellation::reduce($this->event('areas', $minimum), $this->registration(), $quantity, $requested, $current);
    }

    public static function invalidCases(): array
    {
        return [
            [0, [], ['area:1' => 4]], [5, [], ['area:1' => 4]],
            [2, [], ['area:1' => 2, 'area:2' => 2]],
            [2, ['area:1' => 3], ['area:1' => 2, 'area:2' => 2]],
            [2, ['area:9' => 2], ['area:1' => 4]],
            [2, ['area:1' => 1], ['area:1' => 4]],
            [2, ['area:1' => -2], ['area:1' => 4]],
            [2, ['area:1' => '1.5'], ['area:1' => 4]],
            [2, ['area:1' => []], ['area:1' => 4]],
            [2, [], ['area:1' => 3]], [2, [], ['area:1' => 4], 3],
        ];
    }

    public function testFullCancellationRetainsHistoricalAreaAllocation(): void
    {
        $current = ['area:1' => 2, 'area:2' => 2];
        $result = JemRegistrationCancellation::reduce($this->event(), $this->registration(), 4, [], $current);
        self::assertSame(0, $result['remaining']);
        self::assertSame(-1, $result['status']);
        self::assertSame(4, $result['places']);
        self::assertSame($current, $result['allocations']);
        self::assertSame($current, $result['cancelledAreas']);
    }

    public function testSeriesUsesSameReductionNotSameFinalAllocation(): void
    {
        $first = JemRegistrationCancellation::reduce($this->event(), $this->registration(4), 2, ['area:1' => 1, 'area:2' => 1], ['area:1' => 2, 'area:2' => 2]);
        $second = JemRegistrationCancellation::reduce($this->event(), $this->registration(6), 2, ['area:1' => 1, 'area:2' => 1], ['area:1' => 3, 'area:2' => 3]);
        self::assertSame(2, $first['remaining']);
        self::assertSame(4, $second['remaining']);
        self::assertSame(['area:1' => 2, 'area:2' => 2], $second['allocations']);
    }

    public function testCancelledReservationCannotBeReducedAgain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRegistrationCancellation::reduce($this->event(), $this->registration(4, -1), 2, [], ['area:1' => 4]);
    }

    public function testPricedPartialCancellationStillRequiresCommercialWorkflow(): void
    {
        $registration = $this->registration();
        $registration->pricing_mode = 'single';
        $this->expectExceptionMessage('COM_JEM_PRICED_REGISTRATION_REQUIRES_ORDER');
        JemRegistrationCancellation::reduce($this->event('classic'), $registration, 2, [], []);
    }

    public function testClassicCannotSilentlyIgnoreAreaSelection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRegistrationCancellation::reduce($this->event('classic'), $this->registration(), 2, ['area:1' => 2], []);
    }
}
