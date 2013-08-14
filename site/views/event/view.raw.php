<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport( 'joomla.application.component.view');

/**
 * RAW Event View class of the JEM component
 *
 * @package JEM
 * 
 */
class JEMViewEvent extends JViewLegacy
{
	/**
	 * Creates the output for the event view
	 *
	 */
	function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();
		$settings = JEMHelper::config();

		// Get data from the model
		$row     = $this->get('Event');
		$row->categories = $this->get('Categories');
		$row->id = $row->did;

		$vcal = JEMHelper::getCalendarTool();  // initiate new CALENDAR
	//	$vcal->setProperty('unique_id', 'event'.$row->did.'@'.$mainframe->getCfg('sitename'));
		$vcal->setConfig( "filename", "event".$row->did.".ics" );

		JEMHelper::icalAddEvent($vcal, $row);

		$vcal->returnCalendar();                       // generate and redirect output to user browser
		echo $vcal->createCalendar(); // debug
	}
}
?>