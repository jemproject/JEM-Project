<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$labels = array(1 => 'EVENT', 2 => 'VENUE', 3 => 'CATEGORY');
$folders = array(1 => 'events', 2 => 'venues', 3 => 'categories');
?>
<h2><?php echo Text::_('COM_JEM_HOUSEKEEPING_' . $labels[$this->imagesType] . '_IMG'); ?></h2>
<p><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGES_REVIEW_DESC'); ?></p>
<?php if ($this->imagesError !== '') : ?>
    <div class="alert alert-warning"><?php echo $escape($this->imagesError); ?></div>
<?php elseif (!$this->imagesCandidates) : ?>
    <div class="alert alert-info"><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGES_NONE'); ?></div>
<?php else : ?>
    <form method="post" id="jem-image-cleanup"
          action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.deleteReviewedImages'); ?>">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <caption><?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGES_REVIEW'); ?></caption>
                <thead><tr>
                    <th scope="col"><?php echo Text::_('JSELECT'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_IMAGE'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_IMAGEHANDLER_FILE_NAME'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($this->imagesCandidates as $index => $file) : ?>
                    <?php $id = 'jem-image-' . (int) $index; ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="images[]"
                                   id="<?php echo $id; ?>" value="<?php echo $escape($file['id']); ?>"></td>
                        <td><img loading="lazy" alt="" style="max-width:80px;max-height:60px"
                                 src="<?php echo $escape(Uri::root(true) . '/images/jem/' . $folders[$this->imagesType] . '/' . rawurlencode($file['relative'])); ?>"></td>
                        <td><label for="<?php echo $id; ?>"><?php echo $escape($file['relative']); ?></label></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <input type="hidden" name="type" value="<?php echo (int) $this->imagesType; ?>">
        <input type="hidden" name="images_nonce" value="<?php echo $escape($this->imagesNonce); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
        <button type="submit" class="btn btn-danger" id="jem-image-cleanup-submit" disabled>
            <?php echo Text::_('COM_JEM_HOUSEKEEPING_IMAGES_DELETE_SELECTED'); ?>
        </button>
        <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=housekeeping'); ?>">
            <?php echo Text::_('JCANCEL'); ?>
        </a>
    </form>
    <script>
    (function () {
        const form = document.getElementById('jem-image-cleanup');
        const button = document.getElementById('jem-image-cleanup-submit');
        const boxes = Array.from(form.querySelectorAll('input[name="images[]"]'));
        const count = function () { return boxes.filter(function (box) { return box.checked; }).length; };
        const limit = <?php echo (int) $this->imagesBatchLimit; ?>;
        const message = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_IMAGES_CONFIRM'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const update = function () { button.disabled = count() === 0 || count() > limit; };
        boxes.forEach(function (box) { box.addEventListener('change', update); });
        form.addEventListener('submit', function (event) {
            if (count() === 0 || count() > limit || !window.confirm(message.replace('%d', String(count())))) {
                event.preventDefault();
            }
        });
        update();
    })();
    </script>
<?php endif; ?>
