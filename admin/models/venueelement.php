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
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Venueelement-Model
 */
class JemModelVenueelement extends BaseDatabaseModel
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
	 * id
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

		$app         = Factory::getApplication();
		$jemsettings = JemHelper::config();
	//	$itemid      = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$limit       = $app->getUserStateFromRequest('com_jem.venueelement.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = $app->input->getInt('limitstart', 0);
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Get venue-data
	 */
	public function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query 		= $this->buildQuery();
			$pagination = $this->getPagination();

			$this->_data = $this->_getList($query, $pagination->limitstart, $pagination->limit);
		}

		return $this->_data;
	}

	/**
	 * venue-query
	 */
	protected function buildQuery()
	{
		$app              = Factory::getApplication();
		$jemsettings      = JemHelper::config();
		$itemid           = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		$filter_order     = $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_order', 'filter_order', 'l.ordering', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', '', 'word' );

		$filter_order     = JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type      = $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_type', 'filter_type', 0, 'int' );
		$search           = $app->getUserStateFromRequest('com_jem.venueelement.'.$itemid.'.filter_search', 'filter_search', '', 'string' );
		$search           = $this->_db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		// Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select(array('l.id', 'l.state', 'l.city', 'l.country', 'l.published', 'l.venue', 'l.ordering'));
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

		$orderby = array($filter_order.' '.$filter_order_Dir, 'l.ordering ASC');
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
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			$limit      = $this->getState('limit');
			$limitstart = $this->getState('limitstart');

			$query = $this->buildQuery();
			$total = $this->_getListCount($query);

			// Create the pagination object
			$this->_pagination = new Pagination($total, $limitstart, $limit);
		}

		return $this->_pagination;
	}
}
?>
