<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

require_once __DIR__ . '/ticketpolicy.class.php';

/** Exact, transport-independent financial decisions for an administrative relocation. */
final class JemRelocationPolicy
{
    /**
     * Round amount * weight / total without floating point or an overflowing product.
     * @param int $amount Non-negative minor units.
     * @param int $weight Selected share.
     * @param int $total Sum of shares.
     * @return int Rounded share, with halves rounded upwards.
     */
    public static function share(int $amount, int $weight, int $total): int
    {
        if ($amount < 0 || $weight < 0 || $total < 1 || $weight > $total || $total > intdiv(PHP_INT_MAX, 2)) {
            throw new DomainException('COM_JEM_RELOCATION_AMOUNT_INVALID');
        }
        $whole = intdiv($amount, $total) * $weight;
        $factor = $amount % $total;
        $quotient = $remainder = 0;
        foreach (str_split(decbin($weight)) as $bit) {
            $quotient *= 2;
            $remainder *= 2;
            if ($remainder >= $total) {
                $remainder -= $total;
                ++$quotient;
            }
            if ($bit === '1') {
                $remainder += $factor;
                if ($remainder >= $total) {
                    $remainder -= $total;
                    ++$quotient;
                }
            }
        }
        return $whole + $quotient + ($remainder >= $total - $remainder ? 1 : 0);
    }

    /**
     * Resolve only this change's supplement/refund; never manufacture a payment.
     * @param int $old Previous payable total in cents.
     * @param int $new Revised quoted total in cents.
     * @param int $paid Actual collected amount.
     * @param string $choice Requested adjustment resolution.
     * @param bool $accepted Recorded holder acceptance of a no-refund agreement.
     * @return array Reviewed totals and adjustment in integer cents.
     */
    public static function resolve(int $old, int $new, int $paid, string $choice, bool $accepted): array
    {
        if (min($old, $new, $paid) < 0) {
            throw new DomainException('COM_JEM_RELOCATION_AMOUNT_INVALID');
        }
        $difference = $new - $old;
        $adjustment = 0;
        if (in_array($choice, ['promotion', 'consolidation_supplement'], true)) {
            if ($difference <= 0) {
                throw new DomainException('COM_JEM_RELOCATION_CHOICE_INVALID');
            }
            $adjustment = -$difference;
        } elseif (in_array($choice, ['agreement', 'consolidation_refund'], true)) {
            // Only money already collected can be retained. Never retain an unpaid debt.
            if ($difference >= 0 || $paid <= $new || $paid > $old || !$accepted) {
                throw new DomainException('COM_JEM_RELOCATION_ACCEPT_REQUIRED');
            }
            $adjustment = $paid - $new;
        } elseif ($choice !== 'standard') {
            throw new DomainException('COM_JEM_RELOCATION_CHOICE_INVALID');
        }
        $total = $new + $adjustment;
        return ['old' => $old, 'quoted' => $new, 'difference' => $difference,
            'adjustment' => $adjustment, 'total' => $total, 'paid' => $paid,
            'due' => max(0, $total - $paid), 'refund' => max(0, $paid - $total),
            'choice' => $choice, 'accepted' => $accepted];
    }
}
