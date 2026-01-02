<?php
/**
 * @package    JEM
 * @subpackage JEM Calendar Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
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

JemHelper::loadModuleStyleSheet('mod_jem_cal', 'mod_jem_cal_grid');

# Ensure $use_ajax is defined and boolean
$use_ajax = !empty($use_ajax);

# Use Ajax to navigate through the months if JS is enabled on browser.
if ($use_ajax && empty($module->in_ajax_call)) {

?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const naviNoJs = document.getElementById("mod_jem_cal_<?php echo $module->id; ?>_navi_nojs");
    const naviAjax = document.getElementById("mod_jem_cal_<?php echo $module->id; ?>_navi_ajax");

    if (naviNoJs) naviNoJs.style.display = "none";
    if (naviAjax) naviAjax.style.display = "grid";
});

function mod_jem_cal_click_<?php echo $module->id; ?>(url) {
    const target = document.getElementById("eventcalq<?php echo $module->id; ?>");
    if (!target) return;

    // Carga de contenido vía Fetch API (sustituye a jQuery.load)
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error("Error al cargar el contenido");
            return response.text();
        })
        .then(html => {
            target.innerHTML = html;

            // Inicializar tooltips de Bootstrap 5+
            const tooltipTriggerList = [].slice.call(target.querySelectorAll('.hasTooltip'));
            tooltipTriggerList.map(function (el) {
                return new bootstrap.Tooltip(el, { html: true });
            });
        })
        .catch(err => console.error(err));
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
$calendar .= '<div class="mod_jemcalq_calendar">';

# Month navigation links
# use $url_base_nojs or $url_base_ajax followed by $props_prev, $props_home, or $props_next
$navi_nojs  = '<div class="mod_jemcalq_calendar-month caption-top" id="mod_jem_cal_' . $module->id . '_navi_nojs" style="display:' . (!$use_ajax || empty($module->in_ajax_call) ? 'grid' : 'none') . '">';
// $navi_nojs .= $props_prev_year ? ('<a href="' . htmlspecialchars($url_base_nojs . $props_prev_year) . '" title="' . $the_month . ' ' . $prev_year . '">&lsaquo;&lsaquo;</a>') : '&lsaquo;&lsaquo;';
$navi_nojs .= $props_prev      ? ('<a href="' . htmlspecialchars($url_base_nojs . $props_prev) . '" title="' . $the_month_prev . ' ' . $prev_month_year . '">&lsaquo;</a>') : '&lsaquo;';
$navi_nojs .= $props_home      ? ('<span class="evtq_home"><a href="' . htmlspecialchars($url_base_nojs . $props_home) . '" title="' . $the_month_today . ' ' . $today_year . '">' . $title . '</a></span>') : $title;
$navi_nojs .= $props_next      ? ('<a href="' . htmlspecialchars($url_base_nojs . $props_next) . '" title="' . $the_month_next . ' ' . $next_month_year . '">&rsaquo;</a>') : '&rsaquo;';
// $navi_nojs .= $props_next_year ? ('<a href="' . htmlspecialchars($url_base_nojs . $props_next_year) . '" title="' . $the_month . ' ' . $next_year . '">&rsaquo;&rsaquo;</a>') : '&rsaquo;&rsaquo;';
$navi_nojs .= '</div>';
$calendar  .= $navi_nojs;

if ($use_ajax) {
    $navi_ajax  = '<div class="mod_jemcalq_calendar-month caption-top" id="mod_jem_cal_' . $module->id . '_navi_ajax" style="display:' . (empty($module->in_ajax_call) ? 'none' : 'grid') . '">';
    // $navi_ajax .= $props_prev_year ? ('<a href="#" title="' . $the_month . ' ' . $prev_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_prev_year) . '\'); return false;">&lsaquo;&lsaquo;</a>') : '&lsaquo;&lsaquo;';
    $navi_ajax .= $props_prev      ? ('<a href="#" title="' . $the_month_prev . ' ' . $prev_month_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_prev) . '\'); return false;">&lsaquo;</a>') : '&lsaquo;';
    $navi_ajax .= $props_home      ? ('<span class="evtq_home"><a href="#" title="' . $the_month_today . ' ' . $today_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_home) . '\'); return false;">' . $title . '</a></span>') : $title;
    $navi_ajax .= $props_next      ? ('<a href="#" title="' . $the_month_next . ' ' . $next_month_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_next) . '\'); return false;">&rsaquo;</a>') : '&rsaquo;';
    // $navi_ajax .= $props_next_year ? ('<a href="#" title="' . $the_month . ' ' . $next_year . '" onClick="mod_jem_cal_click_' . $module->id . '(\'' . htmlspecialchars($url_base_ajax . $props_next_year) . '\'); return false;">&rsaquo;&rsaquo;</a>') : '&rsaquo;&rsaquo;';
    $navi_ajax .= '</div>';
    $calendar  .= $navi_ajax;
}

$calendar .= '<ul class="mod_jemcalq_grid daynames">';

# If the day names should be shown ($day_name_length > 0)
if ($day_name_length) {
    # If day_name_length is >3, the full name of the day will be printed
    if ($day_name_length > 3) {
        for ($d = 0; $d < 7; ++$d) {
            $dayname = Text::_($day_names_long[($d + $first_day) % 7]);
            $calendar .= '<li class="mod_jemcalq_daynames" abbr="' . $dayname . '">' . $dayname . '</li>';
        }
    } else {
        for ($d = 0; $d < 7; ++$d) {
            $dayname = Text::_($day_names_short[($d + $first_day) % 7]);
            if (function_exists('mb_substr')) {
                $calendar .= '<li class="mod_jemcalq_daynames" abbr="' . $dayname . '">' . mb_substr($dayname, 0, $day_name_length, 'UTF-8') . '</li>';
            } else {
                $calendar .= '<li class="mod_jemcalq_daynames" abbr="' . $dayname . '">' . substr($dayname, 0, $day_name_length) . '</li>';
            }
        }
    }
}

$calendar .= '</ul><ul class="mod_jemcalq_grid days">';

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

# Initial 'empty' days
for ($counti = 0; $counti < $weekday; $counti++) {
    $calendar .= '<li class="mod_jemcalq"></li>';
}

# The days of interest
for ($day = 1; $day <= $days_in_month; $day++, $weekday++) {
    if ($weekday == 7) {
        $weekday = 0; #start a new week
    }

    $istoday     = ($day == $today) && ($currmonth == $month) && ($curryear == $year);
    $tdbaseclass = ($istoday) ? 'mod_jemcalq_caltoday' : 'mod_jemcalq_calday';

    if (isset($days[$day][1])) {
        $link  = $days[$day][0];
        $title = $days[$day][1];

        if ($Show_Tooltips == 1) {
            $calendar .= '<li class="' . $tdbaseclass . ' link">';
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
                        $tip .= '<div class="tip">' . trim($t) . '</div>';
                    }
                }

                # J! version < 3.2.7: title already within $tip to ensure always '::' is present
                # But with J! 3.3+ is a bug in script so we need to use the bad 'hasTooltip'
                # which is default of class parameter.
                // $calendar .= HTMLHelper::tooltip($tip, $tipTitle, 'tooltip.png', '<span class="day">' . $day . '</span><span class="badge">•</span>', $link);

                $calendar .= '<div class="hasTip" data-bs-content="' . htmlspecialchars($tipTitle . $tip, ENT_QUOTES) . '">';
                $calendar .= '<div class="day">' . $day . '</div><div class="badge">•</div>';
                $calendar .= '</div>';

                // https://docs-next.joomla.org/docs/extensions/using-bootstrap-components-in-joomla-4/
                $calendar .= \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.popover', '.hasTip', ['trigger' => 'manual', 'placement' => 'top']);
            }

            $calendar .= '</li>';
        } else {
            $calendar .= '<li class="' . $tdbaseclass . ' link">' . ($link ? '<a href="' . $link . '">' . $day . '</a>' : $day) . '</li>';
        }
    } else {
        $calendar .= '<li class="' . $tdbaseclass . '"><span class="event nolink">' . $day . '</span></li>';
    }
}

# Remaining 'empty' days
for ($counti = $weekday; $counti < 7; $counti++) {
    $calendar .= '<li class="mod_jemcalq"></li>';
}

$calendar .= "</ul></div>";
echo $calendar;

if (!$use_ajax || empty($module->in_ajax_call)) {
    echo "</div>";
}
?>
<script>
(function () {
  const POPOVER_SELECTOR = '.hasTip';
  const CALENDAR_SELECTOR = '.mod_jemcalq_calendar';
  const OPTIONS = { trigger: 'manual', placement: 'top', html: true };

  const getPop = el => bootstrap.Popover.getInstance(el);
  const ensurePop = el => getPop(el) || new bootstrap.Popover(el, OPTIONS);
  const hideAll = except => document.querySelectorAll(POPOVER_SELECTOR)
    .forEach(el => { const p = getPop(el); if (p && el !== except) p.hide(); });

  // Delegate click handler
  document.addEventListener('click', e => {
    const tip = e.target.closest(POPOVER_SELECTOR);
    if (tip) {
      e.stopPropagation();
      hideAll(tip);
      ensurePop(tip).toggle();
    } else {
      hideAll();
    }
  });

  // Popovers für neue Elemente erstellen, für entfernte entsorgen
  const initPopovers = root => root.querySelectorAll(POPOVER_SELECTOR)
    .forEach(ensurePop);
  const disposePopovers = node => {
    if (!(node instanceof Element)) return;
    (node.matches(POPOVER_SELECTOR) ? [node] : [])
      .concat([...node.querySelectorAll(POPOVER_SELECTOR)])
      .forEach(el => { const p = getPop(el); if (p) p.dispose(); });
  };

  // Create popovers for new elements, dispose of them for removed elements
  const observeCalendar = container => {
    initPopovers(container);
    new MutationObserver(muts => {
      let added = false;
      muts.forEach(m => {
        m.removedNodes.forEach(disposePopovers);
        if (m.addedNodes.length) added = true;
      });
      if (added) initPopovers(container);
    }).observe(container, { childList: true, subtree: true });
  };

  document.addEventListener('DOMContentLoaded', () => {
    const cal = document.querySelector(CALENDAR_SELECTOR);
    if (cal) observeCalendar(cal);
    else {
      // If container comes later via AJAX
      new MutationObserver(() => {
        const c = document.querySelector(CALENDAR_SELECTOR);
        if (c) {
          observeCalendar(c);
        }
      }).observe(document.documentElement, { childList: true, subtree: true });
    }
  });
})();
</script>
