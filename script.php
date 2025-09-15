<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
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

// For Joomla 6 - new file system classes
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

        echo "<h2>".Text::_('COM_JEM_INSTALL_STATUS').":</h2>";
        echo "<h3>".Text::_('COM_JEM_INSTALL_CHECK_FOLDERS').":</h3>";

        $imageDir = "/images/jem";
        $createDirs = [
            $imageDir,
            $imageDir.'/categories',
            $imageDir.'/categories/small',
            $imageDir.'/events',
            $imageDir.'/events/small',
            $imageDir.'/venues',
            $imageDir.'/venues/small'
        ];

        // Check for existance of /images/jem directory
        if (is_dir(JPATH_SITE.$createDirs[0])) {
            echo "<p><span style='color:green;'>".Text::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_EXISTS_SKIP', $createDirs[0])."</p>";
        } else {
            echo "<p><span style='color:orange;'>".Text::_('COM_JEM_INSTALL_INFO').":</span> ".
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_EXISTS', $createDirs[0])."</p>";
            echo "<p>".Text::_('COM_JEM_INSTALL_DIRECTORY_TRY_CREATE').":</p><ul>";

            // Folder creation
            foreach($createDirs as $directory) {
                if (@mkdir(JPATH_SITE.$directory, 0755, true)) {
                    echo "<li><span style='color:green;'>".Text::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
                        Text::sprintf('COM_JEM_INSTALL_DIRECTORY_CREATED', $directory)."</li>";
                } else {
                    echo "<li><span style='color:red;'>".Text::_('COM_JEM_INSTALL_ERROR').":</span> ".
                        Text::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_CREATED', $directory)."</li>";
                    $error['folders']++;
                }
            }
            echo "</ul>";
        }

        if($error['folders']) {
            echo "<p>".Text::_('COM_JEM_INSTALL_DIRECTORY_CHECK_EXISTANCE')."</p>";
        }

        echo "<h3>".Text::_('COM_JEM_INSTALL_SETTINGS')."</h3>";

        $db = Factory::getContainer()->get('DatabaseDriver');
        try {
            $query = $db->getQuery(true)->select('*')->from('#__jem_config');
        $db->setQuery($query);
        $conf = $db->loadAssocList();
            if (is_array($conf) && count($conf)) {
            echo "<p><span style='color:green;'>".Text::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
                Text::_('COM_JEM_INSTALL_FOUND_SETTINGS')."</p>";
        }
        } catch (\Exception $e) {
            echo "<p style='color:red;'>DB Error: ".$e->getMessage()."</p>";
            }

        echo "<h3>".Text::_('COM_JEM_INSTALL_SUMMARY')."</h3>";
        $error['summary'] = $error['folders'];
        if($error['summary']) {
            echo "<p style='color:red;'><b>".Text::_('COM_JEM_INSTALL_INSTALLATION_NOT_SUCCESSFUL')."</b></p>";
        } else {
            echo "<p style='color:green;'><b>".Text::_('COM_JEM_INSTALL_INSTALLATION_SUCCESSFUL')."</b></p>";
        }

        $param_array = [
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
        ];

        $this->setGlobalAttribs($param_array);
    }

    /**
     * method to uninstall the component
     *
     * @return void
     */
    public function uninstall($parent)
    {
        $this->getHeader();
        echo "<h2>".Text::_('COM_JEM_UNINSTALL_STATUS').":</h2>";
        echo "<p>".Text::_('COM_JEM_UNINSTALL_TEXT')."</p>";

        $this->useJemConfig = true; // since 2.1.6
        $globalParams = $this->getGlobalParams();
        $cleanup = $globalParams->get('global_cleanup_db_on_uninstall', 0);

        if (!empty($cleanup)) {
            // user decided to fully remove JEM - so do it!
            $this->removeJemMenuItems();
            $this->removeAllJemTables();
            $this->removeUpdateServerEntry();
            $imageDir = JPATH_SITE.'/images/jem';
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
    public function update($parent)
    {
        $this->getHeader();
        echo "<h2>".Text::_('COM_JEM_UPDATE_STATUS').":</h2>";
        echo "<p>".Text::sprintf('COM_JEM_UPDATE_TEXT', $parent->getManifest()->version)."</p>";
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

        // Get Joomla version
        $jversion = new Version();
        $current_version = Version::MAJOR_VERSION . '.' . Version::MINOR_VERSION . '.' . Version::PATCH_VERSION;

        // Version of the extension to be installed (from manifest)
        $this->newRelease = (string) $parent->manifest->version;

        // Allow only Joomla >= 6.0.0-alpha and < 7.0.0 (so Joomla 6.x and 7.x)
        if (!(version_compare($current_version, '6.0.0', '>=') && version_compare($current_version, '7.0.0', '<'))) {
            $app->enqueueMessage(Text::_('COM_JEM_PREFLIGHT_WRONG_JOOMLA_VERSION'), 'warning');
            return false;
        }

        // Minimum required PHP version
        $minPhpVersion = '8.3.0';

        // Abort if PHP version is lower than required
        if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
            $app->enqueueMessage(Text::sprintf('COM_JEM_PREFLIGHT_WRONG_PHP_VERSION', $minPhpVersion, PHP_VERSION), 'warning');
            return false;
        }

        // Additional checks when updating
        if (strtolower($type) == 'update') {
            // Currently installed extension version
            $this->oldRelease = $this->getParam('version');

            // Abort if the new version is older than the currently installed one
            if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
                $app->enqueueMessage(
                    Text::sprintf('COM_JEM_PREFLIGHT_INCORRECT_VERSION_SEQUENCE', $this->oldRelease, $this->newRelease),
                    'warning'
                );
                return false;
            }

            // Remove obsolete files and folders
            $this->deleteObsoleteFiles();

            // Ensure required database columns exist
            $this->checkColumnsIntoDatabase();

            // Ensure CSS files are writable
            $this->makeFilesWritable();

            // Initialize schema table if necessary
            $this->initializeSchema($this->oldRelease);
        }

        // Show a success/info message depending on the operation type (install, update, discover_install)
        echo '<p>' . Text::_('COM_JEM_PREFLIGHT_' . strtoupper($type) . '_TEXT') . '</p>';
    }

    /**
     * Method to run after an install/update/uninstall method
     * (it seams method is not called on uninstall)
     *
     * @return void
     */
    public function postflight($type, $parent)
    {
        // $type is the type of change (install, update or discover_install)
        echo '<p>' . Text::_('COM_JEM_POSTFLIGHT_' . strtoupper($type) . '_TEXT') . '</p>';

        if (strtolower($type) == 'install') {
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
        try {
            $query = $db->getQuery(true)
                ->select('manifest_cache')
                ->from('#__extensions')
                ->where([
                    $db->quoteName('type') . ' = ' . $db->quote('component'), 
                    $db->quoteName('element') . ' = ' . $db->quote('com_jem')
                ]);
            $db->setQuery($query);
            $manifest = json_decode($db->loadResult() ?: '{}', true);
            return $manifest[$name] ?? null;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Sets parameter values in the component's row of the extension table
     *
     * @param $param_array  An array holding the params to store
     */
    private function setParams($param_array)
    {
        if (!is_array($param_array) || !count($param_array)) return;

        try {
            // read the existing component value(s)
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('params')
                ->from('#__extensions')
                ->where([
                    $db->quoteName('type') . ' = ' . $db->quote('component'),
                    $db->quoteName('element') . ' = ' . $db->quote('com_jem')
                ]);
            $db->setQuery($query);
            $params = json_decode($db->loadResult() ?: '{}', true);
            
            foreach ($param_array as $name => $value) {
                $params[(string)$name] = (string)$value;
            }

            // store the combined new and existing values back as a JSON string
            $paramsString = json_encode($params);
            $query = $db->getQuery(true)
                ->update('#__extensions')
                ->set('params = '.$db->quote($paramsString))
                ->where([
                    $db->quoteName('type') . ' = ' . $db->quote('component'),
                    $db->quoteName('element') . ' = ' . $db->quote('com_jem')
                ]);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

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
        $db = Factory::getContainer()->get('DatabaseDriver');
        try {
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query->select('value')->from('#__jem_config')
                    ->where($db->quoteName('keyname').' = '.$db->quote('globalattribs'));
            } else {
                $query->select('globalattribs')->from('#__jem_settings')->where('id=1');
            }
            $db->setQuery($query);
            $registry->loadString($db->loadResult() ?: '{}');
        } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');}
        return $registry;
    }

    /**
     * Sets globalattrib values in the settings table
     *
     * @param $param_array  An array holding the params to store
     */

     private function setGlobalAttribs($param_array)
    {
        if (!is_array($param_array) || !count($param_array)) {
            return;
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        try {
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query
                    ->select('value')
                    ->from('#__jem_config')
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('globalattribs'));
            } else {
                $query->select('globalattribs')->from('#__jem_settings');
            }
            $db->setQuery($query);
            $params = json_decode($db->loadResult() ?: '{}', true);

            // add the new variable(s) to the existing one(s)
            foreach ($param_array as $name => $value) {
                $params[(string) $name] = (string) $value;
            }

            // store the combined new and existing values back as a JSON string
            $paramsString = json_encode($params);
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query
                    ->update('#__jem_config')
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('globalattribs'))
                    ->set($db->quoteName('value') . ' = ' . $db->quote($paramsString));
            } else {
                $query->update('#__jem_settings')->set('globalattribs = ' . $db->quote($paramsString));
            }
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Helper method that outputs a short JEM header with logo and text
     */
    private function getHeader()
    {
        ?>
        <img src="../media/com_jem/images/jemlogo.webp" alt="JEM - Joomla Event Manager" style="float:left; padding-right:20px;" />
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
        try {
            // Get extension ID of JEM
            $query = $db->getQuery(true)
                ->select('extension_id')
                ->from('#__extensions')->where([
                    $db->quoteName('type') . ' = ' . $db->quote('component'),
                    $db->quoteName('element') . ' = ' . $db->quote('com_jem')
                ]);
            $db->setQuery($query);
            $extensionId = $db->loadResult();
            if (!$extensionId) return; // This is a fresh installation, return

            // Check if an entry already exists in schemas table
            $query = $db->getQuery(true)
                ->select('version_id')
                ->from('#__schemas')
                ->where('extension_id = '.$extensionId);
            $db->setQuery($query);
            if ($db->loadResult()) return; // Entry exists, return

            // Insert extension ID and old release version number into schemas table
            $query = $db->getQuery(true)
                ->insert('#__schemas')
                ->columns($db->quoteName(['extension_id','version_id']))
                ->values($extensionId.','.$db->quote($versionId));
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');}
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
        try {
            $query = $db->getQuery(true)
                ->delete('#__menu')
                ->where([
                    $db->quoteName('client_id') . ' = 0',
                    $db->quoteName('link') . ' LIKE "index.php?option=com_jem%"'
                ]);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');}
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
        try {
            $query = $db->getQuery(true)
                ->update('#__menu')
                ->set('published = 0')
                ->where([
                    $db->quoteName('client_id') . ' = 0',
                    $db->quoteName('published') . ' > 0',
                    $db->quoteName('link') . ' LIKE "index.php?option=com_jem%"'
                ]);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');}
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
        try {
            $query = $db->getQuery(true)
                ->select('extension_id')
                ->from('#__extensions')
                ->where([
                    $db->quoteName('type') . ' = ' . $db->quote('component'),
                    $db->quoteName('element') . ' = ' . $db->quote('com_jem')
                ]);
            $db->setQuery($query);
            $newId = $db->loadResult();
            if (!$newId) return;

            // set compponent id on all "com_jem..." frontend entries
            $query = $db->getQuery(true)
                ->update('#__menu')
                ->set('component_id = '.$db->quote($newId))
                ->where([
                    $db->quoteName('client_id') . ' = 0',
                    $db->quoteName('link') . ' LIKE "index.php?option=com_jem%"'
                ]);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {}
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
        $files = [
            // obsolete since JEM 4.0.0 - not neccessary anymore
            
            // remove old langage files with lang prefix
            '/language/en-GB/en-GB.pkg_jem.sys.ini',
            '/administrator/components/com_jem/language/en-GB/en-GB.com_jem.ini',
            '/administrator/components/com_jem/language/en-GB/en-GB.com_jem.sys.ini',
            '/components/com_jem/language/en-GB/en-GB.com_jem.ini',
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
            '/modules/mod_jem/language/en-GB/en-GB.mod_jem.ini',
            '/modules/mod_jem/language/en-GB/en-GB.mod_jem.sys.ini',
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
            '/administrator/language/en-GB/en-GB.plg_content_jem.ini',
            '/administrator/language/en-GB/en-GB.plg_content_jem.sys.ini',
            '/administrator/language/en-GB/en-GB.plg_finder_jem.ini',        
        ];

        // TODO There is an issue while deleting folders using the ftp mode
        $folders = [
            '/media/com_jem/FontAwesome',
            '/plugins/quickicon/jemquickicon',
        ];

        try {
            foreach ($files as $file) {
                $fullpath = JPATH_ROOT.$file;
                if (is_file($fullpath) && !@unlink($fullpath)) {
                    echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $file).'<br />';
                    }
                }

            foreach ($folders as $folder) {
                $fullpath = JPATH_ROOT.$folder;
                if (is_dir($fullpath) && !Folder::delete($fullpath)) {
                    echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $folder).'<br />';
                    }
                }
            }
            catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_ERROR_DELETING_OBSOLETE_FILES', $e->getMessage()),
            '    warning');
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

        // Array the columns to check
        $columnsToCheck = [
            ['table' => '#__jem_categories', 'column' => 'emailacljl',    'definition' => "TINYINT NOT NULL DEFAULT '0' AFTER `email`"],
            ['table' => '#__jem_register',   'column' => 'places',        'definition' => "INT NOT NULL DEFAULT '1' AFTER `uid`"],
            ['table' => '#__jem_events',     'column' => 'requestanswer', 'definition' => "TINYINT(1) NOT NULL DEFAULT '0' AFTER `waitinglist`"]
        ];

        // check if the each column exists
        try {
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
        } catch (\Exception $e) {
            Log::add('JEM Install DB Error: ' . $e->getMessage(), Log::ERROR, 'com_jem');
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
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
        try {
            $path = JPATH_ROOT.'/media/com_jem/css';
            foreach (glob($path.'/*.css') as $fullpath) {
                if (is_file($fullpath)) @chmod($fullpath, 0644);
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_ERROR_MAKING_FILES_WRITABLE', $e->getMessage()),
                'warning'
            );
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
            Factory::getApplication()->enqueueMessage(
            Text::sprintf('COM_JEM_ERROR_UPDATING_UNREGISTRA_UNTIL', $e->getMessage()),
            'error'
            );
        }
    }

    /**
     * Delete JEM update server entry from #__update_sites table.
     *
     * @return void
     */
    private function removeUpdateServerEntry()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        try {
            // Find entry and get id
            $query = $db->getQuery(true)
                ->select('update_site_id')
                ->from($db->quoteName('#__update_sites'))
                ->where([$db->quoteName('location') . ' LIKE ' . $db->quote('%joomlaeventmanager.invalid%')]);
            
            $db->setQuery($query);
            $id = $db->loadResult();

            if (empty($id)) {
                return;
            }

            // Remove from update_sites table
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites'))
                ->where([$db->quoteName('update_site_id') . ' = ' . $db->quote($id)]);
            $db->setQuery($query);
            $db->execute();

            // Remove from update_sites_extensions table
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites_extensions'))
                ->where([$db->quoteName('update_site_id') . ' = ' . $db->quote($id)]);
            $db->setQuery($query);
            $db->execute();
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
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
                // simply continue with next table
            }
        }
    } 
}
