<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$formatBytes = static function ($bytes) {
    $bytes = max(0, (int) $bytes);
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    return number_format($bytes / 1024, 1) . ' KB';
};

$profileLabels = static function (array $profiles) {
    return implode(', ', array_map(static function ($profile) {
        return Text::_('COM_JEM_IMAGE_PROFILE_' . strtoupper((string) $profile));
    }, $profiles));
};
?>
<form action="<?php echo Route::_('index.php?option=com_jem&view=housekeeping'); ?>" name="adminForm" method="post" id="adminForm">
    <?php if (isset($this->sidebar)) : ?>
    <div id="j-sidebar-container" class="span2">
        <?php echo $this->sidebar; ?>
    </div>
    <div id="j-main-container" class="span10">
    <?php endif; ?>
        <?php if (is_array($this->imageProfileReport)) : ?>
            <div class="alert alert-info jem-image-profile-report">
                <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_REPORT'); ?></h3>
                <p><?php echo Text::sprintf(
                    'COM_JEM_HOUSEKEEPING_IMAGE_REPORT_SUMMARY',
                    (int) $this->imageProfileReport['total'],
                    (int) $this->imageProfileReport['valid'],
                    (int) $this->imageProfileReport['pending'],
                    (int) $this->imageProfileReport['blocked']
                ); ?></p>
                <?php if (!empty($this->imageProfileReport['details'])) : ?>
                    <details>
                        <summary><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_REPORT_DETAILS'); ?></summary>
                        <ul class="mt-2 mb-0">
                            <?php foreach ($this->imageProfileReport['details'] as $detail) : ?>
                                <li><?php echo htmlspecialchars(
                                    '[' . Text::_('COM_JEM_HOUSEKEEPING_IMAGE_STATUS_' . strtoupper($detail['status'])) . '] '
                                        . $detail['title'] . ' (#' . (int) $detail['id'] . ') - ' . $detail['file'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>

            <?php if (!empty($this->imageProfileReport['candidates'])) : ?>
                <div class="card mb-4 jem-image-candidate-card">
                    <div class="card-body">
                        <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_CANDIDATES'); ?></h3>
                        <p><?php echo Text::sprintf(
                            'COM_JEM_HOUSEKEEPING_IMAGE_CANDIDATES_DESC',
                            (int) $this->imageBatchLimit
                        ); ?></p>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle jem-image-candidates">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 1%;">
                                            <input type="checkbox" id="jem-image-checkall" title="<?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_SELECT_BATCH'); ?>">
                                        </th>
                                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_FILE', 'file', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_PATH', 'path', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_PROFILE', 'profile', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th class="text-nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_RESOLUTION', 'resolution', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_RATIO', 'ratio', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th class="text-nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_SIZE', 'size', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_EXTENSION', 'extension', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HOUSEKEEPING_IMAGE_TARGET', 'adjustment', $this->imageProfileReport['direction'], $this->imageProfileReport['ordering']); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->imageProfileReport['candidates'] as $index => $candidate) : ?>
                                        <?php
                                        $record = $candidate['title'] . ' (#' . (int) $candidate['id'] . ')';
                                        if ((int) $candidate['uses'] > 1) {
                                            $record .= ' +' . ((int) $candidate['uses'] - 1);
                                        }
                                        $adjustmentKey = $candidate['adjustment'] === 'contain'
                                            ? 'COM_JEM_HOUSEKEEPING_IMAGE_ADJUSTMENT_CONTAIN'
                                            : 'COM_JEM_IMAGE_ADJUSTMENT_' . strtoupper($candidate['adjustment']);
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox"
                                                       class="jem-image-candidate-checkbox"
                                                       id="jem-image-candidate-<?php echo (int) $index; ?>"
                                                       name="image_candidates[]"
                                                       value="<?php echo htmlspecialchars($candidate['identifier'], ENT_QUOTES, 'UTF-8'); ?>"
                                                       aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_JEM_HOUSEKEEPING_IMAGE_SELECT_FILE', $candidate['file']), ENT_QUOTES, 'UTF-8'); ?>">
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($candidate['file'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($candidate['path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code></td>
                                            <td title="<?php echo htmlspecialchars($record, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($profileLabels($candidate['profiles']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                            </td>
                                            <td class="text-nowrap"><?php echo (int) $candidate['width']; ?> × <?php echo (int) $candidate['height']; ?> px</td>
                                            <td class="text-nowrap"><?php echo htmlspecialchars($candidate['ratio'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap"><?php echo $formatBytes($candidate['size']); ?></td>
                                            <td class="text-uppercase"><?php echo htmlspecialchars($candidate['extension'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap">
                                                <?php echo Text::_($adjustmentKey); ?><br>
                                                <small><?php echo (int) $candidate['target_width']; ?> × <?php echo (int) $candidate['target_height']; ?> px · <?php echo htmlspecialchars($candidate['target_ratio'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <button type="button" class="btn btn-primary" id="jem-normalise-selected" disabled>
                                <?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_SELECTED', 0, (int) $this->imageBatchLimit); ?>
                            </button>
                            <?php if ($this->imagePagination) : ?>
                                <div class="pagination pagination-toolbar mb-0">
                                    <?php echo $this->imagePagination->getPagesLinks(); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ((int) $this->imageProfileReport['candidate_total'] === 0) : ?>
                <div class="alert alert-success jem-image-candidates-empty">
                    <?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_CANDIDATES_EMPTY'); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <table class="table table-striped housekeeping">
            <tbody>
                <!-- CLEAN EVENT IMG -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleaneventimg';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleaneventimg.svg', Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG_DESC'); ?>
                    </td>
                </tr>
            <!-- CLEAN VENUE IMG -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleanvenueimg';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanvenueimg.svg', Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG_DESC'); ?>
                    </td>
                </tr>
            <!-- CLEAN CATEGORY IMG -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleancategoryimg';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancategoryimg.svg', Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG_DESC'); ?>
                    </td>
                </tr>
            <!-- AUDIT AND SELECT IMAGE NORMALISATION CANDIDATES -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.auditImages';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-statistics.svg', Text::_('COM_JEM_HOUSEKEEPING_IMAGE_AUDIT'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_AUDIT'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_AUDIT_DESC'); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.resizethumbs';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-resizethumbs.svg', Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS_DESC'); ?>
                    </td>
                </tr>
            <!-- CLEAN TRIGGER ARCHIVE -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.triggerarchive';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-archive.svg', Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE_DESC'); ?>
                    </td>
                </tr>
            <!-- TRUNCATE CATEGORY/EVENT REFERENCES -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleanupCatsEventRelations';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancatseventrels.svg', Text::_('COM_JEM_HOUSEKEEPING_CATSEVENT_RELS'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS_DESC'); ?><br>
                        <?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_TOTAL_CATSEVENT_RELS', $this->totalcats) ?>
                    </td>
                </tr>
            <!-- CLEAN UNUSED ATTACHMENT FILES -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action"
                               onclick="if (!confirm(<?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES_CONFIRM')), ENT_QUOTES, 'UTF-8'); ?>)) return false; this.form.task.value='housekeeping.cleanupUnusedAttachmentFiles'; return true;">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanattachmentfiles.svg', Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES_DESC'); ?>
                    </td>
                </tr>
            <!-- TRUNCATE ALL DATA -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="return jemConfirmTruncateAllData(this.form);">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-truncatealldata.svg', Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_DESC'); ?>
                        <fieldset class="options-form jem-housekeeping-file-options">
                            <legend><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_FILES'); ?></legend>
                            <div class="jem-housekeeping-file-option">
                                <span class="jem-housekeeping-file-question"><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_IMAGES_OPTION'); ?></span>
                                <span class="jem-housekeeping-file-choices">
                                    <label for="jem-delete-images-no">
                                        <input type="radio" name="deleteimages" id="jem-delete-images-no" value="0" checked>
                                        <?php echo Text::_('JNO'); ?>
                                    </label>
                                    <label for="jem-delete-images-yes">
                                        <input type="radio" name="deleteimages" id="jem-delete-images-yes" value="1">
                                        <?php echo Text::_('JYES'); ?>
                                    </label>
                                </span>
                            </div>
                            <div class="jem-housekeeping-file-option">
                                <span class="jem-housekeeping-file-question"><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_ATTACHMENTS_OPTION'); ?></span>
                                <span class="jem-housekeeping-file-choices">
                                    <label for="jem-delete-attachments-no">
                                        <input type="radio" name="deleteattachments" id="jem-delete-attachments-no" value="0" checked>
                                        <?php echo Text::_('JNO'); ?>
                                    </label>
                                    <label for="jem-delete-attachments-yes">
                                        <input type="radio" name="deleteattachments" id="jem-delete-attachments-yes" value="1">
                                        <?php echo Text::_('JYES'); ?>
                                    </label>
                                </span>
                            </div>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php if (isset($this->sidebar)) : ?>
            </div>
        <?php endif; ?>
        <input type="hidden" name="task" value="">
        <input type="hidden" name="truncate_nonce" value="<?php echo htmlspecialchars($this->truncateNonce, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="imageaudit" value="<?php echo is_array($this->imageProfileReport) ? 1 : 0; ?>">
        <input type="hidden" name="filter_order" value="<?php echo is_array($this->imageProfileReport) ? htmlspecialchars($this->imageProfileReport['ordering'], ENT_QUOTES, 'UTF-8') : 'file'; ?>">
        <input type="hidden" name="filter_order_Dir" value="<?php echo is_array($this->imageProfileReport) ? htmlspecialchars($this->imageProfileReport['direction'], ENT_QUOTES, 'UTF-8') : 'asc'; ?>">
        <input type="hidden" name="limitstart" value="<?php echo is_array($this->imageProfileReport) ? (int) $this->imageProfileReport['limitstart'] : 0; ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
    function jemConfirmTruncateAllData(form) {
        if (!confirm(<?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_CONFIRM')); ?>)) {
            return false;
        }

        form.task.value = 'housekeeping.truncateAllData';
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('adminForm');
        const boxes = Array.from(document.querySelectorAll('.jem-image-candidate-checkbox'));
        const checkAll = document.getElementById('jem-image-checkall');
        const submit = document.getElementById('jem-normalise-selected');
        const batchLimit = <?php echo (int) $this->imageBatchLimit; ?>;
        const labelTemplate = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_SELECTED')); ?>;
        const confirmTemplate = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_CONFIRM')); ?>;
        const limitMessage = <?php echo json_encode(Text::sprintf('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_LIMIT_EXCEEDED', (int) $this->imageBatchLimit)); ?>;

        if (!form || !submit || boxes.length === 0) {
            return;
        }

        const checkedBoxes = function () {
            return boxes.filter(function (box) { return box.checked; });
        };

        const updateSelection = function () {
            const selected = checkedBoxes();
            submit.disabled = selected.length === 0;
            submit.textContent = labelTemplate
                .replace('%1$d', String(selected.length))
                .replace('%2$d', String(batchLimit));

            if (checkAll) {
                checkAll.checked = boxes.length > 0 && selected.length === Math.min(boxes.length, batchLimit);
                checkAll.indeterminate = selected.length > 0 && !checkAll.checked;
            }
        };

        boxes.forEach(function (box) {
            box.addEventListener('change', function () {
                if (checkedBoxes().length > batchLimit) {
                    box.checked = false;
                    window.alert(limitMessage);
                }
                updateSelection();
            });
        });

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                boxes.forEach(function (box, index) {
                    box.checked = checkAll.checked && index < batchLimit;
                });
                updateSelection();
            });
        }

        submit.addEventListener('click', function () {
            const selected = checkedBoxes();
            if (selected.length === 0 || selected.length > batchLimit) {
                window.alert(limitMessage);
                return;
            }

            const message = confirmTemplate.replace('%d', String(selected.length));
            if (!window.confirm(message)) {
                return;
            }

            form.querySelector('input[name="task"]').value = 'housekeeping.normaliseImages';
            form.submit();
        });

        updateSelection();
    });
</script>
