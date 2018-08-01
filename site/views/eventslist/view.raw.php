<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * Raw: Eventlist
 */
class JemViewEventslist extends JViewLegacy
{
	/**
	 * Creates the output for the Eventslist view
	 */
	public function display($tpl = null)
	{
		$settings  = JemHelper::config();
		$settings2 = JemHelper::globalattribs();

		if ($settings2->get('global_show_ical_icon','0')==1) {
			// Get data from the model
			$model = $this->getModel();
			$model->setState('list.start',0);
			$model->setState('list.limit',$settings->ical_max_items);
			$rows = $model->getItems();

			// initiate new CALENDAR
			$vcal = JemHelper::getCalendarTool();
			$vcal->setConfig("filename", "events.ics");

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
