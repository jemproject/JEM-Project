<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Jem\Calendar\RecurrenceRule;
use Jem\Calendar\DateTimeValue;

if (!class_exists(DateTimeValue::class, false)) {
    require_once JPATH_SITE . '/components/com_jem/classes/icalendar/datetime.php';
}
if (!class_exists(RecurrenceRule::class, false)) {
    require_once JPATH_SITE . '/components/com_jem/classes/icalendar/recurrence.php';
}

/**
 * Import adapter for the JEM Calendar library and Joomla's translated validation messages.
 *
 * RFC 5545 recurrence set: DTSTART union RRULE union RDATE, minus EXDATE,
 * with RECURRENCE-ID overrides. Each result is a concrete JEM event; export
 * writes separate VEVENTs, not a reconstructed RRULE. Compare occurrence
 * dates/durations on round trips, not source bytes, UIDs or property order.
 * @see https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.5.3
 */
final class JemImportIcsHelper
{
    private const MAX_CANDIDATES = 50000;
    private const MAX_YEARS = 100;

    /**
     * Expand parsed VEVENTs before ordinary import validation and persistence.
     *
     * @param array $events Source VEVENT properties, including exceptions.
     * @param array $options Optional inclusive ics_from/ics_to dates.
     * @param DateTimeZone $siteZone Zone for floating times and the selected window.
     * @param callable $resolveZone Existing JEM timezone resolver.
     * @return array Concrete events with stable occurrence identities.
     * @throws RuntimeException Invalid, unsupported or excessive calendar input.
     */
    public static function expand(array $events, array $options, DateTimeZone $siteZone, callable $resolveZone): array
    {
        $from = self::window($options['ics_from'] ?? '', $siteZone);
        $to = self::window($options['ics_to'] ?? '', $siteZone);
        if (($from === null) !== ($to === null) || ($from && ($from > $to || $to > $from->modify('+10 years')))) {
            self::fail('COM_JEM_IMPORT_ICS_RANGE_INVALID');
        }
        $groups = [];
        foreach ($events as $index => $event) {
            foreach (['UID', 'DTSTART', 'DTEND', 'DURATION', 'RECURRENCE-ID', 'SEQUENCE', 'DTSTAMP', 'STATUS'] as $single) {
                if (isset($event[$single]) && count($event[$single]) > 1) {
                    self::fail('COM_JEM_IMPORT_ICS_CONFLICT');
                }
            }
            $uid = self::value($event, 'UID');
            if ($uid === '' && (isset($event['RRULE']) || isset($event['RDATE']) || isset($event['RECURRENCE-ID']))) {
                self::fail('COM_JEM_IMPORT_ICS_UID_REQUIRED');
            }
            $groups[$uid !== '' ? 'uid:' . $uid : 'row:' . $index][] = $event;
        }
        $result = [];
        $bytes = 0;
        $visited = 0;
        foreach ($groups as $group) {
            $master = null;
            $overrides = [];
            foreach ($group as $event) {
                if (isset($event['EXRULE']) || isset($event['RECURRENCE-ID'][0]['params']['RANGE'])) {
                    self::fail('COM_JEM_IMPORT_ICS_UNSUPPORTED');
                }
                if (isset($event['RECURRENCE-ID'])) {
                    [$rid, $allDay] = self::date($event['RECURRENCE-ID'][0], $siteZone, $resolveZone);
                    $key = self::key($rid, $allDay);
                    $overrides[$key] = self::latest($overrides[$key] ?? null, $event);
                } else {
                    $master = self::latest($master, $event);
                }
            }
            // A detached instance is an ordinary event, not a new recurrence master.
            if ($master === null) {
                foreach ($overrides as $key => $event) {
                    self::append($result, $bytes, $event, $key, $from, $to, $siteZone, $resolveZone);
                }
                continue;
            }
            if (strtoupper(self::value($master, 'STATUS')) === 'CANCELLED') {
                continue;
            }
            if (!isset($master['DTSTART'])) {
                self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
            }
            [$start, $allDay] = self::date($master['DTSTART'][0], $siteZone, $resolveZone);
            $duration = self::duration($master, $start, $allDay, $siteZone, $resolveZone);
            $firstKey = self::key($start, $allDay);
            $dates = [$firstKey => [$start, $duration]];
            if (isset($master['RRULE'])) {
                if (count($master['RRULE']) !== 1) {
                    self::fail('COM_JEM_IMPORT_ICS_UNSUPPORTED');
                }
                self::ruleDates($dates, $master, $start, $allDay, $duration, $to, $siteZone, $resolveZone, $visited);
            }
            foreach ($master['RDATE'] ?? [] as $property) {
                foreach (explode(',', $property['raw']) as $raw) {
                    $parts = explode('/', $raw);
                    $prop = $property;
                    $prop['raw'] = $parts[0];
                    if (($prop['params']['VALUE'] ?? '') === 'PERIOD') {
                        unset($prop['params']['VALUE']);
                    }
                    [$date, $dateOnly] = self::date($prop, $siteZone, $resolveZone);
                    if ($dateOnly !== $allDay || count($parts) > 2) {
                        self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
                    }
                    $span = $duration;
                    if (count($parts) === 2) {
                        if (strtoupper($property['params']['VALUE'] ?? '') !== 'PERIOD' || $allDay) {
                            self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
                        }
                        $period = ['DTSTART' => [$prop]];
                        if (str_starts_with($parts[1], 'P')) {
                            $period['DURATION'] = [['raw' => $parts[1], 'value' => $parts[1], 'params' => []]];
                        } else {
                            $prop['raw'] = $parts[1];
                            $period['DTEND'] = [$prop];
                        }
                        $span = self::duration($period, $date, false, $siteZone, $resolveZone);
                    }
                    $dates[self::key($date, $allDay)] = [$date, $span];
                    self::budget($visited);
                }
            }
            foreach ($master['EXDATE'] ?? [] as $property) {
                foreach (explode(',', $property['raw']) as $raw) {
                    $property['raw'] = $raw;
                    [$date, $dateOnly] = self::date($property, $siteZone, $resolveZone);
                    if ($dateOnly !== $allDay) {
                        self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
                    }
                    unset($dates[self::key($date, $allDay)]);
                }
            }
            foreach ($dates as $key => [$date, $span]) {
                if (isset($overrides[$key])) {
                    continue;
                }
                $instance = $master;
                self::setDates($instance, $date, $allDay, $span);
                self::append($result, $bytes, $instance, $key === $firstKey ? '' : $key, $from, $to, $siteZone, $resolveZone);
            }
            foreach ($overrides as $key => $override) {
                // Exceptions replace their original slot, even when moved outside the selected window.
                $instance = array_replace($master, $override);
                unset($instance['RRULE'], $instance['RDATE'], $instance['EXDATE']);
                if (strtoupper(self::value($instance, 'STATUS')) !== 'CANCELLED') {
                    if (!isset($override['DTSTART'])) {
                        self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
                    }
                    [$date, $dateOnly] = self::date($override['DTSTART'][0], $siteZone, $resolveZone);
                    if ($dateOnly !== $allDay) {
                        self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
                    }
                    $span = isset($override['DTEND']) || isset($override['DURATION'])
                        ? self::duration($override, $date, $allDay, $siteZone, $resolveZone) : $duration;
                    self::setDates($instance, $date, $allDay, $span);
                }
                self::append($result, $bytes, $instance, $key === $firstKey ? '' : $key, $from, $to, $siteZone, $resolveZone);
            }
        }
        return $result;
    }

    /** @param array|null $old Previous revision. @param array $new New revision. @return array Latest unambiguous revision. */
    private static function latest(?array $old, array $new): array
    {
        if ($old === null || $old === $new) {
            return $new;
        }
        $a = [(int) self::value($old, 'SEQUENCE'), self::value($old, 'DTSTAMP')];
        $b = [(int) self::value($new, 'SEQUENCE'), self::value($new, 'DTSTAMP')];
        if ($a === $b) {
            self::fail('COM_JEM_IMPORT_ICS_CONFLICT');
        }
        return $b > $a ? $new : $old;
    }

    /**
     * Generate in wall-clock UTC, then validate in the source zone so DST gaps do not consume COUNT.
     *
     * @param array &$dates Unique occurrence set.
     * @param array $event Source master.
     * @param DateTimeImmutable $start Source start.
     * @param bool $allDay Date-only event.
     * @param int|DateInterval $duration Exact seconds or nominal duration.
     * @param DateTimeImmutable|null $to Inclusive selected end date.
     * @param DateTimeZone $siteZone Floating-time zone.
     * @param callable $resolveZone Timezone resolver.
     * @param int &$visited Shared generation budget.
     * @return void
     */
    private static function ruleDates(
        array &$dates,
        array $event,
        DateTimeImmutable $start,
        bool $allDay,
        $duration,
        ?DateTimeImmutable $to,
        DateTimeZone $siteZone,
        callable $resolveZone,
        int &$visited
    ): void
    {
        if (strlen(self::value($event, 'RRULE')) > 4096) {
            self::fail('COM_JEM_IMPORT_ICS_LIMIT');
        }
        $parts = [];
        foreach (explode(';', strtoupper(self::value($event, 'RRULE'))) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2 || isset($parts[$pair[0]])) {
                self::fail('COM_JEM_IMPORT_ICS_UNSUPPORTED');
            }
            $parts[$pair[0]] = $pair[1];
        }
        if (!in_array($parts['FREQ'] ?? '', ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)
            || isset($parts['COUNT'], $parts['UNTIL'])) {
            self::fail('COM_JEM_IMPORT_ICS_UNSUPPORTED');
        }
        if ($allDay) {
            // RFC 5545 requires time selectors on DATE rules to be ignored.
            unset($parts['BYHOUR'], $parts['BYMINUTE'], $parts['BYSECOND']);
        }
        $count = isset($parts['COUNT']) ? (preg_match('/^[0-9]+$/D', $parts['COUNT']) ? (int) $parts['COUNT'] : false) : null;
        if ($count === false || ($count !== null && ($count < 1 || $count > self::MAX_CANDIDATES))) {
            self::fail('COM_JEM_IMPORT_ICS_LIMIT');
        }
        $until = null;
        if (isset($parts['UNTIL'])) {
            $zonedStart = isset($event['DTSTART'][0]['params']['TZID'])
                || str_ends_with($event['DTSTART'][0]['raw'], 'Z');
            if (!$allDay && $zonedStart !== str_ends_with($parts['UNTIL'], 'Z')) {
                self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
            }
            $prop = $event['DTSTART'][0];
            $prop['raw'] = $parts['UNTIL'];
            if (str_ends_with($parts['UNTIL'], 'Z')) {
                unset($prop['params']['TZID']);
            }
            [$until, $dateOnly] = self::date($prop, $siteZone, $resolveZone);
            if ($dateOnly !== $allDay || $until < $start) {
                self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
            }
        }
        if ($count === null && $until === null && $to === null) {
            self::fail('COM_JEM_IMPORT_ICS_RANGE_REQUIRED');
        }
        $hardEnd = $start->modify('+' . self::MAX_YEARS . ' years');
        $end = $until ?? $hardEnd;
        if ($to !== null && $to->modify('+1 day') < $end) {
            $end = $to->modify('+1 day');
        }
        if ($end > $hardEnd) {
            self::fail('COM_JEM_IMPORT_ICS_LIMIT');
        }
        if ($end < $start) {
            return;
        }
        // JEM validates RFC rule parts and bounds generation without a third-party engine.
        unset($parts['COUNT'], $parts['UNTIL']);
        $wallStart = new DateTimeImmutable($start->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
        $wallEnd = new DateTimeImmutable($end->setTimezone($start->getTimezone())->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
        try {
            $validLocal = static function (DateTimeImmutable $wallDate) use ($start): bool {
                $stamp = $wallDate->format('Ymd\THis');
                return self::localDate($stamp, $start->getTimezone()) !== null;
            };
            $rule = RecurrenceRule::dates($parts, $wallStart, $wallEnd, $validLocal, $visited);
            $found = 0;
            $first = true;
            foreach ($rule as $wallDate) {
                $stamp = $wallDate->format('Ymd\THis');
                $date = self::localDate($stamp, $start->getTimezone());
                if (!$date || $date->format('Ymd\THis') !== $stamp) {
                    continue; // Nonexistent local times are not recurrence instances (RFC 5545).
                }
                if ($first && $date != $start) {
                    self::fail('COM_JEM_IMPORT_ICS_START_MISMATCH');
                }
                $first = false;
                if ($until && $date > $until) {
                    break;
                }
                $dates[self::key($date, $allDay)] = [$date, $duration];
                if ($count !== null && ++$found >= $count) {
                    break;
                }
            }
            if ($first) {
                self::fail('COM_JEM_IMPORT_ICS_START_MISMATCH');
            }
            if ($count !== null && $found < $count && $to === null) {
                self::fail('COM_JEM_IMPORT_ICS_LIMIT');
            }
        } catch (OverflowException $e) {
            self::fail('COM_JEM_IMPORT_ICS_LIMIT');
        } catch (InvalidArgumentException $e) {
            self::fail('COM_JEM_IMPORT_ICS_UNSUPPORTED');
        }
    }

    /** @param int &$visited Candidate count. @return void Enforce a shared bounded expansion. */
    private static function budget(int &$visited): void
    {
        if (++$visited > self::MAX_CANDIDATES) {
            self::fail('COM_JEM_IMPORT_ICS_LIMIT');
        }
    }

    /** @param array $event Source. @param string $name Property. @return string First value. */
    private static function value(array $event, string $name): string
    {
        return trim((string) ($event[$name][0]['value'] ?? $event[$name][0]['raw'] ?? ''));
    }

    /** @param string $value ISO date. @param DateTimeZone $zone Site zone. @return DateTimeImmutable|null Valid date or absent. */
    private static function window(string $value, DateTimeZone $zone): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
        if (!$date || $date->format('Y-m-d') !== $value) {
            self::fail('COM_JEM_IMPORT_ICS_RANGE_INVALID');
        }
        return $date;
    }

    /** @param array $property ICS date property. @param DateTimeZone $fallback Floating-time zone. @param callable $resolver Zone resolver. @return array{DateTimeImmutable,bool} Strict source date and date-only flag. */
    private static function date(array $property, DateTimeZone $fallback, callable $resolver): array
    {
        $raw = trim((string) ($property['raw'] ?? ''));
        $allDay = preg_match('/^\d{8}$/D', $raw) === 1;
        $utc = str_ends_with($raw, 'Z');
        $valueType = strtoupper($property['params']['VALUE'] ?? ($allDay ? 'DATE' : 'DATE-TIME'));
        if (!preg_match('/^\d{8}(T\d{6}Z?)?$/D', $raw)
            || $valueType !== ($allDay ? 'DATE' : 'DATE-TIME') || (($utc || $allDay) && isset($property['params']['TZID']))) {
            self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
        }
        try {
            $zone = $utc ? new DateTimeZone('UTC') : (isset($property['params']['TZID']) ? $resolver($property['params']['TZID']) : $fallback);
        } catch (Exception $e) {
            self::fail('COM_JEM_IMPORT_EXTERNAL_ICS_TIMEZONE_ERROR');
        }
        $format = $allDay ? 'Ymd' : 'Ymd\THis' . ($utc ? '\Z' : '');
        $date = DateTimeImmutable::createFromFormat('!' . $format, $raw, $zone);
        if (!$date || $date->format($format) !== $raw) {
            self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
        }
        if (!$allDay && !$utc) {
            $date = self::localDate($raw, $zone);
        }
        return [$date, $allDay];
    }

    /**
     * Resolve a wall time, rejecting DST gaps and choosing the first occurrence in a repeated hour.
     *
     * @param string $stamp Local YmdTHis value.
     * @param DateTimeZone $zone Source timezone.
     * @return DateTimeImmutable|null First matching instant, or null for a nonexistent local time.
     */
    private static function localDate(string $stamp, DateTimeZone $zone): ?DateTimeImmutable
    {
        return DateTimeValue::local($stamp, $zone);
    }

    /** @param DateTimeImmutable $date Occurrence start. @param bool $allDay Date-only flag. @return string Canonical original-slot identity. */
    private static function key(DateTimeImmutable $date, bool $allDay): string
    {
        return $allDay ? $date->format('Ymd') : $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** @param array $event Source event. @param DateTimeImmutable $start Source start. @param bool $allDay Date-only flag. @param DateTimeZone $zone Floating-time zone. @param callable $resolver Zone resolver. @return int|DateInterval Exact seconds or nominal duration. */
    private static function duration(array $event, DateTimeImmutable $start, bool $allDay, DateTimeZone $zone, callable $resolver)
    {
        if (isset($event['DTEND'], $event['DURATION'])) {
            self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
        }
        if (isset($event['DTEND'])) {
            [$end, $dateOnly] = self::date($event['DTEND'][0], $zone, $resolver);
            if ($dateOnly !== $allDay || $end <= $start) {
                self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
            }
            return $allDay ? $start->diff($end) : $end->getTimestamp() - $start->getTimestamp();
        }
        if (isset($event['DURATION'])) {
            $raw = self::value($event, 'DURATION');
            if (!preg_match('/^P(?=\d|T\d)(?:\d+W|(?:\d+D)?(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+S)?)?)$/D', $raw)
                || ($allDay && str_contains($raw, 'T'))) {
                self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
            }
            try {
                $span = new DateInterval($raw);
                if ($start->add($span) <= $start) {
                    self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
                }
                return $span;
            } catch (Exception $e) {
                self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
            }
        }
        return $allDay ? new DateInterval('P1D') : 0;
    }

    /** @param array &$event Concrete instance. @param DateTimeImmutable $start Start. @param bool $allDay Date-only flag. @param int|DateInterval $span Duration. @return void Store concrete dates in the existing parser format. */
    private static function setDates(array &$event, DateTimeImmutable $start, bool $allDay, $span): void
    {
        $end = $span instanceof DateInterval ? $start->add($span) : $start->setTimestamp($start->getTimestamp() + $span);
        foreach (['DTSTART' => $start, 'DTEND' => $end] as $name => $date) {
            $raw = self::key($date, $allDay);
            $event[$name] = [['raw' => $raw, 'value' => $raw, 'params' => $allDay ? ['VALUE' => 'DATE'] : []]];
        }
        if ($span === 0) {
            unset($event['DTEND']);
        }
        unset($event['RRULE'], $event['RDATE'], $event['EXDATE'], $event['DURATION']);
    }

    /** @param array &$result Output. @param int &$bytes Expanded payload budget. @param array $event Instance. @param string $identity Original slot. @param DateTimeImmutable|null $from Inclusive window start. @param DateTimeImmutable|null $to Inclusive window end. @param DateTimeZone $zone Site zone. @param callable $resolver Zone resolver. @return void Append one active, in-window instance. */
    private static function append(
        array &$result,
        int &$bytes,
        array $event,
        string $identity,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        DateTimeZone $zone,
        callable $resolver
    ): void
    {
        if (strtoupper(self::value($event, 'STATUS')) === 'CANCELLED') {
            return;
        }
        if (!isset($event['DTSTART'])) {
            self::fail('COM_JEM_IMPORT_ICS_INVALID_DATE');
        }
        [$start, $allDay] = self::date($event['DTSTART'][0], $zone, $resolver);
        if ($from && ($start < $from || $start >= $to->modify('+1 day'))) {
            return;
        }
        // Detached events also need DURATION/all-day conversion, but never acquire a recurrence rule.
        self::setDates($event, $start, $allDay, self::duration($event, $start, $allDay, $zone, $resolver));
        $event['_jem_ics_identity'] = $identity;
        $bytes += strlen(serialize($event));
        if ($bytes > JemImportBudgetHelper::MAX_TOTAL_VALUE_BYTES) {
            self::fail('COM_JEM_IMPORT_ICS_LIMIT');
        }
        $result[] = $event;
        JemImportBudgetHelper::assertRecordCount(count($result));
    }

    /** @param string $key Translated administrator-facing error. @return never Abort before any import write. */
    private static function fail(string $key): never
    {
        throw new RuntimeException(Text::_($key));
    }
}
