<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldCountrieslist extends ListField
{
    protected $type = 'Countrieslist';

    protected $multiple = true;

    public function getInput()
    {
        $this->__set('multiple', true);

        $class = trim((string) $this->class);
        $class = $class !== '' ? $class : 'form-select w-auto';
        $class = preg_match('/(^|\s)w-auto(\s|$)/', $class) ? $class : $class . ' w-auto';

        $attr  = ' class="' . $class . '"';
        $attr .= !empty($this->size) ? ' size="' . $this->size . '"' : '';
        $attr .= ' multiple';
        $attr .= $this->required ? ' required aria-required="true"' : '';
        $attr .= $this->disabled ? ' disabled="disabled"' : '';
        $attr .= $this->onchange ? ' onchange="' . $this->onchange . '"' : '';

        $fancyAttr  = ' class="' . $class . '" multiple';
        $fancyAttr .= $this->required ? ' required aria-required="true"' : '';
        $fancyAttr .= $this->disabled ? ' disabled="disabled"' : '';
        $fancyAttr .= ' placeholder="' . Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS') . '"';

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

    protected function getOptions()
    {
        $options = [];

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->select($db->quoteName(['iso2', 'name'], ['value', 'text']))
                ->from($db->quoteName('#__jem_countries'))
                ->order('name ASC');

            $columns = $db->getTableColumns('#__jem_countries');

            if (isset($columns['published'])) {
                $query->where($db->quoteName('published') . ' = 1');
            }

            $db->setQuery($query);
            $countries = $db->loadObjectList();

            // Default option.
            $options[] = HTMLHelper::_('select.option', '', Text::_('JSELECT'));

            // Add countries.
            if (!empty($countries)) {
                foreach ($countries as $country) {
                    $options[] = HTMLHelper::_('select.option', $country->value, $country->text);
                }
            }

        } catch (\Exception $e) {
            $options[] = HTMLHelper::_('select.option', '', 'Error: ' . $e->getMessage());
        }

        return $options;
    }
}
