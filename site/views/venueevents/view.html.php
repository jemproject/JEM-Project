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
		$app = JFactory::getApplication();

		//initialize variables
		$document 	= JFactory::getDocument();
		$menu		= $app->getMenu();
		$jemsettings = JEMHelper::config();
		$db  		=  JFactory::getDBO();

		//get menu information
		$menu		= $app->getMenu();
		$item = $menu->getActive();


		$params 	= $app->getParams('com_jem');
		$uri 		= JFactory::getURI();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		
		// get variables
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.venueevents.filter_order', 'filter_order', 	'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.venueevents.filter_order_Dir', 'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.venueevents.filter_state', 'filter_state', 	'*', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.venueevents.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.venueevents.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		$pop			= JRequest::getBool('pop');
		$task 			= JRequest::getWord('task');
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		//get data from model
		$rows 		= $this->get('Data');
		$venue	 	= $this->get('Venue');
		$total 		= $this->get('Total');

		//does the venue exist?
		if ($venue->id == 0)
		{
			// TODO Translation
			return JError::raiseError( 404, JText::sprintf( 'Venue #%d not found', $venue->id ) );
		}

		//are events available?
		if (!$rows) {
			$noevents = 1;
		} else {
			$noevents = 0;
		}

		// Add needed scripts if the lightbox effect is enabled
		if ($jemsettings->lightbox == 1) {
			JHTML::_('behavior.modal');
		}

		//Get image
		$limage = JEMImage::flyercreator($venue->locimage, 'venue');

		//add alternate feed link
		$link	= 'index.php?option=com_jem&view=venueevents&format=feed&id='.$venue->id;
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//pathway
		$pathway 	= $app->getPathWay();
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
		$maintainer = JEMUser::ismaintainer();
		$genaccess 	= JEMUser::validate_user( $jemsettings->evdelrec, $jemsettings->delivereventsyes );

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
		$allowedtoeditvenue = JEMUser::editaccess($jemsettings->venueowner, $venue->created, $jemsettings->venueeditrec, $jemsettings->venueedit);

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
		$pagination = $this->get('Pagination');

	 
		//search filter
		$filters = array();
		
		if ($jemsettings->showtitle == 1) {
			$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_TITLE' ) );
		}
		if ($jemsettings->showlocate == 1) {
			$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_VENUE' ) );
		}
		if ($jemsettings->showcity == 1) {
			$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_CITY' ) );
		}
		if ($jemsettings->showcat == 1) {
			$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_CATEGORY' ) );
		}
		if ($jemsettings->showstate == 1) {
			$filters[] = JHTML::_('select.option', '5', JText::_( 'COM_JEM_STATE' ) );
		}
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );
		
		// search filter
		$lists['search']= $search;
		
		 
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
		$this->jemsettings			= $jemsettings;
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

}
?>