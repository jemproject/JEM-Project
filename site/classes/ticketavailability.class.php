<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Shared informational availability policy; never authorizes or changes a booking. */
final class JemTicketAvailability
{
    /**
     * Normalize the shared automatic warning rule, preserving the legacy places default.
     *
     * @param object $settings JEM settings.
     *
     * @return array{mode: string, threshold: int} Validated unit and exclusive threshold.
     */
    public static function rule($settings): array
    {
        $mode = ($settings->module_status_last_places_mode ?? 'places') === 'percent' ? 'percent' : 'places';

        return array(
            'mode' => $mode,
            'threshold' => min(
                $mode === 'percent' ? 100 : 9999,
                max(1, (int) ($settings->module_status_last_places_threshold ?? 10))
            ),
        );
    }

    /**
     * Resolve the event-wide label from explicit availability and known seat totals.
     *
     * Capacity is the event's booking ceiling, not a sum of ticket tariffs sharing seats.
     * Waiting registrations do not consume confirmed places. Unknown totals must not be
     * treated as zero bookings. Per-area and per-ticket booking validation is unchanged.
     *
     * @param object $event Event with optional booked/regCount and reservedplaces/reserved totals.
     * @param object $settings JEM settings containing the shared last-places rule.
     * @param bool $registrationOpen Whether automatic low-stock messaging is appropriate.
     *
     * @return string instock, limited, preorder, soldout or waitinglist.
     */
    public static function resolve($event, $settings, bool $registrationOpen): string
    {
        $stored = (string) ($event->ticket_availability ?? 'instock');
        $stored = in_array($stored, array('instock', 'limited', 'preorder', 'soldout'), true) ? $stored : 'instock';
        if ($stored === 'preorder' || $stored === 'soldout') {
            return $stored;
        }

        $capacity = max(0, (int) ($event->maxplaces ?? 0));
        $booked = $event->booked ?? $event->regCount ?? null;
        if ($capacity === 0 || $booked === null) {
            return $stored;
        }

        $reserved = max(0, (int) ($event->reservedplaces ?? $event->reserved ?? 0));
        $remaining = max(0, $capacity - max(0, (int) $booked) - $reserved);
        if ($remaining === 0) {
            return !empty($event->waitinglist) ? 'waitinglist' : 'soldout';
        }
        if ($stored === 'limited' || !$registrationOpen) {
            return $stored;
        }

        $rule = self::rule($settings);
        $lastPlaces = $rule['mode'] === 'percent'
            ? $remaining * 100 < $capacity * $rule['threshold']
            : $remaining < $rule['threshold'];

        return $lastPlaces ? 'limited' : 'instock';
    }
}
