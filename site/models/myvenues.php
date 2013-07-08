<?php
/**
 * @version 1.9 $Id$
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
defined('_JEXEC') or die;

jimport('joomla.application.component.model');
jimport('joomla.html.pagination');

/**
 * JEM Component JEM Model
 *
 * @package JEM
 * @since 1.0
 */
class JEMModelMyvenues extends JModelLegacy
{
  
    var $_venues = null;

    var $_total_venues = null;


    /**
     * Constructor
     *
     * @since 1.0
     */
    function __construct()
    {
        parent::__construct();

        $app =  JFactory::getApplication();
        $jemsettings =  JEMHelper::config();

        // Get the paramaters of the active menu item
        $params =  $app->getParams('com_jem');

        //get the number of events
        $limit		= $app->getUserStateFromRequest('com_jem.myvenues.limit', 'limit', $jemsettings->display_num, 'int');
        $limitstart = $app->getUserStateFromRequest('com_jem.myvenues.limitstart', 'limitstart', 0, 'int');
        
        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

 

    /**
     * Method to get the Events user is attending
     *
     * @access public
     * @return array
     */
    function & getVenues()
    {
        $pop = JRequest::getBool('pop');

        // Lets load the content if it doesn't already exist
        if ( empty($this->_venues))
        {
            $query = $this->_buildQueryVenues();
            $pagination = $this->getVenuesPagination();

            if ($pop)
            {
                $this->_venues = $this->_getList($query);
            } else
            {
            	$pagination = $this->getVenuesPagination();
                $this->_venues = $this->_getList($query, $pagination->limitstart, $pagination->limit);
            }

		
        }

        return $this->_venues;
    }

 

    /**
     * Total nr of events
     *
     * @access public
     * @return integer
     */
    function getTotalVenues()
    {
        // Lets load the total nr if it doesn't already exist
        if ( empty($this->_total_venues))
        {
            $query = $this->_buildQueryVenues();
            $this->_total_venues = $this->_getListCount($query);
        }
        

        return $this->_total_venues;
    }

  

 



    /**
     * Method to get a pagination object for the attending events
     *
     * @access public
     * @return integer
     */
    function getVenuesPagination()
    {
        // Lets load the content if it doesn't already exist
        if ( empty($this->_pagination_venues))
        {
            jimport('joomla.html.pagination');
            $this->_pagination_venues = new MyVenuesPagination($this->getTotalVenues(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination_venues;
    }

 

    /**
     * Build the query
     *
     * @access private
     * @return string
     */
    function _buildQueryVenues()
    {
        // Get the WHERE and ORDER BY clauses for the query
        $where = $this->_buildVenuesWhere();
        $orderby = $this->_buildOrderByVenues();

        //Get Events from Database
        $query = 'SELECT l.id, l.venue, l.city, l.state, l.url, l.created_by, l.published,'
         .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', l.id, l.alias) ELSE l.id END as venueslug'
         .' FROM #__jem_venues AS l '
        .$where
        .$orderby
        ;

      
        return $query;
    }


    
    /**
     * Build the order clause
     *
     * @access private
     * @return string
     */
    function _buildOrderByVenues()
    {
    
    	 
    	$app =  JFactory::getApplication();
    	 
    	$filter_order		= $app->getUserStateFromRequest('com_jem.myvenues.filter_order', 'filter_order', 'l.venue', 'cmd');
    	$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.myvenues.filter_order_Dir', 'filter_order_Dir', '', 'word');
    	 
    	$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
    	$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');
    	 
    	if ($filter_order != '') {
    		$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
    	} else {
    		$orderby = ' ORDER BY l.venue ';
    	}
    	 
    	return $orderby;
    
    }
    
    
 

    /**
     * Build the where clause
     *
     * @access private
     * @return string
     */
    function _buildVenuesWhere()
    {
        $app =  JFactory::getApplication();

        $user =  JFactory::getUser();
		
        // Get the paramaters of the active menu item
        $params =  $app->getParams();
        $task = JRequest::getWord('task');

        $jemsettings =  JEMHelper::config();
        
        $user = JFactory::getUser();
        $gid = JEMHelper::getGID($user);
        
        $filter_state 	= $app->getUserStateFromRequest('com_jem.myvenues.filter_state', 'filter_state', '', 'word');
        $filter 		= $app->getUserStateFromRequest('com_jem.myvenues.filter', 'filter', '', 'int');
        $search 		= $app->getUserStateFromRequest('com_jem.myvenues.search', 'search', '', 'string');
        $search 		= $this->_db->escape(trim(JString::strtolower($search)));
        
        
        $where = array();
        
        $where[] = ' l.published = 1';
       

        // then if the user is attending the event
        $where [] = ' l.created_by = '.$this->_db->Quote($user->id);
        
        
        
        if ($jemsettings->filter)
        {
        
        	//if ($search && $filter == 1) {
        	//	$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
        	//}
        
        	if ($search && $filter == 2) {
        		$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
        	}
        
        	if ($search && $filter == 3) {
        		$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
        	}
        
        	//if ($search && $filter == 4) {
        	//	$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
        	//}
        
        	if ($search && $filter == 5) {
        		$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
        	}
        
        } // end tag of jemsettings->filter decleration
        
        $where 		= (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
        
        return $where;
    }

    
    
 
}



class MyVenuesPagination extends JPagination
{
    /**
     * Create and return the pagination data object
     *
     * @access  public
     * @return  object  Pagination data object
     * @since 1.5
     */
    function _buildDataObject()
    {
        // Initialize variables
        $data = new stdClass ();

        $data->all = new JPaginationObject(JText::_('COM_JEM_VIEW_ALL'));
        if (!$this->_viewall)
        {
            $data->all->base = '0';
            $data->all->link = JRoute::_("&limitstart=0");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('JLIB_HTML_START'));
        $data->previous = new JPaginationObject(JText::_('JPREV'));

        if ($this->get('pages.current') > 1)
        {
            $page = ($this->get('pages.current')-2)*$this->limit;

           // $page = $page == 0?'':$page; //set the empty for removal from route

            $data->start->base = '0';
            $data->start->link = JRoute::_("&limitstart=0");
            $data->previous->base = $page;
            $data->previous->link = JRoute::_("&limitstart=".$page);
        }

        // Set the next and end data objects
        $data->next = new JPaginationObject(JText::_('JNEXT'));
        $data->end = new JPaginationObject(JText::_('JLIB_HTML_END'));

        if ($this->get('pages.current') < $this->get('pages.total'))
        {
            $next = $this->get('pages.current')*$this->limit;
            $end = ($this->get('pages.total')-1)*$this->limit;

            $data->next->base = $next;
            $data->next->link = JRoute::_("&limitstart=".$next);
            $data->end->base = $end;
            $data->end->link = JRoute::_("&limitstart=".$end);
        }

        $data->pages = array ();
        $stop = $this->get('pages.stop');
        for ($i = $this->get('pages.start'); $i <= $stop; $i++)
        {
            $offset = ($i-1)*$this->limit;

            //$offset = $offset == 0?'':$offset; //set the empty for removal from route

            $data->pages[$i] = new JPaginationObject($i);
            if ($i != $this->get('pages.current') || $this->_viewall)
            {
                $data->pages[$i]->base = $offset;
                $data->pages[$i]->link = JRoute::_("&limitstart=".$offset);
            }
        }
        return $data;
    }

    function _list_footer($list)
    {
        // Initialize variables
        $html = "<div class=\"list-footer\">\n";

        $html .= "\n<div class=\"limit\">".JText::_('COM_JEM_DISPLAY_NUM').$list['limitfield']."</div>";
        $html .= $list['pageslinks'];
        $html .= "\n<div class=\"counter\">".$list['pagescounter']."</div>";

        $html .= "\n<input type=\"hidden\" name=\"limitstart_venues\" value=\"".$list['limitstart']."\" />";
        $html .= "\n</div>";

        return $html;
    }



}
?>