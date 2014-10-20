<?php
/**
 * @version 2.0.2
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * Venue-Raw 
 */
class JemViewVenue extends JViewLegacy
{
	/**
	 * Creates the output for the Venue view
	 */
	function display($tpl = null)
	{
		$settings 	= JemHelper::config();
		$settings2	= JemHelper::globalattribs();
		$app 		= JFactory::getApplication();
		$jinput 	= JFactory::getApplication()->input;

		if ($settings2->get('global_show_ical_icon','0')==1) {
			// Get data from the model
			$model = $this->getModel();
			$model->setLimit($settings->ical_max_items);
			$model->setLimitstart(0);
			$rows = $model->getItems();
			$venueid = $jinput->getInt('id');

			// initiate new CALENDAR
			$vcal = JemHelper::getCalendarTool();
			// $vcal->setProperty('unique_id', 'category'.$catid.'@'.$mainframe->getCfg('sitename'));
			$vcal->setConfig("filename", "venue".$venueid.".ics");

			if (!empty($rows)) {
				foreach ($rows as $row) {
					JemHelper::icalAddEvent($vcal, $row);
				}
			}

			// generate and redirect output to user browser
			$vcal->returnCalendar();
		} else {
			return;
		}
	}
}
?>