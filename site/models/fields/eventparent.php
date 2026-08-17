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
        $levels = array_values(array_unique(array_filter(array_map('intval', $user->getAuthorisedViewLevels()))));
        $levelsList = $levels ? implode(',', $levels) : '0';
        $visibleVenueIds = JemHelper::getVisibleVenueHierarchyIds($levels);
        $visibleVenueList = $visibleVenueIds ? implode(',', array_map('intval', $visibleVenueIds)) : '0';
        $currentId = $this->form ? (int) $this->form->getValue('id') : 0;
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('e.id', 'value'), $db->quoteName('e.title', 'text'), $db->quoteName('e.dates')))
            ->from($db->quoteName('#__jem_events', 'e'))
            ->join('LEFT', $db->quoteName('#__jem_types', 't') . ' ON t.id = e.type_id AND t.entity = 1')
            ->where(JemHelper::getEventPublicationWhere('e'))
            ->where($db->quoteName('e.dates') . ' IS NOT NULL')
            ->where($db->quoteName('e.access') . ' IN (' . $levelsList . ')')
            ->where('(' . $db->quoteName('e.parent_event_id') . ' IS NULL OR ' . $db->quoteName('e.parent_event_id') . ' = 0)')
            ->where('(e.locid IS NULL OR e.locid = 0 OR e.locid IN (' . $visibleVenueList . '))')
            ->where('(e.type_id IS NULL OR e.type_id = 0 OR (t.id IS NOT NULL AND t.published = 1 AND t.access IN (' . $levelsList . ')))')
            ->where('EXISTS (SELECT 1 FROM #__jem_cats_event_relations AS parent_rel'
                . ' INNER JOIN #__jem_categories AS parent_cat ON parent_cat.id = parent_rel.catid'
                . ' WHERE parent_rel.itemid = e.id AND parent_cat.published = 1'
                . ' AND parent_cat.access IN (' . $levelsList . '))')
            ->order(array($db->quoteName('e.dates') . ' DESC', $db->quoteName('e.title') . ' ASC'));

        if ($currentId > 0) {
            $query->where($db->quoteName('e.id') . ' <> ' . $currentId);
        }

        $db->setQuery($query);
        $options = array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_NO_PARENT_EVENT')));
        foreach ($db->loadObjectList() as $event) {
            $options[] = HTMLHelper::_('select.option', (int) $event->value, ($event->dates ? $event->dates . ' - ' : '') . $event->text);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
