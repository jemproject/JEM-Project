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

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML View class for the Editevents View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewEditvenue extends JViewLegacy
{
	/**
	 * Creates the output for venue submissions
	 *
	 * @since 0.5
	 * @param int $tpl
	 */
	function display( $tpl=null )
	{
		$app = JFactory::getApplication();;

		$user = JFactory::getUser();
	
		//redirect if not logged in
		if ( !$user->get('id') ) {
			$app->enqueueMessage(JText::_('COM_JEM_NEED_LOGGED_IN'), 'error');
			return false;
		}

		$editor 	= JFactory::getEditor();
		$doc 		= JFactory::getDocument();
		$jemsettings = JEMHelper::config();

		// Get requests
		$id				= JRequest::getInt('id');

		//Get Data from the model
		$row 		= $this->Get('Venue');
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'locdescription' );

		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');

		//add css file
		$doc->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$doc->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		$doc->addScript('media/com_jem/js/attachments.js' );

		// Get the menu object of the active menu item
		$menu		= $app->getMenu();
		$item		= $menu->getActive();
		$params 	= $app->getParams('com_jem');

		$id ? $title = JText::_( 'COM_JEM_EDIT_VENUE' ) : $title = JText::_( 'COM_JEM_ADD_VENUE' );

		//pathway
		$pathway 	= $app->getPathWay();
		$pathway->setItemName(1, $item->title);
		$pathway->addItem($title, '');

		//Set Title
		$doc->setTitle($title);

		//editor user
		$editoruser = JEMUser::editoruser();

		//transform <br /> and <br> back to \r\n for non editorusers
		if (!$editoruser) {
			$row->locdescription = JEMHelper::br2break($row->locdescription);
		}

		//Get image
		$limage = JEMImage::flyercreator($row->locimage, 'venue');

		//Set the info image
		$infoimage = JHTML::_('image', 'media/com_jem/images/icon-16-hint.png', JText::_( 'COM_JEM_NOTES' ) );

		// country list
		$countries = array();
		$countries[] = JHTML::_('select.option', '', JText::_('COM_JEM_SELECT_COUNTRY'));
		$countries = array_merge($countries, JEMHelper::getCountryOptions());
		$selectedCountry = ($row->id) ? $row->country : $jemsettings->defaultCountry;
		$lists['countries'] = JHTML::_('select.genericlist', $countries, 'country', 'class="inputbox"', 'value', 'text', $selectedCountry);
		unset($countries);

		$this->row				= $row;
		$this->editor			= $editor;
		$this->editoruser		= $editoruser;
		$this->limage			= $limage;
		$this->infoimage		= $infoimage;
		$this->jemsettings		= $jemsettings;
		$this->item				= $item;
		$this->params			= $params;
		$this->lists			= $lists;
		$this->title			= $title;

		$mode2 = JRequest::getVar('mode', '');
		$this->mode				= $mode2;

		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access			= $access2;

		parent::display($tpl);
	}
}
?>