<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Model: Venues
 **/
class JemModelVenues extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param  array An optional associative array of configuration settings.
	 * @see    JController
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
					'id', 'a.id',
					'venue', 'a.venue',
					'alias', 'a.alias',
					'state', 'a.state',
					'country', 'a.country',
					'url', 'a.url',
					'street', 'a.street',
					'postalCode', 'a.postalCode',
					'city', 'a.city',
					'ordering', 'a.ordering',
					'created', 'a.created',
					'assignedevents'
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @Note Calling getState in this method will result in recursion.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$search = $this->getUserStateFromRequest($this->context.'.filter_search', 'filter_search');
		$this->setState('filter_search', $search);

		$published = $this->getUserStateFromRequest($this->context.'.filter_state', 'filter_state', '', 'string');
		$this->setState('filter_state', $published);

		$filter_type = $this->getUserStateFromRequest($this->context.'.filter_type', 'filter_type', 0, 'int');
		$this->setState('filter_type', $filter_type);

		$params = ComponentHelper::getParams('com_jem');
		$this->setState('params', $params);

		parent::populateState('a.venue', 'asc');
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
		$query->select(
				$this->getState(
						'list.select',
						'a.id, a.venue, a.alias, a.url, a.street, a.postalCode, a.city, a.state, a.country,'
						.'a.latitude, a.longitude, a.locdescription, a.meta_keywords, a.meta_description,'
						.'a.locimage, a.map, a.created_by, a.author_ip, a.created, a.modified,'
						.'a.modified_by, a.version, a.published, a.checked_out, a.checked_out_time,'
						.'a.ordering, a.publish_up, a.publish_down'
				)
		);
		$query->from($db->quoteName('#__jem_venues').' AS a');

		// Join over the users for the checked out user.
		$query->select('uc.name AS editor');
		$query->join('LEFT', '#__users AS uc ON uc.id = a.checked_out');

		// Join over the user who modified the event.
		$query->select('um.name AS modified_by');
		$query->join('LEFT', '#__users AS um ON um.id = a.modified_by');

		// Join over the author & email.
		$query->select('u.email, u.name AS author');
		$query->join('LEFT', '#__users AS u ON u.id = a.created_by');

		// Join over the assigned events
		$query->select('COUNT(e.locid) AS assignedevents');
		$query->join('LEFT OUTER', '#__jem_events AS e ON e.locid = a.id');
		$query->group('a.id');

		// Filter by published state
		$published = $this->getState('filter_state');
		if (is_numeric($published)) {
			$query->where('a.published = '.(int) $published);
		} elseif ($published === '') {
			$query->where('(a.published IN (0, 1))');
		}

		// Filter by search in title
		$filter_type = $this->getState('filter_type');
		$search      = $this->getState('filter_search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%', false);

				if($search) {
					switch($filter_type) {
						case 1:
							/* search venue or alias */
							$query->where('(a.venue LIKE '.$search.' OR a.alias LIKE '.$search.')');
							break;
						case 2:
							/* search city */
							$query->where('a.city LIKE '.$search);
							break;
						case 3:
							/* search state */
							$query->where('a.state LIKE '.$search);
							break;
						case 4:
							/* search country */
							$query->where('a.country LIKE '.$search);
							break;
						case 5:
						default:
							/* search all */
							$query->where('(a.venue LIKE '.$search.' OR a.alias LIKE '.$search.' OR a.city LIKE '.$search.' OR a.state LIKE '.$search.' OR a.country LIKE '.$search.')');
					}
				}
			}
		}

		// Add the list ordering clause.
		$orderCol	= $this->state->get('list.ordering');
		$orderDirn	= $this->state->get('list.direction');

		$query->order($db->escape($orderCol.' '.$orderDirn));

		return $query;
	}
}
