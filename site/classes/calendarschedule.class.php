<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/eventschedule.class.php';

/** Project per-day programme slots as independent calendar occurrences. */
final class JemCalendarSchedule
{
    public static function projectPerDay(
        $event,
        string $timezone,
        string $from = '',
        string $to = '',
        bool $startDayOnly = false
    ): array {
        $intervals = JemEventSchedule::intervals(
            $event,
            $timezone,
            $startDayOnly || $from === '' ? null : $from,
            $startDayOnly || $to === '' ? null : $to
        );
        $rows = [];
        $firstActiveDay = $intervals ? $intervals[0]['start']->format('Y-m-d') : '';

        foreach ($intervals as $interval) {
            $day = $interval['start']->format('Y-m-d');

            if (($startDayOnly && $day !== $firstActiveDay)
                || ($from !== '' && $day < $from) || ($to !== '' && $day > $to)) {
                continue;
            }

            $row = clone $event;
            $row->dates = $day;
            $row->enddates = $day;
            $row->times = $interval['all_day'] ? null : $interval['start']->format('H:i:s');
            $row->endtimes = $interval['all_day'] ? null : $interval['end']->format('H:i:s');
            $row->calendar_occurrence_key = $interval['key'];
            $rows[] = $row;
        }

        return $rows;
    }
}
