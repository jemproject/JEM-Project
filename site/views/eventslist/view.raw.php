<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE.'/components/com_jem/classes/iCalcreator.class.php';

jimport( 'joomla.application.component.view');

/**
 * ICS events list View class of the JEM component
 *
 * @package JEM
 * @since 1.1
 */
class JEMViewEventslist extends JViewLegacy
{
	/**
	 * Creates the output for the details view
	 *
 	 * @since 2.0
	 */
	function display($tpl = null)
	{		
		$mainframe = JFactory::getApplication();
    
		$offset = (float) $mainframe->getCfg('offset');
		$timezone_name = JEMHelper::getTimeZone($offset);
		$hours = ($offset >= 0) ? floor($offset) : ceil($offset);
		$mins = abs($offset - $hours) * 60;
		$utcoffset = sprintf('%+03d%02d00', $hours, $mins);
		
		$settings = JEMHelper::config();
		
		// Get data from the model
		$model = $this->getModel();
		$model->setLimit($settings->ical_max_items);
		$model->setLimitstart(0);
		$rows =  $model->getData();
				
    // initiate new CALENDAR
		$vcal = JEMHelper::getCalendarTool();
	//	$vcal->setProperty('unique_id', 'allevents@'.$mainframe->getCfg('sitename'));
		$vcal->setConfig( "filename", "events.ics" );
		
		foreach ( $rows as $row )
		{				
			JEMHelper::icalAddEvent($vcal, $row);	
		}
		$vcal->returnCalendar();                       // generate and redirect output to user browser
		echo $vcal->createCalendar(); // debug
	}
}
?>