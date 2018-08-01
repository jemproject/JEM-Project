<?php
/**
 * @version 2.2.2
 * @package JEM
 * @subpackage JEM - Module-Calendar(AJAX)
 * @copyright (C) 2015-2017 joomlaeventmanager.net
 * @copyright (C) 2008-2010 Toni Smillie www.qivva.com
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Version 1.00
 * First Ajax version based on 0.94 eventlistcal15q
 * Thanks to Bart Eversdijk for the Ajax conversion and Piotr Konieczny for assistance in porting of changes
 * Original Eventlist calendar from Christoph Lukes www.schlu.net
 * PHP Calendar (version 2.3), written by Keith Devens
 * http://keithdevens.com/software/php_calendar
 * see example at http://keithdevens.com/weblog
 * License: http://keithdevens.com/software/license
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/helper.php';
require_once JPATH_SITE . '/components/com_jem/helpers/route.php';
require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
require_once JPATH_SITE . '/components/com_jem/factory.php';

// include mootools or bootstrap tooltip
JHtml::_('behavior.tooltip');
if (version_compare(JVERSION, '3.3', 'ge')) {
	JHtml::_('bootstrap.tooltip');
}

	// Parameters
	$app                 = JFactory::getApplication();
	$day_name_length     = $params->get('day_name_length', '2');
	$first_day           = $params->get('first_day', '1');
	$Year_length         = $params->get('Year_length', '1');
	$Month_length        = $params->get('Month_length', '0');
	$Month_offset        = $params->get('Month_offset', '0');
	$Time_offset         = $params->get('Time_offset', '0');
	$Show_Tooltips       = $params->get('Show_Tooltips', '1');
	$Show_Tooltips_Title = $params->get('Show_Tooltips_Title', '1');
	$Remember            = $params->get('Remember', '1');
	$LocaleOverride      = $params->get('locale_override', '');
	$CalTooltipsTitle    = $params->get('cal15q_tooltips_title', JText::_('MOD_JEM_CALAJAX_EVENT'));
	$CalTooltipsTitlePl  = $params->get('cal15q_tooltipspl_title', JText::_('MOD_JEM_CALAJAX_EVENTS'));
	$UseJoomlaLanguage   = $params->get('UseJoomlaLanguage', '1');
	$Default_Stylesheet  = $params->get('Default_Stylesheet', '1');
	$User_stylesheet     = $params->get('User_stylesheet', 'modules/mod_jem_calajax/mod_jem_calajax.css');
	$tooltips_max_events = $params->get('tooltips_max_events', 0);
	$Itemid              = $app->input->request->getInt('Itemid', 0);

	if (!empty($LocaleOverride)) {
		setlocale(LC_ALL, $LocaleOverride);
	}

	// get switch trigger
	$req_month 	= $app->input->request->getInt('el_mcal_month');
	$req_year 	= $app->input->request->getInt('el_mcal_year');

	if ($Remember == 1) { // Remember which month / year is selected. Don't jump back to tday on page change
		if ($req_month == 0) {
			$req_month = $app->getUserState("eventlistcalqmonth".$module->id);
			$req_year  = $app->getUserState("eventlistcalqyear".$module->id);
		} else {
			$app->setUserState("eventlistcalqmonth".$module->id, $req_month);
			$app->setUserState("eventlistcalqyear".$module->id, $req_year);
		}
	}

	//set now
	$config      = JFactory::getConfig();
	$tzoffset    = $config->get('config.offset');
	$time        = time() + (($tzoffset + $Time_offset)*60*60);
	$today_month = date('m', $time);
	$today_year  = date('Y', $time);
	$today       = date('j', $time);

	if ($req_month == 0) $req_month = $today_month;
	$offset_month = $req_month + $Month_offset;
	if ($req_year == 0) $req_year = $today_year;
	$offset_year = $req_year;

	if ($offset_month > 12) {
		$offset_month = $offset_month -12; // Roll over year end
		$offset_year = $req_year + 1;
	}

	// Setting the previous and next month numbers
	$prev_month_year = $req_year;
	$next_month_year = $req_year;

	$prev_month = $req_month - 1;
	if ($prev_month < 1) {
		$prev_month = 12;
		$prev_month_year = $prev_month_year - 1;
	}

	$next_month = $req_month + 1;
	if ($next_month > 12) {
		$next_month = 1;
		$next_month_year = $next_month_year + 1;
	}

	// Create AJAX Links -- MooTool frame work was include by toolip behavior
	$base = JURI::base().'modules/mod_'.$module->name.'/mod_ajaxloader.php?modid='.$module->id.'&Itemid='.$Itemid;
	$prev_link = $base.'&el_mcal_month='.$prev_month.'&el_mcal_year='.$prev_month_year;
	$next_link = $base.'&el_mcal_month='.$next_month.'&el_mcal_year='.$next_month_year;
	$home_link = $base.'&el_mcal_month='.$today_month.'&el_mcal_year='.$today_year;


	$days = ModJemCalajaxHelper::getdays($offset_year, $offset_month, $params);

	require JModuleHelper::getLayoutPath('mod_jem_calajax');
