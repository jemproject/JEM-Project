<?php
/**
 * @version 2.3.0
 * @package JEM
 * @subpackage JEM Module
 * @copyright (C) 2013-2020 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

// get helper
require_once(dirname(__FILE__).'/helper.php');

require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');

JFactory::getLanguage()->load('com_jem', JPATH_SITE.'/components/com_jem');

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
