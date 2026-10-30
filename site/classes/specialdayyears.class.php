<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Shared annual-period choices for the administrator and frontend Special Days lists. */
final class JemSpecialDayYears
{
    public static function periodYear(DateTimeInterface $date, int $startMonth = 1): int
    {
        $startMonth = max(1, min(12, $startMonth));
        return (int) $date->format('Y') - ((int) $date->format('n') < $startMonth ? 1 : 0);
    }

    /** Closed intervals are complete; open intervals offer nearby periods and known boundaries. */
    public static function availableYears(array $rows, int $startMonth, DateTimeInterface $today): array
    {
        $currentYear = self::periodYear($today, $startMonth);
        $ranges = [];

        foreach ($rows as $row) {
            $startValue = (string) ($row->start_date ?? '');
            $endValue = (string) ($row->end_date ?? '');
            $start = self::parseDate($startValue);
            $end = self::parseDate($endValue);

            // Invalid stored dates must not become unbounded rules or invented years.
            if (($startValue !== '' && $start === null) || ($endValue !== '' && $end === null)) {
                continue;
            }
            if (($start === null && trim((string) ($row->weekdays ?? '')) === '')
                || ($start !== null && $end !== null && $end < $start)) {
                continue;
            }

            $firstYear = $start !== null ? self::periodYear($start, $startMonth) : null;
            $lastYear = $end !== null ? self::periodYear($end, $startMonth) : null;

            if ($firstYear !== null && $lastYear !== null) {
                $ranges[] = [$firstYear, $lastYear];
                continue;
            }

            // Legacy NULL boundaries cannot be enumerated to infinity. Retain each
            // known boundary and up to five periods around today within the rule.
            foreach ([$firstYear, $lastYear] as $boundary) {
                if ($boundary !== null) {
                    $ranges[] = [$boundary, $boundary];
                }
            }
            $from = max($firstYear ?? 1, $currentYear - 2);
            $to = min($lastYear ?? 9999, $currentYear + 2);
            if ($from <= $to) {
                $ranges[] = [$from, $to];
            }
        }

        // Expand overlapping intervals only once, even for many long-running rules.
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $years = [];
        $lastAdded = 0;
        foreach ($ranges as [$from, $to]) {
            for ($year = max(1, $from, $lastAdded + 1); $year <= $to; $year++) {
                $years[$year] = $year;
            }
            $lastAdded = max($lastAdded, $to);
        }

        return $years;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
