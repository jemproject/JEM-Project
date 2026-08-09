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
        $user = JemFactory::getUser();
        $levels = array_map('intval', $user->getAuthorisedViewLevels());
        $currentId = $this->form ? (int) $this->form->getValue('id') : 0;
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('title', 'text'), $db->quoteName('dates')))
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('dates') . ' IS NOT NULL')
            ->where($db->quoteName('access') . ' IN (' . (implode(',', $levels) ?: '0') . ')')
            ->where('(' . $db->quoteName('parent_event_id') . ' IS NULL OR ' . $db->quoteName('parent_event_id') . ' = 0)')
            ->order(array($db->quoteName('dates') . ' DESC', $db->quoteName('title') . ' ASC'));

        if ($currentId > 0) {
            $query->where($db->quoteName('id') . ' <> ' . $currentId);
        }

        $db->setQuery($query);
        $options = array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_NO_PARENT_EVENT')));
        foreach ($db->loadObjectList() as $event) {
            $options[] = HTMLHelper::_('select.option', (int) $event->value, ($event->dates ? $event->dates . ' - ' : '') . $event->text);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
