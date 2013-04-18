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
 * HTML View class for the Categoryevents View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewCategoryevents extends JViewLegacy
{
	/**
	 * Creates the Categoryevents View
	 *
	 * @since 0.9
	 */
	function display( $tpl=null )
	{
		$app =  JFactory::getApplication();

		//initialize variables
		$document 	=  JFactory::getDocument();
		$menu		=  $app->getMenu();
		$jemsettings =  JEMHelper::config();
		//$item    	= $menu->getActive();
		
		//get menu information
		$menu		= $app->getMenu();
		$item = $menu->getActive();
		
		
		
		
		$params 	=  $app->getParams();
		$uri 		=  JFactory::getURI();
		$pathway 	=  $app->getPathWay();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		// Request variables
	//	$limitstart		= JRequest::getInt('limitstart');
	//	$limit       	= $app->getUserStateFromRequest('com_jem.categoryevents.limit', 'limit', $params->def('display_num', 0), 'int');
		$task 			= JRequest::getWord('task');
		$pop			= JRequest::getBool('pop');

		//get data from model
		$rows 		=  $this->get('Data');
		$category 	=  $this->get('Category');
		$categories	=  $this->get('Childs');

		//are events available?
		if (!$rows) {
			$noevents = 1;
		} else {
			$noevents = 0;
		}

		//does the category exist
		if ($category->id == 0)
		{
			return JError::raiseError( 404, JText::sprintf( 'Category #%d not found', $category->id ) );
		}

		//Set Meta data
		$document->setTitle( $item->title.' - '.$category->catname );
    	$document->setMetadata( 'keywords', $category->meta_keywords );
    	$document->setDescription( strip_tags($category->meta_description) );

    	//Print function
		$params->def( 'print', !$app->getCfg( 'hidePrint' ) );
		$params->def( 'icons', $app->getCfg( 'icons' ) );

		if ( $pop ) {
			$params->set( 'popup', 1 );
		}

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=categoryevents&format=feed&id='.$category->id;
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom', 'alternate', 'rel'), $attribs);

		//create the pathway
		$cats		= new JEMCategories($category->id);
		$parents	= $cats->getParentlist();

		foreach($parents as $parent) {
			$pathway->addItem( $this->escape($parent->catname), JRoute::_('index.php?view=categoryevents&id='.$parent->categoryslug));
		}

		
		if ($task == 'archive') {
			$pathway->addItem( JText::_( 'COM_JEM_ARCHIVE' ).' - '.$category->catname, JRoute::_('index.php?option=com_jem&view=categoryevents&task=archive&id='.$category->slug));
			$link = JRoute::_( 'index.php?option=com_jem&view=categoryevents&task=archive&id='.$category->slug );
			$print_link = JRoute::_( 'index.php?option=com_jem&view=categoryevents&id='. $category->id .'&task=archive&print=1&tmpl=component');
		        $pagetitle = $category->catname.' - '.JText::_( 'COM_JEM_ARCHIVE' );
                        } else {
			$pathway->addItem( $category->catname, JRoute::_('index.php?option=com_jem&view=categoryevents&id='.$category->slug));
			$link = JRoute::_( 'index.php?option=com_jem&view=categoryevents&id='.$category->slug );
			$print_link = JRoute::_( 'index.php?option=com_jem&view=categoryevents&id='. $category->id .'&print=1&tmpl=component');
		        $pagetitle = $category->catname;
                        }
		
		//Check if the user has access to the form
		$maintainer = ELUser::ismaintainer();
		$genaccess 	= ELUser::validate_user( $jemsettings->evdelrec, $jemsettings->delivereventsyes );

		if ($maintainer || $genaccess ) 
		{ 
		$dellink = 1;
		} else {
		$dellink = 0;	
		}

		// Create the pagination object		
		$pagination =  $this->get('Pagination');

		//Generate Categorydescription
		if (empty ($category->catdescription)) {
			$catdescription = JText::_( 'COM_JEM_NO_DESCRIPTION' );
		} else {
			//execute plugins
			$category->text	= $category->catdescription;
			$category->title 	= $category->catname;
			JPluginHelper::importPlugin('content');
			$results = $app->triggerEvent( 'onContentPrepare', array('com_jem.categoryevents', &$category, &$params, 0 ));
			$catdescription = $category->text;
		}

		/*if ($category->image != '') {
			$category->image = JHTML::image('jem/categories/'.$category->image, $category->catname);
		}
		*/
		if ($category->image != '') {
            $path = "file_path";
            $mediaparams = JComponentHelper::getParams('com_media');
            $imgattribs['width'] = $jemsettings->imagewidth;
			$imgattribs['height'] = $jemsettings->imagehight;

			$category->image = JHTML::image($mediaparams->get($path, 'images').'/jem/categories/'.$category->image, $category->catname, $imgattribs);
		} else {
			$category->image = JHTML::image('media/com_jem/images/noimage.png', $category->catname);
		}
		
		

		//create select lists
		$lists	= $this->_buildSortLists($jemsettings);
		$this->lists			= $lists;
		$this->action			= $uri->toString();

		$this->rows				= $rows;
		$this->noevents			= $noevents;
		$this->category			= $category;
		$this->print_link		= $print_link;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->task				= $task;
		$this->catdescription	= $catdescription;
		$this->pagination		= $pagination;
		$this->jemsettings		= $jemsettings;
		$this->item				= $item;
		$this->categories		= $categories;

	  	if($this->getLayout() == 'calendar') 
	  	{	  	
	    	//add css for calendar
	    	$document->addStyleSheet($this->baseurl.'/media/com_jem/css/calendar.css');
	    
	  		$year  = intval( JRequest::getVar('yearID', strftime( "%Y" ) ));
      		$month = intval( JRequest::getVar('monthID', strftime( "%m" ) ));
      		$day   = intval( JRequest::getVar('dayID', strftime( "%d" ) ));
      		$this->year			= $year;
      		$this->month		= $month;
      		$this->day			= $day;
    	}
		
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

	function _buildSortLists($jemsettings)
	{
		// Table ordering values
		$filter_order		= JRequest::getCmd('filter_order', 'a.dates');
		$filter_order_Dir	= JRequest::getCmd('filter_order_Dir', 'ASC');

		$filter				= $this->escape(JRequest::getString('filter'));
		$filter_type		= JRequest::getString('filter_type');

		$sortselects = array();
		
		if ($jemsettings->showtitle == 1) {
			$sortselects[]	= JHTML::_('select.option', 'title', $jemsettings->titlename );
		}
		if ($jemsettings->showlocate == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'venue', $jemsettings->locationname );
		}
		if ($jemsettings->showcity == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'city', $jemsettings->cityname );
		}
		if ($jemsettings->showcat == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'type', $jemsettings->catfroname );
		}
		if ($jemsettings->showstate == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'state', $jemsettings->statename );
		}

		$sortselect 	= JHTML::_('select.genericlist', $sortselects, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type );

		$lists['order_Dir'] 	= $filter_order_Dir;
		$lists['order'] 		= $filter_order;
		$lists['filter'] 		= $filter;
		$lists['filter_type'] 	= $sortselect;

		return $lists;
	}
}
?>