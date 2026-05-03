<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldCountrieslist extends ListField
{
    protected $type = 'Countrieslist';

    // IMPORTANTE: Añadir esta propiedad para campos múltiples
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

            // Opción por defecto
            $options[] = HTMLHelper::_('select.option', '', Text::_('JSELECT'));

            // Agregar países
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

    // Añadir este método para asegurar el procesamiento del valor
    public function getInput()
    {
        // Asegurar que el atributo multiple está presente
        $this->__set('multiple', true);

        return parent::getInput();
    }
}