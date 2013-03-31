<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.controller');

/**
 * EventList Component Archive Controller
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListControllerPlugins extends EventListController
{
	/**
	 * Constructor
	 *
	 *@since 0.9
	 */
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Handles Plugin screen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function plugins()
	{
		$db =& JFactory::getDBO();
		
		$query = 'SELECT COUNT(*)'
				. ' FROM #__plugins AS p'
				. ' WHERE p.folder = '.$db->Quote("eventlist");
				;
		$db->setQuery( $query );
		
		$total = $db->loadResult();
		
		//any plugins installed? if not redirect to installation screen
		if ($total > 0){
			$link = 'index.php?option=com_plugins&filter_type=eventlist';
			$msg = "";
		} else {
			$link = 'index.php?option=com_installer';
			$msg = JText::_("NO EVENTLIST PLUGINS INSTALLED");
		}
		$this->setRedirect($link, $msg);
	}
	
}
?>