<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

class JemModelSpecialdays extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'title', 'a.title',
                'day_type', 'a.day_type',
                'start_date', 'a.start_date',
                'end_date', 'a.end_date',
                'published', 'a.published',
                'ordering', 'a.ordering',
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        $params = $app->getParams();

        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search'));
        $this->setState('filter.state', $this->getUserStateFromRequest($this->context . '.filter_state', 'filter_state', '', 'string'));
        $this->setState('filter.day_type', $this->getUserStateFromRequest($this->context . '.filter_day_type', 'filter_day_type', '', 'string'));
        $this->setState('filter.year', $app->getUserStateFromRequest($this->context . '.filter_year', 'filter_year', (int) date('Y'), 'int'));
        $this->setState('params', $params);

        parent::populateState('a.ordering', 'asc');
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.day_type');
        $id .= ':' . $this->getState('filter.year');

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.*')
            ->from($db->quoteName('#__jem_special_days', 'a'));

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(a.title LIKE ' . $search . ' OR a.description LIKE ' . $search . ')');
        }

        $published = $this->getState('filter.state');
        if (is_numeric($published)) {
            $query->where('a.published = ' . (int) $published);
        } elseif ($published === '') {
            $query->where('a.published IN (0, 1)');
        }

        $dayType = trim((string) $this->getState('filter.day_type'));
        if ($dayType !== '') {
            $query->where('a.day_type = ' . $db->quote($dayType));
        }

        $year = (int) $this->getState('filter.year', (int) date('Y'));
        $startMonth = max(1, min(12, (int) $this->getState('params')->get('annual_start_month', 1)));
        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
        $periodEnd = $periodStart->modify('+12 months -1 day');
        $nullDate = $db->quote($db->getNullDate());
        $startDate = $db->quoteName('a.start_date');
        $endDate = $db->quoteName('a.end_date');
        $weekdays = $db->quoteName('a.weekdays');

        $query->where('('
            . '('
                . $weekdays . ' IS NOT NULL'
                . ' AND ' . $weekdays . ' <> ' . $db->quote('')
                . ' AND (' . $startDate . ' IS NULL OR ' . $startDate . ' = ' . $nullDate . ' OR ' . $startDate . ' <= ' . $db->quote($periodEnd->format('Y-m-d')) . ')'
                . ' AND (' . $endDate . ' IS NULL OR ' . $endDate . ' = ' . $nullDate . ' OR ' . $endDate . ' >= ' . $db->quote($periodStart->format('Y-m-d')) . ')'
            . ')'
            . ' OR '
            . '('
                . $startDate . ' IS NOT NULL'
                . ' AND ' . $startDate . ' <> ' . $nullDate
                . ' AND ' . $startDate . ' <= ' . $db->quote($periodEnd->format('Y-m-d'))
                . ' AND (' . $endDate . ' IS NULL OR ' . $endDate . ' = ' . $nullDate . ' OR ' . $endDate . ' >= ' . $db->quote($periodStart->format('Y-m-d')) . ')'
            . ')'
        . ')');

        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = strtoupper($this->state->get('list.direction', 'asc'));

        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'a.ordering';
        }

        if (!in_array($orderDir, array('ASC', 'DESC'), true)) {
            $orderDir = 'ASC';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getAvailableYears()
    {
        $db = $this->getDatabase();
        $defaultYear = (int) date('Y');
        $years = array($defaultYear => $defaultYear);
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('start_date', 'end_date')))
            ->from($db->quoteName('#__jem_special_days'))
            ->where($db->quoteName('published') . ' IN (0, 1)');

        try {
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: array();
        } catch (RuntimeException $e) {
            return $years;
        }

        $nullDate = $db->getNullDate();

        foreach ($rows as $row) {
            $start = (!empty($row->start_date) && $row->start_date !== $nullDate) ? (string) $row->start_date : '';
            $end = (!empty($row->end_date) && $row->end_date !== $nullDate) ? (string) $row->end_date : $start;

            if ($start === '') {
                continue;
            }

            try {
                $startYear = (int) (new DateTimeImmutable($start))->format('Y');
                $endYear = (int) (new DateTimeImmutable($end))->format('Y');
            } catch (Exception $e) {
                continue;
            }

            if ($endYear < $startYear) {
                $endYear = $startYear;
            }

            for ($year = $startYear; $year <= $endYear; $year++) {
                $years[$year] = $year;
            }
        }

        ksort($years);

        return $years;
    }
}
