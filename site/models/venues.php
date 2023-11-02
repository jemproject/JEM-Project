<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/eventslist.php';

/**
 * Model: Venues
 */
class JemModelVenues extends JemModelEventslist
{
	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// parent::populateState($ordering, $direction);

		$app    = Factory::getApplication();
		$params = $app->getParams();
		$task   = $app->input->getCmd('task','');

		// List state information
		$limit  = $app->input->getInt('limit', $params->get('display_venues_num'));
		$this->setState('list.limit', $limit);
		$limitstart = $app->input->getInt('limitstart', 0);
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
		$this->setState('list.start', $limitstart);

		# params
		$this->setState('params', $params);
	//	$this->setState('filter.published', 1);
		$this->setState('filter.groupby', array('l.id'));

		# publish state
		$this->_populatePublishState($task);
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function getListQuery()
	{
		$user   = JemFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();

		// Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query  = $db->getQuery(true);

		$case_when_l  = ' CASE WHEN ';
		$case_when_l .= $query->charLength('l.alias');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('l.id');
		$case_when_l .= $query->concatenate(array($id_l, 'l.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as venueslug';

		$query->select(array('l.id AS locid', 'l.locimage', 'l.locdescription', 'l.url', 'l.venue', 'l.created', 'l.created_by',
		                     'l.street', 'l.postalCode', 'l.city', 'l.state', 'l.country',
		                     'l.map', 'l.latitude', 'l.longitude', 'l.published',
		                     'l.custom1', 'l.custom2', 'l.custom3', 'l.custom4', 'l.custom5', 'l.custom6', 'l.custom7', 'l.custom8', 'l.custom9', 'l.custom10',
		                     'l.meta_keywords', 'l.meta_description', 'l.checked_out', 'l.checked_out_time'));
		$query->select(array($case_when_l));
		$query->from('#__jem_venues as l');
		$query->join('LEFT', '#__jem_events AS a ON l.id = a.locid');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		// where
		$where = array();
		// all together: if published or the user is creator of the venue or allowed to edit or publish venues
		if (empty($user->id)) {
			$where[] = ' l.published = 1';
		}
		// no limit if user can publish or edit foreign venues
		elseif ($user->can(array('edit', 'publish'), 'venue')) {
			$where[] = ' l.published IN (0,1)';
		}
		// user maybe creator
		else {
			$where[] = ' (l.published = 1 OR (l.published = 0 AND l.created_by = ' . $this->_db->Quote($user->id) . '))';
		}

		$query->where($where);
		$query->group(array('l.id'));
		$query->order(array('l.ordering', 'l.venue'));

		return $query;
	}

	/**
	 * Method to get a list of venues
	 * We are defining it as we don't want to fire up the getItems function of the eventslist-model
	 */
	public function getItems()
	{
		// Get a storage key.
		$store = $this->getStoreId();

		// Try to load the data from internal storage.
		if (!isset($this->cache[$store]))
		{
			// Load the list items.
			$query = $this->_getListQuery();

			try
			{
				$items = $this->_getList($query, $this->getStart(), $this->getState('list.limit'));
			}
			catch (RuntimeException $e)
			{
				$this->setError($e->getMessage());

				return false;
			}

			// Add the items to the internal cache.
			$this->cache[$store] = $items;
		}

		return $this->cache[$store];
	}

	public function AssignedEvents($id, $state = 1)
	{
		$user   = JemFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query  = $db->getQuery(true);

		$query->select(array('a.id'));
		$query->from('#__jem_events as a');
		$query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
	    $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		# venue-id
		$query->where('l.id= '. $db->quote($id));
		# view access level
		$query->where('a.access IN (' . implode(',', $levels) . ')');
		// Note: categories are filtered in getCategories() called below
		//       so we don't need to check c.access here

		####################
		## FILTER-PUBLISH ##
		####################

		# Filter by published state.
		if ((int)$state === 1) {
			$where_pub = $this->_getPublishWhere();
			if (!empty($where_pub)) {
				$query->where('(' . implode(' OR ', $where_pub) . ')');
			} else {
				// something wrong - fallback to published events
				$query->where('a.published = 1');
			}
		} else {
			$query->where('a.published = '.$db->quote($state));
		}

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
		$nr  = is_array($ids) ? count($ids) : 0;

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
	public function getCategories($id)
	{
		$user     = JemFactory::getUser();
		$levels   = $user->getAuthorisedViewLevels();
		$settings = JemHelper::globalattribs();

		// Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$case_when_c  = ' CASE WHEN ';
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

		###################################
		## FILTER - MAINTAINER/JEM GROUP ##
		###################################

		# as maintainter someone who is registered can see a category that has special rights
		# let's see if the user has access to this category.

	//	$query3	= $db->getQuery(true);
	//	$query3 = 'SELECT gr.id'
	//			. ' FROM #__jem_groups AS gr'
	//			. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
	//			. ' WHERE g.member = ' . (int) $user->get('id')
	//		//	. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
	//			. ' AND g.member NOT LIKE 0';
	//	$db->setQuery($query3);
	//	$groupnumber = $db->loadColumn();

	//	$jemgroups = implode(',',$groupnumber);

	// JEM groups doesn't overrule view access levels!
	//	if ($jemgroups) {
	//		$query->where('(c.access IN ('.$groups.') OR c.groupid IN ('.$jemgroups.'))');
	//	} else {
			$query->where('(c.access IN ('.implode(',', $levels).'))');
	//	}

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
			\Joomla\Utilities\ArrayHelper::toInteger($categoryId);
			$categoryId = implode(',', $categoryId);
			$type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('c.id '.$type.' ('.$categoryId.')');
		}

		# filter set by day-view
		$requestCategoryId = $this->getState('filter.req_catid');

		if ($requestCategoryId) {
			$query->where('c.id = '.$db->quote($requestCategoryId));
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
				$search = $db->Quote('%'.$db->escape($search, true).'%', false);

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
