<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

require_once dirname(__DIR__, 2) . '/classes/eventparentpolicy.class.php';

class JFormFieldEventparent extends ListField
{
    protected $type = 'Eventparent';

    /**
     * @return array Parent options filtered by the same policy used when saving.
     */
    protected function getOptions()
    {
        $selection = $this->parentSelection();
        $options = array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_NO_PARENT_EVENT')));
        foreach ($selection['options'] as $event) {
            $options[] = HTMLHelper::_('select.option', (int) $event->value, ($event->dates ? $event->dates . ' - ' : '') . $event->text);
        }

        return array_merge(parent::getOptions(), $options);
    }

    /**
     * @return string Selector, or a non-editable notice without the restricted parent's title.
     */
    protected function getInput()
    {
        if ($this->parentSelection()['locked']) {
            return '<span class="form-text">' . htmlspecialchars(
                Text::_('COM_JEM_EVENT_PARENT_UNAVAILABLE'), ENT_QUOTES, 'UTF-8'
            ) . '</span>';
        }

        return parent::getInput();
    }

    /**
     * @return array Authoritative editor context; no submitted parent value is trusted.
     */
    private function parentSelection(): array
    {
        return JemEventParentPolicy::selection(JemFactory::getUser(), $this->form ? (int) $this->form->getValue('id') : 0);
    }

}
