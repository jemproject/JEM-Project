<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldCountrieslist extends ListField
{
    protected $type = 'Countrieslist';

    // Important: add this property for multiple-selection fields.
    protected $multiple = true;

    protected function getOptions()
    {
        $options = [];

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->select($db->quoteName(['iso2', 'name'], ['value', 'text']))
                ->from($db->quoteName('#__jem_countries'))
                ->order('name ASC');

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

    // Add this method to ensure the value is processed.
    public function getInput()
    {
        // Ensure that the multiple attribute is present.
        $this->__set('multiple', true);

        return parent::getInput();
    }
}
