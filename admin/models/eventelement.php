<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Eventelement Model
 */
class JemModelEventelement extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();

		$limit		= $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));

			if (is_array($this->_data)) {
				foreach ($this->_data as $item) {
					$item->categories = $this->getCategories($item->id);

					//remove events without categories (users have no access to them)
					if (empty($item->categories)) {
						unset($this->_data[$i]);
					}
				}
			}
		}

		return $this->_data;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();

		$query = 'SELECT a.*, loc.venue, loc.city,c.catname'
					. ' FROM #__jem_events AS a'
					. ' LEFT JOIN #__jem_venues AS loc ON loc.id = a.locid'
					. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
					. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
					. $where
					. ' GROUP BY a.id'
					. $orderby
					;

		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildContentOrderBy()
	{
		$app =  JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_order', 'filter_order', 'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', a.dates';

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildContentWhere()
	{
		$app    = JFactory::getApplication();
		$user   = JemFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
		$itemid = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$published     = $app->getUserStateFromRequest('com_jem.eventelement.'.$itemid.'.filter_state',  'filter_state',  '', 'string');
		$filter_type   = $app->getUserStateFromRequest('com_jem.eventelement.'.$itemid.'.filter_type',   'filter_type',   '', 'int');
		$filter_search = $app->getUserStateFromRequest('com_jem.eventelement.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$filter_search = $this->_db->escape(trim(JString::strtolower($filter_search)));

		$where = array();

		// Filter by published state
		if (is_numeric($published)) {
			$where[] = 'a.published = '.(int) $published;
		} elseif ($published === '') {
			$where[] = '(a.published IN (1))';
		}

		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		if (!empty($filter_search)) {
			switch ($filter_type) {
			case 1:
				$where[] = ' LOWER(a.title) LIKE \'%'.$filter_search.'%\' ';
				break;
			case 2:
				$where[] = ' LOWER(loc.venue) LIKE \'%'.$filter_search.'%\' ';
				break;
			case 3:
				$where[] = ' LOWER(loc.city) LIKE \'%'.$filter_search.'%\' ';
				break;
			case 4:
				$where[] = ' LOWER(c.catname) LIKE \'%'.$filter_search.'%\' ';
				break;
			}
		}

		$where = (count($where) ? ' WHERE (' . implode(') AND (', $where) . ')' : '');

		return $where;
	}


	function getCategories($id)
	{
		$query = 'SELECT DISTINCT c.id, c.catname, c.checked_out AS cchecked_out'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				;

		$this->_db->setQuery( $query );

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
?>