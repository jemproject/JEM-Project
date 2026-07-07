<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * CSV output helpers.
 */
class JemCsv
{
    /**
     * Prefix values that spreadsheet software could treat as formulas.
     *
     * @param   mixed  $value
     *
     * @return  mixed
     */
    public static function protectFormulaValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($value !== '' && preg_match('/^[\s]*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Apply CSV formula protection to a whole row.
     *
     * @param   array  $row
     *
     * @return  array
     */
    public static function protectFormulaRow(array $row)
    {
        return array_map(array(__CLASS__, 'protectFormulaValue'), $row);
    }
}
