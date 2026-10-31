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
 * Model-Calendar
 */
class JemModelWeekcal extends JemModelEventslist
{
    protected $_currentweek = null;

    /** @var array|null One normalized ISO period for all consumers of this model. */
    private ?array $calendarPeriod = null;

    /**
     * Resolve the requested ISO week and displayed date range once per request.
     *
     * Finite week overflow keeps PHP's ISO normalization (for example week zero).
     * Unsupported years/ranges fall back to the current Joomla-local ISO week.
     *
     * @return array{year: int, week: int, iso_start: DateTimeImmutable, start: DateTimeImmutable,
     *     end: DateTimeImmutable, weeks: int, first_weekday: int, today: string} Shared period.
     */
    public function getCalendarPeriod(): array
    {
        if ($this->calendarPeriod === null) {
            $app = Factory::getApplication();
            $params = $app->getParams();
            $today = JemHelper::getJoomlaDate();
            $now = new DateTimeImmutable($today);
            $year = (int) $app->input->getInt('yearID', (int) $now->format('o'));
            $week = (int) $app->input->getInt('weekID', (int) $now->format('W'));
            $weeks = max(1, (int) $params->get('nrweeks', 1));
            $firstWeekday = (int) $params->get('firstweekday', 1) === 0 ? 0 : 1;
            if ($year < 1 || $year > 9999 || $week < -520000 || $week > 520000) {
                $year = (int) $now->format('o');
                $week = (int) $now->format('W');
            }
            // Avoid arithmetic overflow from an unsupported stored week count.
            if ($weeks > 520000) {
                $weeks = 1;
            }
            $isoStart = $now->setISODate($year, $week, 1);
            $start = $firstWeekday === 0 ? $isoStart->modify('-1 day') : $isoStart;
            $end = $start->modify('+' . ($weeks * 7 - 1) . ' days');
            if ($start->format('Y') < 1 || $end->format('Y') > 9999) {
                $isoStart = $now->setISODate((int) $now->format('o'), (int) $now->format('W'), 1);
                $start = $firstWeekday === 0 ? $isoStart->modify('-1 day') : $isoStart;
                $end = $start->modify('+' . ($weeks * 7 - 1) . ' days');
                if ($end->format('Y') > 9999) {
                    $weeks = 1;
                    $end = $start->modify('+6 days');
                }
            }
            $this->calendarPeriod = [
                'year' => (int) $isoStart->format('o'),
                'week' => (int) $isoStart->format('W'),
                'iso_start' => $isoStart,
                'start' => $start,
                'end' => $end,
                'weeks' => $weeks,
                'first_weekday' => $firstWeekday,
                'today' => $today,
            ];
        }

        return $this->calendarPeriod;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Method to auto-populate the model state.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app           = Factory::getApplication();
        $task          = $app->input->getCmd('task', '');
        $params        = $app->getParams();
        $top_category  = $params->get('top_category', 0);
        $show_archived_events = (bool) $params->get('show_archived_events', 0);
        $this->show_archived_events = $show_archived_events;
        $startdayonly  = $params->get('show_only_start', false);
        $period        = $this->getCalendarPeriod();

        # params
        $this->setState('params', $params);
        $this->applyMenuEventFilters($params);

        # publish state
        $this->_populatePublishState($task);

        ###########
        ## DATES ##
        ###########

        #only select events within specified dates. (chosen weeknrs)

        $filter_date_from = $period['start']->format('Y-m-d');
        $filter_date_to   = $period['end']->format('Y-m-d');

        $where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $this->_db->quote($filter_date_from) . ') >= 0';
        $this->setState('filter.calendar_from', $where);
        $this->setState('filter.date.from', $filter_date_from);

        $where = ' DATEDIFF(a.dates, ' . $this->_db->quote($filter_date_to) . ') <= 0';
        $this->setState('filter.calendar_to', $where);
        $this->setState('filter.date.to', $filter_date_to);

        ##################
        ## TOP-CATEGORY ##
        ##################

        if ($top_category) {
            $children = JemCategories::getChilds($top_category);
            if (count($children)) {
                $where = 'rel.catid IN ('. implode(',', $children) .')';
                $this->setState('filter.category_top', $where);
            }
        }

        # set filter
        $this->setState('filter.calendar_startdayonly', (bool)$startdayonly);
        $this->setState('filter.groupby', 'a.id');
        $this->setState('filter.show_archived_events', $show_archived_events);
    }

    /**
     * Method to get a list of events.
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items && $this->getState('filter.calendar_multiday', true) !== false) {
            $items = $this->calendarMultiday($items);
        }

        return $items ?: array();
    }

    /**
     * @return    JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Let parent create a new query object.
        $query = parent::getListQuery();

        $query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff, DAYOFWEEK(a.dates) AS weekday, DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month, WEEK(a.dates) AS weeknumber');

        return $query;
    }

    /**
     * create multi-day events
     */
    protected function calendarMultiday($items)
    {
        if (empty($items)) {
            return array();
        }

        $app          = Factory::getApplication();
        $params       = $app->getParams();
        $startdayonly = $this->getState('filter.calendar_startdayonly');
        $items = $this->projectCalendarSchedules(
            $items,
            (string) $this->getState('filter.date.from', ''),
            (string) $this->getState('filter.date.to', ''),
            (bool) $startdayonly
        );

        if (!$startdayonly) {
            foreach ($items as $i => $item)
            {
                if (!is_null($item->enddates) && ($item->enddates != $item->dates)) {
                    $multi = array();

                    $item->multi = 'first';
                    $item->multitimes = $item->times;
                    $item->multiname = $item->title;
                    $item->sort = 'zlast';

                    for ($counter = 0; $counter <= $item->datesdiff-1; $counter++)
                    {
                        # next day:
                        $nextday = (new DateTimeImmutable($item->dates))->modify('+' . ($counter + 1) . ' days');

                        # generate days of current multi-day selection
                        $multi[$counter] = clone $item;
                        $multi[$counter]->dates = $nextday->format('Y-m-d');

                        if ($multi[$counter]->dates < $item->enddates) {
                            $multi[$counter]->multi = 'middle';
                            $multi[$counter]->multistartdate = $item->dates;
                            $multi[$counter]->multienddate = $item->enddates;
                            $multi[$counter]->multitimes = $item->times;
                            $multi[$counter]->multiname = $item->title;
                            $multi[$counter]->times = $item->times;
                            $multi[$counter]->endtimes = $item->endtimes;
                            $multi[$counter]->sort = 'middle';
                        } elseif ($multi[$counter]->dates == $item->enddates) {
                            $multi[$counter]->multi = 'zlast';
                            $multi[$counter]->multistartdate = $item->dates;
                            $multi[$counter]->multienddate = $item->enddates;
                            $multi[$counter]->multitimes = $item->times;
                            $multi[$counter]->multiname = $item->title;
                            $multi[$counter]->sort = 'first';
                            $multi[$counter]->times = $item->times;
                            $multi[$counter]->endtimes = $item->endtimes;
                        }
                    } // for

                    # add generated days to data
                    $items = array_merge($items, $multi);
                    # unset temp array holding generated days before working on the next multiday event
                    unset($multi);
                }
            } // foreach
        }

        # Remove items out of date range
        $startdate = $this->getState('filter.date.from');
        $enddate   = $this->getState('filter.date.to');
        if (empty($startdate) || empty($enddate)) {
            $period = $this->getCalendarPeriod();
            $startdate = $period['start']->format('Y-m-d');
            $enddate = $period['end']->format('Y-m-d');
        }

        foreach ($items as $index => $item) {
            $date = $item->dates;

            if ($date > $enddate) {
                unset ($items[$index]);
            } elseif ($date < $startdate) {
                unset ($items[$index]);
            }
        }

        # Do we still have events? Return if not.
        if (empty($items)) {
            return array();
        }

        # Sort the items
        foreach ($items as $item) {
            $time[] = $item->times;
            $title[] = $item->title;
        //    $id[] = $item->id;
        //    $dates[] = $item->dates;
            $multi[] = (isset($item->multi) ? $item->multi : false);
            $multitime[] = (isset($item->multitime) ? $item->multitime : false);
            $multititle[] = (isset($item->multititle) ? $item->multititle : false);
            $sort[] = (isset($item->sort) ? $item->sort : 'zlast');
        }

        array_multisort($sort, SORT_ASC, $multitime, $multititle, $time, SORT_ASC, $title, $items);

        return $items;
    }

    /**
     * Get the current ISO week in the configured Joomla timezone.
     *
     * @return int Current ISO week, independent of the displayed first weekday.
     */
    public function getCurrentweek()
    {
        if (!isset($this->_currentweek)) {
            $this->_currentweek = (int) (new DateTimeImmutable(JemHelper::getJoomlaDate()))->format('W');
        }

        return $this->_currentweek;
    }
}
?>
