<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;

/**
 * Category selector using Joomla's native modal content-select field.
 */
class JFormFieldCategories extends ModalSelectField
{
    protected $type = 'Categories';

    protected function getInput()
    {
        $function = 'jSelectCategory_' . preg_replace('/[^A-Za-z0-9_]/', '_', $this->id);

        $this->select      = true;
        $this->clear       = false;
        $this->urlSelect   = 'index.php?option=com_jem&view=categoryelement&tmpl=component&function=' . $function;
        $this->titleSelect = 'COM_JEM_SELECT_CATEGORY';
        $this->iconSelect  = 'icon-folder';

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(
            'window.' . $function . ' = function (id, category) {' . "\n"
            . '    var value = document.getElementById(' . json_encode($this->id . '_id') . ');' . "\n"
            . '    var title = document.getElementById(' . json_encode($this->id) . ') || document.getElementById(' . json_encode($this->id . '_name') . ');' . "\n"
            . '    if (value) { value.value = id; value.dispatchEvent(new CustomEvent("change", {bubbles: true})); }' . "\n"
            . '    if (title) { title.value = category; }' . "\n"
            . '    var dialog = document.querySelector("joomla-dialog.joomla-dialog-content-select-field");' . "\n"
            . '    if (dialog && typeof dialog.close === "function") { dialog.close(); }' . "\n"
            . '};'
        );

        return parent::getInput();
    }

    protected function getValueTitle()
    {
        if (!$this->value) {
            return Text::_('COM_JEM_SELECT_CATEGORY');
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('catname'))
                ->from($db->quoteName('#__jem_categories'))
                ->where($db->quoteName('id') . ' = ' . (int) $this->value);
            $db->setQuery($query);

            return $db->loadResult() ?: Text::_('COM_JEM_SELECT_CATEGORY');
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return Text::_('COM_JEM_SELECT_CATEGORY');
        }
    }
}
