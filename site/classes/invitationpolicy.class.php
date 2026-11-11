<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** Invitation capabilities and organiser-pool arithmetic without transport effects. */
final class JemInvitationPolicy
{
    /** @return string A fresh 256-bit invitation capability, unrelated to ticket locators. */
    public static function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** @param string $token Untrusted URL capability. @return string Storage/lookup digest. */
    public static function digest(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new DomainException('COM_JEM_INV_INVALID_LINK');
        }
        return hash('sha256', $token);
    }

    /** @param object $row Current invitation. @param string $now UTC timestamp. @return bool */
    public static function expired(object $row, string $now): bool
    {
        return !empty($row->invitation_expires) && $row->invitation_expires <= $now;
    }

    /**
     * Derive disjoint current pool quantities; confirmed origin is a subset of places.
     * @param object $row Current registration with invitation metadata.
     * @return array{assigned:int,confirmed:int} Pool allocation, never historical totals.
     */
    public static function allocation(object $row): array
    {
        $quantity = max(0, (int) ($row->invitation_quantity ?? 0));
        if (($row->invitation_kind ?? '') !== 'reserved') {
            return ['assigned' => 0, 'confirmed' => 0];
        }
        return [
            'assigned' => (int) $row->status === 0 && empty($row->invitation_revoked) ? $quantity : 0,
            'confirmed' => (int) $row->status === 1 && empty($row->waiting)
                ? min($quantity, max(0, (int) $row->places)) : 0,
        ];
    }

    /**
     * Calculate the compatible unconfirmed pool after a registration transition.
     * @param int $reserved Current event.reservedplaces.
     * @param object|null $before Stored registration.
     * @param object $after Proposed registration.
     * @param int $othersAssigned Pending holds owned by other invitations.
     * @return int New unconfirmed organiser pool.
     */
    public static function poolAfter(int $reserved, ?object $before, object $after, int $othersAssigned): int
    {
        $old = $before ? self::allocation($before) : ['assigned' => 0, 'confirmed' => 0];
        $new = self::allocation($after);
        $remaining = $reserved + $old['confirmed'] - $new['confirmed'];
        if ($remaining < $othersAssigned + $new['assigned']) {
            throw new DomainException('COM_JEM_INV_NOT_ENOUGH_RESERVED');
        }
        return $remaining;
    }

    /** @param string $email Entered recipient address. @return string Normalised unique key. */
    public static function email(string $email): string
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('COM_JEM_INV_INVALID_EMAIL');
        }
        return $email;
    }

    /**
     * Preserve the quoted amount as provenance while making the invitation complimentary.
     * @param array $quote Authoritative pricing quote.
     * @return array Quote whose payable admission lines and totals are zero.
     */
    public static function complimentary(array $quote): array
    {
        foreach ($quote['lines'] as &$line) {
            $line['conditions']['invitation_original_unit_gross'] = $line['unit_gross'];
            foreach (['unit_net', 'unit_tax', 'unit_gross', 'line_net', 'line_tax', 'line_gross'] as $name) {
                $line[$name] = '0.00';
            }
        }
        unset($line);
        foreach (['subtotal_net', 'tax_total', 'grand_total'] as $name) {
            $quote[$name] = '0.00';
        }
        return $quote;
    }
}
