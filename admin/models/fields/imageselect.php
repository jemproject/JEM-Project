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
        $fieldId = preg_replace('/[^A-Za-z0-9_-]/', '_', $this->id);
        $imageInputId = $fieldId . '_image';
        $imageNameId = $fieldId . '_imagename';
        $imagePreviewId = $fieldId . '_imagelib';
        $uploadModalId = $fieldId . '_imageupload_modal';
        $selectModalId = $fieldId . '_imageselect_modal';

        // Build the script.
        $script = array();
        $script[] = '    window.jemActiveImageField = window.jemActiveImageField || null;';
        $script[] = '    window.jemImageFields = window.jemImageFields || {};';
        $script[] = '    window.jemImageFields[' . json_encode($fieldId) . '] = {';
        $script[] = '        image: ' . json_encode($imageInputId) . ',';
        $script[] = '        name: ' . json_encode($imageNameId) . ',';
        $script[] = '        preview: ' . json_encode($imagePreviewId) . ',';
        $script[] = '        base: ' . json_encode('../images/jem/' . $imagetype . '/') . ',';
        $script[] = '        blank: ' . json_encode('../media/com_jem/images/blank.webp');
        $script[] = '    };';
        $script[] = '    function SelectImage(image, imagename, fieldId) {';
        $script[] = '        var target = fieldId || window.jemActiveImageField || ' . json_encode($fieldId) . ';';
        $script[] = '        var field = window.jemImageFields[target];';
        $script[] = '        if (!field) { return; }';
        $script[] = '        document.getElementById(field.image).value = image;';
        $script[] = '        document.getElementById(field.name).value = imagename;';
        $script[] = '        document.getElementById(field.preview).src = image ? field.base + image : field.blank;';
        // $script[] = '        window.parent.SqueezeBox.close()';
        $script[] = '        $(".btn-close").trigger("click");';
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
#' . $uploadModalId . ' .modal-dialog,
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
');

        // Setup variables for display.
        $html = array();
        $link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task='.$task.'&amp;tmpl=component';
        $link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task='.$taskselect.'&amp;tmpl=component';

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
            $html[] ='<button type="button" class="btn btn-primary btn-margin" onclick="window.jemActiveImageField=\'' . $fieldId . '\';" data-bs-toggle="modal"  data-bs-target="#' . $uploadModalId . '">'.Text::_('COM_JEM_UPLOAD').'</button>';

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
        $html[] = "<button type=\"button\" class=\"btn btn-primary btn-margin\" onclick=\"window.jemActiveImageField='" . $fieldId . "';\" data-bs-toggle=\"modal\" data-bs-target=\"#" . $selectModalId . "\">".Text::_('COM_JEM_SELECTIMAGE')."
        </button>";
        $html[] = "</div></div>";
        $html[] = "\n&nbsp;<input class=\"btn btn-danger btn-margin\" type=\"button\" onclick=\"SelectImage('', '".Text::_('COM_JEM_SELECTIMAGE')."', '" . $fieldId . "');\" value=\"".Text::_('COM_JEM_RESET')."\" />";
        $html[] = "\n<input type=\"hidden\" id=\"" . $imageInputId . "\" name=\"$this->name\" value=\"$this->value\" />";
        $html[] = "<img src=\"../media/com_jem/images/blank.webp\" id=\"" . $imagePreviewId . "\" class=\"venue-image\" alt=\"".Text::_('COM_JEM_SELECTIMAGE_PREVIEW')."\" />";
        $html[] = "<script type=\"text/javascript\">";
        $html[] = "if (document.getElementById('" . $imageNameId . "').value!='') {";
        $html[] = "var imname = document.getElementById('" . $imageNameId . "').value;";
        $html[] = "jsimg='../images/jem/$imagetype/' + imname;";
        $html[] = "document.getElementById('" . $imagePreviewId . "').src= jsimg;";
        $html[] = "}";
        $html[] = "</script>";

        return implode("\n", $html);
    }
}
?>
