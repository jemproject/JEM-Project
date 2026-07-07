<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Article modal field for the front area.
 */
class JFormFieldModal_Article extends FormField
{
    /**
     * Field type.
     *
     * @var string
     */
    protected $type = 'Modal_Article';

    /**
     * Method to get the field input markup.
     *
     * @return string
     */
    protected function getInput()
    {
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $wa       = $document->getWebAssetManager();
        $value    = (int) $this->value;
        $modalId  = 'modal_' . $this->id;

        $link = Route::_(
            'index.php?option=com_jem&view=editevent&layout=choosearticle&tmpl=component'
            . '&function=jSelectArticle_' . $this->id
            . '&selected=' . $value
            . '&' . Session::getFormToken() . '=1',
            false
        );

        $script = array();
        $script[] = '    function jSelectArticle_' . $this->id . '(id, title) {';
        $script[] = '        var idField = document.getElementById("' . $this->id . '_id");';
        $script[] = '        var nameField = document.getElementById("' . $this->id . '_name");';
        $script[] = '        if (idField) {';
        $script[] = '            idField.value = id;';
        $script[] = '            idField.dispatchEvent(new Event("change", { bubbles: true }));';
        $script[] = '        }';
        $script[] = '        if (nameField) {';
        $script[] = '            nameField.value = title;';
        $script[] = '            nameField.dispatchEvent(new Event("change", { bubbles: true }));';
        $script[] = '        }';
        $script[] = '        jemUpdateArticleButtons_' . $this->id . '();';
        $script[] = '        var modalElement = document.getElementById("' . $modalId . '");';
        $script[] = '        var modal = modalElement && window.bootstrap ? bootstrap.Modal.getInstance(modalElement) : null;';
        $script[] = '        if (modal) { modal.hide(); }';
        $script[] = '    }';
        $script[] = '    function jemUpdateArticleButtons_' . $this->id . '() {';
        $script[] = '        var idField = document.getElementById("' . $this->id . '_id");';
        $script[] = '        var createButton = document.getElementById("' . $this->id . '_create");';
        $script[] = '        var clearButton = document.getElementById("' . $this->id . '_clear");';
        $script[] = '        var hasArticle = idField && parseInt(idField.value, 10) > 0;';
        $script[] = '        if (createButton) { createButton.hidden = hasArticle; }';
        $script[] = '        if (clearButton) { clearButton.hidden = !hasArticle; }';
        $script[] = '    }';
        $script[] = '    function jemPrepareArticleModal_' . $this->id . '() {';
        $script[] = '        var modalElement = document.getElementById("' . $modalId . '");';
        $script[] = '        var iframe = modalElement ? modalElement.querySelector("iframe") : null;';
        $script[] = '        if (!iframe) { return; }';
        $script[] = '        var url = new URL("' . addslashes($link) . '", window.location.href);';
        $script[] = '        var title = document.getElementById("jform_title");';
        $script[] = '        var articleCategory = document.getElementById("jform_article_target_category_id");';
        $script[] = '        var selectedCategories = [];';
        $script[] = '        document.querySelectorAll("#jform_cats option:checked, select[name=\'jform[cats][]\'] option:checked, input[name=\'jform[cats][]\']:checked").forEach(function (field) {';
        $script[] = '            if (field.value) { selectedCategories.push(field.value); }';
        $script[] = '        });';
        $script[] = '        if (title && title.value) { url.searchParams.set("article_title", title.value); }';
        $script[] = '        if (articleCategory && articleCategory.value) { url.searchParams.set("article_catid", articleCategory.value); }';
        $script[] = '        if (selectedCategories.length) { url.searchParams.set("jemcats", selectedCategories.join(",")); }';
        $script[] = '        iframe.setAttribute("src", url.toString());';
        $script[] = '    }';
        $script[] = '    document.addEventListener("DOMContentLoaded", jemUpdateArticleButtons_' . $this->id . ');';

        $wa->addInlineScript(implode("\n", $script));

        $title = Text::_('COM_JEM_SELECT_ARTICLE');

        if ($value) {
            $db = Factory::getContainer()->get('DatabaseDriver');

            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('title'))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = ' . (int) $value);

                $db->setQuery($query);
                $title = $db->loadResult() ?: $title;
            } catch (RuntimeException $e) {
                $app->enqueueMessage($e->getMessage(), 'warning');
            }
        }

        $html = array();
        $html[] = '<div class="input-group" style="width: auto; flex-grow: 1;">';
        $html[] = '  <input type="text" id="' . $this->id . '_name" class="form-control readonly" disabled="disabled" value="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" readonly size="35" />';
        $html[] = '  <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#' . $modalId . '" onclick="jemPrepareArticleModal_' . $this->id . '();">';
        $html[] = '    <i class="icon-file"></i> ' . Text::_('COM_JEM_SELECT');
        $html[] = '  </button>';
        $html[] = '  <button type="button" id="' . $this->id . '_create" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#' . $modalId . '" onclick="jemPrepareArticleModal_' . $this->id . '();" ' . ($value ? 'hidden' : '') . '>';
        $html[] = '    <i class="icon-plus"></i> ' . Text::_('JACTION_CREATE');
        $html[] = '  </button>';
        $html[] = '  <button type="button" id="' . $this->id . '_clear" class="btn btn-secondary" onclick="jSelectArticle_' . $this->id . '(0, \'' . htmlspecialchars(addslashes(Text::_('COM_JEM_SELECT_ARTICLE')), ENT_QUOTES, 'UTF-8') . '\');" ' . ($value ? '' : 'hidden') . '>';
        $html[] = '    ' . Text::_('JSEARCH_FILTER_CLEAR');
        $html[] = '  </button>';
        $html[] = '</div>';
        $html[] = HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            array(
                'url'    => $link,
                'title'  => Text::_('COM_JEM_SELECT_ARTICLE'),
                'width'  => '800px',
                'height' => '450px',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
            )
        );

        $class = $this->required ? ' class="required modal-value"' : '';
        $html[] = '<input type="hidden" id="' . $this->id . '_id"' . $class . ' name="' . $this->name . '" value="' . ($value ?: '') . '" />';

        return implode("\n", $html);
    }
}
