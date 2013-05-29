<?php
/**
 * @version 1.9 $Id$
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

defined( '_JEXEC' ) or die;


/**
 * View class for the JEM Help screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewHelp extends JViewLegacy {

	public function display($tpl = null) {

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

		// Check for files in the actual language
		$langTag = $lang->getTag();

		if ( !JFolder::exists( JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag ) ) {
			$langTag = 'en-GB';		// use english as fallback
		}

		//search the keyword in the files
		$toc 		= JEMViewHelp::getHelpToc( $helpsearch );

		//assign data to template
		$this->langTag 		= $langTag;
		$this->helpsearch 	= $helpsearch;
		$this->toc 			= $toc;

		// add toolbar
		$this->addToolbar();
		
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

		if( !JFolder::exists( JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag ) ) {
			$langTag = 'en-GB';		// use english as fallback
		}
		$files = JFolder::files( JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag, '\.xml$|\.html$' );

		$toc = array();
		foreach ($files as $file) {
			$buffer = file_get_contents( JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag.'/'.$file );
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
	
	
	/*
	 * Add Toolbar
	*/
	
	protected function addToolbar()
	{
		
		//Create Submenu
		require_once JPATH_COMPONENT . '/helpers/helper.php';
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_HELP' ), 'help' );
		
	}
	
	
	
	
} // end of class
?>