<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$reportTimezoneName = (string) ($this->diagnosticReport['component']['timezone']
    ?? Factory::getApplication()->get('offset', 'UTC'));
try {
    $reportTimezone = new \DateTimeZone($reportTimezoneName);
} catch (\Throwable $error) {
    $reportTimezoneName = 'UTC';
    $reportTimezone = new \DateTimeZone('UTC');
}
$formatReportDate = static function (string $date) use ($reportTimezone): string {
    try {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))
            ->setTimezone($reportTimezone)->format('Y-m-d H:i:s');
    } catch (\Throwable $error) {
        return $date;
    }
};

HTMLHelper::_('script', 'com_jem/housekeeping-diagnostics-sort.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
?>
<div class="container-fluid jem-housekeeping-tool">
    <div class="row g-3 mb-4">
        <div class="col-12"><div class="card h-100"><div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?php echo $escape(Uri::root(true) . '/media/com_jem/images/icon-48-diagnostics.svg'); ?>" width="48" height="48" alt="">
                    <div>
                        <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_TOOLS_DIAGNOSTICS'); ?></h2>
                        <p class="mb-0 text-muted"><?php echo Text::_('COM_JEM_TOOLS_DIAGNOSTICS_DESC'); ?></p>
                        <?php if (is_array($this->diagnosticReport)) : ?>
                        <p class="mb-0 mt-2">
                            <span class="text-nowrap"><strong><?php echo Text::_('COM_JEM_TOOLS_COMPONENT_VERSION'); ?>:</strong>
                                <?php echo $escape($this->diagnosticReport['component']['version'] ?: Text::_('COM_JEM_TOOLS_UNKNOWN')); ?></span>
                            &ndash;
                            <span class="text-nowrap"><strong><?php echo Text::_('COM_JEM_TOOLS_SCHEMA_VERSION'); ?>:</strong>
                                <?php echo $escape($this->diagnosticReport['component']['schema_version'] ?: Text::_('COM_JEM_TOOLS_UNKNOWN')); ?></span>
                            &ndash;
                            <span class="text-nowrap"><strong><?php echo Text::_('COM_JEM_TOOLS_EXTENSION_ID'); ?>:</strong>
                                <a href="<?php echo $escape(Route::_('index.php?option=com_installer&view=manage&filter_search=id:' . (int) $this->diagnosticReport['component']['id'], false)); ?>">#<?php echo (int) $this->diagnosticReport['component']['id']; ?></a></span>
                        </p>
                        <p class="mb-0 mt-1">
                            <span class="text-nowrap"><strong><?php echo Text::_('COM_JEM_TOOLS_VERSION_DATE'); ?>:</strong>
                                <?php echo $escape(($this->diagnosticReport['component']['version_date'] ?? '') ?: Text::_('COM_JEM_TOOLS_UNKNOWN')); ?></span>
                            &ndash;
                            <span class="text-nowrap"><strong><?php echo Text::_(
                                ($this->diagnosticReport['component']['installed_action'] ?? '') === 'update'
                                    ? 'COM_JEM_TOOLS_LAST_UPDATE' : 'COM_JEM_TOOLS_INSTALLATION_DATE'
                            ); ?>:</strong>
                                <?php echo $escape(($this->diagnosticReport['component']['installed_at'] ?? '') ?: Text::_('COM_JEM_TOOLS_NOT_RECORDED')); ?></span>
                            &ndash;
                            <span class="text-nowrap"><strong><?php echo Text::_('COM_JEM_TOOLS_SITE_TIMEZONE'); ?>:</strong>
                                <?php echo $escape(($this->diagnosticReport['component']['timezone'] ?? '') ?: Text::_('COM_JEM_TOOLS_UNKNOWN')); ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($this->diagnosticStored) : ?>
                    <div class="ms-auto text-end">
                        <div class="fw-bold"><?php echo Text::sprintf('COM_JEM_TOOLS_REPORT_HEADING', (int) $this->diagnosticStored['id']); ?></div>
                        <div class="small text-muted"><?php echo Text::_('COM_JEM_TOOLS_EXECUTED_AT'); ?>:
                            <?php echo $escape($formatReportDate($this->diagnosticStored['created'])); ?>
                            (<?php echo $escape($reportTimezoneName); ?>)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mt-3">
                <?php if ($this->diagnosticRecent) : ?>
                    <div class="d-flex align-items-center gap-2 small flex-grow-1 overflow-auto" style="min-width: 0;">
                        <span class="fw-semibold flex-shrink-0"><?php echo Text::_('COM_JEM_TOOLS_RECENT_REPORTS'); ?>:</span>
                        <?php foreach ($this->diagnosticRecent as $recent) : ?>
                            <div class="d-inline-flex align-items-center flex-shrink-0 border rounded<?php echo $this->diagnosticReportId === (int) $recent->id ? ' border-primary' : ''; ?>">
                                <a class="px-2 py-1" href="<?php echo $escape(Route::_('index.php?option=com_jem&view=housekeeping&tool=diagnostics&report=' . (int) $recent->id, false)); ?>"
                                   <?php echo $this->diagnosticReportId === (int) $recent->id ? 'aria-current="page"' : ''; ?>
                                   title="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OPEN_SAVED_REPORT', (int) $recent->id)); ?>">
                                    #<?php echo (int) $recent->id; ?> (<?php echo $escape($formatReportDate((string) $recent->created)); ?>)
                                </a>
                                <?php if ($this->diagnosticDeleteAllowed) : ?>
                                    <form class="m-0" method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.deleteDiagnosticReport'); ?>"
                                          onsubmit="return confirm(<?php echo $escape(json_encode(Text::sprintf('COM_JEM_TOOLS_DELETE_REPORT_CONFIRM', (int) $recent->id))); ?>);">
                                        <input type="hidden" name="report_id" value="<?php echo (int) $recent->id; ?>">
                                        <input type="hidden" name="return_report" value="<?php echo (int) $this->diagnosticReportId; ?>">
                                        <input type="hidden" name="delete_report_nonce" value="<?php echo $escape($this->diagnosticDeleteNonce); ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button class="btn btn-sm btn-link text-danger px-2" type="submit"
                                                title="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_DELETE_REPORT', (int) $recent->id)); ?>"
                                                aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_DELETE_REPORT', (int) $recent->id)); ?>">
                                            <span class="icon-times" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form class="d-flex justify-content-end flex-shrink-0 ms-lg-auto" method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.runDiagnostics'); ?>">
                    <button class="btn btn-primary" type="submit"><?php echo Text::_('COM_JEM_TOOLS_RUN_CHECK'); ?></button>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </div>
            <form class="d-flex flex-wrap align-items-end gap-2 mt-3" method="post" enctype="multipart/form-data"
                  action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.previewDiagnostics'); ?>">
                <div class="flex-grow-1" style="min-width: 16rem;">
                    <label class="form-label" for="jem-diagnostic-file"><?php echo Text::_('COM_JEM_TOOLS_OPEN_REPORT'); ?></label>
                    <input class="form-control" id="jem-diagnostic-file" name="diagnostic_file" type="file"
                           accept="<?php echo $this->diagnosticDebugAll ? '.json,.zip,application/json,application/zip' : '.json,application/json'; ?>" required>
                </div>
                <button class="btn btn-outline-primary" type="submit"><?php echo Text::_('COM_JEM_TOOLS_OPEN_REPORT'); ?></button>
                <span class="small text-muted"><?php echo Text::_($this->diagnosticDebugAll
                    ? 'COM_JEM_TOOLS_OPEN_REPORT_DEBUG_DESC' : 'COM_JEM_TOOLS_OPEN_REPORT_DESC'); ?></span>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </div></div></div>
    </div>

    <?php if ($this->diagnosticImported) : ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="h5 mb-0"><?php echo Text::_('COM_JEM_TOOLS_IMPORTED_REPORT'); ?></h3>
                    <span class="small text-muted"><?php echo $escape(strtoupper($this->diagnosticImported['source'])); ?>
                        <?php echo $escape((string) ($this->diagnosticImported['data']['created_at'] ?? '')); ?></span>
                </div>
                <p class="text-muted mt-2"><?php echo Text::_('COM_JEM_TOOLS_IMPORTED_REPORT_DESC'); ?></p>
                <details>
                    <summary><?php echo Text::_('COM_JEM_TOOLS_IMPORTED_REPORT_CONTENT'); ?></summary>
                    <pre class="border rounded p-3 mt-2 overflow-auto" style="max-height: 38rem;"><?php echo $escape($this->diagnosticImported['pretty']); ?></pre>
                </details>
            </div>
        </div>
    <?php endif; ?>

    <?php if (is_array($this->diagnosticReport)) : ?>
        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <h3 class="h5 mb-0"><?php echo Text::_('COM_JEM_TOOLS_CONTENT_INVENTORY'); ?></h3>
                            <label class="form-check-label small">
                                <input class="form-check-input" type="checkbox" form="jem-diagnostic-support-form" name="include_inventory" value="1">
                                <?php echo Text::_('COM_JEM_TOOLS_REPORT_INCLUDE'); ?>
                            </label>
                        </div>
                        <div class="table-responsive">
                        <table class="table table-sm mb-0" data-jem-sort-table data-jem-default-sort="id">
                            <thead>
                                <tr>
                                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_STATUS'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number" data-jem-sort-id-column><?php echo Text::_('COM_JEM_TOOLS_LAST_ID'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_TOOLS_PUBLIC'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_TOOLS_PRIVATE'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('JARCHIVED'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('JTRASHED'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_ATTACHMENT_ORPHANED'); ?></th>
                                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_TOOLS_COUNT'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array(
                                    'events' => 'COM_JEM_TOOLS_EVENTS',
                                    'categories' => 'COM_JEM_TOOLS_CATEGORIES',
                                    'venues' => 'COM_JEM_TOOLS_VENUES',
                                    'types' => 'COM_JEM_TYPES',
                                    'special_days' => 'COM_JEM_SPECIAL_DAYS',
                                    'attachments' => 'COM_JEM_ATTACHMENTS',
                                ) as $kind => $label) : ?>
                                    <?php $inventory = $this->diagnosticReport['inventory'][$kind] ?? null; ?>
                                    <tr data-jem-sort-id="<?php echo (int) ($inventory['last_id'] ?? 0); ?>">
                                        <th scope="row">
                                            <?php echo Text::_($label); ?>
                                        </th>
                                        <?php if ($inventory === null) : ?>
                                            <td colspan="8" class="text-end text-muted"><?php echo Text::_('COM_JEM_TOOLS_RERUN_INVENTORY'); ?></td>
                                        <?php else : ?>
                                            <td class="text-nowrap">
                                                <span class="<?php echo $kind === 'attachments' ? 'icon-file text-secondary' : 'icon-publish text-success'; ?> me-1" aria-hidden="true"></span>
                                                <?php echo Text::_($kind === 'attachments' ? 'COM_JEM_TOOLS_STORED' : 'COM_JEM_PUBLISHED'); ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if (!array_key_exists('last_id', $inventory)) : ?>
                                                    <?php echo Text::_('COM_JEM_TOOLS_RERUN_INVENTORY'); ?>
                                                <?php elseif ((int) $inventory['last_id'] > 0) : ?>
                                                    <?php $editTasks = array('events' => 'event', 'categories' => 'category', 'venues' => 'venue', 'types' => 'type', 'special_days' => 'specialday', 'attachments' => 'attachment'); ?>
                                                    <a href="<?php echo $escape(Route::_('index.php?option=com_jem&task=' . $editTasks[$kind] . '.edit&id=' . (int) $inventory['last_id'], false)); ?>">#<?php echo (int) $inventory['last_id']; ?></a>
                                                <?php else : ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?php echo isset($inventory['public']) ? (int) $inventory['public'] : '&mdash;'; ?></td>
                                            <td class="text-end"><?php echo isset($inventory['private']) ? (int) $inventory['private'] : '&mdash;'; ?></td>
                                            <td class="text-end"><?php echo isset($inventory['archived']) ? (int) $inventory['archived'] : '&mdash;'; ?></td>
                                            <td class="text-end"><?php echo isset($inventory['trashed']) ? (int) $inventory['trashed'] : '&mdash;'; ?></td>
                                            <td class="text-end<?php echo $kind === 'attachments' && !empty($inventory['orphaned']) ? ' text-danger fw-bold' : ''; ?>">
                                                <?php echo $kind === 'attachments' && isset($inventory['orphaned']) ? (int) $inventory['orphaned'] : '&mdash;'; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php echo $kind === 'attachments'
                                                    ? (int) $inventory['total']
                                                    : (isset($inventory['active'], $inventory['archived'], $inventory['trashed'])
                                                        ? (int) $inventory['active'] + (int) $inventory['archived'] + (int) $inventory['trashed']
                                                        : '&mdash;'); ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="small text-muted mt-1"><?php echo Text::_('COM_JEM_TOOLS_PUBLIC_PRIVATE_NOTE'); ?></div>
                    </div></div></div>
            <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body">
                <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_TYPES_BLOCK'); ?>:
                    <?php echo (int) ($this->diagnosticReport['types']['active'] ?? 0); ?> /
                    <?php echo (int) ($this->diagnosticReport['types']['total'] ?? 0); ?>
                </h3>
                <?php if (!isset($this->diagnosticReport['types'])) : ?>
                    <p><?php echo Text::_('COM_JEM_TOOLS_RERUN_INVENTORY'); ?></p>
                <?php else : ?>
                    <div class="table-responsive"><table class="table table-sm jem-tools-extension-table mb-0" data-jem-sort-table data-jem-default-sort="id">
                        <thead><tr>
                            <th scope="col"><input class="form-check-input" type="checkbox" data-jem-select-all="report"
                                                   aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_REPORT_SELECT_ALL')); ?>"></th>
                            <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                            <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_TYPE'); ?></th>
                            <th scope="col" data-jem-sort="number" data-jem-sort-id-column><?php echo Text::_('COM_JEM_TOOLS_ID'); ?></th>
                            <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_TOOLS_COUNT'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($this->diagnosticReport['types']['items'] as $type) : ?>
                                <tr data-jem-sort-id="<?php echo (int) $type['id']; ?>">
                                    <td><input class="form-check-input jem-diagnostic-report-item" type="checkbox" form="jem-diagnostic-support-form"
                                               name="include_type_items[]" value="<?php echo (int) $type['id']; ?>"
                                               aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_INCLUDE_ITEM_SETTINGS', $type['name'])); ?>"></td>
                                    <th scope="row" data-jem-sort-value="<?php echo $escape($type['name']); ?>">
                                        <span class="tbody-icon" aria-hidden="true"><span class="<?php echo $type['active'] ? 'icon-publish' : 'icon-unpublish'; ?>"></span></span>
                                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_TOOLS_' . ($type['active'] ? 'ACTIVE' : 'INACTIVE')); ?></span>
                                        <?php echo $escape($type['name']); ?>
                                    </th>
                                    <td><?php echo Text::_('COM_JEM_TOOLS_TYPE_ENTITY_' . (int) $type['entity']); ?></td>
                                    <td><a href="<?php echo $escape(Route::_('index.php?option=com_jem&task=type.edit&id=' . (int) $type['id'], false)); ?>">#<?php echo (int) $type['id']; ?></a></td>
                                    <td class="text-end"><?php echo (int) $type['count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div></div></div>
            <?php foreach (array('modules' => 'module_items', 'plugins' => 'plugin_items') as $kind => $itemsKey) : ?>
                <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body">
                    <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_' . strtoupper($kind)); ?>:
                        <?php echo (int) $this->diagnosticReport[$kind]['active']; ?> / <?php echo (int) $this->diagnosticReport[$kind]['total']; ?>
                    </h3>
                    <div class="table-responsive">
                    <table class="table table-sm jem-tools-extension-table mb-0" data-jem-sort-table data-jem-default-sort="id">
                        <thead><tr>
                            <th scope="col"><input class="form-check-input" type="checkbox" data-jem-select-all="report"
                                                   aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_REPORT_SELECT_ALL')); ?>"></th>
                            <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                            <th scope="col" data-jem-sort="text"><?php echo Text::_($kind === 'modules' ? 'COM_JEM_TOOLS_TYPE' : 'COM_JEM_TOOLS_EXTENSION'); ?></th>
                            <th scope="col" data-jem-sort="number" data-jem-sort-id-column><?php echo Text::_('COM_JEM_TOOLS_ID'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($this->diagnosticReport[$itemsKey] as $item) : ?>
                                <tr data-jem-sort-id="<?php echo (int) $item['id']; ?>">
                                    <td>
                                        <?php if ($item['active']) : ?>
                                            <input class="form-check-input jem-diagnostic-report-item" type="checkbox" form="jem-diagnostic-support-form"
                                                   name="include_<?php echo $kind === 'modules' ? 'module' : 'plugin'; ?>_settings[]"
                                                   value="<?php echo (int) $item['id']; ?>"
                                                   aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_INCLUDE_ITEM_SETTINGS', $item['name'])); ?>">
                                        <?php else : ?>&mdash;<?php endif; ?>
                                    </td>
                                    <th scope="row" data-jem-sort-value="<?php echo $escape($item['name']); ?>">
                                        <span class="tbody-icon" aria-hidden="true"><span class="<?php echo $item['active'] ? 'icon-publish' : 'icon-unpublish'; ?>"></span></span>
                                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_TOOLS_' . ($item['active'] ? 'ACTIVE' : 'INACTIVE')); ?></span>
                                        <?php if ($kind === 'modules') : ?>
                                            <a href="<?php echo $escape(Route::_('index.php?option=com_modules&task=module.edit&id=' . (int) $item['id'], false)); ?>"><?php echo $escape($item['name']); ?></a>
                                        <?php else : ?>
                                            <?php echo $escape($item['name']); ?>
                                        <?php endif; ?>
                                    </th>
                                    <td data-label="<?php echo $escape(Text::_($kind === 'modules' ? 'COM_JEM_TOOLS_TYPE' : 'COM_JEM_TOOLS_EXTENSION')); ?>"><?php echo $escape($item['module']); ?></td>
                                    <td data-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_ID')); ?>">
                                        <?php if ($kind === 'modules') : ?>
                                            <a href="<?php echo $escape(Route::_('index.php?option=com_modules&task=module.edit&id=' . (int) $item['id'], false)); ?>">#<?php echo (int) $item['id']; ?></a>
                                        <?php else : ?>
                                            <a href="<?php echo $escape(Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $item['id'], false)); ?>">#<?php echo (int) $item['id']; ?></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div></div></div>
            <?php endforeach; ?>
            <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body">
            <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_MENU_VIEWS'); ?>:
                <?php echo (int) $this->diagnosticReport['menu_views']['active']; ?> / <?php echo (int) $this->diagnosticReport['menu_views']['total']; ?>
            </h3>
            <div class="table-responsive">
            <table class="table table-sm mb-0" data-jem-sort-table data-jem-sort-groups data-jem-default-sort="id">
                <thead><tr>
                    <th scope="col"><input class="form-check-input" type="checkbox" data-jem-select-all="report"
                                           aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_REPORT_SELECT_ALL')); ?>"></th>
                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_CONFIGURED_LAYOUT'); ?></th>
                    <th scope="col" data-jem-sort="number" data-jem-sort-id-column><?php echo Text::_('COM_JEM_TOOLS_MENU_ITEM_IDS'); ?></th>
                    <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_TOOLS_COUNT'); ?></th>
                </tr></thead>
                <?php foreach ($this->diagnosticReport['menu_views']['items'] as $view) : ?>
                    <?php $menuItems = array_values((array) ($view['menu_items'] ?? array())); ?>
                    <?php $rowspan = max(1, count($menuItems)); ?>
                    <tbody data-jem-sort-id="<?php echo (int) ($menuItems[0]['id'] ?? 0); ?>">
                        <?php foreach ($menuItems ?: array(null) as $menuIndex => $menuItem) : ?>
                            <tr>
                                <?php if ($menuIndex === 0) : ?>
                                    <td rowspan="<?php echo $rowspan; ?>">
                                        <?php if ($view['active'] && isset($view['menu_items'])) : ?>
                                            <input class="form-check-input jem-diagnostic-report-item" type="checkbox" form="jem-diagnostic-support-form"
                                                   name="include_view_types[]" value="<?php echo $escape($view['name']); ?>"
                                                   aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_INCLUDE_VIEW_SETTINGS', $view['name'])); ?>">
                                        <?php else : ?>&mdash;<?php endif; ?>
                                    </td>
                                    <th scope="rowgroup" rowspan="<?php echo $rowspan; ?>"><?php echo $escape($view['name']); ?></th>
                                <?php endif; ?>
                                <td data-jem-sort-value="<?php echo $escape($menuItem['layout'] ?? ''); ?>">
                                    <?php if (!isset($menuItem['layout'])) : ?>
                                        <?php echo Text::_('COM_JEM_TOOLS_RERUN_MENU_IDS'); ?>
                                    <?php else : ?>
                                        <?php echo $escape($menuItem['layout']); ?>
                                        <?php if (!empty($menuItem['mode'])) : ?>
                                            &middot;
                                            <?php $modeLabels = array(
                                                'calendar' => 'COM_JEM_PDF_CALENDAR_LAYOUT_CALENDAR',
                                                'agenda' => $view['name'] === 'weekcal'
                                                    ? 'COM_JEM_CALENDAR_MODE_LIST' : 'COM_JEM_PDF_CALENDAR_LAYOUT_AGENDA',
                                                'list' => 'COM_JEM_CALENDAR_MODE_LIST',
                                                'timetable' => 'COM_JEM_CALENDAR_MODE_TIMETABLE',
                                            ); ?>
                                            <?php echo isset($modeLabels[$menuItem['mode']])
                                                ? Text::_($modeLabels[$menuItem['mode']]) : $escape($menuItem['mode']); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-jem-sort-value="<?php echo (int) ($menuItem['id'] ?? 0); ?>">
                                    <?php if ($menuItem === null) : ?>
                                        <span class="text-muted"><?php echo Text::_('COM_JEM_TOOLS_RERUN_MENU_IDS'); ?></span>
                                    <?php else : ?>
                                        <span class="tbody-icon" aria-hidden="true"><span class="<?php echo $menuItem['published'] ? 'icon-publish' : 'icon-unpublish'; ?>"></span></span>
                                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_TOOLS_' . ($menuItem['published'] ? 'ACTIVE' : 'INACTIVE')); ?></span>
                                        <a href="<?php echo $escape(Route::_('index.php?option=com_menus&task=item.edit&id=' . (int) $menuItem['id'], false)); ?>"
                                           class="<?php echo $menuItem['published'] ? '' : 'text-muted'; ?>">#<?php echo (int) $menuItem['id']; ?> <?php echo $escape($menuItem['title'] ?? ''); ?></a>
                                        <?php if (!$menuItem['published']) : ?>
                                            <small class="text-muted">(<?php echo Text::_('COM_JEM_TOOLS_INACTIVE'); ?>)</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php if ($menuIndex === 0) : ?>
                                    <td class="text-end" rowspan="<?php echo $rowspan; ?>"><?php echo (int) $view['active']; ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
            </div>
            </div></div></div>
        </div>

        <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body">
            <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_LANGUAGES'); ?>:
                <?php echo (int) $this->diagnosticReport['languages']['active']; ?> / <?php echo (int) $this->diagnosticReport['languages']['total']; ?>
            </h3>
            <div class="table-responsive"><table class="table table-sm jem-tools-extension-table mb-0" data-jem-sort-table data-jem-default-sort="id">
                <thead><tr>
                    <th scope="col"><input class="form-check-input" type="checkbox" data-jem-select-all="report"
                                           aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_REPORT_SELECT_ALL')); ?>"></th>
                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_LANGUAGE_CODE'); ?></th>
                    <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_JEM_LANGUAGE_VERSION'); ?></th>
                    <th scope="col" data-jem-sort="number" data-jem-sort-id-column><?php echo Text::_('COM_JEM_TOOLS_ID'); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($this->diagnosticReport['languages']['items'] as $language) : ?>
                        <tr data-jem-sort-id="<?php echo (int) $language['id']; ?>">
                            <td>
                                <?php if ($language['active'] && !empty($language['jem_installed'])) : ?>
                                    <input class="form-check-input jem-diagnostic-report-item" type="checkbox" form="jem-diagnostic-support-form"
                                           name="include_language_ini[]" value="<?php echo $escape($language['code']); ?>"
                                           aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_INCLUDE_LANGUAGE_INI', $language['code'])); ?>">
                                <?php else : ?>&mdash;<?php endif; ?>
                            </td>
                            <th scope="row" data-jem-sort-value="<?php echo $escape($language['name']); ?>">
                                <span class="tbody-icon" aria-hidden="true"><span class="<?php echo $language['active'] ? 'icon-publish' : 'icon-unpublish'; ?>"></span></span>
                                <span class="visually-hidden"><?php echo Text::_('COM_JEM_TOOLS_' . ($language['active'] ? 'ACTIVE' : 'INACTIVE')); ?></span>
                                <?php echo $escape($language['name']); ?>
                            </th>
                            <td><?php echo $escape($language['code']); ?></td>
                            <td>
                                <?php if (!array_key_exists('jem_installed', $language)) : ?>
                                    <?php echo Text::_('COM_JEM_TOOLS_RERUN_LANGUAGE_VERSIONS'); ?>
                                <?php elseif (!$language['jem_installed']) : ?>
                                    <?php echo Text::_('COM_JEM_LANGUAGES_NOT_INSTALLED'); ?>
                                <?php else : ?>
                                    <?php echo $escape($language['jem_version'] ?: Text::_('COM_JEM_TOOLS_UNKNOWN')); ?>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?php echo $escape(Route::_('index.php?option=com_languages&task=language.edit&lang_id=' . (int) $language['id'], false)); ?>">#<?php echo (int) $language['id']; ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></div></div>

        <div class="col-12"><div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDES'); ?></h3>
                <label class="form-check-label small">
                    <input class="form-check-input" type="checkbox" form="jem-diagnostic-support-form" name="include_template_overrides" value="1">
                    <?php echo Text::_('COM_JEM_TOOLS_REPORT_INCLUDE'); ?>
                </label>
            </div>
            <p class="text-muted"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDES_DESC'); ?></p>
            <?php if ($this->overrideDeleteAllowed) : ?>
                <p class="small text-muted"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDES_DELETE_DESC'); ?></p>
            <?php endif; ?>
            <?php if (!isset($this->diagnosticReport['overrides']['templates'])) : ?>
                <p class="mb-0"><?php echo Text::_('COM_JEM_TOOLS_RERUN_OVERRIDES'); ?></p>
            <?php else : ?>
                <div class="table-responsive"><table class="table table-sm mb-3" data-jem-sort-table data-jem-delete-form="jem-diagnostic-delete-template-form">
                    <thead><tr>
                        <th scope="col">
                            <?php if ($this->overrideDeleteAllowed) : ?>
                                <input class="form-check-input" type="checkbox" data-jem-select-all="overrides"
                                       aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_OVERRIDES_SELECT_ALL')); ?>">
                            <?php else : ?><?php echo Text::_('COM_JEM_TOOLS_SELECT'); ?><?php endif; ?>
                        </th>
                        <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_TYPE'); ?></th>
                        <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                        <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDE_TARGET'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDE_FOUND_AT'); ?></th>
                        <th scope="col"><span class="visually-hidden"><?php echo Text::_('JACTION_DELETE'); ?></span></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($this->diagnosticReport['overrides']['templates'] ?? array() as $override) : ?>
                            <tr>
                                <td>
                                    <?php if ($this->overrideDeleteAllowed && $override['winner'] !== '') : ?>
                                        <input class="form-check-input jem-diagnostic-override-item" type="checkbox"
                                               form="jem-diagnostic-delete-template-form" name="override_items[]"
                                               value="<?php echo $escape(JemHousekeepingOverrides::selectionId('template', $override['winner'])); ?>"
                                               aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OVERRIDES_SELECT_ITEM', $override['winner'])); ?>">
                                    <?php else : ?>&mdash;<?php endif; ?>
                                </td>
                                <td><?php echo $escape(ucfirst($override['kind'])); ?></td>
                                <td><?php echo $escape($override['client'] . ' / ' . $override['template']); ?></td>
                                <td><code><?php echo $escape($override['target']); ?></code></td>
                                <td><code><?php echo $escape($override['winner']); ?></code></td>
                                <td>
                                    <?php if ($this->overrideDeleteAllowed && $override['winner'] !== '') : ?>
                                        <button class="btn btn-sm btn-outline-danger jem-diagnostic-delete-one" type="submit"
                                                form="jem-diagnostic-delete-one-form" name="override_items[]"
                                                value="<?php echo $escape(JemHousekeepingOverrides::selectionId('template', $override['winner'])); ?>"
                                                data-jem-override-path="<?php echo $escape($override['winner']); ?>"
                                                title="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OVERRIDES_DELETE_ONE', $override['winner'])); ?>"
                                                aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OVERRIDES_DELETE_ONE', $override['winner'])); ?>" disabled>
                                            <span class="icon-trash" aria-hidden="true"></span>
                                        </button>
                                    <?php else : ?>&mdash;<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($this->diagnosticReport['overrides']['templates'])) : ?>
                            <tr><td colspan="6"><?php echo Text::_('COM_JEM_TOOLS_NO_TEMPLATE_OVERRIDES'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table></div>
                <?php if ($this->overrideDeleteAllowed) : ?>
                    <form id="jem-diagnostic-delete-template-form" method="post"
                          action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.deleteOverrides'); ?>"
                          data-jem-confirm="<?php echo $escape(Text::_('COM_JEM_TOOLS_OVERRIDES_DELETE_CONFIRM')); ?>"
                          class="d-flex justify-content-end">
                        <input type="hidden" name="report_id" value="<?php echo (int) $this->diagnosticStored['id']; ?>">
                        <input type="hidden" name="delete_override_nonce" value="<?php echo $escape($this->overrideDeleteNonce); ?>">
                        <button class="btn btn-danger" type="submit" disabled><?php echo Text::_('COM_JEM_TOOLS_OVERRIDES_DELETE_SELECTED'); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div></div></div>

        <div class="col-12"><div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><?php echo Text::_('COM_JEM_TOOLS_LANGUAGE_OVERRIDES'); ?></h3>
                <label class="form-check-label small">
                    <input class="form-check-input" type="checkbox" form="jem-diagnostic-support-form" name="include_language_overrides" value="1">
                    <?php echo Text::_('COM_JEM_TOOLS_REPORT_INCLUDE'); ?>
                </label>
            </div>
            <p class="text-muted"><?php echo Text::_('COM_JEM_TOOLS_LANGUAGE_OVERRIDES_DESC'); ?></p>
            <?php if (!isset($this->diagnosticReport['overrides']['languages'])) : ?>
                <p class="mb-0"><?php echo Text::_('COM_JEM_TOOLS_RERUN_OVERRIDES'); ?></p>
            <?php else : ?>
                <div class="table-responsive"><table class="table table-sm mb-3" data-jem-sort-table data-jem-delete-form="jem-diagnostic-delete-language-form">
                    <thead><tr>
                        <th scope="col">
                            <?php if ($this->overrideDeleteAllowed) : ?>
                                <input class="form-check-input" type="checkbox" data-jem-select-all="overrides"
                                       aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_OVERRIDES_SELECT_ALL')); ?>">
                            <?php else : ?><?php echo Text::_('COM_JEM_TOOLS_SELECT'); ?><?php endif; ?>
                        </th>
                        <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_LANGUAGE_CODE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_LANGUAGE_OVERRIDE_KEYS'); ?></th>
                        <th scope="col" class="text-end" data-jem-sort="number"><?php echo Text::_('COM_JEM_TOOLS_COUNT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDE_FOUND_AT'); ?></th>
                        <th scope="col"><span class="visually-hidden"><?php echo Text::_('JACTION_DELETE'); ?></span></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($this->diagnosticReport['overrides']['languages'] as $override) : ?>
                            <tr>
                                <td>
                                    <?php if ($this->overrideDeleteAllowed) : ?>
                                        <input class="form-check-input jem-diagnostic-override-item" type="checkbox"
                                               form="jem-diagnostic-delete-language-form" name="override_items[]"
                                               value="<?php echo $escape(JemHousekeepingOverrides::selectionId('language', $override['path'], $override['keys'])); ?>"
                                               aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OVERRIDES_SELECT_ITEM', $override['path'])); ?>">
                                    <?php else : ?>&mdash;<?php endif; ?>
                                </td>
                                <th scope="row"><?php echo $escape($override['client'] . ' / ' . $override['language']); ?></th>
                                <td><small><?php echo $escape(implode(', ', $override['keys'])); ?></small></td>
                                <td class="text-end"><?php echo count($override['keys']); ?></td>
                                <td><code><?php echo $escape($override['path']); ?></code></td>
                                <td>
                                    <?php if ($this->overrideDeleteAllowed) : ?>
                                        <button class="btn btn-sm btn-outline-danger jem-diagnostic-delete-one" type="submit"
                                                form="jem-diagnostic-delete-one-form" name="override_items[]"
                                                value="<?php echo $escape(JemHousekeepingOverrides::selectionId('language', $override['path'], $override['keys'])); ?>"
                                                data-jem-override-path="<?php echo $escape($override['path']); ?>"
                                                title="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OVERRIDES_DELETE_ONE', $override['path'])); ?>"
                                                aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_OVERRIDES_DELETE_ONE', $override['path'])); ?>" disabled>
                                            <span class="icon-trash" aria-hidden="true"></span>
                                        </button>
                                    <?php else : ?>&mdash;<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($this->diagnosticReport['overrides']['languages'])) : ?>
                            <tr><td colspan="6"><?php echo Text::_('COM_JEM_TOOLS_NO_LANGUAGE_OVERRIDES'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table></div>
                <?php if ($this->overrideDeleteAllowed) : ?>
                    <form id="jem-diagnostic-delete-language-form" method="post"
                          action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.deleteOverrides'); ?>"
                          data-jem-confirm="<?php echo $escape(Text::_('COM_JEM_TOOLS_OVERRIDES_DELETE_CONFIRM')); ?>"
                          class="d-flex justify-content-end">
                        <input type="hidden" name="report_id" value="<?php echo (int) $this->diagnosticStored['id']; ?>">
                        <input type="hidden" name="delete_override_nonce" value="<?php echo $escape($this->overrideDeleteNonce); ?>">
                        <button class="btn btn-danger" type="submit" disabled><?php echo Text::_('COM_JEM_TOOLS_OVERRIDES_DELETE_SELECTED'); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($this->overrideDeleteAllowed) : ?>
                <form id="jem-diagnostic-delete-one-form" method="post"
                      action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.deleteOverrides'); ?>"
                      data-jem-confirm="<?php echo $escape(Text::_('COM_JEM_TOOLS_OVERRIDES_DELETE_CONFIRM')); ?>">
                    <input type="hidden" name="report_id" value="<?php echo (int) $this->diagnosticStored['id']; ?>">
                    <input type="hidden" name="delete_override_nonce" value="<?php echo $escape($this->overrideDeleteNonce); ?>">
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            <?php endif; ?>
        </div></div></div>

        <div class="col-12"><div class="card h-100"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h5 mb-0"><?php echo Text::_('COM_JEM_TOOLS_CSS_OVERRIDES'); ?></h3>
                <div class="d-flex align-items-center gap-3">
                    <label class="form-check-label small">
                        <input class="form-check-input" type="checkbox" form="jem-diagnostic-support-form" name="include_css_overrides" value="1">
                        <?php echo Text::_('COM_JEM_TOOLS_REPORT_INCLUDE'); ?>
                    </label>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo $escape(Route::_('index.php?option=com_jem&view=cssmanager', false)); ?>">
                        <?php echo Text::_('COM_JEM_TOOLS_OPEN_CSS_MANAGER'); ?>
                    </a>
                </div>
            </div>
            <p class="text-muted"><?php echo Text::_('COM_JEM_TOOLS_CSS_OVERRIDES_DESC'); ?></p>
            <?php if (!isset($this->diagnosticReport['overrides']['css'])) : ?>
                <p class="mb-0"><?php echo Text::_('COM_JEM_TOOLS_RERUN_OVERRIDES'); ?></p>
            <?php else : ?>
                <div class="table-responsive"><table class="table table-sm mb-0" data-jem-sort-table>
                    <thead><tr>
                        <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_TYPE'); ?></th>
                        <th scope="col" data-jem-sort="text"><?php echo Text::_('COM_JEM_TOOLS_NAME'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_CSS_USED_BY'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_OVERRIDE_FOUND_AT'); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($this->diagnosticReport['overrides']['css'] as $css) : ?>
                            <tr>
                                <td><?php echo Text::_('COM_JEM_TOOLS_CSS_KIND_' . strtoupper($css['kind'])); ?></td>
                                <th scope="row"><?php echo $escape($css['name']); ?></th>
                                <td><?php echo $css['used_by'] ? $escape(implode(', ', $css['used_by'])) : '&mdash;'; ?></td>
                                <td><code><?php echo $escape($css['path']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($this->diagnosticReport['overrides']['css'])) : ?>
                            <tr><td colspan="4"><?php echo Text::_('COM_JEM_TOOLS_NO_CSS_OVERRIDES'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div></div></div>
        </div>
    <?php endif; ?>

    <?php if (is_array($this->diagnosticReport)) : ?>
        <?php $reportUrl = 'index.php?option=com_jem&view=housekeeping&tool=diagnostics&report=' . (int) $this->diagnosticStored['id']; ?>
        <?php $frontendChecks = $this->diagnosticReport['frontend_checks'] ?? array(); ?>
        <?php $attentionChecks = array_filter($frontendChecks, static fn($check): bool => in_array($check['status'], array('warning', 'error', 'review'), true)); ?>
        <div class="card mb-4" id="jem-diagnostic-verification-rules">
            <div class="card-body">
                <h3 class="h5 mb-3"><?php echo Text::_('COM_JEM_TOOLS_VERIFICATION_RULES'); ?></h3>
                <details class="border rounded" <?php echo $attentionChecks ? 'open' : ''; ?>>
                    <summary class="px-3 py-2 fw-semibold">
                        <?php echo Text::_('COM_JEM_TOOLS_FE_CHECKS_TITLE'); ?>
                        <?php if ($frontendChecks) : ?>
                            <span class="small text-muted fw-normal"><?php echo Text::sprintf(
                                'COM_JEM_TOOLS_FE_CHECKS_SUMMARY',
                                count(array_filter($frontendChecks, static fn($check): bool => $check['status'] === 'ok')),
                                count($frontendChecks)
                            ); ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="table-responsive"><table class="table table-sm mb-0">
                        <thead><tr>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_CHECK'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_STATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_DESCRIPTION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_ACTION'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($frontendChecks as $index => $check) : ?>
                                <?php $color = array('ok' => 'success', 'warning' => 'warning', 'error' => 'danger', 'review' => 'info', 'skip' => 'secondary')[$check['status']] ?? 'secondary'; ?>
                                <tr>
                                    <th scope="row"><?php echo (int) $index + 1; ?>. <?php echo $escape($check['label']); ?></th>
                                    <td><span class="badge bg-<?php echo $color; ?><?php echo $color === 'warning' || $color === 'info' ? ' text-dark' : ''; ?>"><?php echo Text::_('COM_JEM_TOOLS_FE_STATUS_' . strtoupper($check['status'])); ?></span></td>
                                    <td><?php echo $escape($check['detail']); ?></td>
                                    <td><a href="<?php echo $escape(Route::_($check['action_url'], false)); ?>"><?php echo Text::_('COM_JEM_TOOLS_FE_OPEN_SETTINGS'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$frontendChecks) : ?>
                                <tr><td colspan="4" class="text-muted"><?php echo Text::_('COM_JEM_TOOLS_FE_RERUN'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table></div>
                </details>
            </div>
        </div>
        <div class="card mb-4" id="jem-diagnostic-results">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h3 class="h5 mb-0">
                        <?php echo Text::_('COM_JEM_TOOLS_CONSOLE'); ?>
                        <?php if ($this->diagnosticSeverity !== '') : ?>
                            <small class="text-muted"><?php echo Text::sprintf(
                                'COM_JEM_TOOLS_SHOWING_SEVERITY',
                                Text::_('COM_JEM_TOOLS_COUNT_' . strtoupper($this->diagnosticSeverity))
                            ); ?></small>
                        <?php endif; ?>
                    </h3>
                    <?php if ($this->diagnosticSeverity !== '') : ?>
                        <a href="<?php echo $escape(Route::_($reportUrl . '#jem-diagnostic-results', false)); ?>">
                            <?php echo Text::_('COM_JEM_TOOLS_SHOW_ALL_FINDINGS'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="row row-cols-1 row-cols-sm-3 g-2 mb-3">
                    <?php foreach (array('notice' => 'info', 'warning' => 'warning', 'error' => 'danger') as $level => $color) : ?>
                        <div class="col">
                            <a class="d-flex align-items-center justify-content-center gap-2 h-100 rounded border border-<?php echo $color; ?> px-3 py-2 text-decoration-none text-body<?php echo $this->diagnosticSeverity === $level ? ' border-2 shadow-sm' : ''; ?>"
                               href="<?php echo $escape(Route::_($reportUrl . '&severity=' . $level . '#jem-diagnostic-results', false)); ?>"
                               <?php echo $this->diagnosticSeverity === $level ? 'aria-current="true"' : ''; ?>>
                                <span class="fs-5 fw-bold text-<?php echo $color; ?>"><?php echo (int) $this->diagnosticReport[$level]; ?></span>
                                <span><?php echo Text::_('COM_JEM_TOOLS_COUNT_' . strtoupper($level)); ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php $filteredLines = $this->diagnosticSeverity === ''
                    ? $this->diagnosticReport['lines']
                    : array_values(array_filter(
                        $this->diagnosticReport['lines'],
                        fn($line) => $line['severity'] === $this->diagnosticSeverity
                    )); ?>
                <?php $pageCount = max(1, (int) ceil(count($filteredLines) / 100)); ?>
                <?php $currentPage = min($this->diagnosticPage, $pageCount); ?>
                <div class="jem-tools-console" role="log" aria-live="polite">
                    <table class="table mb-0 jem-tools-diagnostics-table">
                        <thead><tr>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_TYPE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_DESCRIPTION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_RECOMMENDATION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_ACTION'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach (array_slice($filteredLines, ($currentPage - 1) * 100, 100) as $line) : ?>
                                <tr class="jem-tools-console-<?php echo $escape($line['severity']); ?>">
                                    <th scope="row">
                                        <?php if ($line['subject'] !== 'JEM') : ?>
                                            <?php echo $escape($line['subject']); ?><br>
                                        <?php endif; ?>
                                        <small><?php echo Text::_('COM_JEM_TOOLS_COUNT_' . strtoupper($line['severity'])); ?></small>
                                    </th>
                                    <td data-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_DESCRIPTION')); ?>"><?php echo $escape($line['message']); ?></td>
                                    <td data-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_RECOMMENDATION')); ?>">
                                        <?php echo $line['recommendation'] !== '' ? $escape($line['recommendation']) : Text::_('COM_JEM_TOOLS_NO_ACTION'); ?>
                                    </td>
                                    <td data-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_ACTION')); ?>">
                                        <?php if ($line['action_url'] !== '') : ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $escape(Route::_($line['action_url'], false)); ?>">
                                                <?php echo Text::_($line['action_label']); ?>
                                            </a>
                                        <?php else : ?>&mdash;<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$filteredLines) : ?>
                                <tr><td colspan="4"><?php echo Text::_('COM_JEM_TOOLS_NO_FINDINGS'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pageCount > 1) : ?>
                    <nav class="d-flex justify-content-between align-items-center gap-3 mt-3" aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_RESULT_PAGES')); ?>">
                        <?php if ($currentPage > 1) : ?>
                            <a class="btn btn-outline-secondary" href="<?php echo $escape(Route::_($reportUrl . ($this->diagnosticSeverity !== '' ? '&severity=' . $this->diagnosticSeverity : '') . '&page=' . ($currentPage - 1) . '#jem-diagnostic-results', false)); ?>"><?php echo Text::_('JPREV'); ?></a>
                        <?php else : ?><span></span><?php endif; ?>
                        <span><?php echo Text::sprintf('COM_JEM_TOOLS_RESULT_PAGE', $currentPage, $pageCount); ?></span>
                        <?php if ($currentPage < $pageCount) : ?>
                            <a class="btn btn-outline-secondary" href="<?php echo $escape(Route::_($reportUrl . ($this->diagnosticSeverity !== '' ? '&severity=' . $this->diagnosticSeverity : '') . '&page=' . ($currentPage + 1) . '#jem-diagnostic-results', false)); ?>"><?php echo Text::_('JNEXT'); ?></a>
                        <?php else : ?><span></span><?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="h5"><?php echo Text::sprintf('COM_JEM_TOOLS_SUPPORT_REPORT', (int) $this->diagnosticStored['id']); ?></h3>
                <p class="text-muted"><?php echo Text::_('COM_JEM_TOOLS_SUPPORT_REPORT_DESC'); ?></p>
                <form id="jem-diagnostic-support-form" method="post" action="<?php echo Route::_('index.php?option=com_jem'); ?>">
                    <input type="hidden" name="report_id" value="<?php echo (int) $this->diagnosticStored['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label" for="jem-diagnostic-observations"><?php echo Text::_('COM_JEM_TOOLS_OBSERVATIONS'); ?></label>
                        <textarea class="form-control" id="jem-diagnostic-observations" name="observations" rows="4" maxlength="2000"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="jem-diagnostic-selected-settings" name="include_selected_settings" value="1" checked>
                        <label class="form-check-label" for="jem-diagnostic-selected-settings"><?php echo Text::_('COM_JEM_TOOLS_INCLUDE_SELECTED_SETTINGS'); ?></label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="jem-diagnostic-component" name="include_component_settings" value="1" checked>
                        <label class="form-check-label" for="jem-diagnostic-component"><?php echo Text::_('COM_JEM_TOOLS_INCLUDE_COMPONENT_SETTINGS'); ?></label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="jem-diagnostic-names" name="include_names" value="1" checked>
                        <label class="form-check-label" for="jem-diagnostic-names"><?php echo Text::_('COM_JEM_TOOLS_INCLUDE_NAMES'); ?></label>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                        <button class="btn btn-outline-primary" type="submit" name="task" value="housekeeping.downloadDiagnostics">
                            <?php echo Text::_('COM_JEM_TOOLS_DOWNLOAD_JSON'); ?>
                        </button>
                        <?php if ($this->diagnosticDebugAll) : ?>
                            <button class="btn btn-primary" type="submit" name="task" value="housekeeping.downloadDiagnosticsZip">
                                <?php echo Text::_('COM_JEM_TOOLS_DOWNLOAD_ZIP'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
