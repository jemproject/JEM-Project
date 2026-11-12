<?php
// Read-only investigation: actual checkout controller + Joomla libraries, no site bootstrap/DB.
if (!defined('_JEXEC')) { define('_JEXEC', 1); }
$root = JEM_TEST_ROOT;
$j = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
$loader = require $j . '/libraries/vendor/autoload.php';
$loader->addPsr4('Joomla\\CMS\\', $j . '/libraries/src');
define('JPATH_BASE', $j);
define('JPATH_ROOT', $j);
define('JPATH_SITE', $j);
define('JPATH_ADMINISTRATOR', $j . '/administrator');
define('JPATH_LIBRARIES', $j . '/libraries');
define('JPATH_CACHE', sys_get_temp_dir());
define('JDEBUG', false);
define('JPATH_COMPONENT_ADMINISTRATOR', $root . '/admin');
Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB', false);
Joomla\CMS\Factory::$application = new class {
 public function get($key, $default = null) { return $key === 'offset' ? 'UTC' : $default; }
 public function getLanguage() { return Joomla\CMS\Factory::$language; }
};
require_once $root . '/site/classes/icalendar/datetime.php';
require_once $root . '/site/classes/icalendar/recurrence.php';
require $root . '/admin/controllers/import.php';
