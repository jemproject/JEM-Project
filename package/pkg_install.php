<?php
/**
 * JEM Package
 * @package    JEM.Package
 *
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * @copyright  (C) 2008 - 2013 Kunena Team. All rights reserved.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link https://www.kunena.org
 **/
 
defined ('_JEXEC') or die;

use Joomla\CMS\Factory;
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
			'8.0' => '8.0',
			'0' => '8.0' // Preferred version
			),
		'MySQL' => array (
			'8.0' => '8.0', 
			'5.6' => '5.6',
			'0' => '5.6' // Preferred version
			),
		'Joomla!' => array (
			'4.2' => '4.2', 
			'4.0' => '', 
			'0' => '4.2' // Preferred version
			)
		);

	/**
	 * List of required PHP extensions.
	 * @var array
	 */
	protected $extensions = array ('gd', 'json', 'pcre', 'ctype', 'SimpleXML' /* iCalCreator */	);

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
		/** @var JCache|JCacheController $cache */
		$cache = Factory::getCache();
		$cache->clean('_system');

		// Remove all compiled files from APC cache.
		if (function_exists('apc_clear_cache')) {
			@apc_clear_cache();
		}

		if ($type == 'uninstall') return true;

		$this->enablePlugin('content', 'jem');
	//	$this->enablePlugin('search', 'jem');
	//	$this->enablePlugin('jem', 'mailer');

		# ajax calendar module doesn't fully work on Joomla! 2.5
		if (version_compare(JVERSION, '3', '<')) {
			$this->disableModule('mod_jem_calajax');
		}

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

	function disableModule($element) {
		$module = Table::getInstance('extension');
		if (!$module->load(array('type'=>'module', 'element'=>$element))) {
			return false;
		}
		$module->enabled = 0;
		return $module->store();
	}

	public function checkRequirements() {
        $db = Factory::getContainer()->get('DatabaseDriver');
		$pass  = $this->checkVersion('PHP', phpversion());
		$pass &= $this->checkVersion('Joomla!', JVERSION);
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
