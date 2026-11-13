<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** Prepare parent content changes without replacing occurrence identity or schedule. */
final class JemSeriesInheritance
{
    /**
     * Apply whitelisted content changes to a complete stored occurrence.
     *
     * @param array $child Complete stored occurrence, including its category IDs.
     * @param array $changes Content fields changed by the parent editor.
     * @return array Occurrence ready for normal validation and persistence.
     */
    public static function propagate(array $child, array $changes): array
    {
        $protected = ['id', 'alias', 'dates', 'enddates', 'times', 'endtimes', 'created', 'created_by',
            'author_ip', 'version', 'modified', 'modified_by', 'checked_out', 'checked_out_time',
            'recurrence_first_id', 'recurrence_type', 'recurrence_counter', 'series_id', 'series_order', 'attribs'];
        foreach ($changes as $field => $value) {
            if (!in_array($field, $protected, true) && array_key_exists($field, $child)) {
                $child[$field] = $value;
            }
        }
        return $child;
    }
}
