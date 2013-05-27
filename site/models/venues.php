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

/**
 * JEM Component Venues Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelVenues extends JModelLegacy
{
	/**
	 * Venues data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Venues total
	 *
	 * @var integer
	 */
	var $_total = null;

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
		$params 	=  $app->getParams('com_jem');

		//get the number of events from database
		$limit			= JRequest::getInt('limit', $params->get('display_venues_num'));
		$limitstart		= JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Venues
	 *
	 * @access public
	 * @return array
	 */
	function &getData( )
	{
		$app =  JFactory::getApplication();

		$menu		= $app->getMenu();
		$item    	= $menu->getActive();
		$params		= $menu->getParams($item->id);

		$jemsettings 	=   JEMHelper::config();

		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$pagination = $this->getPagination();
      		$this->_data = $this->_getList( $query, $pagination->limitstart,  $pagination->limit );

			$k = 0;
			for($i = 0; $i <  count($this->_data); $i++)
			{
				$venue = $this->_data[$i];

				//Create image information
				$venue->limage = JEMImage::flyercreator($venue->locimage, 'venue');

				//Generate Venuedescription
				if (empty ($venue->locdescription)) {
					$venue->locdescription = JText::_( 'COM_JEM_NO_DESCRIPTION' );
				} else {
					//execute plugins
					$venue->text	= $venue->locdescription;
					$venue->title 	= $venue->venue;
					JPluginHelper::importPlugin('content');
					$results = $app->triggerEvent( 'onContentPrepare', array( 'com_jem.venues', &$venue, &$params, 0 ));
					$venue->locdescription = $venue->text;
				}

				//build the url
				if(!empty($venue->url) && strtolower(substr($venue->url, 0, 7)) != "http://") {
					$venue->url = 'http://'.$venue->url;
    		    }

				//prepare the url for output
				if (strlen(htmlspecialchars($venue->url, ENT_QUOTES)) > 35) {
					$venue->urlclean = substr( htmlspecialchars($venue->url, ENT_QUOTES), 0 , 35).'...';
				} else {
					$venue->urlclean = htmlspecialchars($venue->url, ENT_QUOTES);
				}

    		    //create flag
				if ($venue->country) {
					$venue->countryimg = JEMOutput::getFlag( $venue->country );
				}
				
				//create target link
				$task 	= JRequest::getVar('task', '', '', 'string');

				if ($task == 'archive') {
					$venue->targetlink = JRoute::_('index.php?view=venueevents&id='.$venue->slug.'&task=archive');
				} else {
					$venue->targetlink = JRoute::_('index.php?view=venueevents&id='.$venue->slug);
				}
		
				$k = 1 - $k;
			}

		}

		return $this->_data;
	}

	/**
	 * Total nr of Venues
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
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
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		//check archive task
		$task 	= JRequest::getVar('task', '', '', 'string');
		if($task == 'archive') {
			$eventstate = ' AND a.published = 2';
		} else {
			$eventstate = ' AND a.published = 1';
		}
		
		//get categories
		$query = 'SELECT v.*, COUNT( a.id ) AS assignedevents,'
				. ' CASE WHEN CHAR_LENGTH(v.alias) THEN CONCAT_WS(\':\', v.id, v.alias) ELSE v.id END as slug'
				. ' FROM #__jem_venues as v'
				. ' LEFT JOIN #__jem_events AS a ON a.locid = v.id'
				. ' WHERE v.published = 1'
				. $eventstate
				. ' GROUP BY v.id'
				. ' ORDER BY v.venue'
				;

		return $query;
	}
}
?>