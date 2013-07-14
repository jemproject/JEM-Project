<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the Categoryevents View
 *
 * @package JEM
 * @since 1.1
 */
class JEMViewCategoryevents extends JViewLegacy
{
	/**
	 * Creates the output for the Categoryevents view
	 *
 	 * @since 2.0
	 */
	function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();
		$settings = JEMHelper::config();

		// Get data from the model
		$model = $this->getModel();
		$model->setLimit($settings->ical_max_items);
		$model->setLimitstart(0);
		$rows = $model->getData();

		$catid = JRequest::getInt('id');

		$vcal = JEMHelper::getCalendarTool();                          // initiate new CALENDAR
		//$vcal->setProperty('unique_id', 'category'.$catid.'@'.$mainframe->getCfg('sitename'));
		$vcal->setConfig( "filename", "category".$catid.".ics" );

		foreach ( $rows as $row )
		{
			JEMHelper::icalAddEvent($vcal, $row);
		}
		$vcal->returnCalendar();                       // generate and redirect output to user browser
		echo $vcal->createCalendar(); // debug
	}
}
?>