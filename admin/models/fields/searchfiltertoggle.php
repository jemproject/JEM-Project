<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\RadioField;

/**
 * Display a Search visibility toggle while resolving older grouped settings.
 */
class JFormFieldSearchfiltertoggle extends RadioField
{
    /** @var string Joomla field type. */
    protected $type = 'Searchfiltertoggle';

    /**
     * Resolve an unset individual option before rendering the standard Yes/No buttons.
     *
     * @param SimpleXMLElement $element Field definition, including its legacy setting.
     * @param mixed $value Saved individual value; null/empty means not yet configured.
     * @param string|null $group Form data group containing the Search menu parameters.
     * @return bool Whether Joomla successfully configured the field.
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        if ($value === null || $value === '') {
            $legacy = (string) $element['legacysetting'];
            $value = $legacy !== '' && $this->form !== null
                ? $this->form->getValue($legacy, $group, 1)
                : 1;
            $value = $value === null || $value === '' ? 1 : (int) (bool) $value;
        }

        return parent::setup($element, $value, $group);
    }
}

