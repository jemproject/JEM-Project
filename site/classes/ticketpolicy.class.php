<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** Monetary and admission decisions without transport or database side effects. */
final class JemTicketPolicy
{
    /**
     * Convert an exact non-negative amount to minor units, rejecting rounding.
     * @param mixed $amount Decimal amount from a stored order or submitted form.
     * @return int Exact cents.
     */
    public static function cents($amount): int
    {
        if (!is_string($amount) || !preg_match('/^(0|[1-9][0-9]{0,10})(?:\.([0-9]{1,2}))?$/D', $amount, $match)) {
            throw new InvalidArgumentException('COM_JEM_TK_INVALID_AMOUNT');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    /** @param int $cents Minor units. @return string Canonical decimal amount. */
    public static function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve current order coverage; a missing priced snapshot is not free.
     * @param object $row Stored registration.
     * @param int $paid Actual recorded collection in the order currency.
     * @return array Order total, balance, pending refund and payment state.
     */
    public static function payment(object $row, int $paid): array
    {
        $classic = ($row->pricing_mode ?? 'classic') === 'classic';
        $active = (int) $row->status === 1 && !(int) $row->waiting;
        $known = $classic || ($row->grand_total !== null && preg_match('/^[A-Z]{3}$/D', (string) $row->currency));
        $total = $known ? ($classic ? 0 : self::cents((string) $row->grand_total)) : null;
        $refund = $total !== null ? max(0, $paid - ($active ? $total : 0)) : 0;
        $due = $total !== null ? max(0, $total - $paid) : null;
        $state = !$known ? 'unknown' : ($refund > 0 ? 'refund_pending'
            : ($total === 0 ? 'free' : ($due === 0 ? 'paid' : 'unpaid')));
        return ['total' => $total, 'paid' => $paid, 'due' => $due, 'refund' => $refund, 'state' => $state];
    }

    /**
     * Explain an admission block using authoritative event and booking state.
     * @param object $event Current event, not the PDF snapshot.
     * @param object $row Current reservation.
     * @param array $payment Result of payment().
     * @param int $entered Places already admitted with this reservation reference.
     * @return string Empty when admission is allowed, otherwise a language key.
     */
    public static function blocked(object $event, object $row, array $payment, int $entered): string
    {
        if ((int) $event->published !== 1 || in_array($event->event_status, ['cancelled', 'postponed'], true)) {
            return 'COM_JEM_TK_EVENT_CLOSED';
        }
        if ((int) $row->status !== 1 || (int) $row->waiting || (int) $row->places < 1) {
            return 'COM_JEM_TK_NOT_CONFIRMED';
        }
        if ($payment['due'] === null || $payment['due'] > 0) {
            return 'COM_JEM_TK_PAYMENT_REQUIRED';
        }
        return $entered >= (int) $row->places ? 'COM_JEM_TK_USED' : '';
    }
}
