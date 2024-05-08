<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

// get module helper
require_once __DIR__.'/helper.php';

//require needed component classes
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/classes/image.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');

Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE.'/components/com_jem');

switch($params->get('color')) {
	case 'red':
	case 'blue':
	case 'green':
	case 'orange':
	case 'category':
		$color = $params->get('color');
		break;
	default:
		$color = "red";
		// ensure getList() always gets a valid 'color' setting
		$params->set('color', $color);
		break;
}

$list = ModJemTeaserHelper::getList($params);

// check if any results returned
if (empty($list) && !$params->get('show_no_events')) {
	return;
}

$mod_name = 'mod_jem_teaser';
$jemsettings = JemHelper::config();
$iconcss = $mod_name . (($jemsettings->useiconfont == 1) ? '_iconfont' : '_iconimg');
JemHelper::loadModuleStyleSheet($mod_name);
JemHelper::loadModuleStyleSheet($mod_name, $color);
JemHelper::loadModuleStyleSheet($mod_name, $iconcss);

// load icon font if needed
JemHelper::loadIconFont();

require(JemHelper::getModuleLayoutPath($mod_name));
