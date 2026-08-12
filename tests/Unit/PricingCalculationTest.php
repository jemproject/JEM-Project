<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/money.class.php';
require_once JEM_TEST_ROOT . '/site/classes/taxpolicy.class.php';
require_once JEM_TEST_ROOT . '/site/classes/taxcalculation.class.php';
require_once JEM_TEST_ROOT . '/site/classes/taxcalculator.class.php';

final class PricingCalculationTest extends TestCase
{
    public function testMoneyUsesExactTwoDecimalMinorUnits(): void
    {
        $money = JemMoney::fromDecimal('0012.5', 'eur');

        self::assertSame(1250, $money->minorUnits());
        self::assertSame('12.50', $money->decimal());
        self::assertSame('EUR', $money->currency());
        self::assertSame('15.00', $money->plus(JemMoney::fromDecimal('2.50', 'EUR'))->decimal());
    }

    /** @dataProvider invalidMoneyProvider */
    public function testMoneyRejectsInexactOrInvalidInput($amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemMoney::fromDecimal($amount, 'EUR');
    }

    public static function invalidMoneyProvider(): iterable
    {
        yield 'float' => array(12.50);
        yield 'three decimals' => array('12.501');
        yield 'exponent' => array('1e2');
        yield 'comma' => array('12,50');
        yield 'empty' => array('');
    }

    public function testTaxIncludedCalculationRoundsUnitsAndLinesIndependently(): void
    {
        $result = JemTaxCalculator::calculate(
            JemMoney::fromDecimal('12.00', 'EUR'),
            new JemTaxPolicy(JemTaxPolicy::STANDARD, '21.00', true),
            2
        );

        self::assertSame('9.92', $result->unitNet->decimal());
        self::assertSame('2.08', $result->unitTax->decimal());
        self::assertSame('12.00', $result->unitGross->decimal());
        self::assertSame('19.83', $result->lineNet->decimal());
        self::assertSame('4.17', $result->lineTax->decimal());
        self::assertSame('24.00', $result->lineGross->decimal());
    }

    public function testTaxExcludedCalculationProducesExactCommercialLine(): void
    {
        $result = JemTaxCalculator::calculate(
            JemMoney::fromDecimal('10.00', 'EUR'),
            new JemTaxPolicy(JemTaxPolicy::REDUCED, '10', false),
            3
        );

        self::assertSame('10.00', $result->unitNet->decimal());
        self::assertSame('1.00', $result->unitTax->decimal());
        self::assertSame('11.00', $result->unitGross->decimal());
        self::assertSame('30.00', $result->lineNet->decimal());
        self::assertSame('3.00', $result->lineTax->decimal());
        self::assertSame('33.00', $result->lineGross->decimal());
    }

    public function testHalfCentRoundsAwayFromZero(): void
    {
        $result = JemTaxCalculator::calculate(
            JemMoney::fromDecimal('0.05', 'EUR'),
            new JemTaxPolicy(JemTaxPolicy::REDUCED, '10.00', false),
            1
        );

        self::assertSame('0.01', $result->unitTax->decimal());
    }

    /** @dataProvider zeroSemanticTypeProvider */
    public function testNonTaxableSemanticTypesRemainDistinct(string $type): void
    {
        $result = JemTaxCalculator::calculate(
            JemMoney::fromDecimal('7.25', 'EUR'),
            new JemTaxPolicy($type, '0.00', true),
            1
        );

        self::assertSame('0.00', $result->lineTax->decimal());
        self::assertSame($type, $result->policy->type());
    }

    public static function zeroSemanticTypeProvider(): iterable
    {
        yield 'zero rated' => array(JemTaxPolicy::ZERO);
        yield 'exempt' => array(JemTaxPolicy::EXEMPT);
        yield 'outside scope' => array(JemTaxPolicy::OUTSIDE_SCOPE);
    }

    public function testSummaryGroupsBySemanticTypeAndRate(): void
    {
        $standard = new JemTaxPolicy(JemTaxPolicy::STANDARD, '21.00', true);
        $zero = new JemTaxPolicy(JemTaxPolicy::ZERO, '0', true);
        $groups = JemTaxCalculator::summarise(array(
            JemTaxCalculator::calculate(JemMoney::fromDecimal('12.00', 'EUR'), $standard, 2),
            JemTaxCalculator::calculate(JemMoney::fromDecimal('6.00', 'EUR'), $standard, 1),
            JemTaxCalculator::calculate(JemMoney::fromDecimal('5.00', 'EUR'), $zero, 1),
        ));

        self::assertSame(array('standard|21.00', 'zero|0.00'), array_keys($groups));
        self::assertSame('24.79', $groups['standard|21.00']['net']->decimal());
        self::assertSame('5.21', $groups['standard|21.00']['tax']->decimal());
        self::assertSame('30.00', $groups['standard|21.00']['gross']->decimal());
        self::assertSame('5.00', $groups['zero|0.00']['net']->decimal());
    }

    public function testCurrencyMixAndInvalidPoliciesAreRejected(): void
    {
        $lineEur = JemTaxCalculator::calculate(
            JemMoney::fromDecimal('1.00', 'EUR'),
            new JemTaxPolicy(JemTaxPolicy::STANDARD, '21', true),
            1
        );
        $lineUsd = JemTaxCalculator::calculate(
            JemMoney::fromDecimal('1.00', 'USD'),
            new JemTaxPolicy(JemTaxPolicy::STANDARD, '21', true),
            1
        );

        try {
            JemTaxCalculator::summarise(array($lineEur, $lineUsd));
            self::fail('Mixed currencies must be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('currency', strtolower($e->getMessage()));
        }

        $this->expectException(InvalidArgumentException::class);
        new JemTaxPolicy(JemTaxPolicy::EXEMPT, '21', true);
    }

    public function testCommercialLinesRejectZeroQuantityAndNegativePrice(): void
    {
        $policy = new JemTaxPolicy(JemTaxPolicy::STANDARD, '21', true);

        try {
            JemTaxCalculator::calculate(JemMoney::fromDecimal('1.00', 'EUR'), $policy, 0);
            self::fail('Zero quantity must be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('quantity', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        JemTaxCalculator::calculate(JemMoney::fromDecimal('-1.00', 'EUR'), $policy, 1);
    }
}
