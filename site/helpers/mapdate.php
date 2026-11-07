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
     * Resolve a preset or anchored period, optionally moving one period.
     *
     * @param string             $mode      All, today, tomorrow, date, week, month or year.
     * @param string             $date      Requested civil date (YYYY-MM-DD), or empty.
     * @param \DateTimeImmutable $now       Current instant in the Joomla site timezone.
     * @param int                $weekStart First weekday: 0 Sunday or 1 Monday.
     * @param int                $step      Previous (-1), current (0), or next (1) period.
     *
     * @return array{mode:string,date:string,start:?string,end:?string,unit:string}
     */
    public static function resolve(string $mode, string $date, \DateTimeImmutable $now, int $weekStart = 0, int $step = 0): array
    {
        if (!in_array($mode, ['today', 'tomorrow', 'date', 'week', 'month', 'year'], true)) {
            return ['mode' => 'all', 'date' => '', 'start' => null, 'end' => null, 'unit' => 'day'];
        }

        $today = $now->setTime(0, 0);
        $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $now->getTimezone()) : false;

        if (!$anchor || $anchor->format('Y-m-d') !== $date || $anchor->format('Y') === '0000') {
            $anchor = $today;
        }

        if ($mode === 'today' || $mode === 'tomorrow') {
            $anchor = $mode === 'tomorrow' ? $today->modify('+1 day') : $today;
        }

        $unit = in_array($mode, ['week', 'month', 'year'], true) ? $mode : 'day';

        // Normalize before shifting: January 31 + one month must not skip February.
        if ($unit === 'week') {
            $offset = ((int) $anchor->format('w') - ($weekStart === 1 ? 1 : 0) + 7) % 7;
            $anchor = $anchor->modify('-' . $offset . ' days');
        } elseif ($unit === 'month') {
            $anchor = $anchor->modify('first day of this month');
        } elseif ($unit === 'year') {
            $anchor = $anchor->setDate((int) $anchor->format('Y'), 1, 1);
        }

        if ($step === -1 || $step === 1) {
            $candidate = $anchor->modify(($step > 0 ? '+' : '-') . '1 ' . $unit);

            if ((int) $candidate->format('Y') >= 1 && (int) $candidate->format('Y') <= 9999) {
                $anchor = $candidate;
            }

            if ($unit === 'day') {
                $mode = 'date';
            }
        }

        $end = match ($unit) {
            'week' => $anchor->modify('+6 days'),
            'month' => $anchor->modify('last day of this month'),
            'year' => $anchor->setDate((int) $anchor->format('Y'), 12, 31),
            default => $anchor,
        };
        $start = $anchor->format('Y-m-d');

        return ['mode' => $mode, 'date' => $start, 'start' => $start, 'end' => $end->format('Y-m-d'), 'unit' => $unit];
    }
}
