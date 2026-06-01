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

$getCategoryColor = static function ($row, $fallback = '#2f6f73') {
    foreach ((array) $row->categories as $category) {
        $color = trim((string) ($category->color ?? ''));

        if ($color !== '' && preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
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
        foreach (array('l_color', 'venuecolor') as $field) {
            $color = trim((string) ($row->$field ?? ''));

            if ($color !== '' && preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
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

    if ($text === '' || $limit === 0 || function_exists('mb_strlen') && mb_strlen($text) <= $limit || !function_exists('mb_strlen') && strlen($text) <= $limit) {
        return $text;
    }

    if (function_exists('mb_substr')) {
        return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    }

    return rtrim(substr($text, 0, $limit - 1)) . '...';
};

$rows = is_array($this->rows) ? $this->rows : array();
usort($rows, static function ($left, $right) use ($getDisplayTimeValue) {
    $dateCompare = strcmp((string) ($left->dates ?? ''), (string) ($right->dates ?? ''));

    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    return $getDisplayTimeValue($left, 'times', PHP_INT_MAX) <=> $getDisplayTimeValue($right, 'times', PHP_INT_MAX);
});

$timelineSide = (string) $this->params->get('timeline_side', 'right');
$timelineSide = in_array($timelineSide, array('right', 'left', 'alternate'), true) ? $timelineSide : 'right';
$timelineRightTextAlign = (string) $this->params->get('timeline_right_text_align', 'left');
$timelineRightTextAlign = in_array($timelineRightTextAlign, array('left', 'center', 'right'), true) ? $timelineRightTextAlign : 'left';
$timelineLeftTextAlign = (string) $this->params->get('timeline_left_text_align', 'right');
$timelineLeftTextAlign = in_array($timelineLeftTextAlign, array('left', 'center', 'right'), true) ? $timelineLeftTextAlign : 'right';
$timelineJustifyMap = array('left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end');
$eventFrame = (bool) $this->params->get('timeline_event_frame', 1);
$eventColorMode = (string) $this->params->get('timeline_event_color_mode', 'bar');
$eventColorMode = in_array($eventColorMode, array('bar', 'background'), true) ? $eventColorMode : 'bar';
$timelineWidth = max(40, min(100, (int) $this->params->get('timeline_width', 80)));
$timelineLineColor = trim((string) $this->params->get('timeline_line_color', '#6c757d'));
if ($timelineLineColor === '' || !preg_match('/^#[0-9a-f]{3,6}$/i', $timelineLineColor)) {
    $timelineLineColor = '#6c757d';
}
$timelineLineStyle = (string) $this->params->get('timeline_line_style', 'solid');
$timelineLineStyle = in_array($timelineLineStyle, array('solid', 'dotted', 'dashed', 'double', 'groove', 'ridge'), true) ? $timelineLineStyle : 'solid';
$legacyMediaSource = (string) $this->params->get('timeline_media_source', $this->params->get('timeline_show_images', 1) ? 'event' : 'none');
$legacyMediaSource = in_array($legacyMediaSource, array('none', 'event', 'venue', 'type_icon'), true) ? $legacyMediaSource : 'event';
$showEventImage = (bool) $this->params->get('timeline_show_event_image', $legacyMediaSource === 'event');
$showVenueImage = (bool) $this->params->get('timeline_show_venue_image', $legacyMediaSource === 'venue');
$showCategory = (bool) $this->params->get('timeline_show_category', 1);
$showVenue = (bool) $this->params->get('timeline_show_venue', 1);
$showType = (bool) $this->params->get('timeline_show_type', 1);
$showEventIntro = (bool) $this->params->get('timeline_show_event_intro', 0);
$eventIntroLimit = max(0, (int) $this->params->get('timeline_event_intro_limit', 160));
$hourDisplay = $this->params->get('timetable_hour_display', 'event_hours');
$hourDisplay = in_array($hourDisplay, array('event_hours', 'all_hours'), true) ? $hourDisplay : 'event_hours';
$rangeStartMinutes = $parseGridTime($this->params->get('timetable_range_start', '08:00'), 8 * 60);
$rangeEndMinutes = $parseGridTime($this->params->get('timetable_range_end', '22:00'), 22 * 60);
$gridIntervalMinutes = max(5, min(240, (int) $this->params->get('timetable_grid_interval', 60)));
$gridSubintervalMinutes = max(0, min(120, (int) $this->params->get('timetable_grid_subinterval', 15)));
if ($gridSubintervalMinutes > 0 && $gridSubintervalMinutes >= $gridIntervalMinutes) {
    $gridSubintervalMinutes = 0;
}
if ($rangeEndMinutes <= $rangeStartMinutes) {
    $rangeEndMinutes = min(1440, $rangeStartMinutes + $gridIntervalMinutes);
}

$timelineDaysToShow = max(1, min(60, (int) $this->params->get('timeline_days_to_show', 1)));
$showEmptyDays = (bool) $this->params->get('timeline_show_empty_days', 1);
$currentDate = new DateTimeImmutable($this->day ?? date('Y-m-d'));
$rangeEndDate = $currentDate->modify('+' . ($timelineDaysToShow - 1) . ' days');
$previousDate = $currentDate->modify('-' . $timelineDaysToShow . ' days');
$nextDate = $currentDate->modify('+' . $timelineDaysToShow . ' days');
$input = Joomla\CMS\Factory::getApplication()->input;
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
$previousLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $previousDate->format('Ymd') . $extraLink);
$nextLink = Route::_('index.php?option=com_jem&view=day&layout=timeline&id=' . $nextDate->format('Ymd') . $extraLink);
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
    $rowDate = (string) ($row->dates ?? '');

    if (!isset($dayRange[$rowDate])) {
        continue;
    }

    $startTime = $getDisplayTimeValue($row, 'times');

    if (!$startTime) {
        $dayRange[$rowDate]['allDayRows'][] = $row;
        continue;
    }

    if ($hourDisplay === 'event_hours') {
        $eventMinutes = ((int) date('G', $startTime) * 60) + (int) date('i', $startTime);

        if ($eventMinutes < $rangeStartMinutes || $eventMinutes >= $rangeEndMinutes) {
            continue;
        }
    }

    $dayRange[$rowDate]['timedRows'][] = $row;
    $eventMinutes = ((int) date('G', $startTime) * 60) + (int) date('i', $startTime);
    $dayRange[$rowDate]['timedRowsByHour'][$eventMinutes][] = $row;
}
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

    #jem .jem-day-timeline-navigation .jem-calendar-nav-title {
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.2;
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

    #jem .jem-day-timeline-navigation .jem-calendar-nav-icon img,
    #jem .jem-day-timeline-navigation img.jem-calendar-nav-icon {
        height: 1rem;
        width: 1rem;
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

    .jem-day-timeline-day:nth-of-type(even) {
        background: rgba(127, 127, 127, .06);
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
        font-size: 1.1rem;
        line-height: 1.3;
        margin: 0;
    }

    .jem-day-timeline-media {
        display: none;
        gap: .35rem;
        grid-template-columns: 1fr;
    }

    .jem-day-timeline-image {
        display: block;
        height: 100%;
        margin: 0;
        min-height: 3.5rem;
        padding: 0;
    }

    .jem-day-timeline-image img {
        border-radius: 4px;
        display: block;
        height: 100%;
        margin: 0;
        max-height: 7rem;
        object-fit: contain;
        padding: 0;
        width: 100%;
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

    .jem-day-timeline-type-badge {
        align-items: center;
        border-radius: 4px;
        display: inline-flex;
        gap: .35rem;
        margin-top: .25rem;
        padding: .15rem .45rem;
    }

    .jem-day-timeline-intro {
        margin: .4rem 0 0;
        opacity: .9;
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
            grid-template-columns: minmax(5rem, 8rem) minmax(0, 1fr);
        }

        .jem-day-timeline-card-inner.has-venue-media:not(.has-leading-media) {
            align-items: stretch;
            grid-template-columns: minmax(0, 1fr) minmax(5rem, 8rem);
        }

        .jem-day-timeline-card-inner.has-leading-media.has-venue-media {
            align-items: stretch;
            grid-template-columns: minmax(5rem, 8rem) minmax(0, 1fr) minmax(5rem, 8rem);
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
            grid-template-columns: minmax(5rem, 8rem) minmax(0, 1fr);
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
            grid-template-columns: minmax(0, 1fr) minmax(5rem, 8rem);
        }

        .jem-day-timeline-alternate .jem-day-timeline-side-left .jem-day-timeline-card-inner.has-leading-media.has-venue-media {
            grid-template-columns: minmax(0, 1fr) minmax(5rem, 8rem);
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

<div id="jem" class="jem_day jem_day_timeline jem-day-timeline-<?php echo $this->escape($timelineSide); ?> <?php echo $eventFrame ? 'jem-day-timeline-framed' : 'jem-day-timeline-no-frame'; ?><?php echo $this->pageclass_sfx; ?>" style="--jem-timeline-right-text-align: <?php echo $this->escape($timelineRightTextAlign); ?>; --jem-timeline-left-text-align: <?php echo $this->escape($timelineLeftTextAlign); ?>; --jem-timeline-right-meta-justify: <?php echo $this->escape($timelineJustifyMap[$timelineRightTextAlign]); ?>; --jem-timeline-left-meta-justify: <?php echo $this->escape($timelineJustifyMap[$timelineLeftTextAlign]); ?>; --jem-timeline-right-badge-margin-left: <?php echo $timelineRightTextAlign === 'right' ? 'auto' : ($timelineRightTextAlign === 'center' ? 'auto' : '0'); ?>; --jem-timeline-right-badge-margin-right: <?php echo $timelineRightTextAlign === 'left' ? 'auto' : ($timelineRightTextAlign === 'center' ? 'auto' : '0'); ?>; --jem-timeline-left-badge-margin-left: <?php echo $timelineLeftTextAlign === 'right' ? 'auto' : ($timelineLeftTextAlign === 'center' ? 'auto' : '0'); ?>; --jem-timeline-left-badge-margin-right: <?php echo $timelineLeftTextAlign === 'left' ? 'auto' : ($timelineLeftTextAlign === 'center' ? 'auto' : '0'); ?>;">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
    <h1 class="componentheading">
        <?php echo $this->escape($pageHeading); ?>
    </h1>
    <?php endif; ?>

    <div class="clr"> </div>

    <nav class="jem-calendar-navigation jem-timetable-navigation jem-day-timeline-navigation" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_DAY_NAVIGATION'); ?>">
        <a class="jem-calendar-nav-link jem-timetable-nav-link" href="<?php echo $previousLink; ?>" rel="prev" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'); ?>">
            <?php echo jemhtml::icon('com_jem/prev.webp', 'fa-solid fa-angle-left jem-calendar-nav-icon', Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'), array('class' => 'jem-calendar-nav-icon')); ?>
        </a>
        <div class="jem-calendar-nav-title jem-timetable-nav-title">
            <?php echo $navigationTitle; ?>
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
        <?php $timelineEventIndex = 0; ?>
        <?php foreach ($dayRange as $dayDate => $dayData) : ?>
            <?php if (!$showEmptyDays && empty($dayData['timedRows']) && empty($dayData['allDayRows'])) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <section class="jem-day-timeline-day" style="--jem-timeline-width: <?php echo (int) $timelineWidth; ?>%;">
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
                    $rowMinHeight = max(5.5, min(18, $durationHours * 3.5));
                    $timeRange = $formatGridTime($startTime) . ' - ' . $formatGridTime($endTime);
                    $accentColor = $getEventBackgroundColor($row);
                    $cardBackgroundColor = $eventColorMode === 'background' ? $accentColor : 'transparent';
                    $cardTextColor = $eventColorMode === 'background' ? $getContrastColor($accentColor) : 'inherit';
                    $cardLinkColor = $eventColorMode === 'background' ? $cardTextColor : 'inherit';
                    $categoryList = $showCategory ? trim(implode(', ', JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))) : '';
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
                    $hasLeadingMedia = $eventImage !== '';
                    $hasVenueMedia = $venueImage !== '';
                    $hasMedia = $hasLeadingMedia || $hasVenueMedia;
                    $introText = $showEventIntro ? $truncateText($row->introtext ?? '', $eventIntroLimit) : '';
                    $timelineEventIndex++;
                    $timelineEventSide = ($timelineSide === 'left' || ($timelineSide === 'alternate' && ($timelineEventIndex % 2) === 0)) ? 'left' : 'right';
                    ?>
                    <article class="jem-day-timeline-row jem-day-timeline-side-<?php echo $this->escape($timelineEventSide); ?><?php echo !empty($row->featured) ? ' featured' : ''; ?> event_id<?php echo $this->escape($row->id); ?>" style="--jem-timeline-accent: <?php echo $this->escape($accentColor); ?>; --jem-timeline-card-background: <?php echo $this->escape($cardBackgroundColor); ?>; --jem-timeline-card-color: <?php echo $this->escape($cardTextColor); ?>; --jem-timeline-card-link-color: <?php echo $this->escape($cardLinkColor); ?>; min-height: <?php echo $this->escape(number_format($rowMinHeight, 1, '.', '')); ?>rem;" itemscope="itemscope" itemtype="https://schema.org/Event">
                        <div class="jem-day-timeline-time">
                            <span><?php echo $this->escape($formatGridTime($startTime)); ?></span>
                            <small><?php echo $this->escape($formatGridTime($endTime)); ?></small>
                        </div>
                        <span class="jem-day-timeline-point" aria-hidden="true"></span>
                        <div class="jem-day-timeline-card">
                            <div class="jem-day-timeline-card-inner<?php echo $hasMedia ? ' has-media' : ''; ?><?php echo $hasLeadingMedia ? ' has-leading-media' : ''; ?><?php echo $hasVenueMedia ? ' has-venue-media' : ''; ?>">
                                <?php if ($hasLeadingMedia) : ?>
                                    <div class="jem-day-timeline-media jem-day-timeline-leading-media">
                                        <?php if ($eventImage !== '') : ?>
                                            <a class="jem-day-timeline-image" href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" aria-hidden="true" tabindex="-1">
                                                <?php echo HTMLHelper::image($eventImage, $this->escape($row->title), array('loading' => 'lazy')); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="jem-day-timeline-content">
                                    <h3>
                                        <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" title="<?php echo $buildTooltip($row, $timeRange); ?>" aria-label="<?php echo $buildTooltip($row, $timeRange); ?>" itemprop="url">
                                            <span itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                        </a>
                                        <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                                    </h3>
                                    <div class="jem-day-timeline-meta">
                                        <?php if ($categoryList !== '') : ?>
                                            <span><?php echo $categoryList; ?></span>
                                        <?php endif; ?>
                                        <?php if ($showVenue && !empty($row->venue)) : ?>
                                            <span><?php echo $this->escape($row->venue); ?></span>
                                        <?php endif; ?>
                                        <?php if ($showVenue && !empty($row->city)) : ?>
                                            <span><?php echo $this->escape($row->city); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($showType && $typeName !== '') : ?>
                                        <span class="jem-day-timeline-type-badge" style="background: <?php echo $this->escape($typeColor); ?>; color: <?php echo $this->escape($typeTextColor); ?>;">
                                            <?php if ($typeIcon !== '') : ?>
                                                <span class="<?php echo $this->escape($typeIcon); ?>" aria-hidden="true"></span>
                                            <?php endif; ?>
                                            <span><?php echo $this->escape($typeName); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($introText !== '') : ?>
                                        <div class="jem-day-timeline-intro"><?php echo $this->escape($introText); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasVenueMedia) : ?>
                                    <div class="jem-day-timeline-media jem-day-timeline-venue-media">
                                        <a class="jem-day-timeline-image" href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" aria-hidden="true" tabindex="-1">
                                            <?php echo HTMLHelper::image($venueImage, $this->escape($row->venue ?: $row->title), array('loading' => 'lazy')); ?>
                                        </a>
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

