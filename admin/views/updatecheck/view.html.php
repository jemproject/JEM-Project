<?php
/**
 * $Id$
 * @package Joomla
 * @subpackage Eventlist
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * Eventlist is maintained by the community located at
 * http://www.joomlaeventmanager.net
 *
 * Eventlist is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Eventlist is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the EventList Updatecheck screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewUpdatecheck extends JViewLegacy {

	function display($tpl = null) {

		$app 	   =  JFactory::getApplication();

		//initialise variables
		$document	= & JFactory::getDocument();

		//get vars
		$template	= $app->getTemplate();

		//add css
		$document->addStyleSheet('templates/'.$template.'/css/general.css');
		$document->addStyleSheet('components/com_eventlist/assets/css/eventlistbackend.css');

		//Get data from the model
		$updatedata      = & $this->get( 'Updatedata');

		//assign data to template
		$this->assignRef('template'		, $template);
		$this->assignRef('updatedata'	, $updatedata);

		parent::display($tpl);
	}
}