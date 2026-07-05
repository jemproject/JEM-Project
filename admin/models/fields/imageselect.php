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
        if ((string) $imagetype === 'events' && $this->form) {
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
        $script[] = '        path: ' . json_encode((string) $imagetype === 'events' ? 'jform_image_path' : '') . ',';
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
        $script[] = '    function jemImageFolderLabel(imagePath) {';
        $script[] = '        imagePath = (imagePath || "").replace(/^\\/+|\\/+$/g, "");';
        $script[] = '        return "images/jem/events" + (imagePath ? "/" + imagePath : "");';
        $script[] = '    }';
        $script[] = '    function jemUpdateImageFolderHint() {';
        $script[] = '        document.querySelectorAll("[data-jem-image-folder-hint]").forEach(function (item) {';
        $script[] = '            item.textContent = jemImageFolderLabel(jemEventImagePathValue());';
        $script[] = '        });';
        $script[] = '    }';
        $script[] = '    function SelectImage(image, imagename, fieldId, imagePath) {';
        $script[] = '        var target = fieldId || window.jemActiveImageField || ' . json_encode($fieldId) . ';';
        $script[] = '        var field = window.jemImageFields[target];';
        $script[] = '        if (!field) { return; }';
        $script[] = '        var pathInput = field.path ? document.getElementById(field.path) : null;';
        $script[] = '        imagePath = typeof imagePath === "undefined" ? "" : imagePath;';
        $script[] = '        document.getElementById(field.image).value = image;';
        $script[] = '        document.getElementById(field.name).value = imagename;';
        $script[] = '        if (pathInput) { pathInput.value = image ? imagePath : ""; }';
        $script[] = '        document.getElementById(field.preview).src = jemImagePreviewPath(field, image, imagePath);';
        $script[] = '        jemUpdateImageFolderHint();';
        // $script[] = '        window.parent.SqueezeBox.close()';
        $script[] = '        $(".btn-close").trigger("click");';
        $script[] = '    }';
        $script[] = '    function jemPrepareImageModal(modalId, baseUrl, activeFieldId) {';
        $script[] = '        window.jemActiveImageField = activeFieldId || ' . json_encode($fieldId) . ';';
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
        $imagePathQuery = ((string) $imagetype === 'events' && $imagePathValue !== '') ? '&amp;image_path=' . rawurlencode($imagePathValue) : '';
        $link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task='.$task.'&amp;tmpl=component' . $imagePathQuery;
        $link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task='.$taskselect.'&amp;tmpl=component' . $imagePathQuery;
        $folderHint = $imagePathValue !== '' ? 'images/jem/events/' . $imagePathValue : 'images/jem/events';

        //
        $html[] = "<div class=\"fltlft\">";
        $html[] = "<input class=\"form-control\" style=\"background: #fff;\" type=\"text\" id=\"" . $imageNameId . "\" value=\"$this->value\" disabled=\"disabled\" onchange=\"javascript:if (document.getElementById('" . $imageNameId . "').value!='') {document.getElementById('" . $imagePreviewId . "').src='../images/jem/$imagetype/' + document.getElementById('" . $imageNameId . "').value} else {document.getElementById('" . $imagePreviewId . "').src='../media/com_jem/images/blank.webp'}\"; />";
        $html[] = "</div>";
        $html[] = "<div class=\"button2-left\"><div class=\"blank\">";
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
            $html[] ='<button type="button" class="btn btn-primary btn-margin" onclick="jemPrepareImageModal(\'' . $uploadModalId . '\', \'' . str_replace('&amp;', '&', $link) . '\', \'' . $fieldId . '\');" data-bs-toggle="modal"  data-bs-target="#' . $uploadModalId . '">'.Text::_('COM_JEM_UPLOAD').'</button>';

        $html[] ='</div></div>';
        // $html[] = "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".Text::_('COM_JEM_SELECTIMAGE')."\" href=\"$link2\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".Text::_('COM_JEM_SELECTIMAGE')."</a></div></div>\n";
        $html[] = "<div class=\"button2-left\"><div class=\"blank\">";
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
        $html[] = "<button type=\"button\" class=\"btn btn-primary btn-margin\" onclick=\"jemPrepareImageModal('" . $selectModalId . "', '" . str_replace('&amp;', '&', $link2) . "', '" . $fieldId . "');\" data-bs-toggle=\"modal\" data-bs-target=\"#" . $selectModalId . "\">".Text::_('COM_JEM_SELECTIMAGE')."
        </button>";
        $html[] = "</div></div>";
        $html[] = "\n&nbsp;<input class=\"btn btn-danger btn-margin\" type=\"button\" onclick=\"SelectImage('', '".Text::_('COM_JEM_SELECTIMAGE')."', '" . $fieldId . "');\" value=\"".Text::_('COM_JEM_RESET')."\" />";
        $html[] = (string) $imagetype === 'events' ? "<div class=\"small text-muted jem-event-image-folder-hint\">" . Text::_('COM_JEM_EVENT_IMAGE_FOLDER') . ": <code data-jem-image-folder-hint>" . htmlspecialchars($folderHint, ENT_COMPAT, 'UTF-8') . "</code></div>" : '';
        $html[] = "\n<input type=\"hidden\" id=\"" . $imageInputId . "\" name=\"$this->name\" value=\"$this->value\" />";
        $html[] = "<img src=\"../media/com_jem/images/blank.webp\" id=\"" . $imagePreviewId . "\" class=\"venue-image\" style=\"width:min(100%, " . $previewWidth . "px);height:" . $previewHeight . "px;max-width:100%;max-height:" . $previewHeight . "px;\" alt=\"".Text::_('COM_JEM_SELECTIMAGE_PREVIEW')."\" />";
        $html[] = "<script type=\"text/javascript\">";
        $html[] = "if (document.getElementById('" . $imageNameId . "').value!='') {";
        $html[] = "var imname = document.getElementById('" . $imageNameId . "').value;";
        $html[] = "var imPath = document.getElementById('jform_image_path') ? document.getElementById('jform_image_path').value.replace(/^\\/+|\\/+$/g, '') : '';";
        $html[] = "jsimg='../images/jem/$imagetype/' + (imPath ? imPath + '/' : '') + imname;";
        $html[] = "document.getElementById('" . $imagePreviewId . "').src= jsimg;";
        $html[] = "}";
        $html[] = "</script>";

        return implode("\n", $html);
    }
}
?>
