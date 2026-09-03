<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * CatOptions Field class.
 */
class JFormFieldCatOptions extends ListField
{
    /**
     * The category options field type.
     */
    protected $type = 'CatOptions';


    /**
     * Create Input
     * @see ListField::getInput()
     */
    public function getInput()
    {
        $attr = '';

        // Initialize field attributes.
        $attr .= !empty($this->class) ? ' class="' . $this->class . '"' : '';
        $attr .= !empty($this->size) ? ' size="' . $this->size . '"' : '';
        $attr .= $this->multiple ? ' multiple' : '';
        $attr .= $this->required ? ' required aria-required="true"' : '';

        // To avoid user's confusion, readonly="true" should imply disabled="true".
        if ((string) $this->readonly == '1' || (string) $this->readonly == 'true' || (string) $this->disabled == '1'|| (string) $this->disabled == 'true')
        {
            $attr .= ' disabled="disabled"';
        }

        // Initialize JavaScript field attributes.
        $attr .= $this->onchange ? ' onchange="' . $this->onchange . '"' : '';

        $attr2  = '';
        $attr2 .= $this->multiple ? ' multiple' : '';
        $attr2 .= $this->required ? ' required aria-required="true"' : '';
        $attr2 .= ' placeholder="' . Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS') . '" ';

        // To avoid user's confusion, readonly="true" should imply disabled="true".
        if ((string) $this->readonly == '1' || (string) $this->readonly == 'true' || (string) $this->disabled == '1'|| (string) $this->disabled == 'true')
        {
            $attr2 .= ' disabled="disabled"';
        }

        // Get the field options.
        $options = (array) $this->getOptions();

        // Use the value bound by the model so edit redirects and validation
        // reloads do not depend on an event id being present in the URL.
        $selectedcats = $this->normaliseCategoryIds($this->value);

        if (empty($selectedcats)) {
            $selectedcats = $this->normaliseCategoryIds($this->default);
        }

        // Create a read-only list (no name) with a hidden input to store the value.
        if ((string) $this->readonly == '1' || (string  ) $this->readonly == 'true')
        {
            $html[] = HTMLHelper::_('select.genericlist', $options, $this->name, trim($attr), 'value', 'text', $selectedcats,$this->id);
            $html[] = '<input type="hidden" name="' . $this->name . '" value="' . htmlspecialchars($selectedcats, ENT_COMPAT, 'UTF-8') . '"/>';
        }
        else
        // Create a regular list.
        {
            $html[] = HTMLHelper::_('select.genericlist', $options, $this->name, trim($attr), 'value', 'text', $selectedcats,$this->id);
        }

        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->usePreset('choicesjs')
            ->useScript('webcomponent.field-fancy-select');

        return '<joomla-field-fancy-select ' . $attr2 . '>' . implode($html) . '</joomla-field-fancy-select>';
    }

    /**
     * Normalize category values supplied by form data or field defaults.
     *
     * @param   mixed  $value  Category ids as an array or comma-separated value.
     *
     * @return  array
     */
    protected function normaliseCategoryIds($value)
    {
        if ($value === null || $value === '') {
            return array();
        }

        $values = is_array($value)
            ? $value
            : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $values = array_map('intval', $values ?: array());

        return array_values(array_unique(array_filter($values, static function ($categoryId) {
            return $categoryId > 0;
        })));
    }


    /**
     * Retrieve Options
     * @see ListField::getOptions()
     */
    protected function getOptions()
    {
        $options = JemCategories::getCategoriesTree();
        $options = array_values($options);

        // Choices.js treats option labels as plain text. Build a plain-text tree
        // instead of relying on the legacy HTML stored in treename.
        for ($i = 0, $n = (is_array($options) ? count($options) : 0); $i < $n; $i++)
        {
            $depth = max(0, (int) ($options[$i]->level ?? 1) - 1);
            $name = (string) ($options[$i]->catname ?? $options[$i]->text ?? '');
            $options[$i]->text = str_repeat("\xC2\xA0\xC2\xA0", $depth)
                . ($depth > 0 ? '└─ ' : '')
                . $name;
        }

        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $options);

        return $options;
    }
}
