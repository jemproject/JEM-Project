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
 * Joomla content category selector.
 */
class JFormFieldContentCategory extends ListField
{
    /**
     * The form field type.
     *
     * @var string
     */
    protected $type = 'ContentCategory';

    /**
     * Render long category lists as searchable selectors.
     *
     * @return  string
     */
    protected function getInput()
    {
        $options = $this->getOptions();
        $useFancy = $this->multiple || count($options) >= $this->getFancySelectThreshold();
        $class = trim((string) $this->class);
        $class = $class !== '' ? $class : 'form-select w-auto';
        $class = preg_match('/(^|\s)w-auto(\s|$)/', $class) ? $class : $class . ' w-auto';
        $selectClass = $useFancy ? trim(preg_replace('/\bw-auto\b/', '', $class)) : $class;
        $wrapperClass = 'jem-fancy-select';

        $attr  = ' class="' . $selectClass . '"';
        $attr .= !empty($this->size) ? ' size="' . $this->size . '"' : '';
        $attr .= $this->multiple ? ' multiple' : '';
        $attr .= $this->required ? ' required aria-required="true"' : '';

        if ((string) $this->readonly == '1' || (string) $this->readonly == 'true' || (string) $this->disabled == '1' || (string) $this->disabled == 'true') {
            $attr .= ' disabled="disabled"';
        }

        $attr .= $this->onchange ? ' onchange="' . $this->onchange . '"' : '';

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

        if (!$useFancy || (string) $this->element['fancy'] === 'false' || (string) $this->element['fancy'] === '0') {
            return $html;
        }

        $fancyAttr  = ' class="' . $wrapperClass . '"';
        $fancyAttr .= $this->multiple ? ' multiple' : '';
        $fancyAttr .= $this->required ? ' required aria-required="true"' : '';
        $fancyAttr .= ' placeholder="' . Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS') . '"';

        if ((string) $this->readonly == '1' || (string) $this->readonly == 'true' || (string) $this->disabled == '1' || (string) $this->disabled == 'true') {
            $fancyAttr .= ' disabled="disabled"';
        }

        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->usePreset('choicesjs')
            ->useScript('webcomponent.field-fancy-select');
        $this->addFancySelectClearButtonStyle();

        return '<joomla-field-fancy-select ' . $fancyAttr . '>' . $html . '</joomla-field-fancy-select>';
    }

    /**
     * Style the Choices clear button so it reads as an action, not as text.
     *
     * @return  void
     */
    protected function addFancySelectClearButtonStyle()
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        Factory::getApplication()->getDocument()->addStyleDeclaration('
joomla-field-fancy-select.jem-fancy-select,
joomla-field-fancy-select.jem-fancy-select .choices,
joomla-field-fancy-select.jem-fancy-select .choices__inner {
    min-width: 20rem;
    max-width: 100%;
}
joomla-field-fancy-select.jem-fancy-select .choices__list--single,
joomla-field-fancy-select.jem-fancy-select .choices__item {
    white-space: nowrap;
}
joomla-field-fancy-select.jem-fancy-select .choices__item--selectable {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
');
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
     * Get Joomla article category options.
     *
     * @return  array
     */
    protected function getOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('id', 'value'),
                $db->quoteName('title', 'text'),
                $db->quoteName('level'),
                $db->quoteName('published')
            ))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' IN (0,1)')
            ->order($db->quoteName('lft') . ' ASC');

        try {
            $db->setQuery($query);
            $options = $db->loadObjectList() ?: array();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            $options = array();
        }

        foreach ($options as $option) {
            $option->text = str_repeat('- ', max(0, (int) $option->level - 1)) . ((int) $option->published === 1 ? $option->text : '[' . $option->text . ']');
        }

        return array_merge(parent::getOptions(), $options);
    }
}
