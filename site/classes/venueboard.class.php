<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Groups already-visible day sessions without changing event visibility. */
final class JemVenueBoard
{
    public static function selectedIds($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map('intval', $values), static function ($id) {
            return $id > 0;
        })));
    }

    /**
     * @param array $locations Ordered venue or Space records keyed by ID.
     * @param array $assignments Event ID => published Space IDs.
     */
    public static function group(array $rows, array $locations, array $assignments = array(), string $mode = 'venues'): array
    {
        $columns = array();
        foreach ($locations as $id => $location) {
            $columns[(int) $id] = array('location' => $location, 'events' => array());
        }

        foreach ($rows as $row) {
            if ($mode === 'spaces') {
                $matched = false;
                foreach (array_unique($assignments[(int) $row->id] ?? array()) as $spaceId) {
                    if (isset($columns[(int) $spaceId])) {
                        $columns[(int) $spaceId]['events'][] = $row;
                        $matched = true;
                    }
                }
                if ($matched) {
                    continue;
                }
            } else {
                $venueId = (int) ($row->locid ?? 0);
                if ($venueId > 0 && !isset($columns[$venueId])) {
                    $columns[$venueId] = array(
                        'location' => (object) array('id' => $venueId, 'name' => (string) ($row->venue ?? ''), 'alias' => (string) ($row->l_alias ?? '')),
                        'events' => array(),
                    );
                }
                if ($venueId > 0) {
                    $columns[$venueId]['events'][] = $row;
                    continue;
                }
            }

            if (!isset($columns[0])) {
                $columns[0] = array('location' => null, 'events' => array());
            }
            $columns[0]['events'][] = $row;
        }

        foreach ($columns as &$column) {
            usort($column['events'], static function ($left, $right) {
                $leftSchedule = $left->day_schedule ?? array();
                $rightSchedule = $right->day_schedule ?? array();

                return ((int) empty($leftSchedule['all_day']) <=> (int) empty($rightSchedule['all_day']))
                    ?: (($leftSchedule['start'] ?? PHP_INT_MAX) <=> ($rightSchedule['start'] ?? PHP_INT_MAX))
                    ?: strcmp((string) ($left->title ?? ''), (string) ($right->title ?? ''))
                    ?: ((int) ($left->id ?? 0) <=> (int) ($right->id ?? 0));
            });
        }
        unset($column);

        return $columns;
    }
}
