<?php
/**
 * @version 1.9.6
 * @package JEM
 * @subpackage JEM Calendar Module
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

abstract class modjemcalqhelper
{
	public static function getdays ($greq_year, $greq_month, &$params)
	{
		$db			= JFactory::getDBO();

		# Retrieve Eventslist model for the data
		$model = JModelLegacy::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));

		# Set params for the model
		//$app = JFactory::getApplication();
		//$appParams = $app->getParams('com_jem');
		$model->setState('params', $params);

		# Access filter
		$model->setState('filter.access', true);

		$user		= JFactory::getUser();
		$levels		= $user->getAuthorisedViewLevels();
		$settings 	= JEMHelper::globalattribs();

		$catid 				= trim($params->get('catid'));
		$venid 				= trim($params->get('venid'));
		$StraightToDetails	= $params->get('StraightToDetails', '1');
		$DisplayCat			= $params->get('DisplayCat', '0');
		$DisplayVenue		= $params->get('DisplayVenue', '0');
		$ArchivedEvents		= $params->get('ArchivedEvents', '0');
		$CurrentEvents		= $params->get('CurrentEvents', '1');
		$FixItemID			= $params->get('FixItemID', '0');
		$defaultItemid	 	= $settings->get('default_Itemid','');

		# filter category's
		if ($catid) {
			$ids = explode(',', $catid);
			$ids = JArrayHelper::toInteger($ids);
			//$categories = ' AND c.id IN (' . implode(',', $ids) . ')';
			$model->setState('filter.category_id',$ids);
			$model->setState('filter.category_id.include',true);
		}

		# filter venue's
		if ($venid) {
			$ids = explode(',', $venid);
			$ids = JArrayHelper::toInteger($ids);
			/*$venues = ' AND l.id IN (' . implode(',', $ids) . ')';*/
			$model->setState('filter.venue_id',$ids);
			$model->setState('filter.venue_id.include',true);
		}

		# filter published
		#  0: unpublished
		#  1: published
		#  2: archived
		# -2: trashed

		if ($CurrentEvents && $ArchivedEvents) {
			$model->setState('filter.published',array(1,2));
		} else {
			if ($CurrentEvents == 1) {
				/*$wherestr = ' WHERE a.published = 1';*/
				$model->setState('filter.published',1);
			}

			# filter archived
			if ($ArchivedEvents == 1) {
			/*$wherestr = ' WHERE a.published = 2';*/
				$model->setState('filter.published',2);
			}
		}

		$model->setState('filter.groupby','a.id');

		# Retrieve the available Items
		$events = $model->getItems();

		# create an array to catch days
		$days = array();

		foreach ($events as $index => $event) {
			# adding categories
			$nr 		= count($event->categories);
			$catname 	= '';
			$ix 		= 0;

			# walk through categories assigned to an event
			foreach($event->categories AS $category) {
				$catname .= htmlspecialchars($category->catname);

				$ix++;
				if ($ix != $nr) {
					$catname .= ', ';
				}
			}

			// Cope with no end date set i.e. set it to same as start date
			if (is_null($event->enddates)) {
				$eyear = $event->created_year;
				$emonth = $event->created_month;
				$eday = $event->created_day;
			} else {
				list($eyear, $emonth, $eday) = explode('-', $event->enddates);
			}
			// The two cases for roll over the year end with an event that goes across the year boundary.
			if ($greq_year < $eyear) {
				$emonth = $emonth + 12;
			}

			if ($event->created_year < $greq_year) {
				$event->created_month = $event->created_month - 12;
			}

			if (($greq_year >= $event->created_year) && ($greq_year <= $eyear)
					&& ($greq_month >= $event->created_month) && ($greq_month <= $emonth)) {
				// Set end day for current month

				if ($emonth > $greq_month) {
					$emonth = $greq_month;

					// $eday = cal_days_in_month(CAL_GREGORIAN, $greq_month,$greq_year);
					$eday = date('t', mktime(0, 0, 0, $greq_month, 1, $greq_year));
				}

				// Set start day for current month
				if ($event->created_month < $greq_month) {
					$event->created_month = $greq_month;
					$event->created_day = 1;
				}
				$stod = 1;

				for ($count = $event->created_day; $count <= $eday; $count++) {

					$uxdate = mktime(0, 0, 0, $greq_month, $count, $greq_year);
					$tdate = strftime('%Y%m%d',$uxdate);// Toni change Joomla 1.5
// 					$created_day = $count;
// 					$tt = $days[$count][1];
// 					if (strlen($tt) == 0)

					if (empty($days[$count][1])) {
						$title = htmlspecialchars($event->title);
						if ($DisplayCat == 1) {
							$title = $title . '&nbsp;(' . $catname . ')';
						}
						if ($DisplayVenue == 1) {
							if (isset($event->venue)) {
								$title = $title . '&nbsp;@' . htmlspecialchars($event->venue);
							}
						}
						$stod = 1;
					} else {
						$tt = $days[$count][1];
						$title = $tt . '+%+%+' . htmlspecialchars($event->title);
						if ($DisplayCat == 1) {
							$title = $title . '&nbsp;(' . $catname . ')';
						}
						if ($DisplayVenue == 1) {
							if (isset($event->venue)) {
								$title = $title . '&nbsp;@' . htmlspecialchars($event->venue);
							}
						}
						$stod = 0;
					}
					if (($StraightToDetails == 1) and ($stod == 1)) {
						if ($FixItemID == 0) {
							$link = JRoute::_(JEMHelperRoute::getEventRoute($event->slug));
						} else {
							//Create the link - copied from Jroute
							$evlink = JEMHelperRoute::getEventRoute($event->slug).'&Itemid='.$FixItemID;
							$link = JRoute::_($evlink);
						}
					} else {
						// @todo fix the getroute link
						if ($FixItemID == 0) {
							if ($defaultItemid)
							{
								$evlink = 'index.php?option=com_jem&view=day&id='. $tdate.'&Itemid='.$defaultItemid;
							} else {
								$evlink = 'index.php?option=com_jem&view=day&id='. $tdate;
							}
							$link = JRoute::_($evlink);
							//$link = JEMHelperRoute::getRoute($tdate, 'day');
						} else {
							//Create the link - copied from Jroute
							$evlink = 'index.php?option=com_jem&view=day&id='. $tdate.'&Itemid='.$FixItemID;
							$link = JRoute::_($evlink);
						}
					}
				$days[$count] = array($link,$title);
				}
			}
		// End of Toni modification


			# check if the item-categories is empty, if so the user has no access to that event at all.
			if (empty($event->categories)) {
				unset ($events[$index]);
			}
		} // end foreach
		return $days;
	}
}
?>