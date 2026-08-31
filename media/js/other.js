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

    function setServerImageValue(imageSelect, value) {
        if (!imageSelect) {
            return;
        }

        var fancySelect = imageSelect.closest('joomla-field-fancy-select');

        imageSelect.value = value;

        if (fancySelect && fancySelect.choicesInstance) {
            fancySelect.choicesInstance.removeActiveItems();
            fancySelect.choicesInstance.setChoiceByValue(value);
        }

        imageSelect.dispatchEvent(new Event('change', {bubbles: true}));
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
            setServerImageValue(imageSelect, '');
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
        var imageSelect = panel
            ? panel.querySelector('joomla-field-fancy-select[data-jem-image-base-url] select')
            : null;
        var removeImage = panel ? panel.querySelector('#removeimage, #removefullimage') : null;

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

        if (imageSelect && imageSelect.value) {
            setServerImageValue(imageSelect, '');
        }
        if (removeImage) {
            removeImage.value = '0';
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

    $('.jem-image-upload-panel joomla-field-fancy-select[data-jem-image-base-url] select').on('change', function () {
        var imageSelect = this;
        var panel = imageSelect.closest('.jem-image-upload-panel');
        var fancySelect = imageSelect.closest('joomla-field-fancy-select[data-jem-image-base-url]');
        var previewWrap = panel ? panel.querySelector('.jem-image-selected-preview') : null;
        var previewImage = previewWrap ? previewWrap.querySelector('img') : null;
        var currentPreview = panel ? panel.querySelector('.jem-image-current') : null;
        var clearButton = panel ? panel.querySelector('.jem-image-clear') : null;
        var fileInputId = clearButton ? clearButton.dataset.jemImageFile : '';
        var fileInput = fileInputId
            ? document.getElementById(fileInputId)
            : (panel ? panel.querySelector('input[type="file"]') : null);
        var removeImage = panel ? panel.querySelector('#removeimage, #removefullimage') : null;
        var filename = imageSelect.value || '';

        if (!fancySelect || !previewWrap || !previewImage) {
            return;
        }

        if (!filename) {
            if (fileInput && fileInput.files && fileInput.files.length) {
                syncPreviewStage(panel);
                return;
            }

            previewImage.removeAttribute('src');
            previewWrap.hidden = true;

            if (currentPreview) {
                currentPreview.hidden = false;
            }

            syncPreviewStage(panel);
            return;
        }

        if (fileInput && (fileInput.value || (fileInput.files && fileInput.files.length))) {
            fileInput.value = '';
            fileInput.dispatchEvent(new Event('change', {bubbles: true}));
        }
        if (removeImage) {
            removeImage.value = '0';
        }

        previewImage.src = fancySelect.dataset.jemImageBaseUrl + encodeURIComponent(filename);
        previewWrap.hidden = false;

        if (currentPreview) {
            currentPreview.hidden = true;
        }

        syncPreviewStage(panel);
    });
});

