<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @subpackage JEM Calendar Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2008 Toni Smillie www.qivva.com
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Original Eventlist calendar from Christoph Lukes
 * PHP Calendar (version 2.3), written by Keith Devens
 * https://keithdevens.com/software/php_calendar
 * see example at https://keithdevens.com/weblog
 * License: https://keithdevens.com/software/license
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

require_once __DIR__ . '/helper.php';
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');

# Create JEM's file logger (for debug)
JemHelper::addFileLogger();

# Parameters
$app                 = Factory::getApplication();
$day_name_length     = $params->get('day_name_length', '2');
$first_day           = $params->get('first_day', '1');
$Year_length         = $params->get('Year_length', '1');
$Month_length        = $params->get('Month_length', '0');
$Month_offset        = $params->get('Month_offset', '0');
$Time_offset         = $params->get('Time_offset', '0');
$Show_Tooltips       = $params->get('Show_Tooltips', '1');
$Show_Tooltips_Title = $params->get('Show_Tooltips_Title', '1');
$Remember            = $params->get('Remember', '1');
$use_ajax            = $params->get('use_ajax', '1');
$CalTooltipsTitle    = $params->get('cal15q_tooltips_title', Text::_('MOD_JEM_CAL_EVENT'));
$CalTooltipsTitlePl  = $params->get('cal15q_tooltipspl_title', Text::_('MOD_JEM_CAL_EVENTS'));
$Default_Stylesheet  = $params->get('Default_Stylesheet', '1');
$User_stylesheet     = $params->get('User_stylesheet', 'modules/mod_jem_cal/tmpl/mod_jem_cal.css');
$tooltips_max_events = $params->get('tooltips_max_events', 0);
$Itemid              = $app->input->request->getInt('Itemid', 0);

if($Itemid ==0){
	
	$Itemid = $app->getMenu()->getActive()->id;
}

# AJAX requires at least J! 3.2.7 (because we use com_ajax)
$use_ajax &= version_compare(JVERSION, '3.2.7', 'ge');

# NEVER use setlocale() - see php manual why
//if (!empty($LocaleOverride)) {
//	setlocale(LC_ALL, $LocaleOverride);
//}

# Get switch trigger
$req_modid = $app->input->getInt('modjemcal_id');
if ((int)$module->id === $req_modid) {
	$req_month = $app->input->request->getInt('modjemcal_month');
	$req_year  = $app->input->request->getInt('modjemcal_year');
} else {
	$req_month = $req_year = 0;
}

# Remember which month / year is selected. Don't jump back to today on page change
if ($Remember == 1) {
	if ($req_month == 0) {
		$req_month = $app->getUserState("modjemcal.month.".$module->id);
		$req_year  = $app->getUserState("modjemcal.year.".$module->id);
	} else {
		$app->setUserState("modjemcal.month.".$module->id, $req_month);
		$app->setUserState("modjemcal.year.".$module->id, $req_year);
	}
}

# Set today
$config      = Factory::getConfig();
$tzoffset    = $config->get('config.offset');
$time        = time() + (($tzoffset + $Time_offset) * 60 * 60);
$today_month = date('m', $time);
$today_year  = date('Y', $time);
$today       = date('j', $time);

if ($req_month == 0) { $req_month = $today_month; }
if ($req_year  == 0) { $req_year  = $today_year;  }

$offset_month = $req_month + $Month_offset;
$offset_year  = $req_year;

if ($offset_month < 1) {
	$offset_month += 12; // Roll over year start
	--$offset_year;
}

if ($offset_month > 12) {
	$offset_month -= 12; // Roll over year end
	++$offset_year;
}

# Setting the previous and next month numbers
$prev_month_year = $req_year;
$next_month_year = $req_year;

$prev_month = $req_month - 1;
if ($prev_month < 1) {
	$prev_month = 12;
	--$prev_month_year;
}

$next_month = $req_month + 1;
if ($next_month > 12) {
	$next_month = 1;
	++$next_month_year;
}

$prev_year = $req_year - 1;
$next_year = $req_year + 1;

# Requested URL
$uri   = Uri::getInstance();
$myurl = $uri->toString(array('query'));

//08/09/09 - Added Fix for sh404sef
if (empty($myurl)) {
	$request_link = $uri->toString(array('path')).'?';
} else {
	# Remove modjemcal params from url
	$request_link = $uri->toString(array('path')).$myurl;
	$request_link = preg_replace('/&modjemcal_(month|year|id)=\d+/i', '', $request_link);
}

$ajax_link = Uri::base().'?option=com_ajax&module=jem_cal&format=raw'.'&Itemid='.$Itemid;

# By default use links working on browsers with JavaScript disabled.
# Then let a JavaScript change this to ajax links.
$url_base_nojs = Route::_($request_link, false) . '&modjemcal_id='.$module->id;
$url_base_ajax = Route::_($ajax_link, false) . '&modjemcal_id='.$module->id;

# Create link params - template must concatenate one of the url_bases above and one of the props below.
$props_prev = '&modjemcal_month='.$prev_month.'&modjemcal_year='.$prev_month_year;
$props_next = '&modjemcal_month='.$next_month.'&modjemcal_year='.$next_month_year;
$props_home = '&modjemcal_month='.$today_month.'&modjemcal_year='.$today_year;
$props_prev_year = '&modjemcal_month='.$req_month.'&modjemcal_year='.$prev_year;
$props_next_year = '&modjemcal_month='.$req_month.'&modjemcal_year='.$next_year;

# Get data
$params->set('module_id', $module->id); // used for debug log
$days = ModJemCalHelper::getDays($offset_year, $offset_month, $params);

$mod_name = 'mod_jem_cal';

# Add css
if ($Default_Stylesheet == 1) {
	JemHelper::loadModuleStyleSheet($mod_name);
} else {
	$document = $app->getDocument();
	$document->addStyleSheet(Uri::base() . $User_stylesheet);
}
$wa = Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('jquery');
# Load icon font if needed
JemHelper::loadIconFont();

# Render
require(JemHelper::getModuleLayoutPath($mod_name));

?>
