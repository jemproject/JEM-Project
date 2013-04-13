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
 * HTML View class for the Categoriesdetailed View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewCategoriesdetailed extends JViewLegacy
{
	/**
	 * Creates the Categoriesdetailed View
	 *
	 * @since 0.9
	 */
	function display( $tpl = null )
	{
		$app =  JFactory::getApplication();

		//initialise variables
		$document 		=  JFactory::getDocument();
		$elsettings 	=  ELHelper::config();
		$model 			=  $this->getModel();
		$menu			=  $app->getMenu();
		$item    		=  $menu->getActive();
		$params 		=  $app->getParams();

		//get vars
		$limitstart		=  JRequest::getInt('limitstart');
		$limit			=  JRequest::getInt('limit', $params->get('cat_num'));
		$pathway 		=  $app->getPathWay();
		$pop			=  JRequest::getBool('pop');
		$task 			=  JRequest::getWord('task');

		//Get data from the model
		$categories		=  $this->get('Data');
		$total 			=  $this->get('Total');
    	
		// Create the pagination object   
    	$pagination =  $this->get('Pagination');

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		$params->def( 'page_title', $item->title);

		//pathway
		$pathway->setItemName(1, $item->title);
		
		if ( $task == 'archive' ) {
			$pathway->addItem(JText::_( 'COM_JEM_ARCHIVE' ), JRoute::_('index.php?view=categoriesdetailed&task=archive') );
			$print_link = JRoute::_( 'index.php?option=com_jem&view=categoriesdetailed&task=archive&print=1&tmpl=component' );
			$pagetitle = $params->get('page_title').' - '.JText::_( 'COM_JEM_ARCHIVE' );
		} else {
			$print_link = JRoute::_( 'index.php?option=com_jem&view=categoriesdetailed&print=1&tmpl=component' );
			$pagetitle = $params->get('page_title');
		}
		//set Page title
		$document->setTitle( $pagetitle );
		$document->setMetadata( 'title' , $pagetitle );
		$document->setMetadata( 'keywords' , $pagetitle );

		//Print
		$params->def( 'print', !$app->getCfg( 'hidePrint' ) );
		$params->def( 'icons', $app->getCfg( 'icons' ) );

		if ( $pop ) {
			$params->set( 'popup', 1 );
		}

		//Check if the user has access to the form
		$maintainer = ELUser::ismaintainer();
		$genaccess 	= ELUser::validate_user( $elsettings->evdelrec, $elsettings->delivereventsyes );

		if ($maintainer || $genaccess ) $dellink = 1;

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);


		// Create the pagination object
		jimport('joomla.html.pagination');

		$this->assignRef('categories' , 			$categories);
		$this->assignRef('print_link' , 			$print_link);
		$this->assignRef('params' , 				$params);
		$this->assignRef('dellink' , 				$dellink);
		$this->assignRef('item' , 					$item);
		$this->assignRef('model' , 					$model);
		$this->assignRef('pagination' , 				$pagination);
		$this->assignRef('elsettings' , 			$elsettings);
		$this->assignRef('task' , 					$task);
		$this->assignRef('pagetitle' , 				$pagetitle);
		

		parent::display($tpl);

	}//function end

	/**
	 * Manipulate Data
	 *
	 * @since 0.9
	 */
	function getRows()
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