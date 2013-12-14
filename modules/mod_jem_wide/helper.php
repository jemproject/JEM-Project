<?php
/**
 * @version 1.9.5
 * @package JEM
 * @subpackage JEM Wide Module
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE.'/components/com_jem/helpers/route.php';


/**
 * JEM Modulewide helper
 *
 * @package Joomla
 * @subpackage JEM Wide Module
 *
 */
abstract class modJEMwideHelper
{

	/**
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	public static function getList(&$params)
	{
		$db = JFactory::getDBO();
		$user = JFactory::getUser();
		$gid = JEMHelper::getGID($user);

		//all upcoming events//all upcoming events
		if ($params->get('type') == 1) {
			$where = " WHERE (TIMEDIFF(CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00')),NOW()) > 1";
			$where .= " OR (a.enddates AND TIMEDIFF(CONCAT(a.enddates,' ',IFNULL(a.times,'00:00:00')),NOW())) > 1) ";
			$where .= ' AND a.published = 1';
			$order = " ORDER BY a.dates, a.times";
		}

		//archived events only
		elseif ($params->get('type') == 2) {
			$where = ' WHERE a.published = 2';
			$order = ' ORDER BY a.dates DESC, a.times DESC';
		}

		//currently running events only
		elseif ($params->get('type') == 3) {
			$where = ' WHERE a.published = 1';
			$where .= ' AND (a.dates = CURDATE()';
			$where .= ' OR (a.enddates >= CURDATE() AND a.dates <= CURDATE()))';
			$order = ' ORDER BY a.dates, a.times';
		}

		//clean parameter data
		$catid = trim($params->get('catid'));
		$venid = trim($params->get('venid'));
		$state = JString::strtolower(trim($params->get('stateloc')));

		//Build category selection query statement
		if ($catid) {
			$ids = explode(',', $catid);
			JArrayHelper::toInteger($ids);
			$categories = ' AND (c.id=' . implode(' OR c.id=', $ids) . ')';
		}

		//Build venue selection query statement
		if ($venid) {
			$ids = explode(',', $venid);
			JArrayHelper::toInteger($ids);
			$venues = ' AND (l.id=' . implode(' OR l.id=', $ids) . ')';
		}

		//Build state selection query statement
		if ($state) {
			$rawstate = explode(',', $state);

			foreach ($rawstate as $val) {
				if ($val) {
					$states[] = '"'.trim($db->escape($val)).'"';
				}
			}

			JArrayHelper::toString($states);
			$stat = ' AND (LOWER(l.state)='.implode(' OR LOWER(l.state)=',$states).')';
		}

		//perform select query
		$query = 'SELECT a.title, a.dates, a.enddates, a.times, a.endtimes, a.datimage, a.fulltext, l.venue, l.state, l.locimage, l.city, l.locdescription, c.catname,'
				.' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				.' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', l.id, l.alias) ELSE l.id END as venueslug,'
				.' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
				.' FROM #__jem_events AS a'
				.' INNER JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				.' INNER JOIN #__jem_categories AS c ON c.id = rel.catid'
				.' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. $where
				.' AND c.access <= '.$gid
				.' AND c.published = 1'
				.($catid ? $categories : '')
				.($venid ? $venues : '')
				.($state ? $stat : '')
				. $order
				.' LIMIT '.(int)$params->get('count', '2')
				;

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if ($params->get('use_modal', 0)) {
			JHtml::_('behavior.modal', 'a.flyermodal');
		}

		//Loop through the result rows and prepare data
		$i		= 0;
		$lists	= array();
		foreach ((array) $rows as $row)
		{
			//create thumbnails if needed and receive imagedata
			if ($row->datimage) {
				$dimage = JEMImage::flyercreator($row->datimage, 'event');
			} else {
				$dimage = null;
			}
			if ($row->locimage) {
				$limage = JEMImage::flyercreator($row->locimage, 'venue');
			} else {
				$limage = null;
			}

			//cut titel
			$length = strlen(htmlspecialchars($row->title));

			if ($length > $params->get('cuttitle', '25')) {
				$row->title = substr($row->title, 0, $params->get('cuttitle', '18'));
				$row->title = $row->title.'...';
			}

			$lists[$i] = new stdClass();
			$lists[$i]->title			= htmlspecialchars($row->title, ENT_COMPAT, 'UTF-8');
			$lists[$i]->venue			= htmlspecialchars($row->venue, ENT_COMPAT, 'UTF-8');
			$lists[$i]->catname			= htmlspecialchars($row->catname, ENT_COMPAT, 'UTF-8');
			$lists[$i]->state			= htmlspecialchars($row->state, ENT_COMPAT, 'UTF-8');
			$lists[$i]->eventlink		= $params->get('linkevent', 1) ? JRoute::_(JEMHelperRoute::getEventRoute($row->slug)) : '';
			$lists[$i]->venuelink		= $params->get('linkvenue', 1) ? JRoute::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : '';
			$lists[$i]->categorylink	= $params->get('linkcategory', 1) ? JRoute::_(JEMHelperRoute::getCategoryRoute($row->categoryslug)) : '';
			$lists[$i]->date 			= modJEMwideHelper::_format_date($row, $params);
			$lists[$i]->time 			= $row->times ? modJEMwideHelper::_format_time($row->dates, $row->times, $params) : '' ;

			if ($dimage == null) {
				$lists[$i]->eventimage		= JURI::base(true).'/media/system/images/blank.png';
				$lists[$i]->eventimageorig	= JURI::base(true).'/media/system/images/blank.png';
			} else {
				$lists[$i]->eventimage		= JURI::base(true).'/'.$dimage['thumb'];
				$lists[$i]->eventimageorig	= JURI::base(true).'/'.$dimage['original'];
			}

			if ($limage == null) {
				$lists[$i]->venueimage		= JURI::base(true).'/media/system/images/blank.png';
				$lists[$i]->venueimageorig	= JURI::base(true).'/media/system/images/blank.png';
			} else {
				$lists[$i]->venueimage		= JURI::base(true).'/'.$limage['thumb'];
				$lists[$i]->venueimageorig	= JURI::base(true).'/'.$limage['original'];
			}
			$lists[$i]->eventdescription= strip_tags($row->fulltext);
			$lists[$i]->venuedescription= strip_tags($row->locdescription);
			$i++;
		}

		return $lists;
	}

	/**
	 * Method to format date information
	 *
	 * @access public
	 * @return string
	 */
	protected static function _format_date($row, &$params)
	{
		//Get needed timestamps and format
		$yesterday_stamp	= mktime(0, 0, 0, date("m") , date("d")-1, date("Y"));
		$yesterday 			= strftime("%Y-%m-%d", $yesterday_stamp);
		$today_stamp		= mktime(0, 0, 0, date("m") , date("d"), date("Y"));
		$today 				= date('Y-m-d');
		$tomorrow_stamp 	= mktime(0, 0, 0, date("m") , date("d")+1, date("Y"));
		$tomorrow 			= strftime("%Y-%m-%d", $tomorrow_stamp);

		$dates_stamp		= $row->dates ? strtotime($row->dates) : null;
		$enddates_stamp		= $row->enddates ? strtotime($row->enddates) : null;

		//if datemethod show day difference
		if($params->get('datemethod', 1) == 2) {
			//check if today or tomorrow
			if($row->dates == $today) {
				$result = JText::_('MOD_JEM_WIDE_TODAY');
			} elseif($row->dates == $tomorrow) {
				$result = JText::_('MOD_JEM_WIDE_TOMORROW');
			} elseif($row->dates == $yesterday) {
				$result = JText::_('MOD_JEM_WIDE_YESTERDAY');

			//This one isn't very different from the DAYS AGO output but it seems
			//adequate to use a different language string here.
			//
			//the event has an enddate and it's earlier than yesterday
			} elseif($row->enddates && $enddates_stamp < $yesterday_stamp) {
				$days = round(($today_stamp - $enddates_stamp) / 86400);
				$result = JText::sprintf('MOD_JEM_WIDE_ENDED_DAYS_AGO', $days);

			//the event has an enddate and it's later than today but the startdate is earlier than today
			//means a currently running event
			} elseif($row->dates && $row->enddates && $enddates_stamp > $today_stamp && $dates_stamp < $today_stamp) {
				$days = round(($today_stamp - $dates_stamp) / 86400);
				$result = JText::sprintf('MOD_JEM_WIDE_STARTED_DAYS_AGO', $days);

			//the events date is earlier than yesterday
			} elseif($row->dates && $dates_stamp < $yesterday_stamp) {
				$days = round(($today_stamp - $dates_stamp) / 86400);
				$result = JText::sprintf('MOD_JEM_WIDE_DAYS_AGO', $days);

			//the events date is later than tomorrow
			} elseif($row->dates && $dates_stamp > $tomorrow_stamp) {
				$days = round(($dates_stamp - $today_stamp) / 86400);
				$result = JText::sprintf('MOD_JEM_WIDE_DAYS_AHEAD', $days);
			}
		} else {
			//single day event
			$date = JEMOutput::formatdate($row->dates, $params->get('formatdate', '%d.%m.%Y') );
			$result = JText::sprintf('MOD_JEM_WIDE_ON_DATE', $date);

			//Upcoming multidayevent (From 16.10.2008 Until 18.08.2008)
			if($dates_stamp > $tomorrow_stamp && $enddates_stamp) {
				$startdate = JEMOutput::formatdate($row->dates, $params->get('formatdate', '%d.%m.%Y') );
				$enddate = JEMOutput::formatdate($row->enddates, $params->get('formatdate', '%d.%m.%Y') );
				$result = JText::sprintf('MOD_JEM_WIDE_FROM_UNTIL', $startdate, $enddate);
			}

			//current multidayevent (Until 18.08.2008)
			if($row->enddates && $enddates_stamp > $today_stamp && $dates_stamp < $today_stamp) {
				//format date
				$result = JEMOutput::formatdate($row->enddates, $params->get('formatdate', '%d.%m.%Y') );
				$result = JText::sprintf('MOD_JEM_WIDE_UNTIL', $result);
			}
		}

		return $result;
	}
	/**
	 * Method to format time information
	 *
	 * @access public
	 * @return string
	 */
	protected static function _format_time($date, $time, &$params)
	{
		$time = strftime($params->get('formattime', '%H:%M'), strtotime($date.' '.$time));

		return $time;
	}
}