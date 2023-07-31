<?php
/**
 * @version 4.0.1-dev1
 * @package JEM
 * @subpackage JEM Wide Module
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

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

        $db     = Factory::getContainer()->get('DatabaseDriver');
		$user   = JemFactory::getUser();
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

		$type = (int)$params->get('type');
		$offset_hours = (int)$params->get('offset_hours', 0);
		$max_title_length = (int)$params->get('cuttitle', '25');

		# clean parameter data
		$catids = JemHelper::getValidIds($params->get('catid'));
		$venids = JemHelper::getValidIds($params->get('venid'));

		# all upcoming or unfinished events
		if (($type == 0) || ($type == 1)) {
			$offset_minutes = $offset_hours * 60;

			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC', 'a.times ASC', 'a.created ASC'));

			$cal_from = "((TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
			$cal_from .= ($type == 1) ? " OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates,a.dates),' ',IFNULL(a.endtimes,'23:59:59'))) > $offset_minutes)) " : ") ";
		}

		# archived events only
		elseif ($type == 2) {
			$model->setState('filter.published',2);
			$model->setState('filter.orderby',array('a.dates DESC', 'a.times DESC', 'a.created DESC'));
			$cal_from = "";
		}

		# currently running events only (today + offset is inbetween start and end date of event)
		elseif ($type == 3) {
			$offset_days = (int)round($offset_hours / 24);

			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC', 'a.times ASC', 'a.created ASC'));

			$cal_from = " ((DATEDIFF(a.dates, CURDATE()) <= $offset_days) AND (DATEDIFF(IFNULL(a.enddates,a.dates), CURDATE()) >= $offset_days))";
		}

		$model->setState('filter.calendar_from',$cal_from);
		$model->setState('filter.groupby','a.id');

		# filter category's
		if ($catids) {
			$model->setState('filter.category_id', $catids);
			$model->setState('filter.category_id.include', true);
		}

		# filter venue's
		if ($venids) {
			$model->setState('filter.venue_id', $venids);
			$model->setState('filter.venue_id.include', true);
		}

		# count
		$count = $params->get('count', '2');
		$model->setState('list.limit', $count);

		// if ($params->get('use_modal', 0)) {
		// 	JHtml::_('behavior.modal', 'a.flyermodal');
		// }

		# date/time
		$dateFormat = $params->get('formatdate', '');
		$timeFormat = $params->get('formattime', '');
		$addSuffix  = empty($timeFormat); // if we use component's default time format we can also add corresponding suffix

		# Retrieve the available Events
		$events = $model->getItems();

		# Loop through the result rows and prepare data
		$lists = array();
		$i     = -1;

		foreach ($events as $row)
		{
			# create thumbnails if needed and receive imagedata
			$dimage = $row->datimage ? JEMImage::flyercreator($row->datimage, 'event') : null;
			$limage = $row->locimage ? JEMImage::flyercreator($row->locimage, 'venue') : null;

			#################
			## DEFINE LIST ##
			#################

			$lists[++$i] = new stdClass();

			# cut titel
			$fulltitle = htmlspecialchars($row->title, ENT_COMPAT, 'UTF-8');
			if (mb_strlen($fulltitle) > $max_title_length) {
				$title = mb_substr($fulltitle, 0, $max_title_length) . '...';
			} else {
				$title = $fulltitle;
			}

			$lists[$i]->eventid     = $row->id;
			$lists[$i]->title       = $title;
			$lists[$i]->fulltitle   = $fulltitle;
			$lists[$i]->venue       = $row->venue ? htmlspecialchars($row->venue, ENT_COMPAT, 'UTF-8') : $row->venue;
			$lists[$i]->catname     = implode(", ", JemOutput::getCategoryList($row->categories, $params->get('linkcategory', 1)));
			$lists[$i]->state       = $row->state ? htmlspecialchars($row->state, ENT_COMPAT, 'UTF-8') : $row->state;
			$lists[$i]->city        = $row->city ? htmlspecialchars($row->city, ENT_COMPAT, 'UTF-8') : $row->city;
			$lists[$i]->eventlink   = $params->get('linkevent', 1) ? Route::_(JEMHelperRoute::getEventRoute($row->slug)) : '';
			$lists[$i]->venuelink   = $params->get('linkvenue', 1) ? Route::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : '';

			# time/date
			list($lists[$i]->date,
			     $lists[$i]->time)  = self::_format_date_time($row, $params->get('datemethod', 1), $dateFormat, $timeFormat, $addSuffix);
			$lists[$i]->dateinfo    = JEMOutput::formatDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $dateFormat, $timeFormat, $addSuffix);

			if ($dimage == null) {
				$lists[$i]->eventimage     = Uri::base(true).'/media/com_jem/images/blank.png';
				$lists[$i]->eventimageorig = Uri::base(true).'/media/com_jem/images/blank.png';
			} else {
				$lists[$i]->eventimage     = Uri::base(true).'/'.$dimage['thumb'];
				$lists[$i]->eventimageorig = Uri::base(true).'/'.$dimage['original'];
			}

			if ($limage == null) {
				$lists[$i]->venueimage     = Uri::base(true).'/media/com_jem/images/blank.png';
				$lists[$i]->venueimageorig = Uri::base(true).'/media/com_jem/images/blank.png';
			} else {
				$lists[$i]->venueimage     = Uri::base(true).'/'.$limage['thumb'];
				$lists[$i]->venueimageorig = Uri::base(true).'/'.$limage['original'];
			}

			$lists[$i]->eventdescription   = $row->fulltext ? strip_tags($row->fulltext) : $row->fulltext;
			$lists[$i]->venuedescription   = $row->locdescription ? strip_tags($row->locdescription) : $row->locdescription;

			# provide custom fields
			for ($n = 1; $n <= 10; ++$n) {
				$var = 'custom'.$n;
				$lists[$i]->$var = htmlspecialchars($row->$var, ENT_COMPAT, 'UTF-8');
			}
		} // foreach ($events as $row)

		return $lists;
	}


	/**
	 * Method to format date and time information
	 *
	 * @access protected
	 * @return array(string, string) returns date and time strings as array
	 */
	protected static function _format_date_time($row, $method, $dateFormat = '', $timeFormat = '', $addSuffix = false)
	{
		if (empty($row->dates)) {
			// open date
			$date  = JEMOutput::formatDateTime('', ''); // "Open date"
			$times = $row->times;
			$endtimes = $row->endtimes;
		} else {
			//Get needed timestamps and format
			$yesterday_stamp = mktime(0, 0, 0, date("m"), date("d")-1, date("Y"));
			$yesterday       = date("Y-m-d", $yesterday_stamp);
			$today_stamp     = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$today           = date('Y-m-d');
			$tomorrow_stamp  = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
			$tomorrow        = date("Y-m-d", $tomorrow_stamp);

			$dates_stamp     = $row->dates ? strtotime($row->dates) : null;
			$enddates_stamp  = $row->enddates ? strtotime($row->enddates) : null;

			$times = $row->times; // show starttime by default

			//if datemethod show day difference
			if ($method == 2) {
				//check if today or tomorrow
				if ($row->dates == $today) {
					$date = Text::_('MOD_JEM_WIDE_TODAY');
				} elseif ($row->dates == $tomorrow) {
					$date = Text::_('MOD_JEM_WIDE_TOMORROW');
				} elseif ($row->dates == $yesterday) {
					$date = Text::_('MOD_JEM_WIDE_YESTERDAY');
				}
				//This one isn't very different from the DAYS AGO output but it seems
				//adequate to use a different language string here.
				//
				//the event has an enddate and it's earlier than yesterday
				elseif ($row->enddates && ($enddates_stamp < $yesterday_stamp)) {
					$days = round(($today_stamp - $enddates_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_WIDE_ENDED_DAYS_AGO', $days);
					// show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}
				//the event has an enddate and it's later than today but the startdate is earlier than today
				//means a currently running event
				elseif ($row->dates && $row->enddates && ($enddates_stamp > $today_stamp) && ($dates_stamp < $today_stamp)) {
					$days = round(($today_stamp - $dates_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_WIDE_STARTED_DAYS_AGO', $days);
				}
				//the events date is earlier than yesterday
				elseif ($row->dates && ($dates_stamp < $yesterday_stamp)) {
					$days = round(($today_stamp - $dates_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_WIDE_DAYS_AGO', $days);
				}
				//the events date is later than tomorrow
				elseif ($row->dates && ($dates_stamp > $tomorrow_stamp)) {
					$days = round(($dates_stamp - $today_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_WIDE_DAYS_AHEAD', $days);
				}
				else {
					$date = JEMOutput::formatDateTime('', ''); // Oops - say "Open date"
				}
			} else { // datemethod show date
			// TODO: check date+time to be more acurate
				//Upcoming multidayevent (From 16.10.2008 Until 18.08.2008)
				if (($dates_stamp >= $today_stamp) && ($enddates_stamp > $dates_stamp)) {
					$startdate = JEMOutput::formatdate($row->dates, $dateFormat);
					$enddate = JEMOutput::formatdate($row->enddates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_WIDE_FROM_UNTIL', $startdate, $enddate);
					// additionally show endtime
					$endtimes = $row->endtimes;
				}
				//current multidayevent (Until 18.08.2008)
				elseif ($row->enddates && ($enddates_stamp >= $today_stamp) && ($dates_stamp < $today_stamp)) {
					$enddate = JEMOutput::formatdate($row->enddates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_WIDE_UNTIL', $enddate);
					// show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}
				//single day event
				else {
					$startdate = JEMOutput::formatdate($row->dates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_WIDE_ON_DATE', $startdate);
					// additionally show endtime, but on single day events only to prevent user confusion
					if (empty($row->enddates)) {
						$endtimes = $row->endtimes;
					}
				}
			}
		}

		$time  = empty($times)    ? '' : JEMOutput::formattime($times, $timeFormat, $addSuffix);
		$time .= empty($endtimes) ? '' : ('&nbsp;-&nbsp;' . JEMOutput::formattime($row->endtimes, $timeFormat, $addSuffix));

		return array($date, $time);
	}
}