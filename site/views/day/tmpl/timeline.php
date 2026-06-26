<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

if (!isset($this->params) || !($this->params instanceof Registry)) {
    $this->params = Factory::getApplication()->getParams('com_jem');

    if (!($this->params instanceof Registry)) {
        $this->params = new Registry();
    }
}

$normaliseTimelineColor = static function ($color) {
    $color = trim((string) $color);

    if ($color !== '' && $color[0] !== '#') {
        $color = '#' . $color;
    }

    return preg_match('/^#[0-9a-f]{3,6}$/i', $color) ? $color : '';
};

$getCategoryColor = static function ($row, $fallback = '#2f6f73') use ($normaliseTimelineColor) {
    foreach ((array) $row->categories as $category) {
        $color = $normaliseTimelineColor($category->color ?? '');

        if ($color !== '') {
            return $color;
        }
    }

    return $fallback;
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

    return sprintf(
        '#%02x%02x%02x',
        (int) round(($rgb[0] * $colorWeight) + (255 * (1 - $colorWeight))),
        (int) round(($rgb[1] * $colorWeight) + (255 * (1 - $colorWeight))),
        (int) round(($rgb[2] * $colorWeight) + (255 * (1 - $colorWeight)))
    );
};

$eventBackgroundMode = $this->params->get('timetable_event_background', 'category');
$eventBackgroundMode = in_array($eventBackgroundMode, array('category', 'type', 'venue', 'custom'), true) ? $eventBackgroundMode : 'category';
$customEventBackground = trim((string) $this->params->get('timetable_event_background_custom', '#6bbf59'));
if ($customEventBackground === '' || !preg_match('/^#[0-9a-f]{3,6}$/i', $customEventBackground)) {
    $customEventBackground = '#6bbf59';
}

$getEventBackgroundColor = static function ($row) use ($eventBackgroundMode, $customEventBackground, $getCategoryColor, $lightenColor, $normaliseTimelineColor) {
    if ($eventBackgroundMode === 'custom') {
        return $customEventBackground;
    }

    if ($eventBackgroundMode === 'type') {
        $typeColor = $normaliseTimelineColor($row->type_color ?? '');

        return $typeColor !== '' ? $typeColor : $getCategoryColor($row, $customEventBackground);
    }

    if ($eventBackgroundMode === 'venue') {
        foreach (array('l_color', 'venuecolor') as $field) {
            $color = $normaliseTimelineColor($row->$field ?? '');

            if ($color !== '') {
                return $lightenColor($color);
            }
        }

        return $lightenColor($customEventBackground);
    }

    return $getCategoryColor($row, $customEventBackground);
};

$getContrastColor = static function ($color) {
    $color = trim((string) $color);

    if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
        return '#111111';
    }

    if (strlen($color) < 5) {
        $scan = sscanf($color, '#%1x%1x%1x');
        $rgb = array($scan[0] * 17, $scan[1] * 17, $scan[2] * 17);
    } else {
        $rgb = sscanf($color, '#%2x%2x%2x');
    }

    $gray = ($rgb[0] * 77) / 255 + ($rgb[1] * 150) / 255 + ($rgb[2] * 28) / 255;

    return $gray <= 160 ? '#ffffff' : '#111111';
};

$getDisplayTimeValue = static function ($row, $field, $fallback = null) {
    $time = trim((string) ($row->$field ?? ''));

    if ($time === '') {
        return $fallback;
    }

    $timestamp = strtotime($row->dates . ' ' . $time);

    return $timestamp ?: $fallback;
};

$formatGridTime = static function ($timestamp) {
    return date('H:i', $timestamp);
};

$formatGridMinutes = static function ($minutes) {
    $minutes = max(0, min(1440, (int) $minutes));

    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
};

$parseGridTime = static function ($value, $fallbackMinutes) {
    $value = trim((string) $value);

    if (preg_match('/^([01]?\d|2[0-4])(?::([0-5]\d))?$/', $value, $match)) {
        $hour = min(24, (int) $match[1]);
        $minute = isset($match[2]) ? (int) $match[2] : 0;

        if ($hour === 24) {
            $minute = 0;
        }

        return ($hour * 60) + $minute;
    }

    if (is_numeric($value)) {
        return max(0, min(1440, (int) $value * 60));
    }

    return $fallbackMinutes;
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

$getEventImage = static function ($row) {
    $imageFile = trim((string) ($row->datimage ?? ''));

    if ($imageFile === '') {
        return '';
    }

    $image = JemImage::flyercreator($imageFile, 'event');

    if (!is_array($image)) {
        return '';
    }

    $source = !empty($image['thumb']) && is_file(JPATH_SITE . '/' . $image['thumb'])
        ? $image['thumb']
        : ($image['original'] ?? '');

    return $source !== '' ? $source : '';
};

$getVenueImage = static function ($row) {
    $imageFile = trim((string) ($row->locimage ?? ''));

    if ($imageFile === '') {
        return '';
    }

    $image = JemImage::flyercreator($imageFile, 'venue');

    if (!is_array($image)) {
        return '';
    }

    $source = !empty($image['thumb']) && is_file(JPATH_SITE . '/' . $image['thumb'])
        ? $image['thumb']
        : ($image['original'] ?? '');

    return $source !== '' ? $source : '';
};

$truncateText = static function ($text, $limit) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    $limit = max(0, (int) $limit);
    $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

    if ($text === '' || $limit === 0 || $length <= $limit) {
        return array('text' => $text, 'truncated' => false);
    }

    if (function_exists('mb_substr')) {
        return array('text' => rtrim(mb_substr($text, 0, $limit - 1)) . '...', 'truncated' => true);
    }

    return array('text' => rtrim(substr($text, 0, $limit - 1)) . '...', 'truncated' => true);
};

$rows = is_array($this->rows) ? $this->rows : array();
usort($rows, static function ($left, $right) use ($getDisplayTimeValue) {
    $dateCompare = strcmp((string) ($left->dates ?? ''), (string) ($right->dates ?? ''));

    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    $leftStart = $getDisplayTimeValue($left, 'times', PHP_INT_MAX);
    $rightStart = $getDisplayTimeValue($right, 'times', PHP_INT_MAX);

    if ($leftStart !== $rightStart) {
        return $leftStart <=> $rightStart;
    }

    $leftEnd = $getDisplayTimeValue($left, 'endtimes', $leftStart === PHP_INT_MAX ? PHP_INT_MAX : $leftStart + 3600);
    $rightEnd = $getDisplayTimeValue($right, 'endtimes', $rightStart === PHP_INT_MAX ? PHP_INT_MAX : $rightStart + 3600);

    if ($leftEnd !== $rightEnd) {
        return $leftEnd <=> $rightEnd;
    }

    return strcmp((string) ($left->title ?? ''), (string) ($right->title ?? ''));
});

$sortTimelineRows = static function (array &$items) use ($getDisplayTimeValue) {
    usort($items, static function ($left, $right) use ($getDisplayTimeValue) {
        $leftStart = $getDisplayTimeValue($left, 'times', PHP_INT_MAX);
        $rightStart = $getDisplayTimeValue($right, 'times', PHP_INT_MAX);

        if ($leftStart !== $rightStart) {
            return $leftStart <=> $rightStart;
        }

        $leftEnd = $getDisplayTimeValue($left, 'endtimes', $leftStart === PHP_INT_MAX ? PHP_INT_MAX : $leftStart + 3600);
        $rightEnd = $getDisplayTimeValue($right, 'endtimes', $rightStart === PHP_INT_MAX ? PHP_INT_MAX : $rightStart + 3600);

        if ($leftEnd !== $rightEnd) {
            return $leftEnd <=> $rightEnd;
        }

        return strcmp((string) ($left->title ?? ''), (string) ($right->title ?? ''));
    });
};

$input = Factory::getApplication()->input;
$timelineSide = (string) $this->params->get('timeline_side', 'right');
$timelineSide = in_array($timelineSide, array('right', 'left', 'alternate'), true) ? $timelineSide : 'right';
$timelineRightTextAlign = (string) $this->params->get('timeline_right_text_align', 'left');
$timelineRightTextAlign = in_array($timelineRightTextAlign, array('left', 'center', 'right'), true) ? $timelineRightTextAlign : 'left';
$timelineLeftTextAlign = (string) $this->params->get('timeline_left_text_align', 'right');
$timelineLeftTextAlign = in_array($timelineLeftTextAlign, array('left', 'center', 'right'), true) ? $timelineLeftTextAlign : 'right';
$timelineJustifyMap = array('left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end');
$eventFrame = (bool) $this->params->get('timeline_event_frame', 1);
$eventHeightMode = (string) $this->params->get('timeline_event_height_mode', 'same');
$eventHeightMode = in_array($eventHeightMode, array('same', 'duration'), true) ? $eventHeightMode : 'same';
$cardLayout = (string) $this->params->get('timeline_card_layout', 'compact');
$cardLayout = in_array($cardLayout, array('details', 'compact'), true) ? $cardLayout : 'details';
$layoutOverride = $input->getCmd('jem_timeline_layout', '');
if (in_array($layoutOverride, array('details', 'compact'), true)) {
    $cardLayout = $layoutOverride;
}
$layoutToggleTarget = $cardLayout === 'compact' ? 'details' : 'compact';
$layoutToggleIcon = $cardLayout === 'compact' ? 'fa-expand' : 'fa-compress';
$layoutToggleText = $layoutToggleTarget === 'compact' ? Text::_('COM_JEM_TIMELINE_LAYOUT_COMPACT') : Text::_('COM_JEM_TIMELINE_LAYOUT_DETAILS');
$eventColorMode = (string) $this->params->get('timeline_event_color_mode', 'bar');
$eventColorMode = in_array($eventColorMode, array('bar', 'background'), true) ? $eventColorMode : 'bar';
$timelineWidth = max(40, min(100, (int) $this->params->get('timeline_width', 80)));
$timelineLineColor = trim((string) $this->params->get('timeline_line_color', '#6c757d'));
if ($timelineLineColor === '' || !preg_match('/^#[0-9a-f]{3,6}$/i', $timelineLineColor)) {
    $timelineLineColor = '#6c757d';
}
$timelineLineStyle = (string) $this->params->get('timeline_line_style', 'solid');
$timelineLineStyle = in_array($timelineLineStyle, array('solid', 'dotted', 'dashed', 'double', 'groove', 'ridge'), true) ? $timelineLineStyle : 'solid';
$timelineImageWidth = max(1, min(1000, (int) ($this->jemsettings->imagewidth ?? 100)));
$timelineImageHeight = max(1, min(1000, (int) ($this->jemsettings->imagehight ?? $timelineImageWidth)));
$timelineImageColumnWidth = max(80, $timelineImageWidth);
$legacyMediaSource = (string) $this->params->get('timeline_media_source', $this->params->get('timeline_show_images', 1) ? 'event' : 'none');
$legacyMediaSource = in_array($legacyMediaSource, array('none', 'event', 'venue', 'type_icon'), true) ? $legacyMediaSource : 'event';
$showEventImage = (bool) $this->params->get('timeline_show_event_image', $legacyMediaSource === 'event');
$eventImagePosition = (string) $this->params->get('timeline_event_image_position', 'left');
$eventImagePosition = in_array($eventImagePosition, array('left', 'right'), true) ? $eventImagePosition : 'left';
$showVenueImage = (bool) $this->params->get('timeline_show_venue_image', $legacyMediaSource === 'venue');
$venueImagePosition = (string) $this->params->get('timeline_venue_image_position', 'right');
$venueImagePosition = in_array($venueImagePosition, array('left', 'right'), true) ? $venueImagePosition : 'right';
$showCategory = (bool) $this->params->get('timeline_show_category', 1);
$categoryDisplay = (string) $this->params->get('timeline_category_display', 'text');
$categoryDisplay = in_array($categoryDisplay, array('text', 'badge'), true) ? $categoryDisplay : 'text';
$showVenue = (bool) $this->params->get('timeline_show_venue', 1);
$showType = (bool) $this->params->get('timeline_show_type', 1);
$showEventIntro = (bool) $this->params->get('timeline_show_event_intro', 1);
$eventIntroLimit = max(0, (int) $this->params->get('timeline_event_intro_limit', 300));
$showEventReadmore = (bool) $this->params->get('timeline_show_event_readmore', 1);
$readmoreStyle = (string) $this->params->get('timeline_readmore_style', 'button');
$readmoreStyle = in_array($readmoreStyle, array('text', 'button'), true) ? $readmoreStyle : 'text';
$showDetailIcons = (bool) $this->params->get('timeline_show_detail_icons', 1);
$hourDisplay = $this->params->get('timetable_hour_display', 'event_hours');
$hourDisplay = in_array($hourDisplay, array('event_hours', 'all_hours'), true) ? $hourDisplay : 'event_hours';
$rangeStartMinutes = $parseGridTime($this->params->get('timetable_range_start', '08:00'), 8 * 60);
$rangeEndMinutes = $parseGridTime($this->params->get('timetable_range_end', '22:00'), 22 * 60);
$gridIntervalMinutes = max(5, min(240, (int) $this->params->get('timetable_grid_interval', 60)));
$gridSubintervalMinutes = max(0, min(120, (int) $this->params->get('timetable_grid_subinterval', 15)));
if ($gridSubintervalMinutes > 0 && $gridSubintervalMinutes >= $gridIntervalMinutes) {
    $gridSubintervalMinutes = 0;
}
$requestedTimelineDays = $input->getInt('timeline_days_to_show', 0);
$timelineDaysToShow = $requestedTimelineDays > 0
    ? max(1, min(30, $requestedTimelineDays))
    : max(1, min(30, (int) $this->params->get('timeline_days_to_show', 1)));
$timelineDayOptions = array(1, 2, 3, 4, 5, 6, 7, 15, 30);
if (!in_array($timelineDaysToShow, $timelineDayOptions, true)) {
    $timelineDayOptions[] = $timelineDaysToShow;
    sort($timelineDayOptions);
}
if ($rangeEndMinutes <= $rangeStartMinutes) {
    $rangeEndMinutes = min(1440, $rangeStartMinutes + $gridIntervalMinutes);
}

$showEmptyDays = (bool) $this->params->get('timeline_show_empty_days', 0);
$legacyAlternateDayBackground = (bool) $this->params->get('timeline_alternate_day_background', 1);
$dayBackground = (string) $this->params->get('timeline_day_background', $legacyAlternateDayBackground ? 'alternate' : 'none');
$dayBackground = in_array($dayBackground, array('none', 'alternate', 'special_day', 'alternate_special_day'), true) ? $dayBackground : 'alternate';
$alternateDayBackgroundColor = trim((string) $this->params->get('timeline_alternate_day_background_color', '#f3f4f6'));
if ($alternateDayBackgroundColor === '' || !preg_match('/^#[0-9a-f]{3,6}$/i', $alternateDayBackgroundColor)) {
    $alternateDayBackgroundColor = '#f3f4f6';
}
$currentDate = new DateTimeImmutable($this->day ?? date('Y-m-d'));
$rangeEndDate = $currentDate->modify('+' . ($timelineDaysToShow - 1) . ' days');
$timelineSpecialDays = in_array($dayBackground, array('special_day', 'alternate_special_day'), true)
    ? JemHelper::calendarSpecialDays($currentDate->format('Y-m-d'), $rangeEndDate->format('Y-m-d'))
    : array();
$showWeekNavigation = (int) $this->params->get('show_week_navigation', 1) === 1;
$previousWeekDate = $currentDate->modify('-1 week');
$nextWeekDate = $currentDate->modify('+1 week');
$previousDate = $currentDate->modify('-' . $timelineDaysToShow . ' days');
$nextDate = $currentDate->modify('+' . $timelineDaysToShow . ' days');
$dayModel = method_exists($this, 'getModel') ? $this->getModel() : null;

if (!$showEmptyDays && $dayModel && method_exists($dayModel, 'getAdjacentEventDate')) {
    $previousEventDate = $dayModel->getAdjacentEventDate($currentDate->format('Y-m-d'), 'previous');
    $nextEventDate = $dayModel->getAdjacentEventDate($rangeEndDate->format('Y-m-d'), 'next');

    if ($previousEventDate) {
        $previousDate = new DateTimeImmutable($previousEventDate);
    }

    if ($nextEventDate) {
        $nextDate = new DateTimeImmutable($nextEventDate);
    }
}
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
$baseLinkParts = $extraLinkParts;
$extraLinkParts[] = 'timeline_days_to_show=' . $timelineDaysToShow;
if ($layoutOverride !== '') {
    $extraLinkParts[] = 'jem_timeline_layout=' . $cardLayout;
}
$extraLink = empty($extraLinkParts) ? '' : '&' . implode('&', $extraLinkParts);
$baseExtraLink = empty($baseLinkParts) ? '' : '&' . implode('&', $baseLinkParts);
$previousWeekLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $previousWeekDate->format('Ymd') . $extraLink);
$nextWeekLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $nextWeekDate->format('Ymd') . $extraLink);
$previousLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $previousDate->format('Ymd') . $extraLink);
$nextLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $nextDate->format('Ymd') . $extraLink);
$todayLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . date('Ymd') . $extraLink);
$windowAction = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $currentDate->format('Ymd') . $baseExtraLink);
$layoutToggleParts = $baseLinkParts;
$layoutToggleParts[] = 'timeline_days_to_show=' . $timelineDaysToShow;
$layoutToggleParts[] = 'jem_timeline_layout=' . $layoutToggleTarget;
$layoutToggleLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $currentDate->format('Ymd') . (empty($layoutToggleParts) ? '' : '&' . implode('&', $layoutToggleParts)));
$navigationTitle = $timelineDaysToShow > 1
    ? HTMLHelper::_('date', $currentDate->format('Y-m-d'), Text::_('DATE_FORMAT_LC3')) . ' - ' . HTMLHelper::_('date', $rangeEndDate->format('Y-m-d'), Text::_('DATE_FORMAT_LC3'))
    : $this->daydate;
$pageHeading = trim((string) $this->params->get('page_heading'));
if ($pageHeading === '' || $pageHeading === 'Day') {
    $pageHeading = Text::_('COM_JEM_DAY_VIEW_TIMELINE_TITLE');
}
$dayRange = array();
for ($dayIndex = 0; $dayIndex < $timelineDaysToShow; $dayIndex++) {
    $dayKey = $currentDate->modify('+' . $dayIndex . ' days')->format('Y-m-d');
    $dayRange[$dayKey] = array(
        'allDayRows' => array(),
        'timedRows' => array(),
        'timedRowsByHour' => array(),
    );
}

foreach ($rows as $row) {
    $rowStart = (string) ($row->dates ?? '');

    if (!JemHelper::isValidDate($rowStart)) {
        continue;
    }

    $rowEnd = (string) ($row->enddates ?? '');
    if (!JemHelper::isValidDate($rowEnd) || $rowEnd < $rowStart) {
        $rowEnd = $rowStart;
    }

    $rowStartDate = new DateTimeImmutable(max($rowStart, $currentDate->format('Y-m-d')));
    $rowEndDate = new DateTimeImmutable(min($rowEnd, $rangeEndDate->format('Y-m-d')));

    if ($rowStartDate > $rowEndDate) {
        continue;
    }

    for ($activeDate = $rowStartDate; $activeDate <= $rowEndDate; $activeDate = $activeDate->modify('+1 day')) {
        $rowDate = $activeDate->format('Y-m-d');

        if (!isset($dayRange[$rowDate])) {
            continue;
        }

        $dayRow = clone $row;
        $dayRow->dates = $rowDate;
        $startTime = $getDisplayTimeValue($dayRow, 'times');

        if (!$startTime) {
            $dayRange[$rowDate]['allDayRows'][] = $dayRow;
            continue;
        }

        if ($hourDisplay === 'event_hours') {
            $eventMinutes = ((int) date('G', $startTime) * 60) + (int) date('i', $startTime);

            if ($eventMinutes < $rangeStartMinutes || $eventMinutes >= $rangeEndMinutes) {
                continue;
            }
        }

        $dayRange[$rowDate]['timedRows'][] = $dayRow;
        $eventMinutes = ((int) date('G', $startTime) * 60) + (int) date('i', $startTime);
        $dayRange[$rowDate]['timedRowsByHour'][$eventMinutes][] = $dayRow;
    }
}

foreach ($dayRange as &$dayData) {
    $sortTimelineRows($dayData['allDayRows']);
    $sortTimelineRows($dayData['timedRows']);

    foreach ($dayData['timedRowsByHour'] as &$hourRows) {
        $sortTimelineRows($hourRows);
    }
    unset($hourRows);
}
unset($dayData);
?>
<style>
    .jem-day-timeline-list {
        clear: both;
        display: grid;
        gap: 0;
        max-width: 100%;
        margin: 1.5rem 0 1rem;
        margin-left: auto;
        margin-right: auto;
        position: relative;
        width: var(--jem-timeline-width, 80%);
    }

    .jem-day-timeline-list::before {
        border-left: 2px var(--jem-timeline-line-style, solid) var(--jem-timeline-line-color, currentColor);
        bottom: .65rem;
        content: "";
        left: 50%;
        opacity: .55;
        position: absolute;
        top: .65rem;
        width: 0;
        z-index: 0;
    }

    .jem-day-timeline-grid::after {
        background: rgba(127, 127, 127, .12);
        bottom: .65rem;
        content: "";
        left: 50%;
        position: absolute;
        top: .65rem;
        transform: translateX(-50%);
        width: 2.25rem;
        z-index: 0;
    }

    #jem .jem-day-timeline-navigation {
        align-items: center;
        clear: both;
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin: .75rem 0 1rem;
    }

    #jem .jem-day-timeline-window-form {
        flex: 0 0 auto;
        margin: 0;
    }

    #jem .jem-day-timeline-window-select {
        min-height: 2rem;
        width: auto;
    }

    #jem .jem-day-timeline-navigation .jem-calendar-nav-title {
        min-width: 0;
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.2;
        text-align: center;
        white-space: nowrap;
    }

    #jem .jem-day-timeline-navigation .jem-calendar-nav-link {
        align-items: center;
        border: 1px solid currentColor;
        border-radius: 4px;
        display: inline-flex;
        justify-content: center;
        min-height: 2rem;
        min-width: 2rem;
        text-decoration: none;
    }

    #jem .jem-day-timeline-navigation .jem-day-timeline-nav-today {
        padding: 0 .85rem;
    }

    #jem .jem-day-timeline-navigation .jem-calendar-nav-icon img,
    #jem .jem-day-timeline-navigation img.jem-calendar-nav-icon {
        height: 1rem;
        width: 1rem;
    }

    #jem .buttons .jem-day-timeline-layout-toggle {
        display: inline-flex;
        align-items: center;
        border-radius: 3px;
        justify-content: center;
        min-height: 1.25rem;
        min-width: 1.25rem;
        padding: 0 .1rem;
        text-decoration: none;
        vertical-align: middle;
    }

    #jem .buttons .jem-day-timeline-layout-toggle .fa {
        font-size: 1.15rem;
    }

    .jem-day-timeline-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 2.25rem minmax(0, 1fr);
        min-height: 5.5rem;
        position: relative;
    }

    .jem-day-timeline-day {
        margin: 1.5rem 0 2rem;
        padding: .75rem 0 1rem;
    }

    .jem-day-timeline-day.jem-day-timeline-day-alternate:nth-of-type(even) {
        background: var(--jem-timeline-alternate-day-background, #f3f4f6);
    }

    .jem-day-timeline-day.jem-day-timeline-day-special {
        background: var(--jem-timeline-special-day-background, #f3f4f6);
    }

    .jem-day-timeline-day-alternate .jem-day-timeline-list::before {
        opacity: .9;
        border-left-width: 3px;
    }

    .jem-day-timeline-day-special .jem-day-timeline-list::before,
    .jem-day-timeline-day-alternate .jem-day-timeline-grid::after {
        opacity: .75;
    }

    .jem-day-timeline-day-special .jem-day-timeline-grid::after {
        opacity: .75;
    }

    .jem-day-timeline-day-special .jem-day-timeline-row:not(.jem-day-timeline-hour-row) > .jem-day-timeline-point,
    .jem-day-timeline-day-alternate .jem-day-timeline-row:not(.jem-day-timeline-hour-row) > .jem-day-timeline-point {
        border-color: var(--jem-timeline-alternate-day-background, #f3f4f6);
        box-shadow: 0 0 0 2px var(--jem-timeline-line-color, currentColor);
    }

    .jem-day-timeline-day-special .jem-day-timeline-row:not(.jem-day-timeline-hour-row) > .jem-day-timeline-point {
        border-color: var(--jem-timeline-special-day-background, #f3f4f6);
    }

    .jem-day-timeline-day-label {
        align-items: center;
        display: flex;
        font-weight: 700;
        justify-content: center;
        margin: 0 auto 1rem;
        max-width: 100%;
        text-align: center;
        width: var(--jem-timeline-width, 80%);
    }

    .jem-day-timeline-day-label span {
        border: 1px solid currentColor;
        border-radius: 999px;
        display: inline-block;
        padding: .35rem .9rem;
    }

    .jem-day-timeline-day-empty {
        margin: .5rem auto 1.5rem;
        max-width: 100%;
        opacity: .75;
        text-align: center;
        width: var(--jem-timeline-width, 80%);
    }

    .jem-day-timeline-right .jem-day-timeline-row {
        grid-template-columns: 6.5rem 2.25rem minmax(0, 1fr);
        justify-content: stretch;
    }

    .jem-day-timeline-right .jem-day-timeline-list::before,
    .jem-day-timeline-right .jem-day-timeline-grid::after {
        left: 7.625rem;
    }

    .jem-day-timeline-left .jem-day-timeline-row {
        grid-template-columns: minmax(0, 1fr) 2.25rem 6.5rem;
        justify-content: stretch;
    }

    .jem-day-timeline-left .jem-day-timeline-list::before,
    .jem-day-timeline-left .jem-day-timeline-grid::after {
        left: calc(100% - 7.625rem);
    }

    .jem-day-timeline-row::before {
        content: none;
    }

    .jem-day-timeline-point {
        align-self: start;
        background: #6c757d;
        border: 3px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 0 1px #6c757d;
        grid-column: 2;
        height: .9rem;
        justify-self: center;
        margin-top: .35rem;
        position: relative;
        width: .9rem;
        z-index: 2;
    }

    .jem-day-timeline-row:not(.jem-day-timeline-hour-row) > .jem-day-timeline-point {
        background: var(--jem-timeline-line-color, currentColor);
        box-shadow: 0 0 0 1px var(--jem-timeline-line-color, currentColor);
        height: 1.05rem;
        margin-top: .25rem;
        width: 1.05rem;
    }

    .jem-day-timeline-row > .jem-day-timeline-time,
    .jem-day-timeline-row > .jem-day-timeline-point,
    .jem-day-timeline-row > .jem-day-timeline-card {
        grid-row: 1;
    }

    .jem-day-timeline-time {
        align-self: start;
        font-weight: 700;
        line-height: 1.25;
        margin: 0 .85rem 0;
    }

    .jem-day-timeline-time small {
        display: block;
        font-weight: 400;
        opacity: .75;
    }

    .jem-day-timeline-card {
        background: var(--jem-timeline-card-background, transparent);
        border: 1px solid var(--jem-timeline-accent, currentColor);
        border-left: .35rem solid var(--jem-timeline-accent, currentColor);
        border-radius: 6px;
        color: var(--jem-timeline-card-color, inherit);
        margin: 0 .85rem 1.25rem;
        padding: .75rem .9rem;
        transition: background-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .jem-day-timeline-card.is-clickable {
        cursor: pointer;
    }

    .jem-day-timeline-card.is-clickable:hover,
    .jem-day-timeline-card.is-clickable:focus {
        background-color: rgba(31, 91, 153, .075);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--jem-timeline-accent, currentColor) 42%, transparent), 0 .45rem 1rem rgba(17, 24, 39, .13);
        outline: none;
        transform: translateY(-2px);
    }

    .jem-day-timeline-day-alternate .jem-day-timeline-card.is-clickable:hover,
    .jem-day-timeline-day-alternate .jem-day-timeline-card.is-clickable:focus {
        background-color: rgba(31, 91, 153, .12);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--jem-timeline-accent, currentColor) 52%, transparent), 0 .5rem 1.1rem rgba(17, 24, 39, .2);
    }

    .jem-day-timeline-card a {
        color: var(--jem-timeline-card-link-color, inherit);
    }

    .jem-day-timeline-no-frame .jem-day-timeline-card {
        border-color: transparent;
        border-left-color: transparent;
        border-radius: 0;
        margin-bottom: 1rem;
        padding: .25rem .5rem;
    }

    .jem-day-timeline-no-frame .jem-day-timeline-left .jem-day-timeline-card,
    .jem-day-timeline-no-frame.jem-day-timeline-left .jem-day-timeline-card,
    .jem-day-timeline-no-frame .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card,
    .jem-day-timeline-no-frame.jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card {
        border-right-color: transparent;
    }

    .jem-day-timeline-row.featured .jem-day-timeline-card {
        box-shadow: 0 0 0 2px var(--jem-timeline-accent, currentColor);
    }

    .jem-day-timeline-row.featured .jem-day-timeline-card h3 {
        font-weight: 700;
    }

    .jem-day-timeline-card-inner {
        align-items: center;
        display: grid;
        gap: .75rem;
        grid-template-columns: minmax(0, 1fr);
    }

    .jem-day-timeline-card h3 {
        font-size: 1.4rem;
        line-height: 1.3;
        margin: 0;
    }

    .jem-day-timeline-media {
        display: none;
        gap: .35rem;
        grid-template-columns: 1fr;
    }

    .jem-day-timeline-leading-media {
        justify-items: start;
    }

    .jem-day-timeline-venue-media {
        justify-items: end;
    }

    .jem-day-timeline-image {
        display: block;
        margin: 0;
        padding: 0;
    }

    .jem-day-timeline-image img {
        border-radius: 4px;
        display: block;
        height: auto;
        margin: 0;
        max-height: <?php echo (int) $timelineImageHeight; ?>px;
        max-width: <?php echo (int) $timelineImageWidth; ?>px;
        object-fit: contain;
        padding: 0;
        width: auto;
    }

    .jem-day-timeline-type-media {
        align-items: center;
        aspect-ratio: 4 / 3;
        border-radius: 4px;
        display: flex;
        font-size: 2rem;
        justify-content: center;
    }

    .jem-day-timeline-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .25rem .75rem;
        margin: .25rem 0 0;
    }

    .jem-day-timeline-meta-item {
        align-items: center;
        display: inline-flex;
        gap: .25rem;
    }

    .jem-day-timeline-meta-icon {
        opacity: .65;
    }

    .jem-day-timeline-details {
        display: grid;
        gap: .35rem;
    }

    .jem-day-timeline-detail-row {
        display: grid;
        gap: .5rem;
        grid-template-columns: minmax(6rem, max-content) minmax(0, 1fr);
        align-items: start;
    }

    .jem-day-timeline-detail-label {
        font-weight: 700;
    }

    .jem-day-timeline-detail-label::after {
        content: ":";
    }

    .jem-day-timeline-detail-title .jem-day-timeline-detail-value {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .jem-day-timeline-detail-value {
        min-width: 0;
    }

    .jem-day-timeline-detail-description {
        border-top: 1px solid rgba(17, 24, 39, .12);
        margin-top: .35rem;
        padding-top: .55rem;
    }

    .jem-day-timeline-category-badges {
        display: inline-flex;
        flex-wrap: wrap;
        gap: .25rem;
    }

    .jem-day-timeline-category-badge {
        border-radius: 4px;
        display: inline-flex;
        font-weight: 600;
        line-height: 1.2;
        padding: .15rem .45rem;
        text-decoration: none;
    }

    .jem-day-timeline-type-badge {
        align-items: center;
        border-radius: 4px;
        display: inline-flex;
        gap: .35rem;
        margin-top: .25rem;
        padding: .15rem .45rem;
    }

    .jem-day-timeline-intro {
        border-top: 1px solid rgba(17, 24, 39, .12);
        margin: .65rem 0 0;
        opacity: .9;
        padding-top: .55rem;
    }

    .jem-day-timeline-readmore {
        display: inline-block;
        margin-left: .35rem;
        white-space: nowrap;
    }

    .jem-day-timeline-readmore-button {
        border: 1px solid currentColor;
        border-radius: 4px;
        font-size: .9em;
        line-height: 1.2;
        padding: .15rem .45rem;
        text-decoration: none;
    }

    .jem-day-timeline-hour-row .jem-day-timeline-card {
        border-color: transparent;
        min-height: 1.5rem;
        opacity: .55;
    }

    .jem-day-timeline-hour-row .jem-day-timeline-time,
    .jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-time {
        color: #5f6b72;
        grid-column: 1;
        text-align: right;
    }

    .jem-day-timeline-hour-row .jem-day-timeline-card,
    .jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-card {
        display: none;
    }

    .jem-day-timeline-grid .jem-day-timeline-row {
        background: none;
    }

    .jem-day-timeline-grid .jem-day-timeline-row::after {
        content: none;
    }

    .jem-day-timeline-grid .jem-day-timeline-hour-row {
        min-height: 1.8rem;
    }

    .jem-day-timeline-grid .jem-day-timeline-subinterval-row {
        min-height: 1.8rem;
    }

    .jem-day-timeline-subinterval-row .jem-day-timeline-time {
        visibility: hidden;
    }

    .jem-day-timeline-range-start-row .jem-day-timeline-time,
    .jem-day-timeline-range-end-row .jem-day-timeline-time {
        color: #5f6b72;
    }

    .jem-day-timeline-subinterval-row .jem-day-timeline-point {
        background: var(--jem-timeline-line-color, currentColor);
        border: 0;
        border-radius: 0;
        box-shadow: none;
        height: 3px;
        justify-self: center;
        margin-top: .55rem;
        position: relative;
        transform: translateX(0);
        width: .85rem;
    }

    .jem-day-timeline-grid .jem-day-timeline-range-end-row {
        min-height: 1.4rem;
    }

    .jem-day-timeline-range-end-row .jem-day-timeline-point {
        background: var(--jem-timeline-line-color, currentColor);
        border: 0;
        border-radius: 0;
        box-shadow: none;
        height: 4px;
        margin-top: .4rem;
        width: 2rem;
    }

    .jem-day-timeline-range-start-row .jem-day-timeline-point {
        background: var(--jem-timeline-line-color, currentColor);
        border: 0;
        border-radius: 0;
        box-shadow: none;
        height: 4px;
        margin-top: .4rem;
        width: 2rem;
    }

    .jem-day-timeline-type-badge i,
    .jem-day-timeline-type-badge span[class^="fa-"],
    .jem-day-timeline-type-badge span[class*=" fa-"] {
        line-height: 1;
    }

    .jem-day-timeline-right .jem-day-timeline-time,
    .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-time {
        grid-column: 1;
        text-align: right;
    }

    .jem-day-timeline-right .jem-day-timeline-card,
    .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card {
        grid-column: 3;
        text-align: var(--jem-timeline-right-text-align, left);
    }

    .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-meta,
    .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-meta {
        justify-content: var(--jem-timeline-right-meta-justify, flex-start);
    }

    .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-content,
    .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-intro,
    .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-content,
    .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-intro {
        text-align: var(--jem-timeline-right-text-align, left);
    }

    .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-type-badge,
    .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-type-badge {
        margin-left: var(--jem-timeline-right-badge-margin-left, 0);
        margin-right: var(--jem-timeline-right-badge-margin-right, auto);
    }

    .jem-day-timeline-left .jem-day-timeline-time,
    .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-time {
        grid-column: 3;
    }

    .jem-day-timeline-left .jem-day-timeline-card,
    .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card {
        border-left-width: 1px;
        border-right: .35rem solid var(--jem-timeline-accent, currentColor);
        grid-column: 1;
        text-align: var(--jem-timeline-left-text-align, right);
    }

    .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-meta,
    .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-meta {
        justify-content: var(--jem-timeline-left-meta-justify, flex-end);
    }

    .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-content,
    .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-intro,
    .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-content,
    .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-intro {
        text-align: var(--jem-timeline-left-text-align, right);
    }

    .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-type-badge,
    .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-type-badge {
        margin-left: var(--jem-timeline-left-badge-margin-left, auto);
        margin-right: var(--jem-timeline-left-badge-margin-right, 0);
    }

    .jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-time,
    .jem-day-timeline-alternate .jem-day-timeline-row.jem-day-timeline-hour-row:nth-child(even) .jem-day-timeline-time,
    .jem-day-timeline-alternate .jem-day-timeline-row.jem-day-timeline-hour-row:nth-child(odd) .jem-day-timeline-time,
    .jem-day-timeline-left.jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-time,
    .jem-day-timeline-right.jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-time {
        grid-column: 1;
        text-align: right;
    }

    .jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-card,
    .jem-day-timeline-alternate .jem-day-timeline-row.jem-day-timeline-hour-row:nth-child(even) .jem-day-timeline-card,
    .jem-day-timeline-alternate .jem-day-timeline-row.jem-day-timeline-hour-row:nth-child(odd) .jem-day-timeline-card,
    .jem-day-timeline-left.jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-card,
    .jem-day-timeline-right.jem-day-timeline-alternate .jem-day-timeline-hour-row .jem-day-timeline-card {
        display: none;
    }

    @media (min-width: 900px) {
        .jem-day-timeline-card-inner.has-leading-media:not(.has-venue-media) {
            align-items: stretch;
            grid-template-columns: minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px) minmax(0, 1fr);
        }

        .jem-day-timeline-card-inner.has-venue-media:not(.has-leading-media) {
            align-items: stretch;
            grid-template-columns: minmax(0, 1fr) minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px);
        }

        .jem-day-timeline-card-inner.has-leading-media.has-venue-media {
            align-items: stretch;
            grid-template-columns: minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px) minmax(0, 1fr) minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px);
        }

        .jem-day-timeline-media {
            display: grid;
        }

        .jem-day-timeline-venue-media {
            grid-column: 3;
        }

        .jem-day-timeline-card-inner.has-venue-media:not(.has-leading-media) .jem-day-timeline-content {
            grid-column: 1;
        }

        .jem-day-timeline-card-inner.has-leading-media .jem-day-timeline-content {
            grid-column: 2;
        }

        .jem-day-timeline-card-inner.has-leading-media.has-venue-media .jem-day-timeline-content {
            grid-column: 2;
        }

        .jem-day-timeline-alternate .jem-day-timeline-card-inner.has-leading-media.has-venue-media {
            grid-template-columns: minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px) minmax(0, 1fr);
        }

        .jem-day-timeline-alternate .jem-day-timeline-card-inner.has-leading-media.has-venue-media .jem-day-timeline-leading-media {
            grid-column: 1;
            grid-row: 1;
        }

        .jem-day-timeline-alternate .jem-day-timeline-card-inner.has-leading-media.has-venue-media .jem-day-timeline-venue-media {
            grid-column: 1;
            grid-row: 2;
        }

        .jem-day-timeline-alternate .jem-day-timeline-card-inner.has-leading-media.has-venue-media .jem-day-timeline-content {
            grid-column: 2;
            grid-row: 1 / span 2;
        }

        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-leading-media:not(.has-venue-media) {
            grid-template-columns: minmax(0, 1fr) minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px);
        }

        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-leading-media.has-venue-media {
            grid-template-columns: minmax(0, 1fr) minmax(5rem, <?php echo (int) $timelineImageColumnWidth; ?>px);
        }

        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-leading-media .jem-day-timeline-leading-media,
        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-venue-media .jem-day-timeline-venue-media {
            grid-column: 2;
        }

        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-leading-media.has-venue-media .jem-day-timeline-venue-media {
            grid-row: 2;
        }

        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-leading-media .jem-day-timeline-content,
        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-venue-media .jem-day-timeline-content {
            grid-column: 1;
        }
    }

    .jem-day-timeline-all-day {
        border: 1px solid currentColor;
        border-radius: 6px;
        margin: 1rem 0;
        padding: .85rem 1rem;
    }

    @media (max-width: 720px) {
        .jem-day-timeline-row {
            grid-template-columns: 6.5rem 2rem minmax(0, 1fr);
        }

        .jem-day-timeline-row::before {
            content: none;
        }

        .jem-day-timeline-list::before,
        .jem-day-timeline-right .jem-day-timeline-list::before,
        .jem-day-timeline-left .jem-day-timeline-list::before,
        .jem-day-timeline-alternate .jem-day-timeline-list::before {
            left: 7.5rem;
        }

        .jem-day-timeline-grid::after,
        .jem-day-timeline-right .jem-day-timeline-grid::after,
        .jem-day-timeline-left .jem-day-timeline-grid::after,
        .jem-day-timeline-alternate .jem-day-timeline-grid::after {
            left: 7.5rem;
        }

        .jem-day-timeline-left .jem-day-timeline-time,
        .jem-day-timeline-right .jem-day-timeline-time,
        .jem-day-timeline-alternate .jem-day-timeline-row .jem-day-timeline-time {
            grid-column: 1;
            margin-left: 0;
            text-align: right;
        }

        .jem-day-timeline-left .jem-day-timeline-card,
        .jem-day-timeline-right .jem-day-timeline-card,
        .jem-day-timeline-alternate .jem-day-timeline-row .jem-day-timeline-card {
            border-left: .35rem solid var(--jem-timeline-accent, currentColor);
            border-right-width: 1px;
            grid-column: 3;
            margin-right: 0;
        }

        .jem-day-timeline-left .jem-day-timeline-card,
        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card {
            text-align: var(--jem-timeline-left-text-align, left);
        }

        .jem-day-timeline-right .jem-day-timeline-card,
        .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card {
            text-align: var(--jem-timeline-right-text-align, left);
        }

        .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-meta,
        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-meta {
            justify-content: var(--jem-timeline-left-meta-justify, flex-start);
        }

        .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-meta,
        .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-meta {
            justify-content: var(--jem-timeline-right-meta-justify, flex-start);
        }

        .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-content,
        .jem-day-timeline-left .jem-day-timeline-card .jem-day-timeline-intro,
        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-content,
        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card .jem-day-timeline-intro {
            text-align: var(--jem-timeline-left-text-align, left);
        }

        .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-content,
        .jem-day-timeline-right .jem-day-timeline-card .jem-day-timeline-intro,
        .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-content,
        .jem-day-timeline-alternate .jem-day-timeline-side-right .jem-day-timeline-card .jem-day-timeline-intro {
            text-align: var(--jem-timeline-right-text-align, left);
        }
    }
</style>
<script>
document.addEventListener('click', function (event) {
    var card = event.target.closest ? event.target.closest('.jem-day-timeline-card[data-href]') : null;
    if (!card || event.target.closest('a, button, input, select, textarea')) {
        return;
    }
    window.location.href = card.getAttribute('data-href');
});
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }
    var card = event.target.closest ? event.target.closest('.jem-day-timeline-card[data-href]') : null;
    if (!card || event.target.closest('a, button, input, select, textarea')) {
        return;
    }
    event.preventDefault();
    window.location.href = card.getAttribute('data-href');
});
</script>

<div id="jem" class="jem_day jem_day_timeline jem-day-timeline-<?php echo $this->escape($timelineSide); ?> jem-day-timeline-layout-<?php echo $this->escape($cardLayout); ?> <?php echo $eventFrame ? 'jem-day-timeline-framed' : 'jem-day-timeline-no-frame'; ?><?php echo $this->pageclass_sfx; ?>" style="--jem-timeline-right-text-align: <?php echo $this->escape($timelineRightTextAlign); ?>; --jem-timeline-left-text-align: <?php echo $this->escape($timelineLeftTextAlign); ?>; --jem-timeline-right-meta-justify: <?php echo $this->escape($timelineJustifyMap[$timelineRightTextAlign]); ?>; --jem-timeline-left-meta-justify: <?php echo $this->escape($timelineJustifyMap[$timelineLeftTextAlign]); ?>; --jem-timeline-right-badge-margin-left: <?php echo $timelineRightTextAlign === 'right' ? 'auto' : ($timelineRightTextAlign === 'center' ? 'auto' : '0'); ?>; --jem-timeline-right-badge-margin-right: <?php echo $timelineRightTextAlign === 'left' ? 'auto' : ($timelineRightTextAlign === 'center' ? 'auto' : '0'); ?>; --jem-timeline-left-badge-margin-left: <?php echo $timelineLeftTextAlign === 'right' ? 'auto' : ($timelineLeftTextAlign === 'center' ? 'auto' : '0'); ?>; --jem-timeline-left-badge-margin-right: <?php echo $timelineLeftTextAlign === 'left' ? 'auto' : ($timelineLeftTextAlign === 'center' ? 'auto' : '0'); ?>;">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link, 'today_link' => $todayLink);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
        <a class="jem-day-timeline-layout-toggle" href="<?php echo $layoutToggleLink; ?>" title="<?php echo $this->escape($layoutToggleText); ?>" aria-label="<?php echo $this->escape($layoutToggleText); ?>">
            <span class="fa <?php echo $this->escape($layoutToggleIcon); ?>" aria-hidden="true"></span>
        </a>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
    <h1 class="componentheading">
        <?php echo $this->escape($pageHeading); ?>
    </h1>
    <?php endif; ?>

    <div class="clr"> </div>

    <nav class="jem-calendar-navigation jem-timetable-navigation jem-day-timeline-navigation" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_DAY_NAVIGATION'); ?>">
        <?php if ($showWeekNavigation) : ?>
            <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $previousWeekLink; ?>" rel="prev" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_PREVIOUS_WEEK'); ?>">
                <?php echo jemhtml::icon('com_jem/prev.webp', 'fa-solid fa-angles-left jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_PREVIOUS_WEEK'), array('class' => 'jem-calendar-nav-icon')); ?>
            </a>
        <?php endif; ?>
        <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $previousLink; ?>" rel="prev" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'); ?>">
            <?php echo jemhtml::icon('com_jem/prev.webp', 'fa-solid fa-angle-left jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'), array('class' => 'jem-calendar-nav-icon')); ?>
        </a>
        <div class="jem-calendar-nav-title jem-timetable-nav-title">
            <?php echo $navigationTitle; ?>
        </div>
        <a class="jem-calendar-nav-link jem-timetable-nav-link jem-day-timeline-nav-today" href="<?php echo $todayLink; ?>" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_TODAY'); ?>">
            <?php echo Text::_('COM_JEM_TIMETABLE_TODAY'); ?>
        </a>
        <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $nextLink; ?>" rel="next" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_NEXT_DAY'); ?>">
            <?php echo jemhtml::icon('com_jem/next.webp', 'fa-solid fa-angle-right jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_NEXT_DAY'), array('class' => 'jem-calendar-nav-icon')); ?>
        </a>
        <?php if ($showWeekNavigation) : ?>
            <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $nextWeekLink; ?>" rel="next" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_NEXT_WEEK'); ?>">
                <?php echo jemhtml::icon('com_jem/next.webp', 'fa-solid fa-angles-right jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_NEXT_WEEK'), array('class' => 'jem-calendar-nav-icon')); ?>
            </a>
        <?php endif; ?>
        <form class="jem-day-timeline-window-form" action="<?php echo $windowAction; ?>" method="get">
            <input type="hidden" name="option" value="com_jem">
            <input type="hidden" name="view" value="day">
            <input type="hidden" name="layout" value="timeline">
            <input type="hidden" name="id" value="<?php echo $currentDate->format('Ymd'); ?>">
            <?php if ($itemId > 0) : ?>
                <input type="hidden" name="Itemid" value="<?php echo (int) $itemId; ?>">
            <?php endif; ?>
            <?php if ($catId > 0) : ?>
                <input type="hidden" name="catid" value="<?php echo (int) $catId; ?>">
            <?php endif; ?>
            <?php if ($locId > 0) : ?>
                <input type="hidden" name="locid" value="<?php echo (int) $locId; ?>">
            <?php endif; ?>
            <?php if ($layoutOverride !== '') : ?>
                <input type="hidden" name="jem_timeline_layout" value="<?php echo $this->escape($cardLayout); ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="jem-day-timeline-days-window"><?php echo Text::_('COM_JEM_TIMELINE_DAYS_TO_SHOW_LABEL'); ?></label>
            <select id="jem-day-timeline-days-window" name="timeline_days_to_show" class="form-select jem-day-timeline-window-select" onchange="this.form.submit()">
                <?php foreach ($timelineDayOptions as $daysOption) : ?>
                    <option value="<?php echo $daysOption; ?>"<?php echo $daysOption === $timelineDaysToShow ? ' selected="selected"' : ''; ?>>
                        <?php echo $daysOption === 1 ? Text::_('COM_JEM_TIMELINE_DAYS_WINDOW_1') : Text::sprintf('COM_JEM_TIMELINE_DAYS_WINDOW_MORE', $daysOption); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript>
                <button type="submit" class="btn btn-primary"><?php echo Text::_('JAPPLY'); ?></button>
            </noscript>
        </form>
    </nav>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)) : ?>
        <div class="jem-timetable-empty"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></div>
    <?php else : ?>
        <?php $timelineEventIndex = 0; ?>
        <?php foreach ($dayRange as $dayDate => $dayData) : ?>
            <?php if (!$showEmptyDays && empty($dayData['timedRows']) && empty($dayData['allDayRows'])) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <?php
            $specialDayColor = '';
            if (!empty($timelineSpecialDays[$dayDate][0]['color']) && preg_match('/^#[0-9a-f]{6}$/i', (string) $timelineSpecialDays[$dayDate][0]['color'])) {
                $specialDayColor = (string) $timelineSpecialDays[$dayDate][0]['color'];
            }

            $dayClasses = array('jem-day-timeline-day');
            if (in_array($dayBackground, array('special_day', 'alternate_special_day'), true) && $specialDayColor !== '') {
                $dayClasses[] = 'jem-day-timeline-day-special';
            } elseif (in_array($dayBackground, array('alternate', 'alternate_special_day'), true)) {
                $dayClasses[] = 'jem-day-timeline-day-alternate';
            }
            ?>
            <section class="<?php echo $this->escape(implode(' ', $dayClasses)); ?>" style="--jem-timeline-width: <?php echo (int) $timelineWidth; ?>%; --jem-timeline-alternate-day-background: <?php echo $this->escape($alternateDayBackgroundColor); ?>;<?php echo $specialDayColor !== '' ? ' --jem-timeline-special-day-background: ' . $this->escape($specialDayColor) . ';' : ''; ?>">
                <div class="jem-day-timeline-day-label">
                    <span><?php echo HTMLHelper::_('date', $dayDate, Text::_('DATE_FORMAT_LC3')); ?></span>
                </div>

                <?php if (!empty($dayData['allDayRows'])) : ?>
                    <div class="jem-day-timeline-all-day">
                        <strong><?php echo Text::_('COM_JEM_TIMETABLE_ALL_DAY'); ?></strong>
                        <div class="jem-day-timeline-meta">
                            <?php foreach ($dayData['allDayRows'] as $row) : ?>
                                <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo $this->escape($row->title); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($dayData['timedRows']) && empty($dayData['allDayRows']) && $hourDisplay !== 'all_hours') : ?>
                    <div class="jem-day-timeline-day-empty"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></div>
                <?php elseif (!empty($dayData['timedRows']) || $hourDisplay === 'all_hours') : ?>
                    <div class="jem-day-timeline-list<?php echo $hourDisplay === 'all_hours' ? ' jem-day-timeline-grid' : ''; ?>" style="--jem-timeline-width: <?php echo (int) $timelineWidth; ?>%; --jem-timeline-line-color: <?php echo $this->escape($timelineLineColor); ?>; --jem-timeline-line-style: <?php echo $this->escape($timelineLineStyle); ?>;">
                <?php
                if ($hourDisplay === 'all_hours') {
                    $timelineRenderRows = range($rangeStartMinutes, $rangeEndMinutes - 1, $gridIntervalMinutes);

                    if ($gridSubintervalMinutes > 0) {
                        for ($mark = $rangeStartMinutes; $mark < $rangeEndMinutes; $mark += $gridIntervalMinutes) {
                            for ($submark = $mark + $gridSubintervalMinutes; $submark < min($mark + $gridIntervalMinutes, $rangeEndMinutes); $submark += $gridSubintervalMinutes) {
                                $timelineRenderRows[] = $submark;
                            }
                        }
                    }

                    $timelineRenderRows = array_unique(array_merge($timelineRenderRows, array_keys($dayData['timedRowsByHour'])));
                    sort($timelineRenderRows, SORT_NUMERIC);
                } else {
                    $timelineRenderRows = $dayData['timedRows'];
                }
                foreach ($timelineRenderRows as $timelineItem) :
                    $hourRows = array();
                    if ($hourDisplay === 'all_hours') {
                        $slotMinutes = (int) $timelineItem;
                        $hourRows = $dayData['timedRowsByHour'][$slotMinutes] ?? array();
                        if (empty($hourRows)) :
                            $isMajorMark = (($slotMinutes - $rangeStartMinutes) % $gridIntervalMinutes) === 0;
                ?>
                    <article class="jem-day-timeline-row jem-day-timeline-hour-row<?php echo $isMajorMark ? '' : ' jem-day-timeline-subinterval-row'; ?><?php echo $slotMinutes === $rangeStartMinutes ? ' jem-day-timeline-range-start-row' : ''; ?>">
                        <div class="jem-day-timeline-time">
                            <span><?php echo $isMajorMark ? $this->escape($formatGridMinutes($slotMinutes)) : '&nbsp;'; ?></span>
                        </div>
                        <span class="jem-day-timeline-point" aria-hidden="true"></span>
                        <div class="jem-day-timeline-card" aria-hidden="true"></div>
                    </article>
                <?php
                            continue;
                        endif;
                    } else {
                        $hourRows = array($timelineItem);
                    }
                    foreach ($hourRows as $row) :
                ?>
                    <?php
                    $startTime = $getDisplayTimeValue($row, 'times');
                    $endTime = $getDisplayTimeValue($row, 'endtimes', $startTime + 3600);
                    if ($endTime <= $startTime) {
                        $endTime = $startTime + 3600;
                    }
                    $durationHours = max(1, (int) ceil(($endTime - $startTime) / 3600));
                    $rowMinHeight = $eventHeightMode === 'duration' ? max(5.5, min(18, $durationHours * 3.5)) : 5.5;
                    $timeRange = $formatGridTime($startTime) . ' - ' . $formatGridTime($endTime);
                    $accentColor = $getEventBackgroundColor($row);
                    $cardBackgroundColor = $eventColorMode === 'background' ? $accentColor : 'transparent';
                    $cardTextColor = $eventColorMode === 'background' ? $getContrastColor($accentColor) : 'inherit';
                    $cardLinkColor = $eventColorMode === 'background' ? $cardTextColor : 'inherit';
                    $categoryList = $showCategory ? trim(implode(', ', JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))) : '';
                    $categoryBadges = array();
                    if ($showCategory && $categoryDisplay === 'badge') {
                        foreach ((array) $row->categories as $category) {
                            $categoryName = trim((string) ($category->catname ?? ''));
                            if ($categoryName === '') {
                                continue;
                            }

                            $categoryColor = $normaliseTimelineColor($category->color ?? '');
                            $categoryColor = $categoryColor !== '' ? $categoryColor : '#6c757d';
                            $categoryTextColor = $getContrastColor($categoryColor);
                            $categorySlug = $category->catslug ?? $category->slug ?? $category->id ?? '';
                            $categoryLabel = $this->escape($categoryName);
                            $categoryStyle = 'background: ' . $this->escape($categoryColor) . '; color: ' . $this->escape($categoryTextColor) . ';';

                            if ($this->jemsettings->catlinklist && $categorySlug !== '') {
                                $categoryBadges[] = '<a class="jem-day-timeline-category-badge" style="' . $categoryStyle . '" href="' . Route::_(JemHelperRoute::getCategoryRoute($categorySlug)) . '">' . $categoryLabel . '</a>';
                            } else {
                                $categoryBadges[] = '<span class="jem-day-timeline-category-badge" style="' . $categoryStyle . '">' . $categoryLabel . '</span>';
                            }
                        }
                    }
                    JemOutput::translateType($row, 'type_');
                    $typeName = trim((string) ($row->type_name ?? ''));
                    $typeIcon = trim((string) ($row->type_icon ?? ''));
                    $typeColor = trim((string) ($row->type_color ?? ''));
                    if ($typeColor === '' || !preg_match('/^#[0-9a-f]{3,6}$/i', $typeColor)) {
                        $typeColor = $accentColor;
                    }
                    $typeTextColor = $getContrastColor($typeColor);
                    $eventImage = $showEventImage ? $getEventImage($row) : '';
                    $venueImage = $showVenueImage ? $getVenueImage($row) : '';
                    $hasLeadingEventMedia = $eventImage !== '' && $eventImagePosition === 'left';
                    $hasTrailingEventMedia = $eventImage !== '' && $eventImagePosition === 'right';
                    $hasLeadingVenueMedia = $venueImage !== '' && $venueImagePosition === 'left';
                    $hasTrailingVenueMedia = $venueImage !== '' && $venueImagePosition === 'right';
                    $hasLeadingMedia = $hasLeadingEventMedia || $hasLeadingVenueMedia;
                    $hasTrailingMedia = $hasTrailingEventMedia || $hasTrailingVenueMedia;
                    $hasMedia = $hasLeadingMedia || $hasTrailingMedia;
                    $eventUrl = Route::_(JemHelperRoute::getEventRoute($row->slug));
                    $intro = $showEventIntro ? $truncateText($row->introtext ?? '', $eventIntroLimit) : array('text' => '', 'truncated' => false);
                    $introText = (string) $intro['text'];
                    $introTruncated = !empty($intro['truncated']);
                    $categoryOutput = $categoryDisplay === 'badge' ? '<span class="jem-day-timeline-category-badges">' . implode('', $categoryBadges) . '</span>' : $categoryList;
                    $venueOutput = array();
                    if ($showVenue && !empty($row->venue)) {
                        $venueOutput[] = $this->escape($row->venue);
                    }
                    if ($showVenue && !empty($row->city)) {
                        $venueOutput[] = $this->escape($row->city);
                    }
                    $typeOutput = '';
                    if ($showType && $typeName !== '') {
                        $typeOutput = '<span class="jem-day-timeline-type-badge" style="background: ' . $this->escape($typeColor) . '; color: ' . $this->escape($typeTextColor) . ';">'
                            . ($typeIcon !== '' ? '<span class="' . $this->escape($typeIcon) . '" aria-hidden="true"></span>' : '')
                            . '<span>' . $this->escape($typeName) . '</span></span>';
                    }
                    $introOutput = '';
                    if ($introText !== '') {
                        $introOutput = $this->escape($introText);
                        if ($showEventReadmore && $introTruncated) {
                            $introOutput .= ' <a class="jem-day-timeline-readmore' . ($readmoreStyle === 'button' ? ' jem-day-timeline-readmore-button' : '') . '" href="' . $eventUrl . '">' . Text::_('COM_JEM_TIMELINE_READ_MORE') . '</a>';
                        }
                    }
                    $detailsDescription = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($row->introtext ?? ''))));
                    $timelineEventIndex++;
                    $timelineEventSide = ($timelineSide === 'left' || ($timelineSide === 'alternate' && ($timelineEventIndex % 2) === 0)) ? 'left' : 'right';
                    ?>
                    <article class="jem-day-timeline-row jem-day-timeline-side-<?php echo $this->escape($timelineEventSide); ?><?php echo !empty($row->featured) ? ' featured' : ''; ?> event_id<?php echo $this->escape($row->id); ?>" style="--jem-timeline-accent: <?php echo $this->escape($accentColor); ?>; --jem-timeline-card-background: <?php echo $this->escape($cardBackgroundColor); ?>; --jem-timeline-card-color: <?php echo $this->escape($cardTextColor); ?>; --jem-timeline-card-link-color: <?php echo $this->escape($cardLinkColor); ?>; min-height: <?php echo $this->escape(number_format($rowMinHeight, 1, '.', '')); ?>rem;" itemscope="itemscope" itemtype="https://schema.org/Event">
                        <div class="jem-day-timeline-time">
                            <span><?php echo $this->escape($formatGridTime($startTime)); ?></span>
                            <small><?php echo $this->escape($formatGridTime($endTime)); ?></small>
                        </div>
                        <span class="jem-day-timeline-point" aria-hidden="true"></span>
                        <div class="jem-day-timeline-card is-clickable" data-href="<?php echo $this->escape($eventUrl); ?>" role="link" tabindex="0">
                            <div class="jem-day-timeline-card-inner<?php echo $hasMedia ? ' has-media' : ''; ?><?php echo $hasLeadingMedia ? ' has-leading-media' : ''; ?><?php echo $hasTrailingMedia ? ' has-venue-media' : ''; ?>">
                                <?php if ($hasLeadingMedia) : ?>
                                    <div class="jem-day-timeline-media jem-day-timeline-leading-media">
                                        <?php if ($hasLeadingEventMedia) : ?>
                                            <a class="jem-day-timeline-image" href="<?php echo $eventUrl; ?>" aria-hidden="true" tabindex="-1">
                                                <?php echo HTMLHelper::image($eventImage, $this->escape($row->title), array('loading' => 'lazy')); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($hasLeadingVenueMedia) : ?>
                                            <a class="jem-day-timeline-image" href="<?php echo $eventUrl; ?>" aria-hidden="true" tabindex="-1">
                                                <?php echo HTMLHelper::image($venueImage, $this->escape($row->venue ?: $row->title), array('loading' => 'lazy')); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="jem-day-timeline-content">
                                    <?php if ($cardLayout === 'details') : ?>
                                        <div class="jem-day-timeline-details">
                                            <div class="jem-day-timeline-detail-row jem-day-timeline-detail-title">
                                                <span class="jem-day-timeline-detail-label"><?php echo Text::_('COM_JEM_TITLE'); ?></span>
                                                <span class="jem-day-timeline-detail-value">
                                                    <a href="<?php echo $eventUrl; ?>" title="<?php echo $buildTooltip($row, $timeRange); ?>" aria-label="<?php echo $buildTooltip($row, $timeRange); ?>" itemprop="url">
                                                        <span itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                                    </a>
                                                    <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                                                </span>
                                            </div>
                                            <?php if ($categoryOutput !== '') : ?>
                                                <div class="jem-day-timeline-detail-row">
                                                    <span class="jem-day-timeline-detail-label"><?php echo Text::_('COM_JEM_CATEGORY'); ?></span>
                                                    <span class="jem-day-timeline-detail-value"><?php echo $categoryOutput; ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($typeOutput !== '') : ?>
                                                <div class="jem-day-timeline-detail-row">
                                                    <span class="jem-day-timeline-detail-label"><?php echo Text::_('COM_JEM_TYPE'); ?></span>
                                                    <span class="jem-day-timeline-detail-value"><?php echo $typeOutput; ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($venueOutput)) : ?>
                                                <div class="jem-day-timeline-detail-row">
                                                    <span class="jem-day-timeline-detail-label"><?php echo Text::_('COM_JEM_VENUE'); ?></span>
                                                    <span class="jem-day-timeline-detail-value"><?php echo implode(' ', $venueOutput); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($showEventIntro && $detailsDescription !== '') : ?>
                                                <div class="jem-day-timeline-detail-row jem-day-timeline-detail-description">
                                                    <span class="jem-day-timeline-detail-label"><?php echo Text::_('COM_JEM_DESCRIPTION'); ?></span>
                                                    <span class="jem-day-timeline-detail-value"><?php echo $this->escape($detailsDescription); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <h3>
                                            <a href="<?php echo $eventUrl; ?>" title="<?php echo $buildTooltip($row, $timeRange); ?>" aria-label="<?php echo $buildTooltip($row, $timeRange); ?>" itemprop="url">
                                                <span itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                            </a>
                                            <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                                        </h3>
                                        <div class="jem-day-timeline-meta">
                                            <?php if ($categoryOutput !== '') : ?>
                                                <span class="jem-day-timeline-meta-item"><?php echo $showDetailIcons ? '<span class="fa fa-folder-open jem-day-timeline-meta-icon" aria-hidden="true"></span>' : ''; ?><?php echo $categoryOutput; ?></span>
                                            <?php endif; ?>
                                            <?php if ($showVenue && !empty($row->venue)) : ?>
                                                <span class="jem-day-timeline-meta-item"><?php echo $showDetailIcons ? '<span class="fa fa-map-marker-alt jem-day-timeline-meta-icon" aria-hidden="true"></span>' : ''; ?><?php echo $this->escape($row->venue); ?></span>
                                            <?php endif; ?>
                                            <?php if ($showVenue && !empty($row->city)) : ?>
                                                <span class="jem-day-timeline-meta-item"><?php echo $showDetailIcons ? '<span class="fa fa-city jem-day-timeline-meta-icon" aria-hidden="true"></span>' : ''; ?><?php echo $this->escape($row->city); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php echo $typeOutput; ?>
                                        <?php if ($introOutput !== '') : ?>
                                            <div class="jem-day-timeline-intro"><?php echo $introOutput; ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasTrailingMedia) : ?>
                                    <div class="jem-day-timeline-media jem-day-timeline-venue-media">
                                        <?php if ($hasTrailingEventMedia) : ?>
                                            <a class="jem-day-timeline-image" href="<?php echo $eventUrl; ?>" aria-hidden="true" tabindex="-1">
                                                <?php echo HTMLHelper::image($eventImage, $this->escape($row->title), array('loading' => 'lazy')); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($hasTrailingVenueMedia) : ?>
                                            <a class="jem-day-timeline-image" href="<?php echo $eventUrl; ?>" aria-hidden="true" tabindex="-1">
                                                <?php echo HTMLHelper::image($venueImage, $this->escape($row->venue ?: $row->title), array('loading' => 'lazy')); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                        </div>
                    </article>
                <?php endforeach; endforeach; ?>
                    <?php if ($hourDisplay === 'all_hours') : ?>
                    <article class="jem-day-timeline-row jem-day-timeline-hour-row jem-day-timeline-range-end-row">
                        <div class="jem-day-timeline-time">
                            <span><?php echo $this->escape($formatGridMinutes($rangeEndMinutes)); ?></span>
                        </div>
                        <span class="jem-day-timeline-point" aria-hidden="true"></span>
                        <div class="jem-day-timeline-card" aria-hidden="true"></div>
                    </article>
                    <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
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
