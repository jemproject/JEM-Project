<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * RAW Details View class of the JEM component
 *
 * @package JEM
 * @since 1.1
 */
class JEMViewDetails extends JViewLegacy
{
	/**
	 * Creates the output for the details view
	 *
 	 * @since 2.0
	 */
	function display($tpl = null)
	{		
		$mainframe = JFactory::getApplication();		
		$settings = ELHelper::config();
		
		// Get data from the model
		$row     = $this->get('Details');
		$row->categories = $this->get('Categories');
		$row->id = $row->did;
		
		$vcal = ELHelper::getCalendarTool();  // initiate new CALENDAR
	//	$vcal->setProperty('unique_id', 'event'.$row->did.'@'.$mainframe->getCfg('sitename'));
		$vcal->setConfig( "filename", "event".$row->did.".ics" );

		ELHelper::icalAddEvent($vcal, $row);		

		$vcal->returnCalendar();                       // generate and redirect output to user browser
		echo $vcal->createCalendar(); // debug
	}
}
?>