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
$eventUseCategoryBackground = !empty($this->params->get('eventbg_usecatcolor', 0));
$today = (new DateTimeImmutable())->format('Y-m-d');
$eventsByDate = array();
$countcatevents = array();
$countvenueevents = array();

foreach ($this->rows as $row) {
    if (!JemHelper::isValidDate($row->dates) || empty($row->user_has_access_event)) {
        continue;
    }

    $dateKey = date('Y-m-d', strtotime($row->dates));
    $categoryClasses = array();
    $categoryColors = array();

    foreach ((array) $row->categories as $category) {
        $categoryClasses[] = 'cat' . (int) $category->id;

        if (!empty($category->color)) {
            $categoryColors[$category->color] = $category->color;
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

    $eventsByDate[$dateKey][] = array(
        'title' => $row->title,
        'classes' => implode(' ', array_unique($categoryClasses)),
        'categories' => implode(' ', array_unique($categoryClasses)),
        'venue' => $venueId > 0 ? 'venue' . $venueId : '',
        'colors' => array_values($categoryColors),
        'link' => Route::_(JemHelperRoute::getEventRoute($row->slug)),
    );
}

$this->calendarLegendDisplayLegend = $displayLegend;
$this->calendarLegendCountCatEvents = $countcatevents;
$this->calendarLegendCountVenueEvents = $countvenueevents;
$this->calendarLegendCategoryColorMarker = (int) $this->params->get('categoryColorMarker', 0);
$this->calendarLegendEventUseCategoryBackground = $eventUseCategoryBackground;

$weekdays = $firstWeekDay === 0
    ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
    : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');
?>

<div id="jem" class="jlcalendar jem_calendar jem-annual-calendar<?php echo $this->pageclass_sfx; ?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link, 'archive_link' => $this->archive_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
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
        <a class="btn btn-outline-primary" href="<?php echo $this->previous_link; ?>" aria-label="<?php echo Text::_('JPREV'); ?>">
            <?php echo jemhtml::icon('com_jem/prev.webp', 'fa-solid fa-angle-left jem-calendar-nav-icon', Text::_('JPREV'), array('class' => 'jem-calendar-nav-icon')); ?>
        </a>
        <div class="jem-annual-period">
            <span class="jem-annual-period-title"><?php echo $this->escape($this->periodLabel); ?></span>
            <span class="jem-annual-period-range">
                <?php echo HTMLHelper::_('date', $this->periodStart->format('Y-m-d'), 'F Y'); ?>
                -
                <?php echo HTMLHelper::_('date', $this->periodEnd->format('Y-m-d'), 'F Y'); ?>
            </span>
        </div>
        <a class="btn btn-outline-primary" href="<?php echo $this->next_link; ?>" aria-label="<?php echo Text::_('JNEXT'); ?>">
            <?php echo jemhtml::icon('com_jem/next.webp', 'fa-solid fa-angle-right jem-calendar-nav-icon', Text::_('JNEXT'), array('class' => 'jem-calendar-nav-icon')); ?>
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
        </div>
    <?php endif; ?>

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
                                <?php echo $weekNumber; ?>
                            </a>
                        <?php endif; ?>

                        <?php
                        if ((int) $cellDate->format('n') !== $monthNumber) : ?>
                            <span class="jem-annual-day is-empty"></span>
                            <?php continue; ?>
                        <?php endif;

                        $date = $cellDate->format('Y-m-d');
                        $dayEvents = $eventsByDate[$date] ?? array();
                        $dayLink = Route::_('index.php?option=com_jem&view=day&id=' . $cellDate->format('Ymd'));
                        $dayClass = 'jem-annual-day' . ($date === $today ? ' is-today' : '') . (!empty($dayEvents) ? ' has-events' : '');
                        $tooltipAttributes = '';

                        if (!empty($dayEvents)) {
                            $tooltipHtml = '<div class="jem-annual-tooltip-content">';
                            $tooltipHtml .= '<div class="jem-annual-tooltip-count">' . Text::plural('COM_JEM_ANNUALCALENDAR_EVENTS_COUNT', count($dayEvents)) . '</div>';
                            $tooltipHtml .= '<ul class="jem-annual-tooltip-events">';

                            foreach ($dayEvents as $event) {
                                $tooltipHtml .= '<li><a href="' . $this->escape($event['link']) . '">' . $this->escape($event['title']) . '</a></li>';
                            }

                            $tooltipHtml .= '</ul></div>';
                            $tooltipAttributes = ' data-bs-toggle="tooltip" data-bs-html="true" data-bs-custom-class="jem-annual-tooltip" data-bs-title="' . htmlspecialchars($tooltipHtml, ENT_QUOTES, 'UTF-8') . '"';
                        }
                        ?>
                        <a class="<?php echo $dayClass; ?>" href="<?php echo $dayLink; ?>" aria-label="<?php echo $this->escape($date); ?>"<?php echo $tooltipAttributes; ?>>
                            <span class="jem-annual-day-number"><?php echo (int) $cellDate->format('j'); ?></span>
                            <?php if (!empty($dayEvents)) : ?>
                                <span class="jem-annual-day-count"><?php echo count($dayEvents); ?></span>
                                <span class="jem-annual-markers">
                                    <?php
                                    $shownMarkers = 0;
                                    foreach ($dayEvents as $event) :
                                        if ($shownMarkers >= $markerLimit) {
                                            break;
                                        }

                                        $color = !empty($event['colors']) ? reset($event['colors']) : '#1f4fb2';
                                        $style = 'background-color:' . $this->escape($color) . ';';
                                        ?>
                                        <span class="event-filter jem-annual-marker <?php echo $this->escape($event['classes']); ?>"
                                            data-categories="<?php echo $this->escape($event['categories']); ?>"
                                            data-venue="<?php echo $this->escape($event['venue']); ?>"
                                            style="<?php echo $style; ?>"
                                            title="<?php echo $this->escape($event['title']); ?>"></span>
                                        <?php
                                        $shownMarkers++;
                                    endforeach;
                                    ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endfor; ?>
    </div>

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
        </div>
    <?php endif; ?>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>

    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
