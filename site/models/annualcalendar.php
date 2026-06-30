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
 * Annual Calendar model.
 */
class JemModelAnnualcalendar extends JemModelEventslist
{
    /**
     * Method to auto-populate the model state.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app                  = Factory::getApplication();
        $params               = $app->getParams();
        $task                 = $app->input->getCmd('task', '');
        $top_category         = (int) $params->get('top_category', 0);
        $show_archived_events = (bool) $params->get('show_archived_events', 0);
        $this->show_archived_events = $show_archived_events;
        $startdayonly         = $params->get('show_only_start', false);
        $year                 = (int) $app->input->getInt('yearID', date('Y'));
        $startMonth           = max(1, min(12, (int) $params->get('annual_start_month', 1)));

        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
        $periodEnd   = $periodStart->modify('+12 months -1 day');

        $this->setState('params', $params);
        $this->applyMenuEventFilters($params);

        $this->_populatePublishState($task);

        $filter_date_from = $periodStart->format('Y-m-d');
        $filter_date_to   = $periodEnd->format('Y-m-d');

        $where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $this->_db->quote($filter_date_from) . ') >= 0';
        $this->setState('filter.calendar_from', $where);
        $this->setState('filter.date.from', $filter_date_from);

        $where = ' DATEDIFF(a.dates, ' . $this->_db->quote($filter_date_to) . ') <= 0';
        $this->setState('filter.calendar_to', $where);
        $this->setState('filter.date.to', $filter_date_to);

        if ($top_category) {
            $children = JemCategories::getChilds($top_category);

            if (count($children)) {
                $where = 'rel.catid IN (' . implode(',', $children) . ')';
                $this->setState('filter.category_top', $where);
            }
        }

        $this->setState('filter.calendar_multiday', true);
        $this->setState('filter.calendar_startdayonly', (bool) $startdayonly);
        $this->setState('filter.groupby', array('a.id'));
        $this->setState('filter.show_archived_events', $show_archived_events);
    }

    /**
     * Method to get a list of events.
     */
    public function getItems()
    {
        $items = parent::getItems();

        return $items ?: array();
    }

    /**
     * @return JDatabaseQuery
     */
    protected function getListQuery()
    {
        $query = parent::getListQuery();

        $query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff, DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month');

        return $query;
    }
}
?>
