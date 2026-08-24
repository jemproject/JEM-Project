<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Normalises the Events List menu date and ordering options.
 */
final class JemEventslistMenuPolicy
{
    /**
     * Preserve the three date-filter states defined by issue #2121:
     * empty (unrestricted), zero (today), and a positive day offset.
     *
     * @return int|string
     */
    public static function normaliseDayLimit($value)
    {
        if (is_array($value) || is_object($value) || is_resource($value)) {
            return '';
        }

        $value = trim((string) $value);

        if ($value === '' || strcasecmp($value, 'all') === 0) {
            return '';
        }

        if (!preg_match('/^(?:0|[1-9][0-9]*)$/D', $value)) {
            return '';
        }

        $days = filter_var($value, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 0),
        ));

        return $days === false ? '' : (int) $days;
    }

    /**
     * Resolve both sides of the date window against the same local day.
     *
     * @return array{from_days: int|string, until_days: int|string, from_date: ?string, until_date: ?string}
     */
    public static function dateWindow($fromValue, $untilValue, $today)
    {
        $fromDays  = self::normaliseDayLimit($fromValue);
        $untilDays = self::normaliseDayLimit($untilValue);
        $fromDate  = null;
        $untilDate = null;

        if ($fromDays !== '') {
            $fromDate = (clone $today)->modify('-' . $fromDays . ' days')->format('Y-m-d');
        }

        if ($untilDays !== '') {
            $untilDate = (clone $today)->modify('+' . $untilDays . ' days')->format('Y-m-d');
        }

        return array(
            'from_days'  => $fromDays,
            'until_days' => $untilDays,
            'from_date'  => $fromDate,
            'until_date' => $untilDate,
        );
    }

    /**
     * Map the menu option to an allowed query field without treating zero as empty.
     */
    public static function orderField($value)
    {
        $fields = array(
            '0' => 'a.dates',
            '1' => 'a.title',
            '2' => 'l.venue',
            '3' => 'l.city',
            '4' => 'l.state',
            '5' => 'c.catname',
        );
        $key = trim((string) $value);

        return isset($fields[$key]) ? $fields[$key] : 'a.dates';
    }

    /**
     * Return an allowed SQL order direction.
     */
    public static function orderDirection($value, $fallback = 'ASC')
    {
        $fallback = strtoupper(trim((string) $fallback));
        $fallback = in_array($fallback, array('ASC', 'DESC'), true) ? $fallback : 'ASC';
        $value    = strtoupper(trim((string) $value));

        return in_array($value, array('ASC', 'DESC'), true) ? $value : $fallback;
    }

    /**
     * Preserve interactive sorting while invalidating stale session defaults
     * after the menu configuration changes.
     */
    public static function orderContext($itemId, $defaultField, $defaultDirection, $archive = false)
    {
        $signature = implode('|', array(
            (string) $defaultField,
            (string) $defaultDirection,
            $archive ? 'archive' : 'current',
        ));

        return 'com_jem.eventslist.' . $itemId . '.order.' . substr(hash('sha256', $signature), 0, 16);
    }

    /**
     * Build deterministic ordering without repeating the primary field.
     *
     * @return array<int, string>
     */
    public static function buildOrderBy($field, $direction, $archive = false)
    {
        $direction    = self::orderDirection($direction);
        $tieDirection = $field === 'a.dates' ? $direction : ($archive ? 'DESC' : 'ASC');
        $orderby      = array($field . ' ' . $direction);

        foreach (array('a.dates', 'a.times', 'a.created') as $tieField) {
            if ($tieField !== $field) {
                $orderby[] = $tieField . ' ' . $tieDirection;
            }
        }

        return $orderby;
    }
}
