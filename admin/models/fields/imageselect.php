<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;

FormHelper::loadFieldClass('list');

/**
 * Imageselect Field
 *
 */
class JFormFieldImageselect extends ListField
{
    protected $type = 'Imageselect';

    public function getLabel() {
        // code that returns HTML that will be shown as the label
    }

    /**
     * Method to get the field input markup.
     *
     * @return    string    The field input markup.
     *
     */
    public function getInput()
    {
        // ImageType
        $imagetype = $this->element['imagetype'];
        $fieldName = (string) $this->fieldname;
        if ($fieldName === 'fullimage') {
            $imageProfile = 'event_full';
        } elseif ($fieldName === 'datimage') {
            $imageProfile = 'event_intro';
        } elseif ($fieldName === 'locimage') {
            $imageProfile = 'venue';
        } else {
            $imageProfile = 'category';
        }
        $jemsettings = JemHelper::config();
        $previewWidth = max(1, (int) ($jemsettings->imagewidth ?? 100));
        $previewHeight = max(1, (int) ($jemsettings->imagehight ?? $previewWidth));
        $fieldId = preg_replace('/[^A-Za-z0-9_-]/', '_', $this->id);
        $imageInputId = $fieldId . '_image';
        $imageNameId = $fieldId . '_imagename';
        $imagePreviewId = $fieldId . '_imagelib';
        $uploadModalId = $fieldId . '_imageupload_modal';
        $selectModalId = $fieldId . '_imageselect_modal';
        $imagePathValue = '';
        if (in_array((string) $imagetype, array('events', 'venues', 'categories'), true) && $this->form) {
            $imagePathValue = (string) $this->form->getValue('image_path', null, '');
        }

        // Build the script.
        $script = array();
        $script[] = '    window.jemActiveImageField = window.jemActiveImageField || null;';
        $script[] = '    window.jemImageFields = window.jemImageFields || {};';
        $script[] = '    window.jemImageFields[' . json_encode($fieldId) . '] = {';
        $script[] = '        image: ' . json_encode($imageInputId) . ',';
        $script[] = '        name: ' . json_encode($imageNameId) . ',';
        $script[] = '        preview: ' . json_encode($imagePreviewId) . ',';
        $script[] = '        uploadModal: ' . json_encode($uploadModalId) . ',';
        $script[] = '        selectModal: ' . json_encode($selectModalId) . ',';
        $script[] = '        path: ' . json_encode(in_array((string) $imagetype, array('events', 'venues', 'categories'), true) ? 'jform_image_path' : '') . ',';
        $script[] = '        root: ' . json_encode('images/jem/' . $imagetype) . ',';
        $script[] = '        base: ' . json_encode('../images/jem/' . $imagetype . '/') . ',';
        $script[] = '        blank: ' . json_encode('../media/com_jem/images/blank.webp');
        $script[] = '    };';
        $script[] = '    function jemImagePreviewPath(field, image, imagePath) {';
        $script[] = '        imagePath = (imagePath || "").replace(/^\\/+|\\/+$/g, "");';
        $script[] = '        return image ? field.base + (imagePath ? imagePath + "/" : "") + image : field.blank;';
        $script[] = '    }';
        $script[] = '    function jemEventImagePathValue() {';
        $script[] = '        var pathInput = document.getElementById("jform_image_path");';
        $script[] = '        return pathInput ? pathInput.value.replace(/^\\/+|\\/+$/g, "") : "";';
        $script[] = '    }';
        $script[] = '    function jemUpdateImageFolderHint() {';
        $script[] = '        var path = jemEventImagePathValue();';
        $script[] = '        document.querySelectorAll("[data-jem-image-folder-hint]").forEach(function (item) {';
        $script[] = '            var field = window.jemImageFields[window.jemActiveImageField] || window.jemImageFields[' . json_encode($fieldId) . '];';
        $script[] = '            item.textContent = field.root + (path ? "/" + path : "");';
        $script[] = '        });';
        $script[] = '    }';
        $script[] = '    function SelectImage(image, imagename, fieldId, imagePath) {';
        $script[] = '        var target = fieldId || window.jemActiveImageField || ' . json_encode($fieldId) . ';';
        $script[] = '        var field = window.jemImageFields[target];';
        $script[] = '        if (!field) { return; }';
        $script[] = '        var pathInput = field.path ? document.getElementById(field.path) : null;';
        $script[] = '        imagePath = typeof imagePath === "undefined" ? (pathInput ? pathInput.value : "") : imagePath;';
        $script[] = '        document.getElementById(field.image).value = image;';
        $script[] = '        document.getElementById(field.name).value = imagename;';
        $script[] = '        if (pathInput) { pathInput.value = image ? imagePath : ""; }';
        $script[] = '        document.getElementById(field.preview).src = jemImagePreviewPath(field, image, imagePath);';
        $script[] = '        jemUpdateImageFolderHint();';
        $script[] = '        [field.uploadModal, field.selectModal].some(function (modalId) {';
        $script[] = '            var modal = document.getElementById(modalId);';
        $script[] = '            if (!modal || !modal.classList.contains("show") || !window.bootstrap || !bootstrap.Modal) { return false; }';
        $script[] = '            var instance = bootstrap.Modal.getInstance(modal);';
        $script[] = '            if (instance) { instance.hide(); }';
        $script[] = '            return true;';
        $script[] = '        });';
        $script[] = '    }';
        $script[] = '    function jemPrepareImageModal(modalId, baseUrl, activeFieldId) {';
        $script[] = '        window.jemActiveImageField = activeFieldId;';
        $script[] = '        var modal = document.getElementById(modalId);';
        $script[] = '        var iframe = modal ? modal.querySelector("iframe") : null;';
        $script[] = '        var path = jemEventImagePathValue();';
        $script[] = '        if (iframe) { iframe.src = baseUrl + (path ? "&image_path=" + encodeURIComponent(path) : ""); }';
        $script[] = '        jemUpdateImageFolderHint();';
        $script[] = '    }';

        switch ($imagetype)
        {
            case 'categories':
                $task         = 'categoriesimg';
                $taskselect = 'selectcategoriesimg';
                break;
            case 'events':
                $task         = 'eventimg';
                $taskselect = 'selecteventimg';
                break;
            case 'venues':
                $task         = 'venueimg';
                $taskselect = 'selectvenueimg';
                break;
        }

        // Add the script to the document head.
        $document = Factory::getApplication()->getDocument();
        $document->getWebAssetManager()->addInlineScript(implode("\n", $script));
        $document->addStyleDeclaration('
#' . $uploadModalId . ' .modal-dialog {
    max-width: min(78vw, 980px);
}
#' . $selectModalId . ' .modal-dialog {
    max-width: min(90vw, 1200px);
}
#' . $uploadModalId . ' .modal-body,
#' . $selectModalId . ' .modal-body {
    height: min(78vh, 720px);
}
#' . $uploadModalId . ' iframe,
#' . $selectModalId . ' iframe {
    min-height: min(72vh, 680px);
}
@media (max-width: 767.98px) {
    #' . $uploadModalId . ' .modal-dialog,
    #' . $selectModalId . ' .modal-dialog {
        max-width: 96vw;
        margin-left: auto;
        margin-right: auto;
    }
}
img.venue-image {
    max-width: 100%;
    object-fit: contain;
    display: block;
    margin-top: 8px;
}
');

        // Setup variables for display.
        $html = array();
        $recordId = (int) ($this->form ? $this->form->getValue('id') : 0);
        $recordQuery = '&amp;record_id=' . $recordId;
        $profileQuery = '&amp;image_profile=' . rawurlencode($imageProfile);
        $link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task='.$task.'&amp;tmpl=component' . $recordQuery . $profileQuery;
        $link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task='.$taskselect.'&amp;tmpl=component' . $profileQuery;
        $folderHint = 'images/jem/' . $imagetype . ($imagePathValue !== '' ? '/' . $imagePathValue : '');
        $uploadPrepare = 'jemPrepareImageModal('
            . json_encode($uploadModalId) . ', '
            . json_encode(str_replace('&amp;', '&', $link)) . ', '
            . json_encode($fieldId) . ');';
        $selectPrepare = 'jemPrepareImageModal('
            . json_encode($selectModalId) . ', '
            . json_encode(str_replace('&amp;', '&', $link2)) . ', '
            . json_encode($fieldId) . ');';

        $html[] = HTMLHelper::_(
                'bootstrap.renderModal',
                $uploadModalId,
                array(
                    'url'    => $link,
                    'title'  => Text::_('COM_JEM_UPLOAD'),
                    'width'  => '90vw',
                    'height' => '78vh',
                    'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
                )
            );
        $html[] = HTMLHelper::_(
            'bootstrap.renderModal',
            $selectModalId,
            array(
                'url'    => $link2,
                'title'  => Text::_('COM_JEM_SELECTIMAGE'),
                'width'  => '90vw',
                'height' => '78vh',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
            )
        );
        $html[] = '<div class="input-group">';
        $html[] = '<input class="form-control" style="background: #fff;" type="text" id="' . $imageNameId . '" value="' . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '" disabled="disabled" />';
        $html[] = '<button type="button" class="btn btn-primary" onclick="' . htmlspecialchars($uploadPrepare, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="modal" data-bs-target="#' . $uploadModalId . '"><span class="icon-upload" aria-hidden="true"></span> '.Text::_('COM_JEM_UPLOAD').'</button>';
        $html[] = '<button type="button" class="btn btn-primary" onclick="' . htmlspecialchars($selectPrepare, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="modal" data-bs-target="#' . $selectModalId . '"><span class="icon-images" aria-hidden="true"></span> '.Text::_('COM_JEM_SELECTIMAGE').'</button>';
        $html[] = '<button type="button" class="btn btn-danger" onclick="SelectImage(\'\', ' . htmlspecialchars(json_encode(Text::_('COM_JEM_SELECTIMAGE')), ENT_QUOTES, 'UTF-8') . ', \'' . $fieldId . '\');"><span class="icon-times" aria-hidden="true"></span> '.Text::_('COM_JEM_RESET').'</button>';
        $html[] = '</div>';
        if (in_array((string) $imagetype, array('events', 'venues', 'categories'), true)) {
            $html[] = '<div class="small text-muted jem-event-image-folder-hint">'
                . Text::_('COM_JEM_EVENT_IMAGE_FOLDER') . ': <code data-jem-image-folder-hint>'
                . htmlspecialchars($folderHint, ENT_QUOTES, 'UTF-8') . '</code></div>';
        }
        $html[] = "\n<input type=\"hidden\" id=\"" . $imageInputId . "\" name=\"$this->name\" value=\"$this->value\" />";
        $html[] = "<img src=\"../media/com_jem/images/blank.webp\" id=\"" . $imagePreviewId . "\" class=\"venue-image\" style=\"width:min(100%, " . $previewWidth . "px);height:" . $previewHeight . "px;max-width:100%;max-height:" . $previewHeight . "px;\" alt=\"".Text::_('COM_JEM_SELECTIMAGE_PREVIEW')."\" />";
        $html[] = "<script type=\"text/javascript\">";
        $html[] = "if (document.getElementById('" . $imageNameId . "').value!='') {";
        $html[] = "var imname = document.getElementById('" . $imageNameId . "').value;";
        $html[] = "var imPath = " . json_encode(in_array((string) $imagetype, array('events', 'venues', 'categories'), true)) . " && document.getElementById('jform_image_path') ? document.getElementById('jform_image_path').value.replace(/^\\/+|\\/+$/g, '') : '';";
        $html[] = "jsimg='../images/jem/$imagetype/' + (imPath ? imPath + '/' : '') + imname;";
        $html[] = "document.getElementById('" . $imagePreviewId . "').src= jsimg;";
        $html[] = "}";
        $html[] = "</script>";

        return implode("\n", $html);
    }
}
?>
