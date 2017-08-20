<?php
/**
 * @version 2.2.2
 * @package JEM
 * @subpackage JEM Banner Module
 * @copyright (C) 2014-2017 joomlaeventmanager.net
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
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');

JFactory::getLanguage()->load('com_jem', JPATH_SITE.'/components/com_jem');

switch($params->get('color')) {
	case 'red':
	case 'blue':
	case 'green':
	case 'orange':
	case 'category':
	case 'alpha':
		$color = $params->get('color');
		break;
	default:
		$color = "red";
		// ensure getList() always gets a valid 'color' setting
		$params->set('color', $color);
		break;
}

$list = ModJemBannerHelper::getList($params);

// check if any results returned
if (empty($list) && !$params->get('show_no_events')) {
	return;
}

$document = JFactory::getDocument();
$document->addStyleSheet(JUri::base(true).'/modules/mod_jem_banner/tmpl/mod_jem_banner.css');
$document->addStyleSheet(JUri::base(true).'/modules/mod_jem_banner/tmpl/'.$color.'.css');

require(JModuleHelper::getLayoutPath('mod_jem_banner'));