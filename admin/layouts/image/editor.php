<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;

require_once JPATH_SITE . '/components/com_jem/classes/imagecamera.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/image.class.php';

$form = $displayData['form'];
$settings = $displayData['settings'];
$profile = (string) $displayData['profile'];
$selectField = (string) $displayData['selectField'];
$fileField = (string) $displayData['fileField'];
$removeField = (string) $displayData['removeField'];
$resolutionName = (string) $displayData['resolutionName'];
$resolutionId = (string) $displayData['resolutionId'];
$title = (string) $displayData['title'];
$description = (string) ($displayData['description'] ?? '');
$currentImagePath = ltrim(str_replace('\\', '/', (string) ($displayData['currentImagePath'] ?? '')), '/');
$currentImageAlt = (string) ($displayData['currentImageAlt'] ?? $title);
$repairEventId = (int) ($displayData['repairEventId'] ?? 0);
$repairField = (string) ($displayData['repairField'] ?? '');
$extraRows = (array) ($displayData['extraRows'] ?? array());
$selectId = 'jform_' . $selectField;
$fileId = 'jform_' . $fileField;
$currentImageUrl = '';
$repairAnalysis = null;
$repairMaximum = JemImageProfilePolicy::maxDimension($settings);
$repairable = false;
$repairFingerprint = '';

if ($repairEventId > 0
    && in_array($repairField, array('datimage', 'fullimage'), true)
    && $currentImagePath !== '') {
    $repairFingerprint = hash('sha256', $repairField . "\0" . $currentImagePath);
}

if ($currentImagePath !== ''
    && strpos("/{$currentImagePath}/", '/../') === false
    && strpos($currentImagePath, "\0") === false) {
    $absolutePath = Path::clean(JPATH_SITE . '/' . $currentImagePath);
    $siteRoot = rtrim(Path::clean(JPATH_SITE), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (strpos($absolutePath . DIRECTORY_SEPARATOR, $siteRoot) === 0 && is_file($absolutePath)) {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $currentImagePath)));
        $currentImageUrl = rtrim(Uri::root(), '/') . '/' . $encodedPath;

        if ($repairEventId > 0 && in_array($repairField, array('datimage', 'fullimage'), true)) {
            $repairAnalysis = JemImage::analyseStoredImage(
                $absolutePath,
                $settings,
                $profile,
                true,
                $repairMaximum,
                JemImageProfilePolicy::UPLOAD_RATIO_ORIGINAL,
                $repairMaximum,
                $repairMaximum
            );
            $repairable = $repairAnalysis['accepted']
                && (int) $repairAnalysis['frames'] === 1
                && extension_loaded('gd');
        }
    }
}
$isOversized = is_array($repairAnalysis)
    && ((int) $repairAnalysis['width'] > $repairMaximum
        || (int) $repairAnalysis['height'] > $repairMaximum);
?>

<section class="jem-admin-image-profile jem-image-upload-panel">
    <header class="jem-admin-image-profile__header">
        <h3><?php echo $this->escape($title); ?></h3>
        <?php if ($description !== '') : ?>
            <p><?php echo $this->escape($description); ?></p>
        <?php endif; ?>
    </header>

    <?php echo JemImageCamera::resolutionControl($resolutionName, $resolutionId, $profile, $settings); ?>

    <?php if ($isOversized) : ?>
        <div
            class="alert alert-warning jem-image-repair-notice"
            data-jem-image-repair-panel
            data-jem-image-repair-error="<?php echo $this->escape(Text::_('COM_JEM_EVENT_IMAGE_REPAIR_FAILED')); ?>"
            data-jem-image-repair-select-id="<?php echo $this->escape($selectId); ?>"
            data-jem-image-repair-file-id="<?php echo $this->escape($fileId); ?>"
        >
            <p data-jem-image-repair-status>
                <?php echo Text::sprintf(
                    'COM_JEM_EVENT_IMAGE_OVERSIZED_WARNING',
                    (int) $repairAnalysis['width'],
                    (int) $repairAnalysis['height'],
                    $repairMaximum
                ); ?>
            </p>
            <?php if ($repairable) : ?>
                <button
                    type="button"
                    class="btn btn-warning btn-sm"
                    data-jem-image-repair
                    data-jem-image-repair-url="index.php?option=com_jem&amp;task=event.repairImage&amp;format=json"
                    data-jem-image-repair-event="<?php echo $repairEventId; ?>"
                    data-jem-image-repair-field="<?php echo $this->escape($repairField); ?>"
                    data-jem-image-repair-expected="<?php echo $this->escape($repairFingerprint); ?>"
                    data-jem-image-repair-working="<?php echo $this->escape(Text::_('COM_JEM_EVENT_IMAGE_REPAIR_WORKING')); ?>"
                    data-jem-image-repair-confirm="<?php echo $this->escape(Text::_('COM_JEM_EVENT_IMAGE_REPAIR_CONFIRM')); ?>"
                ><?php echo Text::sprintf('COM_JEM_EVENT_IMAGE_REPAIR_BUTTON', $repairMaximum); ?></button>
            <?php else : ?>
                <p class="mb-0"><?php echo Text::_('COM_JEM_EVENT_IMAGE_REPAIR_MANUAL'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="jem-image-upload-layout">
        <div class="jem-image-upload-list">
            <div class="jem-image-upload-row">
                <div class="jem-image-upload-label"><?php echo Text::_('COM_JEM_SERVER_IMAGE'); ?></div>
                <div class="jem-image-upload-control"><?php echo $form->getInput($selectField); ?></div>
            </div>

            <div class="jem-image-upload-row">
                <div class="jem-image-upload-label"><?php echo Text::_('COM_JEM_UPLOAD_NEW_IMAGE'); ?></div>
                <div class="jem-image-upload-control">
                    <div class="jem-image-file-control"><?php echo $form->getInput($fileField); ?></div>
                </div>
            </div>

            <?php foreach ($extraRows as $row) : ?>
                <div class="jem-image-upload-row<?php echo !empty($row['class']) ? ' ' . $this->escape($row['class']) : ''; ?>">
                    <div class="jem-image-upload-label"><?php echo $row['label']; ?></div>
                    <div class="jem-image-upload-control"><?php echo $row['input']; ?></div>
                </div>
            <?php endforeach; ?>

            <div class="jem-image-actions jem-image-actions--last">
                <button
                    type="button"
                    class="btn btn-secondary btn-sm jem-image-action-button jem-image-clear"
                    data-jem-image-select="<?php echo $this->escape($selectId); ?>"
                    data-jem-image-file="<?php echo $this->escape($fileId); ?>"
                ><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
            </div>

            <input type="hidden" name="<?php echo $this->escape($removeField); ?>" id="<?php echo $this->escape($removeField); ?>" value="0">
        </div>

        <div class="jem-image-preview-stage<?php echo $currentImageUrl !== '' ? ' jem-image-preview-stage--has-image' : ''; ?>">
            <?php if ($currentImageUrl !== '') : ?>
                <div class="jem-image-current">
                    <div class="visually-hidden"><?php echo Text::_('COM_JEM_CURRENT_IMAGE'); ?></div>
                    <img data-jem-image-current src="<?php echo $this->escape($currentImageUrl); ?>" alt="<?php echo $this->escape($currentImageAlt); ?>">
                </div>
            <?php endif; ?>
            <div class="jem-image-selected-preview" hidden>
                <div class="visually-hidden"><?php echo Text::_('COM_JEM_SELECTED_IMAGE_PREVIEW'); ?></div>
                <img src="" alt="<?php echo Text::_('COM_JEM_SELECTED_IMAGE_PREVIEW'); ?>">
            </div>
            <span class="jem-image-preview-empty"<?php echo $currentImageUrl !== '' ? ' hidden' : ''; ?>>
                <?php echo Text::_('COM_JEM_NO_IMAGE_SELECTED'); ?>
            </span>
        </div>
    </div>
</section>
