<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Normalises and validates recurrence rule values shared by forms and cleanup.
 */
final class JemRecurrenceValidator
{
    private const WEEKDAY_CODES = array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU');

    /**
     * @param   array|string|null  $days  Comma-separated weekday codes or an array of codes.
     *
     * @return  array|false  Normalised unique codes, or false for an invalid selection.
     */
    public static function normaliseWeekdays($days)
    {
        if (!is_array($days)) {
            $days = explode(',', (string) $days);
        }

        $normalised = array();

        foreach ($days as $day) {
            if (!is_string($day)) {
                return false;
            }
            $day = strtoupper(trim((string) $day));

            if ($day === '' || !in_array($day, self::WEEKDAY_CODES, true)) {
                return false;
            }

            if (!in_array($day, $normalised, true)) {
                $normalised[] = $day;
            }
        }

        return $normalised ?: false;
    }

    /**
     * Explicit weekly rules use W:1,3,5 (ISO weekdays) in the existing varchar(20).
     * Unmarked legacy values must NOT activate weekly days: old Weekly ignored them.
     *
     * @return array|false|null Null means legacy; false means a malformed marked rule.
     */
    public static function weeklyDays(?string $rule)
    {
        if (!str_starts_with((string) $rule, 'W:')) {
            return null;
        }

        if (!preg_match('/^W:[1-7](?:,[1-7])*$/D', $rule)) {
            return false;
        }

        $days = array_unique(explode(',', substr($rule, 2)));
        sort($days, SORT_NUMERIC);

        return array_map(static fn ($day) => self::WEEKDAY_CODES[(int) $day - 1], $days);
    }

    /** @return string|false */
    public static function encodeWeeklyDays($days)
    {
        $days = self::normaliseWeekdays($days);
        if ($days === false) {
            return false;
        }

        $numbers = array_map(static fn ($day) => array_search($day, self::WEEKDAY_CODES, true) + 1, $days);
        sort($numbers, SORT_NUMERIC);

        return 'W:' . implode(',', $numbers);
    }

    /** Calendar arithmetic, independent of server timezone and daylight-saving offsets. */
    public static function nextWeeklyOccurrence(array $row)
    {
        $days = self::weeklyDays($row['recurrence_byday'] ?? null);
        $interval = filter_var($row['recurrence_number'] ?? null, FILTER_VALIDATE_INT);
        $start = self::calendarDate($row['dates'] ?? '');
        if (!$days || !$start || $interval < 1 || $interval > 52) {
            return false;
        }

        $weekday = (int) $start->format('N');
        $numbers = array_map(static fn ($day) => array_search($day, self::WEEKDAY_CODES, true) + 1, $days);
        $offset = 7 * $interval + $numbers[0] - $weekday;
        foreach ($numbers as $number) {
            if ($number > $weekday) {
                $offset = $number - $weekday;
                break;
            }
        }

        $next = $start->modify('+' . $offset . ' days');
        if (!empty($row['enddates'])) {
            $end = self::calendarDate($row['enddates']);
            if (!$end || $end < $start) {
                return false;
            }
            $row['enddates'] = $next->add($start->diff($end))->format('Y-m-d');
        }
        $row['dates'] = $next->format('Y-m-d');

        return $row;
    }

    private static function calendarDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
