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
 * HTML View class for the Day View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewDay extends JViewLegacy
{
	/**
	 * Creates the Day View
	 *
	 * @since 0.9
	 */
	function display( $tpl = null )
	{
		$app =  JFactory::getApplication();

		//initialize variables
		$document 	= JFactory::getDocument();
		$elsettings = ELHelper::config();
		$menu		= $app->getMenu();
		$item    	= $menu->getActive();
		$params 	= $app->getParams();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		// get variables
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$limit		= JRequest::getVar('limit', $params->get('display_num'), '', 'int');

		$pop			= JRequest::getBool('pop');
		$pathway 		= $app->getPathWay();

		//get data from model
		$rows 		= $this->get('Data');
		$total 		= $this->get('Total');
		$day		= $this->get('Day');
		
		$daydate = strftime( $elsettings->formatdate, strtotime( $day ));

		//are events available?
		if (!$rows) {
			$noevents = 1;
		} else {
			$noevents = 0;
		}

		//params
		$params->def( 'page_title', $item->title);

		if ( $pop ) {//If printpopup set true
			$params->set( 'popup', 1 );
		}

		$print_link = JRoute::_('index.php?view=day&tmpl=component&pop=1');

		//pathway
		$pathway->setItemName( 1, $item->title );

		//Set Page title
		if (!$item->title) {
			$document->setTitle($params->get('page_title'));
			$document->setMetadata( 'keywords' , $params->get('page_title') );
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

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=day&format=feed&id=' . date('Ymd', strtotime($this->get('Day')));
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		// Create the pagination object
		$page = $total - $limit;

		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		//create select lists
		$lists	= $this->_buildSortLists();

		$this->lists			= $lists;

		$this->rows				= $rows;
		$this->noevents			= $noevents;
		$this->print_link		= $print_link;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->pagination		= $pagination;
		$this->page				= $page;
		$this->elsettings		= $elsettings;
		$this->lists			= $lists;
		$this->daydate			= $daydate;

		parent::display($tpl);

	}//function ListEvents end

	/**
	 * Manipulate Data
	 *
	 * @access public
	 * @return object $rows
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

	/**
	 * Method to build the sortlists
	 *
	 * @access private
	 * @return array
	 * @since 0.9
	 */
	function _buildSortLists()
	{
		$elsettings = ELHelper::config();
		
		$filter_order		= JRequest::getCmd('filter_order', 'a.dates');
		$filter_order_Dir	= JRequest::getWord('filter_order_Dir', 'ASC');

		$filter				= $this->escape(JRequest::getString('filter'));
		$filter_type		= JRequest::getString('filter_type');

		$sortselects = array();
		$sortselects[]	= JHTML::_('select.option', 'title', $elsettings->titlename );
		$sortselects[] 	= JHTML::_('select.option', 'venue', $elsettings->locationname );
		$sortselects[] 	= JHTML::_('select.option', 'city', $elsettings->cityname );
		$sortselect 	= JHTML::_('select.genericlist', $sortselects, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type );

		$lists['order_Dir'] 	= $filter_order_Dir;
		$lists['order'] 		= $filter_order;
		$lists['filter'] 		= $filter;
		$lists['filter_type'] 	= $sortselect;

		return $lists;
	}
}
?>