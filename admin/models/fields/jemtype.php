<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldJemtype extends FormField
{
    protected $type = 'Jemtype';

    /**
     * Check whether the field has published type options for its entity.
     *
     * @return  bool
     */
    public function hasAvailableTypes()
    {
        return count($this->getTypes()) > 0;
    }

    protected function getInput()
    {
        $types = $this->getTypes();
        $class = trim('form-select ' . $this->class);
        $class = preg_match('/(^|\s)w-auto(\s|$)/', $class) ? $class : $class . ' w-auto';

        if ($types === array()) {
            $html = array();
            $html[] = '<select id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" disabled="disabled">';
            $html[] = '<option value="">' . Text::_('COM_JEM_TYPE_SELECT_NONE') . '</option>';
            $html[] = '</select>';
            $html[] = '<input type="hidden" name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '" />';

            return implode("\n", $html);
        }

        $options   = array();
        $options[] = HTMLHelper::_('select.option', '', Text::_('COM_JEM_TYPE_SELECT_NONE'));

        foreach ($types as $t) {
            $label = htmlspecialchars($t->name, ENT_QUOTES, 'UTF-8');
            if ($t->icon) {
                $label = '<span class="' . htmlspecialchars($t->icon, ENT_QUOTES, 'UTF-8') . '"></span> ' . $label;
            }
            $options[] = HTMLHelper::_('select.option', $t->id, $t->name);
        }

        $attribs = array(
            'id'    => $this->id,
            'class' => $class,
        );

        $html = HTMLHelper::_('select.genericlist', $options, $this->name, $attribs, 'value', 'text', $this->value, $this->id);

        if (count($types) < $this->getFancySelectThreshold()) {
            return $html;
        }

        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->usePreset('choicesjs')
            ->useScript('webcomponent.field-fancy-select');

        return '<joomla-field-fancy-select class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" placeholder="' . Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS') . '">' . $html . '</joomla-field-fancy-select>';
    }

    /**
     * Get published type options for the configured entity.
     *
     * @return  array
     */
    protected function getTypes()
    {
        $entity = (int) $this->getAttribute('entity', 1);

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'name', 'icon', 'color')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = ' . $entity)
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: array();
    }

    /**
     * Get the configured threshold for switching long entity lists to fancy select.
     *
     * @return  int
     */
    protected function getFancySelectThreshold()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('value'))
            ->from($db->quoteName('#__jem_config'))
            ->where($db->quoteName('keyname') . ' = ' . $db->quote('fancy_select_threshold'));

        try {
            $db->setQuery($query);
            $threshold = (int) $db->loadResult();
        } catch (RuntimeException $e) {
            $threshold = 10;
        }

        return max(1, $threshold ?: 10);
    }
}
