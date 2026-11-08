<?php
declare(strict_types=1);
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once JEM_TEST_ROOT . '/site/classes/registrationquantity.class.php';

final class AreaQuantityFeedbackTest extends TestCase
{
    #[DataProvider('selectionCases')]
    public function testLimitsApplyToTheTotal(array $quantities, int $maximum, ?int $expected, ?int $code): void
    {
        $event = (object) ['minbookeduser' => 2, 'maxbookeduser' => $maximum];
        try {
            $total = JemRegistrationQuantity::areaSelectionTotal($quantities, $event);
            self::assertNull($code);
            self::assertSame($expected, $total);
        } catch (InvalidArgumentException $exception) {
            self::assertNotNull($code, $exception->getMessage());
            self::assertSame($code, $exception->getCode());
            $message = JemRegistrationQuantity::areaSelectionMessage($exception, $event);
            self::assertStringStartsWith('COM_JEM_CAPACITY_REGISTRATION_', $message[0]);
            if ($code === JemRegistrationQuantity::ERROR_BELOW_MINIMUM) self::assertSame(2, $message[1]);
            if ($code === JemRegistrationQuantity::ERROR_ABOVE_MAXIMUM) self::assertSame(4, $message[1]);
        }
    }

    public static function selectionCases(): array
    {
        return [
            'one in each area' => [[1, 1], 4, 2, null],
            'two in one area' => [[2, 0], 4, 2, null],
            'one total' => [[1, 0], 4, null, JemRegistrationQuantity::ERROR_BELOW_MINIMUM],
            'maximum total' => [[2, 2], 4, 4, null],
            'both fields within maximum but total exceeds it' => [[3, 2], 4, null, JemRegistrationQuantity::ERROR_ABOVE_MAXIMUM],
            'single area exceeds maximum' => [[5], 4, null, JemRegistrationQuantity::ERROR_ABOVE_MAXIMUM],
            'none selected' => [[0, 0], 4, null, JemRegistrationQuantity::ERROR_EMPTY_SELECTION],
            'no fields' => [[], 4, null, JemRegistrationQuantity::ERROR_EMPTY_SELECTION],
            'malformed' => [['abc'], 4, null, JemRegistrationQuantity::ERROR_INVALID_QUANTITY],
            'negative' => [[-1, 3], 4, null, JemRegistrationQuantity::ERROR_INVALID_QUANTITY],
            'decimal' => [['1.5', 1], 4, null, JemRegistrationQuantity::ERROR_INVALID_QUANTITY],
            'array' => [[[1]], 4, null, JemRegistrationQuantity::ERROR_INVALID_QUANTITY],
            'overflow' => [['2147483647', 1], 0, null, JemRegistrationQuantity::ERROR_INVALID_QUANTITY],
            'unlimited' => [[5, 6], 0, 11, null],
            'empty unused area' => [['2', ''], 4, 2, null],
        ];
    }

    #[DataProvider('inputCaps')]
    public function testInputCapRespectsAvailabilityAndWaitingList(int $capacity, int $remaining, int $maximum, int $waiting, int $expected): void
    {
        $area = ['capacity' => $capacity, 'remaining' => $remaining, 'current_quantity' => 2];
        $event = (object) ['minbookeduser' => 2, 'maxbookeduser' => $maximum, 'waitinglist' => $waiting];
        self::assertSame($expected, JemRegistrationQuantity::areaInputMaximum($area, $event));
    }

    public static function inputCaps(): array
    {
        return [[20,20,4,0,4], [20,1,4,0,1], [20,0,4,0,0], [20,0,4,1,4],
            [2,2,4,1,2], [20,5,0,0,5], [20,0,0,1,20], [20,20,4,1,4]];
    }

    public function testClassicPolicyKeepsLimitsWithDistinctCodes(): void
    {
        try {
            JemRegistrationQuantity::assertActiveTotal(1, (object) ['minbookeduser' => 2, 'maxbookeduser' => 4]);
            self::fail('Expected minimum validation.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(JemRegistrationQuantity::ERROR_BELOW_MINIMUM, $exception->getCode());
        }
        $this->expectExceptionCode(JemRegistrationQuantity::ERROR_ABOVE_MAXIMUM);
        JemRegistrationQuantity::assertActiveTotal(5, (object) ['minbookeduser' => 2, 'maxbookeduser' => 4]);
    }
}
