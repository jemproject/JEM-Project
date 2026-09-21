<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

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
        $table = $db->replacePrefix('#__jem_venues');

        if (!in_array($table, $db->getTableList(), true)) {
            return array();
        }

        $columns = array_change_key_case($db->getTableColumns($table, false), CASE_LOWER);
        if (!isset($columns['asset_id'])) {
            return array();
        }

        $componentAsset = Table::getInstance('Asset');
        if (!$componentAsset->loadByName('com_jem')) {
            return array();
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'venue', 'asset_id')))
                ->from($db->quoteName('#__jem_venues'))
                ->order($db->quoteName('id') . ' ASC')
        );
        $failures = array();

        foreach ((array) $db->loadObjectList() as $venue) {
            $asset = Table::getInstance('Asset');
            $assetName = 'com_jem.venue.' . (int) $venue->id;
            $exists = $asset->loadByName($assetName);

            if (!$exists) {
                $asset->name = $assetName;
                $asset->rules = '{}';
            }

            $asset->title = (string) $venue->venue;
            if (!$exists || (int) $asset->parent_id !== (int) $componentAsset->id) {
                $asset->setLocation((int) $componentAsset->id, 'last-child');
            }

            if (!$asset->check() || !$asset->store()) {
                $failures[] = (int) $venue->id;
                continue;
            }

            if ((int) $venue->asset_id !== (int) $asset->id) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__jem_venues'))
                        ->set($db->quoteName('asset_id') . ' = ' . (int) $asset->id)
                        ->where($db->quoteName('id') . ' = ' . (int) $venue->id)
                )->execute();
            }
        }

        Access::clearStatics();

        return $failures;
    }
}
