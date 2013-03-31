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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * EventList Component Home Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelEventList extends JModel
{
	/**
	 * Events data in array
	 *
	 * @var array
	 */
	var $_events = null;

	/**
	 * Venues data in array
	 *
	 * @var array
	 */
	var $_venue = null;

	/**
	 * Categories data in array
	 *
	 * @var array
	 */
	var $_category = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Method to get event item data
	 *
	 * @access public
	 * @return array
	 */
	function getEventsdata()
	{
		$_events = array();

		/*
		* Get nr of all published events
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_events'
					. ' WHERE published = 1'
					;

		$this->_db->SetQuery($query);
  		$_events[] = $this->_db->loadResult();

		/*
		* Get nr of all unpublished events
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_events'
					. ' WHERE published = 0'
					;

		$this->_db->SetQuery($query);
  		$_events[] = $this->_db->loadResult();

		/*
		* Get nr of all archived events
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_events'
					. ' WHERE published = -1'
					;

		$this->_db->SetQuery($query);
  		$_events[] = $this->_db->loadResult();

  		/*
		* Get total nr of events
		*/
		$_events[] = array_sum($_events);

		return $_events;
	}

	/**
	 * Method to get venue item data
	 *
	 * @access public
	 * @return array
	 */
	function getVenuesdata()
	{
		$_venue = array();

		/*
		* Get nr of all published venues
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_venues'
					. ' WHERE published = 1'
					;

		$this->_db->SetQuery($query);
  		$_venue[] = $this->_db->loadResult();

		/*
		* Get nr of all unpublished venues
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_venues'
					. ' WHERE published = 0'
					;

		$this->_db->SetQuery($query);
  		$_venue[] = $this->_db->loadResult();

  		/*
		* Get total nr of venues
		*/
  		$_venue[] = array_sum($_venue);

		return $_venue;
	}

		/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getCategoriesdata()
	{
		$_category = array();

		/*
		* Get nr of all published categories
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_categories'
					. ' WHERE published = 1'
					;

		$this->_db->SetQuery($query);
  		$_category[] = $this->_db->loadResult();

		/*
		* Get nr of all unpublished categories
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__eventlist_categories'
					. ' WHERE published = 0'
					;

		$this->_db->SetQuery($query);
  		$_category[] = $this->_db->loadResult();

  		/*
		* Get total nr of categories
		*/
  		$_category[] = array_sum($_category);

		return $_category;
	}
}
?>