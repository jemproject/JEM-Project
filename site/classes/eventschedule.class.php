<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** A programme inside one event, not a recurrence or a separate registration. */
final class JemEventSchedule
{
    public const MAX_DAYS = 366;
    public const MAX_SLOTS = 8;

    public static function parameters($value): array
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public static function definition($event): ?array
    {
        $parameters = self::parameters($event->attribs ?? []);
        if (!array_key_exists('event_schedule', $parameters)) {
            return null;
        }
        if (!is_array($parameters['event_schedule'])) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID');
        }
        return $parameters['event_schedule'];
    }

    public static function date(string $value): DateTimeImmutable
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || substr($value, 0, 4) === '0000') {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_DATES_REQUIRED');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_DATES_REQUIRED');
        }
        return $date;
    }

    private static function time($value, bool $optional = false): ?string
    {
        if ($optional && ($value === null || $value === '')) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $value)) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID_TIME');
        }
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    public static function normalise($raw, $event): array
    {
        if (is_string($raw)) {
            if (strlen($raw) > 200000) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID');
            }
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw) || ($raw['version'] ?? null) !== 1
            || !in_array($raw['mode'] ?? null, ['daily', 'continuous', 'per_day'], true)) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID');
        }
        $result = ['version' => 1, 'mode' => $raw['mode'], 'days' => []];
        // Open-date events can keep the default selector, but cannot have a per-day programme.
        if (empty($event->dates)) {
            if ($raw['mode'] === 'per_day') {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_DATES_REQUIRED');
            }
            return $result;
        }
        $start = self::date((string) $event->dates);
        $end = self::date((string) (!empty($event->enddates) ? $event->enddates : $event->dates));
        $count = (int) $start->diff($end)->format('%r%a') + 1;
        if ($count < 1) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_DATES_REQUIRED');
        }
        if ($raw['mode'] !== 'per_day') {
            $startTime = self::time($event->times ?? null, true);
            $endTime = self::time($event->endtimes ?? null, true);
            if ($startTime === null && $endTime !== null) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID_TIME');
            }
            if ($raw['mode'] === 'daily' && $startTime !== null && $endTime !== null && $endTime < $startTime) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_SAME_DAY_REQUIRED');
            }
            if ($raw['mode'] === 'continuous' && $count === 1 && $startTime && $endTime && $endTime < $startTime) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_SAME_DAY_REQUIRED');
            }
            return $result;
        }
        if ($count > self::MAX_DAYS || !is_array($raw['days'] ?? null) || count($raw['days']) !== $count) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_DAYS_MISMATCH');
        }
        $days = [];
        $active = false;
        foreach ($raw['days'] as $day) {
            if (!is_array($day) || !is_int($day['offset'] ?? null)
                || $day['offset'] < 0 || $day['offset'] >= $count || isset($days[$day['offset']])
                || !is_bool($day['closed'] ?? null) || !is_bool($day['all_day'] ?? null)
                || !is_array($day['slots'] ?? null) || count($day['slots']) > self::MAX_SLOTS) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID');
            }
            if (($day['closed'] && $day['all_day']) || (($day['closed'] || $day['all_day']) && $day['slots'])) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID');
            }
            $slots = [];
            foreach ($day['slots'] as $slot) {
                if (!is_array($slot)) {
                    throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID');
                }
                $from = self::time($slot['start'] ?? null);
                $until = self::time($slot['end'] ?? null);
                if ($until <= $from) {
                    throw new InvalidArgumentException('COM_JEM_SCHEDULE_SAME_DAY_REQUIRED');
                }
                $slots[] = ['start' => $from, 'end' => $until];
            }
            usort($slots, static fn ($a, $b) => strcmp($a['start'], $b['start']));
            $previousEnd = null;
            foreach ($slots as $slot) {
                if ($previousEnd !== null && $slot['start'] < $previousEnd) {
                    throw new InvalidArgumentException('COM_JEM_SCHEDULE_OVERLAP');
                }
                $previousEnd = $slot['end'];
            }
            if (!$day['closed'] && !$day['all_day'] && !$slots) {
                throw new InvalidArgumentException('COM_JEM_SCHEDULE_EMPTY_DAY');
            }
            $days[$day['offset']] = ['offset' => $day['offset'], 'closed' => $day['closed'],
                'all_day' => !$day['closed'] && $day['all_day'], 'slots' => $slots];
            $active = $active || !$day['closed'];
        }
        if (!$active) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_ALL_CLOSED');
        }
        ksort($days);
        $result['days'] = array_values($days);
        return $result;
    }

    /** Validate direct table stores too (recurrence, copy and custom-series paths). */
    public static function validate($event): void
    {
        $definition = self::definition($event);
        if ($definition !== null) {
            self::normalise($definition, $event);
        }
    }

    public static function hasTimes($event): bool
    {
        $definition = self::definition($event);
        if ($definition !== null && $definition['mode'] === 'per_day') {
            foreach ($definition['days'] as $day) {
                if (!empty($day['slots'])) {
                    return true;
                }
            }
            return false;
        }
        return !empty($event->times);
    }

    /** Preserve an omitted editor value, including legacy template overrides. */
    public static function mergeSubmission(array &$data, $existing = null): void
    {
        $parameters = array_replace(self::parameters($existing->attribs ?? []), self::parameters($data['attribs'] ?? []));
        if (array_key_exists('event_schedule', $data) && $data['event_schedule'] !== '') {
            $parameters['event_schedule'] = self::normalise($data['event_schedule'], (object) $data);
        } elseif ($existing && ($stored = self::definition($existing)) !== null) {
            $parameters['event_schedule'] = $stored;
        } else {
            unset($parameters['event_schedule']);
        }
        unset($data['event_schedule']);
        $data['attribs'] = $parameters;
        self::validate((object) $data);
    }

    /** The per-day editor owns its hours; the general fields remain a compatibility envelope. */
    public static function synchroniseEnvelope($event): void
    {
        $definition = self::definition($event);
        if ($definition === null || $definition['mode'] !== 'per_day') {
            return;
        }
        $config = self::normalise($definition, $event);
        $first = $config['days'][0];
        $last = $config['days'][count($config['days']) - 1];
        $event->times = $first['slots'][0]['start'] ?? null;
        $event->endtimes = $last['slots'] ? $last['slots'][count($last['slots']) - 1]['end'] : null;
    }

    /** Match the event editor's separate hour/minute selectors, including midnight. */
    public static function editorTime(string $hour, string $minute): ?string
    {
        if ($hour === '' && $minute === '') {
            return null;
        }
        return self::time(str_pad($hour === '' ? '0' : $hour, 2, '0', STR_PAD_LEFT)
            . ':' . str_pad($minute === '' ? '0' : $minute, 2, '0', STR_PAD_LEFT));
    }

    private static function instant(string $date, string $time, DateTimeZone $zone): DateTimeImmutable
    {
        $value = new DateTimeImmutable($date . ' ' . $time, $zone);
        // PHP otherwise silently advances nonexistent local times during a DST jump.
        if ($value->format('Y-m-d H:i:s') !== $date . ' ' . $time) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_INVALID_LOCAL_TIME');
        }
        return $value;
    }

    /** Validate the entered wall-clock boundaries before UTC conversion can normalise a DST gap. */
    public static function validateLocalBounds($event, string $timezone): void
    {
        if (empty($event->dates)) {
            return;
        }

        $zone = new DateTimeZone($timezone);
        $start = self::time($event->times ?? null, true);
        $end = self::time($event->endtimes ?? null, true);

        if ($start !== null) {
            self::instant((string) $event->dates, $start, $zone);
        }

        if ($end !== null) {
            self::instant((string) (!empty($event->enddates) ? $event->enddates : $event->dates), $end, $zone);
        }
    }

    /** Session boundaries are half-open. Null/unknown end times are flagged, never invented. */
    public static function intervals($event, string $timezone = 'UTC', ?string $from = null, ?string $until = null): array
    {
        if (empty($event->dates)) {
            return [];
        }
        $definition = self::definition($event);
        $config = $definition === null ? ['mode' => 'daily'] : self::normalise($definition, $event);
        $first = self::date((string) $event->dates);
        $last = self::date((string) (!empty($event->enddates) ? $event->enddates : $event->dates));
        if ($last < $first) {
            return [];
        }
        $zone = new DateTimeZone($timezone);
        $result = [];
        $add = static function ($date, $start, $end, $key, $allDay, $endKnown, $endDate = null) use (&$result, $zone) {
            $a = self::instant($date, $start ?: '00:00:00', $zone);
            $b = $allDay ? self::instant($endDate ?: $date, '00:00:00', $zone)->modify('+1 day')
                : self::instant($endDate ?: $date, $end ?: '23:59:59', $zone);
            if ($b < $a) {
                return;
            }
            $result[] = ['start' => $a, 'end' => $b, 'key' => $key, 'all_day' => $allDay, 'end_known' => $endKnown];
        };
        $startTime = $config['mode'] === 'per_day' ? null : self::time($event->times ?? null, true);
        $endTime = $config['mode'] === 'per_day' ? null : self::time($event->endtimes ?? null, true);
        if ($config['mode'] === 'continuous') {
            $add($first->format('Y-m-d'), $startTime, $endTime, 'continuous', !$startTime, $endTime !== null, $last->format('Y-m-d'));
            return $result;
        }
        $low = $from ? max($first, self::date($from)) : $first;
        $high = $until ? min($last, self::date($until)) : $last;
        if ($high < $low) {
            return [];
        }
        // Bound expansion; a filtered calendar window can still inspect a very long daily event.
        if ((int) $low->diff($high)->days > 3660) {
            throw new InvalidArgumentException('COM_JEM_SCHEDULE_RANGE_TOO_LONG');
        }
        for ($date = $low; $date <= $high; $date = $date->modify('+1 day')) {
            $offset = (int) $first->diff($date)->days;
            $ymd = $date->format('Y-m-d');
            if ($config['mode'] === 'daily') {
                $add($ymd, $startTime, $endTime, $offset . '-0', !$startTime, $endTime !== null);
                continue;
            }
            $day = $config['days'][$offset];
            if ($day['closed']) {
                continue;
            }
            if ($day['all_day']) {
                $add($ymd, null, null, $offset . '-0', true, true);
            }
            foreach ($day['slots'] as $index => $slot) {
                $add($ymd, $slot['start'], $slot['end'], $offset . '-' . $index, false, true);
            }
        }
        return $result;
    }

    /** Ready for day views: retain the true interval and expose only its visible segment. */
    public static function onDay($event, string $day, string $timezone = 'UTC'): array
    {
        self::date($day);
        $start = new DateTimeImmutable($day, new DateTimeZone($timezone));
        $end = $start->modify('+1 day');
        $result = [];
        foreach (self::intervals($event, $timezone, $day, $day) as $interval) {
            if ($interval['start'] >= $end || $interval['end'] < $start
                || ($interval['end'] == $start && $interval['start'] != $start)) {
                continue;
            }
            $interval['visible_start'] = max($start, $interval['start']);
            $interval['visible_end'] = min($end, $interval['end']);
            $result[] = $interval;
        }
        return $result;
    }
}
