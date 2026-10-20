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

    /**
     * Add the exporting JEM version as an extra CSV column.
     * Older JEM importers safely ignore unknown columns.
     */
    public static function addVersion(array $row, $version)
    {
        $row[self::VERSION_FIELD] = self::normaliseVersion($version);
        $row[self::FORMAT_FIELD] = self::FORMAT_RFC4180;

        return $row;
    }

    /**
     * Locate the metadata column in a CSV header.
     */
    public static function findVersionColumn(array $header)
    {
        foreach ($header as $index => $field) {
            $field = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $field));

            if (strcasecmp($field, self::VERSION_FIELD) === 0) {
                return $index;
            }
        }

        return null;
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
