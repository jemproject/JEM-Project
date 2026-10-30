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
     * @param   mixed    $value              Original cell value.
     * @param   boolean  $escapeApostrophes  Also escape literal leading apostrophes in v2 exports.
     *
     * @return  mixed
     */
    public static function protectFormulaValue($value, bool $escapeApostrophes = false)
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($value !== '' && $escapeApostrophes && $value[0] === "'") {
            return "'" . $value;
        }

        if ($value !== '' && preg_match('/^(?: *[\x09-\x0D]|\s*[=+\-@])/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Restore safe values escaped by a versioned JEM CSV export.
     * Formula-like text stays escaped and is never converted into a formula.
     *
     * @param   mixed    $value              Parsed CSV cell.
     * @param   boolean  $escapedApostrophes  Whether the row explicitly declares v2 escaping.
     *
     * @return  mixed  Restored literal text or the still-protected formula value.
     */
    public static function unprotectFormulaValue($value, bool $escapedApostrophes = false)
    {
        if (!is_string($value) || strlen($value) < 2 || $value[0] !== "'") {
            return $value;
        }

        $original = substr($value, 1);
        if ($escapedApostrophes && $original[0] === "'") {
            // Exactly one prefix was added; never decode the original a second time.
            return $original;
        }

        $trimmed = trim($original);

        if (is_numeric($trimmed)
            || preg_match('/^-+$/', $trimmed)
            || preg_match('/^ *[\x09-\x0D]/', $original)
            || preg_match('/^\+\d[\d\s().-]{2,}$/', $trimmed)) {
            return $original;
        }

        return $value;
    }

    /**
     * Apply CSV formula protection to a whole row.
     *
     * @param   array    $row                Original cells, preserving their keys.
     * @param   boolean  $escapeApostrophes  Enable v2 literal-apostrophe escaping.
     *
     * @return  array
     */
    public static function protectFormulaRow(array $row, bool $escapeApostrophes = false)
    {
        return array_map(static function ($value) use ($escapeApostrophes) {
            return self::protectFormulaValue($value, $escapeApostrophes);
        }, $row);
    }

    /**
     * Write a protected CSV row while preserving the caller's CSV format.
     *
     * @param   resource  $stream
     * @param   array     $row
     * @param   string    $separator
     * @param   string    $enclosure
     * @param   string    $escape
     * @param   string    $eol
     * @param   boolean   $escapeApostrophes  Only enable for exports declaring v2 metadata.
     *
     * @return  integer|false
     */
    public static function putRow(
        $stream,
        array $row,
        $separator = ',',
        $enclosure = '"',
        $escape = '',
        $eol = "\n",
        bool $escapeApostrophes = false
    ) {
        return fputcsv(
            $stream,
            self::protectFormulaRow($row, $escapeApostrophes),
            $separator,
            $enclosure,
            $escape,
            $eol
        );
    }
}
