<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * Model-Events
 **/
class JemModelEvents extends ListModel
{
	/**
	 * Constructor.
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
					'alias', 'a.alias',
					'title', 'a.title',
					'state', 'a.state',
					'times', 'a.times',
					'venue','loc.venue',
					'city','loc.city',
					'dates', 'a.dates',
					'hits', 'a.hits',
					'id', 'a.id',
					'catname', 'c.catname',
					'featured', 'a.featured',
					'access', 'a.access', 'access_level',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @Note  Calling getState in this method will result in recursion.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$search = $this->getUserStateFromRequest($this->context.'.filter_search', 'filter_search');
		$this->setState('filter_search', $search);

		$published = $this->getUserStateFromRequest($this->context.'.filter_state', 'filter_state', '', 'string');
		$this->setState('filter_state', $published);

		$filterfield = $this->getUserStateFromRequest($this->context.'.filter_type', 'filter_type', 0, 'int');
		$this->setState('filter_type', $filterfield);

		$begin = $this->getUserStateFromRequest($this->context.'.filter_begin', 'filter_begin', '', 'string');
		$this->setState('filter_begin', $begin);

		$end = $this->getUserStateFromRequest($this->context.'.filter_end', 'filter_end', '', 'string');
		$this->setState('filter_end', $end);

		$access = $this->getUserStateFromRequest($this->context.'.filter.access', 'filter_access', 0, 'int');
		$this->setState('filter.access', $access);

		// Load the parameters.
		$params = JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('a.dates', 'asc');
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param  string $id A prefix for the store id.
	 * @return string A store id.
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter_search');
		$id .= ':' . $this->getState('filter_published');
		$id .= ':' . $this->getState('filter_type');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select($this->getState('list.select', 'a.*'));

		$query->from($db->quoteName('#__jem_events').' AS a');

		// Join over the venue.
		$query->select('loc.venue, loc.city, loc.state, loc.checked_out AS vchecked_out');
		$query->join('LEFT', '#__jem_venues AS loc ON loc.id = a.locid');

		// Join over the users for the checked out user.
		$query->select('uc.name AS editor');
		$query->join('LEFT', '#__users AS uc ON uc.id = a.checked_out');

		// Join over the user who modified the event.
		$query->select('um.name AS modified_by');
		$query->join('LEFT', '#__users AS um ON um.id = a.modified_by');

		// Join over the author & email.
		$query->select('u.email, u.name AS author');
		$query->join('LEFT', '#__users AS u ON u.id = a.created_by');

		// Join over the view access level.
		$query->select('vl.title AS access_level');
		$query->join('LEFT', '#__viewlevels AS vl ON vl.id = a.access');

        // Join over the country.
        $query->select('co.name AS country');
        $query->join('LEFT', '#__jem_countries AS co ON co.iso2 = loc.country');

		// Filter by published state
		$published = $this->getState('filter_state');
		if (is_numeric($published)) {
			$query->where('a.published = '.(int) $published);
		} elseif ($published === '') {
			$query->where('(a.published IN (0, 1))');
		}

		// Filter by access level.
		if ($access = $this->getState('filter.access')) {
			$query->where('a.access = ' . (int) $access);
		}

		// Filter by Date
		$startDate = $this->getState('filter_begin');
		$endDate   = $this->getState('filter_end');
		if (!empty($startDate) && !empty($endDate)) {
			$query->where('(a.dates >= '.$db->Quote($startDate).')');
			$query->where('(a.enddates <= '.$db->Quote($endDate).')');
		} else {
			if (!empty($startDate)) {
				$query->where('(a.dates IS NULL OR a.dates >= '.$db->Quote($startDate).')');
			}
			if (!empty($endDate)) {
				$query->where('(a.enddates IS NULL OR a.enddates <= '.$db->Quote($endDate).')');
			}
		}

		// Filter by search in title
		$filter = $this->getState('filter_type');
		$search = $this->getState('filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				if ($search) {
					switch ($filter) {
						case 1:
							/* search event-title or alias */
							$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.')');
							break;
						case 2:
							/* search venue */
							$query->where('loc.venue LIKE '.$search);
							break;
						case 3:
							/* search city */
							$query->where('loc.city LIKE '.$search);
							break;
						case 4:
							/* search category */
							/* we need to do that here to get correct counting */
							$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
							$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');
							$query->where('c.catname LIKE '.$search);
							break;
						case 5:
							/* search state */
							$query->where('loc.state LIKE '.$search);
							break;
						case 6:
							/* search country */
							$query->where('co.name LIKE '.$search);
							break;
						case 7:
							/* search all */
                            $query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
                            $query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');
                            $query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.' OR loc.city LIKE '.$search.' OR loc.state LIKE '.$search.' OR co.name LIKE '.$search.' OR loc.venue LIKE '.$search.' OR c.catname LIKE '.$search.')');
							break;
						default:
                            /* search event and location (city, state, country)*/
							$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.' OR loc.city LIKE '.$search.' OR loc.state LIKE '.$search.' OR co.name LIKE '.$search.')');
					}
				}
			}
		}
		$query->group('a.id');

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');

		$query->order($db->escape($orderCol.' '.$orderDirn));

		return $query;
	}

	/**
	 * Method to get the userinformation of edited/submitted events
	 *
	 * @return object
	 */
	public function getItems()
	{
		$items  = parent::getItems();

		$filter = $this->getState('filter_type');
		$search = $this->getState('filter_search');

		foreach ($items as $index => $item) {
			$item->categories = $this->getCategories($item->id);

			# check if the item-categories is empty
			# in case of filtering we will unset the items without the reqeusted category

			if ($search) {
				if ($filter == 4) {
					if (empty($item->categories)) {
						unset ($items[$index]);
					}
				}
			}
		}

		JemHelper::getAttendeesNumbers($items); // writes directly into $items

		if ($items) {
			return $items;
		}

		return array();
	}

	/**
	 * Retrieve Categories
	 *
	 * Due to multi-cat this function is needed
	 * filter-index (4) is pointing to the cats
	 */
	public function getCategories($id)
	{
		$user   = JemFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();

		# Query
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query  = $db->getQuery(true);

		$case_when_c  = ' CASE WHEN ';
		$case_when_c .= $query->charLength('c.alias');
		$case_when_c .= ' THEN ';
		$id_c = $query->castAsChar('c.id');
		$case_when_c .= $query->concatenate(array($id_c, 'c.alias'), ':');
		$case_when_c .= ' ELSE ';
		$case_when_c .= $id_c.' END as catslug';

		$query->select(array('DISTINCT c.id','c.catname','c.access','c.path','c.checked_out AS cchecked_out','c.color',$case_when_c));
		$query->from('#__jem_categories as c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');

		$query->select(array('a.id AS multi'));
		$query->join('LEFT','#__jem_events AS a ON a.id = rel.itemid');

		$query->where('rel.itemid ='.(int)$id);

		###################
		## FILTER-ACCESS ##
		###################

		# Filter by access level.
		$access = $this->getState('filter.access');

		if ($access){
			$groups = implode(',', $levels);
			$query->where('c.access IN ('.$groups.')');
		}

		###################
		## FILTER-SEARCH ##
		###################

		# define variables
		$filter = $this->getState('filter_type');
		$search = $this->getState('filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('c.id = ' . (int)substr($search, 3));
			} else {
			/* In case of a search string the db query had already filtered out
			 *  all events without a matching caterory.
			 * So we can here return all categories of the event
			 *  which the user is allowed to see.
			 */
			/*
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				if($search) {
					if ($filter == 4) {
						$query->where('c.catname LIKE '.$search);
					}
				}
			 */
			}
		}

		$db->setQuery($query);
		$cats = $db->loadObjectList();

		return $cats;
	}
}
