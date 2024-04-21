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
use Joomla\CMS\Filter\InputFilter;

/**
 * JEM Component JEM Model
 *
 * @package JEM
 *
 */
class JemModelMyvenues extends BaseDatabaseModel
{
	/**
	 * Venues data array
	 *
	 * @var array
	 */
	protected $_venues = null;

	/**
	 * Venues total count
	 *
	 * @var integer
	 */
	protected $_total_venues = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app = Factory::getApplication();
		$jemsettings = JemHelper::config();

		//get the number of events

		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.myvenues.limitstart', 0);
		}

		$limit      = $app->getUserStateFromRequest('com_jem.myvenues.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.myvenues.limitstart', 'limitstart', 0, 'int');
		// correct start value if required
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get the Events user is attending
	 *
	 * @access public
	 * @return array
	 */
	public function getVenues()
	{
		$pop = Factory::getApplication()->input->getBool('pop', false);
		$user = JemFactory::getUser();
		$userId = $user->get('id');

		if (empty($userId)) {
			$this->_venues = array();
			return array();
		}

		// Lets load the content if it doesn't already exist
		if ( empty($this->_venues)) {
			$query = $this->_buildQueryVenues();
			$pagination = $this->getVenuesPagination();

			if ($pop) {
				$this->_venues = $this->_getList($query);
			} else {
				$pagination = $this->getVenuesPagination();
				$this->_venues = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}
		}

		if ($this->_venues) {
			foreach ($this->_venues as $item) {
				if (empty($item->params)) {
					// Set venue params.
					$item->params = clone JemHelper::globalattribs();
				}

				# edit state access permissions.
				$item->params->set('access-change', $user->can('publish', 'venue', $item->id, $item->created_by));
			}
		}

		return $this->_venues;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	public function getTotalVenues()
	{
		// Lets load the total nr if it doesn't already exist
		if ( empty($this->_total_venues)) {
			$query = $this->_buildQueryVenues();
			$this->_total_venues = $this->_getListCount($query);
		}

		return $this->_total_venues;
	}

	/**
	 * Method to get a pagination object for the attending events
	 *
	 * @access public
	 * @return integer
	 */
	public function getVenuesPagination()
	{
		// Lets load the content if it doesn't already exist
		if ( empty($this->_pagination_venues))
		{
			$this->_pagination_venues = new Pagination($this->getTotalVenues(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination_venues;
	}

	/**
	 * Method to (un)publish one or more venue(s)
	 *
	 * @access public
	 * @return boolean True on success
	 */
	public function publish($cid = array(), $publish = 1)
	{
		$result = false;
		$user   = JemFactory::getUser();
		$userid = (int) $user->get('id');

		if (is_numeric($cid)) {
			$cid = array($cid);
		}
		// simple checks, good enough here
		if (is_array($cid) && count($cid) && ($publish >= -2) && ($publish <= 2)) {
			\Joomla\Utilities\ArrayHelper::toInteger($cid);
			$cids = implode(',', $cid);

			$query = 'UPDATE #__jem_venues'
			       . ' SET published = ' . (int)$publish
			       . ' WHERE id IN (' . $cids . ')'
			       . ' AND (checked_out = 0 OR checked_out IS null OR (checked_out = ' . $userid . '))'
			      ;

			$this->_db->setQuery($query);
			$result = true;

			if ($this->_db->execute() === false) {
				$this->setError($this->_db->getErrorMsg());
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildQueryVenues()
	{
		# Get the WHERE and ORDER BY clauses for the query
		$where   = $this->_buildVenuesWhere();
		$orderby = $this->_buildOrderByVenues();

		# Get Venues from Database
		$query = 'SELECT l.id, l.venue, l.street, l.postalCode, l.city, l.state, l.country, l.url, l.created, l.created_by, l.published,'
		       . ' l.custom1, l.custom2, l.custom3, l.custom4, l.custom5, l.custom6, l.custom7, l.custom8, l.custom9, l.custom10,'
		       . ' l.locdescription, l.locimage, l.latitude, l.longitude, l.map, l.meta_keywords, l.meta_description, l.checked_out, l.checked_out_time,'
		       . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', l.id, l.alias) ELSE l.id END as venueslug'
		       . ' FROM #__jem_venues AS l '
		       . $where
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
	protected function _buildOrderByVenues()
	{
		$app = Factory::getApplication();

		$filter_order     = $app->getUserStateFromRequest('com_jem.myvenues.filter_order', 'filter_order', 'l.venue', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.myvenues.filter_order_Dir', 'filter_order_Dir', '', 'word');

		$filter_order     = InputFilter::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = InputFilter::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order != '') {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir . ', l.venue ASC';
		} else {
			$orderby = ' ORDER BY l.venue ASC';
		}

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	protected function _buildVenuesWhere()
	{
		$app      = Factory::getApplication();
		$user     = JemFactory::getUser();
		$settings = JemHelper::globalattribs();

		$filter   = $app->getUserStateFromRequest('com_jem.myvenues.filter', 'filter', 0, 'int');
		$search   = $app->getUserStateFromRequest('com_jem.myvenues.filter_search', 'filter_search', '', 'string');
		$search   = $this->_db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		$where = array();

	//	$where[] = ' l.published = 1';

		// then if the user is creator of the event
		$where [] = ' l.created_by = '.$this->_db->Quote($user->id);

		if ($settings->get('global_show_filter') && $search) {
			switch($filter) {
				case 1:
// 					$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
					break;
				case 2:
					$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
					break;
				case 3:
					$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
					break;
				case 4:
// 					$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
					break;
				case 5:
				default:
					$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
			}
		}

		$where2 = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');
		return $where2;
	}
}
?>
