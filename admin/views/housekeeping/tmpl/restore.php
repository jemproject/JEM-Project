<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('script', 'com_jem/housekeeping-restore-filter.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);

$escape = static function ($value): string {
    if (is_array($value)) {
        $value = json_encode($value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$preview = is_array($this->restorePreview)
    && ($this->restorePreview['scope'] ?? '') === $this->restoreScope
    && (int) ($this->restorePreview['id'] ?? -1) === $this->restoreTargetId
    ? $this->restorePreview : null;
$run = is_array($this->restoreRun)
    && ($this->restoreRun['scope'] ?? '') === $this->restoreScope
    && (int) ($this->restoreRun['id'] ?? -1) === $this->restoreTargetId
    ? (array) ($this->restoreRun['lines'] ?? array()) : null;
$groups = array();
foreach ((array) ($this->restoreCatalog['fields'] ?? array()) as $field) {
    $groups[$field['group']][] = $field;
}
?>
<div class="container-fluid jem-housekeeping-tool">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <img src="<?php echo $escape(Uri::root(true) . '/media/com_jem/images/icon-48-restoredefaults.svg'); ?>" width="48" height="48" alt="">
                <div>
                    <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_TOOLS_RESTORE'); ?></h2>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_JEM_TOOLS_RESTORE_DESC'); ?></p>
                </div>
                <?php if (is_array($this->restoreCatalog) && $groups) : ?>
                    <div class="d-flex flex-wrap gap-2 ms-auto" role="group" id="jem-restore-filters"
                         aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_RESTORE_FILTERS')); ?>">
                        <?php foreach (array('all' => 'icon-list', 'same' => 'icon-check', 'different' => 'icon-warning') as $mode => $icon) : ?>
                            <?php $label = Text::_('COM_JEM_TOOLS_RESTORE_FILTER_' . strtoupper($mode)); ?>
                            <button type="button" class="btn jem-restore-filter-button<?php echo $mode === 'all' ? ' active' : ''; ?>"
                                    data-jem-restore-filter="<?php echo $mode; ?>" aria-controls="jem-restore-catalog"
                                    aria-pressed="<?php echo $mode === 'all' ? 'true' : 'false'; ?>"
                                    aria-label="<?php echo $escape($label); ?>" title="<?php echo $escape($label); ?>">
                                <span class="<?php echo $icon; ?>" aria-hidden="true"></span>
                                <span><?php echo $escape(Text::_('COM_JEM_TOOLS_RESTORE_FILTER_BUTTON_' . strtoupper($mode))); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <form method="get" action="<?php echo Route::_('index.php'); ?>" class="row g-3 align-items-end" id="jem-restore-target-form">
                <input type="hidden" name="option" value="com_jem">
                <input type="hidden" name="view" value="housekeeping">
                <input type="hidden" name="tool" value="restore">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="jem-restore-scope"><?php echo Text::_('COM_JEM_TOOLS_RESTORE_SCOPE'); ?></label>
                    <select class="form-select" name="scope" id="jem-restore-scope" onchange="document.getElementById('jem-restore-target').value=0; this.form.submit();">
                        <?php foreach (array('component', 'view', 'module', 'plugin') as $scope) : ?>
                            <option value="<?php echo $scope; ?>"<?php echo $scope === $this->restoreScope ? ' selected' : ''; ?>>
                                <?php echo Text::_('COM_JEM_TOOLS_SCOPE_' . strtoupper($scope)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="jem-restore-target"><?php echo Text::_('COM_JEM_TOOLS_RESTORE_TARGET'); ?></label>
                    <select class="form-select" name="target" id="jem-restore-target">
                        <?php if ($this->restoreScope !== 'component') : ?>
                            <option value="0"><?php echo Text::_('COM_JEM_TOOLS_CHOOSE_TARGET'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($this->restoreTargets as $target) : ?>
                            <option value="<?php echo (int) $target['id']; ?>"<?php echo (int) $target['id'] === $this->restoreTargetId ? ' selected' : ''; ?>>
                                <?php echo $escape($target['title'] . ($target['id'] ? ' (#' . $target['id'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><?php echo Text::_('COM_JEM_TOOLS_SHOW_SETTINGS'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($this->restoreError !== '') : ?>
        <div class="alert alert-warning"><?php echo $escape($this->restoreError); ?></div>
    <?php endif; ?>

    <?php if (is_array($this->restoreCatalog)) : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.previewDefaults'); ?>" class="card mb-4" id="jem-restore-catalog">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h3 class="h5 mb-1"><?php echo $escape($this->restoreCatalog['title']); ?></h3>
                        <p class="mb-0"><?php echo Text::_('COM_JEM_TOOLS_RESTORE_SELECT_HINT'); ?></p>
                    </div>
                    <?php if ($groups) : ?>
                        <div class="d-flex flex-wrap gap-2" aria-label="<?php echo $escape(Text::_('COM_JEM_TOOLS_RESTORE_TREE_CONTROLS')); ?>">
                            <button class="btn btn-outline-primary" type="button" data-jem-restore-expand="all" disabled>
                                <span class="icon-expand" aria-hidden="true"></span>
                                <?php echo Text::_('COM_JEM_TOOLS_RESTORE_EXPAND_ALL'); ?>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" data-jem-restore-expand="none" disabled>
                                <span class="icon-collapse" aria-hidden="true"></span>
                                <?php echo Text::_('COM_JEM_TOOLS_RESTORE_COLLAPSE_ALL'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($groups) : ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle jem-restore-catalog-table">
                            <thead><tr>
                                <th scope="col"><?php echo Text::_('JSELECT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_SETTING'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_CURRENT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JEM_TOOLS_DEFAULT'); ?></th>
                            </tr></thead>
                            <?php foreach ($groups as $group => $fields) : ?>
                                <tbody data-jem-restore-group>
                                    <tr class="jem-restore-group-row">
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary jem-restore-group-toggle" type="button"
                                                    data-jem-restore-toggle aria-expanded="true"
                                                    aria-label="<?php echo $escape(Text::sprintf('COM_JEM_TOOLS_RESTORE_TOGGLE_GROUP', $group)); ?>" disabled>
                                                <span class="icon-chevron-down" aria-hidden="true"></span>
                                            </button>
                                        </td>
                                        <th scope="rowgroup"><?php echo $escape($group); ?> <span class="text-muted" data-jem-restore-count>(<?php echo count($fields); ?>)</span></th>
                                        <td></td><td></td>
                                    </tr>
                                    <?php foreach ($fields as $field) : ?>
                                        <tr data-jem-restore-status="<?php echo $field['changed'] ? 'different' : 'same'; ?>">
                                            <td><input type="checkbox" name="restore_fields[]" value="<?php echo $escape($field['key']); ?>"<?php echo $field['changed'] ? '' : ' disabled'; ?> aria-label="<?php echo $escape($field['label']); ?>"></td>
                                            <td><?php echo $escape($field['label']); ?> <small class="text-muted ms-1">(<?php echo $escape($field['key']); ?>)</small></td>
                                            <td><code class="jem-tools-value"><?php echo $escape($field['current'] ?? Text::_('COM_JEM_TOOLS_NOT_SET')); ?></code></td>
                                            <td><code class="jem-tools-value"><?php echo $escape($field['default']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
                <p class="alert alert-info d-none" id="jem-restore-filter-empty" role="status">
                    <?php echo Text::_('COM_JEM_TOOLS_RESTORE_FILTER_EMPTY'); ?>
                </p>
                <?php if (!$groups) : ?>
                    <div class="alert alert-info"><?php echo Text::_('COM_JEM_TOOLS_NO_SAFE_DEFAULTS'); ?></div>
                <?php else : ?>
                    <button type="submit" class="btn btn-primary mt-2"><?php echo Text::_('COM_JEM_TOOLS_PREVIEW_CHANGES'); ?></button>
                <?php endif; ?>
                <input type="hidden" name="restore_scope" value="<?php echo $escape($this->restoreScope); ?>">
                <input type="hidden" name="restore_target" value="<?php echo (int) $this->restoreTargetId; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($preview && $preview['changes']) : ?>
        <div class="card border-warning mb-4">
            <div class="card-body">
                <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_RESTORE_PREVIEW'); ?></h3>
                <p><?php echo Text::sprintf('COM_JEM_TOOLS_RESTORE_PREVIEW_READY', count($preview['changes'])); ?></p>
                <ul>
                    <?php foreach ($preview['changes'] as $field) : ?>
                        <li><?php echo $escape($field['label']); ?>: <code><?php echo $escape($field['current'] ?? Text::_('COM_JEM_TOOLS_NOT_SET')); ?></code> → <code><?php echo $escape($field['default']); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.applyDefaults'); ?>" onsubmit="return confirm(<?php echo $escape(json_encode(Text::_('COM_JEM_TOOLS_RESTORE_CONFIRM'))); ?>);">
                    <input type="hidden" name="restore_nonce" value="<?php echo $escape($this->restoreNonce); ?>">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button class="btn btn-warning" type="submit"><?php echo Text::_('COM_JEM_TOOLS_APPLY_CHANGES'); ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (is_array($run)) : ?>
        <div class="card mb-4"><div class="card-body">
            <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_CONSOLE'); ?></h3>
            <div class="jem-tools-console" role="log">
                <?php foreach ($run as $line) : ?>
                    <div class="jem-tools-console-line jem-tools-console-<?php echo $escape($line['status']); ?>"><?php echo $escape($line['message']); ?></div>
                <?php endforeach; ?>
            </div>
        </div></div>
    <?php endif; ?>
</div>
