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

jimport( 'joomla.application.component.view');

/**
 * View class for the EventList attendee screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 1.1
 */
class EventListViewAttendee extends JView {

	function display($tpl = null)
	{
		//initialise variables
		$editor 	= & JFactory::getEditor();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$app 		= & JFactory::getApplication();
		
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		//get vars
		$cid       = JRequest::getVar( 'cid' );
		$event_id  = JRequest::getInt( 'id' );

		//add css to document
		$document->addStyleSheet('components/com_eventlist/assets/css/eventlistbackend.css');

		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'EDIT ATTENDEE' ), 'users' );

		} else {
			JToolBarHelper::title( JText::_( 'ADD ATTENDEE' ), 'users' );
		}
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::spacer();
		JToolBarHelper::cancel();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'el.editattendee', true );

		//Get data from the model
		$model		= & $this->getModel();
		$row     	= & $this->get( 'Data' );

		// fail if checked out not by 'me'
		if ($row->id) {
//			if ($model->isCheckedOut( $user->get('id') )) {
//				JError::raiseWarning( 'SOME_ERROR_CODE', $row->catname.' '.JText::_( 'EDITED BY ANOTHER ADMIN' ));
//				$app->redirect( 'index.php?option=com_eventlist&view=attendees&id='.$event_id );
//			}
		}

		//build selectlists
		$lists = array();
		$lists['users'] = JHTML::_('list.users', 'uid', $row->uid, false, NULL, 'name', 0);

		//assign data to template
		$this->assignRef('lists'  , $lists);
		$this->assignRef('row'    , $row);
		$this->assignRef('event'  , $event_id);

		parent::display($tpl);
	}
}
?>