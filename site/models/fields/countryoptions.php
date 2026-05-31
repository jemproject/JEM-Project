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
 * CountryOptions Field class
 */
class JFormFieldCountryOptions extends ListField
{
    /**
     * The form field type.
     */
    protected $type = 'CountryOptions';

    /**
     * Countries are always rendered as searchable selectors.
     *
     * @return  string
     */
    protected function getInput()
    {
        $class = trim((string) $this->class);
        $class = $class !== '' ? $class : 'form-select w-auto';
        $class = preg_match('/(^|\s)w-auto(\s|$)/', $class) ? $class : $class . ' w-auto';

        $attr  = ' class="' . $class . '"';
        $attr .= !empty($this->size) ? ' size="' . $this->size . '"' : '';
        $attr .= $this->multiple ? ' multiple' : '';
        $attr .= $this->required ? ' required aria-required="true"' : '';

        if ((string) $this->readonly == '1' || (string) $this->readonly == 'true' || (string) $this->disabled == '1' || (string) $this->disabled == 'true') {
            $attr .= ' disabled="disabled"';
        }

        $attr .= $this->onchange ? ' onchange="' . $this->onchange . '"' : '';

        $fancyAttr  = ' class="' . $class . '"';
        $fancyAttr .= ' style="width: min(100%, 36rem); max-width: 36rem;"';
        $fancyAttr .= $this->multiple ? ' multiple' : '';
        $fancyAttr .= $this->required ? ' required aria-required="true"' : '';
        $fancyAttr .= ' placeholder="' . Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS') . '"';

        if ((string) $this->readonly == '1' || (string) $this->readonly == 'true' || (string) $this->disabled == '1' || (string) $this->disabled == 'true') {
            $fancyAttr .= ' disabled="disabled"';
        }

        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->usePreset('choicesjs')
            ->useScript('webcomponent.field-fancy-select');

        $html = HTMLHelper::_(
            'select.genericlist',
            $this->getOptions(),
            $this->name,
            trim($attr),
            'value',
            'text',
            $this->value,
            $this->id
        );

        return '<joomla-field-fancy-select ' . $fancyAttr . '>' . $html . '</joomla-field-fancy-select>';
    }

    /**
     * Method to get the Country options.
     */
    public function getOptions()
    {
        return JemHelper::getCountryOptions();
    }
}
