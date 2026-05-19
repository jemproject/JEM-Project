/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

/**
 * this file manages the js script for adding/removing attachements in event
 */
//  window.addEvent('domready', function() {
jQuery(document).ready(function ($) {
    $('#userfile-remove').on('click', function (event) {
        var di = document.getElementById('datimage');
        if (di) {
            di.style.display = 'none';
        }
        var li = document.getElementById('locimage');
        if (li) {
            li.style.display = 'none';
        }
        var ufr = document.getElementById('userfile-remove');
        if (ufr) {
            var panel = ufr.closest('.jem-image-upload-panel');
            if (panel) {
                var preview = panel.querySelector('.jem-image-current');
                if (preview) {
                    preview.style.display = 'none';
                }
            }
            ufr.style.display = 'none';
        }
        var ri = document.getElementById('removeimage');
        if (ri) {
            ri.value = '1';
        }
    });

    $('#jform_userfile').on('change', function () {
        var fileInput = this;
        var previewWrap = document.querySelector('.jem-image-selected-preview');
        var previewImage = document.getElementById('jem-selected-venue-image-preview');

        if (!previewWrap || !previewImage) {
            return;
        }

        if (!fileInput.files || !fileInput.files[0]) {
            previewImage.removeAttribute('src');
            previewWrap.hidden = true;
            return;
        }

        if (!fileInput.files[0].type || fileInput.files[0].type.indexOf('image/') !== 0) {
            previewImage.removeAttribute('src');
            previewWrap.hidden = true;
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            previewImage.src = event.target.result;
            previewWrap.hidden = false;
        };
        reader.readAsDataURL(fileInput.files[0]);
    });

});

