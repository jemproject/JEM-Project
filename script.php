<?php
/**
 * @version 2.3.15
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
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

        $this->updateJemSettings216(true);
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

        if (version_compare(JVERSION, '4.4.0', 'ge') ||  // J! 4.x NOT supported, but allow alpha/beta
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
            // Changes between 2.3.12 -> 2.3.13
            if (version_compare($this->oldRelease, '2.3.13', 'lt') && version_compare($this->newRelease, '2.3.12', 'gt')) {
                // change categoriesdetailed view name in menu items
                $this->updateJemMenuItems2313();
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
        $db = JFactory::getDbo();
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
            // obsolete since JEM 1.9.2
            '/administrator/components/com_jem/controllers/archive.php',
            '/administrator/components/com_jem/models/archive.php',
            '/components/com_jem/views/calendar/metadata.xml',
            '/components/com_jem/views/categories/metadata.xml',
            '/components/com_jem/views/categoriesdetailed/metadata.xml',
            '/components/com_jem/views/category/metadata.xml',
            '/components/com_jem/views/day/metadata.xml',
            '/components/com_jem/views/editevent/metadata.xml',
            '/components/com_jem/views/editvenue/metadata.xml',
            '/components/com_jem/views/event/metadata.xml',
            '/components/com_jem/views/eventslist/metadata.xml',
            '/components/com_jem/views/myattending/metadata.xml',
            '/components/com_jem/views/myevents/metadata.xml',
            '/components/com_jem/views/myvenues/metadata.xml',
            '/components/com_jem/views/search/metadata.xml',
            '/components/com_jem/views/venue/metadata.xml',
            '/components/com_jem/views/venues/metadata.xml',
            // obsolete since JEM 1.9.3
            '/components/com_jem/views/category/tmpl/default_attachments.php',
            '/components/com_jem/views/category/tmpl/default_table.php',
            '/components/com_jem/views/editevent/tmpl/default_attachments.php',
            '/components/com_jem/views/editvenue/tmpl/default_attachments.php',
            '/components/com_jem/views/eventslist/tmpl/default_table.php',
            '/components/com_jem/views/venue/tmpl/default_attachments.php',
            '/components/com_jem/views/weekcal/tmpl/default.xml.disabled',
            // obsolete since JEM 1.9.4
            // obsolete since JEM 1.9.5
            '/administrator/components/com_jem/help/en-GB/archive.html',
            '/administrator/components/com_jem/help/en-GB/toolbars/apunedch.png',
            '/administrator/components/com_jem/help/en-GB/toolbars/asch.png',
            '/administrator/components/com_jem/help/en-GB/toolbars/asuch.png',
            '/administrator/components/com_jem/help/en-GB/toolbars/dbh.png',
            '/administrator/components/com_jem/help/en-GB/toolbars/nedh.png',
            '/administrator/components/com_jem/help/en-GB/toolbars/punedh.png',
            '/administrator/components/com_jem/help/en-GB/toolbars/udh.png',
            '/administrator/components/com_jem/help/images/icon-16-attention.png',
            '/administrator/components/com_jem/help/images/icon-16-hint.png',
            '/administrator/components/com_jem/models/jem.php',
            '/administrator/components/com_jem/models/fields/imageselectevent.php',
            '/administrator/components/com_jem/views/category/tmpl/default.php',
            '/administrator/components/com_jem/views/category/tmpl/default_attachments.php',
            '/administrator/components/com_jem/views/event/tmpl/addvenue.php',
            '/administrator/components/com_jem/views/group/tmpl/default.php',
            '/administrator/components/com_jem/views/settings/tmpl/default_basic.php',
            '/administrator/components/com_jem/views/settings/tmpl/default_eventpage.php',
            '/administrator/components/com_jem/views/settings/tmpl/default_navigation.php',
            '/components/com_jem/models/myattending.php',
            '/components/com_jem/views/editevent/tmpl/default.xml',
            '/media/com_jem/css/calendarweek.css',
            '/media/com_jem/css/gmapsoverlay.css',
            '/media/com_jem/css/picker.css',
            '/media/com_jem/images/evlogo.png',
            '/media/com_jem/js/gmapsoverlay.js',
            '/media/com_jem/js/picker.js',
            '/media/com_jem/js/recurrencebackend.js',
            '/media/com_jem/js/seobackend.js',
            // obsolete since JEM 1.9.6
            '/administrator/components/com_jem/models/cleanup.php',
            '/administrator/components/com_jem/controllers/cleanup.php',
            '/administrator/components/com_jem/help/en-GB/cleanup.html',
            '/components/com_jem/controllers/editevent.php',
            '/components/com_jem/controllers/editvenue.php',
            '/components/com_jem/models/categoriesdetailed.php',
            '/components/com_jem/views/editevent/tmpl/default.php',
            '/components/com_jem/views/editvenue/tmpl/default.php',
            '/components/com_jem/views/editvenue/tmpl/default.xml',
            '/components/com_jem/views/venues/view.feed.php',
            '/media/com_jem/js/eventscreen.js',
            '/media/com_jem/js/geodata.js',
            '/media/com_jem/js/jquery.geocomplete.min.js',
            // obsolete since JEM 1.9.7
            '/administrator/components/com_jem/classes/Snoopy.class.php',
            // obsolete since JEM 2.1.7
            '/components/com_jem/views/event/tmpl/default_unregform.php',
        );

        // TODO There is an issue while deleting folders using the ftp mode
        $folders = array(
            // obsolete since JEM 1.9.2
            '/administrator/components/com_jem/views/archive',
            // obsolete since JEM 1.9.3
            // obsolete since JEM 1.9.4
            // obsolete since JEM 1.9.5
            '/administrator/components/com_jem/views/jem',
            '/components/com_jem/views/myattending',
            // obsolete since JEM 1.9.6
            '/components/com_jem/views/categoriesdetailed',
            '/administrator/components/com_jem/views/cleanup/',
            '/administrator/components/com_jem/help/en-GB/toolbars',
            // obsolete since JEM 1.9.7
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
     * Change categoriesdetailed view to categories view in menu items related to com_jem.
     * (required when updating from 1.9.5 or below to 1.9.6 or newer)
     *
     * @return void
     */
    private function updateJemMenuItems2313()
    {
        // get all "com_jem..." frontend entries
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('id, link, params');
        $query->from('#__menu');
        $query->where(array("client_id = 0", "link LIKE 'index.php?option=com_jem&view=categor%'"));
        $db->setQuery($query);
        $items = $db->loadObjectList();

        foreach ($items as $item) {
            $link = $item->link;
            // Decode the item params
            $reg = new JRegistry;
            $reg->loadString($item->params);

            // get view
            preg_match('/view=([^&]+)/', $item->link, $matches);
            $view = $matches[1];
return; //beta in dev
            switch ($view) {
                case 'categoriesdetailed':
                    // replace view name
                    $link = str_replace("&view=categoriesdetailed", "&view=categories", $link);
                // fall through
                case 'categories':
                    // add "&id=..." if required
                    if (strpos($link, '&id=') === false) {
                        $link .= '&id=' . max(1, (int)$reg->get('catid', $reg->get('id', 1)));
                    }

                    // change params as required (order and defaults matching xml)
                    $params = array('showemptycats' => $reg->get('showemptychilds', 1),
                        'cat_num' => 4,
                        'detcat_nr' => 0, // will be overwritten if aleady set
                        'usecat' => 1,
                        'showemptychilds' => $reg->get('empty_cats', 1));
                    foreach ($reg->toArray() as $k => $v) {
                        switch ($k) {
                            case 'id':
                            case 'catid':
                                // remove 'id' and 'catid'
                                break;
                            case 'empty_cat':
                                // rename
                                $params['showemptycats'] = $v;
                                break;
                            default:
                                $params[$k] = $v;
                                break;
                        }
                    }
                    $reg = new JRegistry;
                    $reg->loadArray($params);
                    break;

                case 'category':
                    // add "&id=..." if required
                    if (strpos($link, '&id=') === false) {
                        $link .= '&id=' . max(1, (int)$reg->get('id', 1));

                        // and remove from params
                        $params = array();
                        foreach ($reg->toArray() as $k => $v) {
                            switch ($k) {
                                case 'id':
                                    // remove 'id'
                                    break;
                                default:
                                    $params[$k] = $v;
                                    break;
                            }
                        }
                        $reg = new JRegistry;
                        $reg->loadArray($params);
                    }
                    break;
            }

            // write changed entry back into DB
            $query = $db->getQuery(true);
            $query->update('#__menu');
            $query->set('link = '.$db->quote((string)$link));
            $query->set('params = '.$db->quote((string)$reg));
            $query->where(array('id = '.$db->quote($item->id)));
            $db->setQuery($query);
            $db->execute();
        }
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
     * Remove 'htm' and 'html' from allowed attachment types.
     * (required when updating from 2.1.4 or below to 2.1.4.2 or newer)
     *
     * @return void
     */
    private function updateJemSettings2142()
    {
        // get all "mod_jem..." entries
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('attachments_types')
            ->from('#__jem_settings')
            ->where('id = 1');
        $db->setQuery($query);
        try {
            $ext = $db->loadResult();
        } catch(Exception $e) {
            $ext = '';
        }

        if (!empty($ext)) {
            $ext_to_del = array('csv', 'htm', 'html', 'xml', 'css', 'doc', 'xls', 'rtf', 'ppt', 'swf', 'flv', 'avi', 'wmv', 'mov');
            $a_ext = explode(',', $ext);
            $new_ext = array_diff($a_ext, $ext_to_del);
            $ext = implode(',', $new_ext);

            $query = $db->getQuery(true);
            $query->update('#__jem_settings')
                ->set('attachments_types = '.$db->quote($ext))
                ->where('id = 1');
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Move all settings from table #__jem_settings to table #__jem_config
     * storing every setting in it's own record.
     * (required when updating from 2.1.5 or below to 2.1.6 or newer)
     *
     * @return void
     */
    private function updateJemSettings216($onInstall = false)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // load data from old #__jem_settings
        try {
            $query = $db->getQuery(true);
            $query->select('*')->from('#__jem_settings')->where('id=1');
            $db->setQuery($query);
            $old_data = $db->loadObject();
        } catch (Exception $ex) {
        }

        if ($onInstall && empty($old_data)) {
            return;
        }

        // Special: swap showtime <-> globalattribs.global_show_timedetails
        if (!empty($old_data->globalattribs) && isset($old_data->showtime)) {
            $registry = new JRegistry;
            $registry->loadString($old_data->globalattribs);
            $showtime = $old_data->showtime;
            $old_data->showtime = $registry->get('global_show_timedetails', $showtime);
            $registry->set('global_show_timedetails', $showtime);
            $old_data->globalattribs = $registry->toString();
        }

        if (empty($old_data)) {
            echo "<li><span style='color:red;'>".Text::_('COM_JEM_INSTALL_ERROR').":</span> ".
                Text::_('COM_JEM_INSTALL_SETTINGS_NOT_FOUND')."</li>";
        } else {
            // save to new #__jem_config table ignoring obsolete fields
            $old_data = get_object_vars($old_data);
            $ignore = array('id', 'showmapserv', 'showtimedetails', 'showevdescription', 'showdetailstitle',
                'showdetailsadress', 'showlocdescription', 'showdetlinkvenue', 'communsolution',
                'communoption', 'regname', 'checked_out', 'checked_out_time', 'tld', 'lg', 'cat_num',
                'filter', 'display', 'icons', 'show_print_icon', 'show_email_icon', 'events_ical',
                'show_archive_icon', 'ownedvenuesonly', 'empty_cat'
            );
            $oops = 0;

            try {
                $query = $db->getQuery(true);
                $query->select(array($db->quoteName('keyname'), $db->quoteName('value')));
                $query->from('#__jem_config');
                $db->setQuery($query);
                $list = $db->loadAssocList('keyname', 'value');
            } catch (Exception $ex) {
                $list = array();
            }
            $keys = array_keys($list);

            foreach ($old_data as $k => $v) {
                $query = $db->getQuery(true);
                if (in_array($k, $ignore)) {
                    continue; // skip if obsolete
                }
                if (in_array($k, $keys)) {
                    if ($v == $list[$k]) {
                        continue; // skip if unchanged
                    }
                    // we do overwrite values already in #__jem_config by those from #__jem_settings - shouldn't we?
                    $query->update('#__jem_config');
                    $query->where(array($db->quoteName('keyname') . ' = ' . $db->quote($k)));
                } else {
                    $query->insert('#__jem_config');
                    $query->set(array($db->quoteName('keyname') . ' = ' . $db->quote($k)));
                }
                $query->set(array($db->quoteName('value') . ' = ' . $db->quote($v)));
                $db->setQuery($query);
                try {
                    $db->execute();
                } catch (Exception $e) {
                    $oops++;
                }
            }

            if ($oops) {
                echo "<li><span style='color:red;'>".Text::_('COM_JEM_INSTALL_ERROR').":</span> ".
                    Text::_('COM_JEM_INSTALL_CONFIG_NOT_STORED')."</li>";
            } else {
                // remove old #__jem_settings table
                try {
                    $db->dropTable('#__jem_settings');
                    $this->useJemConfig = true;
                } catch (Exception $ex) {
                }
            }
        }
    }

    /**
     * Change registra on table #__jem_events from 2 to 3.
     * (required when updating from 2.1.7-dev3 or below to 2.1.7-dev4 or newer)
     *
     * @return void
     */
    private function updateJemEvents217()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->update('#__jem_events')
            ->set('registra = 3')
            ->where('registra = 2');
        try {
            $db->setQuery($query)->execute();
        } catch(Exception $e) {
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
