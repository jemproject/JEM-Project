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
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Categoryelement-Model
 */
class JemModelCategoryelement extends BaseDatabaseModel
{
	/**
	 * Pagination object
	 *
	 * @var object
	 */
	protected $_pagination = null;

	/**
	 * Category id
	 *
	 * @var int
	 */
	protected $_id = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$jinput = Factory::getApplication()->input;
		$array = $jinput->get('cid', 0, 'array');

		if(is_array($this) && $this->setId((int)$array[0]));
	}

	/**
	 * Method to set the category identifier
	 *
	 * @access public
	 * @param  int Category identifier
	 */
	public function setId($id)
	{
		// Set id
		$this->_id = $id;
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		$app    = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$itemid = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$limit            = $app->getUserStateFromRequest('com_jem.limit', 'limit', $app->get('list_limit'), 'int');
		$limitstart       = $app->getUserStateFromRequest('com_jem.limitstart', 'limitstart', 0, 'int');
		$limitstart       = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
		$filter_order     = $app->getUserStateFromRequest('com_jem.categoryelement.filter_order', 'filter_order', 'c.lft', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.categoryelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$filter_state     = $app->getUserStateFromRequest('com_jem.categoryelement.'.$itemid.'.filter_state', 'filter_state', '', 'string');
		$search           = $app->getUserStateFromRequest('com_jem.categoryelement.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$search           = $db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		$filter_order     = JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;

		$state = array(1);

		if (is_numeric($filter_state)) {
			$where = ' WHERE c.published = '.(int) $filter_state;
		} else {
			$where = ' WHERE c.published IN (' . implode(',', $state) . ')';
			//$where .= ' AND c.alias NOT LIKE "root"';
		}

		$where2 = ' AND c.published IN (' . implode(',', $state) . ')';
		//$where2 .= ' AND c.alias NOT LIKE "root"';

		// select the records
		// note, since this is a tree we have to do the limits code-side
		if ($search) {
			$query = 'SELECT c.id FROM #__jem_categories AS c' . ' WHERE LOWER(c.catname) LIKE ' . $db->Quote('%' . $this->_db->escape($search, true) . '%', false) . $where2;
			$db->setQuery($query);
			$search_rows = $db->loadColumn();
		}

		$query = 'SELECT c.*, u.name AS editor, g.title AS groupname, gr.name AS catgroup'
				. ' FROM #__jem_categories AS c' . ' LEFT JOIN #__viewlevels AS g ON g.id = c.access'
				. ' LEFT JOIN #__users AS u ON u.id = c.checked_out'
				. ' LEFT JOIN #__jem_groups AS gr ON gr.id = c.groupid'
				. $where
				// . ' ORDER BY c.parent_id, c.ordering';
				. $orderby;

		

		// Check for a database error.
		// if ($db->getErrorNum()) {
		// 	Factory::getApplication()->enqueueMessage($db->getErrorMsg(), 'notice');
		// }
		try
		{
			$db->setQuery($query);
			$mitems = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
		}

		if (!$mitems) {
			$mitems = array();
			$children = array();
			$parentid = 0;
		} else {
			$children = array();
			// First pass - collect children
			foreach ($mitems as $v) {
				$pt = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}

			// list childs of "root" which has no parent and normally id 1
			$parentid = intval(@isset($children[0][0]->id) ? $children[0][0]->id : 1);
		}

		// get list of the items
		$list = JemCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		// eventually only pick out the searched items.
		if ($search) {
			$list1 = array();

			foreach ($search_rows as $sid) {
				foreach ($list as $item) {
					if ($item->id == $sid) {
						$list1[] = $item;
					}
				}
			}
			// replace full list with found items
			$list = $list1;
		}

		$total = count($list);

		$this->_pagination = new Pagination($total, $limitstart, $limit);

		// slice out elements based on limits
		$list = array_slice($list, $this->_pagination->limitstart, $this->_pagination->limit);

		return $list;
	}

	public function getPagination()
	{
		if ($this->_pagination == null) {
			$this->getItems();
		}
		return $this->_pagination;
	}
}
?>
