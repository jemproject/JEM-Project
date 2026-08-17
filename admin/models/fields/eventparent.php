<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldEventparent extends ListField
{
    protected $type = 'Eventparent';

    protected function getOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getApplication()->getIdentity();
        $levels = array_values(array_unique(array_filter(array_map('intval', $user->getAuthorisedViewLevels()))));
        $levelsList = $levels ? implode(',', $levels) : '0';
        $currentId = $this->form ? (int) $this->form->getValue('id') : 0;
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('e.id', 'value'), $db->quoteName('e.title', 'text'), $db->quoteName('e.dates')))
            ->from($db->quoteName('#__jem_events', 'e'))
            ->where($db->quoteName('e.published') . ' >= 0')
            ->where($db->quoteName('e.dates') . ' IS NOT NULL')
            ->where($db->quoteName('e.access') . ' IN (' . $levelsList . ')')
            ->where('(' . $db->quoteName('e.parent_event_id') . ' IS NULL OR ' . $db->quoteName('e.parent_event_id') . ' = 0)')
            ->order(array($db->quoteName('e.dates') . ' DESC', $db->quoteName('e.title') . ' ASC'));

        if ($currentId > 0) {
            $query->where($db->quoteName('e.id') . ' <> ' . $currentId);
        }

        $db->setQuery($query);
        $options = array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_NO_PARENT_EVENT')));

        foreach ($db->loadObjectList() as $event) {
            $label = trim((string) $event->dates) !== '' ? $event->dates . ' - ' . $event->text : $event->text;
            $options[] = HTMLHelper::_('select.option', (int) $event->value, $label);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
