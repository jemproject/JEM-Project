<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldJemlinktype extends FormField
{
    protected $type = 'Jemlinktype';

    protected function getInput()
    {
        $file    = JPATH_ADMINISTRATOR . '/components/com_jem/models/links/actiontypes.json';
        $content = file_get_contents($file);
        $types   = json_decode($content, true);
        $options = array();
        $iconMap = array();

        foreach ($types as $type) {
            if (empty($type['value'])) {
                continue;
            }

            $value = $type['value'];
            $label = !empty($type['label']) ? Text::_($type['label']) : $value;
            $icon = !empty($type['icon']) ? $type['icon'] : '';

            $options[] = HTMLHelper::_('select.option', $value, $label);
            $iconMap[$value] = $icon;
        }

        $selectedIcon = isset($iconMap[$this->value]) ? $iconMap[$this->value] : '';

        $attributes = array(
            'id'         => $this->id,
            'class'      => 'form-select jem-link-type-select',
            'data-icons' => htmlspecialchars(json_encode($iconMap), ENT_QUOTES, 'UTF-8')
        );

        $iconTag = !empty($selectedIcon) ? '<span class="' . htmlspecialchars($selectedIcon, ENT_QUOTES, 'UTF-8') . '"></span>' : '';

        $html = '<div class="jem-link-type-field">'
            . HTMLHelper::_('select.genericlist', $options, $this->name, $attributes, 'value', 'text', $this->value)
            . '<span class="jem-link-type-icon-preview" aria-hidden="true">' . $iconTag . '</span></div>';

        $this->loadAssets();

        return $html;
    }

    protected function loadAssets()
    {
        // load one time
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_jem.jemlinktype', 'media/com_jem/css/jem-links.css');
        $wa->registerAndUseScript('com_jem.jemlinktype', 'media/com_jem/js/jem-links.js');
    }
}