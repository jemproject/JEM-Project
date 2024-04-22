<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @subpackage JEM Search Plugin
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;

jimport('joomla.html.parameter');


class plgSearchJEM extends CMSPlugin
{
    protected static $_areas = array(
        'jemevents'     => 'PLG_JEM_SEARCH_EVENTS',
        'jemvenues'     => 'PLG_JEM_SEARCH_VENUES',
        'jemcategories' => 'PLG_JEM_SEARCH_JEM_CATEGORIES'
    );

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        Plugin::loadLanguage('plg_search_jem', JPATH_ADMINISTRATOR);
    }


    /**
     * @return array An array of search areas
     */
    function onContentSearchAreas()
    {
        include_once(JPATH_SITE . '/components/com_jem/factory.php');
        if (!class_exists('JemFactory')) {
            return array(); // we need jem please
        }

        return self::$_areas;
    }

    /**
     * Categories Search method
     *
     * The sql must return the following fields that are
     * used in a common display routine: href, title, section, created, text,
     * browsernav
     *
     * @param   string Target search string
     * @param   string mathcing option, exact|any|all
     * @param   string ordering option, newest|oldest|popular|alpha|category
     * @param   mixed An array if restricted to areas, null if search all
     */
    function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
    {
        include_once(JPATH_SITE . '/components/com_jem/factory.php');
        if (!class_exists('JemFactory')) {
            return array(); // we need jem please
        }

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $app    = Factory::getApplication();
        $user   = JemFactory::getUser();
        $groups = implode(',', $user->getAuthorisedViewLevels());
        $tag    = Factory::getApplication()->getLanguage()->getTag();

        require_once(JPATH_SITE . '/components/com_jem/helpers/route.php');

        if (is_array($areas)) {
            if (!array_intersect($areas, array_keys(self::$_areas))) {
                return array();
            }
        } else {
            $areas = array_keys(self::$_areas);
        }

        // load plugin params info
        $plugin       = PluginHelper::getPlugin('search', 'jem');
        $pluginParams = new JRegistry($plugin->params);

        $limit = $pluginParams->def('search_limit', 50);

        $text = trim($text);
        if ($text == '') {
            return array();
        }

        $searchJEM = $db->Quote(Text::_('PLG_JEM_SEARCH_JEM'));

        $rows  = array();
        $query = $db->getQuery(true);

        if (in_array('jemevents', $areas) && $limit > 0) {
            $areaName = Text::_(self::$_areas['jemevents']);

            switch ($phrase) {
                case 'exact':
                    $text_q    = $db->Quote('%' . $db->escape($text, true) . '%', false);
                    $wheres2   = array();
                    $wheres2[] = 'LOWER(a.title) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(a.introtext) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(a.fulltext) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(a.meta_keywords) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(a.meta_description) LIKE ' . $text_q;
                    $where     = '(' . implode(') OR (', $wheres2) . ')';
                    break;

                case 'all':
                case 'any':
                default:
                    $words  = explode(' ', $text);
                    $wheres = array();
                    foreach ($words as $word) {
                        $word      = $db->Quote('%' . $db->escape($word, true) . '%', false);
                        $wheres2   = array();
                        $wheres2[] = 'LOWER(a.title) LIKE ' . $word;
                        $wheres2[] = 'LOWER(a.introtext) LIKE ' . $word;
                        $wheres2[] = 'LOWER(a.fulltext) LIKE ' . $word;
                        $wheres2[] = 'LOWER(a.meta_keywords) LIKE ' . $word;
                        $wheres2[] = 'LOWER(a.meta_description) LIKE ' . $word;
                        $wheres[]  = implode(' OR ', $wheres2);
                    }
                    $where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
                    break;
            }

            switch ($ordering) {
                case 'oldest':
                    $order = 'a.dates ASC, a.times ASC';
                    break;

                case 'alpha':
                    $order = 'a.title ASC';
                    break;

                case 'category':
                    $order = 'c.catname ASC, a.title ASC';
                    break;

                case 'newest':
                default:
                    $order = 'a.dates DESC, a.times DESC';
            }

            $query->clear();
            //sqlsrv changes
            $case_when = ' CASE WHEN ';
            $case_when .= $query->charLength('a.alias');
            $case_when .= ' THEN ';
            $a_id      = $query->castAsChar('a.id');
            $case_when .= $query->concatenate(array($a_id, 'a.alias'), ':');
            $case_when .= ' ELSE ';
            $case_when .= $a_id . ' END as slug';

            $case_when1 = ' CASE WHEN ';
            $case_when1 .= $query->charLength('c.alias');
            $case_when1 .= ' THEN ';
            $c_id       = $query->castAsChar('c.id');
            $case_when1 .= $query->concatenate(array($c_id, 'c.alias'), ':');
            $case_when1 .= ' ELSE ';
            $case_when1 .= $c_id . ' END as catslug';

            $query->select('a.title AS title, a.meta_description, a.meta_keywords, a.created AS created');
            $query->select($query->concatenate(array('a.introtext', 'a.fulltext')) . ' AS text');
            $query->select($query->concatenate(array($db->quote($areaName), 'c.catname'), ' / ') . ' AS section');
            $query->select($case_when . ',' . $case_when1 . ', ' . '\'2\' AS browsernav');
            $query->select('rel.catid');

            $query->from('#__jem_events AS a');
            $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
            $query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');
            $query->where(
                '(' . $where . ')' . ' AND a.published=1 AND c.published = 1 AND a.access IN (' . $groups . ') '
                . 'AND c.access IN (' . $groups . ') '
            );
            $query->group('a.id');
            $query->order($order);

            $db->setQuery($query, 0, $limit);
            $list  = $db->loadObjectList();
            $limit -= count($list);

            if (isset($list)) {
                foreach ($list as $key => $row) {
                    $list[$key]->href = JEMHelperRoute::getEventRoute($row->slug);

                    // todo: list ALL accessable categories
                    // todo: show date/time somewhere because this is very useful information
                }
            }

            $rows[] = $list;
        }

        if (in_array('jemvenues', $areas) && $limit > 0) {
            $areaName = Text::_(self::$_areas['jemvenues']);

            switch ($phrase) {
                case 'exact':
                    $text_q    = $db->Quote('%' . $db->escape($text, true) . '%', false);
                    $wheres2   = array();
                    $wheres2[] = 'LOWER(venue) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(locdescription) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(city) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(meta_keywords) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(meta_description) LIKE ' . $text_q;
                    $where     = '(' . implode(') OR (', $wheres2) . ')';
                    break;

                case 'all':
                case 'any':
                default:
                    $words  = explode(' ', $text);
                    $wheres = array();
                    foreach ($words as $word) {
                        $word      = $db->Quote('%' . $db->escape($word, true) . '%', false);
                        $wheres2   = array();
                        $wheres2[] = 'LOWER(venue) LIKE ' . $word;
                        $wheres2[] = 'LOWER(locdescription) LIKE ' . $word;
                        $wheres2[] = 'LOWER(city) LIKE ' . $word;
                        $wheres2[] = 'LOWER(meta_keywords) LIKE ' . $word;
                        $wheres2[] = 'LOWER(meta_description) LIKE ' . $word;
                        $wheres[]  = implode(' OR ', $wheres2);
                    }
                    $where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
                    break;
            }

            switch ($ordering) {
                case 'oldest':
                    $order = 'created DESC';
                    break;

                case 'alpha':
                    $order = 'venue ASC';
                    break;

                case 'newest':
                    $order = 'created ASC';
                    break;

                default:
                    $order = 'venue ASC';
            }

            $query = 'SELECT venue AS title,'
                . ' locdescription AS text,'
                . ' created,'
                . ' "2" AS browsernav,'
                . ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug, '
                . ' ' . $db->quote($areaName) . ' AS section'
                . ' FROM #__jem_venues'
                . ' WHERE ( ' . $where . ')'
                . ' AND published = 1'
                . ' ORDER BY ' . $order;
            $db->setQuery($query, 0, $limit);
            $list2 = $db->loadObjectList();

            foreach ((array)$list2 as $key => $row) {
                $list2[$key]->href = JEMHelperRoute::getVenueRoute($row->slug);
            }

            $rows[] = $list2;
        }

        if (in_array('jemcategories', $areas) && $limit > 0) {
            $areaName = Text::_(self::$_areas['jemcategories']);

            switch ($phrase) {
                case 'exact':
                    $text_q    = $db->Quote('%' . $db->escape($text, true) . '%', false);
                    $wheres2   = array();
                    $wheres2[] = 'LOWER(catname) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(description) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(meta_keywords) LIKE ' . $text_q;
                    $wheres2[] = 'LOWER(meta_description) LIKE ' . $text_q;
                    $where     = '(' . implode(') OR (', $wheres2) . ')';
                    break;

                case 'all':
                case 'any':
                default:
                    $words  = explode(' ', $text);
                    $wheres = array();
                    foreach ($words as $word) {
                        $word      = $db->Quote('%' . $db->escape($word, true) . '%', false);
                        $wheres2   = array();
                        $wheres2[] = 'LOWER(catname) LIKE ' . $word;
                        $wheres2[] = 'LOWER(description) LIKE ' . $word;
                        $wheres2[] = 'LOWER(meta_keywords) LIKE ' . $word;
                        $wheres2[] = 'LOWER(meta_description) LIKE ' . $word;
                        $wheres[]  = implode(' OR ', $wheres2);
                    }
                    $where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
                    break;
            }

            $query = 'SELECT catname AS title,'
                . ' description AS text,'
                . ' "" AS created,'
                . ' "2" AS browsernav,'
                . ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug, '
                . ' ' . $db->quote($areaName) . ' AS section'
                . ' FROM #__jem_categories'
                . ' WHERE ( ' . $where . ' )'
                . ' AND published = 1'
                . ' AND access IN (' . $groups . ') '
                . ' ORDER BY catname';
            $db->setQuery($query, 0, $limit);
            $list3 = $db->loadObjectList();

            foreach ((array)$list3 as $key => $row) {
                $list3[$key]->href = JEMHelperRoute::getCategoryRoute($row->slug);
            }

            $rows[] = $list3;
        }

        $count = count($rows);
        if ($count > 1) {
            switch ($count) {
                case 2:
                    $results = array_merge((array)$rows[0], (array)$rows[1]);
                    break;

                case 3:
                    $results = array_merge((array)$rows[0], (array)$rows[1], (array)$rows[2]);
                    break;

                case 4:
                default:
                    $results = array_merge((array)$rows[0], (array)$rows[1], (array)$rows[2], (array)$rows[3]);
                    break;
            }

            return $results;
        } else {
            if ($count == 1) {
                return $rows[0];
            }
        }
    }
}

?>
