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
 * Personal timeline model.
 */
class JemModelMytimeline extends BaseDatabaseModel
{
    protected $_items = null;
    protected $_cats = array();

    public function getItems()
    {
        if ($this->_items !== null) {
            return $this->_items;
        }

        $user = JemFactory::getUser();

        if (!$user->get('id')) {
            $this->_items = array();
            return $this->_items;
        }

        $this->_items = $this->_getList($this->_buildQuery());
        $levels = $user->getAuthorisedViewLevels();

        foreach ($this->_items as $i => $item) {
            JemHelper::applyAssociatedArticleEventContentToEvents(array($item), $levels);

            $item->categories = $this->getCategories($item->eventid);

            if (empty($item->categories)) {
                unset($this->_items[$i]);
            }
        }

        return array_values($this->_items);
    }

    protected function _buildQuery()
    {
        $where = $this->_buildWhere();
        $userId = (int) JemFactory::getUser()->get('id');

        return 'SELECT DISTINCT a.id AS eventid, a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.alias, a.created, a.created_by, a.locid, a.published,'
            . ' a.recurrence_type, a.recurrence_first_id, a.recurrence_byday, a.recurrence_counter, a.recurrence_limit, a.recurrence_limit_date, a.recurrence_number,'
            . ' a.access, a.attribs, a.article_id, a.datimage, a.featured, a.registra, a.waitinglist, a.requestanswer, a.seriesbooking, a.singlebooking,'
            . ' a.introtext, a.fulltext,'
            . ' a.maxplaces, a.maxbookeduser, a.minbookeduser, a.reservedplaces,'
            . ' l.id AS l_id, l.venue, l.street, l.postalCode, l.city, l.state, l.country, l.url, l.published AS l_published,'
            . ' l.alias AS l_alias, l.latitude, l.longitude,'
            . ' CASE WHEN a.created_by = ' . $userId . ' THEN 1 ELSE 0 END AS is_owner,'
            . ' CASE WHEN r.uid IS NULL THEN 0 ELSE 1 END AS is_registered,'
            . ' r.status, r.waiting, r.places, r.comment,'
            . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
            . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
            . ' FROM #__jem_events AS a'
            . ' LEFT JOIN #__jem_register AS r ON r.event = a.id AND r.uid = ' . $userId
            . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
            . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
            . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
            . $where
            . ' GROUP BY a.id'
            . ' ORDER BY a.dates ASC, a.times ASC, a.created ASC';
    }

    protected function _buildWhere()
    {
        $app = Factory::getApplication();
        $params = $app->getParams();
        $task = $app->input->getCmd('task', '');
        $user = JemFactory::getUser();
        $levels = $user->getAuthorisedViewLevels();
        $userId = (int) $user->get('id');

        $where = array();
        $where[] = ($task === 'archive') ? 'a.published = 2' : 'a.published IN (0,1)';
        $where[] = 'c.published = 1';
        $where[] = 'a.access IN (' . implode(',', $levels) . ')';
        $where[] = 'c.access IN (' . implode(',', $levels) . ')';
        if ($params->get('mytimeline_include_registered', 0)) {
            $where[] = '(a.created_by = ' . $userId . ' OR r.uid = ' . $userId . ')';
        } else {
            $where[] = 'a.created_by = ' . $userId;
        }

        if ($params->get('filtermytimeline', 1)) {
            $purposeWhere = $this->buildPurposeWhere($this->getTimelinePurposes(), (int) $params->get('mytimelinepast', 1));

            if ($purposeWhere !== '') {
                $where[] = $purposeWhere;
            }
        }

        return count($where) ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    protected function getTimelinePurposes()
    {
        $raw = Factory::getApplication()->getParams()->get('mytimeline_purposes', array('personal_calendar', 'planning'));

        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }

        if (!is_array($raw) || empty($raw)) {
            $raw = array('personal_calendar', 'planning');
        }

        $allowed = array('personal_calendar', 'activity_history', 'planning', 'event_diary', 'all');
        $purposes = array_values(array_intersect($allowed, $raw));

        return empty($purposes) ? array('personal_calendar', 'planning') : $purposes;
    }

    protected function buildPurposeWhere(array $purposes, $pastDays)
    {
        if (in_array('all', $purposes, true)) {
            return '';
        }

        $pastDays = max(0, (int) $pastDays);
        $dateEnd = 'IF(a.enddates IS NOT NULL, a.enddates, a.dates)';
        $recentOrFuture = '(a.dates IS NULL OR DATE_SUB(NOW(), INTERVAL ' . $pastDays . ' DAY) < ' . $dateEnd . ')';
        $past = '(a.dates IS NOT NULL AND ' . $dateEnd . ' < CURDATE())';
        $upcoming = '(a.dates IS NULL OR ' . $dateEnd . ' >= CURDATE())';
        $conditions = array();

        if (in_array('personal_calendar', $purposes, true)) {
            $conditions[] = $recentOrFuture;
        }

        if (in_array('planning', $purposes, true)) {
            $conditions[] = $upcoming;
        }

        if (in_array('activity_history', $purposes, true) || in_array('event_diary', $purposes, true)) {
            $conditions[] = $past;
        }

        return empty($conditions) ? '' : '(' . implode(' OR ', array_unique($conditions)) . ')';
    }

    public function getCategories($id)
    {
        if (isset($this->_cats[$id])) {
            return $this->_cats[$id];
        }

        $levels = JemFactory::getUser()->getAuthorisedViewLevels();

        $query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
            . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
            . ' FROM #__jem_categories AS c'
            . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
            . ' WHERE rel.itemid = ' . (int) $id
            . ' AND c.published = 1'
            . ' AND c.access IN (' . implode(',', $levels) . ')';

        $this->_db->setQuery($query);
        $this->_cats[$id] = $this->_db->loadObjectList();

        return $this->_cats[$id];
    }
}
