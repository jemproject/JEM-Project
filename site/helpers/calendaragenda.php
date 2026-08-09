<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class JemCalendarAgendaHelper
{
    public static function getMode($params): string
    {
        $mode = strtolower(Factory::getApplication()->input->getCmd('mode', ''));

        if (in_array($mode, array('agenda', 'calendar'), true)) {
            return $mode;
        }

        $mode = $params && method_exists($params, 'get') ? strtolower((string) $params->get('calendar_display_mode', 'calendar')) : 'calendar';

        return $mode === 'agenda' ? 'agenda' : 'calendar';
    }

    public static function renderToggle(): string
    {
        $current = Uri::getInstance();
        $query = $current->getQuery(true);
        unset($query['format'], $query['layout'], $query['tmpl'], $query['print']);

        $calendarUri = clone $current;
        $agendaUri = clone $current;
        $query['mode'] = 'calendar';
        $calendarUri->setQuery($query);
        $calendarLink = (string) $calendarUri;
        $query['mode'] = 'agenda';
        $agendaUri->setQuery($query);
        $agendaLink = (string) $agendaUri;
        $mode = self::getMode(Factory::getApplication()->getParams());
        $isAgenda = $mode === 'agenda';
        $link = $isAgenda ? $calendarLink : $agendaLink;
        $label = $isAgenda ? Text::_('COM_JEM_ANNUALCALENDAR_SHOW_CALENDAR') : Text::_('COM_JEM_ANNUALCALENDAR_SHOW_AGENDA');
        $icon = $isAgenda ? 'fa-calendar-days' : 'fa-list';

        return '<span class="jem-calendar-layout-toggle">'
            . '<a class="jem-layout-toggle" href="' . htmlspecialchars($link, ENT_COMPAT, 'UTF-8') . '" title="' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '">'
            . '<i class="fa fa-fw fa-lg ' . $icon . '" aria-hidden="true"></i>'
            . '<span class="jem-layout-toggle-label">' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '</span>'
            . '</a>'
            . '</span>';
    }

    public static function renderAgenda(array $rows, string $startDate = '', string $endDate = '', string $subtitle = ''): string
    {
        $eventsByDate = array();
        $categoryLegend = array();
        $periodStart = JemHelper::isValidDate($startDate) ? $startDate : '';
        $periodEnd = JemHelper::isValidDate($endDate) ? $endDate : '';

        foreach ($rows as $row) {
            if (!JemHelper::isValidDate($row->dates ?? '')) {
                continue;
            }

            $date = (string) $row->dates;

            if (($periodStart !== '' && $date < $periodStart) || ($periodEnd !== '' && $date > $periodEnd)) {
                continue;
            }

            $eventsByDate[$date][] = $row;

            foreach ((array) ($row->categories ?? array()) as $category) {
                $name = trim((string) ($category->catname ?? ''));

                if ($name === '') {
                    continue;
                }

                $key = (string) ($category->id ?? strtolower($name));
                $color = trim((string) ($category->color ?? ''));

                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    $color = '#6b7280';
                }

                if (!isset($categoryLegend[$key])) {
                    $categoryLegend[$key] = array(
                        'title' => $name,
                        'color' => strtolower($color),
                        'count' => 0,
                    );
                }

                $categoryLegend[$key]['count']++;
            }
        }

        ksort($eventsByDate);
        $html = array();
        $html[] = '<style>
            .jem-calendar-agenda-subtitle { margin: 0 0 1rem; text-align: center; font-weight: 700; font-size: 1.1rem; }
            .jem-calendar-agenda { display: grid; gap: 1rem; }
            .jem-calendar-agenda-day { border-top: 1px solid #d1d5db; padding-top: .75rem; }
            .jem-calendar-agenda-day h2 { margin: 0 0 .75rem; display: flex; align-items: baseline; gap: .5rem; }
            .jem-calendar-agenda-day-number { font-size: 1.5rem; font-weight: 700; }
            .jem-calendar-agenda-weekday { color: #6b7280; font-size: .95rem; }
            .jem-calendar-agenda-events { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
            .jem-calendar-agenda-event { display: grid; grid-template-columns: minmax(4.5rem, auto) 1fr; gap: .35rem .75rem; padding: .55rem .75rem; background: #fff8e1; border-radius: .35rem; }
            .jem-calendar-agenda-time { color: #374151; }
            .jem-calendar-agenda-title { font-weight: 700; }
            .jem-calendar-agenda-venue, .jem-calendar-agenda-type, .jem-calendar-agenda-categories { color: #4b5563; font-size: .92rem; }
            .jem-calendar-agenda-legend { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: .5rem .9rem; align-items: center; }
            .jem-calendar-agenda-legend-title { font-weight: 700; }
            .jem-calendar-agenda-legend-item { display: inline-flex; align-items: center; gap: .25rem; }
            .jem-calendar-agenda-legend-swatch { width: .75rem; height: .75rem; display: inline-block; }
            @media (min-width: 720px) {
                .jem-calendar-agenda-event { grid-template-columns: 6rem 1.4fr 1fr .8fr 1fr; align-items: center; }
            }
        </style>';

        if ($subtitle !== '') {
            $html[] = '<div class="jem-calendar-agenda-subtitle">' . htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8') . '</div>';
        }

        $html[] = '<div class="jem-calendar-agenda">';

        if (!$eventsByDate) {
            $html[] = '<p class="jem-calendar-agenda-empty">' . Text::_('COM_JEM_NO_EVENTS') . '</p>';
        }

        foreach ($eventsByDate as $date => $events) {
            $html[] = '<section class="jem-calendar-agenda-day">';
            $html[] = '<h2><span class="jem-calendar-agenda-day-number">' . (int) date('j', strtotime($date)) . '</span> <span class="jem-calendar-agenda-weekday">' . htmlspecialchars(date('D', strtotime($date)), ENT_COMPAT, 'UTF-8') . '</span></h2>';
            $html[] = '<ul class="jem-calendar-agenda-events">';

            foreach ($events as $event) {
                $time = trim(JemOutput::formattime($event->times ?? '', '', false) . (!empty($event->endtimes) ? ' - ' . JemOutput::formattime($event->endtimes, '', false) : ''));
                $eventLink = !empty($event->slug) ? Route::_(JemHelperRoute::getEventRoute($event->slug)) : '#';
                $venue = trim((string) ($event->venue ?? ''));
                $venueHtml = htmlspecialchars($venue, ENT_COMPAT, 'UTF-8');

                if ($venue !== '' && !empty($event->venueslug)) {
                    $venueHtml = '<a href="' . Route::_(JemHelperRoute::getVenueRoute($event->venueslug)) . '">' . $venueHtml . '</a>';
                }

                $categories = array();
                foreach ((array) ($event->categories ?? array()) as $category) {
                    $name = trim((string) ($category->catname ?? ''));
                    if ($name !== '') {
                        $categories[] = $name;
                    }
                }

                $html[] = '<li class="jem-calendar-agenda-event">'
                    . '<span class="jem-calendar-agenda-time">' . htmlspecialchars($time, ENT_COMPAT, 'UTF-8') . '</span>'
                    . '<a class="jem-calendar-agenda-title" href="' . $eventLink . '">' . htmlspecialchars((string) ($event->title ?? ''), ENT_COMPAT, 'UTF-8') . '</a>'
                    . '<span class="jem-calendar-agenda-venue">' . $venueHtml . '</span>'
                    . '<span class="jem-calendar-agenda-type">' . htmlspecialchars((string) ($event->type_name ?? ''), ENT_COMPAT, 'UTF-8') . '</span>'
                    . '<span class="jem-calendar-agenda-categories">' . htmlspecialchars(implode(', ', $categories), ENT_COMPAT, 'UTF-8') . '</span>'
                    . '</li>';
            }

            $html[] = '</ul>';
            $html[] = '</section>';
        }

        $html[] = '</div>';

        if ($categoryLegend) {
            $html[] = '<div class="jem-calendar-agenda-legend">';
            $html[] = '<span class="jem-calendar-agenda-legend-title">' . Text::_('COM_JEM_CATEGORIES') . '</span>';

            foreach ($categoryLegend as $item) {
                $html[] = '<span class="jem-calendar-agenda-legend-item">'
                    . '<span class="jem-calendar-agenda-legend-swatch" style="background-color:' . htmlspecialchars($item['color'], ENT_COMPAT, 'UTF-8') . ';"></span>'
                    . '<span>' . htmlspecialchars($item['title'], ENT_COMPAT, 'UTF-8') . ' (' . (int) $item['count'] . ')</span>'
                    . '</span>';
            }

            $html[] = '</div>';
        }

        return implode("\n", $html);
    }
}
