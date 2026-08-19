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

$filters = $this->filters;
$cards = $this->statistics->cards;
$permissions = $this->permissions;
$commerceEnabled = !empty($this->statistics->commerce_enabled);
$filterQuery = http_build_query(array(
    'period' => $filters->period,
    'group' => $filters->group,
    'date_from' => $filters->date_from,
    'date_to' => $filters->date_to,
    'state_filter' => $filters->state,
    'venue_id' => $filters->venue_id,
    'category_id' => $filters->category_id,
    'type_id' => $filters->type_id,
    'parent_event_id' => $filters->parent_event_id,
    'author_id' => $filters->author_id,
));

$metricOptions = array(
    'all' => 'COM_JEM_STATISTICS_ALL_METRICS',
    'events' => 'COM_JEM_MAIN_EVENT_STATS',
    'venues' => 'COM_JEM_MAIN_VENUE_STATS',
    'categories' => 'COM_JEM_MAIN_CATEGORY_STATS',
    'types' => 'COM_JEM_MAIN_TYPE_STATS',
    'images' => 'COM_JEM_MAIN_IMAGE_STATS',
    'attachments' => 'COM_JEM_MAIN_ATTACHMENT_STATS',
    'registrations' => 'COM_JEM_MAIN_REGISTRATION_STATS',
);
if (empty($this->permissions['events'])) unset($metricOptions['events']);
if (empty($this->permissions['venues'])) unset($metricOptions['venues']);
if (empty($this->permissions['registrations'])) unset($metricOptions['registrations']);

$stateLinks = array(
    'events' => array(
        'published' => Route::_('index.php?option=com_jem&view=events&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=events&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=events&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=events&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=events'),
    ),
    'venues' => array(
        'published' => Route::_('index.php?option=com_jem&view=venues&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=venues&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=venues&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=venues&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=venues'),
    ),
    'categories' => array(
        'published' => Route::_('index.php?option=com_jem&view=categories&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=categories&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=categories&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=categories&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=categories'),
    ),
    'types' => array(
        'published' => Route::_('index.php?option=com_jem&view=types&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=types&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=types&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=types&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=types'),
    ),
);

$renderRow = static function ($label, $value, $link = null, $total = false) {
    $tag = $link ? 'a' : 'span';
    $href = $link ? ' href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '"' : '';
    $class = 'badge bg-light text-dark border' . ($link ? ' text-decoration-none' : '');
    return '<div class="jem-statistics-summary-row' . ($total ? ' is-total' : '') . '">'
        . '<span>' . $label . '</span><' . $tag . $href . ' class="' . $class . '">' . (int) $value . '</' . $tag . '></div>';
};

$renderSummary = static function ($card) use ($renderRow, $stateLinks, $permissions) {
    $key = $card->key;
    $summary = $card->summary ?: new stdClass();
    if (isset($stateLinks[$key])) {
        $singular = $key === 'categories' ? 'CATEGORIES' : strtoupper($key);
        $keys = array(
            'published' => 'COM_JEM_MAIN_' . $singular . '_PUBLISHED',
            'unpublished' => 'COM_JEM_MAIN_' . $singular . '_UNPUBLISHED',
            'archived' => 'COM_JEM_MAIN_' . $singular . '_ARCHIVED',
            'trashed' => 'COM_JEM_MAIN_' . $singular . '_TRASHED',
            'total' => 'COM_JEM_MAIN_' . $singular . '_TOTAL',
        );
        $html = '';
        foreach ($keys as $name => $languageKey) {
            $html .= $renderRow(Text::_($languageKey), $summary->{$name} ?? 0, $stateLinks[$key][$name], $name === 'total');
        }
        if ($key === 'events') {
            $html .= $renderRow(Text::_('COM_JEM_STATISTICS_PARENT_EVENTS'), $summary->parents ?? 0);
            $html .= $renderRow(Text::_('COM_JEM_STATISTICS_CHILD_EVENTS'), $summary->children ?? 0);
        } elseif ($key === 'venues') {
            $html .= $renderRow(Text::_('COM_JEM_STATISTICS_PARENT_VENUES'), $summary->parents ?? 0);
            $html .= $renderRow(Text::_('COM_JEM_STATISTICS_CHILD_VENUES'), $summary->children ?? 0);
        }
        if ($key === 'types' && $card->secondary) {
            $links = array(
                'event' => Route::_('index.php?option=com_jem&view=types&filter_entity=1'),
                'category' => Route::_('index.php?option=com_jem&view=types&filter_entity=2'),
                'venue' => Route::_('index.php?option=com_jem&view=types&filter_entity=3'),
            );
            foreach (array('event', 'category', 'venue') as $entity) {
                $html .= $renderRow(
                    Text::_('COM_JEM_MAIN_TYPES_' . strtoupper($entity)),
                    $card->secondary->{$entity} ?? 0,
                    $links[$entity]
                );
            }
        }
        return $html;
    }

    if ($key === 'images') {
        $links = array(
            'events' => Route::_('index.php?option=com_jem&view=events'),
            'venues' => Route::_('index.php?option=com_jem&view=venues'),
            'categories' => Route::_('index.php?option=com_jem&view=categories'),
            'types' => Route::_('index.php?option=com_jem&view=types'),
        );
        $html = '';
        foreach ($links as $name => $link) {
            if (($name === 'events' && empty($permissions['events'])) || ($name === 'venues' && empty($permissions['venues']))) {
                continue;
            }
            $html .= $renderRow(Text::_('COM_JEM_MAIN_IMAGES_' . strtoupper($name)), $summary->{$name} ?? 0, $link);
        }
        return $html . $renderRow(Text::_('COM_JEM_MAIN_IMAGES_TOTAL'), $summary->total ?? 0, null, true);
    }

    if ($key === 'attachments') {
        $links = array(
            'events' => Route::_('index.php?option=com_jem&view=attachments&filter_type=event'),
            'venues' => Route::_('index.php?option=com_jem&view=attachments&filter_type=venue'),
            'categories' => Route::_('index.php?option=com_jem&view=attachments&filter_type=category'),
            'other' => Route::_('index.php?option=com_jem&view=attachments'),
        );
        $html = '';
        foreach ($links as $name => $link) {
            if (($name === 'events' && empty($permissions['events'])) || ($name === 'venues' && empty($permissions['venues']))) {
                continue;
            }
            $html .= $renderRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_' . strtoupper($name)), $summary->{$name} ?? 0, $link);
        }
        return $html . $renderRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_TOTAL'), $summary->total ?? 0, Route::_('index.php?option=com_jem&view=attachments'), true);
    }

    $registrationRows = array(
        'attending_users' => 'COM_JEM_MAIN_REGISTRATION_ATTENDING_USERS',
        'booked_places' => 'COM_JEM_MAIN_REGISTRATION_BOOKED_PLACES',
        'waiting_users' => 'COM_JEM_MAIN_REGISTRATION_WAITING_USERS',
        'waiting_places' => 'COM_JEM_MAIN_REGISTRATION_WAITING_PLACES',
        'invited_users' => 'COM_JEM_MAIN_REGISTRATION_INVITED_USERS',
        'not_attending_users' => 'COM_JEM_MAIN_REGISTRATION_NOT_ATTENDING_USERS',
        'total' => 'COM_JEM_MAIN_REGISTRATION_TOTAL',
    );
    $html = '';
    foreach ($registrationRows as $name => $languageKey) {
        $html .= $renderRow(Text::_($languageKey), $summary->{$name} ?? 0, null, $name === 'total');
    }
    return $html;
};

$renderChart = static function ($card) {
    $points = $card->points;
    $count = count($points);
    $width = 900;
    $height = 280;
    $left = 54;
    $top = 20;
    $plotWidth = 820;
    $plotHeight = 205;
    $bottom = $top + $plotHeight;
    $isMoney = !empty($card->currency);
    $maximum = max(1, ...array_map(static fn ($point) => (float) $point['value'], $points));
    $coordinates = array();
    foreach ($points as $index => $point) {
        $x = $count <= 1 ? $left + ($plotWidth / 2) : $left + ($plotWidth * $index / ($count - 1));
        $y = $bottom - ($plotHeight * (float) $point['value'] / $maximum);
        $coordinates[] = array('x' => $x, 'y' => $y, 'point' => $point);
    }
    $polyline = implode(' ', array_map(static fn ($item) => round($item['x'], 2) . ',' . round($item['y'], 2), $coordinates));
    $labelIndexes = array();
    if ($count > 0) {
        $labelCount = min(6, $count);
        for ($i = 0; $i < $labelCount; ++$i) {
            $labelIndexes[] = $labelCount === 1 ? 0 : (int) round(($count - 1) * $i / ($labelCount - 1));
        }
        $labelIndexes = array_values(array_unique($labelIndexes));
    }
    $chartId = 'jem-statistics-chart-' . preg_replace('/[^a-z0-9_-]/i', '', $card->key);
    ?>
    <div class="jem-statistics-chart-wrap">
        <svg class="jem-statistics-chart" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>" role="img" aria-labelledby="<?php echo $chartId; ?>-title <?php echo $chartId; ?>-desc">
            <title id="<?php echo $chartId; ?>-title"><?php echo htmlspecialchars(Text::_($card->title_key), ENT_QUOTES, 'UTF-8'); ?></title>
            <desc id="<?php echo $chartId; ?>-desc"><?php echo htmlspecialchars(Text::_('COM_JEM_STATISTICS_CHART_DESC'), ENT_QUOTES, 'UTF-8'); ?></desc>
            <?php for ($step = 0; $step <= 4; ++$step) : ?>
                <?php $gridY = $top + ($plotHeight * $step / 4); $gridValue = $maximum * (4 - $step) / 4; ?>
                <line class="jem-statistics-chart-grid" x1="<?php echo $left; ?>" y1="<?php echo $gridY; ?>" x2="<?php echo $left + $plotWidth; ?>" y2="<?php echo $gridY; ?>" />
                <text class="jem-statistics-axis-label" x="<?php echo $left - 8; ?>" y="<?php echo $gridY + 4; ?>" text-anchor="end"><?php echo $isMoney ? number_format($gridValue, 2, '.', '') : (int) round($gridValue); ?></text>
            <?php endfor; ?>
            <line class="jem-statistics-axis" x1="<?php echo $left; ?>" y1="<?php echo $bottom; ?>" x2="<?php echo $left + $plotWidth; ?>" y2="<?php echo $bottom; ?>" />
            <?php if ($polyline !== '') : ?>
                <polyline class="jem-statistics-line" points="<?php echo $polyline; ?>" />
            <?php endif; ?>
            <?php foreach ($coordinates as $index => $item) : ?>
                <circle class="jem-statistics-point" cx="<?php echo round($item['x'], 2); ?>" cy="<?php echo round($item['y'], 2); ?>" r="4">
                    <title><?php echo htmlspecialchars($item['point']['label'] . ': ' . ($item['point']['display'] ?? $item['point']['value']), ENT_QUOTES, 'UTF-8'); ?></title>
                </circle>
                <?php if (in_array($index, $labelIndexes, true)) : ?>
                    <text class="jem-statistics-axis-label" x="<?php echo round($item['x'], 2); ?>" y="<?php echo $bottom + 25; ?>" text-anchor="middle"><?php echo htmlspecialchars($item['point']['label'], ENT_QUOTES, 'UTF-8'); ?></text>
                <?php endif; ?>
            <?php endforeach; ?>
            <text class="jem-statistics-axis-title" x="<?php echo $left + ($plotWidth / 2); ?>" y="<?php echo $height - 5; ?>" text-anchor="middle"><?php echo htmlspecialchars(Text::_('COM_JEM_STATISTICS_TIME_AXIS'), ENT_QUOTES, 'UTF-8'); ?></text>
        </svg>
    </div>
    <?php
};
?>

<div id="j-main-container" class="j-main-container jem-statistics-dashboard">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <p class="lead mb-1"><?php echo Text::_('COM_JEM_STATISTICS_DESC'); ?></p>
            <p class="text-muted mb-0"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_HELP'); ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=main'); ?>">
            <span class="icon-arrow-left" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_MAIN_TITLE'); ?>
        </a>
    </div>

    <form action="<?php echo Route::_('index.php'); ?>" method="get" class="card mb-4 jem-statistics-filters">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-xl-2">
                    <label class="form-label" for="metric"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_METRIC'); ?></label>
                    <select class="form-select" id="metric" name="metric">
                        <?php foreach ($metricOptions as $value => $label) : ?>
                            <option value="<?php echo $value; ?>"<?php echo $filters->metric === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label" for="period"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_PERIOD'); ?></label>
                    <select class="form-select" id="period" name="period">
                        <?php foreach (array('30d' => 'COM_JEM_STATISTICS_PERIOD_30D', '90d' => 'COM_JEM_STATISTICS_PERIOD_90D', '12m' => 'COM_JEM_STATISTICS_PERIOD_12M', 'all' => 'COM_JEM_STATISTICS_PERIOD_ALL', 'custom' => 'COM_JEM_STATISTICS_PERIOD_CUSTOM') as $value => $label) : ?>
                            <option value="<?php echo $value; ?>"<?php echo $filters->period === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label" for="group"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_GROUP'); ?></label>
                    <select class="form-select" id="group" name="group">
                        <?php foreach (array('day' => 'COM_JEM_STATISTICS_GROUP_DAY', 'week' => 'COM_JEM_STATISTICS_GROUP_WEEK', 'month' => 'COM_JEM_STATISTICS_GROUP_MONTH', 'year' => 'COM_JEM_STATISTICS_GROUP_YEAR') as $value => $label) : ?>
                            <option value="<?php echo $value; ?>"<?php echo $filters->group === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label" for="date_from"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_FROM'); ?></label>
                    <input class="form-control" type="date" id="date_from" name="date_from" value="<?php echo $this->escape($filters->date_from); ?>">
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label" for="date_to"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_TO'); ?></label>
                    <input class="form-control" type="date" id="date_to" name="date_to" value="<?php echo $this->escape($filters->date_to); ?>">
                </div>
                <?php if (in_array($filters->metric, array('events', 'venues', 'categories', 'types'), true)) : ?>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label" for="state_filter"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_STATE'); ?></label>
                        <select class="form-select" id="state_filter" name="state_filter">
                            <?php foreach (array('' => 'JALL', '1' => 'JPUBLISHED', '0' => 'JUNPUBLISHED', '2' => 'JARCHIVED', '-2' => 'JTRASHED') as $value => $label) : ?>
                                <option value="<?php echo $value; ?>"<?php echo $filters->state === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php
                $subtypeOptions = array();
                if ($filters->metric === 'events') {
                    $subtypeOptions = array('' => 'JALL', 'parent' => 'COM_JEM_STATISTICS_PARENT_EVENTS', 'child' => 'COM_JEM_STATISTICS_CHILD_EVENTS');
                } elseif ($filters->metric === 'venues') {
                    $subtypeOptions = array('' => 'JALL', 'parent' => 'COM_JEM_STATISTICS_PARENT_VENUES', 'child' => 'COM_JEM_STATISTICS_CHILD_VENUES');
                } elseif ($filters->metric === 'types') {
                    $subtypeOptions = array('' => 'JALL', 'event' => 'COM_JEM_EVENTS', 'category' => 'COM_JEM_CATEGORIES', 'venue' => 'COM_JEM_VENUES');
                } elseif ($filters->metric === 'images') {
                    $subtypeOptions = array('' => 'JALL', 'events' => 'COM_JEM_EVENTS', 'venues' => 'COM_JEM_VENUES', 'categories' => 'COM_JEM_CATEGORIES', 'types' => 'COM_JEM_TYPES');
                    if (empty($this->permissions['events'])) unset($subtypeOptions['events']);
                    if (empty($this->permissions['venues'])) unset($subtypeOptions['venues']);
                } elseif ($filters->metric === 'attachments') {
                    $subtypeOptions = array('' => 'JALL', 'event' => 'COM_JEM_EVENTS', 'venue' => 'COM_JEM_VENUES', 'category' => 'COM_JEM_CATEGORIES', 'other' => 'COM_JEM_MAIN_ATTACHMENTS_OTHER');
                    if (empty($this->permissions['events'])) unset($subtypeOptions['event']);
                    if (empty($this->permissions['venues'])) unset($subtypeOptions['venue']);
                } elseif ($filters->metric === 'registrations') {
                    $subtypeOptions = array('' => 'JALL', 'attending' => 'COM_JEM_ATTENDEES_ATTENDING', 'waiting' => 'COM_JEM_ATTENDEES_ON_WAITINGLIST', 'invited' => 'COM_JEM_ATTENDEES_INVITED', 'not_attending' => 'COM_JEM_ATTENDEES_NOT_ATTENDING');
                }
                ?>
                <?php if ($subtypeOptions) : ?>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label" for="subtype"><?php echo Text::_('COM_JEM_STATISTICS_FILTER_SUBTYPE'); ?></label>
                        <select class="form-select" id="subtype" name="subtype">
                            <?php foreach ($subtypeOptions as $value => $label) : ?>
                                <option value="<?php echo $value; ?>"<?php echo $filters->subtype === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-12 col-md-auto ms-xl-auto d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><span class="icon-filter" aria-hidden="true"></span> <?php echo Text::_('JFILTER'); ?></button>
                    <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=statistics'); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
                </div>
            </div>
            <div class="jem-statistics-event-filters mt-3 pt-3 border-top">
                <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                    <h2 class="h6 mb-0"><?php echo Text::_('COM_JEM_STATISTICS_EVENT_FILTERS'); ?></h2>
                    <small class="text-muted"><?php echo Text::_('COM_JEM_STATISTICS_EVENT_FILTERS_DESC'); ?></small>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label" for="venue_id"><?php echo Text::_('COM_JEM_VENUE'); ?></label>
                        <select class="form-select" id="venue_id" name="venue_id">
                            <option value="0"><?php echo Text::_('COM_JEM_STATISTICS_ALL_VENUES'); ?></option>
                            <?php foreach ($this->filterOptions->venues as $option) : ?>
                                <?php $venueLabel = !empty($option->parent_venue_id) ? '└─ ' . $option->parent_text . ' — ' . $option->text : $option->text; ?>
                                <option value="<?php echo (int) $option->id; ?>"<?php echo (int) $filters->venue_id === (int) $option->id ? ' selected' : ''; ?>><?php echo $this->escape($venueLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label" for="category_id"><?php echo Text::_('COM_JEM_CATEGORY'); ?></label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="0"><?php echo Text::_('COM_JEM_STATISTICS_ALL_CATEGORIES'); ?></option>
                            <?php foreach ($this->filterOptions->categories as $option) : ?>
                                <option value="<?php echo (int) $option->id; ?>"<?php echo (int) $filters->category_id === (int) $option->id ? ' selected' : ''; ?>><?php echo str_repeat('— ', max(0, (int) $option->level - 1)) . $this->escape($option->text); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label" for="type_id"><?php echo Text::_('COM_JEM_TYPE'); ?></label>
                        <select class="form-select" id="type_id" name="type_id">
                            <option value="0"><?php echo Text::_('COM_JEM_STATISTICS_ALL_TYPES'); ?></option>
                            <?php foreach ($this->filterOptions->types as $option) : ?>
                                <option value="<?php echo (int) $option->id; ?>"<?php echo (int) $filters->type_id === (int) $option->id ? ' selected' : ''; ?>><?php echo $this->escape($option->text); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label" for="parent_event_id"><?php echo Text::_('COM_JEM_STATISTICS_PROGRAMME'); ?></label>
                        <select class="form-select" id="parent_event_id" name="parent_event_id">
                            <option value="0"><?php echo Text::_('COM_JEM_STATISTICS_ALL_PROGRAMMES'); ?></option>
                            <?php foreach ($this->filterOptions->programmes as $option) : ?>
                                <option value="<?php echo (int) $option->id; ?>"<?php echo (int) $filters->parent_event_id === (int) $option->id ? ' selected' : ''; ?>><?php echo $this->escape($option->text); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label" for="author_id"><?php echo Text::_('JAUTHOR'); ?></label>
                        <select class="form-select" id="author_id" name="author_id">
                            <option value="0"><?php echo Text::_('COM_JEM_STATISTICS_ALL_AUTHORS'); ?></option>
                            <?php foreach ($this->filterOptions->authors as $option) : ?>
                                <option value="<?php echo (int) $option->id; ?>"<?php echo (int) $filters->author_id === (int) $option->id ? ' selected' : ''; ?>><?php echo $this->escape($option->text . ' (' . $option->username . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" name="option" value="com_jem">
        <input type="hidden" name="view" value="statistics">
    </form>

    <div class="jem-statistics-card-grid">
        <?php foreach ($cards as $card) : ?>
            <article class="card jem-statistics-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h4 mb-0"><?php echo Text::_($card->title_key); ?></h2>
                        <small class="text-muted"><?php echo Text::_($card->series_key); ?></small>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_jem&view=statistics&metric=' . $card->key . '&' . $filterQuery); ?>">
                        <?php echo Text::_('COM_JEM_STATISTICS_FILTER_THIS'); ?>
                    </a>
                </div>
                <div class="card-body">
                    <div class="jem-statistics-kpis">
                        <div><span><?php echo Text::_('COM_JEM_STATISTICS_PERIOD_TOTAL'); ?></span><strong><?php echo (int) $card->period_total; ?></strong></div>
                        <div><span><?php echo Text::_('COM_JEM_STATISTICS_ALL_TIME_TOTAL'); ?></span><strong><?php echo (int) $card->all_total; ?></strong></div>
                    </div>
                    <?php $renderChart($card); ?>
                    <div class="jem-statistics-summary" aria-label="<?php echo $this->escape(Text::_($card->title_key)); ?>">
                        <?php echo $renderSummary($card); ?>
                    </div>
                    <details class="mt-3">
                        <summary><?php echo Text::_('COM_JEM_STATISTICS_DATA_TABLE'); ?></summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-striped mb-0">
                                <thead><tr><th><?php echo Text::_('COM_JEM_STATISTICS_TIME_AXIS'); ?></th><th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_VALUE'); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($card->points as $point) : ?>
                                    <tr><td><?php echo $this->escape($point['label']); ?></td><td class="text-end"><?php echo (int) $point['value']; ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (in_array($filters->metric, array('all', 'registrations'), true) && !empty($this->statistics->booking_value_series)) : ?>
        <section class="card mt-4 jem-statistics-detail-card">
            <div class="card-header">
                <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_STATISTICS_BOOKING_VALUE_TREND'); ?></h2>
                <p class="text-muted mb-0"><?php echo Text::_('COM_JEM_STATISTICS_BOOKING_VALUE_TREND_DESC'); ?></p>
            </div>
            <div class="card-body jem-statistics-money-grid">
                <?php foreach ($this->statistics->booking_value_series as $series) : ?>
                    <?php $moneyCard = (object) array('key' => 'booking-value-' . strtolower($series->currency), 'title_key' => 'COM_JEM_STATISTICS_BOOKING_VALUE_TREND', 'currency' => $series->currency, 'points' => $series->points); ?>
                    <article class="jem-statistics-money-card">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h3 class="h5 mb-0"><?php echo $this->escape($series->currency); ?></h3>
                                <small class="text-muted"><?php echo (int) $series->orders; ?> <?php echo Text::_('COM_JEM_STATISTICS_ORDERS_LOWER'); ?> · <?php echo (int) $series->places; ?> <?php echo Text::_('COM_JEM_STATISTICS_PLACES_LOWER'); ?></small>
                            </div>
                            <strong class="fs-4"><?php echo $this->escape($series->currency . ' ' . number_format((float) $series->total, 2, '.', '')); ?></strong>
                        </div>
                        <?php $renderChart($moneyCard); ?>
                        <details>
                            <summary><?php echo Text::_('COM_JEM_STATISTICS_DATA_TABLE'); ?></summary>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-striped mb-0">
                                    <thead><tr><th><?php echo Text::_('COM_JEM_STATISTICS_TIME_AXIS'); ?></th><th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_ORDERS'); ?></th><th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_PLACES'); ?></th><th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_BOOKING_VALUE'); ?></th></tr></thead>
                                    <tbody><?php foreach ($series->points as $point) : ?><tr><td><?php echo $this->escape($point['label']); ?></td><td class="text-end"><?php echo (int) $point['orders']; ?></td><td class="text-end"><?php echo (int) $point['places']; ?></td><td class="text-end"><?php echo $this->escape($point['display']); ?></td></tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (in_array($filters->metric, array('all', 'events', 'registrations'), true) && !empty($this->statistics->programmes)) : ?>
        <section class="card mt-4 jem-statistics-detail-card">
            <div class="card-header">
                <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_STATISTICS_PROGRAMME_SUMMARY'); ?></h2>
                <p class="text-muted mb-0"><?php echo Text::_('COM_JEM_STATISTICS_PROGRAMME_SUMMARY_DESC'); ?></p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead><tr>
                        <th><?php echo Text::_('COM_JEM_STATISTICS_PROGRAMME'); ?></th>
                        <th><?php echo Text::_('COM_JEM_DATE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CHILD_EVENTS'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_ORDERS'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_PLACES'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_WAITING'); ?></th>
                        <?php if ($commerceEnabled) : ?><th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_BOOKING_VALUE'); ?></th><?php endif; ?>
                    </tr></thead>
                    <tbody><?php foreach ($this->statistics->programmes as $programme) : ?><tr>
                        <td><a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $programme->id); ?>"><?php echo $this->escape($programme->title); ?></a></td>
                        <td class="text-nowrap"><?php echo $programme->dates ? HTMLHelper::_('date', $programme->dates, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?></td>
                        <td class="text-end"><?php echo (int) $programme->child_events; ?></td>
                        <td class="text-end"><?php echo (int) $programme->confirmed_orders; ?></td>
                        <td class="text-end"><?php echo (int) $programme->confirmed_places; ?></td>
                        <td class="text-end"><?php echo (int) $programme->waiting_orders; ?> / <?php echo (int) $programme->waiting_places; ?></td>
                        <?php if ($commerceEnabled) : ?><td class="text-end text-nowrap">
                            <?php if ($programme->revenue) : foreach ($programme->revenue as $revenue) : ?>
                                <span class="badge bg-success ms-1"><?php echo $this->escape($revenue->currency . ' ' . number_format((float) $revenue->total, 2, '.', '')); ?></span>
                            <?php endforeach; else : ?><span class="text-muted">&mdash;</span><?php endif; ?>
                        </td><?php endif; ?>
                    </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (in_array($filters->metric, array('all', 'events', 'registrations'), true) && !empty($this->statistics->future_events)) : ?>
        <section class="card mt-4 jem-statistics-detail-card">
            <div class="card-header">
                <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_STATISTICS_FUTURE_EVENTS'); ?></h2>
                <p class="text-muted mb-0"><?php echo Text::_('COM_JEM_STATISTICS_FUTURE_EVENTS_DESC'); ?></p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?php echo Text::_('COM_JEM_EVENT'); ?></th>
                        <th><?php echo Text::_('COM_JEM_DATE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_ORDERS'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_PLACES'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_RESERVED_CAPACITY'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_AVAILABLE_PLACES'); ?></th>
                        <th><?php echo Text::_('COM_JEM_STATISTICS_OCCUPANCY'); ?></th>
                        <th><?php echo Text::_('COM_JEM_STATISTICS_AREA_AVAILABILITY'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_WAITING'); ?></th>
                        <?php if ($commerceEnabled) : ?><th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_REVENUE'); ?></th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->statistics->future_events as $event) : ?>
                        <tr>
                            <td>
                                <?php if (!empty($event->parent_event_id)) : ?>
                                    <span class="jem-statistics-tree" aria-hidden="true">&#9492;&#9472;</span>
                                    <small class="d-block text-muted"><?php echo $this->escape($event->parent_title); ?></small>
                                <?php endif; ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $event->id); ?>"><?php echo $this->escape($event->title); ?></a>
                            </td>
                            <td class="text-nowrap"><?php echo HTMLHelper::_('date', $event->dates, Text::_('DATE_FORMAT_LC4')); ?></td>
                            <td class="text-end"><?php echo (int) $event->confirmed_orders; ?></td>
                            <td class="text-end"><?php echo (int) $event->confirmed_places; ?></td>
                            <td class="text-end"><?php echo (int) $event->reservedplaces; ?></td>
                            <td class="text-end fw-semibold"><?php echo $event->available_places === null ? '&infin;' : (int) $event->available_places; ?></td>
                            <td style="min-width: 9rem;">
                                <?php if ($event->occupancy_percent !== null) : ?>
                                    <?php $occupancyClass = $event->occupancy_percent >= 100 ? 'bg-danger' : ($event->occupancy_percent >= 85 ? 'bg-warning' : 'bg-success'); ?>
                                    <div class="progress" role="progressbar" aria-label="<?php echo Text::_('COM_JEM_STATISTICS_OCCUPANCY'); ?>" aria-valuenow="<?php echo $event->occupancy_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar <?php echo $occupancyClass; ?>" style="width: <?php echo $event->occupancy_percent; ?>%"><?php echo $event->occupancy_percent; ?>%</div>
                                    </div>
                                <?php else : ?><span class="text-muted"><?php echo Text::_('COM_JEM_STATISTICS_UNLIMITED'); ?></span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($event->pools) : ?>
                                    <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($event->pools as $pool) : ?>
                                        <?php $poolPercent = (int) $pool->capacity > 0 ? min(100, round(100 * (int) $pool->used / (int) $pool->capacity)) : 0; ?>
                                        <?php $poolClass = $poolPercent >= 100 ? 'bg-danger' : ($poolPercent >= 85 ? 'bg-warning text-dark' : 'bg-light text-dark'); ?>
                                        <span class="badge <?php echo $poolClass; ?> border" title="<?php echo $this->escape($pool->name . ': ' . $poolPercent . '% ' . Text::_('COM_JEM_STATISTICS_OCCUPANCY')); ?>">
                                            <?php echo $this->escape($pool->name); ?>: <?php echo (int) $pool->remaining; ?> / <?php echo (int) $pool->capacity; ?> (<?php echo $poolPercent; ?>%)
                                        </span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo (int) $event->waiting_orders; ?> / <?php echo (int) $event->waiting_places; ?></td>
                            <?php if ($commerceEnabled) : ?><td class="text-end text-nowrap">
                                <?php if ((string) $event->pricing_mode !== 'classic' && $event->currency !== '') : ?>
                                    <?php echo $this->escape($event->currency . ' ' . number_format((float) $event->confirmed_revenue, 2, '.', '')); ?>
                                <?php else : ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (in_array($filters->metric, array('all', 'venues'), true) && !empty($this->statistics->venue_infrastructure)) : ?>
        <section class="card mt-4 jem-statistics-detail-card">
            <div class="card-header">
                <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_STATISTICS_VENUE_INFRASTRUCTURE'); ?></h2>
                <p class="text-muted mb-0"><?php echo Text::_('COM_JEM_STATISTICS_VENUE_INFRASTRUCTURE_DESC'); ?></p>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead><tr>
                        <th><?php echo Text::_('COM_JEM_VENUE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_VENUE_CAPACITY'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_PROFILES'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_SPACES'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_JEM_STATISTICS_AREAS'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($this->statistics->venue_infrastructure as $venue) : ?>
                        <tr>
                            <td>
                                <?php if (!empty($venue->parent_venue_id)) : ?>
                                    <span class="jem-statistics-tree" aria-hidden="true">&#9492;&#9472;</span>
                                    <small class="d-block text-muted"><?php echo $this->escape($venue->parent_name); ?></small>
                                <?php endif; ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=venue.edit&id=' . (int) $venue->id); ?>"><?php echo $this->escape($venue->venue); ?></a>
                            </td>
                            <td class="text-end"><?php echo (int) $venue->capacity; ?></td>
                            <td class="text-end"><?php echo (int) $venue->profiles; ?></td>
                            <td class="text-end"><?php echo (int) $venue->spaces; ?></td>
                            <td class="text-end"><?php echo (int) $venue->areas; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (in_array($filters->metric, array('all', 'registrations'), true) && !empty($this->permissions['registrations'])) : ?>
        <?php $commercial = $this->statistics->registration_commercial; ?>
        <?php $workflow = $this->statistics->registration_workflow; ?>
        <section class="card mt-4 jem-statistics-detail-card">
            <div class="card-header">
                <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_STATISTICS_REGISTRATION_ACTIVITY'); ?></h2>
                <p class="text-muted mb-0"><?php echo Text::_('COM_JEM_STATISTICS_REGISTRATION_ACTIVITY_DESC'); ?></p>
            </div>
            <div class="card-body">
                <div class="jem-statistics-order-kpis">
                    <?php $registrationKpis = array(
                        'confirmed' => 'COM_JEM_STATISTICS_CONFIRMED_ORDERS',
                        'confirmed_places' => 'COM_JEM_STATISTICS_CONFIRMED_PLACES',
                        'waiting' => 'COM_JEM_ATTENDEES_ON_WAITINGLIST',
                        'invited' => 'COM_JEM_ATTENDEES_INVITED',
                        'cancelled' => 'COM_JEM_STATISTICS_CANCELLED_ORDERS',
                        'classic' => 'COM_JEM_STATISTICS_CLASSIC_BOOKINGS',
                    ); ?>
                    <?php if ($commerceEnabled) $registrationKpis['priced'] = 'COM_JEM_STATISTICS_PRICED_ORDERS'; ?>
                    <?php foreach ($registrationKpis as $field => $label) : ?>
                        <div><span><?php echo Text::_($label); ?></span><strong><?php echo (int) $commercial->{$field}; ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <h3 class="h5 mt-4"><?php echo Text::_('COM_JEM_STATISTICS_WORKFLOW_ACTIVITY'); ?></h3>
                <p class="text-muted"><?php echo Text::_('COM_JEM_STATISTICS_WORKFLOW_ACTIVITY_DESC'); ?></p>
                <div class="jem-statistics-order-kpis">
                    <?php foreach (array('cancelled' => 'COM_JEM_STATISTICS_CANCELLED_ORDERS', 'reactivated' => 'COM_JEM_STATISTICS_REACTIVATED_ORDERS', 'promoted' => 'COM_JEM_STATISTICS_PROMOTED_ORDERS', 'modified' => 'COM_JEM_STATISTICS_MODIFIED_ORDERS', 'current_waiting' => 'COM_JEM_STATISTICS_CURRENT_WAITING') as $field => $label) : ?>
                        <div><span><?php echo Text::_($label); ?></span><strong><?php echo (int) ($workflow->{$field} ?? 0); ?></strong></div>
                    <?php endforeach; ?>
                    <div><span><?php echo Text::_('COM_JEM_STATISTICS_QUEUE_RESOLUTION'); ?></span><strong><?php echo $workflow->queue_resolution === null ? '&mdash;' : $workflow->queue_resolution . '%'; ?></strong></div>
                </div>
                <?php if ($commerceEnabled) : ?><div class="jem-statistics-revenue mt-3">
                    <strong><?php echo Text::_('COM_JEM_STATISTICS_CONFIRMED_BOOKING_VALUE'); ?>:</strong>
                    <?php if ($commercial->revenue) : ?>
                        <?php foreach ($commercial->revenue as $revenue) : ?>
                            <span class="badge bg-success ms-2"><?php echo $this->escape($revenue->currency . ' ' . number_format((float) $revenue->total, 2, '.', '')); ?></span>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <span class="text-muted ms-2"><?php echo Text::_('COM_JEM_STATISTICS_NO_REVENUE'); ?></span>
                    <?php endif; ?>
                </div><?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const period = document.getElementById('period');
    document.querySelectorAll('#date_from, #date_to').forEach(function (field) {
        field.addEventListener('change', function () { period.value = 'custom'; });
    });
    document.getElementById('metric').addEventListener('change', function () { this.form.submit(); });
});
</script>
