<?php
/**
 * @package    JEM
 * @subpackage JEM Wide Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

$mod_name = 'mod_jem_wide';

// get helper
require_once __DIR__ . '/helper.php';
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');
require_once(JPATH_SITE.'/components/com_jem/classes/image.class.php');

Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE.'/components/com_jem');

$list = ModJemWideHelper::getList($params);
// check if any results returned
if (empty($list) && !$params->get('show_no_events')) {
    return;
}

$jemsettings = JemHelper::config();
$layout = substr(strstr($params->get('layout', 'default'), ':'), 1);
$iconcss = $mod_name . (($jemsettings->useiconfont == 1) ? '_iconfont' : '_iconimg') ;
JemHelper::loadModuleStyleSheet($mod_name, $mod_name . '_' . $layout);
JemHelper::loadModuleStyleSheet($mod_name, $iconcss);

// load icon font if needed
JemHelper::loadIconFont();

require ModuleHelper::getLayoutPath($mod_name, $params->get('layout', 'default'));
