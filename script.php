<?php
/**
 * @version    4.1.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
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

        <h2><?php echo Text::_('COM_JEM_INSTALL_STATUS'); ?>:</h2>
        <h3><?php echo Text::_('COM_JEM_INSTALL_CHECK_FOLDERS'); ?>:</h3>

        <?php
        $imageDir = "/images/jem";
        $createDirs = array(
            $imageDir,
            $imageDir.'/categories',
            $imageDir.'/categories/small',
            $imageDir.'/events',
            $imageDir.'/events/small',
            $imageDir.'/venues',
            $imageDir.'/venues/small'
        );

        // Check for existance of /images/jem directory
        if (Folder::exists(JPATH_SITE.$createDirs[0])) {
            echo "<p><span style='color:green;'>".Text::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_EXISTS_SKIP', $createDirs[0])."</p>";
        } else {
            echo "<p><span style='color:orange;'>".Text::_('COM_JEM_INSTALL_INFO').":</span> ".
                Text::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_EXISTS', $createDirs[0])."</p>";
            echo "<p>".Text::_('COM_JEM_INSTALL_DIRECTORY_TRY_CREATE').":</p>";

            echo "<ul>";
            // Folder creation
            foreach($createDirs as $directory) {
                if (Folder::create(JPATH_SITE.$directory)) {
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
        $query = $db->getQuery(true);
        $query->select('*')->from('#__jem_config');
        $db->setQuery($query);
        $conf = $db->loadAssocList();

        if (count($conf)) {
            echo "<p><span style='color:green;'>".Text::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
                Text::_('COM_JEM_INSTALL_FOUND_SETTINGS')."</p>";
        }

        echo "<h3>".Text::_('COM_JEM_INSTALL_SUMMARY')."</h3>";

        foreach ($error as $k => $v) {
            if($k != 'summary') {
                $error['summary'] += $v;
            }
        }

        if($error['summary']) {
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
            "event_comunoption"=>"0",
            "event_comunsolution"=>"0",
            "event_show_attendeenames"=>"2",
            "event_show_more_attendeedetails"=>"0",
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
            "global_show_mapserv"=>"0",
            "global_tld"=>"",
            "global_lg"=>"",
            "global_cleanup_db_on_uninstall"=>"0"
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
            $imageDir = JPATH_SITE.'/images/jem';
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
        $this->newRelease = (string) $parent->manifest->version;

        if (version_compare(JVERSION, '6.0.0', 'ge') || // J! 6.x NOT supported, but allow alpha/beta
            !(($current_version >= '4.3' && $devLevel >= '0') ||
                ($current_version >= '4.2' && $devLevel >= '9') ||
                ($current_version == '4.1' && $devLevel >= '5') ||
                ($current_version == '4.0' && $devLevel >= '6') )) {
            $app->enqueueMessage(Text::_('COM_JEM_PREFLIGHT_WRONG_JOOMLA_VERSION'), 'warning');
            return false;
        }

        // Minimum required PHP version
        $minPhpVersion = "8.0.0";

        // Abort if PHP release is older than required version
        if(version_compare(PHP_VERSION, $minPhpVersion, '<')) {
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

            // Remove obsolete files and folder
            $this->deleteObsoleteFiles();

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
        }
        elseif (strtolower($type) == 'install') {
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
        $db = Factory::getDbo();
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
                $params[(string) $name] = (string) $value;
            }

            // store the combined new and existing values back as a JSON string
            $paramsString = json_encode($params);
            $query = $db->getQuery(true);
            $query->update('#__extensions')
                ->set('params = '.$db->quote($paramsString))
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
                $params[(string) $name] = (string) $value;
            }

            // store the combined new and existing values back as a JSON string
            $paramsString = json_encode($params);
            $query = $db->getQuery(true);
            if ($this->useJemConfig) {
                $query->update('#__jem_config')
                    ->where($db->quoteName('keyname') . ' = ' . $db->quote('globalattribs'))
                    ->set($db->quoteName('value') . ' = '. $db->quote($paramsString));
            } else {
                $query->update('#__jem_settings')
                    ->set('globalattribs = '.$db->quote($paramsString));
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
        <img src="../media/com_jem/images/jemlogo.png" alt="" style="float:left; padding-right:20px;" />
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
        $query->select('version_id')->from('#__schemas')->where('extension_id = '.$extensionId);
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
        $files = array(

            // obsolete since JEM 4.0.0
			'/administrator/components/com_jem/sql/updates/1.9.1.sql',
			'/administrator/components/com_jem/sql/updates/1.9.2.sql',
			'/administrator/components/com_jem/sql/updates/1.9.3.sql',
			'/administrator/components/com_jem/sql/updates/1.9.4.sql',
			'/administrator/components/com_jem/sql/updates/1.9.5.sql',
			'/administrator/components/com_jem/sql/updates/1.9.6.sql',
			'/administrator/components/com_jem/sql/updates/1.9.7.sql',
			'/administrator/components/com_jem/sql/updates/1.9.8.sql',
			'/administrator/components/com_jem/sql/updates/1.9.sql',
			'/administrator/components/com_jem/sql/updates/2.0.0.sql',
			'/administrator/components/com_jem/sql/updates/2.0.1.sql',
			'/administrator/components/com_jem/sql/updates/2.0.2.sql',
			'/administrator/components/com_jem/sql/updates/2.0.3.sql',
			'/administrator/components/com_jem/sql/updates/2.1.0.sql',
			'/administrator/components/com_jem/sql/updates/2.1.1.sql',
			'/administrator/components/com_jem/sql/updates/2.1.2.sql',
			'/administrator/components/com_jem/sql/updates/2.1.3.sql',
			'/administrator/components/com_jem/sql/updates/2.1.4.1.sql',
			'/administrator/components/com_jem/sql/updates/2.1.4.2.sql',
			'/administrator/components/com_jem/sql/updates/2.1.4.sql',
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
        );

        // TODO There is an issue while deleting folders using the ftp mode
        $folders = array(            
            '/media/com_jem/FontAwesome',
        );

        foreach ($files as $file) {
            if (File::exists(JPATH_ROOT . $file) && !File::delete(JPATH_ROOT . $file)) {
                echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $file).'<br />';
            }
        }

        foreach ($folders as $folder) {
            if (Folder::exists(JPATH_ROOT . $folder) && !Folder::delete(JPATH_ROOT . $folder)) {
                echo Text::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $folder).'<br />';
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
        $path = Path::clean(JPATH_ROOT.'/media/com_jem/css');
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
        $db = Factory::getDbo();
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
        $tables = array('#__jem_attachments',
            '#__jem_categories',
            '#__jem_cats_event_relations',
            '#__jem_countries',
            '#__jem_events',
            '#__jem_groupmembers',
            '#__jem_groups',
            '#__jem_register',
            '#__jem_settings',
            '#__jem_config',
            '#__jem_venues');
        foreach ($tables AS $table) {
            try {
                $db->dropTable($table);
            } catch (Exception $ex) {
                // simply continue with next table
            }
        }
    }

}
