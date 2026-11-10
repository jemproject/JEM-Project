<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/helpers/mapdate.php';

use Joomla\Component\Jem\Site\Helper\JemMapDateHelper;

/** Read-only filter and aggregation rules shared by both management views. */
final class JemRegistrationStatus
{
    /** @var array<string,string> Filter names mapped to event/venue properties. */
    public const LOCATIONS = ['country' => 'country', 'region' => 'region', 'county' => 'state', 'city' => 'city'];

    /**
     * Resolve trusted defaults and visible request filters; hidden filters ignore input.
     * @param array $options Menu configuration.
     * @param array $request Filter input only.
     * @param DateTimeImmutable $now Site-local current time.
     * @param int $weekStart First weekday.
     * @return array Normalised selections, visibility and civil date range.
     */
    public static function filters(array $options, array $request, DateTimeImmutable $now, int $weekStart): array
    {
        $filters = ['show' => []];
        foreach (['date', 'category', 'venue', 'country', 'region', 'county', 'city', 'publication', 'event_status', 'search'] as $name) {
            $filters['show'][$name] = (bool) ($options['show_' . $name . '_filter'] ?? 1);
            $default = $options['default_' . $name] ?? '';
            if ($name === 'publication') {
                $default = $options['default_publication'] ?? '1';
            }
            $value = $filters['show'][$name] ? ($request[$name] ?? $default) : $default;
            $filters[$name] = is_scalar($value) ? trim((string) $value) : '';
        }
        $mode = $options['date_filter_default'] ?? 'upcoming';
        $mode = $filters['show']['date'] ? ($request['mode'] ?? $mode) : $mode;
        $mode = is_string($mode) ? $mode : 'upcoming';
        if ($mode === 'upcoming') {
            $period = ['mode' => 'upcoming', 'date' => '', 'start' => $now->format('Y-m-d'), 'end' => null, 'unit' => 'day'];
        } else {
            $period = JemMapDateHelper::resolve(
                $mode,
                $filters['date'],
                $now,
                $weekStart,
                $filters['show']['date'] ? (int) ($request['step'] ?? 0) : 0,
                $filters['show']['date'] ? (int) ($request['jump'] ?? 0) : 0
            );
        }
        $filters = array_merge($filters, $period);
        $filters['category'] = ctype_digit($filters['category']) ? (int) $filters['category'] : 0;
        $filters['venue'] = ctype_digit($filters['venue']) ? (int) $filters['venue'] : 0;
        $filters['country'] = $filters['country'] === '0' ? '' : $filters['country'];
        $filters['publication'] = in_array($filters['publication'], ['all', '1', '0', '2', '-2'], true)
            ? $filters['publication'] : '1';
        $filters['event_status'] = in_array($filters['event_status'], ['scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online'], true)
            ? $filters['event_status'] : '';
        $filters['search'] = mb_substr($filters['search'], 0, 200);
        return $filters;
    }

    /**
     * Test an event against the effective filters without discarding ongoing events.
     * @param object $event Authorised event metadata, including category IDs.
     * @param array $filters Effective filters.
     * @return bool Whether the event contributes to this report.
     */
    public static function matches(object $event, array $filters): bool
    {
        if ($filters['publication'] !== 'all' && (int) $event->published !== (int) $filters['publication']) {
            return false;
        }
        if ($filters['event_status'] !== '' && $event->event_status !== $filters['event_status']) {
            return false;
        }
        if ($filters['venue'] > 0 && (int) $event->locid !== $filters['venue']) {
            return false;
        }
        if ($filters['category'] > 0 && !in_array($filters['category'], $event->categories ?? [], true)) {
            return false;
        }
        foreach (self::LOCATIONS as $name => $property) {
            if ($filters[$name] !== '' && (string) ($event->$property ?? '') !== $filters[$name]) {
                return false;
            }
        }
        if ($filters['search'] !== '' && mb_stripos((string) $event->title, $filters['search']) === false) {
            return false;
        }
        $start = (string) ($event->dates ?? '');
        $end = (string) ($event->enddates ?: $start);
        if ($filters['start'] !== null && ($start === '' || $end < $filters['start'])) {
            return false;
        }
        return $filters['end'] === null || ($start !== '' && $start <= $filters['end']);
    }

    /**
     * Find matching groups and their permitted ancestors; never include hidden siblings.
     * @param array<int,object> $events Authorised metadata indexed by ID.
     * @param array $filters Effective filters.
     * @return array<int,array<int,bool>> Group members (true = contributes, false = context).
     */
    public static function groups(array $events, array $filters): array
    {
        $groups = [];
        foreach ($events as $id => $event) {
            if (!self::matches($event, $filters)) {
                continue;
            }
            $chain = [$id => true];
            $root = $id;
            while (!empty($events[$root]->parent_event_id)) {
                $parent = (int) $events[$root]->parent_event_id;
                if (isset($chain[$parent])) {
                    $root = min(array_keys($chain));
                    break;
                }
                if (!isset($events[$parent])) {
                    break;
                }
                $chain[$parent] = false;
                $root = $parent;
            }
            foreach ($chain as $member => $matches) {
                $groups[$root][$member] = ($groups[$root][$member] ?? false) || $matches;
            }
        }
        return $groups;
    }

    /**
     * Count cancellation movements without confusing historical quantity with occupancy.
     * @param object $history A registration transition (logical statuses).
     * @return int Cancelled places; not negative responses or deleted historical rows.
     */
    public static function cancelled(object $history): int
    {
        if (!in_array((int) $history->old_status, [1, 2], true) || $history->old_status === null) {
            return 0;
        }
        if ($history->action === 'cancelled' && (int) $history->new_status === -1) {
            return max(0, (int) $history->old_places);
        }
        if (in_array($history->action, ['places_changed', 'order_modified'], true)
            && (int) $history->new_status === (int) $history->old_status) {
            return max(0, (int) $history->old_places - (int) $history->new_places);
        }
        return 0;
    }

    /**
     * Calculate current occupancy and constrain each pool by the shared event limit.
     * @param object $event Event plus aggregated registration counters.
     * @param array $pools Independent capacity breakdowns, not additional bookings.
     * @return array Totals and bounded pool rows; null capacity means unbounded.
     */
    public static function totals(object $event, array $pools): array
    {
        $capacity = (int) $event->maxplaces > 0 ? (int) $event->maxplaces : null;
        $reserved = max(0, (int) $event->reservedplaces);
        $confirmed = max(0, (int) ($event->confirmed ?? 0));
        $poolCapacity = 0;
        $poolFree = 0;
        $boundedPools = $pools !== [];
        foreach ($pools as $pool) {
            $boundedPools = $boundedPools && $pool['capacity'] !== null;
            $poolCapacity += (int) $pool['capacity'];
            $poolFree += $pool['capacity'] === null ? 0 : max(0, $pool['capacity'] - $pool['confirmed']);
        }
        if ($boundedPools) {
            $capacity = $capacity === null ? $poolCapacity : min($capacity, $poolCapacity);
        }
        $free = $capacity === null ? null : max(0, $capacity - $reserved - $confirmed);
        if ($boundedPools) {
            $free = min($free, $poolFree);
        }
        foreach ($pools as &$pool) {
            $remaining = $pool['capacity'] === null ? null : max(0, $pool['capacity'] - $pool['confirmed']);
            $pool['free'] = $free === null ? $remaining : ($remaining === null ? $free : min($free, $remaining));
        }
        unset($pool);
        return [
            'capacity' => $capacity, 'reserved' => $reserved, 'confirmed' => $confirmed,
            'waiting' => max(0, (int) ($event->waiting ?? 0)), 'invited' => max(0, (int) ($event->invited ?? 0)),
            'free' => $free, 'cancelled' => max(0, (int) ($event->cancelled ?? 0)), 'pools' => $pools,
            'unknown' => ($event->capacity_mode ?? 'classic') === 'areas' && !$pools,
        ];
    }
}
