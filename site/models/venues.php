<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


require_once dirname(__FILE__) . '/eventslist.php';
/**
 * Model-Venues
 */
class JemModelVenues extends JemModelEventslist
{

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// parent::populateState($ordering, $direction);

		$app 			= JFactory::getApplication();
		$settings		= JemHelper::globalattribs();
		$jinput			= JFactory::getApplication()->input;
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		$params 		= $app->getParams();
		$task           = $jinput->get('task','','cmd');

		// List state information
		$limitstart = JRequest::getInt('limitstart');
		$this->setState('list.start', $limitstart);

		$limit		= JRequest::getInt('limit', $params->get('display_venues_num'));
		$this->setState('list.limit', $limit);

		# params
		$this->setState('params', $params);

		$this->setState('filter.published',1);

		$this->setState('filter.access', true);
		$this->setState('filter.groupby',array('l.id','l.venue'));

	}


	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function getListQuery()
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
		$case_when_l .= $id_l.' END as venueslug';

		$query->select(array('l.id AS locid','l.locimage','l.locdescription','l.url','l.venue','l.street','l.city','l.country','l.postalCode','l.state','l.map','l.latitude','l.longitude'));
		$query->select(array($case_when_l));
		$query->from('#__jem_venues as l');
		$query->join('LEFT', '#__jem_events AS a ON l.id = a.locid');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		// where
		$where = array();
		// if published or the user is creator of the event
		if (empty($user->id)) {
			$where[] = ' l.published = 1';
		}
		// TODO: no limit if user can publish or edit foreign venues
		else {
			$where[] = ' (l.published = 1 OR l.created_by = ' . $this->_db->Quote($user->id) . ')';
		}

		$query->where($where);
		$query->group(array('l.id','l.venue'));
		$query->order(array('l.ordering', 'l.venue'));

		return $query;
	}


	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		// Get a storage key.
		$store = $this->getStoreId();
		$query = $this->_getListQuery();
		$items = $this->_getList($query, $this->getStart(), $this->getState('list.limit'));

		$app = JFactory::getApplication();
		$params = clone $this->getState('params');

		// Lets load the content if it doesn't already exist
		if ($items) {

			foreach ($items as $item) {

				// Create image information
				$item->limage = JEMImage::flyercreator($item->locimage, 'venue');

				//Generate Venuedescription
				if (!$item->locdescription == '' || !$item->locdescription == '<br />') {
					//execute plugins
					$item->text	= $item->locdescription;
					$item->title 	= $item->venue;
					JPluginHelper::importPlugin('content');
					$app->triggerEvent('onContentPrepare', array('com_jem.venue', &$item, &$params, 0));
					$item->locdescription = $item->text;
				}

				//build the url
				if(!empty($item->url) && strtolower(substr($item->url, 0, 7)) != "http://") {
					$item->url = 'http://'.$item->url;
				}


				//prepare the url for output
				// TODO: Should be part of view! Then use $this->escape()
				if (strlen($item->url) > 35) {
					$item->urlclean = htmlspecialchars(substr($item->url, 0 , 35)).'...';
				} else {
					$item->urlclean = htmlspecialchars($item->url);
				}

				//create flag
				if ($item->country) {
					$item->countryimg = JemHelperCountries::getCountryFlag($item->country);
				}

				//create target link
				$task 	= JRequest::getVar('task', '', '', 'string');

				$item->linkEventsArchived = JRoute::_(JEMHelperRoute::getVenueRoute($item->venueslug.'&task=archive'));
				$item->linkEventsPublished = JRoute::_(JEMHelperRoute::getVenueRoute($item->venueslug));

				$item->EventsPublished = $this->AssignedEvents($item->locid,'1');
				$item->EventsArchived = $this->AssignedEvents($item->locid,'2');
		}

			// Add the items to the internal cache.
			$this->cache[$store] = $items;
			return $this->cache[$store];
		}

		return array();

	}


	function AssignedEvents($id,$state=1)
	{
		$db 	= JFactory::getDBO();
		$query	= $db->getQuery(true);

		$case_when_l = ' CASE WHEN ';
		$case_when_l .= $query->charLength('l.alias');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('l.id');
		$case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as venueslug';

		$query->select(array('a.id'));
		$query->from('#__jem_events as a');
		$query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
	    $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		# venue-id
		$query->where('l.id= '. $id);
		# state
		$query->where('a.published= '.$state);


		#####################
		### FILTER - BYCAT ##
		#####################

		$cats = $this->getCategories('all');
		if (!empty($cats)) {
			$query->where('c.id  IN (' . implode(',', $cats) . ')');
		}


		$db->setQuery($query);
		$ids = $db->loadColumn(0);
		$ids = array_unique($ids);
		$nr = count($ids);

		if (empty($nr)) {
			$nr = 0;
		}

		return ($nr);
	}



	/**
	 * Retrieve Categories
	 *
	 * Due to multi-cat this function is needed
	 * filter-index (4) is pointing to the cats
	 */

	function getCategories($id)
	{
		$user 			= JFactory::getUser();
		$userid			= (int) $user->get('id');
		$levels 		= $user->getAuthorisedViewLevels();
		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();
		$catswitch 		= $params->get('categoryswitch', '0');
		$settings 		= JemHelper::globalattribs();

		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);

		$case_when_c = ' CASE WHEN ';
		$case_when_c .= $query->charLength('c.alias');
		$case_when_c .= ' THEN ';
		$id_c = $query->castAsChar('c.id');
		$case_when_c .= $query->concatenate(array($id_c, 'c.alias'), ':');
		$case_when_c .= ' ELSE ';
		$case_when_c .= $id_c.' END as catslug';

		$query->select(array('DISTINCT c.id','c.catname','c.access','c.checked_out AS cchecked_out','c.color',$case_when_c));
		$query->from('#__jem_categories as c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');

		$query->select(array('a.id AS multi'));
		$query->join('LEFT','#__jem_events AS a ON a.id = rel.itemid');

		if ($id != 'all'){
			$query->where('rel.itemid ='.(int)$id);
		}

		$query->where('c.published = 1');


		###################
		## FILTER-ACCESS ##
		###################

		# Filter by access level.
		$access = $this->getState('filter.access');


		###################################
		## FILTER - MAINTAINER/JEM GROUP ##
		###################################

		# as maintainter someone who is registered can see a category that has special rights
		# let's see if the user has access to this category.


		$query3	= $db->getQuery(true);
		$query3 = 'SELECT gr.id'
				. ' FROM #__jem_groups AS gr'
				. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
				. ' WHERE g.member = ' . (int) $user->get('id')
			//	. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
				. ' AND g.member NOT LIKE 0';
		$db->setQuery($query3);
		$groupnumber = $db->loadColumn();

		if ($access){
			$groups = implode(',', $user->getAuthorisedViewLevels());
			$jemgroups = implode(',',$groupnumber);

			if ($jemgroups) {
				$query->where('(c.access IN ('.$groups.') OR c.groupid IN ('.$jemgroups.'))');
			} else {
				$query->where('(c.access IN ('.$groups.'))');
			}
		}


		#######################
		## FILTER - CATEGORY ##
		#######################

		# set filter for top_category
		$top_cat = $this->getState('filter.category_top');

		if ($top_cat) {
			$query->where($top_cat);
		}

		# Filter by a single or group of categories.
		$categoryId = $this->getState('filter.category_id');

		if (is_numeric($categoryId)) {
			$type = $this->getState('filter.category_id.include', true) ? '= ' : '<> ';
			$query->where('c.id '.$type.(int) $categoryId);
		}
		elseif (is_array($categoryId) && count($categoryId)) {
			JArrayHelper::toInteger($categoryId);
			$categoryId = implode(',', $categoryId);
			$type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('c.id '.$type.' ('.$categoryId.')');
		}

		# filter set by day-view
		$requestCategoryId = $this->getState('filter.req_catid');

		if ($requestCategoryId) {
			$query->where('c.id = '.$requestCategoryId);
		}

		###################
		## FILTER-SEARCH ##
		###################

		# define variables
		$filter = $this->getState('filter.filter_type');
		$search = $this->getState('filter.filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('c.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				if ($search && $settings->get('global_show_filter')) {
					if ($filter == 4) {
						$query->where('c.catname LIKE '.$search);
					}
				}
			}
		}

		$db->setQuery($query);

		if ($id == 'all') {
			$cats = $db->loadColumn(0);
			$cats = array_unique($cats);
		} else {
			$cats = $db->loadObjectList();
		}

		return $cats;
	}

}
?>