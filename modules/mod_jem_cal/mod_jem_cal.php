<?php
/**
 * @version 1.9.6
 * @package JEM
 * @subpackage JEM Calendar Module
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2008 Toni Smillie www.qivva.com
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Original Eventlist calendar from Christoph Lukes www.schlu.net
 * PHP Calendar (version 2.3), written by Keith Devens
 * http://keithdevens.com/software/php_calendar
 * see example at http://keithdevens.com/weblog
 * License: http://keithdevens.com/software/license
 */

defined('_JEXEC') or die;

require_once(dirname(__FILE__).'/helper.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');


// include mootools tooltip
JHtml::_('behavior.tooltip');

// Parameters
$app = JFactory::getApplication();
$day_name_length	= $params->get('day_name_length', '2');
$first_day			= $params->get('first_day', '1');
$Year_length		= $params->get('Year_length', '1');
$Month_length		= $params->get('Month_length', '0');
$Month_offset		= $params->get('Month_offset', '0');
$Time_offset		= $params->get('Time_offset', '0');
$Show_Tooltips		= $params->get('Show_Tooltips', '1');
$Show_Tooltips_Title= $params->get('Show_Tooltips_Title', '1');
$Remember			= $params->get('Remember', '1');
$LocaleOverride		= $params->get('locale_override', '');
$CalTooltipsTitle	= $params->get('cal15q_tooltips_title', JText::_('MOD_JEM_CAL_EVENT'));
$CalTooltipsTitlePl	= $params->get('cal15q_tooltipspl_title', JText::_('MOD_JEM_CAL_EVENTS'));
$UseJoomlaLanguage	= $params->get('UseJoomlaLanguage', '1');
$Default_Stylesheet	= $params->get('Default_Stylesheet', '1');
$User_stylesheet	= $params->get('User_stylesheet', 'modules/mod_jem_cal/mod_jem_cal.css');

if (empty($LocaleOverride)) {
} else {
	setlocale(LC_ALL, $LocaleOverride);
}

//get switch trigger
$req_month 	= (int)JRequest::getVar('el_mcal_month', '', 'request');
$req_year 	= (int)JRequest::getVar('el_mcal_year', '', 'request');

if ($Remember == 1) { // Remember which month / year is selected. Don't jump back to tday on page change
	if ($req_month == 0) {
		$req_month = $app->getUserState("jemcalqmonth");
		$req_year = $app->getUserState("jemcalqyear");
	} else {
		$app->setUserState("jemcalqmonth",$req_month);
		$app->setUserState("jemcalqyear",$req_year);
	}
}

//Requested URL
$uri = JURI::getInstance();
$myurl = $uri->toString(array('query'));

//08/09/09 - Added Fix for sh404sef
if (empty($myurl)) {
	$request_link = $uri->toString(array('path')).'?';
} else {
	$request_link = $uri->toString(array('path')).$myurl;
	$request_link = str_replace("&el_mcal_month=".$req_month,"",$request_link);
	$request_link = str_replace("&el_mcal_year=".$req_year,"",$request_link);
}

//set now
$config = JFactory::getConfig();
$tzoffset = $config->get('config.offset');
$time 			= time() + (($tzoffset + $Time_offset)*60*60); //25/2/08 Change for v 0.6 to incorporate server offset into time;
$today_month 	= date('m', $time);
$today_year 	= date('Y', $time);
$today 			= date('j',$time);

if ($req_month == 0) $req_month = $today_month;
$offset_month = $req_month + $Month_offset;
if ($req_year == 0) $req_year = $today_year;
$offset_year = $req_year;

if ($offset_month > 12) {
	$offset_month = $offset_month -12; // Roll over year end
	$offset_year = $req_year + 1;
}

//Setting the previous and next month numbers
$prev_month_year = $req_year;
$next_month_year = $req_year;

$prev_month = $req_month - 1;
if($prev_month < 1) {
	$prev_month = 12;
	$prev_month_year = $prev_month_year - 1;
}

$next_month = $req_month + 1;
if($next_month > 12) {
	$next_month = 1;
	$next_month_year = $next_month_year+1;
}

//Create Links
$plink = $request_link.'&el_mcal_month='.$prev_month.'&el_mcal_year='.$prev_month_year;
$nlink = $request_link.'&el_mcal_month='.$next_month.'&el_mcal_year='.$next_month_year;

$prev_link = JRoute::_($plink, false);
$next_link = JRoute::_($nlink, false);

$days = modjemcalqHelper::getdays($offset_year, $offset_month, $params);

require(JModuleHelper::getLayoutPath('mod_jem_cal'));
?>