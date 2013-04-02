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

/**
 * EventList Component Categoriesdetailed Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelCategoriesdetailed extends JModelLegacy
{
    /**
     * Top category id
     *
     * @var int
     */
    var $_id = 0;

    /**
     * Event data array
     *
     * @var array
     */
    var $_data = null;

    /**
     * Categories total
     *
     * @var integer
     */
    var $_total = null;

    /**
     * Categories data array
     *
     * @var integer
     */
    var $_categories = null;

    /**
     * Pagination object
     *
     * @var object
     */
    var $_pagination = null;

    /**
     * Constructor
     *
     * @since 0.9
     */
    function __construct()
    {
        parent::__construct();

        $app =  JFactory::getApplication();

        // Get the paramaters of the active menu item
        $params =  $app->getParams('com_eventlist');

        $id = JRequest::getInt('id');
        $this->_id = $id;

        //get the number of events from database
        $limit = JRequest::getInt('limit', $params->get('cat_num'));
        $limitstart = JRequest::getInt('limitstart');

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

    /**
     * Method to get the Categories
     *
     * @access public
     * @return array
     */
    function  getData()
    {
        $app 		=  JFactory::getApplication();
        $params 	=  $app->getParams();
        $elsettings =  ELHelper::config();

        // Lets load the content if it doesn't already exist
        if ( empty($this->_categories))
        {
            // get top level categories
            $query = $this->_buildQuery();
            $pagination = $this->getPagination();
            $this->_categories = $this->_getList($query, $pagination->limitstart, $pagination->limit);

            $k = 0;
            $count = count($this->_categories);
            for ($i = 0; $i < $count; $i++)
            {
                $category = & $this->_categories[$i];

                //child categories
                $query = $this->_buildQuery($category->id);
                $this->_db->setQuery($query);
                $category->subcats = $this->_db->loadObjectList();

                //Generate description
                if ( empty($category->catdescription))
                {
                    $category->catdescription = JText::_('COM_EVENTLIST_NO_DESCRIPTION');
                } else
                {
                    //execute plugins
                    $category->text = $category->catdescription;
                    $category->title = $category->catname;
                    JPluginHelper::importPlugin('content');
                    $results = $app->triggerEvent('onContentPrepare', array ( & $category, & $params, 0));
                    $category->catdescription = $category->text;
                }

                //create target link
                $task = JRequest::getWord('task');

                $category->linktext = $task == 'archive'?JText::_('COM_EVENTLIST_SHOW_ARCHIVE'):
                    JText::_('COM_EVENTLIST_SHOW_EVENTS');

                    if ($task == 'archive')
                    {
                        $category->linktarget = JRoute::_('index.php?view=categoryevents&id='.$category->slug.'&task=archive');
                    } else
                    {
                        $category->linktarget = JRoute::_('index.php?view=categoryevents&id='.$category->slug);
                    }

                    $k = 1-$k;
                }

            }

            return $this->_categories;
        }

        /**
         * Total nr of Categories
         *
         * @access public
         * @return integer
         */
        function getTotal()
        {
            // Lets load the total nr if it doesn't already exist
            if ( empty($this->_total))
            {
                $query = $this->_buildQueryTotal();
                $this->_total = $this->_getListCount($query);
                
               // for debugging
               // print_r($query);
                
                
                
            }

            return $this->_total;
        }

        /**
         * Method to get the Categories events
         *
         * @access public
         * @return array
         */
        function & getEventdata($id)
        {
            $app = JFactory::getApplication();

            $params =  $app->getParams('com_eventlist');

            // Lets load the content
            $query = $this->_buildDataQuery($id);
            $this->_data = $this->_getList($query, 0, $params->get('detcat_nr'));

            $k = 0;
            $count = count($this->_data);
            for ($i = 0; $i < $count; $i++)
            {
                $item = & $this->_data[$i];
                $item->categories = $this->getCategories($item->id);

                //remove events without categories (users have no access to them)
                if ( empty($item->categories))
                {
                    unset ($this->_data[$i]);
                }

                $k = 1-$k;
            }

            return $this->_data;
        }

        /**
         * Method get the event query
         *
         * @access private
         * @return array
         */
        function _buildDataQuery($id)
        {
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
           
           
            $id = (int)$id;

            $task = JRequest::getWord('task');

            // First thing we need to do is to select only the requested events
            if ($task == 'archive')
            {
                $where = ' WHERE a.published = -1 && rel.catid = '.$id;
            } else
            {
                $where = ' WHERE a.published = 1 && rel.catid = '.$id;
            }

            // Second is to only select events assigned to category the user has access to
            $where .= ' AND c.access <= '.$gid;

            $query = 'SELECT DISTINCT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.datdescription, a.created, l.venue, l.city, l.state, l.url,'
            .' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
            .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
            .' FROM #__eventlist_events AS a'
            .' LEFT JOIN #__eventlist_venues AS l ON l.id = a.locid'
            .' LEFT JOIN #__eventlist_cats_event_relations AS rel ON rel.itemid = a.id'
            .' LEFT JOIN #__eventlist_categories AS c ON c.id = '.$id
            .$where
            .' ORDER BY a.dates, a.times'
            ;

            return $query;
        }

        function getCategories($id)
        {
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

            $query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
            .' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
            .' FROM #__eventlist_categories AS c'
            .' LEFT JOIN #__eventlist_cats_event_relations AS rel ON rel.catid = c.id'
            .' WHERE rel.itemid = '.(int)$id
            .' AND c.published = 1'
            .' AND c.access  <= '.$gid;
            ;

            $this->_db->setQuery($query);

            $this->_cats = $this->_db->loadObjectList();
            return $this->_cats;
        }

        /**
         * Method get the categories query
         *
         * @access private
         * @return array
         */
        function _buildQuery($parent_id = null)
        {
            $app =  JFactory::getApplication();

            if (is_null($parent_id))
            {
                $parent_id = (int)$this->_id;
            }

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
            
            
            $ordering = 'c.ordering ASC';

            //build where clause
            $where_sub = ' WHERE cc.published = 1';
            $where_sub .= ' AND cc.parent_id = '.$parent_id;
            $where_sub .= ' AND cc.access <= '.$gid;

            //check archive task and ensure that only categories get selected if they contain a published/archived event
            $task = JRequest::getWord('task');
            if ($task == 'archive')
            {
                $where_sub .= ' AND i.published = -1';
            } else
            {
                $where_sub .= ' AND i.published = 1';
            }
            $where_sub .= ' AND c.id = cc.id';

            // Get the paramaters of the active menu item
            $params =  $app->getParams('com_eventlist');

            // show/hide empty categories
            $empty = null;
            if (!$params->get('empty_cat'))
            {
                $empty = ' HAVING assignedevents > 0';
            }
            $query = 'SELECT c.*,'
            .' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
            .' ('
            .' SELECT COUNT( DISTINCT i.id )'
            .' FROM #__eventlist_events AS i'
            .' LEFT JOIN #__eventlist_cats_event_relations AS rel ON rel.itemid = i.id'
            .' LEFT JOIN #__eventlist_categories AS cc ON cc.id = rel.catid'
            .$where_sub
            .' GROUP BY cc.id'
            .')'
            .' AS assignedevents'
            .' FROM #__eventlist_categories AS c'
            .' WHERE c.published = 1'
            .' AND c.parent_id = '.$parent_id
            .' AND c.access <= '.$gid
            .' GROUP BY c.id '.$empty
            .' ORDER BY '.$ordering
            ;

            return $query;
        }

        /**
         * Method to build the Categories query without subselect
         * That's enough to get the total value.
         *
         * @access private
         * @return string
         */
        function _buildQueryTotal()
        {
            $app =  JFactory::getApplication();
			
            // Get the paramaters of the active menu item
            $params =  $app->getParams('com_eventlist');

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

            $query = 'SELECT DISTINCT c.id'
            .' FROM #__eventlist_categories AS c';

            
            if (!$params->get('empty_cat', 1))
            {
                $query .= ' INNER JOIN #__eventlist_cats_event_relations AS rel ON rel.catid = c.id '
                .' INNER JOIN #__eventlist_events AS e ON e.id = rel.itemid ';
            }
            $query .= ' WHERE c.published = 1'
            .' AND c.parent_id = '.(int)$this->_id
            .' AND c.access <= '.$gid
            ;
			
            if (!$params->get('empty_cat'))
            {
                $task = JRequest::getWord('task');
                if ($task == 'archive')
                {
                    $query .= ' AND e.published = -1';
                } else
                {
                    $query .= ' AND e.published = 1';
                }
            }

            return $query;
        }

        /**
         * Method to get a pagination object for the events
         *
         * @access public
         * @return integer
         */
        function getPagination()
        {
            // Lets load the content if it doesn't already exist
            if ( empty($this->_pagination))
            {
                jimport('joomla.html.pagination');
                $this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
            }
            return $this->_pagination;
        }
    }
?>
