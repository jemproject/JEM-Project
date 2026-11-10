<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$report = $this->report;
$filters = $report['filters'];
$options = $report['options'];
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$base = ['option' => 'com_jem', 'view' => 'registrationstatus'];
if (!$report['backend'] && Factory::getApplication()->input->getInt('Itemid') > 0) {
    $base['Itemid'] = Factory::getApplication()->input->getInt('Itemid');
}
$query = $base;
foreach (['mode', 'date', 'category', 'venue', 'country', 'region', 'county', 'city', 'publication', 'event_status', 'search'] as $name) {
    $query['rs_' . $name] = $filters[$name];
}
$query['rs_page'] = $report['page'];
$canonical = $query;
if ($report['historyId']) {
    $canonical['rs_history'] = $report['historyId'];
    $canonical['rs_history_page'] = $report['historyPage'];
}
$url = static fn (array $values) => Route::_('index.php?' . http_build_query($values), false);
$labels = [
    'category' => 'COM_JEM_RS_CATEGORY', 'venue' => 'COM_JEM_RS_VENUE', 'country' => 'COM_JEM_RS_COUNTRY',
    'region' => 'COM_JEM_RS_REGION', 'county' => 'COM_JEM_RS_COUNTY', 'city' => 'COM_JEM_RS_CITY',
    'publication' => 'COM_JEM_RS_PUBLICATION', 'event_status' => 'COM_JEM_RS_EVENT_STATUS',
];
$choices = $report['choices'];
$choices['publication'] = ['1' => Text::_('JPUBLISHED'), '0' => Text::_('JUNPUBLISHED'), '2' => Text::_('JARCHIVED'), '-2' => Text::_('JTRASHED')];
$choices['event_status'] = [];
foreach (['scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online'] as $status) {
    $choices['event_status'][$status] = Text::_('COM_JEM_RS_EVENT_' . strtoupper($status));
}
$columns = ['capacity', 'reserved', 'confirmed', 'waiting', 'invited', 'free', 'paid', 'entered', 'cancelled'];
$columns = array_values(array_filter($columns, static fn ($name) => (bool) ($options['show_' . $name . '_column'] ?? ($name === 'invited' ? 0 : 1))));
$locations = array_keys(JemRegistrationStatus::LOCATIONS);
$locations = array_values(array_filter($locations, static fn ($name) => !empty($options['show_' . $name . '_column'])));
$childRows = [];
foreach ($report['rows'] as $row) {
    $childRows[$row['parent']][] = $row['key'];
}
$expanded = (bool) ($options['expand_groups'] ?? 1);
?>
<section class="jem-registration-status" data-rs-refresh="<?php echo (int) ($options['refresh_seconds'] ?? 0); ?>" data-rs-canonical="<?php echo $escape($url($canonical)); ?>">
    <?php if (!$report['backend']) : ?><h1><?php echo Text::_('COM_JEM_RS_TITLE'); ?></h1><?php endif; ?>
    <p><?php echo Text::_('COM_JEM_RS_INTRO'); ?></p>
    <?php require __DIR__ . '/ticket-desk.php'; ?>
    <form method="get" action="<?php echo $escape($url($base)); ?>" class="rs-filters">
        <?php foreach ($base as $name => $value) : ?>
            <input type="hidden" name="<?php echo $escape($name); ?>" value="<?php echo $escape($value); ?>">
        <?php endforeach; ?>
        <?php if ($filters['show']['date']) : ?>
            <div class="rs-periods" role="group" aria-label="<?php echo Text::_('COM_JEM_RS_PERIOD'); ?>">
                <?php foreach (['upcoming', 'all', 'today', 'date', 'week', 'month', 'year'] as $mode) : ?>
                    <input type="radio" class="btn-check" name="rs_mode" id="rs-mode-<?php echo $mode; ?>" value="<?php echo $mode; ?>" <?php echo $filters['mode'] === $mode ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary btn-sm" for="rs-mode-<?php echo $mode; ?>"><?php echo Text::_('COM_JEM_RS_PERIOD_' . strtoupper($mode)); ?></label>
                <?php endforeach; ?>
                <div class="rs-date-nav">
                    <?php
                    $unit = strtoupper($filters['unit']);
                    $jump = ['DAY' => 'WEEK', 'WEEK' => 'MONTH', 'MONTH' => 'YEAR'][$unit] ?? null;
                    $disabled = in_array($filters['mode'], ['all', 'upcoming'], true);
                    ?>
                    <?php foreach ([['jump', -1, '«', $jump], ['step', -1, '‹', $unit]] as [$name, $value, $icon, $target]) : ?>
                        <?php if ($target !== null) : ?>
                            <button type="submit" name="rs_<?php echo $name; ?>" value="<?php echo $value; ?>" class="btn btn-outline-primary btn-sm" <?php echo $disabled ? 'disabled' : ''; ?> title="<?php echo Text::_('COM_JEM_RS_PREVIOUS_' . $target); ?>" aria-label="<?php echo Text::_('COM_JEM_RS_PREVIOUS_' . $target); ?>"><?php echo $icon; ?></button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input class="form-control" type="date" name="rs_date" min="0001-01-01" max="9999-12-31" value="<?php echo $escape($filters['date']); ?>" aria-label="<?php echo Text::_('COM_JEM_RS_DATE'); ?>">
                    <?php foreach ([['step', 1, '›', $unit], ['jump', 1, '»', $jump]] as [$name, $value, $icon, $target]) : ?>
                        <?php if ($target !== null) : ?>
                            <button type="submit" name="rs_<?php echo $name; ?>" value="<?php echo $value; ?>" class="btn btn-outline-primary btn-sm" <?php echo $disabled ? 'disabled' : ''; ?> title="<?php echo Text::_('COM_JEM_RS_NEXT_' . $target); ?>" aria-label="<?php echo Text::_('COM_JEM_RS_NEXT_' . $target); ?>"><?php echo $icon; ?></button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="rs-fields">
            <?php if ($filters['show']['search']) : ?>
                <div class="rs-field"><label for="rs-search"><?php echo Text::_('COM_JEM_RS_SEARCH'); ?></label><input class="form-control" id="rs-search" type="search" name="rs_search" maxlength="200" value="<?php echo $escape($filters['search']); ?>"></div>
            <?php endif; ?>
            <?php foreach ($labels as $name => $label) : ?>
                <?php if ($filters['show'][$name]) : ?>
                    <div class="rs-field">
                        <label for="rs-<?php echo $name; ?>"><?php echo Text::_($label); ?></label>
                        <select class="form-select" id="rs-<?php echo $name; ?>" name="rs_<?php echo $name; ?>">
                            <option value="<?php echo $name === 'publication' ? 'all' : ''; ?>" <?php echo ($name === 'publication' ? $filters[$name] === 'all' : in_array((string) $filters[$name], ['', '0'], true)) ? 'selected' : ''; ?>><?php echo Text::_('JALL'); ?></option>
                            <?php foreach ($choices[$name] as $value => $labelValue) : ?>
                                <option value="<?php echo $escape($value); ?>" <?php echo (string) $filters[$name] === (string) $value ? 'selected' : ''; ?>><?php echo $escape($labelValue); ?></option>
                            <?php endforeach; ?>
                            <?php if ((string) $filters[$name] !== '' && (string) $filters[$name] !== '0' && (string) $filters[$name] !== 'all' && !isset($choices[$name][$filters[$name]])) : ?>
                                <option value="<?php echo $escape($filters[$name]); ?>" selected><?php echo $escape($filters[$name]); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="rs-actions">
            <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_JEM_RS_APPLY'); ?></button>
            <a class="btn btn-secondary" href="<?php echo $escape($url($base)); ?>"><?php echo Text::_('COM_JEM_RS_CLEAR'); ?></a>
            <span class="rs-spacer"></span>
            <button type="button" class="btn btn-outline-secondary" data-rs-expand="1"><?php echo Text::_('COM_JEM_RS_EXPAND'); ?></button>
            <button type="button" class="btn btn-outline-secondary" data-rs-expand="0"><?php echo Text::_('COM_JEM_RS_COLLAPSE'); ?></button>
        </div>
    </form>
    <p class="rs-meta"><?php echo Text::sprintf('COM_JEM_RS_UPDATED', $escape($report['updated'])); ?></p>
    <div class="rs-scroll" tabindex="0" role="region" aria-label="<?php echo Text::_('COM_JEM_RS_TITLE'); ?>">
        <table class="table">
            <caption><?php echo Text::_('COM_JEM_RS_CAPTION'); ?></caption>
            <thead><tr>
                <th scope="col"><?php echo Text::_('COM_JEM_RS_DATE'); ?></th>
                <th scope="col"><?php echo Text::_('COM_JEM_RS_TIME'); ?></th>
                <th scope="col"><?php echo Text::_('COM_JEM_RS_EVENT'); ?></th>
                <?php foreach ($locations as $name) : ?><th scope="col"><?php echo Text::_($labels[$name]); ?></th><?php endforeach; ?>
                <?php foreach ($columns as $column) : ?><th scope="col" class="rs-number" title="<?php echo $escape(Text::_('COM_JEM_RS_' . strtoupper($column) . '_DESC')); ?>"><?php echo Text::_('COM_JEM_RS_' . strtoupper($column)); ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($report['rows'] as $row) : ?>
                <?php
                $event = $row['event'];
                $isArea = in_array($row['kind'], ['area', 'space'], true);
                $isProgramme = $row['kind'] === 'programme';
                $attendees = $report['backend']
                    ? ['option' => 'com_jem', 'view' => 'attendees', 'eventid' => (int) $event->id]
                    : ['option' => 'com_jem', 'view' => 'attendees', 'id' => (int) $event->id] + (isset($base['Itemid']) ? ['Itemid' => $base['Itemid']] : []);
                ?>
                <tr id="<?php echo $escape($row['key']); ?>" class="rs-<?php echo $row['kind']; ?>" data-rs-row data-rs-parent="<?php echo $escape($row['parent']); ?>">
                    <td><?php echo !$isArea && $event->dates ? $escape((new DateTimeImmutable($event->dates))->format(Text::_('COM_JEM_RS_DATE_FORMAT'))) : '—'; ?></td>
                    <td><?php echo !$isArea && $event->times ? $escape(substr($event->times, 0, 5)) : '—'; ?></td>
                    <th scope="row" class="rs-name">
                        <div class="rs-entry" style="--rs-depth: <?php echo (int) $row['depth']; ?>">
                            <?php if (!empty($childRows[$row['key']])) : ?>
                                <button type="button" class="rs-toggle" data-rs-toggle aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>" aria-controls="<?php echo $escape(implode(' ', $childRows[$row['key']])); ?>" aria-label="<?php echo $escape(Text::_('COM_JEM_RS_TOGGLE') . ': ' . $event->title); ?>"><?php echo $expanded ? '−' : '+'; ?></button>
                            <?php endif; ?>
                            <?php echo $escape($row['kind'] === 'own' ? Text::_('COM_JEM_RS_OWN') : $row['name']); ?>
                            <small class="rs-detail"><?php echo $escape($isArea ? $row['space'] : ($event->venue ?? Text::_('COM_JEM_RS_NO_VENUE'))); ?></small>
                            <?php if (!$isArea) : ?>
                                <small class="rs-detail"><?php echo $escape(($choices['event_status'][$event->event_status] ?? $event->event_status) . ' · ' . ($choices['publication'][(string) $event->published] ?? '')); ?></small>
                                <?php if ($row['context']) : ?><small class="rs-detail"><?php echo Text::_('COM_JEM_RS_CONTEXT'); ?></small><?php endif; ?>
                                <?php if (!$isProgramme && !$row['context'] && ($event->canManageBookings ?? true)) : ?>
                                    <a href="<?php echo $escape($url($attendees)); ?>"><?php echo Text::_('COM_JEM_RS_BOOKINGS'); ?></a>
                                    <?php if ($report['canHistory']) : ?>
                                        · <a href="<?php echo $escape($url(array_replace($query, ['rs_history' => (int) $event->id]))); ?>#rs-history"><?php echo Text::_('COM_JEM_RS_HISTORY'); ?></a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </th>
                    <?php foreach ($locations as $name) : ?>
                        <td><?php echo $isArea ? '—' : $escape($event->{JemRegistrationStatus::LOCATIONS[$name]} ?? ''); ?></td>
                    <?php endforeach; ?>
                    <?php foreach ($columns as $column) : ?>
                        <?php $value = $row['totals'][$column] ?? null; ?>
                        <td class="rs-number">
                            <?php if (!empty($row['totals']['unknown']) && in_array($column, ['capacity', 'free'], true)) : ?>—
                            <?php elseif ($value === null) : ?>
                                <?php echo !$isProgramme && !$row['context'] && in_array($column, ['capacity', 'free'], true) ? '∞' : '—'; ?>
                            <?php elseif (!$isArea && !$isProgramme && in_array($column, ['confirmed', 'waiting', 'invited'], true) && $value > 0 && ($event->canManageBookings ?? true)) : ?>
                                <a href="<?php echo $escape($url($attendees + ['filter_status' => ['confirmed' => 1, 'waiting' => 2, 'invited' => 0][$column]])); ?>"><?php echo (int) $value; ?></a>
                            <?php elseif ($column === 'cancelled' && !$isArea && !$isProgramme && $value > 0 && $report['canHistory']) : ?>
                                <a href="<?php echo $escape($url(array_replace($query, ['rs_history' => (int) $event->id]))); ?>#rs-history"><?php echo (int) $value; ?></a>
                            <?php else : ?><?php echo (int) $value; ?><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$report['rows']) : ?><tr><td colspan="<?php echo 3 + count($columns) + count($locations); ?>"><?php echo Text::_('COM_JEM_RS_EMPTY'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <nav class="rs-pagination" aria-label="<?php echo Text::_('COM_JEM_RS_PAGES'); ?>">
        <?php if ($report['page'] > 1) : ?><a href="<?php echo $escape($url(array_replace($query, ['rs_page' => $report['page'] - 1]))); ?>"><?php echo Text::_('JPREV'); ?></a><?php endif; ?>
        <span><?php echo Text::sprintf('COM_JEM_RS_PAGE', $report['page'], $report['pages'], $report['groups']); ?></span>
        <?php if ($report['page'] < $report['pages']) : ?><a href="<?php echo $escape($url(array_replace($query, ['rs_page' => $report['page'] + 1]))); ?>"><?php echo Text::_('JNEXT'); ?></a><?php endif; ?>
    </nav>
    <p class="rs-meta"><?php echo Text::_('COM_JEM_RS_LEGEND'); ?></p>
    <?php if ($report['historyId']) : ?>
        <div id="rs-history" class="rs-scroll">
            <h2><?php echo Text::_('COM_JEM_RS_HISTORY') . ': ' . $escape($report['historyTitle']); ?></h2>
            <table class="table"><thead><tr>
                <?php foreach (['WHEN', 'ACTION', 'BEFORE', 'AFTER', 'CANCELLED'] as $name) : ?><th scope="col"><?php echo Text::_('COM_JEM_RS_' . $name); ?></th><?php endforeach; ?>
            </tr></thead><tbody>
            <?php foreach (array_slice($report['history'], 0, 50) as $entry) : ?>
                <tr><td><?php echo $escape(HTMLHelper::_('date', $entry->occurred, 'Y-m-d H:i:s', Factory::getApplication()->get('offset', 'UTC'))); ?></td>
                    <td><?php echo $escape($entry->action); ?></td>
                    <td><?php echo $entry->old_status === null ? '—' : $escape(Text::_('COM_JEM_RS_STATUS_' . (int) $entry->old_status) . ': ' . (int) $entry->old_places); ?></td>
                    <td><?php echo $entry->new_status === null ? '—' : $escape(Text::_('COM_JEM_RS_STATUS_' . (int) $entry->new_status) . ': ' . (int) $entry->new_places); ?></td>
                    <td><?php echo JemRegistrationStatus::cancelled($entry); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$report['history']) : ?><tr><td colspan="5"><?php echo Text::_('COM_JEM_RS_NO_HISTORY'); ?></td></tr><?php endif; ?>
            </tbody></table>
            <?php if ($report['historyPage'] > 1) : ?><a href="<?php echo $escape($url(array_replace($query, ['rs_history' => $report['historyId'], 'rs_history_page' => $report['historyPage'] - 1]))); ?>#rs-history"><?php echo Text::_('JPREV'); ?></a><?php endif; ?>
            <?php if (count($report['history']) > 50) : ?><a href="<?php echo $escape($url(array_replace($query, ['rs_history' => $report['historyId'], 'rs_history_page' => $report['historyPage'] + 1]))); ?>#rs-history"><?php echo Text::_('JNEXT'); ?></a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
