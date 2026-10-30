<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Backwards-compatible metadata embedded in JEM-to-JEM CSV files.
 */
class JemCsvMetadataHelper
{
    const VERSION_FIELD = 'jem_export_version';
    const FORMAT_FIELD = 'jem_csv_format';
    const FORMAT_RFC4180 = 'rfc4180';
    public const ESCAPE_FIELD = 'jem_csv_escape';
    public const ESCAPE_APOSTROPHE_V2 = 'apostrophe-v2';

    /**
     * Add version, quoting and escaping metadata to a JEM Migration export.
     * Older importers ignore the extra columns but cannot decode v2 literal escapes.
     *
     * @param   array   $row      Unescaped database values.
     * @param   string  $version  Exporting JEM version.
     *
     * @return  array  Original values plus the CSV metadata columns.
     */
    public static function addVersion(array $row, $version)
    {
        $row[self::VERSION_FIELD] = self::normaliseVersion($version);
        $row[self::FORMAT_FIELD] = self::FORMAT_RFC4180;
        $row[self::ESCAPE_FIELD] = self::ESCAPE_APOSTROPHE_V2;

        return $row;
    }

    /**
     * Locate the metadata column in a CSV header.
     *
     * @param   array  $header  Parsed CSV column names.
     *
     * @return  integer|string|null  Matching key, or null for unversioned files.
     */
    public static function findVersionColumn(array $header)
    {
        return self::findColumn($header, self::VERSION_FIELD);
    }

    /**
     * Locate an explicitly declared cell-escaping revision.
     *
     * @param   array  $header  Parsed CSV column names.
     *
     * @return  integer|string|null  Matching key, or null for the legacy convention.
     */
    public static function findEscapeColumn(array $header)
    {
        return self::findColumn($header, self::ESCAPE_FIELD);
    }

    /**
     * Match a metadata name without changing user column names.
     *
     * @param   array   $header  Parsed CSV column names.
     * @param   string  $name    Metadata name to locate.
     *
     * @return  integer|string|null  Matching header key, or null.
     */
    private static function findColumn(array $header, string $name)
    {
        foreach ($header as $index => $field) {
            $field = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $field));

            if (strcasecmp($field, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Restore one Migration row according to its declared cell-escaping revision.
     * Unversioned CSV is untouched; legacy JEM files retain their previous policy.
     *
     * @param   array                $row            Parsed, UTF-8-normalised cells.
     * @param   integer|string|null  $versionColumn  Export-version header key.
     * @param   integer|string|null  $escapeColumn   Escape-revision header key.
     *
     * @return  array  Decoded cells with formula protection retained.
     *
     * @throws  UnexpectedValueException  When an explicit revision is unsupported.
     */
    public static function restoreFormulaEscapes(array $row, $versionColumn, $escapeColumn): array
    {
        if ($versionColumn === null) {
            return $row;
        }

        $escapedApostrophes = $escapeColumn !== null;
        if ($escapedApostrophes && ($row[$escapeColumn] ?? null) !== self::ESCAPE_APOSTROPHE_V2) {
            throw new UnexpectedValueException('Unsupported JEM CSV escaping revision.');
        }

        return array_map(static function ($value) use ($escapedApostrophes) {
            return JemCsv::unprotectFormulaValue($value, $escapedApostrophes);
        }, $row);
    }

    /** New exports use RFC 4180 quoting; older JEM exports used PHP's backslash escape. */
    public static function usesRfc4180(array $header)
    {
        foreach ($header as $field) {
            $field = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $field));

            if (strcasecmp($field, self::FORMAT_FIELD) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read and validate the exporting JEM version from a CSV row.
     */
    public static function extractVersion(array $row, $column)
    {
        if ($column === null || !array_key_exists($column, $row)) {
            return '';
        }

        return self::normaliseVersion($row[$column]);
    }

    /**
     * Keep metadata compact and safe for screen messages and logs.
     */
    public static function normaliseVersion($version)
    {
        $version = trim((string) $version);

        return preg_match('/^[0-9A-Za-z][0-9A-Za-z._+\-]{0,63}$/', $version) ? $version : '';
    }
}
