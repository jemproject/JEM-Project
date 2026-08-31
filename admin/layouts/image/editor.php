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
$extraRows = (array) ($displayData['extraRows'] ?? array());
$selectId = 'jform_' . $selectField;
$fileId = 'jform_' . $fileField;
$currentImageUrl = '';

if ($currentImagePath !== ''
    && strpos("/{$currentImagePath}/", '/../') === false
    && strpos($currentImagePath, "\0") === false) {
    $absolutePath = Path::clean(JPATH_SITE . '/' . $currentImagePath);
    $siteRoot = rtrim(Path::clean(JPATH_SITE), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (strpos($absolutePath . DIRECTORY_SEPARATOR, $siteRoot) === 0 && is_file($absolutePath)) {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $currentImagePath)));
        $currentImageUrl = rtrim(Uri::root(), '/') . '/' . $encodedPath;
    }
}
?>

<section class="jem-admin-image-profile jem-image-upload-panel">
    <header class="jem-admin-image-profile__header">
        <h3><?php echo $this->escape($title); ?></h3>
        <?php if ($description !== '') : ?>
            <p><?php echo $this->escape($description); ?></p>
        <?php endif; ?>
    </header>

    <?php echo JemImageCamera::resolutionControl($resolutionName, $resolutionId, $profile, $settings); ?>

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
                    <img src="<?php echo $this->escape($currentImageUrl); ?>" alt="<?php echo $this->escape($currentImageAlt); ?>">
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
