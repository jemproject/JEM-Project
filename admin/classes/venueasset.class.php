<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Creates and repairs Joomla ACL assets for JEM venues.
 */
final class JemVenueAsset
{
    public static function ensureSchema($db = null)
    {
        $db = $db ?: Factory::getContainer()->get('DatabaseDriver');
        $table = $db->replacePrefix('#__jem_venues');

        if (!in_array($table, $db->getTableList(), true)) {
            return;
        }

        $originalSqlMode = (string) $db->setQuery('SELECT @@SESSION.sql_mode')->loadResult();
        $sqlModes = array_values(array_filter(array_map('trim', explode(',', $originalSqlMode))));
        $repairSqlMode = implode(',', array_values(array_diff($sqlModes, array('NO_ZERO_DATE', 'NO_ZERO_IN_DATE'))));

        if ($repairSqlMode !== $originalSqlMode) {
            $db->setQuery('SET SESSION sql_mode = ' . $db->quote($repairSqlMode))->execute();
        }

        try {
            $columns = array_change_key_case($db->getTableColumns($table, false), CASE_LOWER);
            if (!isset($columns['asset_id'])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_venues')
                    . ' ADD COLUMN ' . $db->quoteName('asset_id')
                    . " INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER " . $db->quoteName('id')
                )->execute();
            }

            foreach ((array) $db->getTableKeys($table) as $keyName => $key) {
                $name = is_object($key)
                    ? (string) ($key->Key_name ?? $key->key_name ?? $key->name ?? $keyName)
                    : (string) $keyName;
                if (strcasecmp($name, 'idx_asset_id') === 0) {
                    return;
                }
            }

            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__jem_venues')
                . ' ADD INDEX ' . $db->quoteName('idx_asset_id')
                . ' (' . $db->quoteName('asset_id') . ')'
            )->execute();
        } finally {
            if ($repairSqlMode !== $originalSqlMode) {
                $db->setQuery('SET SESSION sql_mode = ' . $db->quote($originalSqlMode))->execute();
            }
        }
    }

    /**
     * Repair every venue asset without changing existing rules.
     *
     * @return array Venue ids which could not be repaired.
     */
    public static function repair($db = null)
    {
        $db = $db ?: Factory::getContainer()->get('DatabaseDriver');
        require_once __DIR__ . '/aclassetmigration.class.php';

        return (new JemAclAssetMigration($db))->repair('venue');
    }
}
