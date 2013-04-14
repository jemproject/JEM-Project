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
 * HTML View class for the Categories View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewCategories extends JViewLegacy
{
	function display( $tpl=null )
	{
		$app =  JFactory::getApplication();

		$document 	=  JFactory::getDocument();
		$elsettings =  ELHelper::config();

		$rows 		=  $this->get('Data');
		$total 		=  $this->get('Total');
		$pagination    =  $this->get('Pagination');

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		//get menu information
		$menu		= $app->getMenu();
		$item    	= $menu->getActive();
		$params 	= $app->getParams('com_jem');

		// Request variables
		$limitstart		= JRequest::getInt('limitstart');
		$limit			= JRequest::getInt('limit', $params->get('cat_num'));
		$task			= JRequest::getWord('task');

		$params->def( 'page_title', $item->title);

		//pathway
		$pathway 	=  $app->getPathWay();
		$pathway->setItemName(1, $item->title);

		if ( $task == 'archive' ) {
			$pathway->addItem(JText::_( 'COM_JEM_ARCHIVE' ), JRoute::_('index.php?view=categories&task=archive') );
			$pagetitle = $params->get('page_title').' - '.JText::_( 'COM_JEM_ARCHIVE' );
		} else {
			$pagetitle = $params->get('page_title');
		}

		//Set Page title
		$document->setTitle( $pagetitle );
   		$document->setMetaData( 'title' , $pagetitle );

		//get icon settings
		$params->def( 'icons', $app->getCfg( 'icons' ) );

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//Check if the user has access to the form
		$maintainer = ELUser::ismaintainer();
		$genaccess 	= ELUser::validate_user( $elsettings->evdelrec, $elsettings->delivereventsyes );

		if ($maintainer || $genaccess ) $dellink = 1;
		
		// Create the pagination object
		jimport('joomla.html.pagination');

		$pagination = new JPagination($total, $limitstart, $limit);

		$this->rows				= $rows;
		$this->task				= $task;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->pagination		= $pagination;
		$this->item				= $item;
		$this->elsettings		= $elsettings;
		$this->pagetitle		= $pagetitle;

		parent::display($tpl);
	}
}
?>