<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Read-only formatter for immutable Event Venue allocation snapshots.
 */
final class JemVenueSnapshot
{
    public static function decode($event): array
    {
        $value = is_object($event) ? ($event->venue_snapshot ?? '') : $event;
        if (!is_string($value) || trim($value) === '') {
            return array();
        }
        try {
            $snapshot = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return array();
        }

        return is_array($snapshot) && ($snapshot['schema'] ?? '') === 'jem-venue-capacity/v1'
            ? $snapshot
            : array();
    }

    public static function lines($event): array
    {
        $snapshot = self::decode($event);
        if (!$snapshot) {
            return array();
        }
        $lines = array();
        foreach ((array) ($snapshot['spaces'] ?? array()) as $space) {
            $layout = (array) ($space['layout'] ?? array());
            $areas = array_values(array_filter((array) ($space['capacity_areas'] ?? array()), static function ($area) {
                return !empty($area['published']);
            }));
            $lines[] = array(
                'space' => trim((string) ($space['name'] ?? '')),
                'layout' => trim((string) ($layout['name'] ?? '')),
                'capacity' => (int) ($layout['capacity'] ?? 0),
                'areas' => array_values(array_map(static function ($area) {
                    return array(
                        'name' => trim((string) ($area['name'] ?? '')),
                        'capacity' => (int) ($area['capacity'] ?? 0),
                    );
                }, $areas)),
            );
        }

        return $lines;
    }

    public static function summary($event): string
    {
        $parts = array();
        foreach (self::lines($event) as $line) {
            $label = trim($line['space'] . ($line['layout'] !== '' ? ' - ' . $line['layout'] : ''));
            if ($line['areas']) {
                $label .= ' (' . implode(', ', array_map(static function ($area) {
                    return $area['name'];
                }, $line['areas'])) . ')';
            }
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        return implode('; ', $parts);
    }
}
