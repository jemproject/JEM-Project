<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** Additive, repeatable 5.1 migration; no invitation table or fabricated Joomla users. */
final class JemInvitationSchema
{
    /** @return array<string,string> Registration column definitions shared by installer checks. */
    public static function columns(): array
    {
        return [
            'guest_email' => 'VARCHAR(254) NULL DEFAULT NULL',
            'guest_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'invitation_key' => 'CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
            'invitation_scope' => "VARCHAR(8) NOT NULL DEFAULT 'single'",
            'invitation_root' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'invitation_kind' => "VARCHAR(8) NOT NULL DEFAULT ''",
            'invitation_quantity' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'invitation_free' => 'TINYINT NOT NULL DEFAULT 0',
            'invitation_message' => 'TEXT NULL DEFAULT NULL',
            'invitation_expires' => 'DATETIME NULL DEFAULT NULL',
            'invitation_token_hash' => 'CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL',
            'invitation_revoked' => 'TINYINT NOT NULL DEFAULT 0',
            'invitation_actor' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'invitation_terms' => 'MEDIUMTEXT NULL DEFAULT NULL',
            'reserved_assigned' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'reserved_confirmed' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        ];
    }

    /** @param object $db Joomla database. @return void Bring earlier 5.1 betas to the same schema. */
    public static function ensure(object $db): void
    {
        $columns = $db->getTableColumns('#__jem_register', false);
        foreach (self::columns() as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->setQuery('ALTER TABLE #__jem_register ADD ' . $db->quoteName($name) . ' ' . $definition)->execute();
            }
        }
        // NULL permits multiple external holders while retaining UNIQUE(uid,event) for Joomla holders.
        if (($columns['uid']->Null ?? '') !== 'YES') {
            $db->setQuery('ALTER TABLE #__jem_register MODIFY uid INT(11) NULL DEFAULT NULL')->execute();
        }
        $keys = [];
        foreach ($db->getTableKeys('#__jem_register') as $key) {
            $keys[$key->Key_name] = true;
        }
        foreach ([
            'idx_register_guest' => 'UNIQUE KEY idx_register_guest (event,guest_email)',
            'idx_invitation_key' => 'KEY idx_invitation_key (invitation_key)',
            'idx_invitation_token' => 'KEY idx_invitation_token (invitation_token_hash)',
        ] as $name => $definition) {
            if (!isset($keys[$name])) {
                $db->setQuery('ALTER TABLE #__jem_register ADD ' . $definition)->execute();
            }
        }
        if (!isset($db->getTableColumns('#__jem_register_history')['invitation_snapshot'])) {
            $db->setQuery('ALTER TABLE #__jem_register_history ADD invitation_snapshot TEXT NULL DEFAULT NULL')->execute();
        }
    }
}
