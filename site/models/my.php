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
class JEMModelMy extends JModelLegacy
{
    /**
     * Events data array
     *
     * @var array
     */
    var $_events = null;

    /**
     * Events total
     *
     * @var integer
     */
    var $_total_events = null;

    var $_venues = null;

    var $_total_venues = null;

    var $_attending = null;

    var $_total_attending = null;

    /**
     * Pagination object
     *
     * @var object
     */
    var $_pagination_events = null;

    /**
     * Pagination object
     *
     * @var object
     */
    var $_pagination_venues = null;

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

        //get the number of events from database

        $limit		= $app->getUserStateFromRequest('com_jem.my.limit', 'limit', $jemsettings->display_num, 'int');
        $limitstart = $app->getUserStateFromRequest('com_jem.my.limitstart', 'limitstart', 0, 'int');
        
        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
        
        
        
        $limit		= $app->getUserStateFromRequest('com_jem.myvenue.limit', 'limit_venue', $jemsettings->display_num, 'int');
        $limitstart = $app->getUserStateFromRequest('com_jem.myvenue.limitstart', 'limitstart_venue', 0, 'int');
        
        $this->setState('limit_venue', $limit);
        $this->setState('limitstart_venue', $limitstart);
        
        
        
        
        /*
        
        $limit = $app->getUserStateFromRequest('com_jem.my.limit', 'limit', $jemsettings->display_num, 'int');
        $limitstart_events = JRequest::getVar('limitstart_events', 0, '', 'int');
        $limitstart_venues = JRequest::getVar('limitstart_venues', 0, '', 'int');
        $limitstart_attending = JRequest::getVar('limitstart_attending', 0, '', 'int');

      $this->setState('limit', $limit);
        $this->setState('limitstart_events', $limitstart_events);
        $this->setState('limitstart_venues', $limitstart_venues);
        $this->setState('limitstart_attending', $limitstart_attending);

        // Get the filter request variables
        $this->setState('filter_order', JRequest::getCmd('filter_order', 'a.dates'));
        $this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
        */
    }

    /**
     * Method to get the Events
     *
     * @access public
     * @return array
     */
    function & getEvents()
    {
        $pop = JRequest::getBool('pop');

        // Lets load the content if it doesn't already exist
        if ( empty($this->_events))
        {
            $query = $this->_buildQueryEvents();
            $pagination = $this->getEventsPagination();

            if ($pop)
            {
                $this->_events = $this->_getList($query);
            } else
            {
            	$pagination = $this->getEventsPagination();
                $this->_events = $this->_getList($query, $pagination->limitstart, $pagination->limit);
            }

			$k = 0;
			$count = count($this->_events);
			for($i = 0; $i < $count; $i++)
			{
				$item = $this->_events[$i];
				$item->categories = $this->getCategories($item->eventid);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_events[$i]);
				}

				$k = 1 - $k;
			}
        }

        return $this->_events;
    }

    
    
    /**
     * Method to (un)publish a event
     *
     * @access	public
     * @return	boolean	True on success
     * @since	1.5
     */
    function publish($cid = array(), $publish = 1)
    {
    	$user 	= JFactory::getUser();
    	$userid = (int) $user->get('id');
    
    	if (count($cid))
    	{
    		$cids = implode(',', $cid);
    
    		$query = 'UPDATE #__jem_events'
    				. ' SET published = '. (int) $publish
    				. ' WHERE id IN ('. $cids .')'
    						. ' AND (checked_out = 0 OR (checked_out = ' .$userid. '))'
    								;
    
    								$this->_db->setQuery($query);
    
    								if (!$this->_db->query()) {
    									$this->setError($this->_db->getErrorMsg());
    									return false;
    								}
    	}
    }
    
    
    
    
    
    /**
     * Method to get the Events user is attending
     *
     * @access public
     * @return array
     */
    function & getAttending()
    {
        $pop = JRequest::getBool('pop');

        // Lets load the content if it doesn't already exist
        if ( empty($this->_attending))
        {
            $query = $this->_buildQueryAttending();
            $pagination = $this->getAttendingPagination();

            if ($pop)
            {
                $this->_attending = $this->_getList($query);
            } else
            {
                $this->_attending = $this->_getList($query, $pagination->limitstart, $pagination->limit);
            }

			$k = 0;
			$count = count($this->_attending);
			for($i = 0; $i < $count; $i++)
			{
				$item = $this->_attending[$i];
				$item->categories = $this->getCategories($item->eventid);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset($this->_attending[$i]);
				}

				$k = 1 - $k;
			}
        }

        return $this->_attending;
    }

    /**
     * Method to get the Venues
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
    function getTotalEvents()
    {
        // Lets load the total nr if it doesn't already exist
        if ( empty($this->_total_events))
        {
            $query = $this->_buildQueryEvents();
            $this->_total_events = $this->_getListCount($query);
        }

        return $this->_total_events;
    }

    /**
     * Total nr of events
     *
     * @access public
     * @return integer
     */
    function getTotalAttending()
    {
        // Lets load the total nr if it doesn't already exist
        if ( empty($this->_total_attending))
        {
            $query = $this->_buildQueryAttending();
            $this->_total_attending = $this->_getListCount($query);
        }

        return $this->_total_attending;
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

       // var_dump($query);exit;
        return $this->_total_venues;
    }

    /**
     * Method to get a pagination object for the events
     *
     * @access public
     * @return integer
     */
    function getEventsPagination()
    {
        // Lets load the content if it doesn't already exist
        if ( empty($this->_pagination_events))
        {
            jimport('joomla.html.pagination');
            $this->_pagination_events = new MyEventsPagination($this->getTotalEvents(), $this->getState('limitstart'), $this->getState('limit'));
            //$this->_pagination_events = new JPagination($this->getTotalEvents(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination_events;
    }

    /**
     * Method to get a pagination object for the venues
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
            $this->_pagination_venues = new MyVenuesPagination($this->getTotalVenues(), $this->getState('limitstart_venues'), $this->getState('limit'));
        }

        return $this->_pagination_venues;
    }

    /**
     * Method to get a pagination object for the attending events
     *
     * @access public
     * @return integer
     */
    function getAttendingPagination()
    {
        // Lets load the content if it doesn't already exist
        if ( empty($this->_pagination_attending))
        {
            jimport('joomla.html.pagination');
            $this->_pagination_attending = new MyAttendingPagination($this->getTotalAttending(), $this->getState('limitstart_attending'), $this->getState('limit'));
        }

        return $this->_pagination_attending;
    }

    /**
     * Build the query
     *
     * @access private
     * @return string
     */
    function _buildQueryEvents()
    {
        // Get the WHERE and ORDER BY clauses for the query
        $where = $this->_buildWhere();
        $orderby = $this->_buildOrderBy();

        //Get Events from Database
		$query = 'SELECT DISTINCT a.id as eventid, a.dates, a.enddates, a.published, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription,'
				. ' l.venue, l.city, l.state, l.url,'
				. ' c.catname, c.id AS catid,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__jem_events AS a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
				. $where
				. $orderby
				;

        return $query;
    }

    /**
     * Build the query
     *
     * @access private
     * @return string
     */
    function _buildQueryAttending()
    {
        // Get the WHERE and ORDER BY clauses for the query
        $where = $this->_buildAttendingWhere();
        $orderby = $this->_buildOrderBy();

        //Get Events from Database
        $query = 'SELECT a.id AS eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription, a.published, '
        .' l.id, l.venue, l.city, l.state, l.url,'
        .' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
        .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
        .' FROM #__jem_events AS a'
        .' INNER JOIN #__jem_register AS r ON r.event = a.id'
        .' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
        .$where
        .$orderby
        ;

        return $query;
    }

    /**
     * Build the query
     *
     * @access private
     * @return string
     */
    function _buildQueryVenues()
    {
    	$orderbyvenue = $this->_buildOrderByVenue();
        $user =  JFactory::getUser();
        //Get Events from Database
        $query = 'SELECT l.id, l.venue, l.city, l.state, l.url, l.published, '
        .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', l.id, l.alias) ELSE l.id END as venueslug'
        .' FROM #__jem_venues AS l '
        .' WHERE l.created_by = '.$this->_db->Quote($user->id)
        .$orderbyvenue
        ;

        return $query;
    }

    /**
     * Build the order clause
     *
     * @access private
     * @return string
     */
    function _buildOrderBy()
    {
        
    	
    	$app =  JFactory::getApplication();
    	
    	$filter_order		= $app->getUserStateFromRequest('com_jem.my.filter_order', 'filter_order', 'a.dates', 'cmd');
    	$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.my.filter_order_Dir', 'filter_order_Dir', '', 'word');
    	
    	$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
    	$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');
    	
    	if ($filter_order != '') {
    		$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
    	} else {
    		$orderby = ' ORDER BY a.dates, a.times ';
    	}
    	
    	return $orderby;
    	
    	
    	
    	
    	/*
    	$filter_order = $this->getState('filter_order');
        $filter_order_dir = $this->getState('filter_order_dir');


        $filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_dir	= JFilterInput::getinstance()->clean($filter_order_dir, 'word');

        $orderby = ' ORDER BY '.$filter_order.' '.$filter_order_dir.', a.dates, a.times';

        return $orderby;
        */
    }

    
    
    /**
     * Build the order clause
     *
     * @access private
     * @return string
     */
    function _buildOrderByVenue()
    {
    
    	 
    	$app =  JFactory::getApplication();
    	 
    	$filter_order		= $app->getUserStateFromRequest('com_jem.myvenue.filter_order_venue', 'filter_order_venue', 'l.venue', 'cmd');
    	$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.myvenue.filter_order_Dir_venue', 'filter_order_Dir_venue', '', 'word');
    	 
    	$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
    	$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');
    	 
    	if ($filter_order != '') {
    		$orderbyvenue = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
    	} else {
    		$orderbyvenue = ' ORDER BY l.venue ';
    	}
    	 

    	return $orderbyvenue;
    }
    
    
    
    
    
    
    
    /**
     * Build the where clause
     *
     * @access private
     * @return string
     */
    function _buildWhere()
    {
        $app =  JFactory::getApplication();
        $task = JRequest::getWord('task');
        // Get the paramaters of the active menu item
        $params =  $app->getParams();
        
        $jemsettings =  JEMHelper::config();

        $user = JFactory::getUser();
        $gid = JEMHelper::getGID($user);

        $filter_state 	= $app->getUserStateFromRequest('com_jem.my.filter_state', 'filter_state', '', 'word');
        $filter 		= $app->getUserStateFromRequest('com_jem.my.filter', 'filter', '', 'int');
        $search 		= $app->getUserStateFromRequest('com_jem.my.search', 'search', '', 'string');
        $search 		= $this->_db->escape(trim(JString::strtolower($search)));
        
        
        $where = array();
        
        // First thing we need to do is to select only needed events
        if ($task == 'archive') {
        	$where[] = ' a.published = 2';
        } else {
        	$where[] = ' (a.published = 1 OR a.published = 0)';
        }
        $where[] = ' c.published = 1';
        $where[] = ' c.access  <= '.$gid;
        
        // get excluded categories
        $excluded_cats = trim($params->get('excluded_cats', ''));
        
        if ($excluded_cats != '') {
        	$cats_excluded = explode(',', $excluded_cats);
        	$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
        }
        // === END Excluded categories add === //
        
        
        if ($jemsettings->filter)
        {
        
        	if ($search && $filter == 1) {
        		$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
        	}
        
        	if ($search && $filter == 2) {
        		$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
        	}
        
        	if ($search && $filter == 3) {
        		$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
        	}
        
        	if ($search && $filter == 4) {
        		$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
        	}
        
        	if ($search && $filter == 5) {
        		$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
        	}
        
        } // end tag of jemsettings->filter decleration
        
        $where 		= (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
        
        return $where;
        
        
        
        
        
/*
   

        // First thing we need to do is to select only needed events
        if ($task == 'archive')
        {
            $where = ' WHERE a.published = 2';
        } else
        {
            //$where = ' WHERE a.published = 1';
        	$where = ' WHERE (a.published = 1 OR a.published = 0)';
        }


			$where .= ' AND c.published = 1';
        	$where .= ' AND c.access  <= '.$gid;



        // then if the user is the owner of the event
        $where .= ' AND a.created_by = '.$this->_db->Quote($user->id);

        // Second is to only select events assigned to category the user has access to
      //  $where .= ' AND c.access <= '.$gid;

        
        if ($jemsettings->filter)
        {
            $filter = JRequest::getString('filter', '', 'request');
            $filter_type = JRequest::getWord('filter_type', '', 'request');

            if ($filter)
            {
                // clean filter variables
                $filter = JString::strtolower($filter);
                $filter = $this->_db->Quote('%'.$this->_db->escape($filter, true).'%', false);
                $filter_type = JString::strtolower($filter_type);

                switch($filter_type)
                {
                    case 'title':
                        $where .= ' AND LOWER( a.title ) LIKE '.$filter;
                        break;

                    case 'venue':
                        $where .= ' AND LOWER( l.venue ) LIKE '.$filter;
                        break;

                    case 'city':
                        $where .= ' AND LOWER( l.city ) LIKE '.$filter;
                        break;

                    case 'type':
                        $where .= ' AND LOWER( c.catname ) LIKE '.$filter;
                        break;

                }
            }
        }
        return $where;
        */
        
        
    }

    /**
     * Build the where clause
     *
     * @access private
     * @return string
     */
    function _buildAttendingWhere()
    {
        $app =  JFactory::getApplication();

        $user =  JFactory::getUser();
		$nulldate = '0000-00-00';

        // Get the paramaters of the active menu item
        $params =  $app->getParams();

        $task = JRequest::getWord('task');

        // First thing we need to do is to select only needed events
        if ($task == 'archive')
        {
            $where = ' WHERE a.published = 2';
        } else
        {
            $where = ' WHERE a.published = 1';
        }

		//limit output so only future events the user attends will be shown
		if ($params->get('filtermyregs')) {
			$where .= ' AND DATE_SUB(NOW(), INTERVAL '.(int)$params->get('myregspast').' DAY) < (IF (a.enddates <> '.$nulldate.', a.enddates, a.dates))';
		}

        // then if the user is attending the event
        $where .= ' AND r.uid = '.$this->_db->Quote($user->id);

        return $where;
    }

	function getCategories($id)
	{
		$user = JFactory::getUser();
		$gid = JEMHelper::getGID($user);

		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				. ' AND c.published = 1'
				. ' AND c.access  <= '.$gid;
				;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}
}

class MyEventsPagination extends JPagination
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
            //$data->all->link = JRoute::_("&limitstart_events=");
            $data->all->link = JRoute::_("&limitstart=0");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('JLIB_HTML_START'));
        $data->previous = new JPaginationObject(JText::_('JPREV'));

        if ($this->get('pages.current') > 1)
        {
            $page = ($this->get('pages.current')-2)*$this->limit;

            /* by disabling this line,  the link for previous page (second -> first page) is working) */
           // $page = $page == 0?'':$page; //set the empty for removal from route

            $data->start->base = '0';
            //$data->start->link = JRoute::_("&limitstart_events=");
            
            /* this is for the link to the first page */
            $data->start->link = JRoute::_("&limitstart=0");

            $data->previous->base = $page;
            //$data->previous->link = JRoute::_("&limitstart_events=".$page);
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
            //$data->next->link = JRoute::_("&limitstart_events=".$next);
            $data->next->link = JRoute::_("&limitstart=".$next);
            $data->end->base = $end;
            //$data->end->link = JRoute::_("&limitstart_events=".$end);
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
               // $data->pages[$i]->link = JRoute::_("&limitstart_events=".$offset);
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

        //$html .= "\n<input type=\"hidden\" name=\"limitstart_events\" value=\"".$list['limitstart']."\" />";
        $html .= "\n<input type=\"hidden\" name=\"limitstart\" value=\"".$list['limitstart']."\" />";
        $html .= "\n</div>";

        return $html;
    }

}


class MyAttendingPagination extends JPagination
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
            $data->all->link = JRoute::_("&limitstart_attending=");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('COM_JEM_START'));
        $data->previous = new JPaginationObject(JText::_('COM_JEM_PREV'));

        if ($this->get('pages.current') > 1)
        {
            $page = ($this->get('pages.current')-2)*$this->limit;

            $page = $page == 0?'':$page; //set the empty for removal from route

            $data->start->base = '0';
            $data->start->link = JRoute::_("&limitstart_attending=");
            $data->previous->base = $page;
            $data->previous->link = JRoute::_("&limitstart_attending=".$page);
        }

        // Set the next and end data objects
        $data->next = new JPaginationObject(JText::_('COM_JEM_NEXT'));
        $data->end = new JPaginationObject(JText::_('COM_JEM_END'));

        if ($this->get('pages.current') < $this->get('pages.total'))
        {
            $next = $this->get('pages.current')*$this->limit;
            $end = ($this->get('pages.total')-1)*$this->limit;

            $data->next->base = $next;
            $data->next->link = JRoute::_("&limitstart_attending=".$next);
            $data->end->base = $end;
            $data->end->link = JRoute::_("&limitstart_attending=".$end);
        }

        $data->pages = array ();
        $stop = $this->get('pages.stop');
        for ($i = $this->get('pages.start'); $i <= $stop; $i++)
        {
            $offset = ($i-1)*$this->limit;

            $offset = $offset == 0?'':$offset; //set the empty for removal from route

            $data->pages[$i] = new JPaginationObject($i);
            if ($i != $this->get('pages.current') || $this->_viewall)
            {
                $data->pages[$i]->base = $offset;
                $data->pages[$i]->link = JRoute::_("&limitstart_attending=".$offset);
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

        $html .= "\n<input type=\"hidden\" name=\"limitstart_attending\" value=\"".$list['limitstart']."\" />";
        $html .= "\n</div>";

        return $html;
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
            $data->all->link = JRoute::_("&limitstart_venues=");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('COM_JEM_START'));
        $data->previous = new JPaginationObject(JText::_('COM_JEM_PREV'));

        if ($this->get('pages.current') > 1)
        {
            $page = ($this->get('pages.current')-2)*$this->limit;

           // $page = $page == 0?'':$page; //set the empty for removal from route

            $data->start->base = '0';
            $data->start->link = JRoute::_("&limitstart_venues=");
            $data->previous->base = $page;
            $data->previous->link = JRoute::_("&limitstart_venues=".$page);
        }

        // Set the next and end data objects
        $data->next = new JPaginationObject(JText::_('COM_JEM_NEXT'));
        $data->end = new JPaginationObject(JText::_('COM_JEM_END'));

        if ($this->get('pages.current') < $this->get('pages.total'))
        {
            $next = $this->get('pages.current')*$this->limit;
            $end = ($this->get('pages.total')-1)*$this->limit;

            $data->next->base = $next;
            $data->next->link = JRoute::_("&limitstart_venues=".$next);
            $data->end->base = $end;
            $data->end->link = JRoute::_("&limitstart_venues=".$end);
        }

        $data->pages = array ();
        $stop = $this->get('pages.stop');
        for ($i = $this->get('pages.start'); $i <= $stop; $i++)
        {
            $offset = ($i-1)*$this->limit;

            $offset = $offset == 0?'':$offset; //set the empty for removal from route

            $data->pages[$i] = new JPaginationObject($i);
            if ($i != $this->get('pages.current') || $this->_viewall)
            {
                $data->pages[$i]->base = $offset;
                $data->pages[$i]->link = JRoute::_("&limitstart_venues=".$offset);
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