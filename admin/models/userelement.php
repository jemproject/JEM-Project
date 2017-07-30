<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');


/**
 * Userelement-Model
 */
class JemModelUserelement extends JModelLegacy
{
	/**
	 * data array
	 *
	 * @var array
	 */
	protected $_data = null;

	/**
	 * total
	 *
	 * @var integer
	 */
	protected $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	protected $_pagination = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();

		$limit      = $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get data
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		$query      = $this->buildQuery();
		$pagination = $this->getPagination();

		$rows       = $this->_getList($query, $pagination->limitstart, $pagination->limit);

		return $rows;
	}

	/**
	 * Query
	 */
	protected function buildQuery()
	{
		$app              = JFactory::getApplication();
		$jemsettings      = JemHelper::config();

		$filter_order     = $app->getUserStateFromRequest( 'com_jem.userelement.filter_order', 'filter_order', 'u.name', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( 'com_jem.userelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		$filter_order     = JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		$search           = $app->getUserStateFromRequest('com_jem.userelement.filter_search', 'filter_search', '', 'string' );
		$search           = $this->_db->escape( trim(JString::strtolower( $search ) ) );

		// start query
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(array('u.id', 'u.name', 'u.username', 'u.email'));
		$query->from('#__users as u');

		// where
		$where = array();
		$where[] = 'u.block = 0';

		/*
		 * Search name
		 */
		if ($search) {
			$where[] = ' LOWER(u.name) LIKE \'%'.$search.'%\' ';
		}

		$query->where($where);

		// ordering
		$orderby = '';
		$orderby = $filter_order.' '.$filter_order_Dir;

		$query->order($orderby);

		return $query;
	}

	/**
	 * Method to get a pagination object
	 *
	 * @access public
	 * @return integer
	 */
	public function getPagination()
	{
		$app         = JFactory::getApplication();
		$jemsettings = JemHelper::config();

		$limit       = $app->getUserStateFromRequest('com_jem.userelement.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = $app->input->getInt('limitstart', 0);

		$query = $this->buildQuery();
		$total = $this->_getListCount($query);

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		return $pagination;
	}
}
?>