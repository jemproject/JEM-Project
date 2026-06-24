<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\String\StringHelper;

/**
 * Global attendee registration list model.
 */
class JemModelAttendeeregistrations extends BaseDatabaseModel
{
    protected $_data = null;
    protected $_total = null;
    protected $_pagination = null;

    public function __construct()
    {
        parent::__construct();

        $app = Factory::getApplication();
        $settings = JemHelper::config();

        if ($app->input->getInt('limitstart', null) === null) {
            $app->setUserState('com_jem.attendeeregistrations.limitstart', 0);
        }

        $limit = $app->getUserStateFromRequest('com_jem.attendeeregistrations.limit', 'limit', $settings->display_num, 'int');
        $limitstart = $app->getUserStateFromRequest('com_jem.attendeeregistrations.limitstart', 'limitstart', 0, 'int');
        $limitstart = $limit ? (int) (floor($limitstart / $limit) * $limit) : 0;

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        if ($app->input->getCmd('layout', '') === 'pdf') {
            $this->setState('limit', 0);
            $this->setState('limitstart', 0);
        }
    }

    public function getData()
    {
        if ($this->_data === null) {
            $query = $this->buildQuery();
            $limit = (int) $this->getState('limit');
            $limitstart = (int) $this->getState('limitstart');
            $total = $this->getTotal();

            if ($limit > 0) {
                if ($total > 0 && $limitstart >= $total) {
                    $limitstart = 0;
                    $this->setState('limitstart', 0);
                    Factory::getApplication()->setUserState('com_jem.attendeeregistrations.limitstart', 0);
                }

                $this->_data = $this->_getList($query, $limitstart, $limit);
            } else {
                $this->_data = $this->_getList($query);
            }
        }

        return $this->_data;
    }

    public function getTotal()
    {
        if ($this->_total === null) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $db->setQuery($this->buildCountQuery());
            $this->_total = (int) $db->loadResult();
        }

        return $this->_total;
    }

    public function getPagination()
    {
        if ($this->_pagination === null) {
            $this->_pagination = new Pagination($this->getTotal(), (int) $this->getState('limitstart'), (int) $this->getState('limit'));
        }

        return $this->_pagination;
    }

    protected function buildQuery()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select(array(
            'r.id AS registration_id',
            'r.event',
            'r.uid',
            'r.uregdate',
            'r.waiting',
            'r.status',
            'r.places',
            'r.comment',
            'u.name',
            'u.username',
            'u.email',
            'a.id AS eventid',
            'a.alias AS event_alias',
            'a.title AS event_title',
            'a.dates',
            'a.enddates',
            'a.times',
            'a.endtimes',
            'a.published',
            'v.id AS venue_id',
            'v.alias AS venue_alias',
            'v.venue',
            'v.city',
            'v.state',
            'v.country',
        ))
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('r.uid'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('r.event'))
            ->join('LEFT', $db->quoteName('#__jem_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = ' . $db->quoteName('a.locid'));

        $where = $this->buildWhere();

        if ($where) {
            $query->where($where);
        }

        $query->order($this->buildOrderBy());

        return $query;
    }

    protected function buildCountQuery()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('r.uid'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('r.event'));

        $where = $this->buildWhere();

        if ($where) {
            $query->where($where);
        }

        return $query;
    }

    protected function buildWhere(): array
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $filter = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter', 'filter', 0, 'int');
        $status = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_status', 'filter_status', -2, 'int');
        $search = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_search', 'filter_search', '', 'string');
        $search = $db->escape(trim(StringHelper::strtolower($search)));
        $where = array();

        if ($status > -2) {
            if ($status === 2) {
                $where[] = $db->quoteName('r.status') . ' = 1';
                $where[] = $db->quoteName('r.waiting') . ' = 1';
            } else {
                $where[] = $db->quoteName('r.status') . ' = ' . (int) $status;

                if ($status === 1) {
                    $where[] = $db->quoteName('r.waiting') . ' = 0';
                }
            }
        }

        if ($search !== '') {
            switch ($filter) {
                case 1:
                    $where[] = 'LOWER(' . $db->quoteName('u.name') . ') LIKE ' . $db->quote('%' . $search . '%');
                    break;
                case 2:
                    $where[] = 'LOWER(' . $db->quoteName('u.username') . ') LIKE ' . $db->quote('%' . $search . '%');
                    break;
                case 3:
                    $where[] = 'LOWER(' . $db->quoteName('a.title') . ') LIKE ' . $db->quote('%' . $search . '%');
                    break;
                default:
                    $where[] = '('
                        . 'LOWER(' . $db->quoteName('u.name') . ') LIKE ' . $db->quote('%' . $search . '%')
                        . ' OR LOWER(' . $db->quoteName('u.username') . ') LIKE ' . $db->quote('%' . $search . '%')
                        . ' OR LOWER(' . $db->quoteName('a.title') . ') LIKE ' . $db->quote('%' . $search . '%')
                        . ')';
                    break;
            }
        }

        return $where;
    }

    protected function buildOrderBy(): string
    {
        $app = Factory::getApplication();
        $filterOrder = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_order', 'filter_order', 'r.uregdate', 'cmd');
        $filterOrderDir = $app->getUserStateFromRequest('com_jem.attendeeregistrations.filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filterOrder = InputFilter::getInstance()->clean($filterOrder, 'cmd');
        $filterOrderDir = strtoupper(InputFilter::getInstance()->clean($filterOrderDir, 'word')) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = array(
            'r.id',
            'u.name',
            'u.username',
            'r.uid',
            'r.places',
            'r.uregdate',
            'a.title',
            'a.dates',
            'r.status',
            'v.venue',
        );

        if (!in_array($filterOrder, $allowed, true)) {
            $filterOrder = 'r.uregdate';
        }

        return $filterOrder . ' ' . $filterOrderDir . ', r.id DESC';
    }
}
?>
