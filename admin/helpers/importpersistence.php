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
    public static function store($table, $db, bool $replace, string $storeMethod = 'store'): string
    {
        $key = $table->getKeyName();
        $id = $replace ? (int) $table->$key : 0;

        if ($id > 0 && self::exists($table, $db, $id)) {
            if (!$table->$storeMethod() || !self::exists($table, $db, $id)) {
                throw new RuntimeException($table->getError() ?: 'The imported record could not be updated.');
            }

            return 'updated';
        }

        if ($id > 0) {
            // Exclude internal properties (for example ageLevelSubmitted) and
            // virtual import fields. Never use INSERT IGNORE to hide SQL errors.
            $record = (object) array_intersect_key(get_object_vars($table), $table->getFields());

            if (!$db->insertObject($table->getTableName(), $record, $key)) {
                throw new RuntimeException('The imported record could not be inserted.');
            }

            $table->$key = $record->$key;
        } else {
            $table->$key = 0;

            if (!$table->$storeMethod()) {
                throw new RuntimeException($table->getError() ?: 'The imported record could not be inserted.');
            }
        }

        return 'added';
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
