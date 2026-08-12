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

class JFormFieldCapacityPoolOptions extends ListField
{
    protected $type = 'CapacityPoolOptions';

    protected function getOptions()
    {
        $options = array(HTMLHelper::_('select.option', '', Text::_('COM_JEM_EVENT_PRICE_EVENT_WIDE_CAPACITY')));
        $eventId = Factory::getApplication()->input->getInt('id');
        if ($eventId < 1) {
            return array_merge(parent::getOptions(), $options);
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(array('id', 'code', 'name', 'capacity'))
                ->from($db->quoteName('#__jem_capacity_pools'))
                ->where($db->quoteName('event_id') . ' = ' . $eventId)
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            foreach ((array) $db->loadObjectList() as $pool) {
                $options[] = HTMLHelper::_('select.option', (int) $pool->id, sprintf(
                    '%s [%s] - %d',
                    (string) $pool->name,
                    (string) $pool->code,
                    (int) $pool->capacity
                ));
            }
        } catch (Throwable $e) {
            // Keep the event form usable while an update is still running.
        }

        return array_merge(parent::getOptions(), $options);
    }
}
