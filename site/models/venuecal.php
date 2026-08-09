<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/eventslist.php';

/**
 * Model-Venuecal
 **/
class JemModelVenueCal extends JemModelEventslist
{
    /**
     * Venue id
     *
     * @var int
     */
    protected $_venue = null;

    /**
     * Date as timestamp useable for strftime()
     *
     * @var int
     */
    protected $_date = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $app         = Factory::getApplication();
    //    $jemsettings = JemHelper::config();
        $jinput      = $app->input;
        $params      = $app->getParams();

        $id = $jinput->getInt('id', 0);
        if (empty($id)) {
            $id = $params->get('id', 0);
        }

        $this->setdate(JemHelper::getJoomlaDate());
        $this->setId((int)$id);

        parent::__construct();
    }

    public function setdate($date)
    {
        $this->_date = $date;
    }

    /**
     * Method to set the venue id
     */
    public function setId($id)
    {
        // Set new venue ID and wipe data
        $this->_id = $id;
    }

    /**
     * Method to auto-populate the model state.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app          = Factory::getApplication();
        $params       = $app->getParams();
        $itemid       = $app->input->getInt('Itemid', 0);
        $task         = $app->input->getCmd('task', '');
        $startdayonly = $params->get('show_only_start', false);
        $show_archived_events = (bool) $params->get('show_archived_events', 0);
        $this->show_archived_events = $show_archived_events;

        # params
        $this->setState('params', $params);
        $this->applyMenuEventFilters($params);

        # publish state
        $this->_populatePublishState($task);

        ###########
        ## DATES ##
        ###########

        #only select events within specified dates. (chosen month)

        $timeZone = new DateTimeZone(JemHelper::getJoomlaTimeZoneName());
        $selectedDate = is_numeric($this->_date)
            ? (new DateTimeImmutable('@' . (int) $this->_date))->setTimezone($timeZone)
            : new DateTimeImmutable((string) $this->_date, $timeZone);
        $filter_date_from = $selectedDate->modify('first day of this month')->format('Y-m-01');
        $filter_date_to   = $selectedDate->modify('last day of this month')->format('Y-m-d');

        $where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), '. $this->_db->Quote($filter_date_from) .') >= 0';
        $this->setState('filter.calendar_from', $where);
        $this->setState('filter.date.from', $filter_date_from);

        $where = ' DATEDIFF(a.dates, '. $this->_db->Quote($filter_date_to) .') <= 0';
        $this->setState('filter.calendar_to', $where);
        $this->setState('filter.date.to', $filter_date_to);

        # set filter
        $this->setState('filter.calendar_multiday', true);
        $this->setState('filter.calendar_startdayonly', (bool)$startdayonly);
        $this->setState('filter.filter_locid', $this->_id);
        $this->setState('filter.show_archived_events', $show_archived_events);

        $app->setUserState('com_jem.venuecal.locid'.$itemid, $this->_id);

        # groupby
        $this->setState('filter.groupby', array('a.id'));
    }

    /**
     * Method to get a list of events.
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items) {
            return $items;
        }

        return array();
    }

    /**
     * @return    JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Let parent create a new query object.
        $query = parent::getListQuery();

        // here we can extend the query of the Eventslist model
        $query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff,DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month');
        //$query->where('a.locid = '.$this->_id);

        return $query;
    }
}
?>
