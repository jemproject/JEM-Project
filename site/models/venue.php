<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

require_once __DIR__ . '/eventslist.php';

/**
 * Model: venue
 */
class JemModelVenue extends JemModelEventslist
{
    /**
     * Venue id
     *
     * @var int
     */
    protected $_id = null;


    public function __construct()
    {
        $app    = Factory::getApplication();
        $jinput = $app->input;
        $params = $app->getParams();

        # determing the id to load
        if ($jinput->get('id',null,'int')) {
            $id = $jinput->get('id',null,'int');
        } else {
            $id = $params->get('id');
        }
        $this->setId((int)$id);

        parent::__construct();
    }

    /**
     * Method to auto-populate the model state.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app         = Factory::getApplication();
        $jemsettings = JemHelper::config();
        $params      = $app->getParams();
        $jinput      = $app->input;
        $task        = $jinput->getCmd('task','');
        $itemid      = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);
        $user        = JemFactory::getUser();
        $format      = $jinput->getCmd('format',false);

        // List state information

        if (empty($format) || ($format == 'html')) {
            /* Preserve limitstart when it is missing from the request. */
            if ($app->input->getInt('limitstart', null) === null) {
                $app->setUserState('com_jem.venue.'.$itemid.'.limitstart', 0);
            }

            $limit = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
            $this->setState('list.limit', $limit);

            $limitstart = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
            // correct start value if required
            $limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
            $this->setState('list.start', $limitstart);
        }

        # Search
        $search = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_search', 'filter_search', '', 'string');
        $this->setState('filter.filter_search', $search);

        $month = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_month', 'filter_month', '', 'string');
        $this->setState('filter.filter_month', $month);

        # FilterType
        $filtertype = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
        $this->setState('filter.filter_type', $filtertype);

        # filter_order
        $orderCol = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
        $this->setState('filter.filter_ordering', $orderCol);

        # filter_direction
        $listOrder = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
        $this->setState('filter.filter_direction', $listOrder);

        # show open date events
        # (there is no menu item option yet so show all events)
        $this->setState('filter.opendates', 1);

        $defaultOrder = ($task == 'archive') ? 'DESC' : 'ASC';
        if ($orderCol == 'a.dates') {
            $orderby = array('a.dates ' . $listOrder, 'a.times ' . $listOrder, 'a.created ' . $listOrder);
        } else {
            $orderby = array($orderCol . ' ' . $listOrder,
                             'a.dates ' . $defaultOrder, 'a.times ' . $defaultOrder, 'a.created ' . $defaultOrder);
        }
        $this->setState('filter.orderby', $orderby);

        # params
        $this->setState('params', $params);

        # publish state
        $this->_populatePublishState($task);

        $this->setState('filter.groupby',array('a.id'));
    }

    /**
     * Method to get a list of events.
     */
    public function getItems()
    {
        $items = parent::getItems();
        /* no additional things to do yet - place holder */
        if ($items) {
            return $items;
        }

        return array();
    }

    /**
     * @return    JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $query = parent::getListQuery();

        // here we can extend the query of the Eventslist model
        $query->where('a.locid = '.(int)$this->_id);

        return $query;
    }

    /**
     * Method to set the venue id
     *
     * The venue-id can be set by a menu-parameter
     */
    public function setId($id)
    {
        // Set new venue ID and wipe data
        $this->_id   = $id;
        //$this->_data = null;
    }

    /**
     * set limit
     * @param int value
     */
    public function setLimit($value)
    {
        $this->setState('limit', (int) $value);
    }

    /**
     * set limitstart
     * @param int value
     */
    public function setLimitStart($value)
    {
        $this->setState('limitstart', (int) $value);
    }

    /**
     * Method to get a specific Venue
     *
     * @access public
     * @return array
     */
    public function getVenue()
    {
        $user   = JemFactory::getUser();
        $levels = $user->getAuthorisedViewLevels();
        $levelsList = implode(',', array_map('intval', $levels)) ?: '0';
        $jemsettings = JemHelper::config();

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query  = $db->getQuery(true);

        $query->select('v.id, v.venue, v.published, v.city, v.district, v.level, v.capacity, v.state, v.url, v.email, v.phone, v.mobile, v.street, v.custom1, v.custom2, v.custom3, v.custom4, v.custom5, '.
                       ' v.custom6, v.custom7, v.custom8, v.custom9, v.custom10, v.locimage, v.meta_keywords, v.meta_description, v.access, '.
                       ' v.created, v.created_by, v.locdescription, v.country, v.map, v.latitude, v.longitude, v.postalCode, v.checked_out AS vChecked_out, v.checked_out_time AS vChecked_out_time, '.
                       ' v.attribs, '.
                       ' CASE WHEN CHAR_LENGTH(v.alias) THEN CONCAT_WS(\':\', v.id, v.alias) ELSE v.id END as slug');
        $query->from($db->quoteName('#__jem_venues', 'v'));

        $typeLanguage = Factory::getApplication()->getLanguage()->getTag();
        $typeLanguageCondition = '(jt.language IN (' . $db->quote('*') . ', ' . $db->quote($typeLanguage) . ') OR jt.base_language <> ' . $db->quote('') . ' OR jt.translation_languages IS NOT NULL)';
        $query->select(array(
            'jt.id AS type_id',
            'jt.name AS type_name',
            'jt.icon AS type_icon',
            'jt.color AS type_color',
            'jt.alias AS type_alias',
            'jt.description AS type_description',
            'jt.base_language AS type_base_language',
            'jt.translation_languages AS type_translation_languages',
            'jt.translations AS type_translations',
        ));
        $query->join('LEFT', $db->quoteName('#__jem_types', 'jt') . ' ON jt.id = v.type_id AND jt.entity = 3 AND jt.published = 1 AND jt.access IN (' . $levelsList . ') AND ' . $typeLanguageCondition);

        $case_when_a  = ' CASE WHEN ';
        $case_when_a .= " v.access IN (" . $levelsList . ")";
        $case_when_a .= ' THEN 1 ';
        $case_when_a .= ' ELSE 0 ';
        $case_when_a .= ' END as user_has_access_venue';

        $query->select(array($case_when_a));

        # Filter by access level - public or with access_level_locked_venues active.
        if($jemsettings->access_level_locked_venues != "[\"1\"]") {
            $accessLevels = json_decode($jemsettings->access_level_locked_venues, true);
            $newlevels = array_values(array_unique(array_merge($levels, $accessLevels)));
            $query->where('v.access IN ('.implode(',', array_map('intval', $newlevels)).')');
        } else {
            $query->where('v.access IN ('.$levelsList.')');
        }

        $query->where('v.id = '.(int)$this->_id);

        // all together: if published or the user is creator of the venue or allowed to edit or publish venues
        if (empty($user->id)) {
            $query->where('v.published = 1');
        }
        // no limit if user can publish or edit foreign venues
        elseif ($user->can(array('edit', 'publish'), 'venue')) {
            $query->where('v.published IN (0,1)');
        }
        // user maybe creator
        else {
            $query->where('(v.published = 1 OR (v.published = 0 AND v.created_by = ' . $this->_db->Quote($user->id) . '))');
        }

        $db->setQuery($query);
        $_venue = $db->loadObject();

        if (empty($_venue)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND'), 'error');
            return false;
        }

        $registry = new Registry;
        $registry->loadString($_venue->attribs ?? '{}');
        $_venue->params = clone JemHelper::globalattribs();
        $_venue->params->merge($registry);

        $_venue->attachments = JemAttachment::getAttachments('venue'.$_venue->id);

        return $_venue;
    }

    /**
     * Get the published venues available to the current user.
     *
     * @return  array<int, object>
     */
    public function getVenueOptions()
    {
        $user       = JemFactory::getUser();
        $levels     = array_map('intval', $user->getAuthorisedViewLevels());
        $levelsList = implode(',', $levels) ?: '0';
        $db         = Factory::getContainer()->get('DatabaseDriver');
        $query      = $db->getQuery(true);
        $params     = Factory::getApplication()->getParams();
        $allowedIds = $this->normaliseParamIds($params->get('timeline_filter_venues', array()));

        $query->select(array(
                $db->quoteName('v.id', 'value'),
                $db->quoteName('v.venue', 'text'),
                $db->quoteName('v.city'),
            ))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where($db->quoteName('v.published') . ' = 1')
            ->where($db->quoteName('v.access') . ' IN (' . $levelsList . ')')
            ->order(array($db->quoteName('v.venue') . ' ASC', $db->quoteName('v.city') . ' ASC'));

        if ($allowedIds) {
            $query->where($db->quoteName('v.id') . ' IN (' . implode(',', $allowedIds) . ')');
        }

        $db->setQuery($query);
        $options = $db->loadObjectList();

        foreach ($options as $option) {
            $city = trim((string) $option->city);

            if ($city !== '') {
                $option->text .= ' - ' . $city;
            }

            unset($option->city);
        }

        return $options;
    }
}
?>
