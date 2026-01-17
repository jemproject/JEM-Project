<?php
/**
 * @package    JEM
 * @subpackage JEM Timeline Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

$mod_name = 'mod_jem_timeline';

// load helper
require_once __DIR__ . '/helper.php';
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/classes/image.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');

Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE.'/components/com_jem');

// get filtered events
$list = ModJemTimelineHelper::getList($params);

if (empty($list) && !$params->get('show_no_events')) {
    return;
}

// load styles
$jemsettings = JemHelper::config();
JemHelper::loadModuleStyleSheet($mod_name, 'mod_jem_timeline');

// load layout
require ModuleHelper::getLayoutPath($mod_name, $params->get('layout', 'default'));
