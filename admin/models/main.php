<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * JEM Component Main Model
 *
 * @package JEM
 */
class JemModelMain extends BaseDatabaseModel
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get number of items for given states of a table
     *
     * @param  string $tablename Name of the table
     * @param  array  $map       Maps state name to state number
     * @return stdClass
     */
    protected function getStateData($tablename, &$map = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        if($map == null) {
            $map = array('published' => 1, 'unpublished' => 0, 'archived' => 2, 'trashed' => -2);
        }

        // Get nr of all states of events
        $query = $db->getQuery(true);
        $query->select(array('published', 'COUNT(published) as num'));
        $query->from($db->quoteName($tablename));
        if ($tablename == "#__jem_categories")
        {
            $query->where('alias NOT LIKE "root"');
        }
        $query->group('published');

        $db->setQuery($query);
        $result = $db->loadObjectList("published");

        $data = new stdClass();
        $data->total = 0;

        foreach ($map as $key => $value) {
            if ($result) {
                // Check whether we have the current state in the DB result
                if(array_key_exists($value, $result)) {
                    $data->$key = $result[$value]->num;
                    $data->total += $data->$key;
                } else {
                    $data->$key = 0;
                }
            } else {
                $data->$key = 0;
            }
        }

        return $data;
    }

    /**
     * Returns number of events for all possible states
     *
     * @return stdClass
     */
    public function getEventsData()
    {
        return $this->getStateData('#__jem_events');
    }

    /**
     * Returns number of venues for all possible states
     *
     * @return stdClass
     */
    public function getVenuesData()
    {
        return $this->getStateData('#__jem_venues');
    }

    /**
     * Returns number of categories for all possible states
     *
     * @return stdClass
     */
    public function getCategoriesData()
    {
        return $this->getStateData('#__jem_categories');
    }

    /**
     * Returns number of types for all possible states
     *
     * @return stdClass
     */
    public function getTypesData()
    {
        return $this->getStateData('#__jem_types');
    }

    /**
     * Returns number of types by entity
     *
     * @return stdClass
     */
    public function getTypeEntitiesData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array('entity', 'COUNT(*) AS num'))
            ->from($db->quoteName('#__jem_types'))
            ->group($db->quoteName('entity'));

        $db->setQuery($query);
        $result = $db->loadObjectList('entity');

        $data = (object) array(
            'event' => isset($result[1]) ? (int) $result[1]->num : 0,
            'category' => isset($result[2]) ? (int) $result[2]->num : 0,
            'venue' => isset($result[3]) ? (int) $result[3]->num : 0,
        );

        $data->total = $data->event + $data->category + $data->venue;

        return $data;
    }

    /**
     * Returns number of assigned images/icons by entity
     *
     * @return stdClass
     */
    public function getImagesData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $queries = array(
            'events' => array('#__jem_events', 'datimage', ''),
            'venues' => array('#__jem_venues', 'locimage', ''),
            'categories' => array('#__jem_categories', 'image', 'alias NOT LIKE "root"'),
            'types' => array('#__jem_types', 'icon', ''),
        );

        $data = new stdClass();
        $data->total = 0;

        foreach ($queries as $key => $queryData) {
            [$table, $field, $where] = $queryData;

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($table))
                ->where($db->quoteName($field) . ' IS NOT NULL')
                ->where($db->quoteName($field) . ' <> ' . $db->quote(''));

            if ($where !== '') {
                $query->where($where);
            }

            $db->setQuery($query);
            $data->$key = (int) $db->loadResult();
            $data->total += $data->$key;
        }

        return $data;
    }

    /**
     * Returns number of attachments by linked object type
     *
     * @return stdClass
     */
    public function getAttachmentsData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(array(
                'CASE'
                    . ' WHEN ' . $db->quoteName('object') . ' LIKE ' . $db->quote('event%') . ' THEN ' . $db->quote('events')
                    . ' WHEN ' . $db->quoteName('object') . ' LIKE ' . $db->quote('venue%') . ' THEN ' . $db->quote('venues')
                    . ' WHEN ' . $db->quoteName('object') . ' LIKE ' . $db->quote('category%') . ' THEN ' . $db->quote('categories')
                    . ' ELSE ' . $db->quote('other')
                    . ' END AS ' . $db->quoteName('object_type'),
                'COUNT(*) AS ' . $db->quoteName('num'),
            ))
            ->from($db->quoteName('#__jem_attachments'))
            ->group($db->quoteName('object_type'));

        $db->setQuery($query);
        $result = $db->loadObjectList('object_type');

        $data = (object) array(
            'events' => isset($result['events']) ? (int) $result['events']->num : 0,
            'venues' => isset($result['venues']) ? (int) $result['venues']->num : 0,
            'categories' => isset($result['categories']) ? (int) $result['categories']->num : 0,
            'other' => isset($result['other']) ? (int) $result['other']->num : 0,
        );

        $data->total = $data->events + $data->venues + $data->categories + $data->other;

        return $data;
    }

    /**
     * Returns global registration and booked places statistics
     *
     * @return stdClass
     */
    public function getRegistrationData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(array(
                'COUNT(*) AS ' . $db->quoteName('total'),
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = 1 AND ' . $db->quoteName('waiting') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('attending_users'),
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = 1 AND ' . $db->quoteName('waiting') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('waiting_users'),
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('invited_users'),
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = -1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('not_attending_users'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('status') . ' = 1 AND ' . $db->quoteName('waiting') . ' = 0 THEN ' . $db->quoteName('places') . ' ELSE 0 END), 0) AS ' . $db->quoteName('booked_places'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('status') . ' = 1 AND ' . $db->quoteName('waiting') . ' = 1 THEN ' . $db->quoteName('places') . ' ELSE 0 END), 0) AS ' . $db->quoteName('waiting_places'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('status') . ' = 0 THEN ' . $db->quoteName('places') . ' ELSE 0 END), 0) AS ' . $db->quoteName('invited_places'),
            ))
            ->from($db->quoteName('#__jem_register'));

        $db->setQuery($query);
        $row = $db->loadObject() ?: new stdClass();

        return (object) array(
            'total' => isset($row->total) ? (int) $row->total : 0,
            'attending_users' => isset($row->attending_users) ? (int) $row->attending_users : 0,
            'waiting_users' => isset($row->waiting_users) ? (int) $row->waiting_users : 0,
            'invited_users' => isset($row->invited_users) ? (int) $row->invited_users : 0,
            'not_attending_users' => isset($row->not_attending_users) ? (int) $row->not_attending_users : 0,
            'booked_places' => isset($row->booked_places) ? (int) $row->booked_places : 0,
            'waiting_places' => isset($row->waiting_places) ? (int) $row->waiting_places : 0,
            'invited_places' => isset($row->invited_places) ? (int) $row->invited_places : 0,
        );
    }
}
?>
