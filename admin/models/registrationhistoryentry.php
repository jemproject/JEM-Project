<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Read-only detail and timeline model for one registration history entry.
 */
class JemModelRegistrationhistoryentry extends BaseDatabaseModel
{
    public function getItem()
    {
        $id = Factory::getApplication()->input->getInt('id');
        if ($id < 1) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $this->baseQuery()
            ->where($db->quoteName('h.id') . ' = ' . $id);
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    public function getTimeline()
    {
        $item = $this->getItem();
        if (!$item) {
            return array();
        }

        $db = $this->getDatabase();
        $query = $this->baseQuery()
            ->where($db->quoteName('h.registration_reference') . ' = ' . $db->quote($item->registration_reference))
            ->order($db->quoteName('h.revision') . ' ASC, ' . $db->quoteName('h.id') . ' ASC');
        $db->setQuery($query);

        return (array) $db->loadObjectList();
    }

    private function baseQuery()
    {
        $db = $this->getDatabase();

        return $db->getQuery(true)
            ->select(array(
                'h.*',
                'COALESCE(NULLIF(h.event_title, ' . $db->quote('') . '), e.title) AS event_display_title',
                'old_holder.name AS old_holder_name',
                'old_holder.username AS old_holder_username',
                'new_holder.name AS new_holder_name',
                'new_holder.username AS new_holder_username',
                'actor.name AS actor_name',
                'actor.username AS actor_username',
                'r.id AS current_registration_id',
                'e.id AS current_event_id',
            ))
            ->from($db->quoteName('#__jem_registration_history', 'h'))
            ->join('LEFT', $db->quoteName('#__jem_register', 'r')
                . ' ON ' . $db->quoteName('r.id') . ' = ' . $db->quoteName('h.registration_id')
                . ' AND ' . $db->quoteName('r.reference') . ' = ' . $db->quoteName('h.registration_reference'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('h.event_id'))
            ->join('LEFT', $db->quoteName('#__users', 'old_holder') . ' ON ' . $db->quoteName('old_holder.id') . ' = ' . $db->quoteName('h.old_user_id'))
            ->join('LEFT', $db->quoteName('#__users', 'new_holder') . ' ON ' . $db->quoteName('new_holder.id') . ' = ' . $db->quoteName('h.new_user_id'))
            ->join('LEFT', $db->quoteName('#__users', 'actor') . ' ON ' . $db->quoteName('actor.id') . ' = ' . $db->quoteName('h.actor_user_id'));
    }
}
