<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Date-only validation shared by Special Day forms, tables and CSV imports. */
final class JemSpecialDayDate
{
    public static function normalise($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (
            !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            return null;
        }

        return $value;
    }

    /** Accept the documented import formats, never relative dates or date rollover. */
    public static function fromCsv($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^([0-9]{1,2})([-\/])([0-9]{1,2})\2([0-9]{4})$/D', $value, $parts)) {
            // A slash date with a day <= 12 has an ambiguous day/month order.
            if ($parts[2] === '/' && (int) $parts[1] <= 12) {
                return null;
            }

            $value = sprintf('%04d-%02d-%02d', (int) $parts[4], (int) $parts[3], (int) $parts[1]);
        }

        return self::normalise($value);
    }
}
