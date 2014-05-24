<?php
/**
 * @version 1.9.7
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
		mb_internal_encoding('UTF-8');

		$db		= JFactory::getDBO();
		$user	= JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();

		# Retrieve Eventslist model for the data
		$model = JModelLegacy::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));

		# Set params for the model
		# has to go before the getItems function
		$model->setState('params', $params);
		$model->setState('filter.access',true);

		# filter published
		#  0: unpublished
		#  1: published
		#  2: archived
		# -2: trashed

		# all upcoming events//all upcoming events
		if ($params->get('type') == 1) {
			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC','a.times ASC'));

			$cal_from = "(TIMEDIFF(CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00')),NOW()) > 1 OR (a.enddates AND TIMEDIFF(CONCAT(a.enddates,' ',IFNULL(a.times,'00:00:00')),NOW())) > 1) ";
		}

		# archived events only
		elseif ($params->get('type') == 2) {
			$model->setState('filter.published',2);
			$model->setState('filter.orderby',array('a.dates DESC','a.times DESC'));
			$cal_from = "";
		}

		# currently running events only
		elseif ($params->get('type') == 3) {
			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC','a.times ASC'));

			$cal_from = " (a.dates = CURDATE() OR (a.enddates >= CURDATE() AND a.dates <= CURDATE()))";
		}

		$model->setState('filter.calendar_from',$cal_from);
		$model->setState('filter.groupby','a.id');

		# clean parameter data
		$catid = trim($params->get('catid'));
		$venid = trim($params->get('venid'));

		# filter category's
		if ($catid) {
			$ids = explode(',', $catid);
			$ids = JArrayHelper::toInteger($ids);
			$model->setState('filter.category_id',$ids);
			$model->setState('filter.category_id.include',true);
		}

		# filter venue's
		if ($venid) {
			$ids = explode(',', $venid);
			$ids = JArrayHelper::toInteger($ids);
			$model->setState('filter.venue_id',$ids);
			$model->setState('filter.venue_id.include',true);
		}

		# count
		$count = $params->get('count', '2');

		if ($params->get('use_modal', 0)) {
			JHtml::_('behavior.modal', 'a.flyermodal');
		}

		$model->setState('list.limit',$count);

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
			$lists[$i]->date 			= modJEMwideHelper::_format_date($row, $params);
			$lists[$i]->time 			= $row->times ? JEMOutput::formattime($row->times,null,false) : '' ;

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