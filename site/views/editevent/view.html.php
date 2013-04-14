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

// no direct access
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the EditeventView
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewEditevent extends JViewLegacy
{
	/**
	 * Creates the output for event submissions
	 *
	 * @since 0.4
	 *
	 */
	function display( $tpl=null )
	{
		$app 		=  JFactory::getApplication();
		$session 	=  JFactory::getSession();
		
		$user   =  JFactory::getUser();
    	if (!$user->id) {
      		$app->redirect(JRoute::_($_SERVER["HTTP_REFERER"]), JText::_('Please login to be able to submit events'), 'error' );
    	}

		if($this->getLayout() == 'choosevenue') {
			$this->_displaychoosevenue($tpl);
			return;
		}

		// Initialize variables
		$editor 	=  JFactory::getEditor();
		$doc 		=  JFactory::getDocument();
		$elsettings =  ELHelper::config();

		//Get Data from the model
		$row 			= $this->get('Event');
		
		//Cause of group limits we can't use class here to build the categories tree
		$categories		= $this->get('Categories');
		
		//sticky form categorie data
		if ($session->has('eventform', 'com_jem')) {
			$eventform = $session->get('eventform', 0, 'com_jem');
			$selectedcats = $eventform['cid'];
		} else {
			$selectedcats 	= $this->get( 'Catsselected' );
		}
		
		//build selectlists
		$categories = JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8 class="inputbox required validate-cid"');
		//Get requests
		$id					= JRequest::getInt('id');

		//Clean output
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, 'datdescription' );

		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal', 'a.modal');

		//add css file
		$doc->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$doc->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		//Set page title
		$id ? $title = JText::_( 'COM_JEM_EDIT_EVENT' ) : $title = JText::_( 'COM_JEM_ADD_EVENT' );

		$doc->setTitle($title);

		// Get the menu object of the active menu item
		$menu		= $app->getMenu();
		$item    	= $menu->getActive();
		$params 	=  $app->getParams('com_jem');

		//pathway
		$pathway 	=  $app->getPathWay();
		$pathway->setItemName(1, $item->title);
		$pathway->addItem($title, '');

		//Has the user access to the editor and the add venue screen
		$editoruser = ELUser::editoruser();
		$delloclink = ELUser::validate_user( $elsettings->locdelrec, $elsettings->deliverlocsyes );
		
		//transform <br /> and <br> back to \r\n for non editorusers
		if (!$editoruser) {
			$row->datdescription = ELHelper::br2break($row->datdescription);
		}

		//Get image information
		$dimage = ELImage::flyercreator($row->datimage, 'event');

		//Set the info image
		$infoimage = JHTML::_('image', 'media/com_jem/images/icon-16-hint.png', JText::_( 'COM_JEM_NOTES' ) );

		//Create the stuff required for the venueselect functionality
		$url	= $app->isAdmin() ? $app->getSiteURL() : JURI::base();

		$js = "
		function elSelectVenue(id, venue) {
			document.getElementById('a_id').value = id;
			document.getElementById('a_name').value = venue;
			window.parent.SqueezeBox.close();
		}
		
		function closeAdd() {
			window.parent.SqueezeBox.close(); 
    	}
    	";

		$doc->addScriptDeclaration($js);
		// include the recurrence script
		$doc->addScript($url.'media/com_jem/js/recurrence.js');
		// include the unlimited script
		$doc->addScript($url.'media/com_jem/js/unlimited.js');
		
		$doc->addScript('media/com_jem/js/attachments.js' );
		
		$lists = array();
		
		// recurrence type
    	$rec_type = array();
    	$rec_type[] = JHTML::_('select.option', 0, JText::_ ( 'COM_JEM_NOTHING' ));
    	$rec_type[] = JHTML::_('select.option', 1, JText::_ ( 'COM_JEM_DAYLY' ));
    	$rec_type[] = JHTML::_('select.option', 2, JText::_ ( 'COM_JEM_WEEKLY' ));
    	$rec_type[] = JHTML::_('select.option', 3, JText::_ ( 'COM_JEM_MONTHLY' ));
    	$rec_type[] = JHTML::_('select.option', 4, JText::_ ( 'COM_JEM_WEEKDAY' ));
    	$lists['recurrence_type'] = JHTML::_('select.genericlist', $rec_type, 'recurrence_type', '', 'value', 'text', $row->recurrence_type);

    	//if only owned events are allowed
    	if ($elsettings->ownedvenuesonly) {
    		$venues     =  $this->get( 'UserVenues' );
			//build list
			$venuelist       = array();
			$venuelist[]     = JHTML::_('select.option', '0', JText::_( 'COM_JEM_NO_VENUE' ) );
			$venuelist       = array_merge( $venuelist, $venues );

			$lists['venueselect']    = JHTML::_('select.genericlist', $venuelist, 'locid', 'size="1" class="inputbox"', 'value', 'text', $row->locid );
    	}
    	
		$this->row				= $row;
		$this->categories		= $categories;
		$this->editor			= $editor;
		$this->dimage			= $dimage;
		$this->infoimage		= $infoimage;
		$this->delloclink		= $delloclink;
		$this->editoruser		= $editoruser;
		$this->elsettings		= $elsettings;
		$this->item				= $item;
		$this->params			= $params;
		$this->lists			= $lists;

		$access2 = ELHelper::getAccesslevelOptions();
		$this->access			= $access2;
		
		parent::display($tpl);

	}

	/**
	 * Creates the output for the venue select listing
	 *
	 * @since 0.9
	 *
	 */
	function _displaychoosevenue($tpl)
	{
		$app =  JFactory::getApplication();

		$document	=  JFactory::getDocument();
		$params 	=  $app->getParams();

		$limitstart			= JRequest::getVar('limitstart', 0, '', 'int');
		$limit				= $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $params->def('display_num', 0), 'int');
		$filter_order		= JRequest::getCmd('filter_order', 'l.venue');
		$filter_order_Dir	= JRequest::getWord('filter_order_Dir', 'ASC');;
		$filter				= JRequest::getString('filter');
		$filter_type		= JRequest::getInt('filter_type');

		// Get/Create the model
		$rows 	= $this->get('Venues');
		$total 	= $this->get('Countitems');
		
		JHTML::_('behavior.modal', 'a.modal');

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		// table ordering
		$lists['order_Dir'] 	= $filter_order_Dir;
		$lists['order'] 		= $filter_order;

		$document->setTitle(JText::_( 'COM_JEM_SELECTVENUE' ));
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');

		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_VENUE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_CITY' ) );
		$searchfilter = JHTML::_('select.genericlist', $filters, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type );

		$this->rows				= $rows;
		$this->searchfilter		= $searchfilter;
		$this->pagination		= $pagination;
		$this->lists			= $lists;
		$this->filter			= $filter;


		parent::display($tpl);
	}
}
?>