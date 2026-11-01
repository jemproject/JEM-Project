<?php

declare(strict_types=1);

namespace JemProject\Tests\CalendarExpansion;

use DateTimeImmutable;
use Exception;

/** Frozen reference algorithms. Only short fixture spans should use these deliberately unbounded loops. */
trait LegacyCalendarExpansion
{
    /**
     * Frozen pre-#2441 eventslist expansion for exact differential tests; do not optimize.
     *
     * @param array $items Synthetic rows.
     * @return array Legacy expansion and ordering.
     */
    public function legacyCommon($items)
    {
        if (empty($items)) {
            return array();
        }

        $startdayonly = $this->getState('filter.calendar_startdayonly');
        $rangeStart   = (string) $this->getState('filter.date.from', '');
        $rangeEnd     = (string) $this->getState('filter.date.to', '');
        $items = $this->projectCalendarSchedules($items, $rangeStart, $rangeEnd, (bool) $startdayonly);

        if (!$items) {
            return array();
        }

        if (!$startdayonly) {
            foreach ($items as $item)
            {
                if (!is_null($item->enddates) && ($item->enddates != $item->dates)) {
                    try {
                        $startDate = new DateTimeImmutable((string) $item->dates);
                        $endDate   = new DateTimeImmutable((string) $item->enddates);
                    } catch (Exception $e) {
                        continue;
                    }

                    if ($endDate <= $startDate) {
                        continue;
                    }

                    $datesdiff = isset($item->datesdiff)
                        ? (int) $item->datesdiff
                        : (int) $startDate->diff($endDate)->days;
                    $multi = array();

                    # it's multiday regardless if other days are on next month
                    $item->multi = 'first';
                    $item->multitimes = $item->times;
                    $item->multiname = $item->title;
                    $item->sort = 'zlast';

                    for ($counter = 0; $counter <= $datesdiff - 1; $counter++)
                    {
                        # next day:
                        $nextday = $startDate->modify('+' . ($counter + 1) . ' days')->format('Y-m-d');

                        // Generate only days inside the calendar's selected range.
                        if (($rangeStart === '' || $nextday >= $rangeStart)
                            && ($rangeEnd === '' || $nextday <= $rangeEnd)) {
                            $multi[$counter] = clone $item;
                            $multi[$counter]->dates = $nextday;

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
                        }
                    } // for

                    # add generated days to data
                    $items = array_merge($items, $multi);
                    # unset temp array holding generated days before working on the next multiday event
                    unset($multi);
                }
            } // foreach
        }

        # Sort the items
        foreach ($items as $item) {
            $time[] = $item->times;
            $title[] = $item->title;
        }

        array_multisort($time, SORT_ASC, $title, SORT_ASC, $items);

        return $items;
    }

    /**
     * Frozen pre-#2441 weekcal expansion for exact differential tests; do not optimize.
     *
     * @param array $items Synthetic rows.
     * @return array Legacy expansion and ordering.
     */
    public function legacyWeek($items)
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
}
