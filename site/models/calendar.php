<?php
/**
 * @version 1.0 $Id$
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
defined('_JEXEC') or die ;

jimport('joomla.application.component.model');

/**
 * JEM Component Categoryevents Model
 *
 * @package JEM
 * @since		0.9
 */
class JEMModelCalendar extends JModelLegacy
{
    /**
     * Events data array
     *
     * @var array
     */
    var $_data = null;

    /**
     * Tree categories data array
     *
     * @var array
     */
    var $_categories = null;

    /**
     * Events total
     *
     * @var integer
     */
    var $_total = null;

    /**
     * The reference date
     *
     * @var int unix timestamp
     */
    var $_date = 0;

    /**
     * Constructor
     *
     * @since 0.9
     */
    function __construct()
    {
        parent::__construct();

        $app =  JFactory::getApplication();

        $this->setdate(time());

        // Get the paramaters of the active menu item
        $params =  $app->getParams();
    }

    function setdate($date)
    {
        $this->_date = $date;
    }

    /**
     * Method to get the events
     *
     * @access public
     * @return array
     */
    function &getData()
    {

        $app =  JFactory::getApplication();
        $params =  $app->getParams();
        
        // Lets load the content if it doesn't already exist
        if ( empty($this->_data))
        {
          $query = $this->_buildQuery();
          $this->_data = $this->_getList( $query );
          
          $multi = array();
            
	        foreach($this->_data AS $item)
					{
						//$item =& $this->_data[$i];
						
						$item->categories = $this->getCategories($item->id);
						
						if (!is_null($item->enddates) && !$params->get('show_only_start', 1)) 
						{
							if ($item->enddates != $item->dates) 
							{							
								$day = $item->start_day;
		
								for ($counter = 0; $counter <= $item->datediff-1; $counter++)
								{
									$day++;
								
									//next day:
									$nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);
									
									//ensure we only generate days of current month in this loop
									if (strftime('%m', $this->_date) == strftime('%m', $nextday)) {
										$multi[$counter] = clone $item;
										$multi[$counter]->dates = strftime('%Y-%m-%d', $nextday);
										
										//add generated days to data
										$this->_data = array_merge($this->_data, $multi);
									}
									//unset temp array holding generated days before working on the next multiday event
									unset($multi);
								}
							}
						}
		
						//remove events without categories (users have no access to them)
						if (empty($item->categories)) {
							unset($item);
						}						
					}
				}
        return $this->_data;
    }

    /**
     * Build the query
     *
     * @access private
     * @return string
     */
    function _buildQuery()
    {
        // Get the WHERE clauses for the query
        $where = $this->_buildCategoryWhere();

        //Get Events from Database
        $query = 'SELECT DATEDIFF(a.enddates, a.dates) AS datediff, a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.datdescription, a.created, l.venue, l.city, l.state, l.url,'
        .' DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month,'
        .' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
        .' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
        .' FROM #__jem_events AS a'
        .' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
        .' LEFT JOIN #__jem_cats_event_relations AS r ON r.itemid = a.id '
        .$where
        .' GROUP BY a.id '
        ;

        return $query;
    }

    /**
     * Method to build the WHERE clause
     *
     * @access private
     * @return array
     */
    function _buildCategoryWhere()
    {
        $app =  JFactory::getApplication();

        // Get the paramaters of the active menu item
        $params =  $app->getParams();
        
        $top_category = $params->get('top_category', 0);

        $task = JRequest::getWord('task');

        // First thing we need to do is to select only the published events
        if ($task == 'archive')
        {
            $where = ' WHERE a.published = -1 ';
        } else
        {
            $where = ' WHERE a.published = 1 ';
        }

        // only select events within specified dates. (chosen month)
        $monthstart = mktime(0, 0, 1, strftime('%m', $this->_date), 1, strftime('%Y', $this->_date));
        $monthend = mktime(0, 0, -1, strftime('%m', $this->_date)+1, 1, strftime('%Y', $this->_date));
        
				$filter_date_from = $this->_db->Quote(strftime('%Y-%m-%d', $monthstart));
				$where .= ' AND DATEDIFF(IF (a.enddates IS NOT NULL AND a.enddates <> '. $this->_db->Quote('0000-00-00') .', a.enddates, a.dates), '. $filter_date_from .') >= 0';
				$filter_date_to = $this->_db->Quote(strftime('%Y-%m-%d', $monthend));
				$where .= ' AND DATEDIFF(a.dates, '. $filter_date_to .') <= 0';
					
        if ($top_category) {
        	$children = JEMCategories::getChilds($top_category);
        	if (count($children)) {
        		$where .= ' AND r.catid IN ('. implode(',', $children) .')';   
        	}     	
        }
        
        return $where;
    }

    /**
     * Method to get the Categories
     *
     * @access public
     * @return integer
     */
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

        
        $query = 'SELECT c.id, c.catname, c.access, c.color, c.published, c.checked_out AS cchecked_out,'
        . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
        . ' FROM #__jem_categories AS c'
        . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
        . ' WHERE rel.itemid = '.(int)$id
		. ' AND c.published = 1'
        . ' AND c.access  <= '.$gid;
        ;

        $this->_db->setQuery($query);

        $this->_categories = $this->_db->loadObjectList();
        
        return $this->_categories;
    }
}
?>
