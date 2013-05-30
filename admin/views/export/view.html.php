<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright(C) 2013-2013 joomlaeventmanager.net
 * @copyright(C) 2005-2009 Christoph Lukes
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

defined('_JEXEC') or die;



/**
 * View class(based on the import screen)
 * 
 * @package JEM
 * @since 0.9
 */
class JEMViewExport extends JViewLegacy {

	public function display($tpl = null) {
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= JFactory::getDocument();
		$user 		= JFactory::getUser();

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		// add toolbar
		$this->addToolbar();
		
		parent::display($tpl);

	}
	
	/*
	 * Add Toolbar
	*/
	
	protected function addToolbar()
	{
		
		require_once JPATH_COMPONENT . '/helpers/helper.php';
		
		//build toolbar
		JToolBarHelper::back();
		JToolBarHelper::title(JText::_('COM_JEM_EXPORT'), 'tableexport');

	}
	
	
	
} // end of class
?>
