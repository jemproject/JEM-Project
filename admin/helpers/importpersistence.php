<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Preserve import IDs without treating failed inserts as existing records.
 */
final class JemImportPersistenceHelper
{
    /**
     * Store a validated table and return the counter to increment.
     *
     * Existing records retain their table-specific update behaviour. A new
     * explicit ID cannot use Table::store(), which would issue an UPDATE.
     * Database exceptions deliberately propagate to the import error handler.
     */
    public static function store($table, $db, bool $replace, string $storeMethod = 'store', bool $updateNulls = false): string
    {
        $key = $table->getKeyName();
        $id = $replace ? (int) $table->$key : 0;

        if ($id > 0 && self::exists($table, $db, $id)) {
            self::normaliseForStrictSql($table, $table->getFields(), $key);

            if (!$table->$storeMethod($updateNulls) || !self::exists($table, $db, $id)) {
                throw new RuntimeException($table->getError() ?: 'The imported record could not be updated.');
            }

            return 'updated';
        }

        if ($id > 0) {
            // Exclude internal properties (for example ageLevelSubmitted) and
            // virtual import fields. Never use INSERT IGNORE to hide SQL errors.
            $record = (object) array_intersect_key(get_object_vars($table), $table->getFields());
            self::normaliseForStrictSql($record, $table->getFields(), $key);

            if (!$db->insertObject($table->getTableName(), $record, $key)) {
                throw new RuntimeException('The imported record could not be inserted.');
            }

            $table->$key = $record->$key;
        } else {
            $table->$key = 0;
            self::normaliseForStrictSql($table, $table->getFields(), $key);

            if (!$table->$storeMethod()) {
                throw new RuntimeException($table->getError() ?: 'The imported record could not be inserted.');
            }
        }

        return 'added';
    }

    /**
     * Convert only invalid empty typed values. Missing required data is an
     * error, not an implicit zero or empty title.
     */
    private static function normaliseForStrictSql($record, array $fields, string $key): void
    {
        foreach ($fields as $name => $field) {
            if ($name === $key || !is_object($field)) {
                continue;
            }

            $type = strtolower((string) ($field->Type ?? ''));
            $isDate = (bool) preg_match('/^(?:date|datetime|timestamp|time)\b/', $type);
            $isTyped = $isDate || (bool) preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|decimal|float|double)\b/', $type);
            $value = property_exists($record, $name) ? $record->$name : null;

            // These event text fields are optional to the author, but the
            // database requires an empty string when a CSV omits them.
            if ($value === null && in_array($name, array('introtext', 'fulltext', 'metadata'), true)
                && preg_match('/^(?:tinytext|text|mediumtext|longtext)\b/', $type)
                && strtoupper((string) ($field->Null ?? '')) !== 'YES') {
                $record->$name = '';
                continue;
            }

            // A table object's default expression is not a date value. Omit
            // it from an INSERT so the database can supply its own default.
            if ($isDate && is_string($value) && preg_match('/^current_timestamp(?:\(\d*\))?$/i', trim($value))) {
                $record->$name = null;
                continue;
            }

            $invalid = ($isTyped && $value === '')
                || ($isDate && in_array($value, array('0', 0, '0000-00-00', '0000-00-00 00:00:00'), true));

            if ($invalid) {
                if (strtoupper((string) ($field->Null ?? '')) === 'YES') {
                    $record->$name = null;
                } elseif (($field->Default ?? null) !== null) {
                    $record->$name = $field->Default;
                } else {
                    throw new RuntimeException('Missing or invalid required import field: ' . $name);
                }
            }
        }
    }

    private static function exists($table, $db, int $id): bool
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($table->getTableName()))
            ->where($db->quoteName($table->getKeyName()) . ' = ' . $id);

        return (bool) $db->setQuery($query)->loadResult();
    }
}
