<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/categoryasset.class.php';
require_once __DIR__ . '/venueasset.class.php';
require_once __DIR__ . '/aclassetmigration.class.php';

/** One shared runner for the installer and the authorised recovery action. */
final class JemAclMigration
{
    public static function run($db, float $seconds = 8.0): array
    {
        // Serialize runners, including settings/status writes between committed batches.
        $databaseName = (string) $db->setQuery('SELECT DATABASE()')->loadResult();
        $lockName = 'jem.acl.' . substr(hash('sha256', $databaseName . $db->getPrefix()), 0, 40);
        if (!(int) $db->setQuery('SELECT GET_LOCK(' . $db->quote($lockName) . ', 0)')->loadResult()) {
            return ['complete' => false, 'remaining' => null, 'error' => 'Another ACL migration is running. Please retry shortly.'];
        }
        $result = ['complete' => false, 'remaining' => null, 'error' => ''];
        try {
            self::writeState($db, $result);
            JemCategoryAsset::ensureSchema($db);
            JemVenueAsset::ensureSchema($db);
            $runner = new JemAclAssetMigration($db, $seconds);
            $categories = $runner->repair('category');
            $venues = $runner->repair('venue');
            $result['remaining'] = count($categories) + count($venues);
            $result['complete'] = $result['remaining'] === 0;
        } catch (Throwable $error) {
            $result['error'] = $error->getMessage();
        } finally {
            try {
                self::writeState($db, $result);
            } finally {
                $db->setQuery('SELECT RELEASE_LOCK(' . $db->quote($lockName) . ')')->loadResult();
            }
        }
        return $result;
    }

    private static function writeState($db, array $result): void
    {
        $state = json_encode($result, JSON_THROW_ON_ERROR);
        $db->setQuery('INSERT INTO #__jem_config (keyname,value) VALUES ('
            . $db->quote('acl_migration_state') . ',' . $db->quote($state)
            . ') ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute();
        if (class_exists('JemAclMigrationStatus', false)) {
            JemAclMigrationStatus::reset();
        }
    }
}
