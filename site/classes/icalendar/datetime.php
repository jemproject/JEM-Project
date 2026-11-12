<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Jem\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/** RFC 5545 date-time interpretation shared by import and concrete VEVENT export. */
final class DateTimeValue
{
    /**
     * Resolve local time without losing an explicitly imported instant in a repeated hour.
     *
     * @param string $stamp Local YmdTHis value.
     * @param DateTimeZone $zone Effective event timezone.
     * @param string|null $utcHint Previously imported UTC value; ignored if the wall time changed.
     * @return DateTimeImmutable|null Matching instant, or null for an invalid/nonexistent local time.
     * @see https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.5
     */
    public static function local(string $stamp, DateTimeZone $zone, ?string $utcHint = null): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Ymd\THis', $stamp, $zone);
        if (!$date || $date->format('Ymd\THis') !== $stamp) {
            return null;
        }
        if ($utcHint !== null && preg_match('/^[0-9]{8}T[0-9]{6}Z$/D', $utcHint)) {
            $hint = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $utcHint, new DateTimeZone('UTC'));
            if ($hint && $hint->format('Ymd\THis\Z') === $utcHint && $hint->setTimezone($zone)->format('Ymd\THis') === $stamp) {
                return $hint->setTimezone($zone);
            }
        }
        $wall = DateTimeImmutable::createFromFormat('!Ymd\THis', $stamp, new DateTimeZone('UTC'));
        // An ambiguous local DATE-TIME without an explicit instant denotes its first occurrence.
        foreach ($zone->getTransitions($date->getTimestamp() - 172800, $date->getTimestamp() + 172800) ?: [] as $transition) {
            $candidate = $date->setTimestamp($wall->getTimestamp() - $transition['offset']);
            if ($candidate < $date && $candidate->format('Ymd\THis') === $stamp) {
                $date = $candidate;
            }
        }
        return $date;
    }
}
