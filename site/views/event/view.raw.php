<?php
/**
 * @version 1.9.5
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
		// Get data from the model
		$row 				= $this->get('Item');
		$row->categories 	= $this->get('Categories');
		$row->id 			= $row->did;
		$row->slug			= $row->alias ? ($row->id.':'.$row->alias) : $row->id;
		
		// initiate new CALENDAR
		$vcal = JEMHelper::getCalendarTool();
		$vcal->setConfig( "filename", "event".$row->did.".ics" );

		JEMHelper::icalAddEvent($vcal, $row);

		// generate and redirect output to user browser
		$vcal->returnCalendar();
		echo $vcal->createCalendar(); // debug
	}
}
?>