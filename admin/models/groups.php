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
 * JEM Component Groups Model
 *
 **/
class JEMModelGroups extends JModelList
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
					'name', 'a.name',
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
		parent::populateState('a.name', 'asc');
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
		$query->from($db->quoteName('#__jem_groups').' AS a');


		// Join over the users for the checked out user.
		//$query->select('loc.venue, loc.city, loc.state, loc.checked_out AS vchecked_out');
		//$query->join('LEFT', '#__jem_venues AS loc ON loc.id=a.locid');


		// Join over the language
		//$query->select('l.title AS language_title');
		//$query->join('LEFT', $db->quoteName('#__languages').' AS l ON l.lang_code = a.language');

		// Join over the users for the checked out user.
		//$query->select('uc.name AS editor');
		//$query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');

		// Join over the asset groups.
		/*$query->select('ag.title AS access_level');
		$query->join('LEFT', '#__viewlevels AS ag ON ag.id = a.access');*/

		// Join over the cat_relations
		//$query->select('rel.*');
		//$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid=a.id');

		// Join over the categories.
		//$query->select('c.catname, c.id AS catid');
		//$query->join('LEFT', '#__jem_categories AS c ON c.id=rel.catid');


		// Join over the author & email.
		//$query->select('u.email, u.name AS author');
		//$query->join('LEFT', '#__users AS u ON u.id=a.created_by');


		// Implement View Level Access
		//if (!$user->authorise('core.admin'))
		//{
		//	$groups	= implode(',', $user->getAuthorisedViewLevels());
		//	$query->where('a.access IN ('.$groups.')');
		//}

		// Filter by published state
		//$published = $this->getState('filter_state');
		//if (is_numeric($published)) {
		//	$query->where('a.published = '.(int) $published);
		//} elseif ($published === '') {
		//	$query->where('(a.published IN (0, 1))');
		//}

		// Filter by search in title
		//$filter = $this->getState('filter');
		$search = $this->getState('filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');


				/* search category */
				if ($search) {
					$query->where('a.name LIKE '.$search);
				}



			}
		}
		// $query->group('a.id');


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
	 * Method to get the userinformation of edited/submitted venues
	 *
	 * @access private
	 * @return object
	 *
	 */
	public function getItems()
	{
		$items = parent::getItems();

		/*
		$count = count($items);

		if ($count) {

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


			// Get nr of assigned events
			$query = 'SELECT COUNT( id )'
					.' FROM #__jem_events'
					.' WHERE locid = ' . (int)$items[$i]->id
					;

			$this->_db->setQuery($query);
			$items[$i]->assignedevents = $this->_db->loadResult();
		}
		*/

		return $items;
	}

}
