<?php
/**
 * JEM Package
 * @package JEM.Package
 *
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @copyright (C) 2008 - 2013 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

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
			'5.3' => '5.3.1',
			'0' => '5.6' // Preferred version
			),
		'MySQL' => array (
			'5.0' => '5.0.4', // without guarantee, you should update to at least 5.1
			'5.1' => '5.1',
			'0' => '5.5' // Preferred version
			),
		'Joomla!' => array (
			'4.0' => '', // Not supported
			'3.3' => '3.3.3',
			'3.2' => '3.2.7',
			'3.0' => '', // Not supported
			'2.5' => '2.5.24',
			'0' => '2.5.27' // Preferred version
			)
		);

	/**
	 * List of required PHP extensions.
	 * @var array
	 */
	protected $extensions = array ('gd', 'json', 'pcre'
			, 'ctype', 'SimpleXML' /* iCalCreator */
		);

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
		/** @var JInstallerComponent $parent */
		$manifest = $parent->getParent()->getManifest();

		// Prevent installation if requirements are not met.
		if (!$this->checkRequirements($manifest->version)) return false;

		return true;
	}

	public function makeRoute($uri) {
		return JRoute::_($uri, false);
	}

	public function postflight($type, $parent) {
		// Clear Joomla system cache.
		/** @var JCache|JCacheController $cache */
		$cache = JFactory::getCache();
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
		$plugin = JTable::getInstance('extension');
		if (!$plugin->load(array('type'=>'plugin', 'folder'=>$group, 'element'=>$element))) {
			return false;
		}
		$plugin->enabled = 1;
		return $plugin->store();
	}

	function disableModule($element) {
		$module = JTable::getInstance('extension');
		if (!$module->load(array('type'=>'module', 'element'=>$element))) {
			return false;
		}
		$module->enabled = 0;
		return $module->store();
	}

	public function checkRequirements($version) {
		$db = JFactory::getDbo();
		$pass  = $this->checkVersion('PHP', phpversion());
		$pass &= $this->checkVersion('Joomla!', JVERSION);
		$pass &= $this->checkVersion('MySQL', $db->getVersion ());
		$pass &= $this->checkDbo($db->name, array('mysql', 'mysqli'));
		$pass &= $this->checkExtensions($this->extensions);
		$pass &= $this->checkMagicQuotes();
		return $pass;
	}

	// Internal functions

	protected function checkVersion($name, $version) {
		$app = JFactory::getApplication();

		$major = $minor = 0;
		foreach ($this->versions[$name] as $major=>$minor) {
			if (!$major || version_compare($version, $major, '<')) continue;
			if ($minor && version_compare($version, $minor, '>=')) return true;
			break;
		}
		if (!$major) $minor = reset($this->versions[$name]);
		$recommended = end($this->versions[$name]);
		if ($minor) {
			$app->enqueueMessage(sprintf("%s %s is not supported. Minimum required version is %s %s, but it is highly recommended to use %s %s or later.", $name, $version, $name, $minor, $name, $recommended), 'notice');
		} else {
			$app->enqueueMessage(sprintf("%s %s is not supported. It is highly recommended to use %s %s or later.", $name, $version, $name, $recommended), 'notice');
		}
		return false;
	}

	protected function checkDbo($name, $types) {
		$app = JFactory::getApplication();

		if (in_array($name, $types)) {
			return true;
		}
		$app->enqueueMessage(sprintf("Database driver '%s' is not supported. Please use MySQL instead.", $name), 'notice');
		return false;
	}

	protected function checkExtensions($extensions) {
		$app = JFactory::getApplication();

		$pass = 1;
		foreach ($extensions as $name) {
			if (!extension_loaded($name)) {
				$pass = 0;
				$app->enqueueMessage(sprintf("Required PHP extension '%s' is missing. Please install it into your system.", $name), 'notice');
			}
		}
		return $pass;
	}

	protected function checkMagicQuotes() {
		$app = JFactory::getApplication();

		// Abort if Magic Quotes are enabled, it was removed from phpversion 5.4
		if (version_compare(phpversion(), '5.4', '<')) {
			if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
				$app->enqueueMessage("Magic Quotes are enabled. JEM requires Magic Quotes to be disabled.", 'notice');
				return false;
			}
		}
		return true;
	}
}
