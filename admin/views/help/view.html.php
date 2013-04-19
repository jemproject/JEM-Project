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
 
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the JEM Help screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewHelp extends JViewLegacy {

	function display($tpl = null) {

		//Load filesystem folder and pane behavior
		jimport('joomla.html.pane');
		jimport( 'joomla.filesystem.folder' );

		//initialise variables
		$document		=  JFactory::getDocument();
		$lang 			=  JFactory::getLanguage();
		$user			=  JFactory::getUser();

		//get vars
		$helpsearch 	= JRequest::getString( 'search' );

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_JEM' ), 'index.php?option=com_jem');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTS' ), 'index.php?option=com_jem&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_VENUES' ), 'index.php?option=com_jem&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_CATEGORIES' ), 'index.php?option=com_jem&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'index.php?option=com_jem&view=archive');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_GROUPS' ), 'index.php?option=com_jem&view=groups');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_HELP' ), 'index.php?option=com_jem&view=help', true);
		if (JFactory::getUser()->authorise('core.manage')) {
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_SETTINGS' ), 'index.php?option=com_jem&controller=settings&task=edit');
		}

		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_HELP' ), 'help' );

		// Check for files in the actual language
		$langTag = $lang->getTag();

		if ( !JFolder::exists( JPATH_SITE . DS.'administrator'.DS.'components'.DS.'com_jem/help'.DS .$langTag ) ) {
			$langTag = 'en-GB';		// use english as fallback
		}

		//search the keyword in the files
		$toc 		= JEMViewHelp::getHelpToc( $helpsearch );

		//assign data to template
		$this->langTag 		= $langTag;
		$this->helpsearch 	= $helpsearch;
		$this->toc 			= $toc;

		parent::display($tpl);
	}

	/**
 	* Compiles the help table of contents
 	* Based on the Joomla admin component
 	*
 	* @param string A specific keyword on which to filter the resulting list
 	*/
	function getHelpTOC( $helpsearch )
	{
		$lang = JFactory::getLanguage();
		jimport( 'joomla.filesystem.folder' );

		// Check for files in the actual language
		$langTag = $lang->getTag();

		if( !JFolder::exists( JPATH_SITE . DS.'administrator'.DS.'components'.DS.'com_jem'.DS.'help'.DS .$langTag ) ) {
			$langTag = 'en-GB';		// use english as fallback
		}
		$files = JFolder::files( JPATH_SITE . DS.'administrator'.DS.'components'.DS.'com_jem'.DS.'help'.DS.$langTag, '\.xml$|\.html$' );

		$toc = array();
		foreach ($files as $file) {
			$buffer = file_get_contents( JPATH_SITE . DS.'administrator'.DS.'components'.DS.'com_jem'.DS.'help'.DS.$langTag.DS.$file );
			if (preg_match( '#<title>(.*?)</title>#', $buffer, $m )) {
				$title = trim( $m[1] );
				if ($title) {
					if ($helpsearch) {
						if (JString::strpos( strip_tags( $buffer ), $helpsearch ) !== false) {
							$toc[$file] = $title;
						}
					} else {
						$toc[$file] = $title;
					}
				}
			}
		}
		asort( $toc );
		return $toc;
	}
}
?>