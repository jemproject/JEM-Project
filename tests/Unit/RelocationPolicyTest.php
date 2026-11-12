<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationrelocation.class.php';

/** Economic decisions do not create payment/refund ledger entries. */
final class RelocationPolicyTest extends TestCase
{
    /** @return iterable Representative paid, unpaid and partially paid agreements. */
    public static function decisions(): iterable
    {
        yield 'unpaid cheaper' => [2000, 1000, 0, 'standard', false, 1000, 1000, 0];
        yield 'paid cheaper' => [2000, 1000, 2000, 'standard', false, 1000, 0, 1000];
        yield 'paid dearer' => [2000, 3000, 2000, 'standard', false, 3000, 1000, 0];
        yield 'promotion preserves old debt' => [2000, 3000, 500, 'promotion', false, 2000, 1500, 0];
        yield 'consolidation supplement' => [2000, 3000, 2000, 'consolidation_supplement', false, 2000, 0, 0];
        yield 'agreement retains actual surplus' => [3000, 1000, 2000, 'agreement', true, 2000, 0, 0];
        yield 'consolidation refund' => [2000, 1000, 2000, 'consolidation_refund', true, 2000, 0, 0];
        yield 'free' => [0, 0, 0, 'standard', false, 0, 0, 0];
    }

    #[DataProvider('decisions')]
    public function testFinancialDecision(int $old, int $new, int $paid, string $choice, bool $accepted, int $total, int $due, int $refund): void
    {
        $result = JemRelocationPolicy::resolve($old, $new, $paid, $choice, $accepted);
        self::assertSame($total, $result['total']);
        self::assertSame($due, $result['due']);
        self::assertSame($refund, $result['refund']);
        self::assertSame($paid, $result['paid']);
    }

    /** @return iterable Invalid consent, adjustment direction or payment assumptions. */
    public static function rejected(): iterable
    {
        yield [2000, 1000, 2000, 'agreement', false];
        yield [2000, 1000, 0, 'agreement', true];
        yield [2000, 1000, 500, 'consolidation_refund', true];
        yield [2000, 1000, 3000, 'agreement', true];
        yield [2000, 1000, 0, 'promotion', false];
        yield [2000, 2000, 0, 'promotion', false];
        yield [2000, 1000, 0, 'fake_payment', false];
    }

    #[DataProvider('rejected')]
    public function testRejectsInvalidDecisions(int $old, int $new, int $paid, string $choice, bool $accepted): void
    {
        $this->expectException(DomainException::class);
        JemRelocationPolicy::resolve($old, $new, $paid, $choice, $accepted);
    }

    public function testExactLargeProportionsAndRounding(): void
    {
        self::assertSame(3333333333333, JemRelocationPolicy::share(9999999999999, 1, 3));
        self::assertSame(5000000000000, JemRelocationPolicy::share(9999999999999, 1, 2));
        self::assertSame(9999999999998, JemRelocationPolicy::share(9999999999999, 9999999999998, 9999999999999));
        self::assertSame(0, JemRelocationPolicy::share(0, 1, 1));
    }

    public function testMixedTaxAdjustmentKeepsExactTotals(): void
    {
        $quote = ['currency' => 'EUR', 'subtotal_net' => '20.00', 'tax_total' => '3.10', 'grand_total' => '23.10',
            'lines' => [
                ['tax_type' => 'standard', 'tax_rate' => '21.00', 'tax_code' => 'VAT21', 'tax_name' => 'VAT', 'line_gross' => '12.10'],
                ['tax_type' => 'reduced', 'tax_rate' => '10.00', 'tax_code' => 'VAT10', 'tax_name' => 'VAT', 'line_gross' => '11.00'],
            ]];
        $adjusted = JemRegistrationRelocation::adjustQuote($quote, -310, 'promotion');
        self::assertSame('20.00', $adjusted['grand_total']);
        $net = JemMoney::fromDecimal($adjusted['subtotal_net'], 'EUR');
        self::assertSame('20.00', $net->plus(JemMoney::fromDecimal($adjusted['tax_total'], 'EUR'))->decimal());
        $sum = 0;
        foreach ($adjusted['adjustment_items'] as $item) {
            $sum += JemMoney::fromDecimal($item->line_gross, 'EUR')->minorUnits();
        }
        self::assertSame(-310, $sum);
    }
}
