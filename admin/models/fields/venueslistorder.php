<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormField;

/**
 * Text field for the Venues List display order.
 */
class JFormFieldVenuesListOrder extends FormField
{
    /**
     * The form field type.
     *
     * @var string
     */
    protected $type = 'VenuesListOrder';

    /**
     * Convert legacy combo values to the current comma-separated field list.
     *
     * @return string
     */
    protected function getInput()
    {
        $legacyOrders = array(
            'venue_city_country' => 'venue,city,country',
            'venue_country_city' => 'venue,country,city',
            'city_venue_country' => 'city,venue,country',
            'city_country_venue' => 'city,country,venue',
            'country_venue_city' => 'country,venue,city',
            'country_city_venue' => 'country,city,venue',
        );

        $value = strtolower(trim((string) $this->value));

        $value = $legacyOrders[$value] ?? (string) $this->value;
        $class = trim((string) $this->class);
        $class = $class !== '' ? $class : 'form-control';

        $attributes = array(
            'type="text"',
            'name="' . htmlspecialchars((string) $this->name, ENT_QUOTES, 'UTF-8') . '"',
            'id="' . htmlspecialchars((string) $this->id, ENT_QUOTES, 'UTF-8') . '"',
            'value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"',
            'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"',
        );

        if (!empty($this->size)) {
            $attributes[] = 'size="' . (int) $this->size . '"';
        }

        if (!empty($this->hint)) {
            $attributes[] = 'placeholder="' . htmlspecialchars((string) $this->hint, ENT_QUOTES, 'UTF-8') . '"';
        }

        if ($this->required) {
            $attributes[] = 'required aria-required="true"';
        }

        if ((string) $this->readonly === '1' || (string) $this->readonly === 'true') {
            $attributes[] = 'readonly';
        }

        if ((string) $this->disabled === '1' || (string) $this->disabled === 'true') {
            $attributes[] = 'disabled';
        }

        if (!empty($this->onchange)) {
            $attributes[] = 'onchange="' . htmlspecialchars((string) $this->onchange, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<input ' . implode(' ', $attributes) . ' />';
    }
}
