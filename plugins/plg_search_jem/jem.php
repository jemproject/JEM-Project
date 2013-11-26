<?php
/**
 * @version 1.9.5
 * @package JEM
 * @subpackage JEM Search Plugin
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');




class plgSearchJEM extends JPlugin
{




//Load the Plugin language file out of the administration
// JPlugin::loadLanguage( 'plg_search_jem', JPATH_ADMINISTRATOR);




function __construct(& $subject, $config)
    {
            parent::__construct($subject, $config);
            JPlugin::loadLanguage( 'plg_search_jem', JPATH_ADMINISTRATOR);
    }
/**
 * @return array An array of search areas
 */
function onContentSearchAreas()
	{
		static $areas = array(	'elevents' => 'PLG_JEM_SEARCH_EVENTS',
								'elvenues' => 'PLG_JEM_SEARCH_VENUES',
								'elcategories' => 'PLG_JEM_SEARCH_JEM_CATEGORIES'
							  );

			return $areas;
	}

/**
 * Categories Search method
 *
 * The sql must return the following fields that are
 * used in a common display routine: href, title, section, created, text,
 * browsernav
 * @param string Target search string
 * @param string mathcing option, exact|any|all
 * @param string ordering option, newest|oldest|popular|alpha|category
 * @param mixed An array if restricted to areas, null if search all
 */
function onContentSearch( $text, $phrase='', $ordering='', $areas=null )
{
	$db		= JFactory::getDBO();
	$user	= JFactory::getUser();

	require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');

	if (is_array( $areas )) {
		if (!array_intersect( $areas, array_keys( $this->onContentSearchAreas() ) )) {
			return array();
		}
	} else {
		$areas = array_keys( $this->onContentSearchAreas() );
	}

	// load plugin params info
	$plugin = JPluginHelper::getPlugin('search', 'jem');
	$pluginParams = new JRegistry( $plugin->params );

	$limit = $pluginParams->def( 'search_limit', 50 );

	$text = trim( $text );
	if ( $text == '' ) {
		return array();
	}

	$searchJEM = $db->Quote(JText::_( 'PLG_JEM_SEARCH_JEM' ));

	$rows = array();

	if(in_array('elevents', $areas)) {

		switch ($phrase) {
			case 'exact':
				$text 		= $db->Quote( '%'.$db->escape( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'LOWER(a.title) LIKE '.$text;
				$wheres2[] 	= 'LOWER(a.fulltext) LIKE '.$text;
				$wheres2[] 	= 'LOWER(a.meta_keywords) LIKE '.$text;
				$wheres2[] 	= 'LOWER(a.meta_description) LIKE '.$text;
				$where 		= '(' . implode( ') OR (', $wheres2 ) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words = explode( ' ', $text );
				$wheres = array();
				foreach ($words as $word) {
					$word 		= $db->Quote( '%'.$db->escape( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'LOWER(a.title) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.fulltext) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.meta_keywords) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.meta_description) LIKE '.$word;
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		switch ($ordering) {
			case 'oldest':
				$order = 'a.dates, a.times ASC';
				break;

			case 'alpha':
				$order = 'a.title ASC';
				break;

			case 'category':
				$order = 'c.catname ASC, a.title ASC';
				break;

			case 'newest':
			default:
				$order = 'a.dates, a.times DESC';
		}



		if (JFactory::getUser()->authorise('core.manage')) {
           $gid = (int) 3;
            } else {
                if($user->get('id')) {
                   $gid = (int) 2;
                } else {
                   $gid = (int) 1;
                }
            }


		$query = 'SELECT a.id, a.title AS title,'
		. ' a.fulltext AS text,'
		. ' a.dates AS created,'
		. ' "2" AS browsernav,'
		. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug, '
		. ' CONCAT_WS( " / ", '. $searchJEM .', c.catname, a.title ) AS section'
		. ' FROM #__jem_events AS a'
		. ' INNER JOIN #__jem_categories AS c'
		. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
		. ' WHERE ( '.$where.' )'
		. ' AND rel.itemid = a.id'
		. ' AND a.published = 1'
		. ' AND c.published = 1'
		. ' AND c.access <= '.(int) $gid
		. ' GROUP BY a.id '
		. ' ORDER BY '. $order
		;
		$db->setQuery( $query, 0, $limit );
		$list = $db->loadObjectList();

		foreach((array) $list as $key => $row) {
			$list[$key]->href = JEMHelperRoute::getEventRoute($row->slug);
		}

		$rows[] = $list;
	}

	if(in_array('elvenues', $areas)) {

		switch ($phrase) {
			case 'exact':
				$text 		= $db->Quote( '%'.$db->escape( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'LOWER(venue) LIKE '.$text;
				$wheres2[] 	= 'LOWER(locdescription) LIKE '.$text;
				$wheres2[] 	= 'LOWER(city) LIKE '.$text;
				$wheres2[] 	= 'LOWER(meta_keywords) LIKE '.$text;
				$wheres2[] 	= 'LOWER(meta_description) LIKE '.$text;
				$where 		= '(' . implode( ') OR (', $wheres2 ) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words = explode( ' ', $text );
				$wheres = array();
				foreach ($words as $word) {
					$word 		= $db->Quote( '%'.$db->escape( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'LOWER(venue) LIKE '.$word;
					$wheres2[] 	= 'LOWER(locdescription) LIKE '.$word;
					$wheres2[] 	= 'LOWER(city) LIKE '.$word;
					$wheres2[] 	= 'LOWER(meta_keywords) LIKE '.$word;
					$wheres2[] 	= 'LOWER(meta_description) LIKE '.$word;
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		switch ( $ordering ) {
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
		. ' CONCAT_WS( " / ", '. $searchJEM .', venue )AS section'
		. ' FROM #__jem_venues'
		. ' WHERE ( '.$where.')'
		. ' AND published = 1'
		. ' ORDER BY '. $order
		;
		$db->setQuery( $query, 0, $limit );
		$list2 = $db->loadObjectList();

		foreach((array) $list2 as $key => $row) {
			$list2[$key]->href = JEMHelperRoute::getVenueRoute($row->slug);
		}

		$rows[] = $list2;
	}

	if(in_array('elcategories', $areas)) {

		switch ($phrase) {
			case 'exact':
				$text 		= $db->Quote( '%'.$db->escape( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'LOWER(catname) LIKE '.$text;
				$wheres2[] 	= 'LOWER(description) LIKE '.$text;
				$wheres2[] 	= 'LOWER(meta_keywords) LIKE '.$text;
				$wheres2[] 	= 'LOWER(meta_description) LIKE '.$text;
				$where 		= '(' . implode( ') OR (', $wheres2 ) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words = explode( ' ', $text );
				$wheres = array();
				foreach ($words as $word) {
					$word 		= $db->Quote( '%'.$db->escape( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'LOWER(catname) LIKE '.$word;
					$wheres2[] 	= 'LOWER(description) LIKE '.$word;
					$wheres2[] 	= 'LOWER(meta_keywords) LIKE '.$word;
					$wheres2[] 	= 'LOWER(meta_description) LIKE '.$word;
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		$query = 'SELECT catname AS title,'
		. ' description AS text,'
		. ' "" AS created,'
		. ' "2" AS browsernav,'
		. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug, '
		. ' CONCAT_WS( " / ", '. $searchJEM .', catname )AS section'
		. ' FROM #__jem_categories'
		. ' WHERE ( '.$where.' )'
		. ' AND published = 1'
		. ' AND access <= '.(int) $user->get('aid')
		. ' ORDER BY catname'
		;
		$db->setQuery( $query, 0, $limit );
		$list3 = $db->loadObjectList();

		foreach((array) $list3 as $key => $row) {
			$list3[$key]->href = JEMHelperRoute::getCategoryRoute($row->slug);
		}

		$rows[] = $list3;
	}

	$count = count( $rows );
	if ( $count > 1 ) {
		switch ( $count ) {
			case 2:
				$results = array_merge( (array) $rows[0], (array) $rows[1] );
				break;

			case 3:
				$results = array_merge( (array) $rows[0], (array) $rows[1], (array) $rows[2] );
				break;

			case 4:
			default:
				$results = array_merge( (array) $rows[0], (array) $rows[1], (array) $rows[2], (array) $rows[3] );
				break;
		}

		return $results;
	} else if ( $count == 1 ) {
		return $rows[0];
	}
}
}
?>