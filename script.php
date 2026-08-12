<?php
/**
 * @version    5.0.0
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Access\Rules;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\CMS\Version;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;


/**
 * Script file of JEM component
 */
class com_jemInstallerScript
{
    private $oldRelease = "";
    private $newRelease = "";
    private $useJemConfig = false; // set to true if we moved values from settings to config table

    /**
     * Method to install the component
     *
     * @return void
     */
    public function install($parent)
    {
        $this->loadInstallerLanguage();

        $error = array(
            'summary' => 0,
            'folders' => 0
        );

        $this->useJemConfig = true;

        $this->getHeader();
        ?>

        <h2><?php
            echo Text::_('COM_JEM_INSTALL_STATUS'); ?>:</h2>
        <h3><?php
            echo Text::_('COM_JEM_INSTALL_CHECK_FOLDERS'); ?>:</h3>

        <?php
        $imageDir = "/images/jem";
        $createDirs = array(
            $imageDir,
            $imageDir . '/categories',
            $imageDir . '/categories/small',
            $imageDir . '/events',
            $imageDir . '/events/small',
            $imageDir . '/links',
            $imageDir . '/links/small',
            $imageDir . '/venues',
            $imageDir . '/venues/small'
        );

        // Check for existance of /images/jem directory
        if (is_dir(JPATH_SITE . $createDirs[0])) {
            echo "<p><span style='color:green;'>" . Text::_('COM_JEM_INSTALL_SUCCESS') . ":</span> " .
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_EXISTS_SKIP', $createDirs[0]) . "</p>";
        } else {
            echo "<p><span style='color:orange;'>" . Text::_('COM_JEM_INSTALL_INFO') . ":</span> " .
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_EXISTS', $createDirs[0]) . "</p>";
            echo "<p>" . Text::_('COM_JEM_INSTALL_DIRECTORY_TRY_CREATE') . ":</p>";

            echo "<ul>";
            // Folder creation
            foreach ($createDirs as $directory) {
                if (Folder::create(JPATH_SITE . $directory)) {
                    echo "<li><span style='color:green;'>" . Text::_('COM_JEM_INSTALL_SUCCESS') . ":</span> " .
                        Text::sprintf('COM_JEM_INSTALL_DIRECTORY_CREATED', $directory) . "</li>";
                } else {
                    echo "<li><span style='color:red;'>" . Text::_('COM_JEM_INSTALL_ERROR') . ":</span> " .
                        Text::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_CREATED', $directory) . "</li>";
                    $error['folders']++;
                }
            }
            echo "</ul>";
        }

        if ($error['folders']) {
            echo "<p>" . Text::_('COM_JEM_INSTALL_DIRECTORY_CHECK_EXISTANCE') . "</p>";
        }

        echo "<h3>" . Text::_('COM_JEM_INSTALL_SETTINGS') . "</h3>";

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*')->from('#__jem_config');
        $db->setQuery($query);
        $conf = $db->loadAssocList();

        if (count($conf)) {
            echo "<p><span style='color:green;'>" . Text::_('COM_JEM_INSTALL_SUCCESS') . ":</span> " .
                Text::_('COM_JEM_INSTALL_FOUND_SETTINGS') . "</p>";
        }

        echo "<h3>" . Text::_('COM_JEM_INSTALL_SUMMARY') . "</h3>";

        foreach ($error as $k => $v) {
            if ($k != 'summary') {
                $error['summary'] += $v;
            }
        }

        if ($error['summary']) {
            ?>
            <p style='color: red;'>
                <b><?php echo Text::_('COM_JEM_INSTALL_INSTALLATION_NOT_SUCCESSFUL'); ?></b>
            </p>
            <?php
        } else {
            ?>
            <p style='color: green;'>
                <b><?php echo Text::_('COM_JEM_INSTALL_INSTALLATION_SUCCESSFUL'); ?></b>
            </p> <?php
        }

        $param_array = array(
            "event_comunoption" => "0",
            "event_comunsolution" => "0",
            "event_show_attendeenames" => "2",
            "event_show_more_attendeedetails" => "0",
            "event_show_venue_name" => "1",
            "event_show_category" => "1",
            "event_link_category" => "1",
            "event_show_author" => "1",
            "event_lg" => "",
            "event_link_author" => "1",
            "event_show_contact" => "1",
            "event_link_contact" => "1",
            "event_show_description" => "1",
            "event_show_detailsadress" => "1",
            "event_show_detailstitle" => "1",
            "event_show_detlinkvenue" => "1",
            "event_show_hits" => "0",
            "event_show_publish_state" => "0",
            "event_show_locdescription" => "1",
            "event_show_mapserv" => "0",
            "event_show_print_icon" => "1",
            "event_show_email_icon" => "1",
            "event_show_ical_icon" => "1",
            "event_tld" => "",
            "editevent_show_meta_option" => "0",
            "editevent_show_attachment_tab" => "0",
            "editevent_show_other_tab" => "0",
            "global_display" => "1",
            "global_editevent_starttime_limit" => "0",
            "global_editevent_endtime_limit" => "23",
            "global_editevent_minutes_block" => "1",
            "global_regname" => "1",
            "global_show_archive_icon" => "1",
            "global_show_filter" => "1",
            "global_show_email_icon" => "1",
            "global_show_ical_icon" => "1",
            "global_show_icons" => "1",
            "global_show_locdescription" => "1",
            "global_show_print_icon" => "1",
            "global_show_timedetails" => "1",
            "global_show_detailsadress" => "1",
            "global_show_detlinkvenue" => "1",
            "global_show_listevents" => "1",
            "global_show_mapserv" => "0",
            "global_tld" => "",
            "global_lg" => "",
            "global_cleanup_db_on_uninstall" => "0"
        );

        $this->setGlobalAttribs($param_array);
    }

    /**
     * method to uninstall the component
     *
     * @return void
     */
    function uninstall($parent)
    {
        $this->loadInstallerLanguage();

        $this->getHeader(); ?>
        <h2><?php echo Text::_('COM_JEM_UNINSTALL_STATUS'); ?>:</h2>
        <p><?php echo Text::_('COM_JEM_UNINSTALL_TEXT'); ?></p>
        <?php

        $this->useJemConfig = true; 
        $globalParams = $this->getGlobalParams();
        $cleanup = $globalParams->get('global_cleanup_db_on_uninstall', 0);
        if (!empty($cleanup)) {
            // user decided to fully remove JEM - so do it!
            $this->removeJemMenuItems();
            $this->removeAllJemTables();
            $imageDir = JPATH_SITE . '/images/jem';
            if (is_dir($imageDir)) {
                Folder::delete($imageDir);
            }
        } else {
            // prevent dead links on frontend
            $this->disableJemMenuItems();
        }
    }

    /**
     * method to update the component
     *
     * @return void
     */
    function update($parent)
    {
        $this->loadInstallerLanguage();

        $this->getHeader(); ?>
        <h2><?php echo Text::_('COM_JEM_UPDATE_STATUS'); ?>:</h2>
        <p><?php echo Text::sprintf('COM_JEM_UPDATE_TEXT', $parent->getManifest()->version); ?></p>;
        <?php
    }

    /**
     * method to run before an install/update/uninstall method
     * (it seams method is not called on uninstall)
     *
     * @return void
     */
    public function preflight($type, $parent)
    {
        $this->loadInstallerLanguage();

        $app = Factory::getApplication();
        
        // Verify that we are in Joomla 5.4 or Joomla 6.
        if (version_compare(JVERSION, '5.4.0', 'lt') || version_compare(JVERSION, '7.0.0', 'ge')) {
            $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_WRONG_JOOMLA_VERSION', '5.4.0 / 6.x', JVERSION), 'error');
            return false;
        }

        // Minimum required PHP version
        $minPhpVersion = "8.3.0";

        // Abort if PHP release is older than required version
        if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
            $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_WRONG_PHP_VERSION', $minPhpVersion, PHP_VERSION), 'error');
            return false;
        }

        $this->newRelease = (string)$parent->getManifest()->version;

        // abort if the release being installed is not newer than the currently installed version
        if (strtolower($type) == 'update') {
            // Installed component version
            $this->oldRelease = $this->getParam('version');

            $minUpgradeVersion = '4.4.0';

            if ($this->oldRelease !== '' && version_compare($this->oldRelease, $minUpgradeVersion, 'lt')) {
                $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_UNSUPPORTED_UPGRADE_VERSION', $minUpgradeVersion, $this->oldRelease), 'error');
                return false;
            }
            
            if ($this->oldRelease !== '' && version_compare($this->newRelease, $this->oldRelease, 'lt')) {
                $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_INCORRECT_VERSION_SEQUENCE', $this->oldRelease, $this->newRelease), 'error');
                return false;
            }

            if ($this->oldRelease !== '') {
                // Check and remove obsolete files and folder
                $this->deleteObsoleteFiles();
                $this->deleteObsoleteUpdateSqlFiles();

                // Check columns in database
                $this->checkColumnsIntoDatabase();

                // Ensure css files are (over)writable
                $this->makeFilesWritable();

                // Initialize schema table if necessary
                $this->initializeSchema($this->oldRelease);
            }
        }

        // $type is the type of change (install, update or discover_install)
        echo '<p>' . Text::_('COM_JEM_PREFLIGHT_' . strtoupper($type) . '_TEXT') . '</p>';
    }

    /**
     * Method to run after an install/update/uninstall method
     * (it seams method is not called on uninstall)
     *
     * @return void
     */
    function postflight($type, $parent)
    {
        $this->loadInstallerLanguage();

        // $type is the type of change (install, update or discover_install)
        echo '<p>' . Text::_('COM_JEM_POSTFLIGHT_' . strtoupper($type) . '_TEXT') . '</p>';

        $type = strtolower($type);

        if ($type == 'install') {
            $this->fixJemMenuItems();
        }

        if (in_array($type, array('install', 'update', 'discover_install'), true)) {
            $this->removeObsoleteAdminHelpMenuItem();
            $this->repairGeneratedTypeMenuItems();
            $this->repair501SchemaFallback();
            $this->repair510RegistrationSchema();
            $this->repair510NotificationSchema();
            $this->repair510PricingSchema();
            $this->installCountryCurrencyCatalogue();
            $this->installEuropeanTaxRateCatalogue();
            $this->registerNotificationTemplates();
            $this->rebuildEventUtcDates();
            $this->migrateBackendAcl($type === 'update');
        }
    }

    /**
     * Register Point 2A master templates in Joomla's native mail-template table.
     *
     * Language-specific rows are owned by the administrator and are never
     * changed by this idempotent install/update step.
     */
    private function registerNotificationTemplates()
    {
        try {
            require_once JPATH_SITE . '/components/com_jem/classes/notificationtemplatecatalog.class.php';
            require_once JPATH_SITE . '/components/com_jem/classes/notificationtemplaterenderer.class.php';
            require_once JPATH_SITE . '/components/com_jem/classes/notificationtemplateservice.class.php';

            JemNotificationTemplateService::registerDefaults();
        } catch (Throwable $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_INSTALL_NOTIFICATION_TEMPLATES_FAILED', $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Ensure the Point 2B notification journal is available after every
     * install/update. The migration is additive and never rewrites legacy JEM
     * registration data or existing notification snapshots.
     */
    private function repair510NotificationSchema()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $legacyAttemptsTable = $db->replacePrefix('#__jem_notification_attempts');
            $attemptsTable = $db->replacePrefix('#__jem_notifications_attempts');
            $tableList = (array) $db->getTableList();
            $hasLegacyAttemptsTable = in_array($legacyAttemptsTable, $tableList, true);
            $hasAttemptsTable = in_array($attemptsTable, $tableList, true);

            if ($hasLegacyAttemptsTable && !$hasAttemptsTable) {
                $db->setQuery(
                    'RENAME TABLE ' . $db->quoteName($legacyAttemptsTable)
                    . ' TO ' . $db->quoteName($attemptsTable)
                )->execute();
            } elseif ($hasLegacyAttemptsTable && $hasAttemptsTable) {
                $attemptColumns = array(
                    'id', 'notification_id', 'attempt_number', 'transport', 'source',
                    'requested_by_user_id', 'started_at', 'finished_at', 'result',
                    'transport_message_id', 'error_code', 'error_message',
                );
                $attemptColumnList = implode(', ', array_map(array($db, 'quoteName'), $attemptColumns));
                $db->setQuery(
                    'INSERT IGNORE INTO ' . $db->quoteName($attemptsTable) . ' (' . $attemptColumnList . ')'
                    . ' SELECT ' . $attemptColumnList . ' FROM ' . $db->quoteName($legacyAttemptsTable)
                )->execute();

                $equalColumns = implode(' AND ', array_map(
                    static function ($column) use ($db) {
                        return 'new.' . $db->quoteName($column) . ' <=> old.' . $db->quoteName($column);
                    },
                    $attemptColumns
                ));
                $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName($legacyAttemptsTable));
                $legacyAttemptCount = (int) $db->loadResult();
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName($legacyAttemptsTable, 'old'))
                    ->join('INNER', $db->quoteName($attemptsTable, 'new') . ' ON ' . $equalColumns);
                $db->setQuery($query);
                if ((int) $db->loadResult() !== $legacyAttemptCount) {
                    throw new RuntimeException('The legacy notification attempts could not be merged without changing audit data.');
                }

                $db->setQuery('DROP TABLE ' . $db->quoteName($legacyAttemptsTable))->execute();
            }

            $notifications = <<<'SQL'
CREATE TABLE IF NOT EXISTS `#__jem_notifications` (
`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
`notification_uuid` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
`registration_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
`registration_reference` VARCHAR(28) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
`registration_revision` INT(10) UNSIGNED NOT NULL DEFAULT '0',
`event_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
`reminder_definition_id` INT(11) UNSIGNED NULL DEFAULT NULL,
`ticket_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Reserved for a future individual ticket/QR phase',
`notification_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
`recipient_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'user',
`recipient_user_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
`recipient_name` VARCHAR(255) NOT NULL DEFAULT '',
`recipient_email` VARCHAR(320) NOT NULL,
`requested_language` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
`resolved_language` VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'en-GB',
`language_source` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'fallback',
`fallback_reason` VARCHAR(255) NOT NULL DEFAULT '',
`template_id` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
`subject` TEXT NOT NULL,
`body` MEDIUMTEXT NOT NULL,
`htmlbody` MEDIUMTEXT NULL DEFAULT NULL,
`payload_json` MEDIUMTEXT NULL DEFAULT NULL,
`attachments_json` MEDIUMTEXT NULL DEFAULT NULL COMMENT 'Reserved for a future individual ticket/PDF phase',
`content_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
`state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
`scheduled_at` DATETIME NULL DEFAULT NULL,
`queued_at` DATETIME NULL DEFAULT NULL,
`processing_started_at` DATETIME NULL DEFAULT NULL,
`sent_at` DATETIME NULL DEFAULT NULL,
`failed_at` DATETIME NULL DEFAULT NULL,
`cancelled_at` DATETIME NULL DEFAULT NULL,
`next_attempt_at` DATETIME NULL DEFAULT NULL,
`attempt_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
`max_attempts` INT(10) UNSIGNED NOT NULL DEFAULT '4',
`idempotency_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
`resend_of` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
`source` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
`created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0',
`created` DATETIME NOT NULL,
`modified` DATETIME NOT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `idx_notification_uuid` (`notification_uuid`),
UNIQUE KEY `idx_notification_idempotency` (`idempotency_key`),
KEY `idx_notification_registration_created` (`registration_id`,`created`),
KEY `idx_notification_reference_created` (`registration_reference`,`created`),
KEY `idx_notification_event_created` (`event_id`,`created`),
KEY `idx_notification_reminder_schedule` (`reminder_definition_id`,`scheduled_at`),
KEY `idx_notification_recipient_created` (`recipient_user_id`,`created`),
KEY `idx_notification_email_created` (`recipient_email`(191),`created`),
KEY `idx_notification_state_attempt` (`state`,`next_attempt_at`),
KEY `idx_notification_resend_of` (`resend_of`),
KEY `idx_notification_created` (`created`)
) ENGINE=InnoDB
SQL;
            $attempts = <<<'SQL'
CREATE TABLE IF NOT EXISTS `#__jem_notifications_attempts` (
`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
`notification_id` BIGINT(20) UNSIGNED NOT NULL,
`attempt_number` INT(10) UNSIGNED NOT NULL,
`transport` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'mail',
`source` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'automatic',
`requested_by_user_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
`started_at` DATETIME NOT NULL,
`finished_at` DATETIME NULL DEFAULT NULL,
`result` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'processing',
`transport_message_id` VARCHAR(255) NOT NULL DEFAULT '',
`error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
`error_message` VARCHAR(1000) NOT NULL DEFAULT '',
PRIMARY KEY (`id`),
UNIQUE KEY `idx_attempt_notification_number` (`notification_id`,`attempt_number`),
KEY `idx_attempt_notification_started` (`notification_id`,`started_at`),
KEY `idx_attempt_result_started` (`result`,`started_at`)
) ENGINE=InnoDB
SQL;
            $reminders = <<<'SQL'
CREATE TABLE IF NOT EXISTS `#__jem_reminders` (
`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`event_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
`source_id` INT(11) UNSIGNED NULL DEFAULT NULL,
`code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
`title` VARCHAR(255) NOT NULL DEFAULT '',
`minutes` INT(10) UNSIGNED NOT NULL DEFAULT '1440',
`published` TINYINT(1) NOT NULL DEFAULT '1',
`default_new_event` TINYINT(1) NOT NULL DEFAULT '0',
`ordering` INT(11) NOT NULL DEFAULT '0',
`created` DATETIME NOT NULL,
`modified` DATETIME NOT NULL,
`created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
UNIQUE KEY `idx_reminder_event_code` (`event_id`,`code`),
KEY `idx_reminder_event_published_ordering` (`event_id`,`published`,`ordering`),
UNIQUE KEY `idx_reminder_source_event` (`source_id`,`event_id`)
) ENGINE=InnoDB
SQL;

            foreach (array($notifications, $attempts, $reminders) as $sql) {
                $db->setQuery($db->replacePrefix($sql));
                $db->execute();
            }

            $notificationTable = $db->replacePrefix('#__jem_notifications');
            $notificationColumns = array_change_key_case($db->getTableColumns($notificationTable, false), CASE_LOWER);
            if (!isset($notificationColumns['registration_revision'])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_notifications')
                    . ' ADD COLUMN ' . $db->quoteName('registration_revision')
                    . " INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER " . $db->quoteName('registration_reference')
                );
                $db->execute();
            }
            if (!isset($notificationColumns['reminder_definition_id'])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_notifications')
                    . ' ADD COLUMN ' . $db->quoteName('reminder_definition_id')
                    . ' INT(11) UNSIGNED NULL DEFAULT NULL AFTER ' . $db->quoteName('event_id')
                );
                $db->execute();
            }

            $hasReminderScheduleIndex = false;
            foreach ((array) $db->getTableKeys($notificationTable) as $notificationKey) {
                $keyName = is_object($notificationKey)
                    ? (string) ($notificationKey->Key_name ?? $notificationKey->key_name ?? '')
                    : '';
                if (strcasecmp($keyName, 'idx_notification_reminder_schedule') === 0) {
                    $hasReminderScheduleIndex = true;
                    break;
                }
            }
            if (!$hasReminderScheduleIndex) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_notifications')
                    . ' ADD INDEX ' . $db->quoteName('idx_notification_reminder_schedule')
                    . ' (' . $db->quoteName('reminder_definition_id') . ', ' . $db->quoteName('scheduled_at') . ')'
                );
                $db->execute();
            }

            $now = Factory::getDate()->toSql();
            $reminderDefaults = array(
                array('default_7_days', 'COM_JEM_REMINDER_DEFAULT_7_DAYS', 10080, 1),
                array('default_24_hours', 'COM_JEM_REMINDER_DEFAULT_24_HOURS', 1440, 2),
                array('default_2_hours', 'COM_JEM_REMINDER_DEFAULT_2_HOURS', 120, 3),
            );
            foreach ($reminderDefaults as $definition) {
                $row = (object) array(
                    'event_id' => 0,
                    'source_id' => null,
                    'code' => $definition[0],
                    'title' => $definition[1],
                    'minutes' => $definition[2],
                    'published' => 1,
                    'default_new_event' => 1,
                    'ordering' => $definition[3],
                    'created' => $now,
                    'modified' => $now,
                    'created_by' => 0,
                );
                try {
                    $db->insertObject('#__jem_reminders', $row);
                } catch (Throwable $ignored) {
                    // The unique code makes this migration idempotent.
                }
            }

            $defaults = array(
                'notification_retention_days' => '0',
                'notification_user_resend_limit' => '2',
                'notification_user_resend_window_hours' => '24',
                'notification_user_resend_cooldown_minutes' => '10',
                'notification_max_attempts' => '4',
                'reminders_enabled' => '0',
                'reminder_all_day_time' => '09:00',
                'notification_retry_delays_minutes' => '10,30,120',
                'notification_schema_ready' => '1',
            );
            foreach ($defaults as $key => $value) {
                $query = 'INSERT IGNORE INTO ' . $db->quoteName('#__jem_config')
                    . ' (' . $db->quoteName('keyname') . ', ' . $db->quoteName('value') . ') VALUES ('
                    . $db->quote($key) . ', ' . $db->quote($value) . ')';
                $db->setQuery($query);
                $db->execute();
            }
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_config'))
                ->set($db->quoteName('value') . ' = ' . $db->quote('1'))
                ->where($db->quoteName('keyname') . ' = ' . $db->quote('notification_schema_ready'));
            $db->setQuery($query);
            $db->execute();
        } catch (Throwable $e) {
            try {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__jem_config'))
                    ->set($db->quoteName('value') . ' = ' . $db->quote('0'))
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('notification_schema_ready'));
                $db->setQuery($query);
                $db->execute();
            } catch (Throwable $ignored) {
            }
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_INSTALL_NOTIFICATION_SCHEMA_FAILED', $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Ensure the additive Point 4B pricing schema exists without enabling it.
     *
     * This repair is required for JEM 5.1.0 beta installations where Joomla has
     * already recorded 5.1.0.sql. It creates only missing structures, leaves all
     * existing events in classic mode and never invents monetary registration
     * snapshots.
     *
     * @return void
     */
    private function repair510PricingSchema()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $this->setPricingSchemaReady($db, false);
            $definitionsByTable = array(
                '#__jem_events' => array(
                    'pricing_mode'                  => "VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'classic' AFTER `type_id`",
                    'pricing_revision'              => "INT(10) UNSIGNED NOT NULL DEFAULT '1' AFTER `pricing_mode`",
                    'currency'                      => "CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' AFTER `pricing_revision`",
                    'default_tax_rate_id'           => "INT(11) UNSIGNED NULL DEFAULT NULL AFTER `currency`",
                    'prices_include_tax'            => "TINYINT(1) NOT NULL DEFAULT '1' AFTER `default_tax_rate_id`",
                    'management_fee_mode'           => "VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'fixed_per_ticket' AFTER `prices_include_tax`",
                    'management_fee_value'          => "DECIMAL(15,2) NOT NULL DEFAULT '0.00' AFTER `management_fee_mode`",
                    'management_fee_basis'          => "VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'gross' AFTER `management_fee_value`",
                    'management_fee_tax_rate_id'    => "INT(11) UNSIGNED NULL DEFAULT NULL AFTER `management_fee_basis`",
                    'management_fee_refundable'     => "TINYINT(1) NOT NULL DEFAULT '0' AFTER `management_fee_tax_rate_id`",
                ),
                '#__jem_register' => array(
                    'pricing_mode'               => "VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'classic' AFTER `revision`",
                    'currency'                   => "CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' AFTER `pricing_mode`",
                    'subtotal_net'               => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `currency`",
                    'discount_total'             => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `subtotal_net`",
                    'tax_total'                  => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `discount_total`",
                    'management_fee_net'         => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `tax_total`",
                    'management_fee_tax'         => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `management_fee_net`",
                    'management_fee_gross'       => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `management_fee_tax`",
                    'grand_total'                => "DECIMAL(15,2) NULL DEFAULT NULL AFTER `management_fee_gross`",
                    'payment_state'              => "VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER `grand_total`",
                    'price_locked_at'            => "DATETIME NULL DEFAULT NULL AFTER `payment_state`",
                    'external_payment_reference' => "VARCHAR(255) NULL DEFAULT NULL AFTER `price_locked_at`",
                ),
                '#__jem_countries' => array(
                    'currency' => "CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' AFTER `name`",
                ),
            );
            $tableList = (array) $db->getTableList();

            foreach ($definitionsByTable as $table => $definitions) {
                $resolvedTable = $db->replacePrefix($table);
                if (!in_array($resolvedTable, $tableList, true)) {
                    throw new RuntimeException('Required parent table is missing: ' . $table);
                }
                $columns = array_change_key_case($db->getTableColumns($resolvedTable, false), CASE_LOWER);
                foreach ($definitions as $column => $definition) {
                    if (!isset($columns[$column])) {
                        $db->setQuery(
                            'ALTER TABLE ' . $db->quoteName($table)
                            . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
                        )->execute();
                    }
                }
            }

            $statements = array(
                "CREATE TABLE IF NOT EXISTS `#__jem_tax_rates` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `name` VARCHAR(255) NOT NULL DEFAULT '', `tax_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `rate` DECIMAL(7,2) NOT NULL DEFAULT '0.00', `country_code` CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', `region_code` VARCHAR(100) NOT NULL DEFAULT '', `description` TEXT NULL DEFAULT NULL, `valid_from` DATE NULL DEFAULT NULL, `valid_until` DATE NULL DEFAULT NULL, `published` TINYINT(1) NOT NULL DEFAULT '1', `ordering` INT(11) NOT NULL DEFAULT '0', `checked_out` INT(11) UNSIGNED NULL DEFAULT NULL, `checked_out_time` DATETIME NULL DEFAULT NULL, `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', `modified` DATETIME NULL DEFAULT NULL, `modified_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `idx_tax_rate_code` (`code`), KEY `idx_tax_rate_published_ordering` (`published`,`ordering`), KEY `idx_tax_rate_country` (`country_code`,`published`,`valid_from`,`valid_until`), KEY `idx_tax_rate_validity` (`valid_from`,`valid_until`)) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `#__jem_capacity_pools` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `event_id` INT(11) UNSIGNED NOT NULL, `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `name` VARCHAR(255) NOT NULL DEFAULT '', `description` TEXT NULL DEFAULT NULL, `capacity` INT(10) UNSIGNED NOT NULL DEFAULT '0', `published` TINYINT(1) NOT NULL DEFAULT '1', `ordering` INT(11) NOT NULL DEFAULT '0', `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', `modified` DATETIME NULL DEFAULT NULL, `modified_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `idx_capacity_pool_event_code` (`event_id`,`code`), KEY `idx_capacity_pool_event_published` (`event_id`,`published`,`ordering`)) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `#__jem_event_prices` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `event_id` INT(11) UNSIGNED NOT NULL, `capacity_pool_id` INT(11) UNSIGNED NULL DEFAULT NULL, `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `name` VARCHAR(255) NOT NULL DEFAULT '', `description` TEXT NULL DEFAULT NULL, `amount` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `tax_rate_id` INT(11) UNSIGNED NULL DEFAULT NULL, `quota` INT(10) UNSIGNED NULL DEFAULT NULL, `min_quantity` INT(10) UNSIGNED NOT NULL DEFAULT '0', `max_quantity` INT(10) UNSIGNED NULL DEFAULT NULL, `available_from` DATETIME NULL DEFAULT NULL, `available_until` DATETIME NULL DEFAULT NULL, `min_age` TINYINT(3) UNSIGNED NULL DEFAULT NULL, `max_age` TINYINT(3) UNSIGNED NULL DEFAULT NULL, `access_level_id` INT(10) UNSIGNED NULL DEFAULT NULL, `user_group_id` INT(10) UNSIGNED NULL DEFAULT NULL, `verification_mode` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'none', `published` TINYINT(1) NOT NULL DEFAULT '1', `ordering` INT(11) NOT NULL DEFAULT '0', `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `created_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', `modified` DATETIME NULL DEFAULT NULL, `modified_by` INT(11) UNSIGNED NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `idx_event_price_event_code` (`event_id`,`code`), KEY `idx_event_price_event_published` (`event_id`,`published`,`ordering`), KEY `idx_event_price_pool` (`capacity_pool_id`), KEY `idx_event_price_tax` (`tax_rate_id`), KEY `idx_event_price_availability` (`available_from`,`available_until`)) ENGINE=InnoDB",
                "CREATE TABLE IF NOT EXISTS `#__jem_register_items` (`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, `register_id` INT(11) UNSIGNED NOT NULL, `registration_revision` INT(10) UNSIGNED NOT NULL, `line_number` INT(10) UNSIGNED NOT NULL, `line_kind` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `event_price_id` INT(11) UNSIGNED NULL DEFAULT NULL, `capacity_pool_id` INT(11) UNSIGNED NULL DEFAULT NULL, `item_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', `item_name` VARCHAR(255) NOT NULL DEFAULT '', `item_description` TEXT NULL DEFAULT NULL, `quantity` INT(10) UNSIGNED NOT NULL DEFAULT '1', `currency` CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `price_includes_tax` TINYINT(1) NOT NULL DEFAULT '1', `unit_net` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `unit_tax` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `unit_gross` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `line_net` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `line_tax` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `line_gross` DECIMAL(15,2) NOT NULL DEFAULT '0.00', `tax_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', `tax_name` VARCHAR(255) NOT NULL DEFAULT '', `tax_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', `tax_rate` DECIMAL(7,2) NOT NULL DEFAULT '0.00', `calculation_mode` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', `calculation_value` DECIMAL(15,2) NULL DEFAULT NULL, `calculation_basis` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', `condition_snapshot` MEDIUMTEXT NULL DEFAULT NULL, `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `idx_register_item_revision_line` (`register_id`,`registration_revision`,`line_number`), KEY `idx_register_item_price` (`event_price_id`), KEY `idx_register_item_pool` (`capacity_pool_id`), KEY `idx_register_item_kind` (`line_kind`)) ENGINE=InnoDB",
            );

            foreach ($statements as $statement) {
                $db->setQuery($db->replacePrefix($statement))->execute();
            }

            $taxRateColumns = array_change_key_case($db->getTableColumns($db->replacePrefix('#__jem_tax_rates'), false), CASE_LOWER);
            foreach (array(
                'checked_out'      => "INT(11) UNSIGNED NULL DEFAULT NULL AFTER `ordering`",
                'checked_out_time' => "DATETIME NULL DEFAULT NULL AFTER `checked_out`",
            ) as $column => $definition) {
                if (!isset($taxRateColumns[$column])) {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__jem_tax_rates')
                        . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
                    )->execute();
                }
            }

            $hasCountryIndex = false;
            foreach ((array) $db->getTableKeys($db->replacePrefix('#__jem_tax_rates')) as $name => $key) {
                $keyName = is_string($name) ? $name : '';
                if (is_object($key)) {
                    $keyName = (string) ($key->Key_name ?? $key->key_name ?? $key->name ?? $keyName);
                }
                if (strcasecmp($keyName, 'idx_tax_rate_country') === 0) {
                    $hasCountryIndex = true;
                    break;
                }
            }
            if (!$hasCountryIndex) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_tax_rates')
                    . ' ADD INDEX ' . $db->quoteName('idx_tax_rate_country')
                    . ' (' . implode(', ', array_map(array($db, 'quoteName'), array(
                        'country_code', 'published', 'valid_from', 'valid_until',
                    ))) . ')'
                )->execute();
            }

            $tableList = (array) $db->getTableList();
            foreach (array('#__jem_tax_rates', '#__jem_capacity_pools', '#__jem_event_prices', '#__jem_register_items') as $table) {
                if (!in_array($db->replacePrefix($table), $tableList, true)) {
                    throw new RuntimeException('Pricing schema table is missing after repair: ' . $table);
                }
            }

            $this->setPricingSchemaReady($db, true);
        } catch (Throwable $e) {
            try {
                $this->setPricingSchemaReady($db, false);
            } catch (Throwable $ignored) {
            }
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_INSTALL_PRICING_SCHEMA_FAILED', $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Set the Point 4B schema readiness flag without changing feature state.
     */
    private function setPricingSchemaReady($db, $ready)
    {
        $configTable = $db->replacePrefix('#__jem_config');
        if (!in_array($configTable, $db->getTableList(), true)) {
            return;
        }

        $value = $ready ? '1' : '0';
        $db->setQuery(
            'INSERT INTO ' . $db->quoteName('#__jem_config')
            . ' (' . $db->quoteName('keyname') . ', ' . $db->quoteName('value') . ')'
            . ' VALUES (' . $db->quote('pricing_schema_ready') . ', ' . $db->quote($value) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('value') . ' = ' . $db->quote($value)
        )->execute();
    }

    /**
     * Fill empty country currencies from Unicode CLDR territory data.
     *
     * Currency codes are checked against the official ISO 4217 list maintained
     * by SIX when the bundled catalogue is prepared. Source URLs and the as-of
     * date live in the JSON/help documentation, never in database country rows.
     * Territories without exactly one current tender currency remain empty.
     */
    private function installCountryCurrencyCatalogue()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        if (!in_array($db->replacePrefix('#__jem_countries'), $db->getTableList(), true)) {
            return;
        }

        $path = JPATH_ADMINISTRATOR . '/components/com_jem/data/currencies/country-currencies.json';

        try {
            $contents = is_file($path) ? file_get_contents($path) : false;
            $catalogue = $contents !== false ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : null;
            if (!is_array($catalogue) || empty($catalogue['currencies'])) {
                throw new RuntimeException('The bundled country-currency catalogue is missing or invalid.');
            }

            foreach ($catalogue['currencies'] as $countryCode => $currency) {
                $countryCode = strtoupper(trim((string) $countryCode));
                $currency = strtoupper(trim((string) $currency));
                if (preg_match('/^[A-Z]{2}$/D', $countryCode) !== 1 || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
                    continue;
                }

                $db->setQuery(
                    'UPDATE ' . $db->quoteName('#__jem_countries')
                    . ' SET ' . $db->quoteName('currency') . ' = ' . $db->quote($currency)
                    . ' WHERE ' . $db->quoteName('iso2') . ' = ' . $db->quote($countryCode)
                    . ' AND ' . $db->quoteName('currency') . " = ''"
                )->execute();
            }
        } catch (Throwable $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_INSTALL_COUNTRY_CURRENCY_CATALOG_FAILED', $e->getMessage()),
                'warning'
            );
        }
    }

    /**
     * Install the editable European VAT reference catalogue once per version.
     *
     * Source: European Commission, Taxes in Europe Database (TEDB), official
     * SOAP/XML service:
     * https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService.wsdl
     *
     * The bundled JSON is a convenience for administrators, not fiscal advice.
     * Existing codes are never updated, so local names, rates, publication state
     * and validity dates remain entirely under administrator control.
     */
    private function installEuropeanTaxRateCatalogue()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $table = $db->replacePrefix('#__jem_tax_rates');
        $configTable = $db->replacePrefix('#__jem_config');
        if (!in_array($table, $db->getTableList(), true) || !in_array($configTable, $db->getTableList(), true)) {
            return;
        }

        $cataloguePath = JPATH_ADMINISTRATOR . '/components/com_jem/data/taxrates/eu-vat-rates.json';

        try {
            $contents = is_file($cataloguePath) ? file_get_contents($cataloguePath) : false;
            $catalogue = $contents !== false ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : null;
            if (!is_array($catalogue) || empty($catalogue['version']) || empty($catalogue['countries'])) {
                throw new RuntimeException('The bundled European tax-rate catalogue is missing or invalid.');
            }

            $version = trim((string) $catalogue['version']);
            $db->setQuery(
                'SELECT ' . $db->quoteName('value') . ' FROM ' . $db->quoteName('#__jem_config')
                . ' WHERE ' . $db->quoteName('keyname') . ' = ' . $db->quote('tax_rates_eu_catalog_version')
            );
            $installRates = (string) $db->loadResult() !== $version;

            $existingCodes = array();
            if ($installRates) {
                $db->setQuery('SELECT ' . $db->quoteName('code') . ' FROM ' . $db->quoteName('#__jem_tax_rates'));
                $existingCodes = array_fill_keys(array_map('strtoupper', (array) $db->loadColumn()), true);
            }
            $now = Factory::getDate()->toSql();
            $ordering = 0;

            foreach ($catalogue['countries'] as $country) {
                $countryCode = strtoupper(trim((string) ($country['code'] ?? '')));
                $countryName = trim((string) ($country['name'] ?? ''));
                if (preg_match('/^[A-Z]{2}$/D', $countryCode) !== 1 || $countryName === '') {
                    continue;
                }

                $currency = strtoupper(trim((string) ($country['currency'] ?? '')));
                if (preg_match('/^[A-Z]{3}$/D', $currency) === 1) {
                    $db->setQuery(
                        'UPDATE ' . $db->quoteName('#__jem_countries')
                        . ' SET ' . $db->quoteName('currency') . ' = ' . $db->quote($currency)
                        . ' WHERE ' . $db->quoteName('iso2') . ' = ' . $db->quote($countryCode)
                        . ' AND ' . $db->quoteName('currency') . " = ''"
                    )->execute();
                }

                if (!$installRates) {
                    continue;
                }

                foreach ((array) ($country['rates'] ?? array()) as $rateDefinition) {
                    if (!is_array($rateDefinition) || count($rateDefinition) !== 3) {
                        continue;
                    }
                    [$rateClass, $taxType, $rate] = array_map('strval', $rateDefinition);
                    $rateClass = strtolower(trim($rateClass));
                    $taxType = strtolower(trim($taxType));
                    $rate = trim($rate);
                    if (preg_match('/^[a-z0-9_]+$/D', $rateClass) !== 1
                        || !in_array($taxType, array('standard', 'reduced', 'zero', 'exempt', 'outside_scope'), true)
                        || preg_match('/^\d{1,3}\.\d{2}$/D', $rate) !== 1) {
                        continue;
                    }

                    $code = 'EU_' . $countryCode . '_' . strtoupper($rateClass);
                    if (isset($existingCodes[$code])) {
                        continue;
                    }

                    $label = ucwords(str_replace('_', ' ', $rateClass));
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__jem_tax_rates'))
                        ->columns(array_map(array($db, 'quoteName'), array(
                            'code', 'name', 'tax_type', 'rate', 'country_code',
                            'published', 'ordering', 'created', 'created_by',
                        )))
                        ->values(implode(',', array(
                            $db->quote($code),
                            $db->quote($countryName . ' - ' . $label . ' (' . $rate . '%)'),
                            $db->quote($taxType),
                            $db->quote($rate),
                            $db->quote($countryCode),
                            '0',
                            (string) ++$ordering,
                            $db->quote($now),
                            '0',
                        )));
                    $db->setQuery($query)->execute();
                    $existingCodes[$code] = true;
                }
            }

            $db->setQuery(
                'INSERT INTO ' . $db->quoteName('#__jem_config')
                . ' (' . $db->quoteName('keyname') . ', ' . $db->quoteName('value') . ')'
                . ' VALUES (' . $db->quote('tax_rates_eu_catalog_version') . ', ' . $db->quote($version) . ')'
                . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('value') . ' = ' . $db->quote($version)
            )->execute();
        } catch (Throwable $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_INSTALL_EU_TAX_CATALOG_FAILED', $e->getMessage()),
                'warning'
            );
        }
    }

    /**
     * Complete the additive JEM 5.1 registration migration.
     *
     * The versioned SQL owns the normal schema update. This idempotent fallback
     * also handles installations where Joomla recorded the SQL version before
     * a previous request completed the reference/history backfill.
     *
     * @return void
     */
    private function repair510RegistrationSchema()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $registerTable = $db->replacePrefix('#__jem_register');

        if (!in_array($registerTable, $db->getTableList(), true)) {
            return;
        }

        try {
            // Block new registration writes until both schema and legacy-data
            // proof complete, including during an idempotent repair rerun.
            $this->setRegistrationSchemaReady($db, false);
            $columns = array_change_key_case($db->getTableColumns($registerTable, false), CASE_LOWER);
            $definitions = array(
                'reference' => "VARCHAR(28) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER `comment`",
                'created'   => "DATETIME NULL DEFAULT NULL AFTER `reference`",
                'modified'  => "DATETIME NULL DEFAULT NULL AFTER `created`",
                'revision'  => "INT(10) UNSIGNED NOT NULL DEFAULT '1' AFTER `modified`",
            );

            foreach ($definitions as $column => $definition) {
                if (!isset($columns[$column])) {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__jem_register')
                        . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
                    );
                    $db->execute();
                }
            }

            $this->migrateRegistrationHistoryTable($db);
            $this->createRegistrationHistoryTable($db);
            $legacyFingerprint = $this->registrationLegacyFingerprint($db);

            $identityFile = JPATH_SITE . '/components/com_jem/classes/registrationidentity.class.php';
            if (!class_exists('JemRegistrationIdentity', false) && is_file($identityFile)) {
                require_once $identityFile;
            }
            if (!class_exists('JemRegistrationIdentity', false)) {
                throw new RuntimeException('Registration identity generator is unavailable.');
            }

            $lastRegistrationId = 0;
            do {
                $query = $db->getQuery(true)
                    ->select(array('r.*', 'e.title AS event_title'))
                    ->from($db->quoteName('#__jem_register', 'r'))
                    ->join('LEFT', $db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
                    ->where('r.id > ' . $lastRegistrationId)
                    ->order('r.id ASC');
                $db->setQuery($query, 0, 250);
                $registrations = (array) $db->loadObjectList();

                foreach ($registrations as $registration) {
                    $this->backfillRegistrationRow($db, $registration);
                    $lastRegistrationId = (int) $registration->id;
                }
            } while (count($registrations) === 250);

            $this->finaliseRegistrationReferenceConstraint($db);

            if ($legacyFingerprint !== $this->registrationLegacyFingerprint($db)) {
                throw new RuntimeException('Registration migration changed legacy registration data.');
            }

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__jem_register', 'r'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__jem_register_history', 'h')
                    . ' ON h.registration_id = r.id AND h.revision = 1'
                )
                ->where('(r.reference IS NULL OR r.reference = ' . $db->quote('') . ' OR h.id IS NULL)');
            $db->setQuery($query);
            if ((int) $db->loadResult() !== 0) {
                throw new RuntimeException('Registration migration verification found incomplete rows.');
            }

            $this->setRegistrationSchemaReady($db, true);
        } catch (Throwable $e) {
            $this->setRegistrationSchemaReady($db, false);
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_INSTALL_REGISTRATION_MIGRATION_FAILED', $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Preserve the earlier development table while aligning its name with
     * the legacy #__jem_register parent table.
     */
    private function migrateRegistrationHistoryTable($db)
    {
        $legacyTable = $db->replacePrefix('#__jem_registration_history');
        $historyTable = $db->replacePrefix('#__jem_register_history');
        $tableList = (array) $db->getTableList();
        $hasLegacyTable = in_array($legacyTable, $tableList, true);
        $hasHistoryTable = in_array($historyTable, $tableList, true);

        if ($hasLegacyTable && !$hasHistoryTable) {
            $db->setQuery(
                'RENAME TABLE ' . $db->quoteName($legacyTable)
                . ' TO ' . $db->quoteName($historyTable)
            )->execute();

            return;
        }

        if (!$hasLegacyTable || !$hasHistoryTable) {
            return;
        }

        $columns = array(
            'id', 'operation_reference', 'registration_id', 'registration_reference',
            'revision', 'event_id', 'event_title', 'action', 'old_status',
            'new_status', 'old_places', 'new_places', 'old_user_id', 'new_user_id',
            'actor_user_id', 'source', 'reason_code', 'forced', 'changed_fields', 'occurred',
        );
        $columnList = implode(', ', array_map(array($db, 'quoteName'), $columns));
        $db->setQuery(
            'INSERT IGNORE INTO ' . $db->quoteName($historyTable) . ' (' . $columnList . ')'
            . ' SELECT ' . $columnList . ' FROM ' . $db->quoteName($legacyTable)
        )->execute();

        $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName($legacyTable));
        $legacyCount = (int) $db->loadResult();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($legacyTable, 'old'))
            ->join('INNER', $db->quoteName($historyTable, 'new')
                . ' ON new.id = old.id'
                . ' AND new.operation_reference = old.operation_reference'
                . ' AND new.registration_id = old.registration_id'
                . ' AND new.revision = old.revision');
        $db->setQuery($query);
        if ((int) $db->loadResult() !== $legacyCount) {
            throw new RuntimeException('The legacy registration history could not be merged without changing audit identifiers.');
        }

        $db->setQuery('DROP TABLE ' . $db->quoteName($legacyTable))->execute();
    }

    /**
     * Create the append-only registration history table when it is absent.
     */
    private function createRegistrationHistoryTable($db)
    {
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__jem_register_history') . ' ('
            . $db->quoteName('id') . ' BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,'
            . $db->quoteName('operation_reference') . ' VARCHAR(28) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . $db->quoteName('registration_id') . ' INT(11) UNSIGNED NOT NULL,'
            . $db->quoteName('registration_reference') . ' VARCHAR(28) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . $db->quoteName('revision') . ' INT(10) UNSIGNED NOT NULL,'
            . $db->quoteName('event_id') . " INT(11) UNSIGNED NOT NULL DEFAULT '0',"
            . $db->quoteName('event_title') . " VARCHAR(255) NOT NULL DEFAULT '',"
            . $db->quoteName('action') . ' VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . $db->quoteName('old_status') . ' TINYINT(3) NULL DEFAULT NULL,'
            . $db->quoteName('new_status') . ' TINYINT(3) NULL DEFAULT NULL,'
            . $db->quoteName('old_places') . ' INT(11) NULL DEFAULT NULL,'
            . $db->quoteName('new_places') . ' INT(11) NULL DEFAULT NULL,'
            . $db->quoteName('old_user_id') . ' INT(11) NULL DEFAULT NULL,'
            . $db->quoteName('new_user_id') . ' INT(11) NULL DEFAULT NULL,'
            . $db->quoteName('actor_user_id') . " INT(11) UNSIGNED NOT NULL DEFAULT '0',"
            . $db->quoteName('source') . " VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',"
            . $db->quoteName('reason_code') . ' VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,'
            . $db->quoteName('forced') . " TINYINT(1) NOT NULL DEFAULT '0',"
            . $db->quoteName('changed_fields') . ' TEXT NULL DEFAULT NULL,'
            . $db->quoteName('occurred') . ' DATETIME NOT NULL,'
            . ' PRIMARY KEY (' . $db->quoteName('id') . '),'
            . ' UNIQUE KEY ' . $db->quoteName('idx_history_registration_revision') . ' (' . $db->quoteName('registration_id') . ', ' . $db->quoteName('revision') . '),'
            . ' UNIQUE KEY ' . $db->quoteName('idx_history_operation_registration') . ' (' . $db->quoteName('operation_reference') . ', ' . $db->quoteName('registration_id') . '),'
            . ' KEY ' . $db->quoteName('idx_history_reference_occurred') . ' (' . $db->quoteName('registration_reference') . ', ' . $db->quoteName('occurred') . '),'
            . ' KEY ' . $db->quoteName('idx_history_operation') . ' (' . $db->quoteName('operation_reference') . '),'
            . ' KEY ' . $db->quoteName('idx_history_event_occurred') . ' (' . $db->quoteName('event_id') . ', ' . $db->quoteName('occurred') . '),'
            . ' KEY ' . $db->quoteName('idx_history_actor_occurred') . ' (' . $db->quoteName('actor_user_id') . ', ' . $db->quoteName('occurred') . '),'
            . ' KEY ' . $db->quoteName('idx_history_action_occurred') . ' (' . $db->quoteName('action') . ', ' . $db->quoteName('occurred') . '),'
            . ' KEY ' . $db->quoteName('idx_history_source_occurred') . ' (' . $db->quoteName('source') . ', ' . $db->quoteName('occurred') . '),'
            . ' KEY ' . $db->quoteName('idx_history_status_occurred') . ' (' . $db->quoteName('new_status') . ', ' . $db->quoteName('occurred') . '),'
            . ' KEY ' . $db->quoteName('idx_history_occurred') . ' (' . $db->quoteName('occurred') . ')'
            . ') ENGINE=InnoDB'
        );
        $db->execute();
    }

    /**
     * Backfill one legacy registration atomically and safely on retry.
     */
    private function backfillRegistrationRow($db, $registration)
    {
        $db->transactionStart();

        try {
            $reference = trim((string) ($registration->reference ?? ''));
            if ($reference === '') {
                $reference = $this->createUniqueRegistrationReference($db, (int) $registration->id);
            } elseif (!JemRegistrationIdentity::isRegistrationReference($reference)) {
                throw new RuntimeException('Invalid existing registration reference for row ' . (int) $registration->id . '.');
            }

            $created = $registration->created ?? null;
            $modified = $registration->modified ?? null;
            $legacyTimestamp = $this->validLegacyRegistrationTimestamp($registration->uregdate ?? '');
            $revision = max(1, (int) ($registration->revision ?? 1));

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_register'))
                ->set($db->quoteName('reference') . ' = ' . $db->quote($reference))
                ->set($db->quoteName('revision') . ' = ' . $revision)
                ->where($db->quoteName('id') . ' = ' . (int) $registration->id);

            if (empty($created) && $legacyTimestamp !== null) {
                $query->set($db->quoteName('created') . ' = ' . $db->quote($legacyTimestamp));
            }
            if (empty($modified) && $legacyTimestamp !== null) {
                $query->set($db->quoteName('modified') . ' = ' . $db->quote($legacyTimestamp));
            }
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__jem_register_history'))
                ->where($db->quoteName('registration_id') . ' = ' . (int) $registration->id)
                ->where($db->quoteName('revision') . ' = 1');
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                $logicalStatus = ((int) $registration->status === 1 && !empty($registration->waiting))
                    ? 2
                    : (int) $registration->status;
                $history = (object) array(
                    'operation_reference'  => JemRegistrationIdentity::generateOperationReference(),
                    'registration_id'      => (int) $registration->id,
                    'registration_reference' => $reference,
                    'revision'             => 1,
                    'event_id'             => (int) $registration->event,
                    'event_title'          => (string) ($registration->event_title ?? ''),
                    'action'               => 'migrated',
                    'old_status'           => null,
                    'new_status'           => $logicalStatus,
                    'old_places'           => null,
                    'new_places'           => max(0, (int) $registration->places),
                    'old_user_id'          => null,
                    'new_user_id'          => (int) $registration->uid,
                    'actor_user_id'        => 0,
                    'source'               => 'installer.migration',
                    'reason_code'          => 'jem_5_0_1_upgrade',
                    'forced'               => 0,
                    'changed_fields'       => json_encode(array('baseline'), JSON_UNESCAPED_SLASHES),
                    'occurred'             => gmdate('Y-m-d H:i:s'),
                );
                $db->insertObject('#__jem_register_history', $history);
            }

            $db->transactionCommit();
        } catch (Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }
    }

    private function createUniqueRegistrationReference($db, $registrationId)
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = JemRegistrationIdentity::generateRegistrationReference();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__jem_register'))
                ->where($db->quoteName('reference') . ' = ' . $db->quote($reference))
                ->where($db->quoteName('id') . ' <> ' . (int) $registrationId);
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique registration reference.');
    }

    private function validLegacyRegistrationTimestamp($value)
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }

    /**
     * Return a bounded-memory proof of every legacy registration field.
     *
     * Length-prefixed binary values preserve the distinction between NULL,
     * empty strings and arbitrary comment/IP contents without depending on
     * database collation or connection character-set conversions.
     */
    private function registrationLegacyFingerprint($db)
    {
        $context = hash_init('sha256');
        $count = 0;
        $lastRegistrationId = 0;
        $fields = array('id', 'event', 'uid', 'places', 'uregdate', 'uip', 'waiting', 'status', 'comment');

        do {
            $query = $db->getQuery(true)
                ->select($db->quoteName($fields))
                ->from($db->quoteName('#__jem_register'))
                ->where($db->quoteName('id') . ' > ' . $lastRegistrationId)
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query, 0, 250);
            $rows = (array) $db->loadObjectList();

            foreach ($rows as $row) {
                foreach ($fields as $field) {
                    $value = $row->$field ?? null;
                    if ($value === null) {
                        hash_update($context, "\xFF");
                        continue;
                    }

                    $value = (string) $value;
                    hash_update($context, "\x00" . pack('N', strlen($value)) . $value);
                }

                $lastRegistrationId = (int) $row->id;
                ++$count;
            }
        } while (count($rows) === 250);

        return $count . ':' . hash_final($context);
    }

    private function finaliseRegistrationReferenceConstraint($db)
    {
        $table = $db->replacePrefix('#__jem_register');
        $keys = $db->getTableKeys($table);
        $keyNames = array();

        foreach ((array) $keys as $name => $key) {
            if (is_string($name)) {
                $keyNames[] = $name;
            }
            if (is_object($key)) {
                foreach (array('Key_name', 'key_name', 'name') as $property) {
                    if (isset($key->$property)) {
                        $keyNames[] = (string) $key->$property;
                    }
                }
            }
        }

        if (!in_array('idx_register_reference', $keyNames, true)) {
            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__jem_register')
                . ' ADD UNIQUE INDEX ' . $db->quoteName('idx_register_reference')
                . ' (' . $db->quoteName('reference') . ')'
            );
            $db->execute();
        }

        $columns = array_change_key_case($db->getTableColumns($table, false), CASE_LOWER);
        $referenceColumn = $columns['reference'] ?? null;
        $nullable = is_object($referenceColumn)
            ? strtoupper((string) ($referenceColumn->Null ?? $referenceColumn->null ?? 'YES')) === 'YES'
            : true;

        if ($nullable) {
            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__jem_register')
                . ' MODIFY ' . $db->quoteName('reference')
                . ' VARCHAR(28) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
            );
            $db->execute();
        }
    }

    private function setRegistrationSchemaReady($db, $ready)
    {
        $configTable = $db->replacePrefix('#__jem_config');
        if (!in_array($configTable, $db->getTableList(), true)) {
            return;
        }

        $value = $ready ? '1' : '0';
        $db->setQuery(
            'INSERT INTO ' . $db->quoteName('#__jem_config')
            . ' (' . $db->quoteName('keyname') . ', ' . $db->quoteName('value') . ')'
            . ' VALUES (' . $db->quote('registration_schema_ready') . ', ' . $db->quote($value) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('value') . ' = ' . $db->quote($value)
        );
        $db->execute();
    }

    /**
     * Initialise the granular backend ACL without removing existing rules.
     *
     * Updates preserve JEM's historical behaviour by granting the new backend
     * actions to groups which could previously manage the component. Fresh
     * installations map the matching Joomla core action instead. Existing
     * explicit allow or deny rules for a new action are never overwritten, so
     * the operation is safe to repeat after an interrupted installation.
     *
     * @param   boolean  $preserveLegacyManage  True for an update.
     *
     * @return void
     */
    private function migrateBackendAcl($preserveLegacyManage)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $asset = Table::getInstance('Asset');

        if (!$asset->loadByName('com_jem')) {
            return;
        }

        $rules = new Rules((string) $asset->rules);
        $rulesData = $rules->getData();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__usergroups'));
        $db->setQuery($query);
        $groupIds = array_map('intval', (array) $db->loadColumn());

        $sourceActions = array(
            'core.options'          => 'core.options',
            'jem.events.access'     => 'core.manage',
            'jem.events.create'     => 'core.create',
            'jem.events.delete'     => 'core.delete',
            'jem.events.edit'         => 'core.edit',
            'jem.events.edit.state'   => 'core.edit.state',
            'jem.events.edit.own'     => 'core.edit.own',
            'jem.events.edit.created' => 'core.edit',
            'jem.venues.access'       => 'core.manage',
            'jem.venues.create'       => 'core.create',
            'jem.venues.delete'       => 'core.delete',
            'jem.venues.edit'         => 'core.edit',
            'jem.venues.edit.state'   => 'core.edit.state',
            'jem.venues.edit.own'     => 'core.edit.own',
            'jem.venues.edit.created' => 'core.edit',
            'jem.attendees.manage'  => 'core.edit',
            'jem.registrations.history' => 'core.edit',
            'jem.notifications.templates' => 'core.edit',
            'jem.notifications.history' => 'core.edit',
            'jem.notifications.resend' => 'core.edit',
            'jem.tools.manage'      => 'core.admin',
        );
        $changed = false;

        foreach ($groupIds as $groupId) {
            $legacyManager = $preserveLegacyManage
                && Access::checkGroup($groupId, 'core.manage', 'com_jem');

            foreach ($sourceActions as $targetAction => $sourceAction) {
                $existing = isset($rulesData[$targetAction])
                    ? $rulesData[$targetAction]->allow($groupId)
                    : null;

                if ($existing !== null) {
                    continue;
                }

                if (!$legacyManager && !Access::checkGroup($groupId, $sourceAction, 'com_jem')) {
                    continue;
                }

                $rules->mergeAction($targetAction, array($groupId => true));
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $asset->rules = (string) $rules;

        if (!$asset->check() || !$asset->store()) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_JEM_INSTALL_BACKEND_ACL_MIGRATION_FAILED'),
                'warning'
            );

            return;
        }

        Access::clearStatics();
        Factory::getApplication()->enqueueMessage(
            Text::_($preserveLegacyManage
                ? 'COM_JEM_INSTALL_BACKEND_ACL_MIGRATED'
                : 'COM_JEM_INSTALL_BACKEND_ACL_INITIALISED'),
            'notice'
        );
    }

    /**
     * Secondary repair for a partially restored JEM 5.0.1 schema.
     *
     * Joomla owns the normal schema lifecycle through jem.xml,
     * install.mysql.utf8.sql and the versioned update SQL files. This fallback
     * only restores missing 5.0.1 fields when Joomla already has that schema
     * version recorded and therefore does not execute 5.0.1.sql again.
     *
     * @return void
     */
    private function repair501SchemaFallback()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $existingTables = $db->getTableList();
        $definitionsByTable = array(
            '#__jem_events' => array(
                'timezone_mode' => "VARCHAR(10) NOT NULL DEFAULT 'joomla' AFTER `endtimes`",
                'timezone'      => "VARCHAR(64) NOT NULL DEFAULT '' AFTER `timezone_mode`",
                'start_utc'     => "DATETIME NULL DEFAULT NULL AFTER `timezone`",
                'end_utc'       => "DATETIME NULL DEFAULT NULL AFTER `start_utc`",
                'last_visit'    => "DATETIME NULL DEFAULT NULL AFTER `hits`",
                'series_id'     => "INT(11) UNSIGNED NULL DEFAULT NULL AFTER `recurrence_bylastday`",
                'series_order'  => "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `series_id`",
            ),
            '#__jem_venues' => array(
                'district' => "VARCHAR(100) NOT NULL DEFAULT '' AFTER `city`",
                'level'    => "VARCHAR(100) NOT NULL DEFAULT '' AFTER `district`",
                'capacity' => "INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `level`",
                'timezone' => "VARCHAR(64) NOT NULL DEFAULT '' AFTER `country`",
                'email'    => "VARCHAR(254) NOT NULL DEFAULT '' AFTER `timezone`",
                'phone'    => "VARCHAR(50) NOT NULL DEFAULT '' AFTER `email`",
                'mobile'   => "VARCHAR(50) NOT NULL DEFAULT '' AFTER `phone`",
            ),
            '#__jem_attachments' => array(
                'downloads'     => "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `created_by`",
                'last_download' => "DATETIME NULL DEFAULT NULL AFTER `downloads`",
            ),
        );

        foreach ($definitionsByTable as $table => $definitions) {
            $resolvedTable = $db->replacePrefix($table);
            if (!in_array($resolvedTable, $existingTables, true)) {
                continue;
            }

            $columns = array_change_key_case($db->getTableColumns($resolvedTable, false), CASE_LOWER);
            foreach ($definitions as $column => $definition) {
                if (!isset($columns[$column])) {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName($table)
                        . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
                    );
                    $db->execute();
                }
            }
        }

        $eventTable = $db->replacePrefix('#__jem_events');
        if (in_array($eventTable, $existingTables, true)) {
            $keys = $db->getTableKeys($eventTable);
            $keyNames = array();
            foreach ((array) $keys as $name => $key) {
                if (is_string($name)) {
                    $keyNames[] = $name;
                }
                if (is_object($key)) {
                    foreach (array('Key_name', 'key_name', 'name') as $property) {
                        if (isset($key->$property)) {
                            $keyNames[] = (string) $key->$property;
                        }
                    }
                }
            }
            if (!in_array('idx_start_utc', $keyNames, true)) {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__jem_events') . ' ADD INDEX ' . $db->quoteName('idx_start_utc') . ' (' . $db->quoteName('start_utc') . ')');
                $db->execute();
            }
            if (!in_array('idx_end_utc', $keyNames, true)) {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__jem_events') . ' ADD INDEX ' . $db->quoteName('idx_end_utc') . ' (' . $db->quoteName('end_utc') . ')');
                $db->execute();
            }
            if (!in_array('idx_series', $keyNames, true)) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__jem_events')
                    . ' ADD INDEX ' . $db->quoteName('idx_series')
                    . ' (' . $db->quoteName('series_id') . ', ' . $db->quoteName('series_order') . ')'
                );
                $db->execute();
            }
        }

        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__jem_event_series')
            . ' ('
            . $db->quoteName('id') . ' INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,'
            . $db->quoteName('root_event_id') . " INT(11) UNSIGNED NOT NULL DEFAULT '0',"
            . $db->quoteName('title') . " VARCHAR(255) NOT NULL DEFAULT '',"
            . $db->quoteName('series_type') . " VARCHAR(20) NOT NULL DEFAULT 'custom',"
            . $db->quoteName('created') . ' DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . $db->quoteName('created_by') . " INT(11) UNSIGNED NOT NULL DEFAULT '0',"
            . $db->quoteName('modified') . ' DATETIME NULL DEFAULT NULL,'
            . $db->quoteName('modified_by') . " INT(11) UNSIGNED NOT NULL DEFAULT '0',"
            . $db->quoteName('published') . " TINYINT(1) NOT NULL DEFAULT '1',"
            . ' PRIMARY KEY (' . $db->quoteName('id') . '),'
            . ' KEY ' . $db->quoteName('idx_root_event') . ' (' . $db->quoteName('root_event_id') . '),'
            . ' KEY ' . $db->quoteName('idx_created_by') . ' (' . $db->quoteName('created_by') . '),'
            . ' KEY ' . $db->quoteName('idx_published') . ' (' . $db->quoteName('published') . ')'
            . ') ENGINE=InnoDB'
        );
        $db->execute();

        if (in_array($db->replacePrefix('#__jem_config'), $existingTables, true)) {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__jem_config'))
                ->columns(array($db->quoteName('keyname'), $db->quoteName('value')))
                ->values($db->quote('event_timezone_default') . ', ' . $db->quote('joomla'));
            $query = str_replace('INSERT INTO', 'INSERT IGNORE INTO', (string) $query);
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Backfill canonical UTC event boundaries after install or update.
     *
     * @return void
     */
    private function rebuildEventUtcDates()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $tables = $db->getTableList();

        if (!in_array($db->replacePrefix('#__jem_events'), $tables, true)
            || !in_array($db->replacePrefix('#__jem_venues'), $tables, true)) {
            return;
        }

        $joomlaTimeZone = trim((string) Factory::getConfig()->get('offset', 'UTC'));
        try {
            new \DateTimeZone($joomlaTimeZone);
        } catch (\Exception $e) {
            $joomlaTimeZone = 'UTC';
        }

        $query = $db->getQuery(true)
            ->select(array(
                'a.id', 'a.dates', 'a.enddates', 'a.times', 'a.endtimes',
                'a.timezone_mode', 'a.timezone', 'l.timezone AS venue_timezone',
            ))
            ->from($db->quoteName('#__jem_events', 'a'))
            ->join('LEFT', $db->quoteName('#__jem_venues', 'l') . ' ON ' . $db->quoteName('l.id') . ' = ' . $db->quoteName('a.locid'));
        $db->setQuery($query);

        foreach ((array) $db->loadObjectList() as $event) {
            $startUtc = null;
            $endUtc = null;

            if (!empty($event->dates) && $event->dates !== '0000-00-00') {
                $timeZoneName = $joomlaTimeZone;
                if ($event->timezone_mode === 'custom' && $this->isValidTimeZone($event->timezone)) {
                    $timeZoneName = $event->timezone;
                } elseif ($event->timezone_mode === 'venue' && $this->isValidTimeZone($event->venue_timezone)) {
                    $timeZoneName = $event->venue_timezone;
                }

                try {
                    $timeZone = new \DateTimeZone($timeZoneName);
                    $utc = new \DateTimeZone('UTC');
                    $start = new \DateTimeImmutable(
                        $event->dates . ' ' . ($event->times ?: '00:00:00'),
                        $timeZone
                    );
                    $end = new \DateTimeImmutable(
                        ($event->enddates ?: $event->dates) . ' ' . ($event->endtimes ?: '23:59:59'),
                        $timeZone
                    );
                    $startUtc = $start->setTimezone($utc)->format('Y-m-d H:i:s');
                    $endUtc = $end->setTimezone($utc)->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $startUtc = null;
                    $endUtc = null;
                }
            }

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__jem_events'))
                ->set($db->quoteName('start_utc') . ' = ' . ($startUtc === null ? 'NULL' : $db->quote($startUtc)))
                ->set($db->quoteName('end_utc') . ' = ' . ($endUtc === null ? 'NULL' : $db->quote($endUtc)))
                ->where($db->quoteName('id') . ' = ' . (int) $event->id);
            $db->setQuery($update);
            $db->execute();
        }
    }

    /**
     * Validate a timezone identifier during installation.
     *
     * @param   string  $timeZone  Timezone identifier.
     *
     * @return boolean
     */
    private function isValidTimeZone($timeZone)
    {
        $timeZone = trim((string) $timeZone);

        if ($timeZone === '') {
            return false;
        }

        if (!in_array($timeZone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            return false;
        }

        try {
            new \DateTimeZone($timeZone);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get a parameter from the manifest file (actually, from the manifest cache).
     *
     * @param $name  The name of the parameter
     *
     * @return The parameter
     */
    public function getParam($name)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('manifest_cache')
              ->from('#__extensions')
              ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
              ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $manifest = json_decode($db->loadResult(), true);
        return $manifest[$name] ?? '';
    }

    /**
     * Sets parameter values in the component's row of the extension table
     *
     * @param $param_array  An array holding the params to store
     */
    private function setParams($param_array)
    {
        if (is_array($param_array) && (count($param_array) > 0)) {
            // read the existing component value(s)
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            $query->select('params')
                  ->from('#__extensions')
                  ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                  ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
            $db->setQuery($query);
            $params = json_decode($db->loadResult(), true);

            // add the new variable(s) to the existing one(s)
            foreach ($param_array as $name => $value) {
                $params[(string)$name] = (string)$value;
            }

            // store the combined new and existing values back as a JSON string
            $paramsString = json_encode($params);
            $query = $db->getQuery(true);
            $query->update('#__extensions')
                ->set('params = ' . $db->quote($paramsString))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Gets globalattrib values from the settings table
     *
     * @return Registry
     */
    private function getGlobalParams()
    {
        $registry = new Registry();
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query->select('value')->from('#__jem_config')
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('globalattribs'));
            } else {
                $query->select('globalattribs')->from('#__jem_settings')->where('id=1');
            }
            $db->setQuery($query);
            $registry->loadString($db->loadResult());
        } catch (Exception $ex) {
        }
        return $registry;
    }

    /**
     * Sets globalattrib values in the settings table
     *
     * @param $param_array  An array holding the params to store
     */
    private function setGlobalAttribs($param_array)
    {
        if (is_array($param_array) && (count($param_array) > 0)) {
            // read the existing component value(s)
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query->select('value')->from('#__jem_config')
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('globalattribs'));
            } else {
                $query->select('globalattribs')->from('#__jem_settings');
            }
            $db->setQuery($query);
            $params = json_decode($db->loadResult(), true);

            // add the new variable(s) to the existing one(s)
            foreach ($param_array as $name => $value) {
                $params[(string)$name] = (string)$value;
            }

            // store the combined new and existing values back as a JSON string
            $paramsString = json_encode($params);
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query->update('#__jem_config')
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('globalattribs'))
                    ->set($db->quoteName('value') . ' = ' . $db->quote($paramsString));
            } else {
                $query->update('#__jem_settings')
                    ->set('globalattribs = ' . $db->quote($paramsString));
            }
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Helper method that outputs a short JEM header with logo and text
     */
    private function getHeader()
    {
        $this->loadInstallerLanguage();

        $logoDataUri = $this->getHeaderLogoDataUri();
        ?>
        <div style="display:flex;align-items:center;column-gap:40px;row-gap:16px;margin-bottom:24px;flex-wrap:wrap;">
            <div style="flex:0 0 300px;max-width:100%;">
                <img src="<?php echo $logoDataUri; ?>" alt="JEM - Joomla Event Manager" style="display:block;width:100%;height:auto;background-color:#fff;border-radius:8px;" />
            </div>
            <div style="flex:1 1 260px;min-width:260px;">
                <h1 style="margin-top:0;"><?php echo Text::_('COM_JEM'); ?></h1>
                <p class="small"><?php echo Text::_('COM_JEM_INSTALLATION_HEADER'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Loads JEM language strings before Joomla copies the component language files.
     */
    private function loadInstallerLanguage()
    {
        $language = Factory::getApplication()->getLanguage();
        $paths = array(
            JPATH_ADMINISTRATOR,
            JPATH_ADMINISTRATOR . '/components/com_jem',
            __DIR__,
            __DIR__ . '/admin',
        );

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $language->load('com_jem.sys', $path, null, true, true);
                $language->load('com_jem', $path, null, true, true);
            }
        }
    }

    /**
     * Returns the JEM logo as a data URI so uninstall messages do not depend on media files.
     */
    private function getHeaderLogoDataUri()
    {
        $logo = __DIR__ . '/media/images/jemlogo.svg';

        if (!is_file($logo)) {
            return $this->getFallbackHeaderLogoDataUri();
        }

        $data = file_get_contents($logo);

        if ($data === false) {
            return $this->getFallbackHeaderLogoDataUri();
        }

        return 'data:image/svg+xml;base64,' . base64_encode($data);
    }

    /**
     * Returns a compact inline JEM mark used when package media is unavailable during uninstall.
     */
    private function getFallbackHeaderLogoDataUri()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="280" height="110" viewBox="0 0 280 110">'
            . '<rect width="280" height="110" rx="8" fill="#fff"/>'
            . '<g fill="none" stroke="#f69d00" stroke-width="8" stroke-linecap="round">'
            . '<path d="M28 34h48M28 55h48M28 76h48"/>'
            . '</g>'
            . '<circle cx="18" cy="34" r="5" fill="#5e7899"/><circle cx="18" cy="55" r="5" fill="#5e7899"/><circle cx="18" cy="76" r="5" fill="#5e7899"/>'
            . '<text x="92" y="55" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="700" fill="#f69d00">JEM</text>'
            . '<text x="92" y="78" font-family="Arial, Helvetica, sans-serif" font-size="15" fill="#5e7899">Joomla Event Manager</text>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Checks if component is already registered in Joomlas schema table and adds an entry if
     * neccessary
     * @param string $versionId The JEM version to add to the schema table
     */
    private function initializeSchema($versionId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get extension ID of JEM
        $query = $db->getQuery(true);
        $query->select('extension_id')
              ->from('#__extensions')
              ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
              ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $extensionId = $db->loadResult();

        if (!$extensionId) {
            // This is a fresh installation, return
            return;
        }

        // Check if an entry already exists in schemas table
        $query = $db->getQuery(true);
        $query->select('version_id')
              ->from('#__schemas')
              ->where('extension_id = ' . (int)$extensionId);
        $db->setQuery($query);

        if ($db->loadResult()) {
            // Entry exists, return
            return;
        }

        // Insert extension ID and old release version number into schemas table
        $query = $db->getQuery(true);
        $query->insert('#__schemas')
            ->columns($db->quoteName(array('extension_id', 'version_id')))
            ->values(implode(',', array((int)$extensionId, $db->quote($versionId))));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Remove all JEM menu items.
     *
     * @return void
     */
    private function removeJemMenuItems()
    {
        // remove all "com_jem..." frontend entries
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->delete('#__menu');
        $query->where('client_id = 0')
              ->where('link LIKE ' . $db->quote('index.php?option=com_jem%'));
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Disable all JEM menu items.
     * (usefull on uninstall to prevent dead links)
     *
     * @return void
     */
    private function disableJemMenuItems()
    {
        // unpublish all "com_jem..." frontend entries
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->update('#__menu');
        $query->set('published = 0');
        $query->where('client_id = 0')
              ->where('published > 0')
              ->where('link LIKE ' . $db->quote('index.php?option=com_jem%'));
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Fix all JEM menu items by setting new extension id.
     * (usefull on install to let menu items from older installation refer new extension id)
     *
     * @return void
     */
    private function fixJemMenuItems()
    {
        // Get (new) extension ID of JEM
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('extension_id')
              ->from('#__extensions')
              ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
              ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $newId = $db->loadResult();

        if ($newId) {
            // set component id on all "com_jem..." frontend entries
            $query = $db->getQuery(true);
            $query->update('#__menu');
            $query->set('component_id = ' . (int)$newId);
            $query->where('client_id = 0')
                  ->where('link LIKE ' . $db->quote('index.php?option=com_jem%'));
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Remove the legacy Help entry from Joomla's administrator component menu.
     *
     * The Help view remains available from the JEM control panel, but it should
     * no longer be shown as a separate item in the Joomla Components menu.
     *
     * @return void
     */
    private function removeObsoleteAdminHelpMenuItem()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));

        $db->setQuery($query);
        $componentId = (int) $db->loadResult();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where(
                '('
                . $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_jem&view=help')
                . ' OR ' . $db->quoteName('link') . ' = ' . $db->quote('option=com_jem&view=help')
                . ' OR ' . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_jem%view=help%')
                . ')'
            );

        if ($componentId > 0) {
            $query->where($db->quoteName('component_id') . ' = ' . $componentId);
        }

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Repair generated frontend type menu items whose stored type id became stale.
     *
     * @return void
     */
    private function repairGeneratedTypeMenuItems()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $componentId = (int) $db->loadResult();

        $items = array(
            'events-by-type' => array(
                'entity' => 1,
                'link'   => 'index.php?option=com_jem&view=typeevents&id=%d',
            ),
            'venues-by-type' => array(
                'entity' => 3,
                'link'   => 'index.php?option=com_jem&view=typevenues&id=%d',
            ),
            'categories-by-type' => array(
                'entity' => 2,
                'link'   => 'index.php?option=com_jem&view=categories&id=1&typeid=0',
            ),
        );

        foreach ($items as $alias => $item) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__jem_types'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('entity') . ' = ' . (int) $item['entity'])
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');
            $db->setQuery($query, 0, 1);
            $typeId = (int) $db->loadResult();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('menutype') . ' = ' . $db->quote('jem-frontend-menu'))
                ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));

            if ($typeId || $alias === 'categories-by-type') {
                $link = (strpos($item['link'], '%d') !== false) ? sprintf($item['link'], $typeId) : $item['link'];

                $query->set($db->quoteName('link') . ' = ' . $db->quote($link))
                    ->set($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->set($db->quoteName('published') . ' = 1')
                    ->set($db->quoteName('access') . ' = 1');

                if ($componentId) {
                    $query->set($db->quoteName('component_id') . ' = ' . $componentId);
                }
            } else {
                $query->set($db->quoteName('published') . ' = 0');
            }

            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Remove all obsolete files and folders of previous versions.
     *
     * Todo: Enhance the lists on each new version.
     *
     * @return void
     */
    private function deleteObsoleteFiles()
    {
        // obsolete files
        $files = array(
            '/administrator/components/com_jem/help/images/administrator.gif',
            '/administrator/components/com_jem/help/images/checked_out.png',
            '/administrator/components/com_jem/help/images/icon-32-attention.png',
            '/administrator/components/com_jem/help/images/icon-32-hint.png',
            '/administrator/components/com_jem/help/images/manager.png',
            '/administrator/components/com_jem/help/images/publish_x.png',
            '/administrator/components/com_jem/help/images/super_administrator.gif',
            '/administrator/components/com_jem/help/images/tablemodern.jpg',
            '/administrator/components/com_jem/help/images/tick.png',
            '/administrator/components/com_jem/language/en-GB/en-GB.com_jem.ini',
            '/administrator/components/com_jem/language/en-GB/en-GB.com_jem.sys.ini',
            '/administrator/components/com_jem/sql/updates/1.9.sql',
            '/administrator/components/com_jem/sql/updates/1.9.1.sql',
            '/administrator/components/com_jem/sql/updates/1.9.2.sql',
            '/administrator/components/com_jem/sql/updates/1.9.3.sql',
            '/administrator/components/com_jem/sql/updates/1.9.4.sql',
            '/administrator/components/com_jem/sql/updates/1.9.5.sql',
            '/administrator/components/com_jem/sql/updates/1.9.6.sql',
            '/administrator/components/com_jem/sql/updates/1.9.7.sql',
            '/administrator/components/com_jem/sql/updates/1.9.8.sql',
            '/administrator/language/en-GB/en-GB.plg_content_jem.ini',
            '/administrator/language/en-GB/en-GB.plg_content_jem.sys.ini',
            '/administrator/language/en-GB/en-GB.plg_finder_jem.ini',
            '/components/com_jem/language/en-GB/en-GB.com_jem.ini',
            '/language/en-GB/en-GB.pkg_jem.sys.ini',
            '/media/com_jem/css/jem-alternative.css',
            '/media/com_jem/images/toolbar/icon-32-adduser.png',
            '/media/com_jem/images/toolbar/icon-32-alert.png',
            '/media/com_jem/images/toolbar/icon-32-apply.png',
            '/media/com_jem/images/toolbar/icon-32-archive.png',
            '/media/com_jem/images/toolbar/icon-32-article.png',
            '/media/com_jem/images/toolbar/icon-32-article-add.png',
            '/media/com_jem/images/toolbar/icon-32-back.png',
            '/media/com_jem/images/toolbar/icon-32-banner.png',
            '/media/com_jem/images/toolbar/icon-32-banner-categories.png',
            '/media/com_jem/images/toolbar/icon-32-banner-client.png',
            '/media/com_jem/images/toolbar/icon-32-banner-tracks.png',
            '/media/com_jem/images/toolbar/icon-32-calendar.png',
            '/media/com_jem/images/toolbar/icon-32-cancel.png',
            '/media/com_jem/images/toolbar/icon-32-checkin.png',
            '/media/com_jem/images/toolbar/icon-32-component.png',
            '/media/com_jem/images/toolbar/icon-32-config.png',
            '/media/com_jem/images/toolbar/icon-32-contacts.png',
            '/media/com_jem/images/toolbar/icon-32-contact-categories.png',
            '/media/com_jem/images/toolbar/icon-32-copy.png',
            '/media/com_jem/images/toolbar/icon-32-css.png',
            '/media/com_jem/images/toolbar/icon-32-default.png',
            '/media/com_jem/images/toolbar/icon-32-delete.png',
            '/media/com_jem/images/toolbar/icon-32-delete-style.png',
            '/media/com_jem/images/toolbar/icon-32-deny.png',
            '/media/com_jem/images/toolbar/icon-32-download.png',
            '/media/com_jem/images/toolbar/icon-32-edit.png',
            '/media/com_jem/images/toolbar/icon-32-error.png',
            '/media/com_jem/images/toolbar/icon-32-export.png',
            '/media/com_jem/images/toolbar/icon-32-extension.png',
            '/media/com_jem/images/toolbar/icon-32-featured.png',
            '/media/com_jem/images/toolbar/icon-32-forward.png',
            '/media/com_jem/images/toolbar/icon-32-help.png',
            '/media/com_jem/images/toolbar/icon-32-html.png',
            '/media/com_jem/images/toolbar/icon-32-inbox.png',
            '/media/com_jem/images/toolbar/icon-32-info.png',
            '/media/com_jem/images/toolbar/icon-32-links.png',
            '/media/com_jem/images/toolbar/icon-32-lock.png',
            '/media/com_jem/images/toolbar/icon-32-menu.png',
            '/media/com_jem/images/toolbar/icon-32-messaging.png',
            '/media/com_jem/images/toolbar/icon-32-module.png',
            '/media/com_jem/images/toolbar/icon-32-move.png',
            '/media/com_jem/images/toolbar/icon-32-new.png',
            '/media/com_jem/images/toolbar/icon-32-new-privatemessage.png',
            '/media/com_jem/images/toolbar/icon-32-new-style.png',
            '/media/com_jem/images/toolbar/icon-32-notice.png',
            '/media/com_jem/images/toolbar/icon-32-preview.png',
            '/media/com_jem/images/toolbar/icon-32-print.png',
            '/media/com_jem/images/toolbar/icon-32-publish.png',
            '/media/com_jem/images/toolbar/icon-32-purge.png',
            '/media/com_jem/images/toolbar/icon-32-read-privatemessage.png',
            '/media/com_jem/images/toolbar/icon-32-refresh.png',
            '/media/com_jem/images/toolbar/icon-32-remove.png',
            '/media/com_jem/images/toolbar/icon-32-revert.png',
            '/media/com_jem/images/toolbar/icon-32-save.png',
            '/media/com_jem/images/toolbar/icon-32-save-copy.png',
            '/media/com_jem/images/toolbar/icon-32-save-new.png',
            '/media/com_jem/images/toolbar/icon-32-search.png',
            '/media/com_jem/images/toolbar/icon-32-send.png',
            '/media/com_jem/images/toolbar/icon-32-stats.png',
            '/media/com_jem/images/toolbar/icon-32-trash.png',
            '/media/com_jem/images/toolbar/icon-32-unarchive.png',
            '/media/com_jem/images/toolbar/icon-32-unblock.png',
            '/media/com_jem/images/toolbar/icon-32-unpublish.png',
            '/media/com_jem/images/toolbar/icon-32-upload.png',
            '/media/com_jem/images/toolbar/icon-32-user-add.png',
            '/media/com_jem/images/toolbar/icon-32-xml.png',
            '/media/com_jem/images/addvenue.png',
            '/media/com_jem/images/ajax-loader.gif',
            '/media/com_jem/images/archive_front.png',
            '/media/com_jem/images/arrow-left.png',
            '/media/com_jem/images/arrow-middle.png',
            '/media/com_jem/images/arrow-right.png',
            '/media/com_jem/images/back.png',
            '/media/com_jem/images/blank.png',
            '/media/com_jem/images/calendar_copy.png',
            '/media/com_jem/images/calendar_edit.png',
            '/media/com_jem/images/category.png',
            '/media/com_jem/images/clear.png',
            '/media/com_jem/images/close.png',
            '/media/com_jem/images/closelabel.gif',
            '/media/com_jem/images/defaultcolor.jpg',
            '/media/com_jem/images/disabled.png',
            '/media/com_jem/images/download_16.png',
            '/media/com_jem/images/edit.png',
            '/media/com_jem/images/el.png',
            '/media/com_jem/images/emailButton.png',
            '/media/com_jem/images/export_excel.png',
            '/media/com_jem/images/featured.png',
            '/media/com_jem/images/iCal2.0.png',
            '/media/com_jem/images/icon-16-back.png',
            '/media/com_jem/images/icon-16-blank.png',
            '/media/com_jem/images/icon-16-hint.png',
            '/media/com_jem/images/icon-16-info.png',
            '/media/com_jem/images/icon-16-new.png',
            '/media/com_jem/images/icon-16-recurrence.png',
            '/media/com_jem/images/icon-16-recurrence-first.png',
            '/media/com_jem/images/icon-16-warning.png',
            '/media/com_jem/images/icon-32-contacts-categories.png',
            '/media/com_jem/images/icon-32-recurrence.png',
            '/media/com_jem/images/icon-32-recurrence-first.png',
            '/media/com_jem/images/icon-32-tableexport.png',
            '/media/com_jem/images/icon-48-archive.png',
            '/media/com_jem/images/icon-48-categories.png',
            '/media/com_jem/images/icon-48-categoriesedit.png',
            '/media/com_jem/images/icon-48-cleancategoryimag.png',
            '/media/com_jem/images/icon-48-cleancategoryimg.png',
            '/media/com_jem/images/icon-48-cleaneventimg.png',
            '/media/com_jem/images/icon-48-cleanvenueimg.png',
            '/media/com_jem/images/icon-48-cssedit.png',
            '/media/com_jem/images/icon-48-cssmanager.png',
            '/media/com_jem/images/icon-48-eventedit.png',
            '/media/com_jem/images/icon-48-events.png',
            '/media/com_jem/images/icon-48-globe.png',
            '/media/com_jem/images/icon-48-groupedit.png',
            '/media/com_jem/images/icon-48-groups.png',
            '/media/com_jem/images/icon-48-help.png',
            '/media/com_jem/images/icon-48-home.png',
            '/media/com_jem/images/icon-48-housekeeping.png',
            '/media/com_jem/images/icon-48-housekeeing.png',
            '/media/com_jem/images/icon-48-latest-version.png',
            '/media/com_jem/images/icon-48-plugins.png',
            '/media/com_jem/images/icon-48-sampledata.png',
            '/media/com_jem/images/icon-48-settings.png',
            '/media/com_jem/images/icon-48-tableexport.png',
            '/media/com_jem/images/icon-48-tableimport.png',
            '/media/com_jem/images/icon-48-truncatealldata.png',
            '/media/com_jem/images/icon-48-unknown-version.png',
            '/media/com_jem/images/icon-48-unknown-versino.png',
            '/media/com_jem/images/icon-48-update.png',
            '/media/com_jem/images/icon-48-users.png',
            '/media/com_jem/images/icon-48-venues.png',
            '/media/com_jem/images/icon-48-venuesedit.png',
            '/media/com_jem/images/icon-48-venuesedit_2.png',
            '/media/com_jem/images/invited.png',
            '/media/com_jem/images/jem.png',
            '/media/com_jem/images/jemlogo.png',
            '/media/com_jem/images/loading.png',
            '/media/com_jem/images/loading.gif',
            '/media/com_jem/images/map_icon.png',
            '/media/com_jem/images/mapsicon.png',
            '/media/com_jem/images/marker.png',
            '/media/com_jem/images/next.png',
            '/media/com_jem/images/noimage.png',
            '/media/com_jem/images/PayPal_DonateButton.png',
            '/media/com_jem/images/prev.png',
            '/media/com_jem/images/printButton.png',
            '/media/com_jem/images/publish.png',
            '/media/com_jem/images/publish_r.png',
            '/media/com_jem/images/publish_x.png',
            '/media/com_jem/images/publish_y.png',
            '/media/com_jem/images/submitevent.png',
            '/media/com_jem/images/tick.png',
            '/media/com_jem/images/trash.png',
            '/media/com_jem/images/unlimited.png',
            '/media/com_jem/images/unpublish.png',
            '/media/com_jem/images/user.png',
            '/media/com_jem/images/users.png',
            '/media/com_jem/images/venue.png',
            '/media/com_jem/images/venue_add_btn_left.png',
            '/media/com_jem/images/venue_reset_btn_left.png',
            '/media/com_jem/images/venue_select_btn_left.png',
            '/media/com_jem/images/venue_select_btn_right.png',
            '/modules/mod_jem/language/en-GB/en-GB.mod_jem.ini',
            '/modules/mod_jem/language/en-GB/en-GB.mod_jem.sys.ini',
            '/modules/mod_jem/tmpl/mod_jem.css',
            '/modules/mod_jem/tmpl/mod_jem_responsive.css',
            '/modules/mod_jem/tmpl/mod_jem_table.css',
            '/modules/mod_jem/tmpl/mod_jem_table-advanced.css',
            '/modules/mod_jem/tmpl/mod_jem_table-style.css',
            '/modules/mod_jem_banner/language/en-GB/en-GB.mod_jem_banner.ini',
            '/modules/mod_jem_banner/language/en-GB/en-GB.mod_jem_banner.sys.ini',
            '/modules/mod_jem_banner/tmpl/mod_jem_banner.css',
            '/modules/mod_jem_banner/tmpl/mod_jem_banner_cards.css',
            '/modules/mod_jem_banner/tmpl/mod_jem_banner_iconfont.css',
            '/modules/mod_jem_banner/tmpl/mod_jem_banner_iconimg.css',
            '/modules/mod_jem_banner/tmpl/mod_jem_banner_responsive.css',
            '/modules/mod_jem_banner/tmpl/mod_jem_banner_table-advanced.css',
            '/modules/mod_jem_banner/tmpl/img/building.png',
            '/modules/mod_jem_banner/tmpl/img/cal.png',
            '/modules/mod_jem_banner/tmpl/img/cal1.png',
            '/modules/mod_jem_banner/tmpl/img/calendar_alpha.png',
            '/modules/mod_jem_banner/tmpl/img/calendar_blue.png',
            '/modules/mod_jem_banner/tmpl/img/calendar_green.png',
            '/modules/mod_jem_banner/tmpl/img/calendar_orange.png',
            '/modules/mod_jem_banner/tmpl/img/calendar_red.png',
            '/modules/mod_jem_banner/tmpl/img/category.png',
            '/modules/mod_jem_banner/tmpl/img/date.png',
            '/modules/mod_jem_banner/tmpl/img/digg.png',
            '/modules/mod_jem_banner/tmpl/img/facebook.png',
            '/modules/mod_jem_banner/tmpl/img/flag_red.png',
            '/modules/mod_jem_banner/tmpl/img/time.png',
            '/modules/mod_jem_banner/tmpl/img/twitter.png',
            '/modules/mod_jem_banner/tmpl/img/venue.png',
            '/modules/mod_jem_cal/language/en-GB/en-GB.mod_jem_cal.ini',
            '/modules/mod_jem_cal/language/en-GB/en-GB.mod_jem_cal.sys.ini',
            '/modules/mod_jem_cal/tmpl/mod_jem_cal.css',
            '/modules/mod_jem_cal/tmpl/mod_jem_cal_darkblue.css',
            '/modules/mod_jem_jubilee/language/en-GB/en-GB.mod_jem_jubilee.ini',
            '/modules/mod_jem_jubilee/language/en-GB/en-GB.mod_jem_jubilee.sys.ini',
            '/modules/mod_jem_jubilee/tmpl/mod_jem_jubilee.css',
            '/modules/mod_jem_jubilee/tmpl/mod_jem_jubilee_iconfont.css',
            '/modules/mod_jem_jubilee/tmpl/mod_jem_jubilee_iconimg.css',
            '/modules/mod_jem_jubilee/tmpl/img/building.png',
            '/modules/mod_jem_jubilee/tmpl/img/cal.png',
            '/modules/mod_jem_jubilee/tmpl/img/calendar_alpha.png',
            '/modules/mod_jem_jubilee/tmpl/img/calendar_blue.png',
            '/modules/mod_jem_jubilee/tmpl/img/calendar_green.png',
            '/modules/mod_jem_jubilee/tmpl/img/calendar_orange.png',
            '/modules/mod_jem_jubilee/tmpl/img/calendar_red.png',
            '/modules/mod_jem_jubilee/tmpl/img/category.png',
            '/modules/mod_jem_jubilee/tmpl/img/date.png',
            '/modules/mod_jem_jubilee/tmpl/img/flag_red.png',
            '/modules/mod_jem_jubilee/tmpl/img/time.png',
            '/modules/mod_jem_jubilee/tmpl/img/venue.png',
            '/modules/mod_jem_teaser/language/en-GB/en-GB.mod_jem_teaser.ini',
            '/modules/mod_jem_teaser/language/en-GB/en-GB.mod_jem_teaser.sys.ini',
            '/modules/mod_jem_teaser/tmpl/mod_jem_teaser.css',
            '/modules/mod_jem_teaser/tmpl/mod_jem_teaser_iconfont.css',
            '/modules/mod_jem_teaser/tmpl/mod_jem_teaser_iconimg.css',
            '/modules/mod_jem_teaser/tmpl/img/building.png',
            '/modules/mod_jem_teaser/tmpl/img/cal.png',
            '/modules/mod_jem_teaser/tmpl/img/calendar_alpha.png',
            '/modules/mod_jem_teaser/tmpl/img/calendar_blue.png',
            '/modules/mod_jem_teaser/tmpl/img/calendar_green.png',
            '/modules/mod_jem_teaser/tmpl/img/calendar_orange.png',
            '/modules/mod_jem_teaser/tmpl/img/calendar_red.png',
            '/modules/mod_jem_teaser/tmpl/img/category.png',
            '/modules/mod_jem_teaser/tmpl/img/date.png',
            '/modules/mod_jem_teaser/tmpl/img/digg.png',
            '/modules/mod_jem_teaser/tmpl/img/facebook.png',
            '/modules/mod_jem_teaser/tmpl/img/flag_red.png',
            '/modules/mod_jem_teaser/tmpl/img/time.png',
            '/modules/mod_jem_teaser/tmpl/img/twitter.png',
            '/modules/mod_jem_teaser/tmpl/img/venue.png',
            '/modules/mod_jem_wide/language/en-GB/en-GB.mod_jem_wide.ini',
            '/modules/mod_jem_wide/language/en-GB/en-GB.mod_jem_wide.sys.ini',
            '/modules/mod_jem_wide/tmpl/mod_jem_wide_iconfont.css',
            '/modules/mod_jem_wide/tmpl/mod_jem_wide_iconimg.css',
            '/modules/mod_jem_wide/tmpl/img/building.png',
            '/modules/mod_jem_wide/tmpl/img/category.png',
            '/modules/mod_jem_wide/tmpl/img/date.png',
            '/modules/mod_jem_wide/tmpl/img/flag_red.png',
            '/modules/mod_jem_wide/tmpl/img/time.png',
            '/modules/mod_jem_wide/tmpl/img/venue.png',
            '/modules/mod_jem_wide/tmpl/mod_jem_wide.css',
            '/plugins/content/jem/language/en-GB/en-GB.plg_content_jem.ini',
            '/plugins/content/jem/language/en-GB/en-GB.plg_content_jem.sys.ini',
            '/plugins/content/jemlistevents/language/en-GB/en-GB.plg_content_jemlistevents.ini',
            '/plugins/content/jemlistevents/language/en-GB/en-GB.plg_content_jemlistevents.sys.ini',
            '/plugins/finder/jem/language/en-GB/en-GB.plg_finder_jem.ini',
            '/plugins/finder/jem/language/en-GB/en-GB.plg_finder_jem.sys.ini',
            '/plugins/jem/comments/language/en-GB/en-GB.plg_jem_comments.ini',
            '/plugins/jem/comments/language/en-GB/en-GB.plg_jem_comments.sys.ini',
            '/plugins/jem/mailer/language/en-GB/en-GB.plg_jem_mailer.ini',
            '/plugins/jem/mailer/language/en-GB/en-GB.plg_jem_mailer.sys.ini',
            '/plugins/search/jem/language/en-GB/en-GB.plg_search_jem.ini',
            '/plugins/search/jem/language/en-GB/en-GB.plg_search_jem.sys.ini',
        );

        // obsolete folders
        $folders = array(
            '/media/com_jem/FontAwesome',
            '/plugins/quickicon/jemquickicon',
            '/plugins/search/jem',
            '/media/com_jem/images/flags/w20-png',
            '/components/com_jem/common/views/tmpl/alternative',
            '/components/com_jem/views/attendees/tmpl/alternative',
            '/components/com_jem/views/categories/tmpl/alternative',
            '/components/com_jem/views/day/tmpl/alternative',
            '/components/com_jem/views/myattendances/tmpl/alternative',            
            '/components/com_jem/views/myevents/tmpl/alternative',
            '/components/com_jem/views/myvenues/tmpl/alternative',
            '/components/com_jem/views/search/tmpl/alternative',
            '/modules/mod_jem/tmpl/responsive',
            '/modules/mod_jem_banner/tmpl/responsive',
            '/modules/mod_jem_jubilee/tmpl/responsive',
            '/modules/mod_jem_wide/tmpl/responsive',
            '/modules/mod_jem_teaser/tmpl/responsive',
        );

        // delete files
        foreach ($files as $file) {
            if (is_file(JPATH_ROOT . $file) && !File::delete(JPATH_ROOT . $file)) {
                echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $file).'<br>';
            }
        }

        // delete folders
        foreach ($folders as $folder) {
            if (is_dir(JPATH_ROOT . $folder) && !Folder::delete(JPATH_ROOT . $folder)) {
                echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $folder).'<br>';
            }
        }
    }

    /**
     * Remove old SQL update files that no longer describe the current schema.
     *
     * Joomla's Database Check compares installed update files with the current
     * database structure, so leftovers from old JEM branches can report false
     * problems after a long upgrade path.
     *
     * @return void
     */
    private function deleteObsoleteUpdateSqlFiles()
    {
        $dirs = array(
            JPATH_ADMINISTRATOR . '/components/com_jem/sql/updates',
            JPATH_ADMINISTRATOR . '/components/com_jem/sql/updates/mysql',
        );

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = Folder::files($dir, '\.sql$', false, true);

            foreach ($files as $file) {
                $version = basename($file, '.sql');

                if (preg_match('/^\d+(?:\.\d+)+$/', $version) && version_compare($version, '4.4.1', 'lt')) {
                    File::delete($file);
                }
            }
        }
    }

    /**
     * Ensure some columns exist into JEM tables (database)
     *
     * @return void
     */
    private function checkColumnsIntoDatabase()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $existingTables = $db->getTableList();

        // Array the columns to check
        $columnsToCheck = [
            ['table' => '#__jem_categories', 'column' => 'emailacljl',    'definition' => "TINYINT NOT NULL DEFAULT '0' AFTER `email`"],
            ['table' => '#__jem_register',   'column' => 'places',        'definition' => "INT NOT NULL DEFAULT '1' AFTER `uid`"],
            ['table' => '#__jem_events',     'column' => 'requestanswer', 'definition' => "TINYINT(1) NOT NULL DEFAULT '0' AFTER `waitinglist`"],
            ['table' => '#__jem_attachments','column' => 'description',   'definition' => "VARCHAR(255) DEFAULT NULL AFTER `name`"],
            ['table' => '#__jem_attachments','column' => 'frontend',      'definition' => "TINYINT(1) NOT NULL DEFAULT '1' AFTER `icon`"],
            ['table' => '#__jem_attachments','column' => 'ordering',      'definition' => "INT(11) NOT NULL DEFAULT '0' AFTER `access`"]
        ];

        // check if the each column exists
        foreach ($columnsToCheck as $data) {
            $tableName = str_replace('#__', $db->getPrefix(), $data['table']);

            if (!in_array($tableName, $existingTables, true)) {
                continue;
            }

            $query = 'SHOW COLUMNS FROM ' . $db->quoteName($data['table']) . ' WHERE Field = ' . $db->quote($data['column']);
            $db->setQuery($query);
            $result = $db->loadResult();
            if (!$result) {
                // The column does not exist, so add it
                $alterQuery = 'ALTER TABLE ' . $db->quoteName($data['table']) . ' ADD COLUMN ' . $db->quoteName($data['column']) . ' ' . $data['definition'];
                $db->setQuery($alterQuery);
                $db->execute();
            }
        }

        if (in_array(str_replace('#__', $db->getPrefix(), '#__jem_special_days'), $existingTables, true)) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_special_days'))
                ->set($db->quoteName('show_dates') . ' = 0')
                ->where($db->quoteName('alias') . ' = ' . $db->quote('weekend'))
                ->where($db->quoteName('day_type') . ' = ' . $db->quote('Weekend'))
                ->where($db->quoteName('weekdays') . ' IN (' . $db->quote('0,6') . ', ' . $db->quote('6,0') . ')');
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Ensure css files are writable.
     * (they maybe read-only caused by CSS Manager)
     *
     * @return void
     */
    private function makeFilesWritable()
    {
        $path = Path::clean(JPATH_ROOT . '/media/com_jem/css');
        $files = Folder::files($path, '.*\.css', false, true); // all css files, full path
        foreach ($files as $fullpath) {
            if (is_file($fullpath)) {
                Path::setPermissions($fullpath);
            }
        }
    }

    /**
     * Deletes all JEM tables on database if option says so.
     *
     * @return void
     */
    private function removeAllJemTables()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $tables = array(
            '#__jem_attachments',
            '#__jem_categories',
            '#__jem_cats_event_relations',
            '#__jem_countries',
            '#__jem_capacity_pools',
            '#__jem_events',
            '#__jem_event_prices',
            '#__jem_groupmembers',
            '#__jem_groups',
            '#__jem_import_profiles',
            '#__jem_links',
            '#__jem_registration_history',
            '#__jem_register_history',
            '#__jem_reminders',
            '#__jem_notification_attempts',
            '#__jem_notifications_attempts',
            '#__jem_notifications',
            '#__jem_register',
            '#__jem_register_items',
            '#__jem_special_days',
            '#__jem_settings',
            '#__jem_config',
            '#__jem_types',
            '#__jem_tax_rates',
            '#__jem_venues'
        );
        foreach ($tables as $table) {
            try {
                $db->dropTable($table);
            } catch (Exception $ex) {
                // simply continue with next table
            }
        }
    }
}
