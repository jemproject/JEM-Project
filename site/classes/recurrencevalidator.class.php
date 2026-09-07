<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Normalises and validates recurrence rule values shared by forms and cleanup.
 */
final class JemRecurrenceValidator
{
    private const WEEKDAY_CODES = array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU');

    /**
     * @param   array|string|null  $days  Comma-separated weekday codes or an array of codes.
     *
     * @return  array|false  Normalised unique codes, or false for an invalid selection.
     */
    public static function normaliseWeekdays($days)
    {
        if (!is_array($days)) {
            $days = explode(',', (string) $days);
        }

        $normalised = array();

        foreach ($days as $day) {
            $day = strtoupper(trim((string) $day));

            if ($day === '' || !in_array($day, self::WEEKDAY_CODES, true)) {
                return false;
            }

            if (!in_array($day, $normalised, true)) {
                $normalised[] = $day;
            }
        }

        return $normalised ?: false;
    }
}
