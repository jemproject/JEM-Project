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

 if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the EventList CSS edit screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewEditcss extends JViewLegacy {

	function display($tpl = null) {

		$app =  JFactory::getApplication();

		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();
		
		//only admins have access to this view
		if (!JFactory::getUser()->authorise('core.manage')) {
			JError::raiseWarning( 'SOME_ERROR_CODE', JText::_( 'ALERTNOTAUTH'));
			$app->redirect( 'index.php?option=com_jem&view=eventlist' );
		}

		//get vars
		$filename	= 'eventlist.css';
		$path		= JPATH_SITE.DS.'components'.DS.'com_jem'.DS.'assets'.DS.'css';
		$css_path	= $path.DS.$filename;

		//create the toolbar
		JToolBarHelper::title( JText::_( 'EDIT CSS' ), 'cssedit' );
		JToolBarHelper::apply( 'applycss' );
		JToolBarHelper::spacer();
		JToolBarHelper::save( 'savecss' );
		JToolBarHelper::spacer();
		JToolBarHelper::cancel();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'el.editcss', true );
		
		JRequest::setVar( 'hidemainmenu', 1 );

		//add css to document
		$document->addStyleSheet('components/com_jem/assets/css/eventlistbackend.css');

		//read the the stylesheet
		jimport('joomla.filesystem.file');
		$content = JFile::read($css_path);
		
		jimport('joomla.client.helper');
		$ftp = JClientHelper::setCredentialsFromRequest('ftp');

		if ($content !== false)
		{
			$content = htmlspecialchars($content, ENT_COMPAT, 'UTF-8');
		}
		else
		{
			$msg = JText::sprintf('FAILED TO OPEN FILE FOR WRITING', $css_path);
			$app->redirect('index.php?option=com_jem', $msg);
		}

		//assign data to template
		$this->assignRef('css_path'		, $css_path);
		$this->assignRef('content'		, $content);
		$this->assignRef('filename'		, $filename);
		$this->assignRef('ftp'			, $ftp);
		

		parent::display($tpl);
	}
}