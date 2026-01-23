<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Registry\Registry;
use Joomla\CMS\Version;
use Joomla\CMS\Uri\Uri;

// Joomla 6 - new file system classes
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
        $error = ['summary' => 0, 'folders' => 0];
        $this->useJemConfig = true;
        $this->getHeader();

        echo '<h2>' . Text::_('COM_JEM_INSTALL_STATUS') . ':</h2>';
        echo '<h3>' . Text::_('COM_JEM_INSTALL_CHECK_FOLDERS') . ':</h3>';

        $imageDir = "/images/jem";
        $createDirs = [
            $imageDir,
            $imageDir . '/categories',
            $imageDir . '/categories/small',
            $imageDir . '/events',
            $imageDir . '/events/small',
            $imageDir . '/venues',
            $imageDir . '/venues/small'
        ];

        // Check for existance of /images/jem directory
        if (Folder::exists(JPATH_SITE . $createDirs[0])) {
            echo "<p><span style='color:green;'>" . Text::_('COM_JEM_INSTALL_SUCCESS') . ":</span> " .
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_EXISTS_SKIP', $createDirs[0]) . "</p>";
        } else {
            echo "<p><span style='color:orange;'>" . Text::_('COM_JEM_INSTALL_INFO') . ":</span> " .
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_EXISTS', $createDirs[0]) . "</p>";
            echo "<p>" . Text::_('COM_JEM_INSTALL_DIRECTORY_TRY_CREATE') . ":</p>";

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
        }

        if ($error['folders']) {
            echo "<p>" . Text::_('COM_JEM_INSTALL_DIRECTORY_CHECK_EXISTANCE') . "</p>";
        }

        echo "<h3>" . Text::_('COM_JEM_INSTALL_SETTINGS') . "</h3>";

        $db = Factory::getContainer()->get('DatabaseDriver');
        try {
            $query = $db->getQuery(true)->select('*')->from('#__jem_config');
            $db->setQuery($query);
            $conf = $db->loadAssocList();

            if (count($conf)) {
                echo "<p><span style='color:green;'>" . Text::_('COM_JEM_INSTALL_SUCCESS') . ":</span> " .
                    Text::_('COM_JEM_INSTALL_FOUND_SETTINGS') . "</p>";
            }
        } catch (\Exception $e) {
            echo "<p style='color:red;'>DB Error: " . $e->getMessage() . "</p>";
        }

        echo "<h3>" . Text::_('COM_JEM_INSTALL_SUMMARY') . "</h3>";

        foreach ($error as $k => $v) {
            if ($k !== 'summary') {
                $error['summary'] += $v;
            }
        }

        if ($error['summary']) {
            echo "<p style='color:red;'><b>" . Text::_('COM_JEM_INSTALL_INSTALLATION_NOT_SUCCESSFUL') . "</b></p>";
        } else {
            echo "<p style='color:green;'><b>" . Text::_('COM_JEM_INSTALL_INSTALLATION_SUCCESSFUL') . "</b></p>";
        }

        $this->setGlobalAttribs([
            "event_comunoption"=>"0",
            "event_comunsolution"=>"0",
            "event_show_attendeenames"=>"2",
            "event_show_more_attendeedetails"=>"0",
            "event_show_venue_name"=>"1",
            "event_show_category"=>"1",
            "event_link_category"=>"1",
            "event_show_author"=>"1",
            "event_lg"=>"",
            "event_link_author"=>"1",
            "event_show_contact"=>"1",
            "event_link_contact"=>"1",
            "event_show_description"=>"1",
            "event_show_detailsadress"=>"1",
            "event_show_detailstitle"=>"1",
            "event_show_detlinkvenue"=>"1",
            "event_show_hits"=>"0",
            "event_show_locdescription"=>"1",
            "event_show_mapserv"=>"0",
            "event_show_print_icon"=>"1",
            "event_show_email_icon"=>"1",
            "event_show_ical_icon"=>"1",
            "event_tld"=>"",
            "editevent_show_meta_option"=>"0",
            "editevent_show_attachment_tab"=>"0",
            "editevent_show_other_tab"=>"0",
            "global_display"=>"1",
            "global_editevent_starttime_limit"=>"0",
            "global_editevent_endtime_limit"=>"23",
            "global_editevent_minutes_block"=>"1",
            "global_regname"=>"1",
            "global_show_archive_icon"=>"1",
            "global_show_filter"=>"1",
            "global_show_email_icon"=>"1",
            "global_show_ical_icon"=>"1",
            "global_show_icons"=>"1",
            "global_show_locdescription"=>"1",
            "global_show_print_icon"=>"1",
            "global_show_timedetails"=>"1",
            "global_show_detailsadress"=>"1",
            "global_show_detlinkvenue"=>"1",
            "global_show_listevents"=>"1",
            "global_show_mapserv"=>"0",
            "global_tld"=>"",
            "global_lg"=>"",
            "global_cleanup_db_on_uninstall"=>"0"
        ]);
    }

    /* … Rest der Datei unverändert, außer: */

    /**
     * Verify the data type of 'unregistra_until' in the database when JEM version < 4.3.1
     *
     * @return void
     */
    private function checkUnregistraUntil()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');              
        try {
            $db->setQuery("ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` INT(11) NULL DEFAULT '0'")->execute();
            $db->setQuery("UPDATE `#__jem_events` SET `unregistra_until` = NULL WHERE `unregistra_until` = 0")->execute();
            $db->setQuery("UPDATE `#__jem_events` SET `unregistra_until` = NULL WHERE `unregistra_until` != 0 AND (times IS NULL OR dates IS NULL)")->execute();
            $db->setQuery("ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` VARCHAR(20) NULL")->execute();
            $db->setQuery("UPDATE `#__jem_events` SET `unregistra_until` = DATE_FORMAT(DATE_SUB(CONCAT(`dates`, ' ', `times`), INTERVAL `unregistra_until` HOUR),'%Y-%m-%d %H:%i:%s') WHERE `unregistra_until` != 0 AND `times` IS NOT NULL AND `dates` IS NOT NULL")->execute();
            $db->setQuery("ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` DATETIME DEFAULT NULL")->execute();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_ERROR_UPDATING_UNREGISTRA_UNTIL', $e->getMessage()),
                'error'
            );
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
        $tables = [
            '#__jem_attachments',
            '#__jem_categories',
            '#__jem_cats_event_relations',
            '#__jem_countries',
            '#__jem_events',
            '#__jem_groupmembers',
            '#__jem_groups',
            '#__jem_register',
            '#__jem_settings',
            '#__jem_config',
            '#__jem_venues'
        ];

        foreach ($tables as $table) {
            try {
                $db->dropTable($table);
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }
    }
}
