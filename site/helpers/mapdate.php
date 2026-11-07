<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Joomla\Component\Jem\Site\Helper;

defined('_JEXEC') or die;

/** Civil-date periods shared by the Events Map screen and its exports. */
final class JemMapDateHelper
{
    /**
     * Resolve a complete period and navigate by its unit or the next larger unit.
     *
     * @param string             $mode      All, today, tomorrow, date, week, month or year.
     * @param string             $date      Requested civil date (YYYY-MM-DD), or empty.
     * @param \DateTimeImmutable $now       Current instant in the Joomla site timezone.
     * @param int                $weekStart First weekday: 0 Sunday or 1 Monday.
     * @param int                $step      Previous (-1), current (0), or next (1) period.
     * @param int                $jumpStep  Larger jump: week for day, month for week, year for month.
     *
     * @return array{mode:string,date:string,start:?string,end:?string,unit:string}
     */
    public static function resolve(string $mode, string $date, \DateTimeImmutable $now, int $weekStart = 0, int $step = 0, int $jumpStep = 0): array
    {
        // Tomorrow remains an administrator preset, displayed as Day in the frontend.
        $tomorrow = $mode === 'tomorrow';
        if ($tomorrow) {
            $mode = 'date';
        }
        if (!in_array($mode, ['today', 'tomorrow', 'date', 'week', 'month', 'year'], true)) {
            return ['mode' => 'all', 'date' => '', 'start' => null, 'end' => null, 'unit' => 'day'];
        }

        $today = $now->setTime(0, 0);
        $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $now->getTimezone()) : false;

        if (!$anchor || $anchor->format('Y-m-d') !== $date || $anchor->format('Y') === '0000') {
            $anchor = $today;
        }

        if ($mode === 'today') {
            $anchor = $today;
        } elseif ($tomorrow) {
            $anchor = $today->modify('+1 day');
        }

        $unit = in_array($mode, ['week', 'month', 'year'], true) ? $mode : 'day';

        // Normalise before moving: a month containing January 31 moves to February.
        $anchor = self::periodStart($anchor, $unit, $weekStart);
        $jumpUnit = ['day' => 'week', 'week' => 'month', 'month' => 'year'][$unit] ?? null;
        $largeJump = $jumpUnit !== null && in_array($jumpStep, [-1, 1], true);
        if (in_array($step, [-1, 1], true) || $largeJump) {
            $direction = ($largeJump ? $jumpStep : $step) > 0 ? '+' : '-';
            $moveUnit = $largeJump ? $jumpUnit : $unit;
            if ($moveUnit === 'month') {
                $candidate = $anchor->modify('first day of this month')->modify($direction . '1 month');
                $candidate = $candidate->setDate((int) $candidate->format('Y'), (int) $candidate->format('m'), min((int) $anchor->format('d'), (int) $candidate->format('t')));
            } else {
                $candidate = $anchor->modify($direction . '1 ' . $moveUnit);
            }
            if ((int) $candidate->format('Y') >= 1 && (int) $candidate->format('Y') <= 9999) {
                $anchor = $candidate;
            }
            if ($mode === 'today') {
                $mode = 'date';
            }
        }

        $anchor = self::periodStart($anchor, $unit, $weekStart);

        $end = match ($unit) {
            'week' => $anchor->modify('+6 days'),
            'month' => $anchor->modify('last day of this month'),
            'year' => $anchor->setDate((int) $anchor->format('Y'), 12, 31),
            default => $anchor,
        };
        // Keep the date control and exported ranges inside supported civil years.
        $maximum = $today->setDate(9999, 12, 31);
        if ($end > $maximum) {
            $end = $maximum;
        }
        $start = $anchor->format('Y-m-d');

        return ['mode' => $mode, 'date' => $start, 'start' => $start, 'end' => $end->format('Y-m-d'), 'unit' => $unit];
    }

    /**
     * Find the first civil date of the containing period, bounded to year one.
     *
     * @param \DateTimeImmutable $date Date in the Joomla site timezone.
     * @param string $unit Day, week, month or year.
     * @param int $weekStart First weekday: 0 Sunday or 1 Monday.
     * @return \DateTimeImmutable First supported date of the period.
     */
    private static function periodStart(\DateTimeImmutable $date, string $unit, int $weekStart): \DateTimeImmutable
    {
        $minimum = $date->setDate(1, 1, 1);
        if ($unit === 'week') {
            $offset = ((int) $date->format('w') - ($weekStart === 1 ? 1 : 0) + 7) % 7;
            $date = $date->modify('-' . $offset . ' days');
        } elseif ($unit === 'month') {
            $date = $date->modify('first day of this month');
        } elseif ($unit === 'year') {
            $date = $date->setDate((int) $date->format('Y'), 1, 1);
        }

        return $date < $minimum ? $minimum : $date;
    }
}
