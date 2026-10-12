<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Creates and repairs Joomla ACL assets for JEM's private category tree.
 */
final class JemCategoryAsset
{
    /**
     * Add the category asset column and index on clean or upgraded schemas.
     *
     * Some older JEM tables still declare zero-date defaults. MariaDB refuses
     * an otherwise unrelated ALTER while NO_ZERO_DATE is active, so those two
     * session flags are removed only for this repair and restored afterwards.
     */
    public static function ensureSchema($db = null)
    {
        $db = $db ?: Factory::getContainer()->get('DatabaseDriver');
        $categoryTable = $db->replacePrefix('#__jem_categories');

        if (!in_array($categoryTable, $db->getTableList(), true)) {
            return;
        }

        $originalSqlMode = (string) $db->setQuery('SELECT @@SESSION.sql_mode')->loadResult();
        $sqlModes = array_values(array_filter(array_map('trim', explode(',', $originalSqlMode))));
        $repairSqlModes = array_values(array_diff($sqlModes, array('NO_ZERO_DATE', 'NO_ZERO_IN_DATE')));
        $repairSqlMode = implode(',', $repairSqlModes);

        if ($repairSqlMode !== $originalSqlMode) {
            $db->setQuery('SET SESSION sql_mode = ' . $db->quote($repairSqlMode))->execute();
        }

        try {
            $columns = array_change_key_case($db->getTableColumns($categoryTable, false), CASE_LOWER);

            if (!isset($columns['asset_id'])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_categories')
                    . ' ADD COLUMN ' . $db->quoteName('asset_id')
                    . " INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER " . $db->quoteName('id')
                )->execute();
            }

            $hasAssetIndex = false;

            foreach ((array) $db->getTableKeys($categoryTable) as $keyName => $key) {
                $resolvedName = is_string($keyName) ? $keyName : '';

                if (is_object($key)) {
                    $resolvedName = (string) ($key->Key_name ?? $key->key_name ?? $key->name ?? $resolvedName);
                }

                if (strcasecmp($resolvedName, 'idx_asset_id') === 0) {
                    $hasAssetIndex = true;
                    break;
                }
            }

            if (!$hasAssetIndex) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_categories')
                    . ' ADD INDEX ' . $db->quoteName('idx_asset_id')
                    . ' (' . $db->quoteName('asset_id') . ')'
                )->execute();
            }
        } finally {
            if ($repairSqlMode !== $originalSqlMode) {
                $db->setQuery('SET SESSION sql_mode = ' . $db->quote($originalSqlMode))->execute();
            }
        }
    }

    /**
     * Repair every category asset without changing its existing rules.
     *
     * @return array Category ids which could not be repaired.
     */
    public static function repair($db = null)
    {
        $db = $db ?: Factory::getContainer()->get('DatabaseDriver');
        require_once __DIR__ . '/aclassetmigration.class.php';

        return (new JemAclAssetMigration($db))->repair('category');
    }
}
