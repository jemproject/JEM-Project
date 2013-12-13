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
 * HTML View class for the Category View
 *
 * @package JEM
 *         
 */
class JEMViewCategory extends JViewLegacy
{

	/**
	 * Creates the output for the Category view
	 */
	function display($tpl = null)
	{
		$settings = JEMHelper::config();
		
		// Get data from the model
		$model = $this->getModel();
		$model->setLimit($settings->ical_max_items);
		$model->setLimitstart(0);
		$rows = $model->getData();
		
		$catid = JRequest::getInt('id');
		
		// initiate new CALENDAR
		$vcal = JEMHelper::getCalendarTool();
		$vcal->setConfig("filename", "category" . $catid . ".ics");
		
		foreach ($rows as $row) {
			JEMHelper::icalAddEvent($vcal, $row);
		}
		// generate and redirect output to user browser
		$vcal->returnCalendar();
		echo $vcal->createCalendar(); // debug
	}
}
?>