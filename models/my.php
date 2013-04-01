<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');
jimport('joomla.html.pagination');

/**
 * EventList Component EventList Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		1.0
 */
class EventListModelMy extends JModelLegacy
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

        // Get the paramaters of the active menu item
        $params =  $app->getParams('com_eventlist');

        //get the number of events from database
        $limit = $app->getUserStateFromRequest('com_eventlist.my.limit', 'limit', $params->def('display_num', 0), 'int');
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
                $this->_events = $this->_getList($query, $pagination->limitstart, $pagination->limit);
            }
			
			$k = 0;
			$count = count($this->_events);
			for($i = 0; $i < $count; $i++)
			{
				$item =& $this->_events[$i];
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
				$item =& $this->_attending[$i];
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
            $this->_pagination_events = new MyEventsPagination($this->getTotalEvents(), $this->getState('limitstart_events'), $this->getState('limit'));
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
        $where = $this->_buildEventListWhere();
        $orderby = $this->_buildEventListOrderBy();

        //Get Events from Database
		$query = 'SELECT DISTINCT a.id as eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription,'
				. ' l.venue, l.city, l.state, l.url,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__eventlist_events AS a'
				. ' LEFT JOIN #__eventlist_venues AS l ON l.id = a.locid'
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
        $where = $this->_buildEventListAttendingWhere();
        $orderby = $this->_buildEventListOrderBy();

        //Get Events from Database
        $query = 'SELECT a.id AS eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription, a.published, '
        .' l.id, l.venue, l.city, l.state, l.url,'
        .' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
        .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
        .' FROM #__eventlist_events AS a'
        .' INNER JOIN #__eventlist_register AS r ON r.event = a.id'
        .' LEFT JOIN #__eventlist_venues AS l ON l.id = a.locid'
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
        $user =  JFactory::getUser();
        //Get Events from Database
        $query = 'SELECT l.id, l.venue, l.city, l.state, l.url, l.published, '
        .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', l.id, l.alias) ELSE l.id END as venueslug'
        .' FROM #__eventlist_venues AS l '
        .' WHERE l.created_by = '.$this->_db->Quote($user->id)
        .' ORDER BY l.venue ASC '
        ;

        return $query;
    }

    /**
     * Build the order clause
     *
     * @access private
     * @return string
     */
    function _buildEventListOrderBy()
    {
        $filter_order = $this->getState('filter_order');
        $filter_order_dir = $this->getState('filter_order_dir');

        $orderby = ' ORDER BY '.$filter_order.' '.$filter_order_dir.', a.dates, a.times';

        return $orderby;
    }

    /**
     * Build the where clause
     *
     * @access private
     * @return string
     */
    function _buildEventListWhere()
    {
        $app =  JFactory::getApplication();

        $user =  JFactory::getUser();
        if (JFactory::getUser()->authorise('core.manage')) {
           $gid = (int) 3;      //viewlevel Special
           } else {
               if($user->get('id')) {
                   $gid = (int) 2;    //viewlevel Registered
               } else {
                   $gid = (int) 1;    //viewlevel Public
               }
           }

        // Get the paramaters of the active menu item
        $params =  $app->getParams();

        $task = JRequest::getWord('task');

        // First thing we need to do is to select only needed events
        if ($task == 'archive')
        {
            $where = ' WHERE a.published = -1';
        } else
        {
            $where = ' WHERE a.published = 1';
        }

        // then if the user is the owner of the event
        $where .= ' AND a.created_by = '.$this->_db->Quote($user->id);

        // Second is to only select events assigned to category the user has access to
      //  $where .= ' AND c.access <= '.$gid;

        /*
         * If we have a filter, and this is enabled... lets tack the AND clause
         * for the filter onto the WHERE clause of the item query.
         */
        if ($params->get('filter'))
        {
            $filter = JRequest::getString('filter', '', 'request');
            $filter_type = JRequest::getWord('filter_type', '', 'request');

            if ($filter)
            {
                // clean filter variables
                $filter = JString::strtolower($filter);
                $filter = $this->_db->Quote('%'.$this->_db->getEscaped($filter, true).'%', false);
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
/*
                    case 'type':
                        $where .= ' AND LOWER( c.catname ) LIKE '.$filter;
                        break;
*/
                }
            }
        }
        return $where;
    }

    /**
     * Build the where clause
     *
     * @access private
     * @return string
     */
    function _buildEventListAttendingWhere()
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
            $where = ' WHERE a.published = -1';
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
		$user		=  JFactory::getUser();
		if (JFactory::getUser()->authorise('core.manage')) {
           $gid = (int) 3;      //viewlevel Special
           } else {
               if($user->get('id')) {
                   $gid = (int) 2;    //viewlevel Registered
               } else {
                   $gid = (int) 1;    //viewlevel Public
               }
           }
		
		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__eventlist_categories AS c'
				. ' LEFT JOIN #__eventlist_cats_event_relations AS rel ON rel.catid = c.id'
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

        $data->all = new JPaginationObject(JText::_('COM_EVENTLIST_VIEW_ALL'));
        if (!$this->_viewall)
        {
            $data->all->base = '0';
            $data->all->link = JRoute::_("&limitstart_events=");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('COM_EVENTLIST_START'));
        $data->previous = new JPaginationObject(JText::_('COM_EVENTLIST_PREV'));

        if ($this->get('pages.current') > 1)
        {
            $page = ($this->get('pages.current')-2)*$this->limit;

            $page = $page == 0?'':$page; //set the empty for removal from route

            $data->start->base = '0';
            $data->start->link = JRoute::_("&limitstart_events=");
            $data->previous->base = $page;
            $data->previous->link = JRoute::_("&limitstart_events=".$page);
        }

        // Set the next and end data objects
        $data->next = new JPaginationObject(JText::_('COM_EVENTLIST_NEXT'));
        $data->end = new JPaginationObject(JText::_('COM_EVENTLIST_END'));

        if ($this->get('pages.current') < $this->get('pages.total'))
        {
            $next = $this->get('pages.current')*$this->limit;
            $end = ($this->get('pages.total')-1)*$this->limit;

            $data->next->base = $next;
            $data->next->link = JRoute::_("&limitstart_events=".$next);
            $data->end->base = $end;
            $data->end->link = JRoute::_("&limitstart_events=".$end);
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
                $data->pages[$i]->link = JRoute::_("&limitstart_events=".$offset);
            }
        }
        return $data;
    }

    function _list_footer($list)
    {
        // Initialize variables
        $html = "<div class=\"list-footer\">\n";

        $html .= "\n<div class=\"limit\">".JText::_('COM_EVENTLIST_DISPLAY_NUM').$list['limitfield']."</div>";
        $html .= $list['pageslinks'];
        $html .= "\n<div class=\"counter\">".$list['pagescounter']."</div>";

        $html .= "\n<input type=\"hidden\" name=\"limitstart_events\" value=\"".$list['limitstart']."\" />";
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

        $data->all = new JPaginationObject(JText::_('COM_EVENTLIST_VIEW_ALL'));
        if (!$this->_viewall)
        {
            $data->all->base = '0';
            $data->all->link = JRoute::_("&limitstart_attending=");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('COM_EVENTLIST_START'));
        $data->previous = new JPaginationObject(JText::_('COM_EVENTLIST_PREV'));

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
        $data->next = new JPaginationObject(JText::_('COM_EVENTLIST_NEXT'));
        $data->end = new JPaginationObject(JText::_('COM_EVENTLIST_END'));

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

        $html .= "\n<div class=\"limit\">".JText::_('Display Num').$list['limitfield']."</div>";
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

        $data->all = new JPaginationObject(JText::_('COM_EVENTLIST_VIEW_ALL'));
        if (!$this->_viewall)
        {
            $data->all->base = '0';
            $data->all->link = JRoute::_("&limitstart_venues=");
        }

        // Set the start and previous data objects
        $data->start = new JPaginationObject(JText::_('COM_EVENTLIST_START'));
        $data->previous = new JPaginationObject(JText::_('COM_EVENTLIST_PREV'));

        if ($this->get('pages.current') > 1)
        {
            $page = ($this->get('pages.current')-2)*$this->limit;

            $page = $page == 0?'':$page; //set the empty for removal from route

            $data->start->base = '0';
            $data->start->link = JRoute::_("&limitstart_venues=");
            $data->previous->base = $page;
            $data->previous->link = JRoute::_("&limitstart_venues=".$page);
        }

        // Set the next and end data objects
        $data->next = new JPaginationObject(JText::_('COM_EVENTLIST_NEXT'));
        $data->end = new JPaginationObject(JText::_('COM_EVENTLIST_END'));

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

        $html .= "\n<div class=\"limit\">".JText::_('COM_EVENTLIST_DISPLAY_NUM').$list['limitfield']."</div>";
        $html .= $list['pageslinks'];
        $html .= "\n<div class=\"counter\">".$list['pagescounter']."</div>";

        $html .= "\n<input type=\"hidden\" name=\"limitstart_venues\" value=\"".$list['limitstart']."\" />";
        $html .= "\n</div>";

        return $html;
    }
}
?>