<?php
/**
 * @version    4.2.2
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

jimport('joomla.application.component.model');


/**
 * Contactelement-Model
 */
class JemModelContactelement extends BaseDatabaseModel
{
	///**
	// * Category data array
	// *
	// * @var array
	// */
	//protected $_data = null;

	///**
	// * Category total
	// *
	// * @var integer
	// */
	//protected $_total = null;

	///**
	// * Pagination object
	// *
	// * @var object
	// */
	//protected $_pagination = null;

	///**
	// * Categorie id
	// *
	// * @var int
	// */
	//protected $_id = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app =  Factory::getApplication();

		$limit      = $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->get('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get data
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
		$app              = Factory::getApplication();

		$filter_order     = $app->getUserStateFromRequest( 'com_jem.contactelement.filter_order','filter_order','con.ordering','cmd');
		$filter_order_Dir = $app->getUserStateFromRequest( 'com_jem.contactelement.filter_order_Dir','filter_order_Dir','','word' );

		$filter_order     = InputFilter::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = InputFilter::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type      = $app->getUserStateFromRequest('com_jem.contactelement.filter_type','filter_type',0,'int');
		$search           = $app->getUserStateFromRequest('com_jem.contactelement.filter_search','filter_search','','string');
		$search           = $this->_db->escape( trim(\Joomla\String\StringHelper::strtolower( $search ) ) );

		// start query
		$db = Factory::getContainer()->get('DatabaseDriver');
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
	public function getPagination()
	{
		$limit      = $this->getState('limit');
		$limitstart = $this->getState('limitstart');

		$query = $this->buildQuery();
		$total = $this->_getListCount($query);

		// Create the pagination object
		$pagination = new Pagination($total, $limitstart, $limit);

		return $pagination;
	}
}
?>
