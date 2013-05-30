<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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

defined( '_JEXEC' ) or die;


/**
 * View class for the JEM editgroup screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewGroup extends JViewLegacy {

	public function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		//Load pane behavior
		jimport('joomla.html.pane');

		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();

		//get vars
		$template		= $app->getTemplate();
		$cid 			= JRequest::getInt( 'cid' );

		//add css
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
		$model				=  $this->getModel();
		$row      			=  $this->get( 'Data');

		//sticky forms
		/*$session = JFactory::getSession();
		if ($session->has('groupform', 'com_jem')) {
			$groupform 	= $session->get('groupform', 0, 'com_jem');
			$maintainers = $groupform['maintainers'];
			//TODO: refactor model to make this work
		} else {		*/
			$maintainers 		=  $this->get( 'Members');
		//	}
		$available_users 	=  $this->get( 'Available');

		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_('COM_JEM_EDITED_BY_ANOTHER_ADMIN'));
				$app->redirect( 'index.php?option=com_jem&view=groups' );
			}
		}

		//make data safe
		JFilterOutput::objectHTMLSafe( $row );

		//create selectlists
		$lists = array();
		$lists['maintainers']		= JHTML::_('select.genericlist', $maintainers, 'maintainers[]', 'class="inputbox" size="20" onDblClick="moveOptions(document.adminForm[\'maintainers[]\'], document.adminForm[\'available_users\'])" multiple="multiple" style="padding: 6px; width: 250px;"', 'value', 'text' );
		$lists['available_users']	= JHTML::_('select.genericlist', $available_users, 'available_users', 'class="inputbox" size="20" onDblClick="moveOptions(document.adminForm[\'available_users\'], document.adminForm[\'maintainers[]\'])" multiple="multiple" style="padding: 6px; width: 250px;"', 'value', 'text' );

		//assign data to template
		$this->row 			= $row;
		$this->template 	= $template;
		$this->lists 		= $lists;

		// add toolbar
		$this->addToolbar();
		
		parent::display($tpl);
	}
	
	
	/*
	 * Add Toolbar
	*/
	
	protected function addToolbar()
	{
		
		//get vars
		$cid 			= JRequest::getInt( 'cid' );
		
		//build toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'COM_JEM_EDIT_GROUP' ), 'groupedit' );
			JToolBarHelper::spacer();
		} else {
			JToolBarHelper::title( JText::_( 'COM_JEM_ADD_GROUP' ), 'groupedit' );
			JToolBarHelper::spacer();
		}
		JToolBarHelper::save();
		JToolBarHelper::spacer();
		JToolBarHelper::cancel();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'editgroup', true );
		
	}
	
	
} // end of class
?>