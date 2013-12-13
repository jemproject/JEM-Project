<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Venues Model
 *
 * @package JEM
 *
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
	 */
	function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();

		// Get the paramaters of the active menu item
		$params = $app->getParams('com_jem');

		//get the number of events from database
		$limit		= JRequest::getInt('limit', $params->get('display_venues_num'));
		$limitstart	= JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Venues
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
		$app = JFactory::getApplication();
		$params = $app->getParams('com_jem');

		// Lets load the content if it doesn't already exist
		if (empty($this->_data)) {
			$query = $this->_buildQuery();
			$pagination = $this->getPagination();
			$this->_data = $this->_getList($query, $pagination->limitstart,  $pagination->limit);

			for($i = 0; $i < count($this->_data); $i++) {
				$venue = $this->_data[$i];

				//Create image information
				$venue->limage = JEMImage::flyercreator($venue->locimage, 'venue');

				//Generate Venuedescription
				if (!$venue->locdescription == '' || !$venue->locdescription == '<br />') {
					//execute plugins
					$venue->text	= $venue->locdescription;
					$venue->title 	= $venue->venue;
					JPluginHelper::importPlugin('content');
					$app->triggerEvent('onContentPrepare', array('com_jem.venue', &$venue, &$params, 0));
					$venue->locdescription = $venue->text;
				}

				//build the url
				if(!empty($venue->url) && strtolower(substr($venue->url, 0, 7)) != "http://") {
					$venue->url = 'http://'.$venue->url;
				}

				//prepare the url for output
				if (strlen(htmlspecialchars($venue->url, ENT_QUOTES)) > 35) {
					$venue->urlclean = substr(htmlspecialchars($venue->url, ENT_QUOTES), 0 , 35).'...';
				} else {
					$venue->urlclean = htmlspecialchars($venue->url, ENT_QUOTES);
				}

				//create flag
				if ($venue->country) {
					$venue->countryimg = JEMOutput::getFlag($venue->country);
				}

				//create target link
				$task 	= JRequest::getVar('task', '', '', 'string');

				if ($task == 'archive') {
					$venue->targetlink = JRoute::_(JEMHelperRoute::getVenueRoute($venue->slug.'&task=archive'));
				} else {
					$venue->targetlink = JRoute::_(JEMHelperRoute::getVenueRoute($venue->slug));
				}
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
		if (empty($this->_total)) {
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
		if (empty($this->_pagination)) {
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
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
		$user = JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
		
		//check archive task
		$task 	= JRequest::getVar('task', '', '', 'string');
		
		
		$where = array();
		
		
		if($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' a.published = 1';
		}
		
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';
		$where[] = ' c.published = 1';
		$where[] = ' l.published = 1';
		
		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		//get categories
		$query = 'SELECT'
				. ' l.locimage,l.locdescription,l.url,l.venue,l.street,l.city,l.country,l.postalCode,l.state,l.map,l.latitude,l.longitude,'
				. ' CASE WHEN a.id IS NULL THEN 0 ELSE COUNT(a.id) END AS assignedevents,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', l.id, l.alias) ELSE l.id END as slug'
				. ' FROM #__jem_events as a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'					
				. $where
				. ' GROUP BY l.id'
				. ' ORDER BY l.venue'
				;
		return $query;
	}
}
?>