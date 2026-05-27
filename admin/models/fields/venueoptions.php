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
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

FormHelper::loadFieldClass('list');

/**
 * Field: Venueoptions
 */
class JFormFieldVenueoptions extends ListField
{
    /**
     * A venue list
     */
    public $type = 'Venueoptions';

    /**
     * Render multiple venue filters as a searchable selector.
     *
     * @return  string
     */
    protected function getInput()
    {
        $options = $this->getOptions();
        $useFancy = $this->multiple || count($options) >= $this->getFancySelectThreshold();

        if (!$useFancy) {
            return parent::getInput();
        }

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
            $options,
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

    /**
     * @return    array    The field option objects.
     */
    protected function getOptions()
    {
        // Initialise variables.
        $options = array();
        $published = $this->element['published']? $this->element['published'] : array(0,1);
        $name = (string) $this->element['name'];

        // Let's get the id for the current item
        $jinput = Factory::getApplication()->input;

        // Create SQL
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query    = $db->getQuery(true);

        $query->select('l.id AS value, l.venue AS text, l.published');
        $query->from('#__jem_venues AS l');

        // Filter on the published state
        if (is_numeric($published))
        {
            $query->where('l.published = ' . (int) $published);
        }
        elseif (is_array($published))
        {
            \Joomla\Utilities\ArrayHelper::toInteger($published);
            $query->where('l.published IN (' . implode(',', $published) . ')');
        }

        $query->group('l.id');
        $query->order('l.venue');

        // Get the options.
        $db->setQuery($query);

        try
        {
            $options = $db->loadObjectList();
        }
        catch (RuntimeException $e)
        {
            Factory::getApplication()->enqueueMessage($e->getMessage, 'warning');
        }

        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $options);

        return $options;
    }
}
