<?php
/**
 * @version 1.9.1
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
					'state', 'a.state',
					'times', 'a.times',
					'venue','loc.venue',
					'city','loc.city',
					'dates', 'a.dates',
					'hits', 'a.hits',
					'id', 'a.id',
					'catname', 'c.catname',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 *
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app = JFactory::getApplication('administrator');

		$search = $this->getUserStateFromRequest($this->context.'.filter_search', 'filter_search');
		$this->setState('filter_search', $search);

		//	$accessId = $this->getUserStateFromRequest($this->context.'.filter.access', 'filter_access', null, 'int');
		//	$this->setState('filter.access', $accessId);

		$published = $this->getUserStateFromRequest($this->context.'.filter_state', 'filter_state', '', 'string');
		$this->setState('filter_state', $published);

		$filterfield = $this->getUserStateFromRequest($this->context.'.filter', 'filter', '', 'int');
		$this->setState('filter', $filterfield);

		//  $categoryId = $this->getUserStateFromRequest($this->context.'.filter.category_id', 'filter_category_id', '');
		//  $this->setState('filter.category_id', $categoryId);

		//	$language = $this->getUserStateFromRequest($this->context.'.filter.language', 'filter_language', '');
		//	$this->setState('filter.language', $language);


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
		//$id.= ':' . $this->getState('filter.access');
		$id.= ':' . $this->getState('filter_published');
		$id.= ':' . $this->getState('filter');
		//$id.= ':' . $this->getState('filter.category_id');
		//$id.= ':' . $this->getState('filter.language');

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
		$user	= JFactory::getUser();

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
		$query->join('LEFT', '#__jem_venues AS loc ON loc.id=a.locid');


		// Join over the language
		//$query->select('l.title AS language_title');
		//$query->join('LEFT', $db->quoteName('#__languages').' AS l ON l.lang_code = a.language');

		// Join over the users for the checked out user.
		$query->select('uc.name AS editor');
		$query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');

		// Join over the asset groups.
		/*$query->select('ag.title AS access_level');
		$query->join('LEFT', '#__viewlevels AS ag ON ag.id = a.access');*/

		// Join over the cat_relations
		$query->select('rel.*');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid=a.id');

		// Join over the categories.
		$query->select('c.catname, c.id AS catid');
		$query->join('LEFT', '#__jem_categories AS c ON c.id=rel.catid');


		// Join over the author & email.
		$query->select('u.email, u.name AS author');
		$query->join('LEFT', '#__users AS u ON u.id=a.created_by');


		// Implement View Level Access
		//if (!$user->authorise('core.admin'))
		//{
		//	$groups	= implode(',', $user->getAuthorisedViewLevels());
		//	$query->where('a.access IN ('.$groups.')');
		//}

		// Filter by published state
		$published = $this->getState('filter_state');
		if (is_numeric($published)) {
			$query->where('a.published = '.(int) $published);
		} elseif ($published === '') {
			$query->where('(a.published IN (0, 1))');
		}

		// Filter by search in title
		$filter = $this->getState('filter');
		$search = $this->getState('filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');

				/* search venue or alias */
				if ($search && $filter == 1) {
				$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.')');
				}

				/* search city */
				if ($search && $filter == 2) {
				$query->where('loc.city LIKE '.$search);
				}

				/* search state */
				if ($search && $filter == 3) {
					$query->where('loc.state LIKE '.$search);
				}

				/* search country */
				if ($search && $filter == 4) {
					$query->where('loc.country LIKE '.$search);
				}

				/* search category */
				if ($search && $filter == 5) {
					$query->where('c.catname LIKE '.$search);
				}

				/* search all */
				if ($search && $filter == 6) {
					$query->where('(a.title LIKE '.$search.' OR a.alias LIKE '.$search.' OR c.catname LIKE '.$search.' OR loc.city LIKE '.$search.' OR loc.state LIKE '.$search.' OR loc.country LIKE '.$search.')');
				}


			}
		}
		$query->group('a.id');


		// Add the list ordering clause.
		$orderCol	= $this->state->get('list.ordering');
		$orderDirn	= $this->state->get('list.direction');
		//if ($orderCol == 'a.ordering' || $orderCol == 'category_title') {
		//	$orderCol = 'c.title '.$orderDirn.', a.ordering';
		//}
		$query->order($db->escape($orderCol.' '.$orderDirn));
		//echo nl2br(str_replace('#__','jos_',$query));
		return $query;
	}

	/**
	 * Method to (un)publish a venue
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user 	= JFactory::getUser();
		$userid = $user->get('id');

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__jem_venues'
					. ' SET published = '. (int) $publish
					. ' WHERE id IN ('. $cids .')'
					. ' AND ( checked_out = 0 OR ( checked_out = ' .$userid. ' ) )'
					;

			$this->_db->setQuery( $query );

			if (!$this->_db->query()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
			}
		}
	}

	/**
	 * Method to move a venue
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function move($direction)
	{
		$row = JTable::getInstance('jem_venues', '');

		if (!$row->load( $this->_id ) ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		if (!$row->move( $direction )) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		return true;
	}


	/**
	 * Method to remove a venue
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function remove($cid)
	{
		$cids = implode( ',', $cid );

		$query = 'SELECT v.id, v.venue, COUNT( e.locid ) AS numcat'
				. ' FROM #__jem_venues AS v'
				. ' LEFT JOIN #__jem_events AS e ON e.locid = v.id'
				. ' WHERE v.id IN ('. $cids .')'
				. ' GROUP BY v.id'
				;
		$this->_db->setQuery( $query );

		if (!($rows = $this->_db->loadObjectList())) {
			JError::raiseError( 500, $this->_db->stderr() );
			return false;
		}

		$err = array();
		$cid = array();
		foreach ($rows as $row) {
			if ($row->numcat == 0) {
				$cid[] = $row->id;
			} else {
				$err[] = $row->venue;
			}
		}

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'DELETE FROM #__jem_venues'
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		if (count( $err )) {
			$cids 	= implode( ', ', $err );
			$msg 	= JText::sprintf('COM_JEM_VENUE_ASSIGNED_EVENT', $cids );
			return $msg;
		} else {
			$total 	= count( $cid );
			$msg 	= $total.' '.JText::_('COM_JEM_VENUES_DELETED');
			return $msg;
		}
	}

	/**
	 * Method to get the userinformation of edited/submitted venues
	 *
	 * @access private
	 * @return object
	 *
	 */
	public function getItems()
	{
		$items = parent::getItems();

		$count = count($items);

		if ($count) {
			$items = JEMHelper::getAttendeesNumbers($items);
		}

		for ($i=0, $n=$count; $i < $n; $i++) {
			// Get editor name
			$query = 'SELECT name'
					. ' FROM #__users'
					. ' WHERE id = '.$items[$i]->modified_by
					;

			$this->_db->setQuery( $query );
			$items[$i]->editor = $this->_db->loadResult();

			$items[$i]->categories = $this->getCategories($items[$i]->id);

			/*
			 * Get nr of assigned events
			*/
			$query = 'SELECT COUNT( id )'
					.' FROM #__jem_events'
					.' WHERE locid = ' . (int)$items[$i]->id
					;

			$this->_db->setQuery($query);
			$items[$i]->assignedevents = $this->_db->loadResult();
		}

		return $items;
	}

	function getCategories($id)
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
