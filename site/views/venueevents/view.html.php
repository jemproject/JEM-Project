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
 * HTML View class for the Venueevents View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewVenueevents extends JViewLegacy
{
	/**
	 * Creates the Venueevents View
	 *
	 * @since 0.9
	 */
	function display( $tpl = null )
	{
		$app =  JFactory::getApplication();

		//initialize variables
		$document 	=  JFactory::getDocument();
		$menu		=  $app->getMenu();
		$elsettings =  ELHelper::config();
		
		//get menu information
		$menu		= $app->getMenu();
		$item = $menu->getActive();
	
		
		$params 	=  $app->getParams('com_jem');
		$uri 		=  JFactory::getURI();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		// Request variables
		$limitstart		= JRequest::getInt('limitstart');
		$limit       	= $app->getUserStateFromRequest('com_jem.venueevents.limit', 'limit', $params->def('display_num', 0), 'int');
		$pop			= JRequest::getBool('pop');
		$task 			= JRequest::getWord('task');

		//get data from model
		$rows 		=  $this->get('Data');
		$venue	 	=  $this->get('Venue');
		$total 		=  $this->get('Total');

		//does the venue exist?
		if ($venue->id == 0)
		{
			return JError::raiseError( 404, JText::sprintf( 'Venue #%d not found', $venue->id ) );
		}

		//are events available?
		if (!$rows) {
			$noevents = 1;
		} else {
			$noevents = 0;
		}

		// Add needed scripts if the lightbox effect is enabled
		if ($elsettings->lightbox == 1) {
			JHTML::_('behavior.modal');
		}

		//Get image
		$limage = ELImage::flyercreator($venue->locimage, 'venue');

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=venueevents&format=feed&id='.$venue->id;
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//pathway
		$pathway 	=  $app->getPathWay();
		$pathway->setItemName(1, $item->title);
		
		//create the pathway
		if ($task == 'archive') {
			$pathway->addItem( JText::_( 'COM_JEM_ARCHIVE' ).' - '.$venue->venue, JRoute::_('index.php?option=com_jem&view=venueevents&task=archive&id='.$venue->slug));
			$link = JRoute::_( 'index.php?option=com_jem&view=venueevents&id='.$venue->slug.'&task=archive' );
			$print_link = JRoute::_('index.php?option=com_jem&view=venueevents&id='. $venue->slug .'&task=archive&print=1&tmpl=component');
			$pagetitle = $venue->venue.' - '.JText::_( 'COM_JEM_ARCHIVE' );
		} else {
			$pathway->addItem( $venue->venue, JRoute::_('index.php?option=com_jem&view=venueevents&id='.$venue->slug));
			$link = JRoute::_( 'index.php?option=com_jem&view=venueevents&id='.$venue->slug );
			$print_link = JRoute::_('index.php?option=com_jem&view=venueevents&id='. $venue->slug .'&print=1&tmpl=component');
			$pagetitle = $venue->venue;
		}
		
		//set Page title
		$document->setTitle( $pagetitle );
   		$document->setMetaData( 'title' , $pagetitle );
		$document->setMetadata('keywords', $venue->meta_keywords );
		$document->setDescription( strip_tags($venue->meta_description) );

		//Printfunction
		$params->def( 'print', !$app->getCfg( 'hidePrint' ) );
		$params->def( 'icons', $app->getCfg( 'icons' ) );

		if ( $pop ) {
			$params->set( 'popup', 1 );
		}

		//Check if the user has access to the form
		$maintainer = ELUser::ismaintainer();
		$genaccess 	= ELUser::validate_user( $elsettings->evdelrec, $elsettings->delivereventsyes );

		if ($maintainer || $genaccess ) 
		{ 
		$dellink = 1;
		} else {
		$dellink = 0;	
		}

		//Generate Venuedescription
		if (!$venue->locdescription == '' || !$venue->locdescription == '<br />') {
			//execute plugins
			$venue->text	= $venue->locdescription;
			$venue->title 	= $venue->venue;
			JPluginHelper::importPlugin('content');
			$results = $app->triggerEvent( 'onContentPrepare', array('com_jem.venueevents', &$venue, &$params, 0 ));
			$venuedescription = $venue->text;
		}
    	$allowedtoeditvenue = ELUser::editaccess($elsettings->venueowner, $venue->created, $elsettings->venueeditrec, $elsettings->venueedit);
    
		//build the url
        if(!empty($venue->url) && strtolower(substr($venue->url, 0, 7)) != "http://") {
        	$venue->url = 'http://'.$venue->url;
        }

        //prepare the url for output
        if (strlen(htmlspecialchars($venue->url, ENT_QUOTES)) > 35) {
			$venue->urlclean = substr( htmlspecialchars($venue->url, ENT_QUOTES), 0 , 35).'...';
		} else {
			$venue->urlclean = htmlspecialchars($venue->url, ENT_QUOTES);
		}

        //create flag
        if ($venue->country) {
        	$venue->countryimg = JEMOutput::getFlag( $venue->country );
        }

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		//create select lists
		$lists	= $this->_buildSortLists($elsettings);
		$this->lists				= $lists;
		$this->action				= $uri->toString();

		$this->rows					= $rows;
		$this->noevents				= $noevents;
		$this->venue				= $venue;
		$this->print_link			= $print_link;
		$this->params				= $params;
		$this->dellink				= $dellink;
		$this->limage				= $limage;
		$this->venuedescription		= $venuedescription;
		$this->pagination			= $pagination;
		$this->elsettings			= $elsettings;
		$this->item					= $item;
		$this->pagetitle			= $pagetitle;
		$this->task					= $task;
		$this->allowedtoeditvenue	= $allowedtoeditvenue;

		parent::display($tpl);
	}

	/**
	 * Manipulate Data
	 *
	 * @since 0.9
	 */
	function &getRows()
	{
		$count = count($this->rows);

		if (!$count) {
			return;
		}
		
		$k = 0;
		foreach($this->rows as $key => $row)
		{
			$row->odd   = $k;
			
			$this->rows[$key] = $row;
			$k = 1 - $k;
		}

		return $this->rows;
	}

	function _buildSortLists($elsettings)
	{
		// Table ordering values
		$filter_order		= JRequest::getCmd('filter_order', 'a.dates');
		$filter_order_Dir	= JRequest::getWord('filter_order_Dir', 'ASC');

		$filter				= $this->escape(JRequest::getString('filter'));
		$filter_type		= JRequest::getString('filter_type');

		if ($elsettings->showcat) {
			$sortselects = array();
			$sortselects[]	= JHTML::_('select.option', 'title', $elsettings->titlename );
			$sortselects[] 	= JHTML::_('select.option', 'type', $elsettings->catfroname );
			$sortselect 	= JHTML::_('select.genericlist', $sortselects, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type );
		} else {
			$sortselect = '';
		}

		$lists['order_Dir'] 	= $filter_order_Dir;
		$lists['order'] 		= $filter_order;
		$lists['filter'] 		= $filter;
		$lists['filter_types'] 	= $sortselect;

		return $lists;
	}
}
?>