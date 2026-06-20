<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

JemHelper::loadCss('timetable');
Factory::getApplication()->getDocument()->addStyleSheet(Uri::root() . 'media/com_jem/css/timetable.css');

$getCategoryColor = static function ($row) {
    foreach ((array) $row->categories as $category) {
        $color = trim((string) ($category->color ?? ''));
        if ($color !== '' && preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
            return $color;
        }
    }

    return '#2f6f73';
};

$getVenueColorData = static function ($row) {
    foreach (array('l_color', 'venuecolor') as $field) {
        $color = trim((string) ($row->$field ?? ''));
        if ($color !== '' && preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
            return array($color, true);
        }
    }

    return array('#dce8e6', false);
};

$getVenueColor = static function ($row) use ($getVenueColorData) {
    list($color) = $getVenueColorData($row);

    return $color;
};

$lightenColor = static function ($color, $colorWeight = 45) {
    $color = trim((string) $color);

    if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
        return '#dce8e6';
    }

    if (strlen($color) < 5) {
        $scan = sscanf($color, '#%1x%1x%1x');
        $rgb = array($scan[0] * 17, $scan[1] * 17, $scan[2] * 17);
    } else {
        $rgb = sscanf($color, '#%2x%2x%2x');
    }

    $colorWeight = max(0, min(100, (int) $colorWeight)) / 100;
    $red = (int) round(($rgb[0] * $colorWeight) + (255 * (1 - $colorWeight)));
    $green = (int) round(($rgb[1] * $colorWeight) + (255 * (1 - $colorWeight)));
    $blue = (int) round(($rgb[2] * $colorWeight) + (255 * (1 - $colorWeight)));

    return sprintf('#%02x%02x%02x', $red, $green, $blue);
};

$getContrastColor = static function ($color) {
    $color = trim((string) $color);
    $gray = false;

    if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
        return '#111111';
    }

    if (strlen($color) < 5) {
        $scan = sscanf($color, '#%1x%1x%1x');
        if (is_array($scan) && count($scan) == 3) {
            $gray = (17 * $scan[0] * 77) / 255
                + (17 * $scan[1] * 150) / 255
                + (17 * $scan[2] * 28) / 255;
        }
    } else {
        $scan = sscanf($color, '#%2x%2x%2x');
        if (is_array($scan) && count($scan) == 3) {
            $gray = ($scan[0] * 77) / 255
                + ($scan[1] * 150) / 255
                + ($scan[2] * 28) / 255;
        }
    }

    return ($gray !== false && $gray <= 160) ? '#ffffff' : '#111111';
};

$getEventUrl = static function ($row) {
    return Route::_(JemHelperRoute::getEventRoute($row->slug));
};

$getTimeValue = static function ($row, $field, $fallback = null) {
    $time = trim((string) ($row->$field ?? ''));

    if ($time === '') {
        return $fallback;
    }

    $timestamp = strtotime($row->dates . ' ' . $time);

    return $timestamp ?: $fallback;
};

$sortTime = static function ($row) use ($getTimeValue) {
    return $getTimeValue($row, 'times', PHP_INT_MAX);
};

$formatGridTime = static function ($timestamp) {
    return date('H:i', $timestamp);
};

$buildTooltip = function ($row, $timeRange = '') {
    $parts = array($this->escape($row->title));

    if ($timeRange !== '') {
        $parts[] = $this->escape($timeRange);
    }

    if (!empty($row->venue)) {
        $parts[] = $this->escape($row->venue);
    }

    $categories = trim(strip_tags(implode(', ', JemOutput::getCategoryList($row->categories, false))));
    if ($categories !== '') {
        $parts[] = $this->escape($categories);
    }

    return implode(' | ', $parts);
};

$rows = is_array($this->rows) ? $this->rows : array();
usort($rows, static function ($left, $right) use ($sortTime) {
    return $sortTime($left) <=> $sortTime($right);
});

$orientation = $this->params->get('timetable_orientation', 'horizontal');
$orientation = in_array($orientation, array('horizontal', 'vertical'), true) ? $orientation : 'horizontal';
$horizontalAxis = ($orientation === 'vertical') ? 'venue' : 'time';
$hourDisplay = $this->params->get('timetable_hour_display', 'event_hours');
$hourDisplay = in_array($hourDisplay, array('event_hours', 'all_hours'), true) ? $hourDisplay : 'event_hours';
$rangeStartHour = max(0, min(23, (int) $this->params->get('timetable_range_start', 8)));
$rangeEndHour = max(1, min(24, (int) $this->params->get('timetable_range_end', 22)));
if ($rangeEndHour <= $rangeStartHour) {
    $rangeEndHour = min(24, $rangeStartHour + 1);
}
$minimumVisibleHours = max(1, min(24, (int) $this->params->get('timetable_minimum_hours', 8)));
$eventBackgroundMode = $this->params->get('timetable_event_background', 'category');
$eventBackgroundMode = in_array($eventBackgroundMode, array('category', 'venue', 'custom'), true) ? $eventBackgroundMode : 'category';
$customEventBackground = trim((string) $this->params->get('timetable_event_background_custom', '#6bbf59'));
if ($customEventBackground === '' || !preg_match('/^#[0-9a-f]{3,6}$/i', $customEventBackground)) {
    $customEventBackground = '#6bbf59';
}
$getEventBackgroundColor = static function ($row) use ($eventBackgroundMode, $customEventBackground, $getCategoryColor, $lightenColor) {
    if ($eventBackgroundMode === 'custom') {
        return $customEventBackground;
    }

    if ($eventBackgroundMode === 'venue') {
        $venueColor = $customEventBackground;
        foreach (array('l_color', 'venuecolor') as $field) {
            $color = trim((string) ($row->$field ?? ''));
            if ($color !== '' && preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
                $venueColor = $color;
                break;
            }
        }

        return $lightenColor($venueColor);
    }

    return $getCategoryColor($row);
};
$useStrongVenueHeader = ($eventBackgroundMode === 'venue');
$currentDate = new DateTimeImmutable($this->day ?? date('Y-m-d'));
$previousDate = $currentDate->modify('-1 day');
$nextDate = $currentDate->modify('+1 day');
$input = Factory::getApplication()->input;
$itemId = $input->getInt('Itemid', 0);
$catId = $input->getInt('catid', 0);
$locId = $input->getInt('locid', 0);
$extraLinkParts = array();
if ($itemId > 0) {
    $extraLinkParts[] = 'Itemid=' . $itemId;
}
if ($catId > 0) {
    $extraLinkParts[] = 'catid=' . $catId;
}
if ($locId > 0) {
    $extraLinkParts[] = 'locid=' . $locId;
}
$extraLink = empty($extraLinkParts) ? '' : '&' . implode('&', $extraLinkParts);
$previousLink = Route::_('index.php?option=com_jem&view=day&layout=timetable&id=' . $previousDate->format('Ymd') . $extraLink);
$nextLink = Route::_('index.php?option=com_jem&view=day&layout=timetable&id=' . $nextDate->format('Ymd') . $extraLink);
?>
<div id="jem" class="jem_day jem_day_timetable jem_day_timetable_<?php echo $this->escape($orientation); ?> jem_day_timetable_axis_<?php echo $this->escape($horizontalAxis); ?><?php echo $this->pageclass_sfx;?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
    <h1 class="componentheading">
        <?php echo $this->escape($this->params->get('page_heading')); ?>
    </h1>
    <?php endif; ?>

    <div class="clr"> </div>

    <nav class="jem-calendar-navigation jem-timetable-navigation" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_DAY_NAVIGATION'); ?>">
        <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $previousLink; ?>" rel="prev" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'); ?>">
            <?php echo jemhtml::icon('com_jem/prev.webp', 'fa-solid fa-angle-left jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'), array('class' => 'jem-calendar-nav-icon')); ?>
        </a>
        <div class="jem-calendar-nav-title jem-timetable-nav-title">
            <?php echo $this->daydate; ?>
        </div>
        <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $nextLink; ?>" rel="next" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_NEXT_DAY'); ?>">
            <?php echo jemhtml::icon('com_jem/next.webp', 'fa-solid fa-angle-right jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_NEXT_DAY'), array('class' => 'jem-calendar-nav-icon')); ?>
        </a>
    </nav>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)) : ?>
        <div class="jem-timetable-empty"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></div>
    <?php else : ?>
        <?php
        $slotMinutes = 60;
        $slotSeconds = $slotMinutes * 60;
        $gridDate = $currentDate->format('Y-m-d');
        $getDisplayTimeValue = static function ($row, $field, $fallback = null) use ($gridDate) {
            $time = trim((string) ($row->$field ?? ''));

            if ($time === '') {
                return $fallback;
            }

            $timestamp = strtotime($gridDate . ' ' . $time);

            return $timestamp ?: $fallback;
        };
        $timedRows = array();
        $visibleTimedRows = array();
        $allDayRows = array();
        $minTime = null;
        $maxTime = null;
        $activeHourKeys = array();

        foreach ($rows as $row) {
            $startTime = $getDisplayTimeValue($row, 'times');

            if (!$startTime) {
                $allDayRows[] = $row;
                continue;
            }

            $endTime = $getDisplayTimeValue($row, 'endtimes', $startTime + 3600);
            if ($endTime <= $startTime) {
                $endTime = $startTime + 3600;
            }

            $eventStartHour = (int) date('G', $startTime);
            $eventEndHour = (int) date('G', $endTime - 1);
            if ($eventEndHour < $eventStartHour) {
                $eventEndHour = 23;
            }
            $isInConfiguredRange = ($eventStartHour < $rangeEndHour && $eventEndHour >= $rangeStartHour);

            $timedRows[] = array($row, $startTime, $endTime);
            if ($isInConfiguredRange) {
                $visibleTimedRows[] = array($row, $startTime, $endTime);
            }
            $minTime = $minTime === null ? $startTime : min($minTime, $startTime);
            $maxTime = $maxTime === null ? $endTime : max($maxTime, $endTime);

            if (!$isInConfiguredRange) {
                continue;
            }

            for ($hour = max($rangeStartHour, $eventStartHour); $hour <= min($rangeEndHour - 1, $eventEndHour); $hour++) {
                $activeHourKeys[$hour] = $hour;
            }
        }

        if ($minTime === null) {
            $minTime = strtotime($gridDate . ' 08:00');
            $maxTime = strtotime($gridDate . ' 18:00');
        }

        $configuredGridStart = strtotime($gridDate . ' ' . sprintf('%02d:00', $rangeStartHour));
        $configuredGridEnd = strtotime($gridDate . ' ' . sprintf('%02d:00', $rangeEndHour));
        $gridStart = max($configuredGridStart, floor($minTime / $slotSeconds) * $slotSeconds);
        $gridEnd = min($configuredGridEnd, ceil($maxTime / $slotSeconds) * $slotSeconds);
        if ($gridEnd <= $gridStart) {
            $gridStart = $configuredGridStart;
            $gridEnd = $configuredGridEnd;
        }

        $slotCount = max(1, (int) (($gridEnd - $gridStart) / $slotSeconds));
        $timeSlots = array();
        if ($hourDisplay === 'event_hours') {
            if (!empty($activeHourKeys)) {
                ksort($activeHourKeys);
                $timeSlots = array_values($activeHourKeys);
            } else {
                $timeSlots = range($rangeStartHour, $rangeEndHour - 1);
            }

            $slotCount = count($timeSlots);
        } else {
            for ($slot = 0; $slot < $slotCount; $slot++) {
                $timeSlots[] = $gridStart + ($slot * $slotSeconds);
            }
        }

        if ($hourDisplay === 'event_hours') {
            $slotCount = count($timeSlots);
            if ($slotCount < $minimumVisibleHours) {
                $firstHour = (int) reset($timeSlots);
                $lastHour = (int) end($timeSlots);
                $needed = $minimumVisibleHours - $slotCount;

                while ($needed > 0 && $firstHour > $rangeStartHour) {
                    $firstHour--;
                    array_unshift($timeSlots, $firstHour);
                    $needed--;
                }

                while ($needed > 0 && $lastHour < $rangeEndHour - 1) {
                    $lastHour++;
                    $timeSlots[] = $lastHour;
                    $needed--;
                }

                $slotCount = count($timeSlots);
            }
        }

        $timeSlotIndexes = array();
        foreach ($timeSlots as $index => $timeSlot) {
            $timeSlotKey = ($hourDisplay === 'event_hours') ? (int) $timeSlot : (int) date('G', $timeSlot);
            $timeSlotIndexes[$timeSlotKey] = (int) $index;
        }

        $venueRows = array();
        $renderedTimedRows = $hourDisplay === 'event_hours' ? $visibleTimedRows : $timedRows;
        foreach ($renderedTimedRows as $eventData) {
            list($row, $startTime, $endTime) = $eventData;
            $venueKey = !empty($row->locid) ? 'venue-' . (int) $row->locid : 'venue-0';
            if (!isset($venueRows[$venueKey])) {
                list($venueColor, $hasVenueColor) = $getVenueColorData($row);
                $venueRows[$venueKey] = array(
                    'title' => !empty($row->venue) ? $row->venue : Text::_('COM_JEM_TIMETABLE_NO_VENUE'),
                    'color' => $venueColor,
                    'has_color' => $hasVenueColor,
                    'items' => array(),
                );
            }

            $venueRows[$venueKey]['items'][] = $eventData;
        }
        ?>
        <?php if ($horizontalAxis === 'venue') : ?>
            <?php
            $venueKeys = array_keys($venueRows);
            $venueCount = max(1, count($venueKeys));
            ?>
            <div class="jem-timetable-horizontal jem-timetable-horizontal-venues-top" style="--jem-timetable-slots: <?php echo (int) $venueCount; ?>;">
                <div class="jem-timetable-horizontal-stage">
                    <div class="jem-timetable-horizontal-header">
                        <div class="jem-timetable-corner"><?php echo Text::_('COM_JEM_TIME_SHORT'); ?></div>
                        <div class="jem-timetable-venuegrid">
                            <?php foreach ($venueRows as $venueRow) : ?>
                                <?php $strongVenueHeader = $useStrongVenueHeader && !empty($venueRow['has_color']); ?>
                                <?php $venueHeaderText = $strongVenueHeader ? $getContrastColor($venueRow['color']) : '#1f2a2a'; ?>
                                <div class="jem-timetable-grid-venue<?php echo $strongVenueHeader ? ' jem-timetable-grid-venue-strong' : ''; ?>" style="--jem-timetable-venue-color: <?php echo $this->escape($venueRow['color']); ?>; --jem-timetable-venue-text: <?php echo $this->escape($venueHeaderText); ?>;"><?php echo $this->escape($venueRow['title']); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php foreach ($timeSlots as $timeSlot) : ?>
                        <?php
                        $slotStart = ($hourDisplay === 'event_hours') ? strtotime($gridDate . ' ' . sprintf('%02d:00', (int) $timeSlot)) : $timeSlot;
                        $slotEnd = $slotStart + $slotSeconds;
                        ?>
                        <div class="jem-timetable-horizontal-row">
                            <div class="jem-timetable-venue"><?php echo $this->escape($formatGridTime($slotStart)); ?></div>
                            <div class="jem-timetable-track">
                                <?php foreach ($venueRows as $venueRow) : ?>
                                    <div class="jem-timetable-venue-cell"></div>
                                <?php endforeach; ?>
                                <?php foreach ($venueRows as $venueIndex => $venueRow) : ?>
                                    <?php foreach ($venueRow['items'] as $eventData) : ?>
                                        <?php
                                        list($row, $startTime, $endTime) = $eventData;
                                        if ($startTime >= $slotEnd || $endTime <= $slotStart) {
                                            continue;
                                        }

                                        $timeRange = $formatGridTime($startTime) . ' - ' . $formatGridTime($endTime);
                                        $accentColor = $getEventBackgroundColor($row);
                                        $textColor = $getContrastColor($accentColor);
                                        $gridColumnStart = array_search($venueIndex, $venueKeys, true) + 1;
                                        ?>
                                        <a class="jem-timetable-block" href="<?php echo $getEventUrl($row); ?>" title="<?php echo $buildTooltip($row, $timeRange); ?>" aria-label="<?php echo $buildTooltip($row, $timeRange); ?>" style="--jem-timetable-accent: <?php echo $this->escape($accentColor); ?>; --jem-timetable-event-text: <?php echo $this->escape($textColor); ?>; grid-column: <?php echo (int) $gridColumnStart; ?> / <?php echo (int) ($gridColumnStart + 1); ?>;" itemscope="itemscope" itemtype="https://schema.org/Event">
                                            <span class="jem-timetable-block-title" itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                            <span class="jem-timetable-block-time"><?php echo $this->escape($timeRange); ?></span>
                                            <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($allDayRows)) : ?>
                        <div class="jem-timetable-horizontal-row">
                            <div class="jem-timetable-venue"><?php echo Text::_('COM_JEM_TIMETABLE_ALL_DAY'); ?></div>
                            <div class="jem-timetable-track jem-timetable-track-all-day">
                                <?php foreach ($venueRows as $venueRow) : ?>
                                    <div class="jem-timetable-venue-cell"></div>
                                <?php endforeach; ?>
                                <?php foreach ($allDayRows as $row) : ?>
                                    <?php
                                    $accentColor = $getEventBackgroundColor($row);
                                    $textColor = $getContrastColor($accentColor);
                                    $venueKey = !empty($row->locid) ? 'venue-' . (int) $row->locid : 'venue-0';
                                    $gridColumnStart = max(1, (int) array_search($venueKey, $venueKeys, true) + 1);
                                    ?>
                                    <a class="jem-timetable-block" href="<?php echo $getEventUrl($row); ?>" title="<?php echo $buildTooltip($row); ?>" aria-label="<?php echo $buildTooltip($row); ?>" style="--jem-timetable-accent: <?php echo $this->escape($accentColor); ?>; --jem-timetable-event-text: <?php echo $this->escape($textColor); ?>; grid-column: <?php echo (int) $gridColumnStart; ?> / <?php echo (int) ($gridColumnStart + 1); ?>;" itemscope="itemscope" itemtype="https://schema.org/Event">
                                        <span class="jem-timetable-block-title" itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                        <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="jem-timetable-horizontal" style="--jem-timetable-slots: <?php echo (int) $slotCount; ?>;">
                <div class="jem-timetable-horizontal-stage">
                    <div class="jem-timetable-horizontal-header">
                        <div class="jem-timetable-corner"><?php echo Text::_('COM_JEM_VENUE_SHORT'); ?></div>
                        <div class="jem-timetable-timegrid">
                            <?php foreach ($timeSlots as $index => $timeSlot) : ?>
                                <div class="jem-timetable-grid-time">
                                    <?php echo $this->escape($hourDisplay === 'event_hours' ? sprintf('%02d:00', (int) $timeSlot) : $formatGridTime($timeSlot)); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php foreach ($venueRows as $venueRow) : ?>
                        <div class="jem-timetable-horizontal-row" style="--jem-timetable-venue-color: <?php echo $this->escape($venueRow['color']); ?>;">
                            <?php $strongVenueHeader = $useStrongVenueHeader && !empty($venueRow['has_color']); ?>
                            <?php $venueHeaderText = $strongVenueHeader ? $getContrastColor($venueRow['color']) : '#1f2a2a'; ?>
                            <div class="jem-timetable-venue<?php echo $strongVenueHeader ? ' jem-timetable-venue-strong' : ''; ?>" style="--jem-timetable-venue-text: <?php echo $this->escape($venueHeaderText); ?>;"><?php echo $this->escape($venueRow['title']); ?></div>
                            <div class="jem-timetable-track">
                                <?php foreach ($venueRow['items'] as $eventData) : ?>
                                    <?php
                                    list($row, $startTime, $endTime) = $eventData;
                                    if ($hourDisplay === 'event_hours') {
                                        $startHour = (int) date('G', $startTime);
                                        $endHour = (int) date('G', $endTime - 1);
                                        if ($endHour < $startHour) {
                                            $endHour = 23;
                                        }
                                        $startIndex = isset($timeSlotIndexes[$startHour]) ? $timeSlotIndexes[$startHour] : 0;
                                        $endIndex = isset($timeSlotIndexes[$endHour]) ? $timeSlotIndexes[$endHour] : $startIndex;
                                        $leftPercent = ($startIndex / $slotCount) * 100;
                                        $widthPercent = max(1.5, (($endIndex - $startIndex + 1) / $slotCount) * 100);
                                    } else {
                                        $eventStart = max($gridStart, $startTime);
                                        $eventEnd = min($gridEnd, $endTime);
                                        $leftPercent = (($eventStart - $gridStart) / ($gridEnd - $gridStart)) * 100;
                                        $widthPercent = max(1.5, (($eventEnd - $eventStart) / ($gridEnd - $gridStart)) * 100);
                                    }
                                    $timeRange = $formatGridTime($startTime) . ' - ' . $formatGridTime($endTime);
                                    $accentColor = $getEventBackgroundColor($row);
                                    $textColor = $getContrastColor($accentColor);
                                    ?>
                                    <a class="jem-timetable-block" href="<?php echo $getEventUrl($row); ?>" title="<?php echo $buildTooltip($row, $timeRange); ?>" aria-label="<?php echo $buildTooltip($row, $timeRange); ?>" style="--jem-timetable-accent: <?php echo $this->escape($accentColor); ?>; --jem-timetable-event-text: <?php echo $this->escape($textColor); ?>; left: <?php echo round($leftPercent, 4); ?>%; width: <?php echo round($widthPercent, 4); ?>%;" itemscope="itemscope" itemtype="https://schema.org/Event">
                                        <span class="jem-timetable-block-title" itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                        <span class="jem-timetable-block-time"><?php echo $this->escape($timeRange); ?></span>
                                        <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($allDayRows)) : ?>
                        <div class="jem-timetable-horizontal-row">
                            <div class="jem-timetable-venue"><?php echo Text::_('COM_JEM_TIMETABLE_ALL_DAY'); ?></div>
                            <div class="jem-timetable-track jem-timetable-track-all-day">
                                <?php foreach ($allDayRows as $row) : ?>
                                    <?php
                                    $accentColor = $getEventBackgroundColor($row);
                                    $textColor = $getContrastColor($accentColor);
                                    ?>
                                    <a class="jem-timetable-block" href="<?php echo $getEventUrl($row); ?>" title="<?php echo $buildTooltip($row); ?>" aria-label="<?php echo $buildTooltip($row); ?>" style="--jem-timetable-accent: <?php echo $this->escape($accentColor); ?>; --jem-timetable-event-text: <?php echo $this->escape($textColor); ?>; grid-column: 1 / -1;" itemscope="itemscope" itemtype="https://schema.org/Event">
                                        <span class="jem-timetable-block-title" itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                        <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
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
