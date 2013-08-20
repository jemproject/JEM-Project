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

		$db = JFactory::getDbo();


		/* Get nr of all published events */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_events');
		$query->where(array('published = 1'));

		$db->setQuery($query);
		$_events[] = $db->loadResult();


		/* Get nr of all unpublished events */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_events');
		$query->where(array('published = 0'));

		$db->setQuery($query);
		$_events[] = $db->loadResult();


		/* Get nr of all archived events */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_events');
		$query->where(array('published = 2'));

		$db->setQuery($query);
		$_events[] = $db->loadResult();


		/* Get nr of all trashed events */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_events');
		$query->where(array('published = -2'));

		$db->setQuery($query);
		$_events[] = $db->loadResult();


  		/* Get total nr of events */
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

		$db = JFactory::getDbo();


		/* Get nr of all published venues */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_venues');
		$query->where(array('published = 1'));

		$db->setQuery($query);
		$_venue[] = $db->loadResult();


		/* Get nr of all unpublished venues */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_venues');
		$query->where(array('published = 0'));

		$db->setQuery($query);
		$_venue[] = $db->loadResult();


		/* Get total nr of venues */
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

		$db = JFactory::getDbo();


		/* Get nr of all published categories */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_categories');
		$query->where(array('published = 1'));

		$db->setQuery($query);
		$_category[] = $db->loadResult();


		/* Get nr of all published categories */
		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__jem_categories');
		$query->where(array('published = 0'));

		$db->setQuery($query);
		$_category[] = $db->loadResult();


  		/* Get total nr of categories */
  		$_category[] = array_sum($_category);

		return $_category;
	}
}
?>