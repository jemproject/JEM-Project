<?php
/**
 * @version 1.9.6
 * @package JEM
 * @subpackage JEM Teaser Module
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// get module helper
require_once(dirname(__FILE__).'/helper.php');

//require needed component classes
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/classes/image.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');

$paramsdatemethod = $params->get('datemethod', 1);
$paramsuse_modal = $params->get('use_modal', 0);

$document = JFactory::getDocument();
$list = modJEMteaserHelper::getList($params);

// check if any results returned
if (count($list) == 0) {
	return;
}

switch($params->get('color')) {
	case 'red':
	case 'blue':
	case 'green':
	case 'orange':
		$color = $params->get('color');
		break;
	default:
		$color = "red";
		break;
}
$document->addStyleSheet(JURI::base(true).'/modules/mod_jem_teaser/tmpl/mod_jem_teaser.css');
$document->addStyleSheet(JURI::base(true).'/modules/mod_jem_teaser/tmpl/'.$color.'.css');

require(JModuleHelper::getLayoutPath('mod_jem_teaser'));