<?php
/**
 * JEM Package
 * @package    JEM.Package
 *
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * @copyright  (C) 2008 - 2013 Kunena Team. All rights reserved.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link https://www.kunena.org
 **/

defined ('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Version;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
/**
 * JEM package installer script.
 */
class Pkg_JemInstallerScript
{
    /** SHA-256 of the catalog snapshot included by an earlier Beta 2 build. */
    const LEGACY_BUNDLED_LANGUAGE_CATALOG_SHA256 = '2f9515c8b36e478438c4cef564ca6d7f26b00a1781caa31ed4a196f86602999a';

    /**
     * List of supported versions. Newest version first!
     * @var array
     */
    protected $versions = array(
        'PHP' => array (
            '8.3.0' => '8.3.0',
            '0' => '8.4' // Preferred version
            ),
        'MySQL' => array (
            '8.0.13' => '8.0.13',
            '0' => '8.4' // Preferred version
            )
        );

    /**
     * List of required PHP extensions.
     * @var array
     */
    protected $extensions = array ('gd', 'json', 'pcre', 'ctype', 'SimpleXML' /* iCalCreator */    );

    public function install($parent) {
        return true;
    }

    public function discover_install($parent) {
        return self::install($parent);
    }

    public function update($parent) {
        return self::install($parent);
    }

    public function uninstall($parent) {
        return true;
    }

    public function preflight($type, $parent) {
        // Prevent installation if requirements are not met.
        if (!$this->checkRequirements()){
            return false;
        }
        return true;
    }

    public function makeRoute($uri) {
        return Route::_($uri, false);
    }

    public function postflight($type, $parent) {
        // Clear Joomla system cache.
        $cache = Factory::getCache();
        $cache->clean('_system');

        // Remove all compiled files from APC cache.
        if (function_exists('apc_clear_cache')) {
            @apc_clear_cache();
        }

        if ($type == 'uninstall') return true;

        $this->enablePlugin('content', 'jemlistevents');
        $this->enablePlugin('quickicon', 'jem');
        $this->enablePlugin('task', 'jem');
        $this->enablePlugin('user', 'jem');
        $this->uninstallPlugin('content', 'jem');
        $this->uninstallPlugin('search', 'jem');
        $this->uninstallModule('mod_jem_calajax');
        $this->normaliseJemModuleParams();
        $this->ensureJemNotificationTask();
        $this->removeLegacyLocalEnglishLanguageFiles();
        $this->removeLegacyBundledLanguageCatalog();
        $this->removeUnusedCatalogDirectories();

        return true;
    }

    function enablePlugin($group, $element) {
        $plugin = Table::getInstance('extension');
        if (!$plugin->load(array('type'=>'plugin', 'folder'=>$group, 'element'=>$element))) {
            return false;
        }
        $plugin->enabled = 1;
        return $plugin->store();
    }

    function uninstallPlugin($group, $element) {
        $plugin = Table::getInstance('extension');
        if (!$plugin->load(array('type'=>'plugin', 'folder'=>$group, 'element'=>$element))) {
            return false;
        }

        if (!is_dir(JPATH_ROOT . '/plugins/' . $group . '/' . $element)) {
            return $plugin->delete((int) $plugin->extension_id);
        }

        return Installer::getInstance()->uninstall('plugin', (int) $plugin->extension_id);
    }

    function uninstallModule($element) {
        $module = Table::getInstance('extension');
        if (!$module->load(array('type'=>'module', 'element'=>$element))) {
            return false;
        }

        return Installer::getInstance()->uninstall('module', (int) $module->extension_id);
    }

    /**
     * Joomla's module editor expects module instance params to contain valid JSON.
     */
    function normaliseJemModuleParams() {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('params') . ' = ' . $db->quote('{}'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('module') . ' LIKE ' . $db->quote('mod_jem%'))
            ->where('(' . $db->quoteName('params') . ' IS NULL OR ' . $db->quoteName('params') . ' = ' . $db->quote('') . ')');

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Remove the bundled English files installed by the pre-5.1 local layout.
     *
     * Other language tags are owned by separately installed JEM language
     * packs. They remain available to the temporary local fallback until the
     * matching global language pack is installed.
     */
    protected function removeLegacyLocalEnglishLanguageFiles()
    {
        $locations = array(
            JPATH_SITE . '/components/com_jem/language/en-GB' => 'com_jem',
            JPATH_ADMINISTRATOR . '/components/com_jem/language/en-GB' => 'com_jem',
            JPATH_SITE . '/modules/mod_jem/language/en-GB' => 'mod_jem',
            JPATH_SITE . '/modules/mod_jem_banner/language/en-GB' => 'mod_jem_banner',
            JPATH_SITE . '/modules/mod_jem_cal/language/en-GB' => 'mod_jem_cal',
            JPATH_SITE . '/modules/mod_jem_jubilee/language/en-GB' => 'mod_jem_jubilee',
            JPATH_SITE . '/modules/mod_jem_map/language/en-GB' => 'mod_jem_map',
            JPATH_SITE . '/modules/mod_jem_teaser/language/en-GB' => 'mod_jem_teaser',
            JPATH_SITE . '/modules/mod_jem_types/language/en-GB' => 'mod_jem_types',
            JPATH_SITE . '/modules/mod_jem_wide/language/en-GB' => 'mod_jem_wide',
            JPATH_PLUGINS . '/actionlog/jem/language/en-GB' => 'plg_actionlog_jem',
            JPATH_PLUGINS . '/content/jemembed/language/en-GB' => 'plg_content_jemembed',
            JPATH_PLUGINS . '/content/jemlistevents/language/en-GB' => 'plg_content_jemlistevents',
            JPATH_PLUGINS . '/finder/jem/language/en-GB' => 'plg_finder_jem',
            JPATH_PLUGINS . '/jem/comments/language/en-GB' => 'plg_jem_comments',
            JPATH_PLUGINS . '/jem/mailer/language/en-GB' => 'plg_jem_mailer',
            JPATH_PLUGINS . '/quickicon/jem/language/en-GB' => 'plg_quickicon_jem',
            JPATH_PLUGINS . '/task/jem/language/en-GB' => 'plg_task_jem',
            JPATH_PLUGINS . '/user/jem/language/en-GB' => 'plg_user_jem',
        );

        foreach ($locations as $directory => $extension) {
            foreach (array($extension . '.ini', $extension . '.sys.ini', 'index.html') as $filename) {
                $path = $directory . '/' . $filename;

                if (is_file($path) && !File::delete($path)) {
                    Factory::getApplication()->enqueueMessage(
                        'JEM could not remove the obsolete local language file: ' . $path,
                        'warning'
                    );
                }
            }

            $this->removeDirectoryIfEmpty($directory);
            $this->removeDirectoryIfEmpty(dirname($directory));
        }
    }

    /**
     * Remove one known language directory only when it contains no files.
     */
    protected function removeDirectoryIfEmpty($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries !== false && count($entries) === 2) {
            Folder::delete($directory);
        }
    }

    /**
     * Remove only the known pre-release snapshot that was previously bundled.
     * A different local catalog was placed manually and must be preserved.
     */
    protected function removeLegacyBundledLanguageCatalog()
    {
        $path = JPATH_ROOT . '/media/com_jem/data/language_catalog_jem.xml';

        if (!is_file($path)) {
            return;
        }

        $hash = hash_file('sha256', $path);

        if (!is_string($hash)
            || !hash_equals(self::LEGACY_BUNDLED_LANGUAGE_CATALOG_SHA256, strtolower($hash))) {
            return;
        }

        if (!File::delete($path)) {
            Factory::getApplication()->enqueueMessage(
                'JEM could not remove the obsolete local language catalog: ' . $path,
                'warning'
            );

            return;
        }
    }

    /**
     * Remove catalog directories left by pre-release packages only when they
     * contain no user catalog or other custom file.
     */
    protected function removeUnusedCatalogDirectories()
    {
        foreach (array('data', 'import', 'languages', 'update') as $name) {
            $directory = JPATH_ROOT . '/media/com_jem/' . $name;

            if (!is_dir($directory)) {
                continue;
            }

            $entries = scandir($directory);

            if ($entries === false) {
                continue;
            }

            $entries = array_values(array_diff($entries, array('.', '..')));

            if ($entries === array('index.html')) {
                File::delete($directory . '/index.html');
            }

            $this->removeDirectoryIfEmpty($directory);
        }
    }

    public function checkRequirements() {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $pass  = $this->checkVersion('PHP', phpversion());
        $pass &= $this->checkJoomlaVersion();
        $pass &= $this->checkVersion('MySQL', $db->getVersion ());
        $pass &= $this->checkDbo($db->name, array('mysql', 'mysqli'));
        $pass &= $this->checkExtensions($this->extensions);
        return $pass;
    }

    // Internal functions

    protected function checkVersion($name, $version) {
        $app = Factory::getApplication();

        $major = $minor = 0;
        foreach ($this->versions[$name] as $major=>$minor) {
            if (!$major || version_compare($version, $major, '<')) {
                continue;
            }
            if ($minor && version_compare($version, $minor, '>=')) {
                return true;
            }
            break;
        }
        if (!$major) {
            $minor = reset($this->versions[$name]);
        }
        $recommended = end($this->versions[$name]);
        if ($minor) {
            $app->enqueueMessage(sprintf("%s %s is not supported. Minimum required version is %s %s, but it is highly recommended to use %s %s or later.", $name, $version, $name, $minor, $name, $recommended), 'notice');
        } else {
            $app->enqueueMessage(sprintf("%s %s is not supported. It is highly recommended to use %s %s or later.", $name, $version, $name, $recommended), 'notice');
        }
        return false;
    }

    protected function checkJoomlaVersion() {
        $app = Factory::getApplication();

        if (version_compare(JVERSION, '5.4.0', '>=') && version_compare(JVERSION, '7.0.0', '<')) {
            return true;
        }

        $app->enqueueMessage(sprintf("Joomla! %s is not supported. This package requires Joomla! 5.4.x or Joomla! 6.x.", JVERSION), 'notice');
        return false;
    }

    /**
     * Create the single native Joomla scheduler task during install/update.
     * The global JEM option determines its initial state.
     */
    function ensureJemNotificationTask() {
        $factory = JPATH_SITE . '/components/com_jem/factory.php';
        if (!is_file($factory)) {
            return false;
        }

        require_once $factory;
        if (!class_exists('JemReminderSchedulerService')) {
            return false;
        }

        try {
            (new JemReminderSchedulerService())->syncFromConfig();
            return true;
        } catch (Throwable $error) {
            Factory::getApplication()->enqueueMessage(
                'JEM could not create the scheduled notification task: ' . $error->getMessage(),
                'warning'
            );
            return false;
        }
    }

    protected function checkDbo($name, $types) {
        $app = Factory::getApplication();

        if (in_array($name, $types)) {
            return true;
        }
        $app->enqueueMessage(sprintf("Database driver '%s' is not supported. Please use MySQL instead.", $name), 'notice');
        return false;
    }

    protected function checkExtensions($extensions) {
        $app = Factory::getApplication();

        $pass = 1;
        foreach ($extensions as $name) {
            if (!extension_loaded($name)) {
                $pass = 0;
                $app->enqueueMessage(sprintf("Required PHP extension '%s' is missing. Please install it into your system.", $name), 'notice');
            }
        }
        return $pass;
    }

}
