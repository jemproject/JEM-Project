<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
// get helper
require_once __DIR__ . '/helper.php';

require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');

Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE.'/components/com_jem');

$list = ModJemHelper::getList($params);

// check if any results returned
if (empty($list) && !$params->get('show_no_events')) {
	return;
}

$mod_name = 'mod_jem';

// maybe a layout style provides a css file
JemHelper::loadModuleStyleSheet($mod_name);
// load icon font if needed
JemHelper::loadIconFont();

require(JemHelper::getModuleLayoutPath($mod_name));
