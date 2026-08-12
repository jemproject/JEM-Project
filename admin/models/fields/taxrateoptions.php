<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Published tax rates with country metadata for venue-driven client filtering.
 */
class JFormFieldTaxRateOptions extends ListField
{
    protected $type = 'TaxRateOptions';

    protected function getInput()
    {
        $options = $this->getTaxOptions();
        $attributes = array(
            'id'    => $this->id,
            'class' => trim('form-select ' . (string) $this->class),
        );
        if ($this->required) {
            $attributes['required'] = 'required';
            $attributes['aria-required'] = 'true';
        }

        $html = '<select name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '"';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        $html .= '>';
        $selected = (string) $this->value;
        foreach ($options as $option) {
            $value = (string) $option->value;
            $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
                . ($value === $selected ? ' selected="selected"' : '')
                . ' data-country-code="' . htmlspecialchars((string) $option->country_code, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-tax-rate="' . htmlspecialchars((string) $option->tax_rate, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-tax-type="' . htmlspecialchars((string) $option->tax_type, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-valid-from="' . htmlspecialchars((string) $option->valid_from, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-valid-until="' . htmlspecialchars((string) $option->valid_until, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars((string) $option->text, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    protected function getOptions()
    {
        $options = array();
        foreach ($this->getTaxOptions() as $option) {
            $options[] = HTMLHelper::_('select.option', $option->value, $option->text);
        }

        return array_merge(parent::getOptions(), $options);
    }

    private function getTaxOptions(): array
    {
        $empty = (object) array(
            'value'        => '',
            'text'         => Text::_('COM_JEM_EVENT_PRICE_SELECT_TAX_RATE'),
            'country_code' => '',
            'tax_rate'     => '',
            'tax_type'     => '',
            'valid_from'   => '',
            'valid_until'  => '',
        );
        $options = array($empty);

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(array('id', 'code', 'name', 'tax_type', 'rate', 'country_code', 'valid_from', 'valid_until'))
                ->from($db->quoteName('#__jem_tax_rates'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('country_code') . ' ASC, ' . $db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');
            $db->setQuery($query);
            foreach ((array) $db->loadObjectList() as $rate) {
                $countryCode = strtoupper(trim((string) $rate->country_code));
                $options[] = (object) array(
                    'value'        => (int) $rate->id,
                    'text'         => ($countryCode !== '' ? $countryCode . ' - ' : '')
                        . (string) $rate->name . ' (' . (string) $rate->rate . '%)',
                    'country_code' => $countryCode,
                    'tax_rate'     => (string) $rate->rate,
                    'tax_type'     => (string) $rate->tax_type,
                    'valid_from'   => (string) $rate->valid_from,
                    'valid_until'  => (string) $rate->valid_until,
                );
            }
        } catch (Throwable $e) {
            // Keep the event form usable while an update is still running.
        }

        return $options;
    }
}
