<?php
/**
 * @version 1.9.1
 * @package JEM
 * @subpackage JEM Calendar Module
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2008 Toni Smillie www.qivva.com
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Original Eventlist calendar from Christoph Lukes www.schlu.net
 * PHP Calendar (version 2.3), written by Keith Devens
 * http://keithdevens.com/software/php_calendar
 * see example at http://keithdevens.com/weblog
 * License: http://keithdevens.com/software/license
 */

defined('_JEXEC') or die;

class modjemcalqhelper
{
	static function getdays ($greq_year, $greq_month, &$params)
	{
		$db		= JFactory::getDBO();
		$user	= JFactory::getUser();
		$gid 	= JEMHelper::getGID($user);

		$catid 				= trim( $params->get('catid') );
		$venid 				= trim( $params->get('venid') );
		$StraightToDetails	= $params->get( 'StraightToDetails', '1' );
		$DisplayCat			= $params->get( 'DisplayCat', '0' );
		$DisplayVenue		= $params->get( 'DisplayVenue', '0' );
		$ArchivedEvents		= $params->get( 'ArchivedEvents', '0' );
		$CurrentEvents		= $params->get( 'CurrentEvents', '1' );
		$FixItemID			= $params->get( 'FixItemID', '0' );

		//Get eventdates
		if ($catid)
		{
			$ids = explode( ',', $catid );
			JArrayHelper::toInteger( $ids );
			$categories = ' AND (c.id=' . implode( ' OR c.id=', $ids ) . ')';
		}
		if ($venid)
		{
			$ids = explode( ',', $venid );
			JArrayHelper::toInteger( $ids );
			$venues = ' AND (l.id=' . implode( ' OR l.id=', $ids ) . ')';
		}
		if ($CurrentEvents==1)
		{
			$wherestr = ' WHERE a.published = 1';
		}
		else
		{
			$wherestr = ' WHERE a.published = 0';
		}

		if ($ArchivedEvents==1)
		{
			$wherestr = $wherestr. ' OR a.published = -1';
		}

		$query = 'SELECT a.*, l.venue, DAYOFMONTH(a.dates) AS created_day, YEAR(a.dates) AS created_year, MONTH(a.dates) AS created_month,c.id AS mcatid,c.catname,l.id AS mlocid,l.venue,'
				.' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
				. ' FROM #__jem_events AS a'
				.' INNER JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				.' INNER JOIN #__jem_categories AS c ON c.id = rel.catid'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. $wherestr
				. ' AND c.access  <= '.$gid
				.($catid ? $categories : '')
				.($venid ? $venues : '')
				. ' GROUP BY a.id'
				;


		$db->setQuery( $query );
		$events = $db->loadObjectList();

		$days = array();
		foreach ( $events as $event )
		{
			// Cope with no end date set i.e. set it to same as start date
			if (($event->enddates == '0000-00-00') or (is_null($event->enddates)))
			{
				$eyear = $event->created_year;
				$emonth = $event->created_month;
				$eday = $event->created_day;

			}
			else
			{
				list($eyear, $emonth, $eday) = explode('-', $event->enddates);
			}
			// The two cases for roll over the year end with an event that goes across the year boundary.
			if ($greq_year < $eyear)
			{
				$emonth = $emonth + 12;
			}

			if ($event->created_year < $greq_year)
			{
				$event->created_month = $event->created_month - 12;
			}

			if (  ($greq_year >= $event->created_year) && ($greq_year <= $eyear)
				&& ($greq_month >= $event->created_month) && ($greq_month <= $emonth) )
			{
				// Set end day for current month

				if ($emonth > $greq_month)
				{
					$emonth = $greq_month;

					// $eday = cal_days_in_month(CAL_GREGORIAN, $greq_month,$greq_year);
					$eday = date('t', mktime(0,0,0, $greq_month, 1, $greq_year));
				}

				// Set start day for current month
				if ($event->created_month < $greq_month)
				{
					$event->created_month = $greq_month;
					$event->created_day = 1;
				}
				$stod = 1;
				for ($count = $event->created_day; $count <= $eday; $count++)
				{

					$uxdate = mktime(0,0,0,$greq_month,$count,$greq_year);
					$tdate = strftime('%Y%m%d',$uxdate);// Toni change Joomla 1.5
					$created_day = $count;

		//			$tt = $days[$count][1];

		//			if (strlen($tt) == 0)

					if (empty($days[$count][1]))
					{
						$title = htmlspecialchars($event->title);
						if ($DisplayCat ==1)
						{
							$title = $title . '&nbsp;(' . htmlspecialchars($event->catname) . ')';
						}
						if ($DisplayVenue == 1)
						{
							if (isset($event->venue))
							{
							$title = $title . '&nbsp;@' . htmlspecialchars($event->venue);
							}
						}
						$stodid = $event->id;
						$stod = 1;
					}
					else
					{
						$tt = $days[$count][1];
						$title = $tt . '+%+%+' . htmlspecialchars($event->title);
						if ($DisplayCat ==1)
						{
							$title = $title . '&nbsp;(' . htmlspecialchars($event->catname) . ')';
						}
						if ($DisplayVenue == 1)
						{
							if (isset($event->venue))
							{
							$title = $title . '&nbsp;@' . htmlspecialchars($event->venue);
							}
						}
						$stod = 0;
					}
					if (($StraightToDetails == 1) and ($stod==1))
					{
						if ($FixItemID == 0)
						{
							$link = JRoute::_( JEMHelperRoute::getRoute($event->slug) );
						}
						else
						{
							//Create the link - copied from Jroute
							$evlink = 'index.php?option=com_jem&view=event&id='. $event->slug.'&Itemid='.$FixItemID;
							$link = JRoute::_( $evlink );
						}

					}
					else
					{
						// @todo fix the getroute link
						if ($FixItemID == 0)
						{
						$link			= JEMHelperRoute::getRoute( $tdate, 'day') ;
						}
						else
						{
							//Create the link - copied from Jroute
							$evlink = 'index.php?option=com_jem&view=day&id='. $tdate.'&Itemid='.$FixItemID;
							$link = JRoute::_( $evlink );
						}
					}
				$days[$count] = array($link,$title);
				}
			}
		// End of Toni modification
		}
		return $days;
	} //End of function getdays
}

?>
