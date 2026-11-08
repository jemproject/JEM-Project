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

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatBytes = static function ($bytes): string {
    $bytes = max(0, (int) $bytes);

    return $bytes >= 1048576 ? number_format($bytes / 1048576, 2) . ' MB' : number_format($bytes / 1024, 1) . ' KB';
};
$area = $this->filesArea;
$isImages = $area !== 'attachments';
$icons = array(
    'events' => 'icon-48-cleaneventimg.svg',
    'venues' => 'icon-48-cleanvenueimg.svg',
    'categories' => 'icon-48-cleancategoryimg.svg',
    'attachments' => 'icon-48-cleanattachmentfiles.svg',
);
$totalBytes = array_sum(array_map(static fn(array $file): int => (int) $file['size'], $this->filesCandidates));
?>
<div class="container-fluid jem-housekeeping-tool">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <?php echo HTMLHelper::_('image', 'com_jem/' . $icons[$area], '', array('width' => 48, 'height' => 48), true); ?>
                <div>
                    <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES'); ?></h2>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_DESC'); ?></p>
                </div>
            </div>
            <ul class="nav nav-tabs">
                <?php foreach ($this->filesCounts as $name => $count) : ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $name === $area ? ' active' : ''; ?>"
                           <?php echo $name === $area ? 'aria-current="page"' : ''; ?>
                           href="<?php echo Route::_('index.php?option=com_jem&view=housekeeping&tool=files&area=' . $name); ?>">
                            <?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_AREA_' . strtoupper($name)); ?>
                            <span class="badge <?php echo $count ? 'bg-warning text-dark' : 'bg-secondary'; ?>"><?php echo $count === null ? '&mdash;' : (int) $count; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if ($this->filesRun) : ?>
        <div class="card mb-4"><div class="card-body">
            <h3 class="h5"><?php echo Text::_('COM_JEM_TOOLS_CONSOLE'); ?></h3>
            <div class="jem-tools-console" role="log">
                <?php foreach ($this->filesRun as $line) : ?>
                    <div class="jem-tools-console-line jem-tools-console-<?php echo $escape($line['status']); ?>">
                        <?php echo Text::sprintf($line['status'] === 'error' ? 'COM_JEM_HOUSEKEEPING_FILES_LINE_FAILED' : 'COM_JEM_HOUSEKEEPING_FILES_LINE_DELETED', $escape($line['message'])); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></div>
    <?php endif; ?>

    <?php if (!empty($this->filesErrors[$area])) : ?>
        <div class="alert alert-warning"><?php echo $escape($this->filesErrors[$area]); ?></div>
    <?php elseif (!$this->filesCandidates) : ?>
        <div class="alert alert-success">
            <span class="icon-check" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_NONE_' . ($isImages ? 'IMAGES' : 'ATTACHMENTS')); ?>
        </div>
    <?php else : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.deleteUnusedFiles'); ?>"
              class="card mb-4" id="jem-files-form">
            <div class="card-body">
                <h3 class="h5"><?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_FILES_FOUND', count($this->filesCandidates), $formatBytes($totalBytes)); ?></h3>
                <p><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_EXPLAIN_' . ($isImages ? 'IMAGES' : 'ATTACHMENTS')); ?></p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <caption class="visually-hidden"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES'); ?></caption>
                        <thead><tr>
                            <th scope="col" class="text-center" style="width:1%">
                                <input type="checkbox" class="form-check-input" id="jem-files-all" title="<?php echo $escape(Text::_('COM_JEM_HOUSEKEEPING_FILES_SELECT_ALL')); ?>"
                                       aria-label="<?php echo $escape(Text::_('COM_JEM_HOUSEKEEPING_FILES_SELECT_ALL')); ?>">
                            </th>
                            <?php if ($isImages) : ?><th scope="col"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_PREVIEW'); ?></th><?php endif; ?>
                            <th scope="col"><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_FILE'); ?></th>
                            <th scope="col" class="text-nowrap"><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGE_SIZE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_MODIFIED'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_NOTES'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($this->filesCandidates as $index => $file) : ?>
                            <?php $id = 'jem-file-' . (int) $index; ?>
                            <tr>
                                <td class="text-center">
                                    <input class="form-check-input jem-file-box" type="checkbox" name="files[]" id="<?php echo $id; ?>"
                                           data-thumbnail="<?php echo $file['thumbnail'] !== '' ? '1' : '0'; ?>"
                                           value="<?php echo $escape($file['id']); ?>" <?php echo $file['elsewhere'] ? 'data-elsewhere="1"' : ''; ?>>
                                </td>
                                <?php if ($isImages) : ?>
                                    <td><img src="<?php echo $escape(Uri::root(true) . '/' . $file['url']); ?>" alt="" loading="lazy"
                                             style="max-width:80px;max-height:60px"></td>
                                <?php endif; ?>
                                <td><label for="<?php echo $id; ?>"><code><?php echo $escape($file['relative']); ?></code></label></td>
                                <td class="text-nowrap"><?php echo $formatBytes($file['size']); ?></td>
                                <td class="text-nowrap"><?php echo $file['modified'] ? HTMLHelper::_('date', '@' . (int) $file['modified'], Text::_('DATE_FORMAT_LC5')) : '&mdash;'; ?></td>
                                <td class="small">
                                    <?php if ($file['kind'] === 'thumbnail') : ?>
                                        <div><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_ORPHAN_THUMB'); ?></div>
                                    <?php elseif ($file['thumbnail'] !== '') : ?>
                                        <div><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_WITH_THUMB'); ?></div>
                                    <?php endif; ?>
                                    <?php if ($file['kind'] === 'attachment') : ?>
                                        <div><?php echo Text::sprintf(
                                            $file['object_exists'] ? 'COM_JEM_HOUSEKEEPING_FILES_OBJECT_EXISTS' : 'COM_JEM_HOUSEKEEPING_FILES_OBJECT_MISSING',
                                            $escape($file['object'])
                                        ); ?></div>
                                    <?php endif; ?>
                                    <?php foreach ($file['elsewhere'] as $use) : ?>
                                        <div class="text-danger"><span class="icon-warning" aria-hidden="true"></span>
                                            <?php echo Text::sprintf($use['label'], (int) $use['id']); ?></div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" name="confirm_references" id="jem-files-confirm-references" value="0">
                <input type="hidden" name="area" value="<?php echo $escape($area); ?>">
                <input type="hidden" name="files_nonce" value="<?php echo $escape($this->filesNonce); ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
                <button type="submit" class="btn btn-danger mt-2" id="jem-files-submit" disabled>
                    <?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_FILES_DELETE_SELECTED', 0, (int) $this->filesBatchLimit); ?>
                </button>
                <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_JEM_HOUSEKEEPING_FILES_AFTER'); ?></p>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php if ($this->filesCandidates) : ?>
<script>
(function () {
    const form = document.getElementById('jem-files-form');
    const all = document.getElementById('jem-files-all');
    const submit = document.getElementById('jem-files-submit');
    const boxes = Array.from(form.querySelectorAll('.jem-file-box'));
    const limit = <?php echo (int) $this->filesBatchLimit; ?>;
    const label = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_FILES_DELETE_SELECTED')); ?>;
    const confirmText = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_FILES_CONFIRM')); ?>;
    const confirmImages = <?php echo json_encode($isImages ? Text::_('COM_JEM_HOUSEKEEPING_FILES_CONFIRM_IMAGES') : ''); ?>;
    const confirmUsed = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_FILES_CONFIRM_USED')); ?>;
    const acknowledgement = document.getElementById('jem-files-confirm-references');
    const update = function () {
        const selected = boxes.filter(function (box) { return box.checked; }).length;
        submit.textContent = label.replace('%1$d', String(selected)).replace('%2$d', String(limit));
        submit.disabled = selected === 0 || selected > limit;
    };
    // "Select all" leaves files that seem to be used elsewhere unticked.
    all.addEventListener('change', function () {
        let count = 0;
        boxes.forEach(function (box) {
            box.checked = all.checked && !box.dataset.elsewhere && count < limit;
            if (box.checked) { count++; }
        });
        update();
    });
    boxes.forEach(function (box) { box.addEventListener('change', update); });
    form.addEventListener('submit', function (event) {
        acknowledgement.value = '0';
        const selected = boxes.filter(function (box) { return box.checked; });
        const used = selected.filter(function (box) { return box.dataset.elsewhere; }).length;
        const thumbnails = selected.filter(function (box) { return box.dataset.thumbnail === '1'; }).length;
        let message = confirmImages
            ? confirmImages.replace('%1$d', String(selected.length)).replace('%2$d', String(thumbnails))
            : confirmText.replace('%d', String(selected.length));
        if (used) {
            message += '\n\n' + confirmUsed.replace('%d', String(used));
        }
        if (!window.confirm(message)) {
            event.preventDefault();
            return;
        }
        acknowledgement.value = used ? '1' : '0';
    });
    update();
})();
</script>
<?php endif; ?>
