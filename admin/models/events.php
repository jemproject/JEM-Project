<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * JEM Component Events Model
 *
 **/
class JEMModelEvents extends JModelList
{
	/**
	 * Constructor.
	 *
	 * @param	array	An optional associative array of configuration settings.
	 * @see		JController
	 *
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
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$search = $this->getUserStateFromRequest($this->context.'.filter_search', 'filter_search');
		$this->setState('filter_search', $search);

		$published = $this->getUserStateFromRequest($this->context.'.filter_state', 'filter_state', '', 'string');
		$this->setState('filter_state', $published);

		$filterfield = $this->getUserStateFromRequest($this->context.'.filter', 'filter', '', 'int');
		$this->setState('filter', $filterfield);

		$begin = $this->getUserStateFromRequest($this->context.'.filter_begin', 'filter_begin', '', 'string');
		$this->setState('filter_begin', $begin);

		$end = $this->getUserStateFromRequest($this->context.'.filter_end', 'filter_end', '', 'string');
		$this->setState('filter_end', $end);

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
	 * @param	string		$id	A prefix for the store id.
	 * @return	string		A store id.
	 *
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id.= ':' . $this->getState('filter_search');
		$id.= ':' . $this->getState('filter_published');
		$id.= ':' . $this->getState('filter');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return	JDatabaseQuery
	 *
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
				$this->getState(
						'list.select',
						'a.*'
				)
		);
		$query->from($db->quoteName('#__jem_events').' AS a');

		// Join over the users for the checked out user.
		$query->select('loc.venue, loc.city, loc.state, loc.checked_out AS vchecked_out');
		$query->join('LEFT', '#__jem_venues AS loc ON loc.id = a.locid');

		// Join over the users for the checked out user.
		$query->select('uc.name AS editor');
		$query->join('LEFT', '#__users AS uc ON uc.id = a.checked_out');

		// Join over the cat_relations
		$query->select('rel.itemid, rel.ordering');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');

		// Join over the categories.
		$query->select('c.catname, c.id AS catid');
		$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

		// Join over the author & email.
		$query->select('u.email, u.name AS author');
		$query->join('LEFT', '#__users AS u ON u.id = a.created_by');

		// Filter by published state
		$published = $this->getState('filter_state');
		if (is_numeric($published)) {
			$query->where('a.published = '.(int) $published);
		} elseif ($published === '') {
			$query->where('(a.published IN (0, 1))');
		}

		// Filter by begin date
		// @todo test with multi-day
		$begin = $this->getState('filter_begin');
		if (!empty($begin)) {
			$query->where('a.dates >= '.$db->Quote($begin));
		}

		// Filter by end date
		// @todo test with multi-day
		$end = $this->getState('filter_end');
		if (!empty($end)) {
			$query->where('a.enddates <= '.$db->Quote($end));
		}

		// Filter by search in title
		$filter = $this->getState('filter');
		$search = $this->getState('filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				if($search) {
					switch($filter) {
						case 1:
							/* search venue or alias */
							$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.')');
							break;
						case 2:
							/* search city */
							$query->where('loc.city LIKE '.$search);
							break;
						case 3:
							/* search state */
							$query->where('loc.state LIKE '.$search);
							break;
						case 4:
							/* search country */
							$query->where('loc.country LIKE '.$search);
							break;
						case 5:
							/* search category */
							$query->where('c.catname LIKE '.$search);
							break;
						case 6:
						default:
							/* search all */
							$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.' OR c.catname LIKE '.$search.' OR loc.city LIKE '.$search.' OR loc.state LIKE '.$search.' OR loc.country LIKE '.$search.')');
					}
				}
			}
		}
		$query->group('a.id');

		// Add the list ordering clause.
		$orderCol	= $this->state->get('list.ordering');
		$orderDirn	= $this->state->get('list.direction');

		$query->order($db->escape($orderCol.' '.$orderDirn));
		return $query;
	}


	/**
	 * Method to get the userinformation of edited/submitted events
	 * @return object
	 */
	public function getItems()
	{
		$items = parent::getItems();

		if(!count($items)) {
			return $items;
		}

		$items = JEMHelper::getAttendeesNumbers($items);

		foreach ($items as $item) {
			$item->categories = $this->getCategories($item->id);
		}

		return $items;
	}

	/**
	 * Get the categories of an event
	 * @param unknown $id
	 */
	protected function getCategories($id)
	{
		$query = 'SELECT DISTINCT c.id, c.catname, c.checked_out AS cchecked_out'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				;

		$this->_db->setQuery($query);

		$this->_cats = $this->_db->loadObjectList();

		$count = count($this->_cats);
		for($i = 0; $i < $count; $i++)
		{
			$item = $this->_cats[$i];
			$cats = new JEMCategories($item->id);
			$item->parentcats = $cats->getParentlist();
		}

		return $this->_cats;
	}
}
