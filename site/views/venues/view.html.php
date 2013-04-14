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
 * HTML View class for the Venues View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewVenues extends JViewLegacy
{
	/**
	 * Creates the Venuesview
	 *
	 * @since 0.9
	 */
	function display( $tpl = null )
	{
		$app =  JFactory::getApplication();

		$document 	=  JFactory::getDocument();
		$elsettings =  ELHelper::config();

		//get menu information
		$menu		= $app->getMenu();
		$item    	= $menu->getActive();
		$params 	=  $app->getParams();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		// Request variables
		$limitstart		= JRequest::getInt('limitstart');
		$limit			= JRequest::getVar('limit', $params->get('display_num'), '', 'int');
		$pop			= JRequest::getBool('pop', 0, '', 'int');
		$task 			= JRequest::getWord('task');

		$rows 		=  $this->get('Data');
		$total 		=  $this->get('Total');

		//Add needed scripts if the lightbox effect is enabled
		if ($elsettings->lightbox == 1) {
  			JHTML::_('behavior.modal');
		}

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=venues&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//pathway
		$pathway 	=  $app->getPathWay();
		$pathway->setItemName(1, $item->title);
		
		if ( $task == 'archive' ) {
			$pathway->addItem(JText::_( 'COM_JEM_ARCHIVE' ), JRoute::_('index.php?view=venues&task=archive') );
			$pagetitle = $params->get('page_title').' - '.JText::_( 'COM_JEM_ARCHIVE' );
			$print_link = JRoute::_('index.php?view=venues&task=archive&print=1&tmpl=component');
		} else {
			$pagetitle = $params->get('page_title');
			$print_link = JRoute::_('index.php?view=venues&print=1&tmpl=component');
		}
		
		//Set Page title
		$document->setTitle( $pagetitle );
   		$document->setMetadata( 'title' , $pagetitle );
   		$document->setMetadata('keywords', $pagetitle );


		//Printfunction
		$params->def( 'print', !$app->getCfg( 'hidePrint' ) );
		$params->def( 'icons', $app->getCfg( 'icons' ) );

		if ( $pop ) {
			$params->set( 'popup', 1 );
		}

		//Check if the user has access to the form
		$maintainer = ELUser::ismaintainer();
		$genaccess 	= ELUser::validate_user( $elsettings->evdelrec, $elsettings->delivereventsyes );

		if ($maintainer || $genaccess ) $dellink = 1;

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		$this->rows 		= $rows;
		$this->print_link 	= $print_link;
		$this->params 		= $params;
		$this->dellink 		= $dellink;
		$this->pagination 	= $pagination;
		$this->limit 		= $limit;
		$this->total 		= $total;
		$this->item 		= $item;
		$this->elsettings 	= $elsettings;
		$this->task 		= $task;
		$this->pagetitle 	= $pagetitle;

		parent::display($tpl);
	}
}
?>