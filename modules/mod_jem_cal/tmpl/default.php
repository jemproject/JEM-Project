<?php
/**
 * @version    4.2.1
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

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

# Ensure $use_ajax is defined and boolean
$use_ajax = !empty($use_ajax);

# Use Ajax to navigate through the months if JS is enabled on browser.
if ($use_ajax && empty($module->in_ajax_call)) { ?>
<script type="text/javascript">
jQuery(document).ready(function(){
	jQuery('#mod_jem_cal_<?php print $module->id; ?>_navi_nojs').css("display", "none");
	jQuery('#mod_jem_cal_<?php print $module->id; ?>_navi_ajax').css("display", "table-caption");
});
function mod_jem_cal_click_<?php print $module->id; ?>(url) {
	jQuery('#eventcalq<?php echo $module->id;?>').load(url, function () {
		jQuery(".hasTooltip").tooltip({'html':true});		
	});
}
</script>
<?php
}

# Output
if (!$use_ajax || empty($module->in_ajax_call)) {
	echo '<div class="eventcalq' . $params->get('moduleclass_sfx') . '" id="eventcalq' . $module->id . '">';
}

$calendar = '';

$year  = $offset_year;
$month = $offset_month;

$uxtime_first_of_month = gmmktime(0, 0, 0, $month, 1, $year);
# Remember that mktime will automatically correct if invalid dates are entered
#  for instance, mktime(0,0,0,12,32,1997) will be the date for Jan 1, 1998
#  this provides a built in "rounding" feature to generate_calendar()
$month_weekday = gmdate('w', $uxtime_first_of_month);
$days_in_month = gmdate('t', $uxtime_first_of_month);

$day_names_long  = array('SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY');
$day_names_short = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');
$month_names     = array('', 'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER');

$month_name_short = Text::_($month_names[(int)$month] . '_SHORT');
$month_name_long  = Text::_($month_names[(int)$month]);
$weekday  = ($month_weekday + 7 - $first_day) % 7;    # adjust for $first_day of week
$the_year = $Year_length ? $year : substr($year, -2); # full or last two digits

if (!function_exists('mb_convert_case')) {
	$the_month = ucfirst(htmlentities($Month_length ? $month_name_short : $month_name_long, ENT_COMPAT, "UTF-8"));
	$the_month_prev  = ucfirst(htmlentities(Text::_($month_names[(int)$prev_month]  . ($Month_length ? '_SHORT' : '')), ENT_COMPAT, "UTF-8"));
	$the_month_next  = ucfirst(htmlentities(Text::_($month_names[(int)$next_month]  . ($Month_length ? '_SHORT' : '')), ENT_COMPAT, "UTF-8"));
	$the_month_today = ucfirst(htmlentities(Text::_($month_names[(int)$today_month] . ($Month_length ? '_SHORT' : '')), ENT_COMPAT, "UTF-8"));
} else {
	$the_month = mb_convert_case($Month_length ? $month_name_short : $month_name_long, MB_CASE_TITLE, "UTF-8");
	$the_month_prev  = mb_convert_case(Text::_($month_names[(int)$prev_month]  . ($Month_length ? '_SHORT' : '')), MB_CASE_TITLE, "UTF-8");
	$the_month_next  = mb_convert_case(Text::_($month_names[(int)$next_month]  . ($Month_length ? '_SHORT' : '')), MB_CASE_TITLE, "UTF-8");
	$the_month_today = mb_convert_case(Text::_($month_names[(int)$today_month] . ($Month_length ? '_SHORT' : '')), MB_CASE_TITLE, "UTF-8");
}

$title = $the_month . '&nbsp;' . $the_year;

# Begin calendar. Uses a real <caption>. See https://diveintomark.org/archives/2002/07/03
$calendar .= '<table class="mod_jemcalq_calendar" cellspacing="0" cellpadding="0">' . "\n";

# Month navigation links
# use $url_base_nojs or $url_base_ajax followed by $props_prev, $props_home, or $props_next
$navi_nojs  = '<caption class="mod_jemcalq_calendar-month caption-top" id="mod_jem_cal_' . $module->id . '_navi_nojs" style="display:' . (!$use_ajax || empty($module->in_ajax_call) ? 'table-caption' : 'none') . '">';
$navi_nojs .= $props_prev_year ? ('<a href="' . htmlspecialchars($url_base_nojs . $props_prev_year) . '" title="' . $the_month . ' ' . $prev_year . '">&lt;&lt;</a>&nbsp;&nbsp;') : '&lt;&lt;&nbsp;&nbsp;';
$navi_nojs .= $props_prev      ? ('<a href="' . htmlspecialchars($url_base_nojs . $props_prev) . '" title="' . $the_month_prev . ' ' . $prev_month_year . '">&lt;</a>&nbsp;&nbsp;') : '&lt;&nbsp;&nbsp;';
$navi_nojs .= $props_home      ? ('<span class="evtq_home"><a href="' . htmlspecialchars($url_base_nojs . $props_home) . '" title="' . $the_month_today . ' ' . $today_year . '">' . $title . '</a></span>') : $title;
$navi_nojs .= $props_next      ? ('&nbsp;&nbsp;<a href="' . htmlspecialchars($url_base_nojs . $props_next) . '" title="' . $the_month_next . ' ' . $next_month_year . '">&gt;</a>') : '&nbsp;&nbsp;&gt;';
$navi_nojs .= $props_next_year ? ('&nbsp;&nbsp;<a href="' . htmlspecialchars($url_base_nojs . $props_next_year) . '" title="' . $the_month . ' ' . $next_year . '">&gt;&gt;</a>') : '&nbsp;&nbsp;&gt;&gt;';
$navi_nojs .= '</caption>';
$calendar  .= $navi_nojs;

if ($use_ajax) {
	$navi_ajax  = '<caption class="mod_jemcalq_calendar-month caption-top" id="mod_jem_cal_' . $module->id . '_navi_ajax" style="display:' . (empty($module->in_ajax_call) ? 'none' : 'table-caption') . '">';
	$navi_ajax .= $props_prev_year ? ('<a href="#" title="' . $the_month . ' ' . $prev_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_prev_year) . '\'); return false;">&lt;&lt;</a>&nbsp;&nbsp;') : '&lt;&lt;&nbsp;&nbsp;';
	$navi_ajax .= $props_prev      ? ('<a href="#" title="' . $the_month_prev . ' ' . $prev_month_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_prev) . '\'); return false;">&lt;</a>&nbsp;&nbsp;') : '&lt;&nbsp;&nbsp;';
	$navi_ajax .= $props_home      ? ('<span class="evtq_home"><a href="#" title="' . $the_month_today . ' ' . $today_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_home) . '\'); return false;">' . $title . '</a></span>') : $title;
	$navi_ajax .= $props_next      ? ('&nbsp;&nbsp;<a href="#" title="' . $the_month_next . ' ' . $next_month_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_next) . '\'); return false;">&gt;</a>') : '&nbsp;&nbsp;&gt;';
	$navi_ajax .= $props_next_year ? ('&nbsp;&nbsp;<a href="#" title="' . $the_month . ' ' . $next_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_next_year) . '\'); return false;">&gt;&gt;</a>') : '&nbsp;&nbsp;&gt;&gt;';
	$navi_ajax .= '</caption>';
	$calendar  .= $navi_ajax;
}

# If the day names should be shown ($day_name_length > 0)
if ($day_name_length) {
	$calendar .= '<tr>';
	# If day_name_length is >3, the full name of the day will be printed
	if ($day_name_length > 3) {
		for ($d = 0; $d < 7; ++$d) {
			$dayname = Text::_($day_names_long[($d + $first_day) % 7]);
			$calendar .= '<th class="mod_jemcalq_daynames" abbr="' . $dayname . '">&nbsp;' . $dayname . '&nbsp;</th>';
		}
	} else {
		for ($d = 0; $d < 7; ++$d) {
			$dayname = Text::_($day_names_short[($d + $first_day) % 7]);
			if (function_exists('mb_substr')) {
				$calendar .= '<th class="mod_jemcalq_daynames" abbr="' . $dayname . '">&nbsp;' . mb_substr($dayname, 0, $day_name_length, 'UTF-8') . '&nbsp;</th>';
			} else {
				$calendar .= '<th class="mod_jemcalq_daynames" abbr="' . $dayname . '">&nbsp;' . substr($dayname, 0, $day_name_length) . '&nbsp;</th>';
			}
		}
	}
	$calendar .= "</tr>\n";
}

# Today
$config    = Factory::getConfig();
$tzoffset  = $config->get('config.offset');
$time      = time() + (($tzoffset + $Time_offset) * 60 * 60); //25/2/08 Change for v 0.6 to incorporate server offset into time;
$today     = date('j', $time);
$currmonth = date('m', $time);
$curryear  = date('Y', $time);

# Switch off tooltips if neighter title nor text should be shown
if (($Show_Tooltips_Title == 0) && ($tooltips_max_events === '0')) {
	$Show_Tooltips = 0;
}

$calendar .= '<tr>';

# Initial 'empty' days
for ($counti = 0; $counti < $weekday; $counti++) {
	$calendar .= '<td class="mod_jemcalq">&nbsp;</td>';
}

# The days of interest
for ($day = 1; $day <= $days_in_month; $day++, $weekday++) {
	if ($weekday == 7) {
		$weekday = 0; #start a new week
		$calendar .= "</tr>\n<tr>";
	}

	$istoday = ($day == $today) && ($currmonth == $month) && ($curryear == $year);
	$tdbaseclass = ($istoday) ? 'mod_jemcalq_caltoday' : 'mod_jemcalq_calday';

	# Space in front of daynumber when day < 10
	$space = ($day < 10) ? '&nbsp;&nbsp;': '';

	if (isset($days[$day][1])) {
		$link = $days[$day][0];
		$title = $days[$day][1];

		if ($Show_Tooltips == 1) {
			$calendar .= '<td class="' . $tdbaseclass . 'link">';
			if ($link) {
				$tip = '';
				$title = explode('+%+%+', $title);
				if ($Show_Tooltips_Title == 1) {
					if (count($title) > 1) {
						$tipTitle = count($title) . ' ' . Text::_($CalTooltipsTitlePl);
					} else {
						$tipTitle = '1 ' . Text::_($CalTooltipsTitle);
					}
				} else {
					$tipTitle = '';
				}

				if (version_compare(JVERSION, '3.2.7', 'lt')) {
					# There is a bug in Joomla which will format complete tip text as title
					#  if $tipTitle is empty (because then no '::' will be added).
					#  So add it manually and let title param empty.
					$tip = $tipTitle . '::';
					$tipTitle = '';
				}

				# If user hadn't explicitely typed in a 0 list limited number or all events
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

				# J! version < 3.2.7: title already within $tip to ensure always '::' is present
				# But with J! 3.3+ is a bug in script so we need to use the bad 'hasTooltip'
				#  which is default of class parameter.
				$calendar .= HTMLHelper::tooltip($tip, $tipTitle, 'tooltip.png', $space . $day, $link);
			}

			$calendar .= '</td>';
		} else {
			$calendar .= '<td class="' . $tdbaseclass . 'link">' . ($link ? '<a href="' . $link . '">' . $space . $day . '</a>' : $space . $day) . '</td>';
		}
	} else {
		$calendar .= '<td class="' . $tdbaseclass . '"><span class="nolink">' . $space . $day . '</span></td>';
	}
}

# Remaining 'empty' days
for ($counti = $weekday; $counti < 7; $counti++) {
	$calendar .= '<td class="mod_jemcalq">&nbsp;</td>';
}

$calendar .= "</tr>\n</table>\n";
echo $calendar;

if (!$use_ajax || empty($module->in_ajax_call)) {
	echo "</div>";
}
