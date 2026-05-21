<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date;

require_once __DIR__ . '/eventslist.php';

class JemModelTypeevents extends JemModelEventslist
{
    protected function populateState($ordering = null, $direction = null)
    {
        $app    = Factory::getApplication();
        $params = $app->getParams('com_jem');
        $typeId = $app->input->getInt('id', 0) ?: (int) $params->get('id', 0);

        parent::populateState($ordering, $direction);

        $this->setState('filter.type_id', $typeId);
        $this->setState('filter.published', 1);
        $this->setState('filter.unpublished', null);
        $this->setState('filter.unpublished.events.on_groups', null);
        $this->setState('filter.unpublished.on_user', null);
        $this->setState('filter.show_archived_events', 0);

        $db        = Factory::getContainer()->get('DatabaseDriver');
        $today     = (new Date('now', $app->get('offset')))->format('Y-m-d');
        $dateWhere = 'COALESCE(a.enddates, a.dates) >= ' . $db->quote($today);

        $this->setState('filter.calendar_from', $dateWhere);
        $this->setState('filter.tablefiltereventfrom', 0);
        $this->setState('filter.tablefiltereventuntil', '');
    }

    public function getType()
    {
        $typeId = (int) $this->getState('filter.type_id');
        if (!$typeId) {
            return null;
        }

        $app      = Factory::getApplication();
        $user     = JemFactory::getUser();
        $levels   = $user->getAuthorisedViewLevels();
        $levelsList = implode(',', array_map('intval', $levels)) ?: '0';
        $language = $app->getLanguage()->getTag();
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $query    = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'name', 'alias', 'icon', 'color', 'description', 'base_language', 'translation_languages', 'translations', 'language', 'access')))
            ->select('CASE WHEN ' . $db->quoteName('access') . ' IN (' . $levelsList . ') THEN 1 ELSE 0 END AS ' . $db->quoteName('user_has_access_type'))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('id') . ' = ' . $typeId)
            ->where($db->quoteName('entity') . ' = 1')
            ->where($db->quoteName('published') . ' = 1')
            ->where('('
                . $db->quoteName('language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')'
                . ' OR ' . $db->quoteName('base_language') . ' = ' . $db->quote($language)
                . ' OR ' . $db->quoteName('translation_languages') . ' LIKE ' . $db->quote('%' . $language . '%')
                . ')');

        $db->setQuery($query);
        $type = $db->loadObject();

        if ($type) {
            require_once JPATH_SITE . '/components/com_jem/classes/output.class.php';
            JemOutput::translateType($type);
        }

        return $type;
    }
}
