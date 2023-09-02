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
use Joomla\CMS\Filter\InputFilter;
Use Joomla\Utilities\ArrayHelper;

require_once __DIR__ . '/eventslist.php';

/**
 * Model: Category
 *
 * \todo Remove all the collected stuff copied from somewhere but unused/useless.
 */
class JemModelCategory extends JemModelEventslist
{
	protected $_id			= null;
	//protected $_data		= null;
	//protected $_childs	= null;
	//protected $_category	= null;
	//protected $_pagination= null;
	protected $_item		= null;
	//protected $_articles	= null;
	//protected $_siblings	= null;
	protected $_children	= null;
	protected $_parent	= null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$app    = Factory::getApplication();
		// Get the parameters of the active menu item
		$params = $app->getParams();

		$id = $app->input->getInt('id', 0);
		if (empty($id)) {
			$id = $params->get('id', 1);
		}

		$this->setId((int)$id);

		parent::__construct();
	}

	/**
	 * Set Date
	 */
	public function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to set the category id
	 */
	public function setId($id)
	{
		// Set new category ID and wipe data
		$this->_id   = $id;
		$this->_item = null;
		//$this->_data = null;
	}

	/**
	 * set limit
	 * @param int value
	 */
	public function setLimit($value)
	{
		$this->setState('list.limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	public function setLimitStart($value)
	{
		$this->setState('list.start', (int) $value);
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initiliase variables.
		$app         = Factory::getApplication('site');
		$jemsettings = JemHelper::config();
		$task        = $app->input->getCmd('task','');
		$format      = $app->input->getCmd('format',false);
		$pk          = $app->input->getInt('id', 0);
		$itemid      = $pk . ':' . $app->input->getInt('Itemid', 0);

		$this->setState('category.id', $pk);
		$this->setState('filter.req_catid', $pk);

		// Load the parameters. Merge Global and Menu Item params into new object
		$params = $app->getParams();
		$menuParams = new JRegistry;

		if ($menu = $app->getMenu()->getActive()) {
			// $menu_params = $menu->getParams();
			// $menuParams->loadString($menu->params);
			$menuParams->loadString($menu->getParams());
		}

		$mergedParams = clone $menuParams;
		$mergedParams->merge($params);

		$this->setState('params', $mergedParams);

		# limit/start

		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.category.'.$itemid.'.limitstart', 0);
		}

		if (empty($format) || ($format == 'html')) {
			$limit = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
			$this->setState('list.limit', $limit);

			$limitstart = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
			// correct start value if required
			$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
			$this->setState('list.start', $limitstart);
		}

		# Search - variables
		$search = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search);

		$filtertype = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
		$this->setState('filter.filter_type', $filtertype);

		# show open date events
		# (there is no menu item option yet so show all events)
		$this->setState('filter.opendates', 1);

		# publish state
		$this->_populatePublishState($task);

		###########
		## ORDER ##
		###########

		$filter_order = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.category.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_order     = InputFilter::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = InputFilter::getInstance()->clean($filter_order_Dir, 'word');

		$default_order_Dir = ($task == 'archive') ? 'DESC' : 'ASC';
		if ($filter_order == 'a.dates') {
			$orderby = array('a.dates ' . $filter_order_Dir, 'a.times ' . $filter_order_Dir, 'a.created ' . $filter_order_Dir);
		} else {
			$orderby = array($filter_order . ' ' . $filter_order_Dir,
			                 'a.dates ' . $default_order_Dir, 'a.times ' . $default_order_Dir, 'a.created ' . $default_order_Dir);
		}

		$this->setState('filter.orderby',$orderby);
	}

	/**
	 * Get the events in the category
	 */
	public function getItems()
	{
		//$params = clone $this->getState('params');
		$items = parent::getItems();

		if ($items) {
			return $items;
		}

		return array();
	}

	/**
	 * Method to get category data for the current category
	 */
	public function getCategory()
	{
		if (!is_object($this->_item)) {
			$options = array();

			if (isset($this->state->params)) {
				$params = $this->state->params;
				$options['countItems'] = ($params->get('show_cat_num_articles', 1) || !$params->get('show_empty_categories_cat', 0)) ? 1 : 0;
			}
			else {
				$options['countItems'] = 0;
			}

			$where_pub = $this->_getPublishWhere('i');
			if (!empty($where_pub)) {
				$options['published_where'] = '(' . implode(' OR ', $where_pub) . ')';
			} else {
				// something wrong - fallback to published events
				$options['published_where'] = 'i.published = 1';
			}

			$catId = $this->getState('category.id', 'root');
			$categories = new JemCategories($catId, $options);
			$this->_item = $categories->get($catId);

			// Compute selected asset permissions.
			if (is_object($this->_item)) { // a JemCategoryNode object
				$user = JemFactory::getUser();

				// Check general or category specific create permission.
				$this->_item->getParams()->set('access-create', $user->can('add', 'event', false, false, $this->_item->id));

				$this->_children = $this->_item->getChildren();

				$this->_parent = $this->_item->getParent();
				if (empty($this->_parent)) {
					$this->_parent = false;
				}

				$this->_rightsibling = $this->_item->getSibling();
				$this->_leftsibling = $this->_item->getSibling(false);
			}
			else {
				$this->_children = false;
				$this->_parent = false;
			}
		}

		return $this->_item;
	}

	/**
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		//$params  = $this->state->params;
		//$jinput  = Factory::getApplication()->input;
		//$task    = $jinput->getCmd('task','','cmd');

		// Create a new query object.
		$query = parent::getListQuery();

		// here we can extend the query of the Eventslist model
		return $query;
	}

	/**
	 * Get the parent categorie.
	 */
	public function getParent()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_parent;
	}

	/**
	 * Get the left sibling (adjacent) categories.
	 */
	public function &getLeftSibling()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_leftsibling;
	}

	/**
	 * Get the right sibling (adjacent) categories.
	 */
	public function &getRightSibling()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		return $this->_rightsibling;
	}

	/**
	 * Get the child categories.
	 */
	public function &getChildren()
	{
		if (!is_object($this->_item)) {
			$this->getCategory();
		}

		// Order subcategories
		if (sizeof($this->_children)) {
			$params = $this->getState()->get('params');
			if ($params->get('orderby_pri') == 'alpha' || $params->get('orderby_pri') == 'ralpha') {
				ArrayHelper::sortObjects($this->_children, 'title', ($params->get('orderby_pri') == 'alpha') ? 1 : -1);
			}
		}

		return $this->_children;
	}
}
?>
