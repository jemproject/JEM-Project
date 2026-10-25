<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/../classes/dayschedule.class.php';
require_once __DIR__ . '/calendarfilter.php';

/**
 * Read-only hourly projection shared by the Day and Week calendar views.
 */
final class JemCalendarHourGridHelper
{
    public static function renderNavigation(
        string $view,
        string $firstDay,
        int $days = 1,
        bool $sundayFirst = false,
        string $mode = 'timetable'
    ): string
    {
        if (!JemHelper::isValidCalendarDate($firstDay)) {
            return '';
        }

        $date = new DateTimeImmutable($firstDay, new DateTimeZone(JemHelper::getJoomlaTimeZoneName()));
        $step = $view === 'weekcal' ? max(7, $days) : 1;
        $previous = $date->modify('-' . $step . ' days');
        $next = $date->modify('+' . $step . ' days');
        $today = new DateTimeImmutable(JemHelper::getJoomlaDate());
        $itemId = Factory::getApplication()->input->getInt('Itemid', 0);
        $context = $itemId > 0 ? '&Itemid=' . $itemId : '';

        if ($view === 'weekcal') {
            $mode = $mode === 'agenda' ? 'agenda' : 'timetable';
            $makeLink = static function (DateTimeImmutable $target) use ($context, $sundayFirst, $mode): string {
                $anchor = $sundayFirst ? $target->modify('+1 day') : $target;

                return Route::_('index.php?option=com_jem&view=weekcal&yearID=' . $anchor->format('o')
                    . '&weekID=' . $anchor->format('W') . '&mode=' . $mode . $context, false);
            };
            $todayAnchor = $sundayFirst ? $today->modify('+1 day') : $today;
            $today = $todayAnchor->setISODate(
                (int) $todayAnchor->format('o'),
                (int) $todayAnchor->format('W'),
                $sundayFirst ? 7 : 1
            );
            if ($sundayFirst) {
                $today = $today->modify('-1 week');
            }
            $previousLabel = Text::_('COM_JEM_WKCAL_PREVIOUS_WEEK');
            $nextLabel = Text::_('COM_JEM_WKCAL_NEXT_WEEK');
        } else {
            $input = Factory::getApplication()->input;
            foreach (array('catid', 'locid') as $field) {
                $value = $input->getInt($field, 0);
                if ($value > 0) {
                    $context .= '&' . $field . '=' . $value;
                }
            }
            $makeLink = static function (DateTimeImmutable $target) use ($context): string {
                return Route::_('index.php?option=com_jem&view=day&id=' . $target->format('Ymd')
                    . '&mode=timetable' . $context, false);
            };
            $previousLabel = Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY');
            $nextLabel = Text::_('COM_JEM_TIMETABLE_NEXT_DAY');
        }

        $lastDay = $date->modify('+' . (max(1, $days) - 1) . ' days');
        $title = JemOutput::formatdate($firstDay);
        if ($days > 1) {
            $title .= ' – ' . JemOutput::formatdate($lastDay->format('Y-m-d'));
        }

        $navigationLabel = $view === 'weekcal' ? 'COM_JEM_TIMETABLE_WEEK_NAVIGATION'
            : 'COM_JEM_TIMETABLE_DAY_NAVIGATION';

        return '<nav class="jem-hourgrid-navigation" aria-label="' . self::escape(Text::_($navigationLabel)) . '">'
            . '<a href="' . self::escape($makeLink($previous)) . '" aria-label="' . self::escape($previousLabel)
            . '"><span class="fa fa-angle-left" aria-hidden="true"></span></a>'
            . '<a href="' . self::escape($makeLink($today)) . '">' . self::escape(Text::_('COM_JEM_TIMETABLE_TODAY')) . '</a>'
            . '<strong>' . self::escape($title) . '</strong>'
            . '<a href="' . self::escape($makeLink($next)) . '" aria-label="' . self::escape($nextLabel)
            . '"><span class="fa fa-angle-right" aria-hidden="true"></span></a></nav>';
    }

    public static function render(array $events, string $firstDay, int $days, int $topCategoryId = 0): string
    {
        if (!JemHelper::isValidCalendarDate($firstDay) || $days < 1) {
            return '';
        }

        $calendar = new DateTimeImmutable($firstDay, new DateTimeZone(JemHelper::getJoomlaTimeZoneName()));
        $columns = array();
        $firstHour = 7;
        $lastHour = 23;

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $calendar->modify('+' . $offset . ' days')->format('Y-m-d');
            $allDay = array();
            $timed = array();

            foreach ($events as $event) {
                foreach (JemDaySchedule::project($event, $date, JemHelper::getEventTimeZoneName($event)) as $session) {
                    $schedule = $session->day_schedule;

                    if ($schedule['all_day']) {
                        $allDay[] = $session;
                        continue;
                    }

                    $start = self::minutes($schedule['start_label']);
                    $end = $schedule['end_label'] !== ''
                        ? self::minutes($schedule['end_label'])
                        : min(1440, $start + 60);
                    $end = max($start + 1, min(1440, $end));
                    $firstHour = min($firstHour, intdiv($start, 60));
                    $lastHour = max($lastHour, (int) ceil($end / 60));
                    $timed[] = array('row' => $session, 'start' => $start, 'end' => $end);
                }
            }

            usort($timed, static function (array $left, array $right): int {
                return ($left['start'] <=> $right['start'])
                    ?: ($left['end'] <=> $right['end'])
                    ?: ((int) $left['row']->id <=> (int) $right['row']->id);
            });

            // Allocate non-overlapping lanes; every visible session remains clickable.
            $laneEnds = array();
            foreach ($timed as &$item) {
                $lane = 0;
                while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $item['start']) {
                    $lane++;
                }
                $item['lane'] = $lane;
                $laneEnds[$lane] = $item['end'];
            }
            unset($item);

            $columns[] = array(
                'date' => $date,
                'all_day' => $allDay,
                'timed' => $timed,
                'lanes' => max(1, count($laneEnds)),
            );
        }

        $firstHour = max(0, $firstHour);
        $lastHour = min(24, $lastHour);
        $hours = max(1, $lastHour - $firstHour);
        $html = array('<div class="jem-hourgrid' . ($days === 1 ? ' jem-hourgrid-single-day' : '')
            . '" style="--jem-hourgrid-hours:' . $hours . '">');
        $lastDate = $calendar->modify('+' . ($days - 1) . ' days')->format('Y-m-d');
        $specialDays = JemHelper::calendarSpecialDays($firstDay, $lastDate);

        foreach (array_chunk($columns, 7) as $week) {
            $isWeek = $days > 1;
            $html[] = '<div class="jem-hourgrid-days' . ($isWeek ? ' jem-hourgrid-week' : '') . '">';

            if ($isWeek) {
                $html[] = '<div class="jem-hourgrid-axis-heading" aria-hidden="true"></div>';
                $html[] = '<div class="jem-hourgrid-axis-all-day">'
                    . self::escape(Text::_('COM_JEM_TIMETABLE_ALL_DAY')) . '</div>';
                $html[] = '<div class="jem-hourgrid-axis-plot" aria-hidden="true">';
                for ($hour = $firstHour; $hour < $lastHour; $hour++) {
                    $top = (($hour - $firstHour) / $hours) * 100;
                    $html[] = '<span class="jem-hourgrid-axis-hour" style="top:' . $top . '%">'
                        . sprintf('%02d:00', $hour) . '</span>';
                }
                $html[] = '</div>';
            }

            foreach ($week as $index => $column) {
                $date = $column['date'];
                $empty = !$column['all_day'] && !$column['timed'];
                $daySpecials = $specialDays[$date] ?? array();
                $weekend = $isWeek && (int) (new DateTimeImmutable($date))->format('N') >= 6;
                $weekendColor = '#d1d5db';

                if ($weekend) {
                    foreach ($daySpecials as $specialDay) {
                        if (strcasecmp(trim((string) ($specialDay['type'] ?? '')), 'Weekend') === 0
                            && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($specialDay['color'] ?? ''))) {
                            $weekendColor = $specialDay['color'];
                            break;
                        }
                    }
                }

                $visibleSpecials = $isWeek ? array_values(array_filter($daySpecials, static function (array $specialDay): bool {
                    return strcasecmp(trim((string) ($specialDay['type'] ?? '')), 'Weekend') !== 0;
                })) : $daySpecials;
                $columnStyle = $isWeek ? ' style="--jem-hourgrid-column:' . ($index + 2)
                    . ';--jem-hourgrid-weekend-color:' . $weekendColor . '"' : '';
                $html[] = '<section class="jem-hourgrid-day' . ($empty ? ' jem-hourgrid-day-empty' : '')
                    . ($weekend ? ' jem-hourgrid-weekend' : '')
                    . '"' . $columnStyle . '>';
                $fullDate = self::escape(JemOutput::formatdate($date));
                $html[] = '<div class="jem-hourgrid-date"><h3>';

                if ($isWeek) {
                    $dayUrl = 'index.php?option=com_jem&view=day&id=' . str_replace('-', '', $date) . '&mode=timetable'
                        . ($topCategoryId > 0 ? '&topcat=' . $topCategoryId : '');
                    $html[] = '<a class="jem-hourgrid-day-link" href="' . self::escape(Route::_($dayUrl, false))
                        . '" aria-label="' . $fullDate . '" title="' . $fullDate . '">'
                        . '<span class="jem-hourgrid-day-short">' . self::escape(JemOutput::formatdate($date, 'D j')) . '</span>'
                        . '<span class="jem-hourgrid-day-mobile">' . self::escape(JemOutput::formatdate($date, 'D j M')) . '</span>'
                        . '</a>';
                } else {
                    $html[] = $fullDate;
                }

                $html[] = '</h3>' . JemHelper::renderCalendarSpecialDayBadges($date, $visibleSpecials) . '</div>';
                $html[] = '<div class="jem-hourgrid-all-day">';
                if ($column['all_day']) {
                    $html[] = '<span class="jem-hourgrid-all-day-label">'
                        . self::escape(Text::_('COM_JEM_TIMETABLE_ALL_DAY')) . '</span>';
                }

                foreach ($column['all_day'] as $session) {
                    $html[] = self::eventLink($session, 'jem-hourgrid-all-day-event');
                }

                $html[] = '</div>';
                $html[] = '<div class="jem-hourgrid-plot">';

                for ($hour = $firstHour; $hour < $lastHour; $hour++) {
                    $top = (($hour - $firstHour) / $hours) * 100;
                    $html[] = '<span class="jem-hourgrid-hour" style="top:' . $top . '%">'
                        . sprintf('%02d:00', $hour) . '</span>';
                }

                foreach ($column['timed'] as $item) {
                    $start = max($item['start'], $firstHour * 60);
                    $end = min($item['end'], $lastHour * 60);
                    $top = (($start - $firstHour * 60) / ($hours * 60)) * 100;
                    $height = max(0.1, (($end - $start) / ($hours * 60)) * 100);
                    $width = 100 / $column['lanes'];
                    $left = $item['lane'] * $width;
                    $style = 'top:' . round($top, 4) . '%;height:' . round($height, 4)
                        . '%;left:calc(var(--jem-hourgrid-gutter, 3rem) + (100% - var(--jem-hourgrid-gutter, 3rem)) * '
                        . round($left / 100, 4) . ');width:calc((100% - var(--jem-hourgrid-gutter, 3rem)) * '
                        . round($width / 100, 4) . ' - 2px)';
                    $html[] = self::eventLink($item['row'], 'jem-hourgrid-event', $style);
                }

                $html[] = '</div>';

                if ($empty) {
                    $html[] = '<p class="jem-hourgrid-empty">' . self::escape(Joomla\CMS\Language\Text::_('COM_JEM_NO_EVENTS')) . '</p>';
                }

                $html[] = '</section>';
            }

            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private static function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return max(0, min(1440, ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0)));
    }

    private static function eventLink($row, string $class, string $style = ''): string
    {
        $color = '';
        foreach ((array) ($row->categories ?? array()) as $category) {
            $candidate = trim((string) ($category->color ?? ''));
            if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $candidate)) {
                $color = $candidate;
                break;
            }
        }

        $style .= ';--jem-hourgrid-accent:' . ($color ?: '#386b87');
        $href = !empty($row->slug) ? Route::_(JemHelperRoute::getEventRoute($row->slug)) : '#';
        $label = trim((string) ($row->day_schedule['label'] ?? ''));
        $title = trim((string) ($row->title ?? ''));
        $venue = trim((string) ($row->venue ?? ''));
        $accessible = trim($label . ' ' . $title . ' ' . $venue);

        return '<a' . JemCalendarFilterHelper::attributes($row, $class . ' event-filter')
            . ' href="' . self::escape($href) . '" style="' . self::escape($style)
            . '" aria-label="' . self::escape($accessible) . '">'
            . ($label !== '' && empty($row->day_schedule['all_day'])
                ? '<span class="jem-hourgrid-event-time">' . self::escape($label) . '</span>' : '')
            . '<span class="jem-hourgrid-event-title">' . self::escape($title) . '</span>'
            . ($venue !== '' ? '<span class="jem-hourgrid-event-venue">' . self::escape($venue) . '</span>' : '')
            . '</a>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
