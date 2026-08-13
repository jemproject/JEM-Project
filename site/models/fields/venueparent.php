<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldVenueparent extends ListField
{
    protected $type = 'Venueparent';

    protected function getOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = JemFactory::getUser();
        $levels = array_map('intval', $user->getAuthorisedViewLevels());
        $currentId = $this->form ? (int) $this->form->getValue('id') : 0;
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id', 'value'), $db->quoteName('venue', 'text'), $db->quoteName('city')))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' = 1')
            ->where('(' . $db->quoteName('parent_venue_id') . ' IS NULL OR ' . $db->quoteName('parent_venue_id') . ' = 0)')
            ->where($db->quoteName('access') . ' IN (' . (implode(',', $levels) ?: '0') . ')')
            ->order(array($db->quoteName('venue') . ' ASC', $db->quoteName('city') . ' ASC'));

        if ($currentId > 0) {
            $excluded = array_merge(array($currentId), $this->getDescendantIds($currentId));
            $query->where($db->quoteName('id') . ' NOT IN (' . implode(',', array_map('intval', $excluded)) . ')');
        }

        $db->setQuery($query);
        $options = array(HTMLHelper::_('select.option', 0, Text::_('COM_JEM_NO_PARENT_VENUE')));
        foreach ($db->loadObjectList() as $venue) {
            $options[] = HTMLHelper::_('select.option', (int) $venue->value, $venue->text . ($venue->city ? ' (' . $venue->city . ')' : ''));
        }

        return array_merge(parent::getOptions(), $options);
    }

    /**
     * Return all descendants so the selector cannot offer a cyclic parent.
     */
    private function getDescendantIds($venueId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $found = array();
        $pending = array((int) $venueId);

        while ($pending && count($found) < 1000) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('parent_venue_id') . ' IN (' . implode(',', array_map('intval', $pending)) . ')');
            $db->setQuery($query);
            $children = array_values(array_diff(array_map('intval', $db->loadColumn()), $found, array((int) $venueId)));
            $found = array_merge($found, $children);
            $pending = $children;
        }

        return array_values(array_unique($found));
    }
}
