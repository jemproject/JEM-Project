<?php
/**
 * @version $Id$
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

jimport( 'joomla.application.component.view');

/**
 * View class for the JEM Updatecheck screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewUpdatecheck extends JViewLegacy {

	function display($tpl = null) {

		$app 	   =  JFactory::getApplication();

		//initialise variables
		$document	= & JFactory::getDocument();

		//get vars
		$template	= $app->getTemplate();

		//add css
		$document->addStyleSheet('templates/'.$template.'/css/general.css');
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
		$updatedata      = & $this->get( 'Updatedata');

		//assign data to template
		$this->template		= $template;
		$this->updatedata	= $updatedata;

		parent::display($tpl);
	}
}