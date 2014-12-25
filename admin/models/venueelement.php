<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Venueelement-Model
 */
class JemModelVenueelement extends JModelLegacy
{
	/**
	 * data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * total
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
	 * id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app         = JFactory::getApplication();
		$jemsettings = JemHelper::config();
		$itemid      = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$limit       = $app->getUserStateFromRequest('com_jem.venueelement.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = $app->input->getInt('limitstart', 0);
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Get venue-data
	 */
	function getData()
	{
		$query 		= $this->buildQuery();
		$pagination = $this->getPagination();

		$rows 		= $this->_getList($query, $pagination->limitstart, $pagination->limit);

		return $rows;
	}

	/**
	 * venue-query
	 */
	function buildQuery() {

		$app 				= JFactory::getApplication();
		$jemsettings 		= JemHelper::config();
		$itemid 			= $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$filter_order		= $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_order', 'filter_order', 'l.ordering', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type 		= $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_type', 'filter_type', '', 'int' );
		$search 			= $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_search', 'filter_search', '', 'string' );
		$search 			= $this->_db->escape(trim(JString::strtolower($search)));

		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(array('l.id','l.state','l.city','l.country','l.published','l.venue','l.ordering'));
		$query->from('#__jem_venues as l');

		// where
		$where = array();
		$where[] = 'l.published = 1';

		/* something to search for? (we like to search for "0" too) */
		if ($search || ($search === "0")) {
			switch ($filter_type) {
				case 1: /* Search venues */
					$where[] = 'LOWER(l.venue) LIKE "%' . $search . '%"';
					break;
				case 2: // Search city
					$where[] = 'LOWER(l.city) LIKE "%' . $search . '%"';
					break;
				case 3: // Search state
					$where[] = 'LOWER(l.state) LIKE "%' . $search . '%"';
			}
		}

		$query->where($where);

		$orderby 	= array($filter_order.' '.$filter_order_Dir,'l.ordering ASC');
		$query->order($orderby);

		return $query;
	}

	/**
	 * Method to get a pagination object
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		$jemsettings	= JemHelper::config();
		$app 			= JFactory::getApplication();

		$limit 			= $this->getState('limit');
		$limitstart 	= $this->getState('limitstart');

		$query 			= $this->buildQuery();
		$total 			= $this->_getListCount($query);

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination 	= new JPagination($total, $limitstart, $limit);

		return $pagination;
	}
}
?>