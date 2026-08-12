<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldReminderDefinitions extends ListField
{
    protected $type = 'ReminderDefinitions';

    protected function getOptions()
    {
        $options = array();
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $selectedIds = array_values(array_filter(array_unique(array_map('intval', (array) $this->value))));
            $selectedSourceIds = array();
            $selectedCodes = array();
            if ($selectedIds) {
                $query = $db->getQuery(true)
                    ->select(array($db->quoteName('source_id'), $db->quoteName('code')))
                    ->from($db->quoteName('#__jem_reminders'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', $selectedIds) . ')')
                    ->where($db->quoteName('event_id') . ' > 0');
                $db->setQuery($query);
                foreach ((array) $db->loadObjectList() as $selected) {
                    if (!empty($selected->source_id)) {
                        $selectedSourceIds[] = (int) $selected->source_id;
                    }
                    if ((string) $selected->code !== '') {
                        $selectedCodes[] = (string) $selected->code;
                    }
                }
            }

            $query = $db->getQuery(true)
                ->select(array('id', 'title', 'ordering', 'minutes'))
                ->from($db->quoteName('#__jem_reminders'))
                ->where('((' . $db->quoteName('event_id') . ' = 0 AND '
                    . $db->quoteName('published') . ' = 1)'
                    . ($selectedIds ? ' OR ' . $db->quoteName('id') . ' IN (' . implode(',', $selectedIds) . ')' : '')
                    . ')')
                ->order(array($db->quoteName('ordering') . ' ASC', $db->quoteName('minutes') . ' DESC'));
            if ($selectedSourceIds || $selectedCodes) {
                $query->where('(' . $db->quoteName('event_id') . ' > 0 OR '
                    . '(' . ($selectedSourceIds
                        ? $db->quoteName('id') . ' NOT IN (' . implode(',', $selectedSourceIds) . ')'
                        : '1 = 1')
                    . ' AND ' . ($selectedCodes
                        ? $db->quoteName('code') . ' NOT IN (' . implode(',', array_map(array($db, 'quote'), $selectedCodes)) . ')'
                        : '1 = 1') . '))');
            }
            $db->setQuery($query);
            foreach ((array) $db->loadObjectList() as $definition) {
                $label = strpos((string) $definition->title, 'COM_JEM_') === 0
                    ? Text::_((string) $definition->title)
                    : (string) $definition->title;
                $options[] = HTMLHelper::_('select.option', (int) $definition->id, $label);
            }
        } catch (Throwable $error) {
            // Keep event editing operational while an update is still running.
        }

        return array_merge(parent::getOptions(), $options);
    }

    protected function getInput()
    {
        return '<input type="hidden" name="' . htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8')
            . '[]" value="">' . parent::getInput();
    }
}
