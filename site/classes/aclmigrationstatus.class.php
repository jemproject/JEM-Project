<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

final class JemAclMigrationStatus
{
    private static ?array $state = null;

    public static function reset(): void
    {
        self::$state = null;
    }

    public static function state(): array
    {
        if (self::$state === null) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $value = $db->setQuery('SELECT value FROM #__jem_config WHERE keyname = '
                . $db->quote('acl_migration_state'))->loadResult();
            $decoded = json_decode((string) $value, true);
            self::$state = $value === null ? ['complete' => true] : (is_array($decoded) ? $decoded : ['complete' => false]);
        }
        return self::$state;
    }

    public static function pending(): bool
    {
        return empty(self::state()['complete']);
    }
}
