<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$displayLegend = (int) $this->params->get('displayLegend', 1);
$firstWeekDay = (int) $this->params->get('firstweekday', 1);
$markerLimit = max(1, (int) $this->params->get('annual_marker_limit', 4));
$showDayEventCount = (bool) $this->params->get('annual_show_day_event_count', 0);
$eventUseCategoryBackground = !empty($this->params->get('eventbg_usecatcolor', 0));
$today = (new DateTimeImmutable())->format('Y-m-d');
$eventsByDate = array();
$countcatevents = array();
$countvenueevents = array();
$dayLinkTarget = (string) $this->params->get('annual_day_link_target', 'timetable');
$dayLinkTarget = in_array($dayLinkTarget, array('default', 'timetable', 'timeline'), true) ? $dayLinkTarget : 'timetable';
$dayLinkItemid = !empty($this->itemid) ? '&Itemid=' . (int) $this->itemid : '';
$dayLinkArchived = $this->params->get('show_archived_events', 0) ? '&show_archived_events=1' : '';
$createDayLink = static function (DateTimeImmutable $date) use ($dayLinkTarget, $dayLinkItemid, $dayLinkArchived) {
    $layout = in_array($dayLinkTarget, array('timetable', 'timeline'), true) ? '&layout=' . $dayLinkTarget : '';

    return Route::_('index.php?option=com_jem&view=day' . $layout . '&id=' . $date->format('Ymd') . $dayLinkItemid . $dayLinkArchived);
};
$specialDaysByDate = JemHelper::calendarSpecialDays($this->periodStart->format('Y-m-d'), $this->periodEnd->format('Y-m-d'));

$getSpecialDayPresentation = static function (array $specialDays) {
    if (!$specialDays) {
        return array('', '', '');
    }

    $primary = reset($specialDays);
    $color = !empty($primary['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $primary['color'])
        ? (string) $primary['color']
        : '#d1d5db';
    $textColor = '#111827';
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches)) {
        $hex = $matches[1];
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $textColor = (($red * 299 + $green * 587 + $blue * 114) / 1000) < 150 ? '#ffffff' : '#111827';
    }
    $labels = array();

    foreach ($specialDays as $specialDay) {
        $label = trim((string) ($specialDay['title'] ?: $specialDay['type']));
        if ($label !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
        }
    }

    return array(
        ' is-special-day',
        '--jem-calendar-special-day-bg:' . $color . ';--jem-calendar-special-day-color:' . $textColor . ';color:' . $textColor . ';',
        implode(', ', $labels),
    );
};

foreach ($this->rows as $row) {
    if (!JemHelper::isValidDate($row->dates) || empty($row->user_has_access_event)) {
        continue;
    }

    if (isset($row->multi) && $row->multi !== 'first') {
        continue;
    }

    $eventStartDate = new DateTimeImmutable($row->dates);
    $eventEndDate = JemHelper::isValidDate($row->enddates) && $row->enddates >= $row->dates
        ? new DateTimeImmutable($row->enddates)
        : $eventStartDate;
    $isMultidayEvent = $eventEndDate > $eventStartDate;
    $eventStartDate = $eventStartDate < $this->periodStart ? $this->periodStart : $eventStartDate;
    $eventEndDate = $eventEndDate > $this->periodEnd ? $this->periodEnd : $eventEndDate;
    $categoryClasses = array();
    $categoryColors = array();
    $categoryNames = array();

    foreach ((array) $row->categories as $category) {
        $categoryClasses[] = 'cat' . (int) $category->id;
        $categoryNames[] = $category->catname;

        $categoryColor = trim((string) ($category->color ?? ''));

        if ($categoryColor !== '') {
            $categoryColors[$categoryColor] = $categoryColor;
        }

        if (!isset($row->multi) || $row->multi === 'first') {
            if (!array_key_exists($category->id, $countcatevents)) {
                $countcatevents[$category->id] = 1;
            } else {
                $countcatevents[$category->id]++;
            }
        }
    }

    $venueId = (int) $row->locid;
    if ($venueId > 0 && (!isset($row->multi) || $row->multi === 'first')) {
        if (!array_key_exists($venueId, $countvenueevents)) {
            $countvenueevents[$venueId] = 1;
        } else {
            $countvenueevents[$venueId]++;
        }
    }

    for ($eventDate = $eventStartDate; $eventDate <= $eventEndDate; $eventDate = $eventDate->modify('+1 day')) {
        $eventsByDate[$eventDate->format('Y-m-d')][] = array(
            'title' => $row->title,
            'dates' => $row->dates,
            'times' => $row->times,
            'enddates' => $row->enddates,
            'endtimes' => $row->endtimes,
            'venue_title' => $row->venue ?? '',
            'type_title' => $row->type_name ?? '',
            'category_titles' => implode(', ', array_unique($categoryNames)),
            'classes' => implode(' ', array_unique($categoryClasses)),
            'categories' => implode(' ', array_unique($categoryClasses)),
            'venue' => $venueId > 0 ? 'venue' . $venueId : '',
            'colors' => array_values($categoryColors),
            'is_multiday' => $isMultidayEvent,
            'link' => Route::_(JemHelperRoute::getEventRoute($row->slug)),
        );
    }
}

$this->calendarLegendDisplayLegend = $displayLegend;
$this->calendarLegendCountCatEvents = $countcatevents;
$this->calendarLegendCountVenueEvents = $countvenueevents;
$this->calendarLegendCategoryColorMarker = (int) $this->params->get('categoryColorMarker', 0);
$this->calendarLegendEventUseCategoryBackground = $eventUseCategoryBackground;

$weekdays = $firstWeekDay === 0
    ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
    : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');

HTMLHelper::_('bootstrap.popover', '.jem-annual-day-popover', array('trigger' => 'manual', 'placement' => 'top', 'html' => true));
?>

<div id="jem" class="jlcalendar jem_calendar jem-annual-calendar<?php echo $showDayEventCount ? ' has-day-event-count' : ''; ?><?php echo $this->pageclass_sfx; ?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link, 'pdf_link' => $this->pdf_link, 'ical_link' => $this->ical_link, 'archive_link' => $this->archive_link);
        if (!$this->params->get('show_archived_events', 0)) {
            $btn_params['show'] = array('archive');
        }
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
        <?php if ($this->displayMode === 'agenda') : ?>
            <a class="jem-layout-toggle" href="<?php echo $this->calendar_link; ?>" title="<?php echo Text::_('COM_JEM_ANNUALCALENDAR_SHOW_CALENDAR'); ?>" aria-label="<?php echo Text::_('COM_JEM_ANNUALCALENDAR_SHOW_CALENDAR'); ?>">
                <i class="fa fa-fw fa-calendar-days" aria-hidden="true"></i>
                <span><?php echo Text::_('COM_JEM_ANNUALCALENDAR_SHOW_CALENDAR'); ?></span>
            </a>
        <?php else : ?>
            <a class="jem-layout-toggle" href="<?php echo $this->agenda_link; ?>" title="<?php echo Text::_('COM_JEM_ANNUALCALENDAR_SHOW_AGENDA'); ?>" aria-label="<?php echo Text::_('COM_JEM_ANNUALCALENDAR_SHOW_AGENDA'); ?>">
                <i class="fa fa-fw fa-list" aria-hidden="true"></i>
                <span><?php echo Text::_('COM_JEM_ANNUALCALENDAR_SHOW_AGENDA'); ?></span>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading">
            <?php echo $this->escape($this->params->get('page_heading')); ?>
        </h1>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <div class="jem-annual-nav">
        <a class="btn btn-outline-primary jem-annual-nav-button jem-annual-nav-prev" href="<?php echo $this->previous_link; ?>" aria-label="<?php echo Text::_('JPREV'); ?>">
            <span class="jem-annual-nav-text" aria-hidden="true">&lt;&lt;</span>
        </a>
        <div class="jem-annual-period">
            <span class="jem-annual-period-title"><?php echo $this->escape($this->periodLabel); ?></span>
            <span class="jem-annual-period-range">
                <?php echo HTMLHelper::_('date', $this->periodStart->format('Y-m-d'), 'F Y'); ?>
                -
                <?php echo HTMLHelper::_('date', $this->periodEnd->format('Y-m-d'), 'F Y'); ?>
            </span>
        </div>
        <a class="btn btn-outline-primary jem-annual-nav-button jem-annual-nav-next" href="<?php echo $this->next_link; ?>" aria-label="<?php echo Text::_('JNEXT'); ?>">
            <span class="jem-annual-nav-text" aria-hidden="true">&gt;&gt;</span>
        </a>
    </div>

    <?php if (in_array($displayLegend, array(2, 4, 6), true)) : ?>
        <div id="jlcalendarlegend">
            <div class="calendarButtons">
                <div class="calendarButtonsToggle">
                    <div id="buttonshowall" class="btn btn-outline-dark me-2"><?php echo Text::_('COM_JEM_SHOWALL'); ?></div>
                    <div id="buttonhideall" class="btn btn-outline-dark"><?php echo Text::_('COM_JEM_HIDEALL'); ?></div>
                </div>
            </div>
            <div class="clr"></div>
            <?php include JPATH_COMPONENT . '/views/calendar/tmpl/default_legend.php'; ?>
            <div class="calendarLegends mt-3 jem-annual-event-marker-legend">
                <span class="me-3">&#9679; <?php echo Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_ONE_DAY'); ?></span>
                <span>&#9632; <?php echo Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_MULTI_DAY'); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->displayMode === 'agenda') : ?>
        <div class="jem-annual-agenda">
            <?php
            $showEmptyDays = (bool) $this->params->get('annual_agenda_show_empty_days', 0);
            $showEmptyMonths = (bool) $this->params->get('annual_agenda_show_empty_months', 1);

            for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) :
                $monthDate = $this->periodStart->modify('+' . $monthIndex . ' months');
                $monthStart = $monthDate->modify('first day of this month');
                $monthEnd = $monthDate->modify('last day of this month');
                $monthHasEvents = false;

                for ($dayDate = $monthStart; $dayDate <= $monthEnd; $dayDate = $dayDate->modify('+1 day')) {
                    if (!empty($eventsByDate[$dayDate->format('Y-m-d')])) {
                        $monthHasEvents = true;
                        break;
                    }
                }

                if (!$showEmptyMonths && !$monthHasEvents) {
                    continue;
                }
                ?>
                <section class="jem-annual-agenda-month">
                    <h2 class="jem-annual-agenda-month-title">
                        <a href="<?php echo Route::_('index.php?option=com_jem&view=calendar&yearID=' . (int) $monthStart->format('Y') . '&monthID=' . (int) $monthStart->format('n')); ?>">
                            <?php echo HTMLHelper::_('date', $monthStart->format('Y-m-d'), 'F Y'); ?>
                        </a>
                    </h2>

                    <div class="jem-annual-agenda-days">
                        <div class="jem-annual-agenda-header" aria-hidden="true">
                            <span></span>
                            <span class="jem-annual-agenda-header-columns">
                                <span><?php echo Text::_('COM_JEM_TIME'); ?></span>
                                <span><?php echo Text::_('COM_JEM_TITLE'); ?></span>
                                <span><?php echo Text::_('COM_JEM_VENUE'); ?></span>
                                <span><?php echo Text::_('COM_JEM_TYPE'); ?></span>
                                <span><?php echo Text::_('COM_JEM_CATEGORY'); ?></span>
                            </span>
                        </div>
                        <?php
                        $monthHasVisibleDays = false;

                        for ($dayDate = $monthStart; $dayDate <= $monthEnd; $dayDate = $dayDate->modify('+1 day')) :
                            $date = $dayDate->format('Y-m-d');
                            $dayEvents = $eventsByDate[$date] ?? array();
                            $specialDays = $specialDaysByDate[$date] ?? array();
                            [$specialClass, $specialStyle, $specialTitle] = $getSpecialDayPresentation($specialDays);

                            if (!$showEmptyDays && empty($dayEvents)) {
                                continue;
                            }

                            $monthHasVisibleDays = true;
                            $dayLink = $createDayLink($dayDate);
                            ?>
                            <div class="jem-annual-agenda-day<?php echo empty($dayEvents) ? ' is-empty' : ' has-events'; ?><?php echo $specialClass; ?>"<?php echo $specialStyle ? ' style="' . $this->escape($specialStyle) . '"' : ''; ?><?php echo $specialTitle ? ' title="' . $this->escape($specialTitle) . '"' : ''; ?>>
                                <a class="jem-annual-agenda-date" href="<?php echo $dayLink; ?>">
                                    <span class="jem-annual-agenda-day-number"><?php echo (int) $dayDate->format('j'); ?></span>
                                    <span class="jem-annual-agenda-weekday"><?php echo HTMLHelper::_('date', $date, 'D'); ?></span>
                                </a>

                                <?php if (!empty($dayEvents)) : ?>
                                    <ul class="jem-annual-agenda-events">
                                        <?php foreach ($dayEvents as $event) :
                                            $time = '';
                                            if (!empty($event['times']) && $event['times'] !== '00:00:00') {
                                                $time = JemOutput::formattime($event['times']);
                                            }
                                            if (!empty($event['endtimes']) && $event['endtimes'] !== '00:00:00') {
                                                $time .= ($time ? ' - ' : '') . JemOutput::formattime($event['endtimes']);
                                            }
                                            ?>
                                            <li class="event-filter jem-annual-agenda-event <?php echo $this->escape($event['classes']); ?>"
                                                data-categories="<?php echo $this->escape($event['categories']); ?>"
                                                data-venue="<?php echo $this->escape($event['venue']); ?>">
                                                <span class="jem-annual-agenda-event-time"><?php echo $time; ?></span>
                                                <a class="jem-annual-agenda-event-title" href="<?php echo $this->escape($event['link']); ?>">
                                                    <?php echo $this->escape($event['title']); ?>
                                                </a>
                                                <span class="jem-annual-agenda-event-venue"><?php echo $this->escape($event['venue_title']); ?></span>
                                                <span class="jem-annual-agenda-event-type"><?php echo $this->escape($event['type_title']); ?></span>
                                                <span class="jem-annual-agenda-event-categories"><?php echo $this->escape($event['category_titles']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <div class="jem-annual-agenda-empty"><?php echo Text::_('COM_JEM_ANNUALCALENDAR_NO_EVENTS'); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>

                        <?php if (!$monthHasVisibleDays) : ?>
                            <div class="jem-annual-agenda-empty-month"><?php echo Text::_('COM_JEM_ANNUALCALENDAR_NO_EVENTS_MONTH'); ?></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endfor; ?>
        </div>
    <?php else : ?>
    <div class="jem-annual-grid">
        <?php for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) :
            $monthDate = $this->periodStart->modify('+' . $monthIndex . ' months');
            $monthStart = $monthDate->modify('first day of this month');
            $monthEnd = $monthDate->modify('last day of this month');
            $monthNumber = (int) $monthStart->format('n');
            $monthYear = (int) $monthStart->format('Y');
            $daysInMonth = (int) $monthStart->format('t');
            $weekday = (int) $monthStart->format('w');
            $offset = $firstWeekDay === 0 ? $weekday : ($weekday === 0 ? 6 : $weekday - 1);
            $monthLink = Route::_('index.php?option=com_jem&view=calendar&yearID=' . $monthYear . '&monthID=' . $monthNumber);
            ?>
            <section class="jem-annual-month">
                <h2 class="jem-annual-month-title">
                    <a href="<?php echo $monthLink; ?>">
                        <?php echo HTMLHelper::_('date', $monthStart->format('Y-m-d'), 'F Y'); ?>
                    </a>
                </h2>
                <div class="jem-annual-weekdays" aria-hidden="true">
                    <span class="jem-annual-week-heading"><?php echo Text::_('COM_JEM_WKCAL_WEEK'); ?></span>
                    <?php foreach ($weekdays as $weekdayLabel) : ?>
                        <span><?php echo Text::_($weekdayLabel); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="jem-annual-days">
                    <?php
                    $gridStart = $monthStart->modify('-' . $offset . ' days');
                    $cellCount = (int) (ceil(($offset + $daysInMonth) / 7) * 7);

                    for ($cell = 0; $cell < $cellCount; $cell++) :
                        $cellDate = $gridStart->modify('+' . $cell . ' days');

                        if ($cell % 7 === 0) :
                            $weekDate = $firstWeekDay === 0 ? $cellDate->modify('+1 day') : $cellDate;
                            $weekYear = (int) $weekDate->format('o');
                            $weekNumber = (int) $weekDate->format('W');
                            $weekLink = Route::_('index.php?option=com_jem&view=weekcal&yearID=' . $weekYear . '&weekID=' . $weekNumber);
                            ?>
                            <a class="jem-annual-week-number" href="<?php echo $weekLink; ?>" aria-label="<?php echo Text::_('COM_JEM_WKCAL_WEEK') . ' ' . $weekNumber; ?>">
                                <span class="jem-annual-week-number-label"><?php echo $weekNumber; ?></span>
                            </a>
                        <?php endif; ?>

                        <?php
                        if ((int) $cellDate->format('n') !== $monthNumber) : ?>
                            <span class="jem-annual-day is-empty"></span>
                            <?php continue; ?>
                        <?php endif;

                        $date = $cellDate->format('Y-m-d');
                        $dayEvents = $eventsByDate[$date] ?? array();
                        $specialDays = $specialDaysByDate[$date] ?? array();
                        [$specialClass, $specialStyle, $specialTitle] = $getSpecialDayPresentation($specialDays);
                        $dayLink = $createDayLink($cellDate);
                        $dayClass = 'jem-annual-day' . ($date === $today ? ' is-today' : '') . (!empty($dayEvents) ? ' has-events' : '') . $specialClass;
                        $popoverAttributes = '';

                        if (!empty($dayEvents)) {
                            $popoverHtml = '<div class="jem-annual-popover-content">';
                            $popoverHtml .= '<div class="jem-annual-popover-count">' . Text::plural('COM_JEM_ANNUALCALENDAR_EVENTS_COUNT', count($dayEvents)) . '</div>';
                            $popoverHtml .= '<ul class="jem-annual-popover-events">';

                            foreach ($dayEvents as $event) {
                                $popoverHtml .= '<li><a href="' . $this->escape($event['link']) . '">' . $this->escape($event['title']) . '</a></li>';
                            }

                            $popoverHtml .= '</ul></div>';
                            $popoverAttributes = ' data-bs-content="' . htmlspecialchars($popoverHtml, ENT_QUOTES, 'UTF-8') . '" data-bs-custom-class="jem-annual-popover"';
                        }
                        ?>
                        <div class="<?php echo $dayClass; ?>" data-day-link="<?php echo $dayLink; ?>" role="link" tabindex="0" aria-label="<?php echo $this->escape($date); ?>"<?php echo $specialStyle ? ' style="' . $this->escape($specialStyle) . '"' : ''; ?><?php echo $specialTitle ? ' title="' . $this->escape($specialTitle) . '"' : ''; ?>>
                            <a class="jem-annual-day-number" href="<?php echo $dayLink; ?>" aria-label="<?php echo $this->escape($date); ?>">
                                <?php echo (int) $cellDate->format('j'); ?>
                            </a>
                            <?php if (!empty($dayEvents)) : ?>
                                <button type="button" class="jem-annual-day-popover" aria-label="<?php echo Text::plural('COM_JEM_ANNUALCALENDAR_EVENTS_COUNT', count($dayEvents)); ?>"<?php echo $popoverAttributes; ?>>
                                    <?php if ($showDayEventCount) : ?>
                                        <span class="jem-annual-day-count"><?php echo count($dayEvents); ?></span>
                                    <?php else : ?>
                                        <span class="visually-hidden"><?php echo Text::plural('COM_JEM_ANNUALCALENDAR_EVENTS_COUNT', count($dayEvents)); ?></span>
                                    <?php endif; ?>
                                </button>
                                <span class="jem-annual-markers">
                                    <?php
                                    $shownMarkers = 0;
                                    $eventCount = count($dayEvents);
                                    $visibleMarkerLimit = $eventCount > $markerLimit ? max(0, $markerLimit - 1) : $markerLimit;

                                    foreach ($dayEvents as $event) :
                                        if ($shownMarkers >= $visibleMarkerLimit) {
                                            break;
                                        }

                                        $color = !empty($event['colors']) ? reset($event['colors']) : '';
                                        $hasCategoryColor = $color !== '';
                                        $style = $hasCategoryColor ? 'background-color:' . $this->escape($color) . ';' : '';
                                        ?>
                                        <span class="event-filter jem-annual-marker<?php echo !empty($event['is_multiday']) ? ' jem-annual-marker-multiday' : ''; ?><?php echo $hasCategoryColor ? '' : ' jem-annual-marker-no-color'; ?> <?php echo $this->escape($event['classes']); ?>"
                                            data-categories="<?php echo $this->escape($event['categories']); ?>"
                                            data-venue="<?php echo $this->escape($event['venue']); ?>"
                                            <?php echo $style ? 'style="' . $style . '"' : ''; ?>
                                            title="<?php echo $this->escape($event['title']); ?>"></span>
                                        <?php
                                        $shownMarkers++;
                                    endforeach;

                                    $hiddenMarkers = $eventCount - $shownMarkers;
                                    if ($hiddenMarkers > 0) :
                                    ?>
                                        <span class="jem-annual-marker-more" title="<?php echo Text::plural('COM_JEM_ANNUALCALENDAR_EVENTS_COUNT_MORE', count($dayEvents)); ?>">+<?php echo (int) $hiddenMarkers; ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php if (in_array($displayLegend, array(0, 1, 3, 5), true)) : ?>
        <div id="jlcalendarlegend">
            <div class="calendarButtons">
                <div class="calendarButtonsToggle">
                    <div id="buttonshowall" class="btn btn-outline-dark me-2"><?php echo Text::_('COM_JEM_SHOWALL'); ?></div>
                    <div id="buttonhideall" class="btn btn-outline-dark"><?php echo Text::_('COM_JEM_HIDEALL'); ?></div>
                </div>
            </div>
            <div class="clr"></div>
            <?php include JPATH_COMPONENT . '/views/calendar/tmpl/default_legend.php'; ?>
            <div class="calendarLegends mt-3 jem-annual-event-marker-legend">
                <span class="me-3">&#9679; <?php echo Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_ONE_DAY'); ?></span>
                <span>&#9632; <?php echo Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_MULTI_DAY'); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php echo JemHelper::renderCalendarSpecialDayLegend($this->periodStart->format('Y-m-d'), $this->periodEnd->format('Y-m-d'), $this->params); ?>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>

    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
