<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\ListModel;

class JemModelSpecialdays extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'title', 'a.title',
                'day_type_id', 'a.day_type_id',
                'day_type', 'a.day_type',
                'start_date', 'a.start_date',
                'end_date', 'a.end_date',
                'show_dates', 'a.show_dates',
                'article_id', 'a.article_id',
                'url', 'a.url',
                'published', 'a.published',
                'access', 'a.access', 'access_level',
                'created_by', 'a.created_by',
                'author_name', 'u.name',
                'ordering', 'a.ordering',
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $params = ComponentHelper::getParams('com_jem');

        $search = $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter_state', 'filter_state', '', 'string');
        $this->setState('filter.state', $published);

        $dayType = $this->getUserStateFromRequest($this->context . '.filter_day_type', 'filter_day_type', '', 'string');
        $this->setState('filter.day_type', $dayType);

        $availableYears = $this->getAvailableYears();
        $defaultYear = $this->getDefaultYear($availableYears);
        $year = $this->getUserStateFromRequest($this->context . '.filter_year', 'filter_year', $defaultYear, 'int');

        if (!empty($availableYears) && !isset($availableYears[(int) $year])) {
            $year = $defaultYear;
        }

        $this->setState('filter.year', $year);
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
            ->select($db->quoteName('vl.title', 'access_level'))
            ->select($db->quoteName('u.name', 'author_name'))
            ->from($db->quoteName('#__jem_special_days', 'a'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'vl') . ' ON ' . $db->quoteName('vl.id') . ' = ' . $db->quoteName('a.access'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'));

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
            if (is_numeric($dayType)) {
                $query->where('a.day_type_id = ' . (int) $dayType);
            } else {
                $query->where('a.day_type = ' . $db->quote($dayType));
            }
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
        $years = array();
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

            if ($start === '') {
                continue;
            }

            try {
                $year = (int) (new DateTimeImmutable($start))->format('Y');
            } catch (Exception $e) {
                continue;
            }

            $years[$year] = $year;
        }

        ksort($years);

        return $years;
    }

    protected function getDefaultYear(array $years)
    {
        $currentYear = (int) date('Y');

        if (isset($years[$currentYear])) {
            return $currentYear;
        }

        foreach ($years as $year) {
            if ((int) $year > $currentYear) {
                return (int) $year;
            }
        }

        return $years ? (int) max($years) : $currentYear;
    }
}
