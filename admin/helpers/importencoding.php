<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

if (!function_exists('jem_normalise_csv_utf8')) {
    /**
     * Normalise a CSV value to UTF-8.
     *
     * @param  mixed  $value
     * @param  mixed  $key
     * @return void
     */
    function jem_normalise_csv_utf8(&$value, $key)
    {
        if ($value === null || $value === '') {
            return;
        }

        $value = (string) $value;

        if ($key === 0) {
            $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
            if (strncmp($value, $bom, 3) === 0) {
                $value = substr($value, 3);
            }
        }

        $isUtf8 = function_exists('mb_check_encoding')
            ? mb_check_encoding($value, 'UTF-8')
            : (bool) preg_match('//u', $value);

        if (!$isUtf8) {
            $converted = iconv('windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
    }
}
