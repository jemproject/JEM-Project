<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

require_once __DIR__ . '/eventschedule.class.php';

/** Day-view projections; never replace the source event dates or invent a finish. */
final class JemDaySchedule
{
    public static function project($event, string $day, string $timezone = 'UTC'): array
    {
        if (empty($event->dates)) {
            return [];
        }
        $source = clone $event;
        $definition = JemEventSchedule::definition($source);
        if ($definition === null) {
            $first = JemEventSchedule::date($source->dates);
            $last = !empty($source->enddates) && $source->enddates >= $source->dates
                ? JemEventSchedule::date($source->enddates) : $first;
            $source->enddates = $last->format('Y-m-d');
            // Preserve legacy daily hours, with the unambiguous next-day overnight exception.
            if (
                $first->diff($last)->days === 1 && !empty($source->times) && !empty($source->endtimes)
                && $source->endtimes < $source->times
            ) {
                $parameters = JemEventSchedule::parameters($source->attribs ?? []);
                $parameters['event_schedule'] = ['version' => 1, 'mode' => 'continuous', 'days' => []];
                $source->attribs = $parameters;
            }
        }

        $result = [];
        $base = JemEventSchedule::date($day)->getTimestamp();
        foreach (JemEventSchedule::onDay($source, $day, $timezone) as $interval) {
            $start = $interval['visible_start'];
            $end = $interval['visible_end'];
            $startSeconds = (int) $start->format('H') * 3600 + (int) $start->format('i') * 60 + (int) $start->format('s');
            $endSeconds = $end->format('Y-m-d') > $day ? 86400
                : (int) $end->format('H') * 3600 + (int) $end->format('i') * 60 + (int) $end->format('s');
            $startLabel = $start->format('H:i');
            $endLabel = $interval['end_known'] ? ($endSeconds === 86400 ? '24:00' : $end->format('H:i')) : '';
            $range = $startLabel . ($endLabel !== '' && $endLabel !== $startLabel ? ' - ' . $endLabel : '');
            // Unknown ends and point events need a visible footprint only, bounded by midnight.
            $visualEnd = $interval['end_known'] ? max($startSeconds + 60, $endSeconds) : $startSeconds + 3600;
            $row = clone $event;
            $row->day_schedule = [
                'key' => $interval['key'], 'day' => $day, 'all_day' => $interval['all_day'],
                'start' => $base + $startSeconds, 'end' => $base + min(86400, $visualEnd),
                'start_label' => $startLabel, 'end_label' => $endLabel, 'label' => $range,
                'actual' => $interval['start']->format('Y-m-d H:i')
                    . ($interval['end_known'] ? ' - ' . $interval['end']->format('Y-m-d H:i') : '') . ' ' . $timezone,
                'continuation' => $start != $interval['start'] || ($interval['end_known'] && $end != $interval['end']),
            ];
            $result[] = $row;
        }

        return $result;
    }

    /** Allocate lanes from the final visible rectangles, including compressed-hour widths. */
    public static function lanes(array $items): array
    {
        usort($items, static function ($a, $b) {
            return ($a['position'][0] <=> $b['position'][0])
                ?: ($a['position'][1] <=> $b['position'][1])
                ?: strcmp((string) ($a[0]->id ?? ''), (string) ($b[0]->id ?? ''))
                ?: strcmp($a[0]->day_schedule['key'], $b[0]->day_schedule['key']);
        });
        $ends = [];
        foreach ($items as &$item) {
            [$left, $width] = $item['position'];
            $lane = 0;
            while (isset($ends[$lane]) && $ends[$lane] > $left + 0.000001) {
                $lane++;
            }
            $item['position'][2] = $lane;
            $ends[$lane] = $left + $width;
        }
        unset($item);

        return ['items' => $items, 'count' => max(1, count($ends))];
    }
}
