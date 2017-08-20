<?php
/**
 * @version 2.2.2
 * @package JEM
 * @subpackage JEM - Module-Calendar(AJAX)
 * @copyright (C) 2015-2017 joomlaeventmanager.net
 * @copyright (C) 2008-2010 Toni Smillie www.qivva.com
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*/

defined('_JEXEC') or die();

//Month Names
$uxtime_first_of_month = gmmktime(0, 0, 0, $prev_month, 1, $offset_year);
list($tmp, $year, $prev_month, $weekday) = explode(',', gmstrftime('%m,%Y,%b,%w', $uxtime_first_of_month));

$uxtime_first_of_month = gmmktime(0, 0, 0, $next_month, 1, $offset_year);
list($tmp, $year, $next_month, $weekday) = explode(',', gmstrftime('%m,%Y,%b,%w', $uxtime_first_of_month));

//Creating switching links
$pn = array($prev_month=>$prev_link, $next_month=>$next_link);

// Use MooTools to navigate through the months
if (!defined('_IN_AJAXCALL')) { ?>
<script type="text/javascript">
function mod_jem_calajax_click<?php print $module->id; ?>(url) {
	jQuery('#eventcalq<?php echo $module->id.'\'';?>).load(url, function () {
		jQuery(".hasTooltip").tooltip();
	});
}
</script>
<?php
 }

$document = JFactory::getDocument();
if ($Default_Stylesheet == 1) {
	$document->addStyleSheet(JUri::base() . 'modules/mod_jem_calajax/mod_jem_calajax.css');
} else {
	$document->addStyleSheet(JUri::base() . $User_stylesheet);
}

//Output
if (!defined('_IN_AJAXCALL')) {
    echo '<div class="eventcalq'.$params->get('moduleclass_sfx').'" align="center" id="eventcalq' . $module->id . '">';
}

	$calendar = '';
	$month_href = NULL;
	$year = $offset_year;
	$month = $offset_month;

    $uxtime_first_of_month = gmmktime(0, 0, 0, $month, 1, $year);
    #remember that mktime will automatically correct if invalid dates are entered
    # for instance, mktime(0,0,0,12,32,1997) will be the date for Jan 1, 1998
    # this provides a built in "rounding" feature to generate_calendar()

    $day_names = array(); #generate all the day names according to the current locale
	$day_names_short = array();
	$day_names_long = array();

	$user = JFactory::getUser();
	$userLanguage = $user->getParam('language');

	if ($userLanguage) {
		$lng = $userLanguage;
	} else {
		$lng = JComponentHelper::getParams('com_languages')->get('site');
	}
	$lang = JFactory::getLanguage();
	$lang->setLanguage($lng);
	$lang->load();

	if ($UseJoomlaLanguage == 1) {
		if ($first_day == 1) {
			$day_names_long = array(JText::_('MONDAY'),JText::_('TUESDAY'),JText::_('WEDNESDAY'),JText::_('THURSDAY'),JText::_('FRIDAY'),JText::_('SATURDAY'),JText::_('SUNDAY'));
			$day_names_short = array(JText::_('MON'),JText::_('TUE'),JText::_('WED'),JText::_('THU'),JText::_('FRI'),JText::_('SAT'),JText::_('SUN'));
		} else {
			$day_names_long = array(JText::_('SUNDAY'),JText::_('MONDAY'),JText::_('TUESDAY'),JText::_('WEDNESDAY'),JText::_('THURSDAY'),JText::_('FRIDAY'),JText::_('SATURDAY'));
			$day_names_short = array(JText::_('SUN'),JText::_('MON'),JText::_('TUE'),JText::_('WED'),JText::_('THU'),JText::_('FRI'),JText::_('SAT'));
		}
	} else {
		for ($n = 0, $t = (3 + $first_day) *24 *60 *60; $n < 7; ++$n, $t += 24 *60 *60) { #January 4, 1970 was a Sunday
			if (!function_exists('mb_convert_case')) {
			   $day_names_long[$n] = ucfirst(gmstrftime('%A',$t)); #%A means full textual day name
			   $day_names_short[$n] = ucfirst(gmstrftime('%A',$t)); #%a means short day name
			} else {
				$day_names_long[$n] = mb_convert_case(gmstrftime('%A',$t),MB_CASE_TITLE, "UTF-8"); #%A means full textual day name
				$day_names_short[$n] = mb_convert_case(gmstrftime('%A',$t),MB_CASE_TITLE, "UTF-8"); #%a means short day name
			}
		}
	}

	list($month, $year, $month_name_long, $month_name_short, $weekday) = explode(',', gmstrftime('%m,%Y,%B,%b,%w', $uxtime_first_of_month));
	if ($UseJoomlaLanguage == 1) {
		$month_names = array('', 'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER');
		$month_name_short = JText::_($month_names[(int)$month] . '_SHORT');
		$month_name_long  = JText::_($month_names[(int)$month]);
	}
    $weekday = ($weekday + 7 - $first_day) % 7; #adjust for $first_day
	$year_length = $Year_length ? $year : substr($year, 2, 3);

	if (!function_exists('mb_convert_case')) {
		$the_month = ucfirst($Month_length ? htmlentities($month_name_short,ENT_COMPAT,"UTF-8") :htmlentities($month_name_long,ENT_COMPAT,"UTF-8"));
	} else {
		$the_month = mb_convert_case($Month_length ? $month_name_short : $month_name_long ,MB_CASE_TITLE, "UTF-8");
	}

	$title = $the_month.'&nbsp;'.$year_length;    #note that some locales don't capitalize month and day names

	#Begin calendar. Uses a real <caption>. See http://diveintomark.org/archives/2002/07/03

	#previous and next links, if applicable
	@list($p, $pl) = each($pn);
	@list($n, $nl) = each($pn);
	$calendar .= '<table class="mod_jem_calajax_calendar" cellspacing="0" cellpadding="0">'."\n";
	$calendar .= '<caption class="mod_jem_calajax_calendar-month">';

	if ($p) {
		if ($pl) {
			$calendar .= '<a href="#" onClick="mod_jem_calajax_click'.$module->id.'(\''.htmlspecialchars($pl).'\'); return false;"> &lt;&lt;</a>&nbsp;';
		} else {
			$calendar .= $p."&nbsp;";
		}
	}
	$calendar .= '<span class="evtq_home"><a href="#" onClick="mod_jem_calajax_click'.$module->id.'(\''.htmlspecialchars($home_link).'\'); return false;">'.$title.'</a></span>';
	if ($n) {
		if ($nl) {
			$calendar .= '<a href="#" onClick="mod_jem_calajax_click'.$module->id.'(\''.htmlspecialchars($nl).'\'); return false;"> &gt;&gt;</a>&nbsp;';
		} else {
			$calendar .= $n."&nbsp;";
		}
	}
	$calendar .= "</caption>";
	$calendar .= "<tr>";


	if ($day_name_length) { #if the day names should be shown ($day_name_length > 0)
		#if day_name_length is >3, the full name of the day will be printed
		if ($day_name_length >3) {
			foreach($day_names_long as $d) {
				$calendar .= '<th class="mod_jem_calajax_daynames" abbr="'.$d.'">&nbsp;'.$d.'&nbsp;</th>';
			}
			$calendar .= "</tr>\n<tr>";
		} else {
			foreach($day_names_short as $d) {
				if (function_exists('mb_substr')) {
					$calendar .= '<th class="mod_jem_calajax_daynames" abbr="'.$d.'">&nbsp;'.mb_substr($d,0,$day_name_length,'UTF-8').'&nbsp;</th>';
				} else {
					$calendar .= '<th class="mod_jem_calajax_daynames" abbr="'.$d.'">&nbsp;'.substr($d,0,$day_name_length).'&nbsp;</th>';
				}
			}
			$calendar .= "</tr>\n<tr>";
		}
	}

	// Today
	$config    = JFactory::getConfig();
	$tzoffset  = $config->get('config.offset');
	$time      = time() + (($tzoffset + $Time_offset)*60*60); //25/2/08 Change for v 0.6 to incorporate server offset into time;
	$today     = date('j', $time);
	$currmonth = date('m', $time);
	$curryear  = date('Y', $time);

	for ($counti = 0; $counti < $weekday; $counti++) {
		$calendar .= '<td class="mod_jem_calajax">&nbsp;</td>'; #initial 'empty' days
	}

	for ($day = 1, $days_in_month = gmdate('t', $uxtime_first_of_month); $day <= $days_in_month; $day++, $weekday++) {
		if ($weekday == 7) {
			$weekday = 0; #start a new week
			$calendar .= "</tr>\n<tr>";
		}

		if (($day == $today) & ($currmonth == $month) & ($curryear == $year)) {
			$istoday = 1;
		} else {
			$istoday = 0;
		}
		$tdbaseclass = ($istoday) ? 'mod_jem_calajax_caltoday' : 'mod_jem_calajax_calday';

		//space in front of daynumber when day < 10
		($day < 10) ? $space = '&nbsp;&nbsp;': $space = '';

		if (isset($days[$day][1])) {
			$link = $days[$day][0];
			$title = $days[$day][1];

			if ($Show_Tooltips == 1) {
				$calendar .= '<td class="'.$tdbaseclass.'link">';
				if ($link) {
					$tip = '';
					$title = explode('+%+%+', $title);
					if ($Show_Tooltips_Title == 1) {
						if (count($title) > 1) {
							$tipTitle = count($title) . ' ' . JText::_($CalTooltipsTitlePl);
						} else {
							$tipTitle = '1 ' . JText::_($CalTooltipsTitle);
						}
					} else {
						$tipTitle = '';
					}

				if (version_compare(JVERSION, '3.3', 'lt')) {
					// There is a bug in Joomla which will format complete tip text as title
					//  if $tipTitle is empty (because then no '::' will be added).
					//  So add it manually and let title param empty.
					$tip = $tipTitle . '::';
					$tipTitle = '';
				}

					// if user hadn't explicitely typed in a 0 list limited number or all events
					if ($tooltips_max_events !== '0') {
						$count = 0;
						foreach ($title as $t) {
							if (($tooltips_max_events > 0) && (++$count > $tooltips_max_events)) {
								$tip .= '...';
								break; // foreach
							}
							$tip .= trim($t) . '<br />';
						}
					}

					$calendar .= JHtml::tooltip($tip, $tipTitle, 'tooltip.png', $space.$day, $link);
				}

				$calendar .= '</td>';
			} else {
				$calendar .= '<td class="'.$tdbaseclass.'link">'.($link ? '<a href="'.$link.'">'.$space.$day.'</a>' : $space.$day).'</td>';
			}
		} else {
			$calendar .= '<td class="'.$tdbaseclass.'"><span class="nolink">'.$space.$day.'</span></td>';
		}
	}

	for ($counti = $weekday; $counti < 7; $counti++) {
		$calendar .= '<td class="mod_jem_calajax">&nbsp;</td>'; #remaining 'empty' days
	}

	echo $calendar."</tr>\n</table>\n";

if (!defined('_IN_AJAXCALL')) {
	echo "</div>";
}