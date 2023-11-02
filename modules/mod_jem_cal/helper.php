<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @subpackage JEM Calendar Module
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2008 Toni Smillie www.qivva.com
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Original Eventlist calendar from Christoph Lukes
 * PHP Calendar (version 2.3), written by Keith Devens
 * https://keithdevens.com/software/php_calendar
 * see example at https://keithdevens.com/weblog
 * License: https://keithdevens.com/software/license
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

abstract class ModJemCalHelper extends JModuleHelper
{
	/**
	 * Get module by id
	 *
	 * Same as JModuleHelper::getModule() but checking id instead of name.
	 * This is required because multiple instances with different settings
	 * can be shown on same position. The only unique thing is the id.
	 *
	 * @param   int  $id  The id of the module
	 *
	 * @return  stdClass  The Module object or null
	 *
	 * @since   2.2.3
	 */
	public static function &getModuleById($id)
	{
		$result = null;
		$modules =& static::load();
		$total = count($modules);

		for ($i = 0; $id && $i < $total; $i++)
		{
			# Match the id of the module
			if ($modules[$i]->id == $id)
			{
				# Found it
				$result = &$modules[$i];
				break;
			}
		}

		return $result;
	}

	/**
	 * Get rendered content.
	 *
	 * This function is called by com_ajax if user navigates through
	 * calendar module's months. Url query must contain id, month, and year.
	 *
	 * @return  string  The rendered content.
	 *
	 * @since   2.2.3
	 */
	public static function getAjax()
	{
		$app     = Factory::getApplication();
		$modid   = $app->input->getInt('modjemcal_id');
		# JModuleHelper doesn't provide module by id - but we
		$module = self::getModuleById($modid);
		if (!empty($module->id) && ((int)$module->id === $modid)) {
			# Indicate ajax mode where some parts will be suppressed or rendered different.
			$module->in_ajax_call = true;
			return self::renderModule($module);
		}
	}

	public static function getDays($greq_year, $greq_month, &$params)
	{
		# Retrieve Eventslist model for the data
		$model = JModelLegacy::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));

		# Set params for the model
		$model->setState('params', $params);

		$db       = Factory::getDbo();
		$user     = JemFactory::getUser();
		$levels   = $user->getAuthorisedViewLevels();
		$settings = JemHelper::globalattribs();

		$StraightToDetails = $params->get('StraightToDetails', '1');
		$DisplayCat        = $params->get('DisplayCat', '0');
		$DisplayVenue      = $params->get('DisplayVenue', '0');
		$ArchivedEvents    = $params->get('ArchivedEvents', '0');
		$CurrentEvents     = $params->get('CurrentEvents', '1');
		$FixItemID         = $params->get('FixItemID', '0');
		$defaultItemid     = $settings->get('default_Itemid', '');
		$daylinkparams     = ''; // collects additional params for link to day view
		$max_title_len     = (int)$params->get('event_cut_title', '25');

		# Only select events within specified date range. (choosen month)
		$monthstart = mktime(0, 0,  1, $greq_month,     1, $greq_year);
		$monthend   = mktime(0, 0, -1, $greq_month + 1, 1, $greq_year);
		$filter_date_from = $db->Quote(date('Y-m-d', $monthstart));
		$filter_date_to   = $db->Quote(date('Y-m-d', $monthend));
		$where_from = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $filter_date_from . ') >= 0';
		$model->setState('filter.calendar_from', $where_from);
		$where_to = ' DATEDIFF(a.dates, ' . $filter_date_to . ') <= 0';
		$model->setState('filter.calendar_to', $where_to);

		# Clean parameter data
		$catids   = JemHelper::getValidIds($params->get('catid'));
		$venids   = JemHelper::getValidIds($params->get('venid'));

		# Filter categories
		if ($catids) {
			$model->setState('filter.category_id', $catids);
			$model->setState('filter.category_id.include', true);
			$daylinkparams .= '&catids=' . implode(',', $catids);
		}

		# Filter venues
		if ($venids) {
			$model->setState('filter.venue_id', $venids);
			$model->setState('filter.venue_id.include', true);
			$daylinkparams .= '&locids=' . implode(',', $venids);
		}

		# Filter published
		#  0: unpublished
		#  1: published
		#  2: archived
		# -2: trashed

		if ($CurrentEvents && $ArchivedEvents) {
			$model->setState('filter.published',array(1,2));
			$daylinkparams .= '&pub=1,2';
		} else {
			if ($CurrentEvents == 1) {
				$model->setState('filter.published',1);
				$daylinkparams .= '&pub=1';
			}

			# Filter archived
			if ($ArchivedEvents == 1) {
				$model->setState('filter.published',2);
				$daylinkparams .= '&pub=2';
			}
		}

		$model->setState('filter.groupby','a.id');

		# Retrieve the available Items
		$events = $model->getItems();

		# Create an array to catch days
		$days = array();

		foreach ($events as $index => $event) {
			# Adding categories
			$nr      = is_array($event->categories) ? count($event->categories) : 0;
			$catname = '';
			$ix      = 0;

			# Walk through categories assigned to an event
			foreach($event->categories AS $category) {
				$catname .= htmlspecialchars($category->catname, ENT_COMPAT, 'UTF-8');

				$ix++;
				if ($ix != $nr) {
					$catname .= ', ';
				}
			}

			# Cope with no end date set i.e. set it to same as start date
			if (is_null($event->enddates)) {
				$eyear = $event->created_year;    # Note: "created_*" refers to start date
				$emonth = $event->created_month;
				$eday = $event->created_day;
			} else {
				list($eyear, $emonth, $eday) = explode('-', $event->enddates);
			}
			# The two cases for roll over the year end with an event that goes across the year boundary.
			if ($greq_year < $eyear) {
				$emonth = $emonth + 12;
			}

			if ($event->created_year < $greq_year) {
				$event->created_month = $event->created_month - 12;
			}

			if (($greq_year >= $event->created_year) && ($greq_year <= $eyear) &&
			    ($greq_month >= $event->created_month) && ($greq_month <= $emonth))
			{
				//JemHelper::addLogEntry("mod_jem_cal[$params->module_id] : Show event $event->title on $event->dates - $event->enddates");

				# Set end day for current month
				if ($emonth > $greq_month) {
					$emonth = $greq_month;

					$eday = date('t', mktime(0, 0, 0, $greq_month, 1, $greq_year));
				}

				# Set start day for current month
				if ($event->created_month < $greq_month) {
					$event->created_month = $greq_month;
					$event->created_day = 1;
				}
				$stod = 1;

				for ($count = $event->created_day; $count <= $eday; $count++) {

					$uxdate = mktime(0, 0, 0, $greq_month, $count, $greq_year);
					$tdate = date('Ymd',$uxdate);// Toni change Joomla 1.5

					if (empty($days[$count][1])) {
						$cut = ($max_title_len > 0) && (($l = mb_strlen($event->title)) > $max_title_len);
						$title = htmlspecialchars($cut ? mb_substr($event->title,  0, $max_title_len) . '...' : $event->title, ENT_COMPAT, 'UTF-8');
						if ($DisplayCat == 1) {
							$title = $title . ' (' . $catname . ')';
						}
						if ($DisplayVenue == 1) {
							if (isset($event->venue)) {
								$title = $title . ' @' . htmlspecialchars($event->venue, ENT_COMPAT, 'UTF-8');
							}
						}
						$stod = 1;
					} else {
						$tt = $days[$count][1];
						$cut = ($max_title_len > 0) && (($l = mb_strlen($event->title)) > $max_title_len);
						$title = $tt . '+%+%+' . htmlspecialchars($cut ? mb_substr($event->title,  0, $max_title_len) . '...' : $event->title, ENT_COMPAT, 'UTF-8');
						if ($DisplayCat == 1) {
							$title = $title . ' (' . $catname . ')';
						}
						if ($DisplayVenue == 1) {
							if (isset($event->venue)) {
								$title = $title . ' @' . htmlspecialchars($event->venue, ENT_COMPAT, 'UTF-8');
							}
						}
						$stod = 0;
					}
					if (($StraightToDetails == 1) and ($stod == 1)) {
						if ($FixItemID == 0) {
							$link = Route::_(JemHelperRoute::getEventRoute($event->slug));
						} else {
							# Create the link - copied from Route
							$evlink = JemHelperRoute::getEventRoute($event->slug).'&Itemid='.$FixItemID;
							$link = Route::_($evlink);
						}
					} else {
						/// @todo fix the getroute link
						if ($FixItemID == 0) {
							if ($defaultItemid)
							{
								$evlink = 'index.php?option=com_jem&view=day&id=' . $tdate . $daylinkparams . '&Itemid=' . $defaultItemid;
							} else {
								$evlink = 'index.php?option=com_jem&view=day&id=' . $tdate . $daylinkparams;
							}
							$link = Route::_($evlink);
							//$link = JemHelperRoute::getRoute($tdate, 'day');
						} else {
							# Create the link - copied from Route
							$evlink = 'index.php?option=com_jem&view=day&id=' . $tdate . $daylinkparams . '&Itemid=' . $FixItemID;
							$link = Route::_($evlink);
						}
					}
					$days[$count] = array($link,$title);
				}
			}
			// End of Toni modification
			else {
				JemHelper::addLogEntry("mod_jem_cal[$params->module_id] : Skip event $event->title on $event->dates - $event->enddates");
			}

			# Check if the item-categories is empty, if so the user has no access to that event at all.
			if (empty($event->categories)) {
				unset ($events[$index]);
			}
		} // end foreach

		return $days;
	}
}
