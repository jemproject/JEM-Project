<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

/**
 * Module-Teaser
 */
abstract class ModJemTeaserHelper
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

		static $formats  = array('year' => 'Y', 'month' => 'F', 'day' => 'j', 'weekday' => 'l');
		static $defaults = array('year' => '&nbsp;', 'month' => '', 'day' => '?', 'weekday' => '');

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

		# type:
		#  0: upcoming (not started) - dates,times > now+offset
		#  1: unfinished (not ended) - enddates,endtimes > now+offset
		#  2: archived               - no limit, but from now back to the past
		#  3: running (today)        - enddates,endtimes > today+offset AND dates,times < tomorrow+offset
		#  4: featured               - ? (same as upcoming yet)
		$type = (int)$params->get('type');
		$offset_hours = (int)$params->get('offset_hours', 0);
		$max_title_length = (int)$params->get('cuttitle', '25');
		$max_desc_length  = (int)$params->get('descriptionlength', 300);

		# clean parameter data
		$catids = JemHelper::getValidIds($params->get('catid'));
		$venids = JemHelper::getValidIds($params->get('venid'));
		$eventids = JemHelper::getValidIds($params->get('eventid'));
        $countryids = JemHelper::getValidIds($params->get('couid'));
		$stateloc      = $params->get('stateloc');
		$stateloc_mode = $params->get('stateloc_mode', 0);

		# Open date support
		$opendates = empty($eventids) ? 0 : 1; // allow open dates if limited to specific events
		$model->setState('filter.opendates', $opendates);

		# all upcoming or unfinished events
		if (($type == 0) || ($type == 1)) {
			$offset_minutes = $offset_hours * 60;

			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC', 'a.times ASC', 'a.created ASC'));

			$cal_from = "(a.dates IS NULL OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
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

		# featured
		elseif ($type == 4) {
			$offset_minutes = $offset_hours * 60;

			$model->setState('filter.featured',1);
			$model->setState('filter.orderby',array('a.dates ASC', 'a.times ASC', 'a.created ASC'));

			$cal_from  = "((TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
			$cal_from .= " OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates,a.dates),' ',IFNULL(a.endtimes,'23:59:59'))) > $offset_minutes)) ";
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

		# filter event id's
		if ($eventids) {
			$model->setState('filter.event_id', $eventids);
			$model->setState('filter.event_id.include', true);
		}

		# filter venue's state/province
		if ($stateloc) {
			$model->setState('filter.venue_state', $stateloc);
			$model->setState('filter.venue_state.mode', $stateloc_mode); // 0: exact, 1: partial
		}

        # filter country's
        if ($countryids) {
            $model->setState('filter.country_id', $countryids);
            $model->setState('filter.country_id.include', true);
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

		$color = $params->get('color');
		$fallback_color = $params->get('fallbackcolor', '#EEEEEE');

		# Loop through the result rows and prepare data
		$lists = array();
		$i     = -1; // it's easier to increment first

		foreach ($events as $row)
		{
			# create thumbnails if needed and receive imagedata
			$dimage = $row->datimage ? JemImage::flyercreator($row->datimage, 'event') : null;
			$limage = $row->locimage ? JemImage::flyercreator($row->locimage, 'venue') : null;

			#################
			## DEFINE LIST ##
			#################

			$lists[++$i] = new stdClass();

			# check view access
			if (in_array($row->access, $levels)) {
				# We know that user has the privilege to view the event
				$lists[$i]->link = Route::_(JemHelperRoute::getEventRoute($row->slug));
				$lists[$i]->linkText = Text::_('MOD_JEM_TEASER_READMORE');
			} else {
				$lists[$i]->link = Route::_('index.php?option=com_users&view=login');
				$lists[$i]->linkText = Text::_('MOD_JEM_TEASER_READMORE_REGISTER');
			}

			# cut title
			$fulltitle = htmlspecialchars($row->title, ENT_COMPAT, 'UTF-8');
			if (mb_strlen($fulltitle) > $max_title_length) {
				$title = mb_substr($fulltitle, 0, $max_title_length) . '&hellip;';
			} else {
				$title = $fulltitle;
			}

			$lists[$i]->eventid     = $row->id;
			$lists[$i]->title       = $title;
			$lists[$i]->fulltitle   = $fulltitle;
			$lists[$i]->venue       = htmlspecialchars($row->venue ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->catname     = implode(", ", JemOutput::getCategoryList($row->categories, $params->get('linkcategory', 1)));
			$lists[$i]->state       = htmlspecialchars($row->state ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->city        = htmlspecialchars($row->city ?? '', ENT_COMPAT, 'UTF-8');
            $lists[$i]->country     = htmlspecialchars($row->country ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->eventlink   = $params->get('linkevent', 1) ? Route::_(JemHelperRoute::getEventRoute($row->slug)) : '';
			$lists[$i]->venuelink   = $params->get('linkvenue', 1) ? Route::_(JemHelperRoute::getVenueRoute($row->venueslug)) : '';
            $lists[$i]->showimageevent   = $params->get('showimageevent', 1);
			$lists[$i]->showimagevenue   = $params->get('showimagevenue', 1);
			$lists[$i]->showdescriptionevent   = $params->get('showdescriptionevent', 1);

			# time/date
			$lists[$i]->day         = modJEMteaserHelper::_format_day($row, $params);
			$tmpdate                = empty($row->dates) ? $defaults : self::_format_date_fields($row->dates, $formats);
			$lists[$i]->dayname     = $tmpdate['weekday']; // keep them for backward compatibility
			$lists[$i]->daynum      = $tmpdate['day'];
			$lists[$i]->month       = $tmpdate['month'];
			$lists[$i]->year        = $tmpdate['year'];
			$lists[$i]->startdate   = $tmpdate;
			$lists[$i]->enddate     = empty($row->enddates) ? $defaults : self::_format_date_fields($row->enddates, $formats);
			list($lists[$i]->date,
			     $lists[$i]->time)  = self::_format_date_time($row, $params->get('datemethod', 1), $dateFormat, $timeFormat, $addSuffix);
			$lists[$i]->dateinfo    = JemOutput::formatDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $dateFormat, $timeFormat, $addSuffix);

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

      if ($max_desc_length != 1208) {
        # append <br /> tags on line breaking tags so they can be stripped below
        $description = preg_replace("'<(hr[^/>]*?/|/(div|h[1-6]|li|p|tr))>'si", "$0<br />", $row->introtext);

        # strip html tags but leave <br /> tags
        $description = strip_tags($description, "<br>");

        # switch <br /> tags to space character
        if ($params->get('br') == 0) {
          $description = mb_ereg_replace('<br[ /]*>',' ', $description);
        }

        if (empty($description)) {
          $lists[$i]->eventdescription = Text::_('MOD_JEM_TEASER_NO_DESCRIPTION');
        } elseif (mb_strlen($description) > $max_desc_length) {
          $lists[$i]->eventdescription = mb_substr($description, 0, $max_desc_length) . '&hellip;';
        } else {
          $lists[$i]->eventdescription = $description;
        }
      } else {
        $description = $row->introtext;
        if (empty($description)) {
          $lists[$i]->eventdescription = Text::_('MOD_JEM_TEASER_NO_DESCRIPTION');
        } else {
          $lists[$i]->eventdescription = $description;          
        }
      }

			$lists[$i]->readmore = mb_strlen(trim($row->fulltext));

			$lists[$i]->colorclass = $color;
			if (($color == 'category') && !empty($row->categories)) {
				$colors = array();
				foreach ($row->categories as $category) {
					if (!empty($category->color)) {
						$colors[$category->color] = $category->color;
					}
				}
				$lists[$i]->color = (count($colors) == 1) ? array_pop($colors) : $fallback_color;
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
	 *format days
	 */
	protected static function _format_day($row, &$params)
	{
		//Get needed timestamps and format
		//setlocale (LC_TIME, 'de_DE.UTF8');
		$yesterday_stamp = mktime(0, 0, 0, date("m"), date("d")-1, date("Y"));
		$yesterday       = date("Y-m-d", $yesterday_stamp);
		$today_stamp     = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
		$today           = date('Y-m-d');
		$tomorrow_stamp  = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
		$tomorrow        = date("Y-m-d", $tomorrow_stamp);

		$dates_stamp     = strtotime($row->dates);
		$enddates_stamp  = $row->enddates ? strtotime($row->enddates) : null;

		//check if today or tomorrow or yesterday and no current running multiday event
		if (($row->dates == $today) && empty($enddates_stamp)) {
			$result = Text::_('MOD_JEM_TEASER_TODAY');
		} elseif ($row->dates == $tomorrow) {
			$result = Text::_('MOD_JEM_TEASER_TOMORROW');
		} elseif ($row->dates == $yesterday) {
			$result = Text::_('MOD_JEM_TEASER_YESTERDAY');
		} else {
			//if daymethod show day
			if ($params->get('daymethod', 1) == 1) {
				//single day event
				$date = date('l', strtotime($row->dates));
				$result = Text::sprintf('MOD_JEM_TEASER_ON_DATE', $date);

				//Upcoming multidayevent (From 16.10.2010 Until 18.10.2010)
				if (($dates_stamp > $tomorrow_stamp) && $enddates_stamp) {
					$startdate = date('l', strtotime($row->dates));
					$result = Text::sprintf('MOD_JEM_TEASER_FROM', $startdate);
				}

				//current multidayevent (Until 18.08.2008)
				if ($row->enddates && ($enddates_stamp > $today_stamp) && ($dates_stamp <= $today_stamp)) {
					//format date
					$enddate = date('l', strtotime($row->enddates));
					$result = Text::sprintf('MOD_JEM_TEASER_UNTIL', $enddate);
				}
			} else { // show day difference
				//the event has an enddate and it's earlier than yesterday
				if ($row->enddates && ($enddates_stamp < $yesterday_stamp)) {
					$days = round( ($today_stamp - $enddates_stamp) / 86400 );
					$result = Text::sprintf('MOD_JEM_TEASER_ENDED_DAYS_AGO', $days);

				//the event has an enddate and it's later than today but the startdate is today or earlier than today
				//means a currently running event with startdate = today
				} elseif ($row->enddates && ($enddates_stamp > $today_stamp) && ($dates_stamp <= $today_stamp)) {
					$days = round( ($enddates_stamp - $today_stamp) / 86400 );
					$result = Text::sprintf('MOD_JEM_TEASER_DAYS_LEFT', $days);

				//the events date is earlier than yesterday
				} elseif ($dates_stamp < $yesterday_stamp) {
					$days = round( ($today_stamp - $dates_stamp) / 86400 );
					$result = Text::sprintf('MOD_JEM_TEASER_DAYS_AGO', $days );

				//the events date is later than tomorrow
				} elseif ($dates_stamp > $tomorrow_stamp) {
					$days = round( ($dates_stamp - $today_stamp) / 86400 );
					$result = Text::sprintf('MOD_JEM_TEASER_DAYS_AHEAD', $days);
				}
			}
		}

		return $result;
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
			$yesterday_stamp = mktime(0, 0, 0, date("m"), date("d")-1, date("Y"));
			$yesterday       = date("Y-m-d", $yesterday_stamp);
			$today_stamp     = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$today           = date('Y-m-d');
			$tomorrow_stamp  = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
			$tomorrow        = date("Y-m-d", $tomorrow_stamp);

			$dates_stamp     = $row->dates ? strtotime($row->dates) : null;
			$enddates_stamp  = $row->enddates ? strtotime($row->enddates) : null;

			$times = $row->times; // show starttime by default

			# datemethod show day difference
			if ($method == 2) {
				# Check if today, tomorrow, or yesterday
				if ($row->dates == $today) {
					$date = Text::_('MOD_JEM_TEASER_TODAY');
				} elseif ($row->dates == $tomorrow) {
					$date = Text::_('MOD_JEM_TEASER_TOMORROW');
				} elseif ($row->dates == $yesterday) {
					$date = Text::_('MOD_JEM_TEASER_YESTERDAY');
				}
				# This one isn't very different from the DAYS AGO output but it seems
				# adequate to use different language strings here.
				#
				# The event has an enddate and it's earlier than yesterday
				elseif ($row->enddates && ($enddates_stamp < $yesterday_stamp)) {
					$days = round(($today_stamp - $enddates_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_TEASER_ENDED_DAYS_AGO', $days);
					# show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}
				# The event has an enddate and it's later than today but the startdate is earlier than today
				# means a currently running event
				elseif ($row->dates && $row->enddates && ($enddates_stamp > $today_stamp) && ($dates_stamp < $today_stamp)) {
					$days = round(($today_stamp - $dates_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_TEASER_STARTED_DAYS_AGO', $days);
				}
				# The events date is earlier than yesterday
				elseif ($row->dates && ($dates_stamp < $yesterday_stamp)) {
					$days = round(($today_stamp - $dates_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_TEASER_DAYS_AGO', $days);
				}
				# The events date is later than tomorrow
				elseif ($row->dates && ($dates_stamp > $tomorrow_stamp)) {
					$days = round(($dates_stamp - $today_stamp) / 86400);
					$date = Text::sprintf('MOD_JEM_TEASER_DAYS_AHEAD', $days);
				}
				else {
					$date = JemOutput::formatDateTime('', ''); // Oops - say "Open date"
				}
			}
			# datemethod show date - not shown beause there is a calendar image
			else {
			///@todo check date+time to be more acurate
				# Upcoming multidayevent (From 16.10.2008 Until 18.08.2008)
				if (($dates_stamp >= $today_stamp) && ($enddates_stamp > $dates_stamp)) {
					$startdate = JemOutput::formatdate($row->dates, $dateFormat);
					$enddate = JemOutput::formatdate($row->enddates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_TEASER_FROM_UNTIL', $startdate, $enddate);
					# don't show endtime because calendar is shown
				}
				# Current multidayevent (Until 18.08.2008)
				elseif ($row->enddates && ($enddates_stamp >= $today_stamp) && ($dates_stamp < $today_stamp)) {
					$enddate = JEMOutput::formatdate($row->enddates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_TEASER_UNTIL', $enddate);
					# don't show endtime because calendar is shown
				}
				# Single day event
				else {
					$startdate = JEMOutput::formatdate($row->dates, $dateFormat);
					$date = Text::sprintf('MOD_JEM_TEASER_ON_DATE', $startdate);
					# additionally show endtime, but on single day events only to prevent user confusion
					if (empty($row->enddates)) {
						$endtimes = $row->endtimes;
					}
				}
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