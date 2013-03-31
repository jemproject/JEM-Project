<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENCE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$mainframe->registerEvent( 'onSearch', 'plgSearchEventlist' );
$mainframe->registerEvent( 'onSearchAreas', 'plgSearchEventlistAreas' );

//Load the Plugin language file out of the administration
JPlugin::loadLanguage( 'plg_search_eventlist', JPATH_ADMINISTRATOR);

/**
 * @return array An array of search areas
 */
function &plgSearchEventlistAreas() {
	static $areas = array(
	'elevents' => 'EVENTS',
	'elvenues' => 'VENUES',
	'elcategories' => 'EVENTLIST CATEGORIES'
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
function plgSearchEventlist( $text, $phrase='', $ordering='', $areas=null )
{
	$db		=& JFactory::getDBO();
	$user	=& JFactory::getUser();

	require_once(JPATH_SITE.DS.'components'.DS.'com_eventlist'.DS.'helpers'.DS.'route.php');

	if (is_array( $areas )) {
		if (!array_intersect( $areas, array_keys( plgSearchEventlistAreas() ) )) {
			return array();
		}
	} else {
		$areas = array_keys( plgSearchEventlistAreas() );
	}

	// load plugin params info
	$plugin =& JPluginHelper::getPlugin('search', 'eventlist');
	$pluginParams = new JParameter( $plugin->params );

	$limit = $pluginParams->def( 'search_limit', 50 );

	$text = trim( $text );
	if ( $text == '' ) {
		return array();
	}

	$searchEventList = $db->Quote(JText::_( 'EVENTLIST' ));

	$rows = array();

	if(in_array('elevents', $areas)) {

		switch ($phrase) {
			case 'exact':
				$text 		= $db->Quote( '%'.$db->getEscaped( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'LOWER(a.title) LIKE '.$text;
				$wheres2[] 	= 'LOWER(a.datdescription) LIKE '.$text;
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
					$word 		= $db->Quote( '%'.$db->getEscaped( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'LOWER(a.title) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.datdescription) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.meta_keywords) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.meta_description) LIKE '.$word;
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		switch ( $ordering ) {
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

		$query = 'SELECT a.id, a.title AS title,'
		. ' a.datdescription AS text,'
		. ' a.dates AS created,'
		. ' "2" AS browsernav,'
		. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug, '
		. ' CONCAT_WS( " / ", '. $searchEventList .', c.catname, a.title ) AS section'
		. ' FROM #__eventlist_events AS a'
		. ' INNER JOIN #__eventlist_categories AS c'
		. ' LEFT JOIN #__eventlist_cats_event_relations AS rel ON rel.catid = c.id'
		. ' WHERE ( '.$where.' )'
		. ' AND rel.itemid = a.id'
		. ' AND a.published = 1'
		. ' AND c.published = 1'
		. ' AND c.access <= '.(int) $user->get('aid')
		. ' GROUP BY a.id '
		. ' ORDER BY '. $order
		;
		$db->setQuery( $query, 0, $limit );
		$list = $db->loadObjectList();

		foreach((array) $list as $key => $row) {
			$list[$key]->href = EventListHelperRoute::getRoute($row->slug);
		}

		$rows[] = $list;
	}

	if(in_array('elvenues', $areas)) {

		switch ($phrase) {
			case 'exact':
				$text 		= $db->Quote( '%'.$db->getEscaped( $text, true ).'%', false );
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
					$word 		= $db->Quote( '%'.$db->getEscaped( $word, true ).'%', false );
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
		. ' CONCAT_WS( " / ", '. $searchEventList .', venue )AS section'
		. ' FROM #__eventlist_venues'
		. ' WHERE ( '.$where.')'
		. ' AND published = 1'
		. ' ORDER BY '. $order
		;
		$db->setQuery( $query, 0, $limit );
		$list2 = $db->loadObjectList();

		foreach((array) $list2 as $key => $row) {
			$list2[$key]->href = EventListHelperRoute::getRoute($row->slug, 'venueevents');
		}

		$rows[] = $list2;
	}

	if(in_array('elcategories', $areas)) {

		switch ($phrase) {
			case 'exact':
				$text 		= $db->Quote( '%'.$db->getEscaped( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'LOWER(catname) LIKE '.$text;
				$wheres2[] 	= 'LOWER(catdescription) LIKE '.$text;
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
					$word 		= $db->Quote( '%'.$db->getEscaped( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'LOWER(catname) LIKE '.$word;
					$wheres2[] 	= 'LOWER(catdescription) LIKE '.$word;
					$wheres2[] 	= 'LOWER(meta_keywords) LIKE '.$word;
					$wheres2[] 	= 'LOWER(meta_description) LIKE '.$word;
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		$query = 'SELECT catname AS title,'
		. ' catdescription AS text,'
		. ' "" AS created,'
		. ' "2" AS browsernav,'
		. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug, '
		. ' CONCAT_WS( " / ", '. $searchEventList .', catname )AS section'
		. ' FROM #__eventlist_categories'
		. ' WHERE ( '.$where.' )'
		. ' AND published = 1'
		. ' AND access <= '.(int) $user->get('aid')
		. ' ORDER BY catname'
		;
		$db->setQuery( $query, 0, $limit );
		$list3 = $db->loadObjectList();

		foreach((array) $list3 as $key => $row) {
			$list3[$key]->href = EventListHelperRoute::getRoute($row->slug, 'categoryevents');
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
?>