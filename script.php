<?php
/**
 * @version    4.2.3
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Registry\Registry;
use Joomla\CMS\Version;

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
            $imageDir . '/venues',
            $imageDir . '/venues/small'
        );

        // Check for existance of /images/jem directory
        if (Folder::exists(JPATH_SITE . $createDirs[0])) {
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
        $this->getHeader(); ?>
        <h2><?php echo Text::_('COM_JEM_UNINSTALL_STATUS'); ?>:</h2>
        <p><?php echo Text::_('COM_JEM_UNINSTALL_TEXT'); ?></p>
        <?php

        $this->useJemConfig = true; // since 2.1.6
        $globalParams = $this->getGlobalParams();
        $cleanup = $globalParams->get('global_cleanup_db_on_uninstall', 0);
        if (!empty($cleanup)) {
            // user decided to fully remove JEM - so do it!
            $this->removeJemMenuItems();
            $this->removeAllJemTables();
            $imageDir = JPATH_SITE . '/images/jem';
            if (Folder::exists($imageDir)) {
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
        $app = Factory::getApplication();
        // Are we installing in J4.0?
        $jversion = new Version();
        $current_version = Version::MAJOR_VERSION . '.' . Version::MINOR_VERSION;
        $devLevel = Version::PATCH_VERSION;
        $this->newRelease = (string)$parent->manifest->version;

        if (version_compare(JVERSION, '6.0.0', 'ge') || // J! 6.x NOT supported, but allow alpha/beta
            !(($current_version >= '4.3' && $devLevel >= '0') ||
                ($current_version >= '4.2' && $devLevel >= '9') ||
                ($current_version == '4.1' && $devLevel >= '5') ||
                ($current_version == '4.0' && $devLevel >= '6'))) {
            $app->enqueueMessage(Text::_('COM_JEM_PREFLIGHT_WRONG_JOOMLA_VERSION'), 'warning');
            return false;
        }

        // Minimum required PHP version
        $minPhpVersion = "8.0.0";

        // Abort if PHP release is older than required version
        if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
            $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_WRONG_PHP_VERSION', $minPhpVersion, PHP_VERSION), 'warning');
            return false;
        }

        // abort if the release being installed is not newer than the currently installed version
        if (strtolower($type) == 'update') {
            // Installed component version
            $this->oldRelease = $this->getParam('version');
            // Installing component version as per Manifest file
            // $this->newRelease = $parent->get('manifest')->version;
            if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
                $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_INCORRECT_VERSION_SEQUENCE', $this->oldRelease, $this->newRelease), 'warning');
                return false;
            }

            // Check and remove obsolete files and folder
            $this->deleteObsoleteFiles();

            // Check columns in database
            $this->checkColumnsIntoDatabase();

            // Verify the data type of 'unregistra_until' in the database
            if ($this->oldRelease < '4.3.1') {
                $this->checkUnregistraUntil();
            }

            // Ensure css files are (over)writable
            $this->makeFilesWritable();

            // Initialize schema table if necessary
            $this->initializeSchema($this->oldRelease);
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
        // $type is the type of change (install, update or discover_install)
        echo '<p>' . Text::_('COM_JEM_POSTFLIGHT_' . strtoupper($type) . '_TEXT') . '</p>';

        if (strtolower($type) == 'update') {
            // Changes between 2.3.5 -> 4.0
            if (version_compare($this->oldRelease, '4.0', 'lt') && version_compare($this->newRelease, '2.3.5', 'gt')) {
                // change categoriesdetailed view name in menu items
                $this->updateJem2315();
            }
        } elseif (strtolower($type) == 'install') {
            $this->fixJemMenuItems();
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
        $query->select('manifest_cache')->from('#__extensions')->where(array("type = 'component'", "element = 'com_jem'"));
        $db->setQuery($query);
        $manifest = json_decode($db->loadResult(), true);
        return $manifest[$name];
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
            $query->select('params')->from('#__extensions')->where(array("type = 'component'", "element = 'com_jem'"));
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
                ->where(array("type = 'component'", "element = 'com_jem'"));
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Gets globalattrib values from the settings table
     *
     * @return JRegistry object
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
        ?>
        <img src="../media/com_jem/images/jemlogo.svg" alt="JEM - Joomla Event Manager" style="float:left; padding-right:20px;height: 160px;width: 396px;background-color:#fff;border-radius: 8px;" />
        <h1><?php echo Text::_('COM_JEM'); ?></h1>
        <p class="small"><?php echo Text::_('COM_JEM_INSTALLATION_HEADER'); ?></p>
        <?php
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
        $query->select('extension_id')->from('#__extensions')->where(array("type='component'", "element='com_jem'"));
        $db->setQuery($query);
        $extensionId = $db->loadResult();

        if (!$extensionId) {
            // This is a fresh installation, return
            return;
        }

        // Check if an entry already exists in schemas table
        $query = $db->getQuery(true);
        $query->select('version_id')->from('#__schemas')->where('extension_id = ' . $extensionId);
        $db->setQuery($query);

        if ($db->loadResult()) {
            // Entry exists, return
            return;
        }

        // Insert extension ID and old release version number into schemas table
        $query = $db->getQuery(true);
        $query->insert('#__schemas')
            ->columns($db->quoteName(array('extension_id', 'version_id')))
            ->values(implode(',', array($extensionId, $db->quote($versionId))));

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
        $query->where(array('client_id = 0', 'link LIKE "index.php?option=com_jem%"'));
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
        $query->where(array('client_id = 0', 'published > 0', 'link LIKE "index.php?option=com_jem%"'));
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
        $query->select('extension_id')->from('#__extensions')->where(array("type='component'", "element='com_jem'"));
        $db->setQuery($query);
        $newId = $db->loadResult();

        if ($newId) {
            // set compponent id on all "com_jem..." frontend entries
            $query = $db->getQuery(true);
            $query->update('#__menu');
            $query->set('component_id = ' . $db->quote($newId));
            $query->where(array('client_id = 0', 'link LIKE "index.php?option=com_jem%"'));
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
            '/administrator/components/com_jem/sql/updates/2.0.0.sql',
            '/administrator/components/com_jem/sql/updates/2.0.1.sql',
            '/administrator/components/com_jem/sql/updates/2.0.2.sql',
            '/administrator/components/com_jem/sql/updates/2.0.3.sql',
            '/administrator/components/com_jem/sql/updates/2.1.0.sql',
            '/administrator/components/com_jem/sql/updates/2.1.1.sql',
            '/administrator/components/com_jem/sql/updates/2.1.2.sql',
            '/administrator/components/com_jem/sql/updates/2.1.3.sql',
            '/administrator/components/com_jem/sql/updates/2.1.4.sql',
            '/administrator/components/com_jem/sql/updates/2.1.4.1.sql',
            '/administrator/components/com_jem/sql/updates/2.1.4.2.sql',
            '/administrator/components/com_jem/sql/updates/2.1.5.sql',
            '/administrator/components/com_jem/sql/updates/2.1.6-dev3.sql',
            '/administrator/components/com_jem/sql/updates/2.1.6-dev5.sql',
            '/administrator/components/com_jem/sql/updates/2.1.7-dev1.sql',
            '/administrator/components/com_jem/sql/updates/2.1.7-dev5.sql',
            '/administrator/components/com_jem/sql/updates/2.2.0-p1.sql',
            '/administrator/components/com_jem/sql/updates/2.2.1-dev2.sql',
            '/administrator/components/com_jem/sql/updates/2.2.3-dev3.sql',
            '/administrator/components/com_jem/sql/updates/2.3.0-beta2.sql',
            '/administrator/components/com_jem/sql/updates/2.3.0-dev1.sql',
            '/administrator/components/com_jem/sql/updates/2.3.1.sql',
            '/administrator/language/en-GB/en-GB.plg_content_jem.ini',
            '/administrator/language/en-GB/en-GB.plg_content_jem.sys.ini',
            '/administrator/language/en-GB/en-GB.plg_finder_jem.ini',
            '/components/com_jem/language/en-GB/en-GB.com_jem.ini',
            '/language/en-GB/en-GB.pkg_jem.sys.ini',
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
            '/media/com_jem/images/icon-32-recurrence.png',
            '/media/com_jem/images/icon-32-recurrence-first.png',
            '/media/com_jem/images/icon-32-tableexport.png',
            '/media/com_jem/images/icon-48-archive.png',
            '/media/com_jem/images/icon-48-categories.png',
            '/media/com_jem/images/icon-48-categoriesedit.png',
            '/media/com_jem/images/icon-48-cleancategoryimag.png',
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
            '/media/com_jem/images/icon-48-housekeeing.png',
            '/media/com_jem/images/icon-48-latest-version.png',
            '/media/com_jem/images/icon-48-plugins.png',
            '/media/com_jem/images/icon-48-sampledata.png',
            '/media/com_jem/images/icon-48-settings.png',
            '/media/com_jem/images/icon-48-tableexport.png',
            '/media/com_jem/images/icon-48-tableimport.png',
            '/media/com_jem/images/icon-48-truncatealldata.png',
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
            '/modules/mod_jem_banner/language/en-GB/en-GB.mod_jem_banner.ini',
            '/modules/mod_jem_banner/language/en-GB/en-GB.mod_jem_banner.sys.ini',
            '/modules/mod_jem_cal/language/en-GB/en-GB.mod_jem_cal.ini',
            '/modules/mod_jem_cal/language/en-GB/en-GB.mod_jem_cal.sys.ini',
            '/modules/mod_jem_jubilee/language/en-GB/en-GB.mod_jem_jubilee.ini',
            '/modules/mod_jem_jubilee/language/en-GB/en-GB.mod_jem_jubilee.sys.ini',
            '/modules/mod_jem_teaser/language/en-GB/en-GB.mod_jem_teaser.ini',
            '/modules/mod_jem_teaser/language/en-GB/en-GB.mod_jem_teaser.sys.ini',
            '/modules/mod_jem_wide/language/en-GB/en-GB.mod_jem_wide.ini',
            '/modules/mod_jem_wide/language/en-GB/en-GB.mod_jem_wide.sys.ini',
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
            '/media/com_jem/images/flags/w20-png',
            '/components/com_jem/common/views/tmpl/alternative',
            '/components/com_jem/views/attendees/tmpl/alternative',
            '/components/com_jem/views/categories/tmpl/alternative',
            '/components/com_jem/views/day/tmpl/alternative',
            '/components/com_jem/views/myattendances/tmpl/alternative',            
             '/components/com_jem/views/myevents/tmpl/alternative',
             '/components/com_jem/views/myvenues/tmpl/alternative',
             '/components/com_jem/views/search/tmpl/alternative',
        );

        // delete files
        foreach ($files as $file) {
            if (File::exists(JPATH_ROOT . $file) && !File::delete(JPATH_ROOT . $file)) {
                echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $file).'<br>';
            }
        }

        // delete folders
        foreach ($folders as $folder) {
            if (Folder::exists(JPATH_ROOT . $folder) && !Folder::delete(JPATH_ROOT . $folder)) {
                echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $folder).'<br>';
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
        $query = $db->getQuery(true);

        // Array the columns to check
        $columnsToCheck = [
            ['table' => '#__jem_categories', 'column' => 'emailacljl',    'definition' => "TINYINT NOT NULL DEFAULT '0' AFTER `email`"],
            ['table' => '#__jem_register',   'column' => 'places',        'definition' => "INT NOT NULL DEFAULT '1' AFTER `uid`"],
            ['table' => '#__jem_events',     'column' => 'requestanswer', 'definition' => "TINYINT(1) NOT NULL DEFAULT '0' AFTER `waitinglist`"]
        ];

        // check if the each column exists
        foreach ($columnsToCheck as $data) {
            $query = "SHOW COLUMNS FROM " . $data['table'] . " WHERE Field ='" . $data['column'] . "'";
            $db->setQuery($query);
            $result = $db->loadResult();
            if (!$result) {
                // The column does not exist, so add it
                $alterQuery = "ALTER TABLE " . $data['table'] . " ADD COLUMN " . $data['column'] . " " . $data['definition'];
                $db->setQuery($alterQuery);
                $db->execute();
            }
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
     * Update data items related to datetime format into JEM.
     * (required when updating/migrating from 2.3.3/5/6 to new version 4.0.0 with support Joomla 4.x or newer)
     *
     * @return void
     */
    private function updateJem2315()
    {
        // write changed datetime entry '0000-00-00 ...' to null into DB
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        //Categories table
        $query = $db->getQuery(true);
        $query->update('#__jem_categories');
        $query->set("modified_time = null");
        $query->where(array("modified_time LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_categories');
        $query->set("checked_out_time = null");
        $query->where(array("checked_out_time LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_categories');
        $query->set("created_time = now()");
        $query->where(array("created_time LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        //Events table
        $query = $db->getQuery(true);
        $query->update('#__jem_events');
        $query->set("created = now()");
        $query->where(array("created LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_events');
        $query->set("modified = null");
        $query->where(array("modified LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_events');
        $query->set("checked_out_time = null");
        $query->where(array("checked_out_time LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        //Groups table
        $query = $db->getQuery(true);
        $query->update('#__jem_groups');
        $query->set("checked_out_time = null");
        $query->where(array("checked_out_time LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        //Venues table
        $query = $db->getQuery(true);
        $query->update('#__jem_venues');
        $query->set("created = now()");
        $query->where(array("created LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_venues');
        $query->set("modified = null");
        $query->where(array("modified LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_venues');
        $query->set("checked_out_time = null");
        $query->where(array("checked_out_time LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_venues');
        $query->set("publish_up = null");
        $query->where(array("publish_up LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true);
        $query->update('#__jem_venues');
        $query->set("publish_down = null");
        $query->where(array("publish_down LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();

        //Attachments table
        $query = $db->getQuery(true);
        $query->update('#__jem_attachments');
        $query->set("added = null");
        $query->where(array("added LIKE '%0000-00-00%'"));
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Delete JEM update server entry from #__update_sites table.
     *
     * @return void
     */
    private function removeUpdateServerEntry()
    {
        // Find entry and get id
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('update_site_id');
        $query->from('#__update_sites');
        $query->where(array("location LIKE '%joomlaeventmanager.invalid%'"));
        $db->setQuery($query);
        $id = $db->loadResult();

        if (!empty($id)) {
            // remove entry
            $query = $db->getQuery(true);
            $query->delete('#__update_sites');
            $query->where(array('update_site_id = ' . $db->quote($id)));
            $db->setQuery($query);
            $db->execute();

            // but also from this table
            $query = $db->getQuery(true);
            $query->delete('#__update_sites_extensions');
            $query->where(array('update_site_id = ' . $db->quote($id)));
            $db->setQuery($query);
            $db->execute();
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
            '#__jem_events',
            '#__jem_groupmembers',
            '#__jem_groups',
            '#__jem_register',
            '#__jem_settings',
            '#__jem_config',
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

    /**
     * Verify the data type of 'unregistra_until' in the database when JEM version < 4.3.1
     *
     * @return void
     */
    private function checkUnregistraUntil()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $query = "ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` INT(11) NULL DEFAULT '0'";
            $db->setQuery($query);
            $db->execute();

            $query = "UPDATE `#__jem_events` SET `unregistra_until` = NULL WHERE `unregistra_until` = 0";
            $db->setQuery($query);
            $db->execute();

            $query = "UPDATE `#__jem_events` SET `unregistra_until` = NULL WHERE `unregistra_until` != 0 AND (times IS NULL OR dates IS NULL)";
            $db->setQuery($query);
            $db->execute();

            $query = "ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` VARCHAR(20) NULL";
            $db->setQuery($query);
            $db->execute();

            $query = "UPDATE `#__jem_events` SET `unregistra_until` = DATE_FORMAT(DATE_SUB(CONCAT(`dates`, ' ', `times`), INTERVAL `unregistra_until` HOUR),'%Y-%m-%d %H:%i:%s') WHERE `unregistra_until` != 0 AND `times` IS NOT NULL AND `dates` IS NOT NULL";
            $db->setQuery($query);
            $db->execute();

            $query = "ALTER TABLE `#__jem_events` CHANGE `unregistra_until` `unregistra_until` DATETIME DEFAULT NULL";
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            echo "Error updating `unregistra_until`: " . $e->getMessage();
        }
    }

}
