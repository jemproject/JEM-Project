<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

final class JemEventSeriesSchedule
{
    public static function parse($raw, $required = true, $maximum = 250)
    {
        if (!$required && trim((string) $raw) === '') {
            return array();
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || count($decoded) > (int) $maximum) {
            throw new InvalidArgumentException('invalid');
        }

        $rows = array();
        $seen = array();
        $seenEventIds = array();
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = trim((string) ($row['date'] ?? ''));
            $time = trim((string) ($row['time'] ?? ''));
            $endDate = trim((string) ($row['end_date'] ?? ''));
            $endTime = trim((string) ($row['end_time'] ?? ''));
            if ($date === '' && $time === '' && $endDate === '' && $endTime === '') {
                continue;
            }
            if (!self::validDate($date) || ($endDate !== '' && !self::validDate($endDate))
                || ($time !== '' && !self::validTime($time)) || ($endTime !== '' && !self::validTime($endTime))) {
                throw new InvalidArgumentException('invalid');
            }

            $effectiveEndDate = $endDate !== '' ? $endDate : $date;
            $startValue = $date . ' ' . ($time !== '' ? $time : '00:00');
            $endValue = $effectiveEndDate . ' ' . ($endTime !== '' ? $endTime : ($time !== '' ? $time : '23:59'));
            if ($endValue < $startValue) {
                throw new InvalidArgumentException('end_before_start');
            }

            $key = $date . '|' . $time . '|' . $endDate . '|' . $endTime;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('duplicate');
            }
            $seen[$key] = true;
            $eventId = max(0, (int) ($row['event_id'] ?? 0));
            if ($eventId > 0 && isset($seenEventIds[$eventId])) {
                throw new InvalidArgumentException('duplicate');
            }
            if ($eventId > 0) {
                $seenEventIds[$eventId] = true;
            }
            $rows[] = array(
                'event_id' => $eventId,
                'date' => $date,
                'time' => $time,
                'end_date' => $endDate,
                'end_time' => $endTime,
            );
        }

        usort($rows, static function ($left, $right) {
            return strcmp($left['date'] . ' ' . $left['time'], $right['date'] . ' ' . $right['time']);
        });
        if ($required && count($rows) < 1) {
            throw new InvalidArgumentException('minimum');
        }

        return $rows;
    }

    public static function apply(array &$event, array $row)
    {
        $event['dates'] = $row['date'];
        $event['times'] = $row['time'] !== '' ? $row['time'] : null;
        $event['enddates'] = $row['end_date'] !== '' ? $row['end_date'] : null;
        $event['endtimes'] = $row['end_time'] !== '' ? $row['end_time'] : null;
    }

    public static function combine(array $primary, array $additional, $requirePrimaryFirst = false, $maximum = 250)
    {
        if ($requirePrimaryFirst) {
            $primaryStart = (string) ($primary['date'] ?? '') . ' '
                . (!empty($primary['time']) ? (string) $primary['time'] : '00:00');
            foreach ($additional as $row) {
                $additionalStart = (string) ($row['date'] ?? '') . ' '
                    . (!empty($row['time']) ? (string) $row['time'] : '00:00');
                if ($additionalStart <= $primaryStart) {
                    throw new InvalidArgumentException('before_primary');
                }
            }
        }

        return self::parse(json_encode(array_merge(array($primary), $additional)), true, $maximum);
    }

    private static function validDate($value)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private static function validTime($value)
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $value) === 1;
    }
}
