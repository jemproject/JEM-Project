<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Home Model
 *
 * @package JEM
 * 
 */
class JEMModelJEM extends JModelLegacy
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
					. ' FROM #__jem_events'
					. ' WHERE published = 1'
					;

		$this->_db->SetQuery($query);
  		$_events[] = $this->_db->loadResult();

		/*
		* Get nr of all unpublished events
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__jem_events'
					. ' WHERE published = 0'
					;

		$this->_db->SetQuery($query);
  		$_events[] = $this->_db->loadResult();

		/*
		* Get nr of all archived events
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__jem_events'
					. ' WHERE published = 2'
					;

		$this->_db->SetQuery($query);
  		$_events[] = $this->_db->loadResult();
  		
  		
  		/*
  		 * Get nr of all trashed events
  		*/
  		$query = 'SELECT count(*)'
					. ' FROM #__jem_events'
					. ' WHERE published = -2'
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
					. ' FROM #__jem_venues'
					. ' WHERE published = 1'
					;

		$this->_db->SetQuery($query);
  		$_venue[] = $this->_db->loadResult();

		/*
		* Get nr of all unpublished venues
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__jem_venues'
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
					. ' FROM #__jem_categories'
					. ' WHERE published = 1'
					;

		$this->_db->SetQuery($query);
  		$_category[] = $this->_db->loadResult();

		/*
		* Get nr of all unpublished categories
		*/
		$query = 'SELECT count(*)'
					. ' FROM #__jem_categories'
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