<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Value normalisation helpers for external venue imports.
 */
class JemImportVenueHelper
{
    /**
     * Split a latitude/longitude pair. Latitude-first is the default, but a
     * first value outside latitude range is recognised as longitude-first.
     *
     * @return array|null
     */
    public static function normaliseCoordinatePair($value)
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\s*([+-]?\d{1,3}(?:[.,]\d+)?)\s*[,;|]\s*([+-]?\d{1,3}(?:[.,]\d+)?)\s*$/', $value, $match)) {
            return null;
        }

        $first = (float) str_replace(',', '.', $match[1]);
        $second = (float) str_replace(',', '.', $match[2]);

        if (abs($first) > 90 && abs($first) <= 180 && abs($second) <= 90) {
            [$first, $second] = [$second, $first];
        }

        if (abs($first) > 90 || abs($second) > 180 || ($first === 0.0 && $second === 0.0)) {
            return null;
        }

        return array(
            'latitude' => number_format($first, 6, '.', ''),
            'longitude' => number_format($second, 6, '.', ''),
        );
    }
}
