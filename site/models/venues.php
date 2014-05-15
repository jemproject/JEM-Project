<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


jimport('joomla.application.component.model');

/**
 * Model-Venues
 */
class JemModelVenues extends JModelLegacy
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
	public function __construct()
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
		if (empty($this->_data)) 
		{
			$query = $this->_buildQuery();
			$pagination = $this->getPagination();
			$this->_data = $this->_getList($query, $pagination->limitstart,  $pagination->limit);

			for($i = 0; $i < count($this->_data); $i++) {
				$venue = $this->_data[$i];

				// Create image information
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
				// TODO: Should be part of view! Then use $this->escape()
				if (strlen($venue->url) > 35) {
					$venue->urlclean = htmlspecialchars(substr($venue->url, 0 , 35)).'...';
				} else {
					$venue->urlclean = htmlspecialchars($venue->url);
				}

				//create flag
				if ($venue->country) {
					$venue->countryimg = JemHelperCountries::getCountryFlag($venue->country);
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
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		$jemsettings 		= JemHelper::config();
		$app 				= JFactory::getApplication();
		$itemid 			= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		$params 			= $app->getParams('com_jem');
		
		$query 				= $this->_buildQuery();
		$total				= $this->_getListCount($query);
		$limit				= JRequest::getInt('limit', $params->get('display_venues_num'));
		$limitstart			= JRequest::getInt('limitstart');
		
		
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination($total, $limitstart, $limit);
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQuery()
	{
		$user 	= JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
		$task 	= JRequest::getVar('task', '', '', 'string');

		// Query
		$db 	= JFactory::getDBO();
		$query	= $db->getQuery(true);
		
		$case_when_l = ' CASE WHEN ';
		$case_when_l .= $query->charLength('l.alias');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('l.id');
		$case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as slug';
		
		$query->select(array('l.locimage','l.locdescription','l.url','l.venue','l.street','l.city','l.country','l.postalCode','l.state','l.map','l.latitude','l.longitude'));
		$query->select(array('CASE WHEN a.id IS NULL THEN 0 ELSE COUNT(a.id) END AS assignedevents',$case_when_l));
		$query->from('#__jem_events as a');
		$query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');
		
		// where
		$where = array();
		
		if($task == 'archive') {
			$where[] = ' a.published = 2';
		} else {
			$where[] = ' a.published = 1';
		}
				
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';
		$where[] = ' c.published = 1';
		$where[] = ' l.published = 1';
				
		
		$query->where($where);
		$query->group(array('l.id','l.venue'));
		
	
		return $query;
	}
}
?>