<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Deterministic two-decimal line tax calculator.
 */
final class JemTaxCalculator
{
    private const PERCENT_FACTOR = 10000;

    public static function calculate(JemMoney $enteredUnitPrice, JemTaxPolicy $policy, int $quantity): JemTaxCalculation
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A commercial line quantity must be at least one.');
        }
        if ($enteredUnitPrice->minorUnits() < 0) {
            throw new InvalidArgumentException('Admission and management-fee prices cannot be negative.');
        }

        [$unitNet, $unitTax, $unitGross] = self::split($enteredUnitPrice, $policy);
        $enteredLine = $enteredUnitPrice->multipliedBy($quantity);
        [$lineNet, $lineTax, $lineGross] = self::split($enteredLine, $policy);

        return new JemTaxCalculation(
            $unitNet,
            $unitTax,
            $unitGross,
            $lineNet,
            $lineTax,
            $lineGross,
            $quantity,
            $policy
        );
    }

    /**
     * Group commercial-line totals by semantic tax type and exact rate.
     *
     * @param   JemTaxCalculation[]  $calculations
     *
     * @return  array<string,array{tax_type:string,tax_rate:string,net:JemMoney,tax:JemMoney,gross:JemMoney}>
     */
    public static function summarise(array $calculations): array
    {
        $groups = array();

        foreach ($calculations as $calculation) {
            if (!$calculation instanceof JemTaxCalculation) {
                throw new InvalidArgumentException('Tax summaries accept only calculated commercial lines.');
            }

            $policy = $calculation->policy;
            $key = $policy->type() . '|' . $policy->rateDecimal();
            if (!isset($groups[$key])) {
                $currency = $calculation->lineGross->currency();
                $groups[$key] = array(
                    'tax_type' => $policy->type(),
                    'tax_rate' => $policy->rateDecimal(),
                    'net' => JemMoney::fromMinorUnits(0, $currency),
                    'tax' => JemMoney::fromMinorUnits(0, $currency),
                    'gross' => JemMoney::fromMinorUnits(0, $currency),
                );
            }

            $groups[$key]['net'] = $groups[$key]['net']->plus($calculation->lineNet);
            $groups[$key]['tax'] = $groups[$key]['tax']->plus($calculation->lineTax);
            $groups[$key]['gross'] = $groups[$key]['gross']->plus($calculation->lineGross);
        }

        ksort($groups);

        return $groups;
    }

    private static function split(JemMoney $entered, JemTaxPolicy $policy): array
    {
        $rate = $policy->rateBasisPoints();
        $currency = $entered->currency();

        if ($rate === 0) {
            $zero = JemMoney::fromMinorUnits(0, $currency);

            return array($entered, $zero, $entered);
        }

        if ($policy->priceIncludesTax()) {
            $netMinor = self::multiplyDivideRounded(
                $entered->minorUnits(),
                self::PERCENT_FACTOR,
                self::PERCENT_FACTOR + $rate
            );
            $net = JemMoney::fromMinorUnits($netMinor, $currency);

            return array($net, $entered->minus($net), $entered);
        }

        $taxMinor = self::multiplyDivideRounded($entered->minorUnits(), $rate, self::PERCENT_FACTOR);
        $tax = JemMoney::fromMinorUnits($taxMinor, $currency);

        return array($entered, $tax, $entered->plus($tax));
    }

    /**
     * Round value * multiplier / divisor half away from zero without creating
     * an overflowing intermediate product.
     */
    private static function multiplyDivideRounded(int $value, int $multiplier, int $divisor): int
    {
        if ($multiplier < 0 || $divisor < 1 || $multiplier > $divisor) {
            throw new InvalidArgumentException('Unsupported monetary ratio.');
        }

        $negative = $value < 0;
        $absolute = abs($value);
        $whole = intdiv($absolute, $divisor) * $multiplier;
        $remainderProduct = ($absolute % $divisor) * $multiplier;
        $fraction = intdiv($remainderProduct + intdiv($divisor, 2), $divisor);
        $result = $whole + $fraction;

        return $negative ? -$result : $result;
    }
}
