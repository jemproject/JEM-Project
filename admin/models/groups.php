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
 * JEM Component Groups Model
 *
 **/
class JemModelGroups extends ListModel
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
				'name', 'a.name',
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

	//	$filterfield = $this->getUserStateFromRequest($this->context.'.filter_type', 'filter_type', 0, 'int');
	//	$this->setState('filter_type', $filterfield);

		// Load the parameters.
		$params = ComponentHelper::getParams('com_jem');
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
	 * @param  string $id A prefix for the store id.
	 * @return string A store id.
	 *
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter_search');
		$id .= ':' . $this->getState('filter_published');
	//	$id .= ':' . $this->getState('filter_type');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return JDatabaseQuery
	 *
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select($this->getState('list.select', 'a.*'));
		$query->from($db->quoteName('#__jem_groups').' AS a');

		// Join over the users for the checked out user.
		$query->select('uc.name AS editor');
		$query->join('LEFT', '#__users AS uc ON uc.id = a.checked_out');

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
		$orderCol  = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');
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

		return $items;
	}

	/**
	 * Method to remove a group
	 *
	 * @access public
	 * @return boolean True on success
	 *
	 */
	public function delete($cid = array())
	{
		if (is_array($cid) && count($cid))
		{
			\Joomla\Utilities\ArrayHelper::toInteger($cid);
			$cids = implode(',', $cid);

			$query = 'DELETE FROM #__jem_groups'
			       . ' WHERE id IN ('. $cids .')'
			       ;

			$this->_db->setQuery($query);

			if ($this->_db->execute() === false) {
				$this->setError($this->_db->getError());
				return false;
			}

			$query = 'DELETE FROM #__jem_groupmembers'
			       . ' WHERE group_id IN ('. $cids .')'
			       ;

			$this->_db->setQuery($query);

			if ($this->_db->execute() === false) {
				$this->setError($this->_db->getError());
				return false;
			}
		}

		return true;
	}
}
