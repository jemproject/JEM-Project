<?php
/**
 * @version 2.0.2
 * @package JEM
 * @subpackage JEM Wide Module
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

/**
 * Module-Wide
 */
abstract class ModJemWideHelper
{

	/**
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	public static function getList(&$params)
	{
		mb_internal_encoding('UTF-8');

		$db		= JFactory::getDBO();
		$user	= JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();

		# Retrieve Eventslist model for the data
		$model = JModelLegacy::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));

		# Set params for the model
		# has to go before the getItems function
		$model->setState('params', $params);

		# filter published
		#  0: unpublished
		#  1: published
		#  2: archived
		# -2: trashed

		$type = $params->get('type');
		$offset_hourss = $params->get('offset_hours', 0);

		# all upcoming or unfinished events
		if (($type == 0) || ($type == 1)) {
			$offset_minutes = $offset_hourss * 60;

			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC','a.times ASC'));

			$cal_from = "((TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
			$cal_from .= ($type == 1) ? " OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates,a.dates),' ',IFNULL(a.endtimes,'23:59:59'))) > $offset_minutes)) " : ") ";
		}

		# archived events only
		elseif ($type == 2) {
			$model->setState('filter.published',2);
			$model->setState('filter.orderby',array('a.dates DESC','a.times DESC'));
			$cal_from = "";
		}

		# currently running events only (today + offset is inbetween start and end date of event)
		elseif ($type == 3) {
			$offset_days = (int)round($offset_hourss / 24);

			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC','a.times ASC'));

			$cal_from = " ((DATEDIFF(a.dates, CURDATE()) <= $offset_days) AND (DATEDIFF(IFNULL(a.enddates,a.dates), CURDATE()) >= $offset_days))";
		}

		$model->setState('filter.calendar_from',$cal_from);
		$model->setState('filter.groupby','a.id');

		# clean parameter data
		$catids = JemHelper::getValidIds($params->get('catid'));
		$venids = JemHelper::getValidIds($params->get('venid'));

		# filter category's
		if ($catids) {
			$model->setState('filter.category_id',$catids);
			$model->setState('filter.category_id.include',true);
		}

		# filter venue's
		if ($venids) {
			$model->setState('filter.venue_id',$venids);
			$model->setState('filter.venue_id.include',true);
		}

		# count
		$count = $params->get('count', '2');
		$model->setState('list.limit',$count);

		if ($params->get('use_modal', 0)) {
			JHtml::_('behavior.modal', 'a.flyermodal');
		}

		# Retrieve the available Events
		$events = $model->getItems();

		# define list-array
		# in here we collect the row information
		$lists	= array();
		$i = 0;


		#####################
		### DEFINE FOREACH ##
		#####################

		foreach ($events as $row)
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
			$length = mb_strlen($row->title);

			if ($length > $params->get('cuttitle', '25')) {
				$row->title = mb_substr($row->title, 0, $params->get('cuttitle', '18'));
				$row->title = $row->title.'...';
			}

			$lists[$i] = new stdClass();
			$lists[$i]->title			= htmlspecialchars($row->title, ENT_COMPAT, 'UTF-8');
			$lists[$i]->venue			= htmlspecialchars($row->venue, ENT_COMPAT, 'UTF-8');
			$lists[$i]->state			= htmlspecialchars($row->state, ENT_COMPAT, 'UTF-8');
			$lists[$i]->eventlink		= $params->get('linkevent', 1) ? JRoute::_(JEMHelperRoute::getEventRoute($row->slug)) : '';
			$lists[$i]->venuelink		= $params->get('linkvenue', 1) ? JRoute::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : '';
			list($lists[$i]->date,
			     $lists[$i]->time)		= ModJemWideHelper::_format_date_time($row, $params);

			# walk through categories assigned to an event
			$lists[$i]->catname			= implode(", ", JemOutput::getCategoryList($row->categories, $params->get('linkcategory', 1)));

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
	 * @return array(string, string) returns date and time strings as array
	 */
	protected static function _format_date_time($row, &$params)
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

		$date_format = $params->get('formatdate', 'D, j. F Y');
		$time_format = $params->get('formattime', '%H:%M');

		//if datemethod show day difference
		if($params->get('datemethod', 1) == 2) {
			//check if today or tomorrow
			if($row->dates == $today) {
				$date = JText::_('MOD_JEM_WIDE_TODAY');
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';
			} elseif($row->dates == $tomorrow) {
				$date = JText::_('MOD_JEM_WIDE_TOMORROW');
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';
			} elseif($row->dates == $yesterday) {
				$date = JText::_('MOD_JEM_WIDE_YESTERDAY');
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';

			//This one isn't very different from the DAYS AGO output but it seems
			//adequate to use a different language string here.
			//
			//the event has an enddate and it's earlier than yesterday
			} elseif($row->enddates && $enddates_stamp < $yesterday_stamp) {
				$days = round(($today_stamp - $enddates_stamp) / 86400);
				$date = JText::sprintf('MOD_JEM_WIDE_ENDED_DAYS_AGO', $days);
				$time = $row->times ? JEMOutput::formattime($row->endtimes, $time_format, false) : '';

			//the event has an enddate and it's later than today but the startdate is earlier than today
			//means a currently running event
			} elseif($row->dates && $row->enddates && $enddates_stamp > $today_stamp && $dates_stamp < $today_stamp) {
				$days = round(($today_stamp - $dates_stamp) / 86400);
				$date = JText::sprintf('MOD_JEM_WIDE_STARTED_DAYS_AGO', $days);
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';

			//the events date is earlier than yesterday
			} elseif($row->dates && $dates_stamp < $yesterday_stamp) {
				$days = round(($today_stamp - $dates_stamp) / 86400);
				$date = JText::sprintf('MOD_JEM_WIDE_DAYS_AGO', $days);
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';

			//the events date is later than tomorrow
			} elseif($row->dates && $dates_stamp > $tomorrow_stamp) {
				$days = round(($dates_stamp - $today_stamp) / 86400);
				$date = JText::sprintf('MOD_JEM_WIDE_DAYS_AHEAD', $days);
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';
			}
		} else {
			//Upcoming multidayevent (From 16.10.2008 Until 18.08.2008)
			if($dates_stamp > $today_stamp && $enddates_stamp > $dates_stamp) {
				$startdate = JEMOutput::formatdate($row->dates, $date_format);
				$enddate = JEMOutput::formatdate($row->enddates, $date_format);
				$date = JText::sprintf('MOD_JEM_WIDE_FROM_UNTIL', $startdate, $enddate);
				$time  = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';
				// endtime always starts with separator, also if there is no starttime
				$time .= $row->endtimes ? (' - ' . JEMOutput::formattime($row->endtimes, $time_format, false)) : '';
			}

			//current multidayevent (Until 18.08.2008)
			elseif($row->enddates && $enddates_stamp > $today_stamp && $dates_stamp < $today_stamp) {
				//format date
				$date = JEMOutput::formatdate($row->enddates, $date_format);
				$date = JText::sprintf('MOD_JEM_WIDE_UNTIL', $date);
				$time = $row->times ? JEMOutput::formattime($row->endtimes, $time_format, false) : '';
			}

			//single day event
			else {
				$date = JEMOutput::formatdate($row->dates, $date_format);
				$date = JText::sprintf('MOD_JEM_WIDE_ON_DATE', $date);
				$time = $row->times ? JEMOutput::formattime($row->times, $time_format, false) : '';
			}
		}

		return array($date, $time);
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