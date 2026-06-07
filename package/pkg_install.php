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
/**
 * JEM package installer script.
 */
class Pkg_JemInstallerScript
{
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
        $this->uninstallPlugin('content', 'jem');
        $this->uninstallPlugin('search', 'jem');
        $this->uninstallModule('mod_jem_calajax');
        $this->normaliseJemModuleParams();

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

        if (Version::MAJOR_VERSION === 6 && version_compare(JVERSION, '6.0', '>=')) {
            return true;
        }

        $app->enqueueMessage(sprintf("Joomla! %s is not supported. This package requires Joomla! 6.x.", JVERSION), 'notice');
        return false;
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
