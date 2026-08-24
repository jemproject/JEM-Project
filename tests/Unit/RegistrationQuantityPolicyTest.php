<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationquantity.class.php';

final class RegistrationQuantityPolicyTest extends TestCase
{
    public function testOptionalRequestValuesRemainCompatible(): void
    {
        self::assertSame(0, JemRegistrationQuantity::parseOptional(null));
        self::assertSame(0, JemRegistrationQuantity::parseOptional(''));
        self::assertSame(2, JemRegistrationQuantity::parseOptional('002'));
    }

    #[DataProvider('invalidQuantityProvider')]
    public function testMalformedOrOutOfRangeQuantitiesAreRejected($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRegistrationQuantity::parse($value);
    }

    public static function invalidQuantityProvider(): array
    {
        return array(
            'negative integer' => array(-1),
            'negative string' => array('-1'),
            'signed positive' => array('+1'),
            'decimal' => array('1.5'),
            'exponent' => array('1e3'),
            'overflow' => array('2147483648'),
            'array' => array(array('1')),
            'boolean' => array(true),
        );
    }

    public function testSelectedOperationIgnoresTheValidInactiveFormValue(): void
    {
        $decision = JemRegistrationQuantity::resolveResponse(
            JemRegistrationTransition::ATTENDING,
            1,
            2,
            $this->registration(JemRegistrationTransition::ATTENDING, 2),
            $this->event(1, 5)
        );

        self::assertSame(JemRegistrationQuantity::INCREASE, $decision->operation);
        self::assertSame(3, $decision->places);
        self::assertSame(JemRegistrationTransition::ATTENDING, $decision->status);
    }

    public function testManagerPerUserQuantitiesRequireAnExactMapping(): void
    {
        $selection = JemRegistrationQuantity::parseManagerSelection('12:2,15:3', array(12, 15));

        self::assertSame(0, $selection->places);
        self::assertSame(array(12 => 2, 15 => 3), $selection->byUser);

        foreach (array('12:2', '12:2,12:3', '12:2,99:1', '12:1.5,15:2') as $invalid) {
            try {
                JemRegistrationQuantity::parseManagerSelection($invalid, array(12, 15));
                self::fail('Invalid manager quantity mapping was accepted: ' . $invalid);
            } catch (InvalidArgumentException $e) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testActiveTotalsMustRespectTheConfiguredMinimumAndMaximum(): void
    {
        try {
            JemRegistrationQuantity::resolveResponse(
                JemRegistrationTransition::ATTENDING,
                1,
                0,
                false,
                $this->event(2, 4)
            );
            self::fail('A total below the configured minimum was accepted.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('minimum', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        JemRegistrationQuantity::resolveResponse(
            JemRegistrationTransition::ATTENDING,
            3,
            0,
            $this->registration(JemRegistrationTransition::ATTENDING, 2),
            $this->event(1, 4)
        );
    }

    public function testCompleteCancellationCanReachZero(): void
    {
        $decision = JemRegistrationQuantity::resolveResponse(
            JemRegistrationTransition::NOT_ATTENDING,
            1,
            3,
            $this->registration(JemRegistrationTransition::ATTENDING, 3),
            $this->event(2, 5)
        );

        self::assertSame(JemRegistrationQuantity::CANCEL, $decision->operation);
        self::assertSame(0, $decision->places);
        self::assertSame(JemRegistrationTransition::NOT_ATTENDING, $decision->status);
    }

    public function testPartialCancellationCannotLeaveAnActiveTotalBelowMinimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRegistrationQuantity::resolveResponse(
            JemRegistrationTransition::NOT_ATTENDING,
            1,
            2,
            $this->registration(JemRegistrationTransition::ATTENDING, 3),
            $this->event(2, 5)
        );
    }

    public function testCancellationCannotExceedCurrentPlaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemRegistrationQuantity::resolveResponse(
            JemRegistrationTransition::NOT_ATTENDING,
            1,
            4,
            $this->registration(JemRegistrationTransition::ATTENDING, 3),
            $this->event(1, 5)
        );
    }

    public function testInactiveRowsMayStoreZeroButActiveRowsMayNot(): void
    {
        JemRegistrationQuantity::assertStoredRow(
            $this->registration(JemRegistrationTransition::NOT_ATTENDING, 0),
            $this->event(1, 5)
        );
        self::addToAssertionCount(1);

        $this->expectException(InvalidArgumentException::class);
        JemRegistrationQuantity::assertStoredRow(
            $this->registration(JemRegistrationTransition::ATTENDING, 0),
            $this->event(1, 5)
        );
    }

    private function registration(int $status, int $places): object
    {
        return (object) array('status' => $status, 'waiting' => 0, 'places' => $places);
    }

    private function event(int $minimum, int $maximum): object
    {
        return (object) array('minbookeduser' => $minimum, 'maxbookeduser' => $maximum);
    }
}
