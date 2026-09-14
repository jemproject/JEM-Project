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

    /**
     * Add the exporting JEM version as an extra CSV column.
     * Older JEM importers safely ignore unknown columns.
     */
    public static function addVersion(array $row, $version)
    {
        $row[self::VERSION_FIELD] = self::normaliseVersion($version);

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
