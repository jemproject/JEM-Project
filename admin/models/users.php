<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\String\StringHelper;

/**
 * JEM Component users Model
 *
 * @package JEM
 *
 */
class JemModelUsers extends BaseDatabaseModel
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
     */
    public function __construct()
    {
        parent::__construct();

        $app = Factory::getApplication();

        $limit      = $app->getUserStateFromRequest( 'com_jem.limit', 'limit', $app->get('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest( 'com_jem.limitstart', 'limitstart', 0, 'int' );
        $limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

    /**
     * Method to get venues item data
     *
     * @access public
     * @return array
     */
    function getData()
    {
        // Lets load the venues if they doesn't already exist
        if (empty($this->_data))
        {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_data;
    }

    /**
     * Total nr of venues
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
     * Method to get a pagination object for the venues
     *
     * @access public
     * @return integer
     */
    function getPagination()
    {
        // Lets load the content if it doesn't already exist
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
        }

        return $this->_pagination;
    }

    /**
     * Method to build the query for the venues
     *
     * @access private
     * @return string
     */
    protected function _buildQuery()
    {
        // Get the WHERE and ORDER BY clauses for the query
        $where   = $this->_buildContentWhere();
        $orderby = $this->_buildContentOrderBy();

        $query = 'SELECT u.id, u.name, u.username, u.email '
               . ' FROM #__users AS u '
               . $where
               . $orderby
               ;

        return $query;
    }

    /**
     * Method to build the orderby clause of the query for the venues
     *
     * @access private
     * @return string
     */
    protected function _buildContentOrderBy()
    {
        $app = Factory::getApplication();

        $filter_order     = $app->getUserStateFromRequest( 'com_jem.users.filter_order', 'filter_order', 'u.name', 'cmd' );
        $filter_order_Dir = $app->getUserStateFromRequest( 'com_jem.users.filter_order_Dir', 'filter_order_Dir', '', 'word' );

        $filter_order     = InputFilter::getInstance()->clean($filter_order, 'cmd');
        $filter_order_Dir = InputFilter::getInstance()->clean($filter_order_Dir, 'word');
        $allowedOrder = array('u.name', 'u.username', 'u.email', 'u.id');
        if (!in_array($filter_order, $allowedOrder, true)) {
            $filter_order = 'u.name';
        }
        $filter_order_Dir = strtoupper($filter_order_Dir) === 'DESC' ? 'DESC' : 'ASC';

        $orderby = ' ORDER BY '.$filter_order.' '.$filter_order_Dir;

        return $orderby;
    }

    /**
     * Method to build the where clause of the query for the venues
     *
     * @access private
     * @return string
     */
    protected function _buildContentWhere()
    {
        $app = Factory::getApplication();

        $search = $app->getUserStateFromRequest( 'com_jem.users.search', 'search', '', 'string' );
        $search = $this->_db->escape( trim(StringHelper::strtolower( $search ) ) );

        $where = array();

        /*
         * Search venues
         */
        if ($search) {
            $where[] = ' LOWER(u.name) LIKE '.$this->_db->quote('%'.$search.'%');
        }

        $where = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        return $where;
    }
}
?>
