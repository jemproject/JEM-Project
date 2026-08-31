/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

/**
 * this file manages the js script for adding/removing attachements in event
 */
jQuery(document).ready(function ($) {
    function hasVisibleImage(wrapper) {
        var image = wrapper ? wrapper.querySelector('img') : null;

        return Boolean(
            wrapper
            && !wrapper.hidden
            && wrapper.style.display !== 'none'
            && image
            && image.getAttribute('src')
        );
    }

    function syncPreviewStage(panel) {
        var stage = panel ? panel.querySelector('.jem-image-preview-stage') : null;

        if (!stage) {
            return;
        }

        var emptyMessage = stage.querySelector('.jem-image-preview-empty');
        var hasImage = hasVisibleImage(stage.querySelector('.jem-image-selected-preview'))
            || hasVisibleImage(stage.querySelector('.jem-image-current'));

        stage.classList.toggle('jem-image-preview-stage--has-image', hasImage);

        if (emptyMessage) {
            emptyMessage.hidden = hasImage;
        }
    }

    $('.jem-image-upload-panel').each(function () {
        syncPreviewStage(this);
    });

    $('.jem-image-clear').on('click', function () {
        var panel = this.closest('.jem-image-upload-panel');

        if (!panel) {
            return;
        }

        var fileInputId = this.dataset.jemImageFile || '';
        var selectId = this.dataset.jemImageSelect || '';
        var fileInput = fileInputId ? document.getElementById(fileInputId) : panel.querySelector('input[type="file"]');
        var imageSelect = selectId ? document.getElementById(selectId) : null;
        var currentPreview = panel.querySelector('.jem-image-current');
        var selectedPreview = panel.querySelector('.jem-image-selected-preview');
        var selectedImage = selectedPreview ? selectedPreview.querySelector('img') : null;
        var storedImage = panel.querySelector('#locimage');
        var alternativeText = panel.querySelector('#jform_locimage_alt, [id$="_alt"]');
        var removeImage = panel.querySelector('#removeimage, #removefullimage');

        if (fileInput) {
            fileInput.value = '';
        }
        if (imageSelect) {
            $(imageSelect).val('').trigger('change');
        }
        if (currentPreview) {
            currentPreview.hidden = true;
        }
        if (selectedPreview) {
            selectedPreview.hidden = true;
        }
        if (selectedImage) {
            selectedImage.removeAttribute('src');
        }
        if (storedImage) {
            storedImage.value = '';
        }
        if (alternativeText) {
            alternativeText.value = '';
        }
        if (removeImage) {
            removeImage.value = '1';
        }
        syncPreviewStage(panel);
    });

    $('.jem-image-upload-panel input[type="file"]').on('change', function () {
        var fileInput = this;
        var panel = fileInput.closest('.jem-image-upload-panel');
        var previewWrap = panel ? panel.querySelector('.jem-image-selected-preview') : null;
        var previewImage = previewWrap ? previewWrap.querySelector('img') : null;
        var currentPreview = panel ? panel.querySelector('.jem-image-current') : null;

        if (!previewWrap || !previewImage) {
            return;
        }

        if (!fileInput.files || !fileInput.files[0]) {
            previewImage.removeAttribute('src');
            previewWrap.hidden = true;
            syncPreviewStage(panel);
            return;
        }

        if (!fileInput.files[0].type || fileInput.files[0].type.indexOf('image/') !== 0) {
            previewImage.removeAttribute('src');
            previewWrap.hidden = true;
            syncPreviewStage(panel);
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            previewImage.src = event.target.result;
            previewWrap.hidden = false;

            if (currentPreview) {
                currentPreview.hidden = true;
            }

            syncPreviewStage(panel);
        };
        reader.readAsDataURL(fileInput.files[0]);
    });
});

