<?php
/**
 * @version 2.2.1
* @package JEM
* @subpackage JEM Jubilee Module
* @copyright (C) 2014-2017 joomlaeventmanager.net
* @copyright (C) 2005-2009 Christoph Lukes
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*/
defined('_JEXEC') or die;

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
	 * @return array
	 */
	public static function getList(&$params)
	{
		mb_internal_encoding('UTF-8');

		$formats  = array('year' => 'Y', 'month' => 'F', 'day' => 'j', 'weekday' => 'l', 'md' => 'md');
		$defaults = array('year' => '&nbsp;', 'month' => '', 'day' => '?', 'weekday' => '', 'md' => '');

		$db     = JFactory::getDBO();
		$user   = JFactory::getUser();
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

		$status           = (int)$params->get('status', 2);
		$offset_days      = (int)$params->get('offset_days', 0);
		$max_title_length = (int)$params->get('cuttitle', '25');
		$orderdir         = $params->get('order', 0) ? 'ASC' : 'DESC';

		# date/time
		$dateFormat = $params->get('formatdate', '');
		$timeFormat = $params->get('formattime', '');
		$showtime   = $params->get('showtime', 0);
		$addSuffix  = empty($timeFormat); // if we use component's default time format we can also add corresponding suffix

		$now = JDate::getInstance();
		if ($offset_days > 0) {
			$now->add(new DateInterval('P'.$offset_days.'D'));
		} elseif ($offset_days < 0) {
			$now->sub(new DateInterval('P'.abs($offset_days).'D'));
		}
		$date = self::_format_date_fields($now->toSql(), $formats);
		$date['date'] = JEMOutput::formatdate($now, $dateFormat);
		$params->set('date', $date);
		$cur_md = $date['md'];

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

		if (empty($cur_md)) { // oops...
			return array();
		}

		switch ($status) {
		case 1: // published
			$published = 1;
			break;
		case 2: // archived
		default:
			$published = 2;
			break;
		case 3: // both
			$published = array(1, 2);
			break;
		}

		# filter by day + month
		$cal_from  = " IF(YEAR(IFNULL(a.enddates, a.dates)) > YEAR(a.dates)";
		$cal_from .= " , (DATE_FORMAT(a.dates, '%m%d') <= $cur_md) OR  ($cur_md <= DATE_FORMAT(IFNULL(a.enddates, a.dates), '%m%d'))";
		$cal_from .= " , (DATE_FORMAT(a.dates, '%m%d') <= $cur_md) AND ($cur_md <= DATE_FORMAT(IFNULL(a.enddates, a.dates), '%m%d'))";
		$cal_from .= " ) ";
		$cal_to    = false;

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

		if ($params->get('flyer_link_type', 0) == 1) {
			JHtml::_('behavior.modal', 'a.flyermodal');
		}

		####
		# Retrieve the available Events
		####
		$events = $model->getItems();

		$color = $params->get('color');
		$user_color = $params->get('usercolor', '#EEEEEE');

		# Loop through the result rows and prepare data
		$lists = array();
		$i     = -1; // it's easier to increment first

		// Don't shuffle original array to keep ordering of remaining events intact.
		$indices = array_keys($events);
		if (count($events) > $count) {
			if ($shuffle) {
				shuffle($indices);
			}
			array_splice($indices, $count);
		}

		foreach ($events as $key => $row)
		{
			if (!in_array($key, $indices)) {
				continue; // skip removed events
			}

			# create thumbnails if needed and receive imagedata
			$dimage = $row->datimage ? JEMImage::flyercreator($row->datimage, 'event') : null;
			$limage = $row->locimage ? JEMImage::flyercreator($row->locimage, 'venue') : null;

			#################
			## DEFINE LIST ##
			#################

			$lists[++$i] = new stdClass(); // add new object

			# check view access
			if (in_array($row->access, $levels)) {
				# We know that user has the privilege to view the event
				$lists[$i]->link = JRoute::_(JEMHelperRoute::getEventRoute($row->slug));
				$lists[$i]->linkText = JText::_('MOD_JEM_JUBILEE_READMORE');
			} else {
				$lists[$i]->link = JRoute::_('index.php?option=com_users&view=login');
				$lists[$i]->linkText = JText::_('MOD_JEM_JUBILEE_READMORE_REGISTER');
			}

			# cut titel
			$fulltitle = htmlspecialchars($row->title, ENT_COMPAT, 'UTF-8');
			if (mb_strlen($fulltitle) > $max_title_length) {
				$title = mb_substr($fulltitle, 0, $max_title_length) . '...';
			} else {
				$title = $fulltitle;
			}

			$lists[$i]->title       = $title;
			$lists[$i]->fulltitle   = $fulltitle;
			$lists[$i]->venue       = htmlspecialchars($row->venue, ENT_COMPAT, 'UTF-8');
			$lists[$i]->catname     = implode(", ", JemOutput::getCategoryList($row->categories, $params->get('linkcategory', 1)));
			$lists[$i]->state       = htmlspecialchars($row->state, ENT_COMPAT, 'UTF-8');
			$lists[$i]->city        = htmlspecialchars($row->city, ENT_COMPAT, 'UTF-8');
			$lists[$i]->eventlink   = $params->get('linkevent', 1) ? JRoute::_(JEMHelperRoute::getEventRoute($row->slug)) : '';
			$lists[$i]->venuelink   = $params->get('linkvenue', 1) ? JRoute::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : '';

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
			$lists[$i]->dateinfo    = JEMOutput::formatDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $dateFormat, $timeFormat, $addSuffix, $showtime);

			if ($dimage == null) {
				$lists[$i]->eventimage     = '';
				$lists[$i]->eventimageorig = '';
			} else {
				$lists[$i]->eventimage     = JUri::base(true).'/'.$dimage['thumb'];
				$lists[$i]->eventimageorig = JUri::base(true).'/'.$dimage['original'];
			}

			if ($limage == null) {
				$lists[$i]->venueimage     = '';
				$lists[$i]->venueimageorig = '';
			} else {
				$lists[$i]->venueimage     = JUri::base(true).'/'.$limage['thumb'];
				$lists[$i]->venueimageorig = JUri::base(true).'/'.$limage['original'];
			}

			$length = $params->get('descriptionlength');
			$length2 = 1;
			$etc = '...';
			$etc2 = JText::_('MOD_JEM_JUBILEE_NO_DESCRIPTION');

			//append <br /> tags on line breaking tags so they can be stripped below
			$description = preg_replace("'<(hr[^/>]*?/|/(div|h[1-6]|li|p|tr))>'si", "$0<br />", $row->introtext);

			//strip html tags but leave <br /> tags
			$description = strip_tags($description, "<br>");

			//switch <br /> tags to space character
			if ($params->get('br') == 0) {
				$description = str_replace('<br />',' ', $description);
			}
			//
			if (strlen($description) > $length) {
				$length -= strlen($etc);
				$description = preg_replace('/\s+?(\S+)?$/', '', substr($description, 0, $length+1));
				$lists[$i]->eventdescription = substr($description, 0, $length).$etc;
			} elseif (strlen($description) < $length2) {
				$length -= strlen($etc2);
				$description = preg_replace('/\s+?(\S+)?$/', '', substr($description, 0, $length+1));
				$lists[$i]->eventdescription = substr($description, 0, $length).$etc2;
			} else {
				$lists[$i]->eventdescription = $description;
			}

			$lists[$i]->readmore = strlen(trim($row->fulltext));

			$lists[$i]->colorclass = $color;
			if ($color == 'alpha') {
				$lists[$i]->color = $user_color;
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
			$yesterday       = strftime("%Y-%m-%d", $yesterday_stamp);
			$today_stamp     = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$today           = date('Y-m-d');
			$tomorrow_stamp  = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
			$tomorrow        = strftime("%Y-%m-%d", $tomorrow_stamp);

			$dates_stamp     = $row->dates ? strtotime($row->dates) : null;
			$enddates_stamp  = $row->enddates ? strtotime($row->enddates) : null;

			$times = $row->times; // show starttime by default

			//if datemethod show day difference
			if ($method == 2) {
				//check if today or tomorrow
				if ($row->dates == $today) {
					$date = JText::_('MOD_JEM_JUBILEE_TODAY');
				} elseif ($row->dates == $tomorrow) {
					$date = JText::_('MOD_JEM_JUBILEE_TOMORROW');
				} elseif ($row->dates == $yesterday) {
					$date = JText::_('MOD_JEM_JUBILEE_YESTERDAY');
				}
				//This one isn't very different from the DAYS AGO output but it seems
				//adequate to use a different language string here.
				//
				//the event has an enddate and it's earlier than yesterday
				elseif ($row->enddates && ($enddates_stamp < $yesterday_stamp)) {
					$years = JEMOutput::formatdate($today, 'Y') - JEMOutput::formatdate($row->enddates, 'Y');
					$date  = JText::sprintf($years === 1 ? 'MOD_JEM_JUBILEE_ENDED_YEAR_AGO' : 'MOD_JEM_JUBILEE_ENDED_YEARS_AGO', $years);
					// show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}
				//the event has an enddate and it's later than today but the startdate is earlier than today
				//means a currently running event
				elseif ($row->dates && $row->enddates && ($enddates_stamp > $today_stamp) && ($dates_stamp < $today_stamp)) {
					$years = JEMOutput::formatdate($today, 'Y') - JEMOutput::formatdate($row->dates, 'Y');
					$date  = JText::sprintf($years === 1 ? 'MOD_JEM_JUBILEE_STARTED_YEAR_AGO' : 'MOD_JEM_JUBILEE_STARTED_YEARS_AGO', $years);
				}
				//the events date is earlier than yesterday
				elseif ($row->dates && ($dates_stamp < $yesterday_stamp)) {
					$years = JEMOutput::formatdate($today, 'Y') - JEMOutput::formatdate($row->dates, 'Y');
					$date  = JText::sprintf($years === 1 ? 'MOD_JEM_JUBILEE_YEAR_AGO' : 'MOD_JEM_JUBILEE_YEARS_AGO', $years);
				}
				//the events date is later than tomorrow
				elseif ($row->dates && ($dates_stamp > $tomorrow_stamp)) {
					$years = JEMOutput::formatdate($row->dates, 'Y') - JEMOutput::formatdate($today, 'Y');
					$date  = JText::sprintf($years === 1 ? 'MOD_JEM_JUBILEE_YEAR_AHEAD' : 'MOD_JEM_JUBILEE_YEARS_AHEAD', $years);
				}
				else {
					$date = JEMOutput::formatDateTime('', ''); // Oops - say "Open date"
				}
			} else if ($method == 1) { // datemethod show date
			// TODO: check date+time to be more acurate
				//Upcoming multidayevent (From 16.10.2008 Until 18.08.2008)
				if (($dates_stamp >= $today_stamp) && ($enddates_stamp > $dates_stamp)) {
					$startdate = JEMOutput::formatdate($row->dates, $dateFormat);
					$enddate = JEMOutput::formatdate($row->enddates, $dateFormat);
					$date = JText::sprintf('MOD_JEM_JUBILEE_FROM_UNTIL', $startdate, $enddate);
					// additionally show endtime
					$endtimes = $row->endtimes;
				}
				//current multidayevent (Until 18.08.2008)
				elseif ($row->enddates && ($enddates_stamp >= $today_stamp) && ($dates_stamp < $today_stamp)) {
					$enddate = JEMOutput::formatdate($row->enddates, $dateFormat);
					$date = JText::sprintf('MOD_JEM_JUBILEE_UNTIL', $enddate);
					// show endtime instead of starttime
					$times = false;
					$endtimes = $row->endtimes;
				}
				//single day event
				else {
					$startdate = JEMOutput::formatdate($row->dates, $dateFormat);
					$date = JText::sprintf('MOD_JEM_JUBILEE_ON_DATE', $startdate);
					// additionally show endtime, but on single day events only to prevent user confusion
					if (empty($row->enddates)) {
						$endtimes = $row->endtimes;
					}
				}
			} else {
				$date = '';
			}
		}

		$time  = empty($times)    ? '' : JEMOutput::formattime($times, $timeFormat, $addSuffix);
		$time .= empty($endtimes) ? '' : ('&nbsp;-&nbsp;' . JEMOutput::formattime($row->endtimes, $timeFormat, $addSuffix));

		return array($date, $time);
	}

	/**
	 * Method to format different parts of a date as array
	 *
	 * @access public
	 * @param  mixed  date in form 'yyyy-mm-dd' or as JDate object
	 * @param  array  formats to get as assotiative array (e.g. 'day' => 'j'; see {@link PHP_MANUAL#date})
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