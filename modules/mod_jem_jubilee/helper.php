<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @subpackage JEM Jubilee Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

/**
* Module-Jubilee
*/
abstract class ModJemJubileeHelper
{
	/**
	 * Method to get the events
	 *
	 * @access public
	 *
	 * @param  (J)Registry  &$params  Reference to module's parameters
	 *
	 * @return array
	 */
	public static function getList(&$params)
	{
		mb_internal_encoding('UTF-8');

		static $formats  = array('year' => 'Y', 'month' => 'F', 'day' => 'j', 'weekday' => 'l', 'md' => 'md');
		static $defaults = array('year' => '&nbsp;', 'month' => '', 'day' => '?', 'weekday' => '', 'md' => '');

        $db     = Factory::getContainer()->get('DatabaseDriver');
		$user   = JemFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
        $uri    = Uri::getInstance();

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

		$status           = (int)$params->get('status', 2);
		$offset_days      = (int)$params->get('offset_days', 0);
		$date_match_mode  = (int)$params->get('date_match_mode', 0);
		$max_title_length = (int)$params->get('cuttitle', '25');
		$max_desc_length  = (int)$params->get('descriptionlength', 300);
		$orderdir         = $params->get('order', 0) ? 'ASC' : 'DESC';

		# date/time
		$dateFormat = $params->get('formatdate', '');
		$timeFormat = $params->get('formattime', '');
		$showtime   = $params->get('showtime', 0);
		$addSuffix  = empty($timeFormat); // if we use component's default time format we can also add corresponding suffix

		$now = self::_get_local_now(false);
		if ($offset_days > 0) {
			$now->add(new DateInterval('P'.$offset_days.'D'));
		} elseif ($offset_days < 0) {
			$now->sub(new DateInterval('P'.abs($offset_days).'D'));
		}
		$date = self::_format_date_fields($now->toSql(true), $formats);
		$date['date'] = JemOutput::formatdate($now, $dateFormat);
		$params->set('date', $date);
		$cur_md = $date['md'];

		if (empty($cur_md)) { // oops...
			return array();
		}

		# count
		$count = min(max($params->get('count', '5'), 1), 100); // range 1..100, default 5

		# shuffle
		$shuffle = (bool)$params->get('shuffle', 0);
		if ($shuffle) {
			$max_count = min(max((int)$params->get('shuffle_count', 20), $count), 100);
		} else {
			$max_count = $count;
		}

		$model->setState('list.limit', $max_count);

		# replace bbCodes in intro text
		$intro = (string)$params->get('introtext', '');
		if (!empty($intro)) {
			$tokens = array('day', 'month', 'year');
			$tmp = $intro;
			foreach ($tokens as $token) {
				$tmp = preg_replace("/\[$token\]/", $date[$token], $tmp);
			}
			if ($tmp !== $intro) {
				$params->set('introtext', $tmp);
			}
		}

		switch ($status) {
		case 1: # published
			$published = 1;
			break;
		case 2: # archived
		default:
			$published = 2;
			break;
		case 3: # both
			$published = array(1, 2);
			break;
		}

		# filter by day + month
		switch ($date_match_mode) {
		case 0: # somewhen from start date to end date
			$cal_from  = " IF(YEAR(IFNULL(a.enddates, a.dates)) > YEAR(a.dates)";
			$cal_from .= " , (DATE_FORMAT(a.dates, '%m%d') <= $cur_md) OR  ($cur_md <= DATE_FORMAT(IFNULL(a.enddates, a.dates), '%m%d'))";
			$cal_from .= " , (DATE_FORMAT(a.dates, '%m%d') <= $cur_md) AND ($cur_md <= DATE_FORMAT(IFNULL(a.enddates, a.dates), '%m%d'))";
			$cal_from .= " ) ";
			break;
		case 1: # on start date
		default:
			$cal_from  = " (DATE_FORMAT(a.dates, '%m%d') = $cur_md) ";
			break;
		case 2: # on end date
			$cal_from  = " (DATE_FORMAT(IFNULL(a.enddates, a.dates), '%m%d') = $cur_md) ";
			break;
		case 3: # on start or end date
			$cal_from  = " ((DATE_FORMAT(a.dates, '%m%d') = $cur_md) OR ";
			$cal_from .= "  (DATE_FORMAT(IFNULL(a.enddates, a.dates), '%m%d') = $cur_md)) ";
			break;
		}
		$cal_to    = false;

		$model->setState('filter.opendates', 0);
		$model->setState('filter.published', $published);
		$model->setState('filter.orderby', array('a.dates '.$orderdir, 'a.times '.$orderdir, 'a.created '.$orderdir));
		if (!empty($cal_from)) {
			$model->setState('filter.calendar_from', $cal_from);
		}
		if (!empty($cal_to)) {
			$model->setState('filter.calendar_to', $cal_to);
		}
		$model->setState('filter.groupby', 'a.id');

		# clean parameter data
		$catids = JemHelper::getValidIds($params->get('catid'));
		$venids = JemHelper::getValidIds($params->get('venid'));
		$stateloc      = $params->get('stateloc');
		$stateloc_mode = $params->get('stateloc_mode', 0);

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

		# filter venue's state/province
		if ($stateloc) {
			$model->setState('filter.venue_state', $stateloc);
			$model->setState('filter.venue_state.mode', $stateloc_mode); // 0: exact, 1: partial
		}

		// if ($params->get('flyer_link_type', 0) == 1) {
		// 	JHtml::_('behavior.modal', 'a.flyermodal');
		// }

		####
		# Retrieve the available Events
		####
		$events = $model->getItems();

		$color = $params->get('color');
		$user_color = $params->get('usercolor', '#EEEEEE');
		$user_color_is_dark = self::_is_dark($user_color);
		$params->set('usercolor_is_dark', $user_color_is_dark);

		// Don't shuffle original array to keep ordering of remaining events intact.
		$indices = array_keys($events);
		if (count($events) > $count) {
			if ($shuffle) {
				shuffle($indices);
			}
			array_splice($indices, $count);
		}

		# Loop through the result rows and prepare data
		$lists = array();
		$i     = -1; // it's easier to increment first

		foreach ($events as $key => $row)
		{
			if (!in_array($key, $indices)) {
				continue; // skip removed events
			}

			# create thumbnails if needed and receive imagedata
			$dimage = $row->datimage ? JemImage::flyercreator($row->datimage, 'event') : null;
			$limage = $row->locimage ? JemImage::flyercreator($row->locimage, 'venue') : null;

			#################
			## DEFINE LIST ##
			#################

			$lists[++$i] = new stdClass(); // add new object

			# check view access
			if (in_array($row->access, $levels)) {
				# We know that user has the privilege to view the event
				$lists[$i]->link = Route::_(JemHelperRoute::getEventRoute($row->slug));
				$lists[$i]->linkText = Text::_('MOD_JEM_JUBILEE_READMORE');
			} else {
				$lists[$i]->link = Route::_('index.php?option=com_users&view=login');
				$lists[$i]->linkText = Text::_('MOD_JEM_JUBILEE_READMORE_REGISTER');
			}

			# cut title
			$fulltitle = htmlspecialchars($row->title, ENT_COMPAT, 'UTF-8');
			if (mb_strlen($fulltitle) > $max_title_length) {
				$title = mb_substr($fulltitle, 0, $max_title_length) . '&hellip;';
			} else {
				$title = $fulltitle;
			}

			$lists[$i]->title       = $title;
			$lists[$i]->fulltitle   = $fulltitle;
			$lists[$i]->venue       = htmlspecialchars($row->venue ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->catname     = implode(", ", JemOutput::getCategoryList($row->categories, $params->get('linkcategory', 1)));
			$lists[$i]->state       = htmlspecialchars($row->state ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->city        = htmlspecialchars($row->city ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->eventlink   = $params->get('linkevent', 1) ? Route::_(JemHelperRoute::getEventRoute($row->slug)) : '';
			$lists[$i]->venuelink   = $params->get('linkvenue', 1) ? Route::_(JemHelperRoute::getVenueRoute($row->venueslug)) : '';

			# time/date
			/* depending on settongs we need:
		     *  showcalendar 1, datemethod 1 : month, weekday, day + time
		     *  showcalendar 1, datemethod 2 : month, weekday, day + relative date + time
		     *  showcalendar 0, datemethod 1 : (long) date + time
		     *  showcalendar 0, datemethod 2 : relative date + time
		     */
			$lists[$i]->startdate   = empty($row->dates)    ? $defaults : self::_format_date_fields($row->dates,    $formats);
			$lists[$i]->enddate     = empty($row->enddates) ? $defaults : self::_format_date_fields($row->enddates, $formats);
			list($lists[$i]->date,
			     $lists[$i]->time)  = self::_format_date_time($row, $params->get('datemethod', 1), $dateFormat, $timeFormat, $addSuffix);
			$lists[$i]->dateinfo    = JemOutput::formatDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $dateFormat, $timeFormat, $addSuffix, $showtime);

			if ($dimage == null) {
				$lists[$i]->eventimage     = '';
				$lists[$i]->eventimageorig = '';
			} else {
				$lists[$i]->eventimage     = $uri->base(true).'/'.$dimage['thumb'];
				$lists[$i]->eventimageorig = $uri->base(true).'/'.$dimage['original'];
			}

			if ($limage == null) {
				$lists[$i]->venueimage     = '';
				$lists[$i]->venueimageorig = '';
			} else {
				$lists[$i]->venueimage     = $uri->base(true).'/'.$limage['thumb'];
				$lists[$i]->venueimageorig = $uri->base(true).'/'.$limage['original'];
			}

			# append <br /> tags on line breaking tags so they can be stripped below
			$description = preg_replace("'<(hr[^/>]*?/|/(div|h[1-6]|li|p|tr))>'si", "$0<br />", $row->introtext);

			# strip html tags but leave <br /> tags
			$description = strip_tags($description, "<br>");

			# switch <br /> tags to space character
			if ($params->get('br') == 0) {
				$description = mb_ereg_replace('<br[ /]*>',' ', $description);
			}

			if (empty($description)) {
				$lists[$i]->eventdescription = Text::_('MOD_JEM_JUBILEE_NO_DESCRIPTION');
			} elseif (mb_strlen($description) > $max_desc_length) {
				$lists[$i]->eventdescription = mb_substr($description, 0, $max_desc_length) . '&hellip;';
			} else {
				$lists[$i]->eventdescription = $description;
			}

			$lists[$i]->readmore = mb_strlen(trim($row->fulltext));

			$lists[$i]->colorclass = $color;
			if ($color == 'alpha') {
				$lists[$i]->color = $user_color;
				$lists[$i]->color_is_dark = $user_color_is_dark;
			}

			# provide custom fields
			for ($n = 1; $n <= 10; ++$n) {
				$var = 'custom'.$n;
				$lists[$i]->$var = htmlspecialchars($row->$var, ENT_COMPAT, 'UTF-8');
			}
		} // foreach ($events as $row)

		return $lists;
	}

	/**
	 * Method to decide if given color is dark.
	 *
	 * @access protected
	 *
	 * @param  string  $color  color value in form '#rgb' or '#rrggbb'
	 *
	 * @return bool  given color is dark (true) or not (false)
	 *
	 * @since  2.2.1
	 */
	protected static function _is_dark($color)
	{
		$gray = false;

		# we understand '#rgb' or '#rrggbb' colors only
		# r:77, g:150, b:28
		if (strlen($color) < 5) {
			$scan = sscanf($color, '#%1x%1x%1x');
			if (is_array($scan) && count($scan) == 3) {
				$gray = (17 * $scan[0] *  77) / 255
				      + (17 * $scan[1] * 150) / 255
				      + (17 * $scan[2] *  28) / 255;
			}
		} else {
			$scan = sscanf($color, '#%2x%2x%2x');
			if (is_array($scan) && count($scan) == 3) {
				$gray = ($scan[0] *  77) / 255
				      + ($scan[1] * 150) / 255
				      + ($scan[2] *  28) / 255;
			}
		}

		return (!empty($gray) && ($gray <= 127));
	}

	/**
	 * Method to get current day repecting local time.
	 *
	 * @access protected
	 *
	 * @param  bool  $cleartime  clear time values (default) or keep them
	 *
	 * @return JDate user's today
	 *
	 * @since  2.2.1
	 */
	protected static function _get_local_now($cleartime = true)
	{
		$app    = Factory::getApplication();
		$user   = Factory::getUser();
		$offset = $app->get('offset');
		$userTz = $user->getParam('timezone', $offset);

		# Set the time to be the beginning of today, local time.
		$today = new JDate('now', $userTz);
		if ($cleartime) {
			$today->setTime(0, 0, 0);
		}

		return $today;
	}

	/**
	 * Method used by _format_date_time() to format day difference string
	 *
	 * @access private
	 *
	 * @param  DateInterval  $dateDiff  difference to current day
	 * @param  string        $fmt2      one of '_STARTED', '_ENDED', or empty (default)
	 * @param  string        $fmt5      one of '_AGO' (default) or '_AHEAD'
	 *
	 * @return string day difference string
	 *
	 * @since  2.2.1
	 */
	private static function _format_relative_date($dateDiff, $fmt2 = '', $fmt5 = '_AGO')
	{
		if (!is_a($dateDiff, 'DateInterval')) {
			return false;
		}

		$years  = $dateDiff->format('%y');
		$months = $dateDiff->format('%m');
		$days   = $dateDiff->format('%a');

		# prepare / sanitize token parts
		$fmt1 = 'MOD_JEM_JUBILEE';
		if (($fmt2 !== '_STARTED') && ($fmt2 !== '_ENDED')) {
			$fmt2 = '';
		}
		if ($fmt5 !== '_AGO') {
			$fmt5 = '_AHEAD';
		}

		# influent months
		if ($months > 10) {
			++$years;
			$fmt3 = '_NEARLY';
		} elseif ($months > 0) {
			$fmt3 = '_OVER';
		} else {
			$fmt3 = '';
		}

		# construct date string
		if ($years > 0) {
			$fmt4 = $years === 1 ? '_YEAR' : '_YEARS';
			$date = Text::sprintf($fmt1 . $fmt2 . $fmt3 . $fmt4 . $fmt5, $years);
		} elseif ($months > 1) {
			$fmt4 = '_MONTHS';
			$date = Text::sprintf($fmt1 . $fmt2 . $fmt3 . $fmt4 . $fmt5, $months);
		} else {
			$fmt4 = '_DAYS';
			$date = Text::sprintf($fmt1 . $fmt2 . $fmt3 . $fmt4 . $fmt5, $days);
		}

		return $date;
	}

	/**
	 * Method to format date and time information
	 *
	 * @access protected
	 *
	 * @param  object  $row  event as got from db
	 * @param  int     $method  1 for absolute date, or 2 for relative date
	 * @param  string  $dateFormat format string for date (optional)
	 * @param  string  $timeFormat format string for time (optional)
	 * @param  bool    $addSuffix  show or hide (default) time suffix, e.g. 'h' (optional)
	 *
	 * @return array(string, string) returns date and time strings as array
	 */
	protected static function _format_date_time($row, $method, $dateFormat = '', $timeFormat = '', $addSuffix = false)
	{
		if (empty($row->dates)) {
			# open date
			$date  = JemOutput::formatDateTime('', ''); // "Open date"
			$times = $row->times;
			$endtimes = $row->endtimes;
		} else {
			# Get needed timestamps and format
			$today_stamp     = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$dates_stamp     = $row->dates ? strtotime($row->dates) : null;
			$enddates_stamp  = $row->enddates ? strtotime($row->enddates) : null;
		//	$enddates_stamp  = null; // it doesn't make sense on a Jubilee list

			$times = $row->times; // show starttime by default

			# datemethod show day difference
			if ($method == 2) {
				$dateToday = self::_get_local_now();//new DateTime('today');
				$diffStart = !empty($row->dates)    ? $dateToday->diff(date_create($row->dates))   : null;
			//	$diffEnd   = !empty($row->enddates) ? $dateToday->diff(date_create($row->enddates)): null;
				$diffEnd   = null; // it doesn't make sense on a Jubilee list
				$daysStart = is_object($diffStart)  ? $diffStart->format('%r%a') : null;
				$daysEnd   = is_object($diffEnd)    ? $diffEnd->format('%r%a')   : null;

				# Check if today, tomorrow, or yesterday
				if (is_object($diffStart) && ($daysStart == 0)) {
					$date = Text::_('MOD_JEM_JUBILEE_TODAY');
				} elseif (is_object($diffStart) && ($daysStart == 1)) {
					$date = Text::_('MOD_JEM_JUBILEE_TOMORROW');
				} elseif (is_object($diffStart) && ($daysStart == -1)) {
					$date = Text::_('MOD_JEM_JUBILEE_YESTERDAY');
				}
				# This one isn't very different from the DAYS AGO output but it seems
				# adequate to use different language strings here.
				# NOTE: We ignore end dates (see above) because it's not really usefull on a Jubilee list.
				#
				# The event has an enddate and it's earlier than yesterday
				elseif (is_object($diffEnd) && ($daysEnd < -1)) {
					$date = self::_format_relative_date($diffEnd, '_ENDED', '_AGO');
					# show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}
				# The event has an enddate and it's later than today but the startdate is earlier than today
				# means a currently running event
				elseif (is_object($diffStart) && is_object($diffEnd) && ($daysStart < 0) && ($daysEnd > 0)) {
					$date = self::_format_relative_date($diffStart, '_STARTED', '_AGO');
				}
				# The events date is earlier than yesterday
				elseif (is_object($diffStart) && ($daysStart < -1)) {
					$date = self::_format_relative_date($diffStart, '', '_AGO');
				}
				# The events date is later than tomorrow
				elseif (is_object($diffStart) && ($daysStart > 1)) {
					$date = self::_format_relative_date($diffStart, '', '_AHEAD');
				}
				else {
					$date = JemOutput::formatDateTime('', ''); // Oops - say "Open date"
				}
			}
			# datemethod show date
			elseif ($method == 1) {
			///@todo check date+time to be more acurate
				/*
				 * On Jubilee module differentiation between upcoming, running, and past is unimportant.
				 * Let's show us "From ... Until ..." and "On ..." only.
				 */
				# (-Upcoming-) multiday event (From 16.10.2008 Until 18.08.2008)
				if (/*($dates_stamp >= $today_stamp) &&*/ ($enddates_stamp > $dates_stamp)) {
					$startdate = JemOutput::formatdate($row->dates, $dateFormat);
					$enddate = JemOutput::formatdate($row->enddates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_JUBILEE_FROM_UNTIL', $startdate, $enddate);
					# additionally show endtime
					$endtimes = $row->endtimes;
				}
				/* (not useful on Jubilee list)
				# Currently running multiday event (Until 18.08.2008)
				elseif ($row->enddates && ($enddates_stamp >= $today_stamp) && ($dates_stamp < $today_stamp)) {
					$enddate = JEMOutput::formatdate($row->enddates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_JUBILEE_UNTIL', $enddate);
					# show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}*/
				# Singleday event
				else {
					$startdate = JEMOutput::formatdate($row->dates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_JUBILEE_ON_DATE', $startdate);
					# additionally show endtime, but on single day events only to prevent user confusion
					if (empty($row->enddates)) {
						$endtimes = $row->endtimes;
					}
				}
			} else {
				$date = '';
			}
		}

		$time  = empty($times)    ? '' : JemOutput::formattime($times, $timeFormat, $addSuffix);
		$time .= empty($endtimes) ? '' : ('&nbsp;-&nbsp;' . JemOutput::formattime($row->endtimes, $timeFormat, $addSuffix));

		return array($date, $time);
	}

	/**
	 * Method to format different parts of a date as array
	 *
	 * @access public
	 *
	 * @param  mixed  date in form 'yyyy-mm-dd' or as JDate object
	 * @param  array  formats to get as assotiative array (e.g. 'day' => 'j'; see {@link PHP_MANUAL#date})
	 *
	 * @return mixed  array of formatted date parts or false
	 */
	protected static function _format_date_fields($date, array $formats)
	{
		if (empty($formats)) {
			return false;
		}

		$result = array();
		$jdate = ($date instanceof JDate) ? $date : new JDate($date);

		foreach ($formats as $k => $v) {
			$result[$k] = $jdate->format($v, false, true);
		}

		return $result;
	}
}