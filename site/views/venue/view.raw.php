<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML View class for the Venue View
 *
 * @package JEM
 *
 */
class JEMViewVenue extends JViewLegacy
{
	/**
	 * Creates the output for the Venue view
	 */
	function display($tpl = null)
	{
		$settings = JEMHelper::config();

		// Get data from the model
		$model = $this->getModel();
		$model->setLimit($settings->ical_max_items);
		$model->setLimitstart(0);
		$rows = $model->getData();

		$venueid = JRequest::getInt('id');

		// initiate new CALENDAR
		$vcal = JEMHelper::getCalendarTool();
		// $vcal->setProperty('unique_id', 'category'.$catid.'@'.$mainframe->getCfg('sitename'));
		$vcal->setConfig("filename", "venue".$venueid.".ics");

		foreach ($rows as $row) {
			JEMHelper::icalAddEvent($vcal, $row);
		}
		// generate and redirect output to user browser
		$vcal->returnCalendar();
		echo $vcal->createCalendar(); // debug
	}
}
?>