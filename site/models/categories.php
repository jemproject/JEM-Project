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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Language\Text;

/**
 * JEM Component Categories Model
 *
 * @package JEM
 *
 */
class JemModelCategories extends BaseDatabaseModel
{
	/**
	 * Top category id
	 *
	 * @var int
	 */
	protected $_id = 0;

	/**
	 * Event data array
	 *
	 * @var array
	 */
	protected $_data = null;

	/**
	 * Categories total
	 *
	 * @var integer
	 */
	protected $_total = null;

	/**
	 * Categories data array
	 *
	 * @var array
	 */
	protected $_categories = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	protected $_pagination = null;

	/**
	 * Show empty categories in list
	 *
	 * @var bool
	 */
	protected $_showemptycats = false;

	/**
	 * Show subcategories
	 *
	 * @var bool
	 */
	protected $_showsubcats = false;

	/**
	 * Show empty subcategories
	 *
	 * @var bool
	 */
	protected $_showemptysubcats = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app = Factory::getApplication();

		// Get the parameters of the active menu item
		$params = $app->getParams('com_jem');

		$id = $app->input->getInt('id', 0);
		if (empty($id)) {
			$id = $params->get('id', 1);
		}

		$this->_id = $id;

		$this->_showemptycats    = (bool)$params->get('showemptycats', 1);
		$this->_showsubcats      = (bool)$params->get('usecat', 1);
		$this->_showemptysubcats = (bool)$params->get('showemptychilds', 1);

		//get the number of events from database
		$limit      = $app->input->getInt('limit', $params->get('cat_num'));
		$limitstart = $app->input->getInt('limitstart', 0);
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Categories
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		$app = Factory::getApplication();
		$params = $app->getParams();

		// Lets load the content if it doesn't already exist
		if (empty($this->_categories))
		{
			// include category itself but not if it's the root category
			$parentCategory = ($this->_id > 1) ? $this->_getList($this->_buildQueryParentCategory(true)) : array();
			$query = $this->_buildQuerySubCategories($this->_showemptycats);
			$pagination = $this->getPagination();
			$this->_categories = $this->_getList($query, $pagination->limitstart, $pagination->limit);

			// Include parent category itself
			$this->_categories = array_merge($parentCategory, $this->_categories);

			foreach($this->_categories as $category) {
				if ($this->_showsubcats) {
					//child categories
					// ensure parent shows at least all categories also shown in list
					$showempty = $this->_showemptysubcats | ($category->id == $this->_id ? $this->_showemptycats : false);
					$query = $this->_buildQuerySubCategories($showempty, $category->id);
					$this->_db->setQuery($query);
					$category->subcats = $this->_db->loadObjectList();
				} else {
					$category->subcats = array();
				}

				//Generate description
				if (empty ($category->description)) {
					$category->description = Text::_('COM_JEM_NO_DESCRIPTION');
				} else {
					//execute plugins
					$category->text = $category->description;
					$category->title = $category->catname;
					JPluginHelper::importPlugin('content');
					$app->triggerEvent('onContentPrepare', array('com_jem.categories', &$category, &$params, 0));
					$category->description = $category->text;
				}

				//create target link
				// TODO: Move to view?
				$task = $app->input->getCmd('task', '');
				if ($task == 'archive') {
					$category->linktext   = Text::_('COM_JEM_SHOW_ARCHIVE');
					$category->linktarget = JRoute::_(JemHelperRoute::getCategoryRoute($category->slug.'&task=archive'));
				} else {
					$category->linktext   = Text::_('COM_JEM_SHOW_EVENTS');
					$category->linktarget = JRoute::_(JemHelperRoute::getCategoryRoute($category->slug));
				}
			}
		}

		return $this->_categories;
	}

	/**
	 * Total nr of Categories
	 *
	 * @access public
	 * @return integer
	 */
	public function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQueryTotal();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get the Categories events
	 *
	 * @access public
	 * @return array
	 */
	public function getEventdata($id)
	{
		$app = Factory::getApplication();
		$params = $app->getParams('com_jem');

		if (empty($this->_data[$id])) {
			// Lets load the content
			$query = $this->_buildDataQuery($id);
			$this->_data[$id] = $this->_getList($query, 0, $params->get('detcat_nr'));

			foreach ($this->_data[$id] as $i => &$item) {
				$item->categories = $this->getCategories($item->id);

				//remove events without categories (users have no access to them)
				if (empty($item->categories)) {
					unset ($this->_data[$id][$i]);
				}
			}
		}

		return $this->_data[$id];
	}

	/**
	 * Method get the event query
	 *
	 * @access private
	 * @return array
	 */
	protected function _buildDataQuery($id)
	{
		$user   = JemFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
		$task   = Factory::getApplication()->input->getCmd('task', '');

		$id = (int)$id;

		// First thing we need to do is to select only the requested events
		if ($task == 'archive') {
			$where = ' WHERE a.published = 2 AND rel.catid = '.$id;
		} else {
			$where = ' WHERE a.published = 1 AND rel.catid = '.$id;
		}

		// Second is to only select events assigned to category the user has access to
		$where .= ' AND c.access IN (' . implode(',', $levels) . ')';
		$where .= ' AND a.access IN (' . implode(',', $levels) . ')';

		$query = 'SELECT DISTINCT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.created, a.published,'
		       . ' a.recurrence_type, a.recurrence_first_id,'
		       . ' a.access, a.checked_out, a.checked_out_time, a.contactid, a.created, a.created_by, a.created_by_alias, a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, a.datimage, a.featured,'
		       . ' a.fulltext, a.hits, a.introtext, a.language, a.maxplaces, a.metadata, a.meta_keywords, a.meta_description, a.modified, a.modified_by, a.registra, a.unregistra, a.waitinglist,'
		       . ' a.recurrence_byday, a.recurrence_counter, a.recurrence_limit, a.recurrence_limit_date, a.recurrence_number, a.version,'
		       . ' l.venue, l.street, l.postalCode, l.city, l.state, l.url, l.country, l.published AS l_published,'
		       . ' l.alias AS l_alias, l.checked_out AS l_checked_out, l.checked_out_time AS l_checked_out_time, l.created AS l_created, l.created_by AS l_createdby,'
		       . ' l.custom1 AS l_custom1, l.custom2 AS l_custom2, l.custom3 AS l_custom3, l.custom4 AS l_custom4, l.custom5 AS l_custom5, l.custom6 AS l_custom6, l.custom7 AS l_custom7, l.custom8 AS l_custom8, l.custom9 AS l_custom9, l.custom10 AS l_custom10,'
		       . ' l.id AS l_id, l.latitude, l.locdescription, l.locimage, l.longitude, l.map, l.meta_description AS l_meta_description, l.meta_keywords AS l_meta_keywords, l.modified AS l_modified, l.modified_by AS l_modified_by,'
		       . ' l.publish_up AS l_publish_up, l.publish_down AS l_publish_down, l.version AS l_version,'
		       . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
		       . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
		       . ' FROM #__jem_events AS a'
		       . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
		       . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
		       . ' LEFT JOIN #__jem_categories AS c ON c.id = '.$id
		       . $where
		       . ' ORDER BY a.dates, a.times, a.created DESC'
		       ;

		return $query;
	}

	public function getCategories($id)
	{
		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT DISTINCT c.id, c.catname, c.access, c.checked_out AS cchecked_out,'
		       . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
		       . ' FROM #__jem_categories AS c'
		       . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
		       . ' WHERE rel.itemid = '.(int)$id
		       . ' AND c.published = 1'
		       . ' AND c.access IN (' . implode(',', $levels) . ')'
		       ;

		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}

	/**
	 * Method to get the subcategories query
	 * @param  bool   $emptycat include empty categories
	 * @param  string $parent_id Parent ID of the subcategories
	 * @return string The query string
	 */
	protected function _buildQuerySubCategories($emptycat, $parent_id = null)
	{
		return $this->_buildQuery($emptycat, $parent_id);
	}

	/**
	 * Method to get the parent category query
	 * @param  bool   $emptycat include empty categories
	 * @param  string $parent_id ID of the parent category
	 * @return string The query string
	 */
	protected function _buildQueryParentCategory($emptycat, $parent_id = null)
	{
		return $this->_buildQuery($emptycat, $parent_id, true);
	}

	/**
	 * Method to get the categories query
	 * @param  bool   $emptycat include empty categories
	 * @param  string $parent_id
	 * @param  bool   $parentCategory
	 * @return string The query string
	 */
	protected function _buildQuery($emptycat, $parent_id = null, $parentCategory = false)
	{
		if (is_null($parent_id)) {
			$parent_id = $this->_id;
		}

		$app    = Factory::getApplication();
		$jinput = $app->input;
		$user   = JemFactory::getUser();
		$userId = $user->get('id');
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();
		$jemsettings = JemHelper::config();

		$ordering = 'c.lft ASC';

		// build where clause
		$where_sub = ' WHERE cc.published = 1';
		if($parentCategory) {
			$where_sub .= ' AND cc.id = '.(int) $parent_id;
		} else {
			$where_sub .= ' AND cc.parent_id = '.(int) $parent_id;
		}
		$where_sub .= ' AND cc.access IN (' . implode(',', $levels) . ')';
		$where_sub .= ' AND i.access IN (' . implode(',', $levels) . ')';

		// check archive task and ensure that only categories get selected
		// if they contain a published/archived event
		$task   = $jinput->getCmd('task', '');
		$format = $jinput->getCmd('format', '');

		# inspired by JemModelEventslist

		if ($task == 'archive') {
			$where_sub .= ' AND i.published = 2';
		} elseif (($format == 'raw') || ($format == 'feed')) {
			$where_sub .= ' AND i.published = 1';
		} else {
			$show_unpublished = $user->can(array('edit', 'publish'), 'event', false, false, 1);
			if ($show_unpublished) {
				// global editor or publisher permission
				$where_sub .= ' AND i.published IN (0, 1)';
			} else {
				// no global permission but maybe on event level
				$where_sub_or = array();
				$where_sub_or[] = '(i.published = 1)';

				$jemgroups = $user->getJemGroups(array('editevent', 'publishevent'));
				if (($userId !== 0) && ($jemsettings->eventedit == -1)) {
					$jemgroups[0] = true; // we need key 0 to get unpublished events not attached to any jem group
				}
				// user permitted on that jem groups
				if (is_array($jemgroups) && count($jemgroups)) {
					$on_groups = array_keys($jemgroups);
					// to allow only events with categories attached to allowed jemgroups use this line:
					//$where_sub_or[] = '(i.published = 0 AND c.groupid IN (' . implode(',', $on_groups) . '))';
					// to allow also events with categories not attached to disallowed jemgroups use this crazy block:
					$where_sub_or[] = '(i.published = 0 AND '
					                . ' i.id NOT IN (SELECT rel3.itemid FROM #__jem_categories as c3 '
					                . '              INNER JOIN #__jem_cats_event_relations as rel3 '
					                . '              WHERE c3.id = rel3.catid AND c3.groupid NOT IN (0,' . implode(',', $on_groups) . ')'
					                . '              GROUP BY rel3.itemid)'
					                . ')';
					// hint: above it's a not not ;-)
					//       meaning: Show unpublished events not connected to a category which is not one of the allowed categories.
				}
				// user permitted on own events
				if (($userId !== 0) && ($user->authorise('core.edit.own', 'com_jem') || $jemsettings->eventowner)) {
					$where_sub_or[] = '(i.published = 0 AND i.created_by = ' . $userId . ')';
				}
				$where_sub .= ' AND (' . implode(' OR ', $where_sub_or) . ')';
			}
		}
		$where_sub .= ' AND c.id = cc.id';

		// show/hide empty categories
		$empty = $emptycat ? '' : ' HAVING assignedevents > 0';

		// Parent category itself or its sub categories
		$parentCategoryQuery = $parentCategory ? 'c.id='.(int)$parent_id : 'c.parent_id='.(int)$parent_id;

		$query = 'SELECT c.*,'
		       . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS slug,'
		       . ' ('
		       . '  SELECT COUNT(DISTINCT i.id)'
		       . '  FROM #__jem_events AS i'
		       . '  LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = i.id'
		       . '  LEFT JOIN #__jem_categories AS cc ON cc.id = rel.catid'
		       . $where_sub
		       . '  GROUP BY cc.id'
		       . ' ) AS assignedevents'
		       . ' FROM #__jem_categories AS c'
		       . ' WHERE c.published = 1'
		       . ' AND '.$parentCategoryQuery
		       . ' AND c.access IN (' . implode(',', $levels) . ')'
		       . ' GROUP BY c.id '.$empty
		       . ' ORDER BY '.$ordering
		       ;

		return $query;
	}

	/**
	 * Method to build the Categories query without subselect
	 * That's enough to get the total value.
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQueryTotal()
	{
		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$query = 'SELECT DISTINCT c.id'
		       . ' FROM #__jem_categories AS c';

		if (!$this->_showemptycats) {
			$query .= ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id '
			        . ' INNER JOIN #__jem_events AS e ON e.id = rel.itemid ';
		}

		$query .= ' WHERE c.published = 1'
		        . ' AND c.parent_id = ' . (int) $this->_id
		        . ' AND c.access IN (' . implode(',', $levels) . ')'
		        ;

		if (!$this->_showemptycats) {
			$query .= ' AND e.access IN (' . implode(',', $levels) . ')';

			$task = Factory::getApplication()->input->getCmd('task', '');
			if($task == 'archive') {
				$query .= ' AND e.published = 2';
			} else {
				$query .= ' AND e.published = 1';
			}
		}

		return $query;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	public function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			$this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}
		return $this->_pagination;
	}
}
?>
