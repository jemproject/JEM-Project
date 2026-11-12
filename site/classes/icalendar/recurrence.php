<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Jem\Calendar;

use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use OverflowException;

/**
 * JEM Calendar: bounded event recurrence calculation, independent of Joomla.
 *
 * Implements the supported RRULE subset from RFC 5545 sections 3.3.10 and 3.8.5.3.
 * @see https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.10
 * @see https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.5.3
 */
final class RecurrenceRule
{
    private const DAYS = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
    private const LIMIT = 50000;

    /**
     * Generate ordered wall-clock dates; the importer owns COUNT, exceptions and timezone conversion.
     *
     * @param array $parts Rule parts, without COUNT/UNTIL/DTSTART.
     * @param DateTimeImmutable $start Wall-clock start represented in UTC.
     * @param DateTimeImmutable $end Inclusive wall-clock expansion boundary in UTC.
     * @param callable $validLocal Reject nonexistent local times before applying BYSETPOS.
     * @param int &$visited Shared work budget across the calendar.
     * @return Generator<DateTimeImmutable> Valid candidates in chronological order.
     * @throws InvalidArgumentException Unsupported rule syntax.
     * @throws OverflowException Excessive expansion.
     */
    public static function dates(array $parts, DateTimeImmutable $start, DateTimeImmutable $end, callable $validLocal, int &$visited): Generator
    {
        $rule = self::validate($parts);
        $frequency = $rule['FREQ'];
        $step = $rule['INTERVAL'];
        $period = $start->setTime(0, 0);
        if ($frequency === 'WEEKLY') {
            $period = $period->modify('-' . (((int) $period->format('N') - $rule['WKST'] + 7) % 7) . ' days');
        } elseif ($frequency === 'MONTHLY') {
            $period = $period->modify('first day of this month');
        } elseif ($frequency === 'YEARLY') {
            $period = $period->setDate((int) $period->format('Y'), 1, 1);
        }
        $unit = ['DAILY' => 'day', 'WEEKLY' => 'week', 'MONTHLY' => 'month', 'YEARLY' => 'year'][$frequency];
        $hours = $rule['BYHOUR'] ?? [(int) $start->format('H')];
        $minutes = $rule['BYMINUTE'] ?? [(int) $start->format('i')];
        $seconds = $rule['BYSECOND'] ?? [(int) $start->format('s')];

        while ($period <= $end) {
            $next = $period->modify('+1 ' . $unit);
            $candidates = [];
            for ($day = $period; $day < $next; $day = $day->modify('+1 day')) {
                self::visit($visited);
                if (!self::matchesDay($day, $start, $rule)) {
                    continue;
                }
                foreach ($hours as $hour) {
                    foreach ($minutes as $minute) {
                        foreach ($seconds as $second) {
                            self::visit($visited);
                            $candidate = $day->setTime($hour, $minute, $second);
                            if (!$validLocal($candidate)) {
                                continue;
                            }
                            if (isset($rule['BYSETPOS'])) {
                                $candidates[] = $candidate;
                            } elseif ($candidate >= $start && $candidate <= $end) {
                                yield $candidate;
                            }
                        }
                    }
                }
            }
            if (isset($rule['BYSETPOS'])) {
                // Positions refer to the complete frequency period, before DTSTART/UNTIL clipping.
                $selected = [];
                foreach ($rule['BYSETPOS'] as $position) {
                    $index = $position > 0 ? $position - 1 : count($candidates) + $position;
                    if (isset($candidates[$index])) {
                        $selected[$index] = $candidates[$index];
                    }
                }
                ksort($selected, SORT_NUMERIC);
                foreach ($selected as $candidate) {
                    if ($candidate >= $start && $candidate <= $end) {
                        yield $candidate;
                    }
                }
            }
            $period = $period->modify('+' . $step . ' ' . $unit);
        }
    }

    /**
     * Validate the supported daily-to-yearly RFC grammar and cross-part restrictions.
     *
     * @param array $parts Raw uppercase RRULE parts.
     * @return array Typed rule with sorted, unique selectors and explicit defaults.
     */
    private static function validate(array $parts): array
    {
        $ranges = ['BYMONTH' => [1, 12], 'BYMONTHDAY' => [-31, 31], 'BYYEARDAY' => [-366, 366],
            'BYWEEKNO' => [-53, 53], 'BYSETPOS' => [-366, 366], 'BYHOUR' => [0, 23],
            'BYMINUTE' => [0, 59], 'BYSECOND' => [0, 59]];
        $allowed = array_merge(['FREQ', 'INTERVAL', 'WKST', 'BYDAY'], array_keys($ranges));
        if (array_diff(array_keys($parts), $allowed)
            || !in_array($parts['FREQ'] ?? '', ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            self::unsupported();
        }
        $rule = $parts;
        $interval = $parts['INTERVAL'] ?? '1';
        if (!preg_match('/^[0-9]{1,10}$/D', $interval) || (int) $interval < 1 || (int) $interval > 2147483647) {
            self::unsupported();
        }
        // Larger intervals cannot produce a second occurrence inside the importer's 100-year horizon.
        if ((int) $interval > self::LIMIT) {
            throw new OverflowException('Recurrence expansion limit exceeded.');
        }
        $rule['INTERVAL'] = (int) $interval;
        $rule['WKST'] = self::DAYS[$parts['WKST'] ?? 'MO'] ?? 0;
        if (!$rule['WKST']) {
            self::unsupported();
        }
        foreach ($ranges as $name => [$min, $max]) {
            if (!isset($parts[$name])) {
                continue;
            }
            $values = [];
            foreach (explode(',', $parts[$name]) as $value) {
                $pattern = $min < 0 ? '/^[+-]?[0-9]{1,3}$/D' : '/^[0-9]{1,2}$/D';
                if (!preg_match($pattern, $value) || (int) $value < $min || (int) $value > $max
                    || ($min < 0 && (int) $value === 0)) {
                    self::unsupported();
                }
                $values[] = (int) $value;
            }
            $values = array_values(array_unique($values));
            sort($values, SORT_NUMERIC);
            $rule[$name] = $values;
        }
        $frequency = $rule['FREQ'];
        if ((isset($rule['BYWEEKNO']) && $frequency !== 'YEARLY')
            || (isset($rule['BYYEARDAY']) && $frequency !== 'YEARLY')
            || (isset($rule['BYMONTHDAY']) && $frequency === 'WEEKLY')
            || (isset($rule['BYSETPOS']) && !array_intersect(array_keys($parts),
                ['BYMONTH', 'BYWEEKNO', 'BYYEARDAY', 'BYMONTHDAY', 'BYDAY', 'BYHOUR', 'BYMINUTE', 'BYSECOND']))) {
            self::unsupported();
        }
        if (isset($parts['BYDAY'])) {
            $rule['BYDAY'] = [];
            foreach (array_unique(explode(',', $parts['BYDAY'])) as $day) {
                if (!preg_match('/^([+-]?[0-9]{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/D', $day, $match)) {
                    self::unsupported();
                }
                $ordinal = $match[1] === '' ? null : (int) $match[1];
                if ($ordinal !== null && ($ordinal === 0 || abs($ordinal) > 53
                    || !in_array($frequency, ['MONTHLY', 'YEARLY'], true) || isset($rule['BYWEEKNO']))) {
                    self::unsupported();
                }
                $rule['BYDAY'][] = [$ordinal, self::DAYS[$match[2]]];
            }
        }
        return $rule;
    }

    /**
     * Apply date selectors and DTSTART defaults within the current frequency period.
     *
     * @param DateTimeImmutable $day Candidate calendar day in UTC wall time.
     * @param DateTimeImmutable $start Original wall-clock start.
     * @param array $rule Validated rule.
     * @return bool Whether the calendar day belongs to the recurrence set.
     */
    private static function matchesDay(DateTimeImmutable $day, DateTimeImmutable $start, array $rule): bool
    {
        $month = (int) $day->format('n');
        $monthDay = (int) $day->format('j');
        $weekDay = (int) $day->format('N');
        $yearDay = (int) $day->format('z') + 1;
        $yearLength = 365 + (int) $day->format('L');
        if ((isset($rule['BYMONTH']) && !in_array($month, $rule['BYMONTH'], true))
            || (isset($rule['BYMONTHDAY']) && !self::ordinalMatches($monthDay, (int) $day->format('t'), $rule['BYMONTHDAY']))
            || (isset($rule['BYYEARDAY']) && !self::ordinalMatches($yearDay, $yearLength, $rule['BYYEARDAY']))) {
            return false;
        }
        if (isset($rule['BYWEEKNO'])) {
            [$week, $weeks] = self::weekNumber($day, $rule['WKST']);
            if (!self::ordinalMatches($week, $weeks, $rule['BYWEEKNO'])) {
                return false;
            }
        }
        if (isset($rule['BYDAY'])) {
            $matches = false;
            foreach ($rule['BYDAY'] as [$ordinal, $weekday]) {
                if ($weekday !== $weekDay) {
                    continue;
                }
                $position = $rule['FREQ'] === 'MONTHLY' || isset($rule['BYMONTH']) ? $monthDay : $yearDay;
                $length = $rule['FREQ'] === 'MONTHLY' || isset($rule['BYMONTH']) ? (int) $day->format('t') : $yearLength;
                if ($ordinal === null || $ordinal === intdiv($position - 1, 7) + 1
                    || $ordinal === -(intdiv($length - $position, 7) + 1)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                return false;
            }
        }
        if ($rule['FREQ'] === 'WEEKLY' && !isset($rule['BYDAY'])) {
            return $weekDay === (int) $start->format('N');
        }
        $hasDay = isset($rule['BYDAY']) || isset($rule['BYMONTHDAY']) || isset($rule['BYYEARDAY']) || isset($rule['BYWEEKNO']);
        if (in_array($rule['FREQ'], ['MONTHLY', 'YEARLY'], true) && !$hasDay && $monthDay !== (int) $start->format('j')) {
            return false;
        }
        return $rule['FREQ'] !== 'YEARLY' || $hasDay || isset($rule['BYMONTH']) || $month === (int) $start->format('n');
    }

    /** @param int $value One-based index. @param int $length Period length. @param array $list Signed selectors. @return bool A positive or negative selector matches. */
    private static function ordinalMatches(int $value, int $length, array $list): bool
    {
        return in_array($value, $list, true) || in_array($value - $length - 1, $list, true);
    }

    /** @param DateTimeImmutable $day Day to classify. @param int $weekStart ISO weekday of WKST. @return array{int,int} Week number and number of weeks in its week-year. */
    private static function weekNumber(DateTimeImmutable $day, int $weekStart): array
    {
        $year = (int) $day->format('Y');
        $first = self::firstWeek($day, $year, $weekStart);
        if ($day < $first) {
            --$year;
            $first = self::firstWeek($day, $year, $weekStart);
        }
        $next = self::firstWeek($day, $year + 1, $weekStart);
        if ($day >= $next) {
            ++$year;
            $first = $next;
            $next = self::firstWeek($day, $year + 1, $weekStart);
        }
        return [intdiv((int) $first->diff($day)->days, 7) + 1, intdiv((int) $first->diff($next)->days, 7)];
    }

    /** @param DateTimeImmutable $day UTC base. @param int $year Week-year. @param int $weekStart ISO weekday. @return DateTimeImmutable Start of the first week containing at least four days of the year. */
    private static function firstWeek(DateTimeImmutable $day, int $year, int $weekStart): DateTimeImmutable
    {
        $fourth = $day->setDate($year, 1, 4);
        return $fourth->modify('-' . (((int) $fourth->format('N') - $weekStart + 7) % 7) . ' days');
    }

    /** @param int &$visited Shared candidate-check count. @return void Stop pathological expansion before excessive work or allocation. */
    private static function visit(int &$visited): void
    {
        if (++$visited > self::LIMIT) {
            throw new OverflowException('Recurrence expansion limit exceeded.');
        }
    }

    /** @return never Reject unsupported grammar; the application translates this exception for its users. */
    private static function unsupported(): never
    {
        throw new InvalidArgumentException('Invalid or unsupported RFC 5545 recurrence rule.');
    }
}
