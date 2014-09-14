<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.model');


/**
 * Contactelement-Model
 */
class JemModelContactelement extends JModelLegacy
{
	/**
	 * Category data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Category total
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
	 * Categorie id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();

		$limit		= $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 'int' );
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	
	/**
	 * Method to get data
	 */
	function getData()
	{
		$query 		= $this->buildQuery();
		$pagination = $this->getPagination();
	
		$rows 		= $this->_getList($query, $pagination->limitstart, $pagination->limit);
	
		return $rows;
	}
	
	
	/**
	 * Query
	 */
	
	function buildQuery() {
	
		$app 				= JFactory::getApplication();
		$jemsettings 		= JemHelper::config();
	
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.contactelement.filter_order','filter_order','con.ordering','cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.contactelement.filter_order_Dir','filter_order_Dir','','word' );
		
		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');
		
		$filter_type 		= $app->getUserStateFromRequest('com_jem.contactelement.filter_type','filter_type','','int');
		$search 			= $app->getUserStateFromRequest('com_jem.contactelement.filter_search','filter_search','','string');
		$search 			= $this->_db->escape( trim(JString::strtolower( $search ) ) );
		
		// start query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(array('con.*'));
		$query->from('#__contact_details as con');
		
		// where
		$where = array();
		$where[] = 'con.published = 1';
		
		
		// search
		if ($search) {
			switch ($filter_type) {
				case 1: /* name */
					$where[] = 'LOWER(con.name) LIKE \'%'.$search.'%\' ';
					break;
				case 2: /* address */
					$where[] = 'LOWER(con.address) LIKE \'%'.$search.'%\' ';
					break;
				case 3: /* city */
					$where[] = 'LOWER(con.suburb) LIKE \'%'.$search.'%\' ';
					break;
				case 4: /* state */
					$where[] = 'LOWER(con.state) LIKE \'%'.$search.'%\' ';
					break;
			}
		}
		
		$query->where($where);
		
		
		// order
		if ($filter_order != '') {
			$orderby = $filter_order . ' ' . $filter_order_Dir;
		} else {
			$orderby = 'con.name';
		}
		
		$query->order($orderby);
		
		return $query;
		
	}

	/**
	 * Method to get a pagination object for the contactelement
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		$app 				= JFactory::getApplication();
		$jemsettings 		= JemHelper::config();
		
		$limit 				= $this->getState('limit');
		$limitstart 		= $this->getState('limitstart');
		
		$query = $this->buildQuery();
		$total = $this->_getListCount($query);
		
		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);
		
		return $pagination;
	}

	
}
?>