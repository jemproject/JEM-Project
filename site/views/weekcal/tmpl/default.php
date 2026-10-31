<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/calendaragenda.php';
require_once JPATH_SITE . '/components/com_jem/helpers/calendarhourgrid.php';
require_once JPATH_SITE . '/components/com_jem/helpers/calendarfilter.php';

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
?>

<div id="jem" class="jlcalendar jem_calendar<?php echo $this->pageclass_sfx;?>">
    <div class="buttons">
        <?php
        $btn_params = array('print_link' => $this->print_link, 'ical_link' => $this->ical_link, 'archive_link' => $this->archive_link);
        if (!$this->params->get('show_archived_events', 0)) {
            $btn_params['show'] = array('archive');
        }
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        echo JemCalendarAgendaHelper::renderCycleToggle('week', $this->params);
        ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)): ?>
        <h1 class="componentheading">
            <?php echo $this->escape($this->params->get('page_heading')); ?>
        </h1>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
        <p> </p>
    <?php endif; ?>

    <?php
    if (JemCalendarAgendaHelper::getWeekMode($this->params) === 'timetable') :
        $firstDate = $this->calendarPeriod['start']->format('Y-m-d');
        $nrWeeks = $this->calendarPeriod['weeks'];
        echo JemCalendarHourGridHelper::renderNavigation(
            'weekcal',
            $firstDate,
            $nrWeeks * 7,
            (int) $this->params->get('firstweekday', 1) === 0
        );
        $lastDate = $this->calendarPeriod['end']->format('Y-m-d');
        $displayLegend = (int) $this->params->get('displayLegend', 1);
        $legend = JemCalendarFilterHelper::render((array) $this->rows, $displayLegend, $firstDate, $lastDate);
        if (in_array($displayLegend, array(2, 4, 6), true)) {
            echo $legend;
        }
        echo JemCalendarHourGridHelper::render((array) $this->rows, $firstDate, $nrWeeks * 7, (int) $this->params->get('top_category', 0));
        if (in_array($displayLegend, array(1, 3, 5), true)) {
            echo $legend;
        }
        if ($this->params->get('showfootertext')) :
            echo '<div class="description no_space floattext">' . $this->params->get('footertext') . '</div>';
        endif;
        echo '<div class="copyright">';
        JemOutput::footer();
        echo '</div>';
        echo '</div>';
        return;
    endif;

    if (JemCalendarAgendaHelper::getMode($this->params) === 'agenda') :
        $agendaStartDate = $this->calendarPeriod['start']->format('Y-m-d');
        $agendaNrWeeks = $this->calendarPeriod['weeks'];
        echo JemCalendarHourGridHelper::renderNavigation(
            'weekcal',
            $agendaStartDate,
            $agendaNrWeeks * 7,
            (int) $this->params->get('firstweekday', 1) === 0,
            'agenda'
        );
        $agendaEndDate = $this->calendarPeriod['end']->format('Y-m-d');
        $agendaSubtitle = $this->weekTitle;
        $displayLegend = (int) $this->params->get('displayLegend', 1);
        $legend = JemCalendarFilterHelper::render((array) $this->rows, $displayLegend, $agendaStartDate, $agendaEndDate);
        if (in_array($displayLegend, array(2, 4, 6), true)) {
            echo $legend;
        }
        echo JemCalendarAgendaHelper::renderAgenda((array) $this->rows, $agendaStartDate, $agendaEndDate, $agendaSubtitle, true);
        if (in_array($displayLegend, array(1, 3, 5), true)) {
            echo $legend;
        }
        if ($this->params->get('showfootertext')) :
            echo '<div class="description no_space floattext">' . $this->params->get('footertext') . '</div>';
        endif;
        echo '<div class="copyright">';
        JemOutput::footer();
        echo '</div>';
        echo '</div>';
        return;
    endif;
    ?>

    <?php
    $countcatevents = array ();
    $countperday = array();
    $limit = $this->params->get('daylimit', 10);
    $evbg_usecatcolor = $this->params->get('eventbg_usecatcolor', 0);
    $currentWeek = $this->currentweek;
    $firstDate = $this->calendarPeriod['start']->format('Y-m-d');
    $nrweeks = $this->calendarPeriod['weeks'];
    $weekLegendEndDate = $this->calendarPeriod['end']->format('Y-m-d');
    $specialDaysLegendHtml = JemHelper::renderCalendarSpecialDayLegend($firstDate, $weekLegendEndDate, $this->params);
    JemHelper::applyCalendarSpecialDayAttributes($this->cal, $firstDate, $weekLegendEndDate);
    $recurrenceIconRender = $this->params->get('recurrenceIconRender', 0);
    $showtime = $this->settings->get('global_show_timedetails', 1);
    $categoryColorMarker = $this->params->get('categoryColorMarker', 0);

    foreach ($this->rows as $row) :
        if (!JemHelper::isValidDate($row->dates)) {
            continue; // skip, open date !
        }

        // has user access
        $eventaccess = '';
        if (!$row->user_has_access_event) {
            // show a closed lock icon
            $eventaccess = ' <span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
        }

        //get event date
        $year = date('Y', strtotime($row->dates));
        $month = date('m', strtotime($row->dates));
        $day = date('d', strtotime($row->dates));

        @$countperday[$year.$month.$day]++;
        if ($countperday[$year.$month.$day] == $limit+1) {
            $var1a = Route::_('index.php?option=com_jem&view=day&id='.$year.$month.$day . $this->param_topcat);
            $var1b = Text::_('COM_JEM_AND_MORE');
            $var1c = "<a href=\"".$var1a."\">".$var1b."</a>";
            $id = 'eventandmore';

            $this->cal->setEventContent($year, $month, $day, $var1c, null, $id);
            continue;
        } elseif ($countperday[$year.$month.$day] > $limit+1) {
            continue;
        }

        //for time in tooltip
        $timehtml = '';

        if ($showtime) {
            $start = JemOutput::formattime($row->times);
            $end = JemOutput::formattime($row->endtimes);

            if ($start != '') {
                $timehtml = '<div class="time"><span class="text-label">'.Text::_('COM_JEM_TIME_SHORT').': </span>';
                $timehtml .= $start;
                if ($end != '') {
                    $timehtml .= ' - '.$end;
                }
                $timehtml .= '</div>';
            }
        }

        $eventname  = '<div class="eventName">'.Text::_('COM_JEM_TITLE_SHORT').': '.$this->escape($row->title).'</div>';
        $detaillink = Route::_(JemHelperRoute::getEventRoute($row->slug));
        $eventid = $this->escape($row->id);

        //Contact
        $contact = '';

        if (JemHelper::isContactComponentEnabled() && $row->contactid) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $ids = array_map('intval', explode(',', $row->contactid));

            $query = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__contact_details'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

            $db->setQuery($query);
            $contactNames = $db->loadColumn();

            if ($contactNames) {
                $contact  = '<div class="contact"><span class="text-label">' . Text::_('COM_JEM_CONTACTS') . ': </span>';
                $contact .= $this->escape(implode(', ', $contactNames));
                $contact .= '</div>';
            }
        }

        //initialize variables
        $multicatname = '';
        $color = '';
        $colorpic = '';
        $nr = is_array($row->categories) ? count($row->categories) : 0;
        $ix = 0;
        $content = '';
        $contentend = '';
        $catcolor = array();
        $categoryFilterClasses = array();

        //walk through categories assigned to an event
        $catcolor = array();

        foreach((array)$row->categories AS $category) {
            // Currently only one id possible...so simply just pick one up...
            $detaillink = Route::_(JemHelperRoute::getEventRoute($row->slug));

            $categoryFilterClasses[] = 'cat' . (int) $category->id;

            // Attach category color in front of the catname
            if ($category->color) {
                $multicatname .= '<span class="colorpicblock" style="background-color: '.$category->color.';"></span>&nbsp;'.$this->escape($category->catname);
            } else {
                $multicatname .= $this->escape($category->catname);
            }

            $ix++;
            if ($ix != $nr) {
                $multicatname .= ', ';
            }

            // Collect all category colors (needed for the bar or blocks)
            if (isset($category->color) && $category->color) {
                $catcolor[$category->color] = $category->color;
            }

            // Count category occurrence
            if (!isset($row->multi) || ($row->multi == 'first')) {
                if (!array_key_exists($category->id, $countcatevents)) {
                    $countcatevents[$category->id] = 1;
                } else {
                    $countcatevents[$category->id]++;
                }
            }
        }

        $categoryFilterClasses = array_values(array_unique($categoryFilterClasses));
        $categoryFilterClassAttribute = implode(' ', $categoryFilterClasses);
        $venueFilterClass = (int) ($row->locid ?? 0) > 0 ? 'venue' . (int) $row->locid : '';
        $content = '<div class="event-filter ' . $categoryFilterClassAttribute . ' ' . $venueFilterClass
            . '" data-categories="' . $this->escape($categoryFilterClassAttribute)
            . '" data-venue="' . $venueFilterClass . '">';
        $contentend = '</div>';

        // Build color output depending on $categoryColorMarker
        if (!empty($catcolor)) {
            if ($categoryColorMarker) {
                // Build a single multicolor TOP BAR
                $numColors = count($catcolor);
                $step = 100 / $numColors;
                $gradientParts = [];
                $i = 0;

                foreach ($catcolor as $color) {
                    $start = $i * $step;
                    $end = ($i + 1) * $step;
                    $gradientParts[] = "$color $start% $end%";
                    $i++;
                }

                $gradientCss = "linear-gradient(to right, " . implode(", ", $gradientParts) . ")";
                $color  = '<div id="eventcontenttop" class="eventcontenttop pt-0">';
                $color .= '<div class="colorpicbar" style="background: '.$gradientCss.';"></div>';
                $color .= '</div>';

            } else {
                // Build individual color BLOCKS
                $colorpic = '';
                foreach ($catcolor as $color) {
                    $colorpic .= '<span class="colorpicblock" style="background-color: '.$color.';"></span>';
                }
                $color = $colorpic;
            }
        }

        // multiday
        $multi_mode = 0; // single day
        $multi_icon = '';
        if (isset($row->multi)) {
            switch ($row->multi) {
                case 'first': // first day
                    $multi_mode = 1;
                    if($recurrenceIconRender){
                        $multi_icon = HTMLHelper::_("image","com_jem/arrow-left.webp",'', NULL, true);
                    }else{
                        $multi_icon = '<i class="fa fa-step-backward" aria-hidden="true"></i>';
                    }
                    break;
                case 'middle': // middle day
                    $multi_mode = 2;
                    if($recurrenceIconRender){
                        $multi_icon = HTMLHelper::_("image","com_jem/arrow-middle.webp",'', NULL, true);
                    }else{
                        $multi_icon = '<i class="fas fa-arrows-alt-h"></i>';
                    }
                    break;
                case 'zlast': // last day
                    $multi_mode = 3;
                    if($recurrenceIconRender){
                        $multi_icon = HTMLHelper::_("image","com_jem/arrow-right.webp",'', NULL, true);
                    }else{
                        $multi_icon = '<i class="fa fa-step-forward" aria-hidden="true"></i>';
                    }
                    break;
            }
        }

        //for time in calendar
        $timetp = '';

        if ($showtime) {
            $start = JemOutput::formattime($row->times,'',false);
            $end   = JemOutput::formattime($row->endtimes,'',false);

            switch ($multi_mode) {
                case 1:
                    $timetp .= $multi_icon . ' ' . $start . '<br>';
                    break;
                case 2:
                    $timetp .= $multi_icon . '<br>';
                    break;
                case 3:
                    $timetp .= $multi_icon . ' ' . $end . '<br>';
                    break;
                default:
                    if ($start != '') {
                        $timetp .= $start;
                        if ($end != '') {
                            $timetp .= ' - '.$end;
                        }
                        $timetp .= '<br>';
                    }
                    break;
            }
        } else {
            if (!empty($multi_icon)) {
                $timetp .= $multi_icon . ' ';
            }
        }

        $catname = '<div class="catname">'.$multicatname.'</div>';

        $eventdate = !empty($row->multistartdate) ? JemOutput::formatdate($row->multistartdate) : JemOutput::formatdate($row->dates);
        if (!empty($row->multienddate)) {
            $eventdate .= ' - ' . JemOutput::formatdate($row->multienddate);
        } else if ($row->enddates && $row->dates < $row->enddates) {
            $eventdate .= ' - ' . JemOutput::formatdate($row->enddates);
        }

        //venue
        if ($this->jemsettings->showlocate == 1) {
            $venue  = '<div class="location"><span class="text-label">'.Text::_('COM_JEM_VENUE_SHORT').': </span>';
            $venue .=     !empty($row->venue) ? $this->escape($row->venue) : '-';
            $venue .= '</div>';
        } else {
            $venue = '';
        }

        // state if unpublished
        $statusicon = '';
        if (isset($row->published) && ($row->published != 1)) {
            $statusicon  = JemOutput::publishstateicon($row);
            $eventstate  = '<div class="eventstate"><span class="text-label">'.Text::_('JSTATUS').': </span>';
            switch ($row->published) {
                case  1: $eventstate .= Text::_('JPUBLISHED');   break;
                case  0: $eventstate .= Text::_('JUNPUBLISHED'); break;
                case  2: $eventstate .= Text::_('JARCHIVED');    break;
                case -2: $eventstate .= Text::_('JTRASHED');     break;
            }
            $eventstate .= '</div>';
        } else {
            $eventstate  = '';
        }

        // has user access
        $eventaccess = "";
        if(!$row->user_has_access_event){
            // show a closed lock icon
            $statusicon  = JemOutput::publishstateicon($row);
            $eventaccess  = '<span class="icon-lock" style="margin-left:5px;" aria-hidden="true"></span>';
        }

        //date in tooltip
        $multidaydate = '<div class="time"><span class="text-label">'.Text::_('COM_JEM_DATE').': </span>';
        switch ($multi_mode) {
            case 1:  // first day
                $multidaydate .= JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row);
                break;
            case 2:  // middle day
                $multidaydate .= JemOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes, true, $row);
                break;
            case 3:  // last day
                $multidaydate .= JemOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes, true, $row);
                break;
            default: // single day
                $multidaydate .= JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row);
                break;
        }
        $multidaydate .= '</div>';

        //create little Edit and/or Copy icon on top right corner of event if user is allowed to edit and/or create
        $editicon = '';
        if (!$this->print) {
            $btns = array();
            if ($this->params->get('show_editevent_icon', 0) && $row->params->get('access-edit', false)) {
                $btns[] = JemOutput::editbutton($row, null, null, true, 'editevent');
            }
            if ($this->params->get('show_copyevent_icon', 0) && $this->permissions->canAddEvent) {
                $btns[] = JemOutput::copybutton($row, null, null, true, 'editevent');
            }
            if (!empty($btns)) {
                $editicon .= '<div class="inline-button-right">';
                $editicon .= join(' ', $btns);
                $editicon .= '</div>';
            }
        }

        //get border for featured event
        $usefeaturedborder = $this->params->get('usefeaturedborder', 0);
        $featuredbordercolor = $this->params->get('featuredbordercolor', 0);
        $featuredclass = '';
        $featuredstyle ='';
        if($usefeaturedborder && $row->featured){
            $featuredclass="borderfeatured";
            $featuredstyle="border-color:" . $featuredbordercolor;
        }

        //generate the output
        // if we have exact one color from categories we can use this as background color of event
        $content .= '<div class="eventcontentinner jem-calendar-event event_id' . $eventid . ' cat_id' . $category->id . ' ' . $featuredclass . '" style="' . $featuredstyle;
        $style = '';
        $eventBackgroundColor = '';
        if (!empty($evbg_usecatcolor) && count($catcolor) === 1) {
            $eventBackgroundColor = reset($catcolor);
        }
        if ($eventBackgroundColor) {
            $style = '; background-color:' . $eventBackgroundColor;
            $contrastColor = JemHelper::getContrastTextColor($eventBackgroundColor);
            if ($contrastColor) {
                $style .= '; color:' . $contrastColor;
            }
        }
        $content .= $style . '" onclick="location.href=\'' . $detaillink . '\'">';
        $divClass = $categoryColorMarker ? 'eventcontenttextbar' : 'eventcontenttextblock';
        $content .= '<div class="' . $divClass . '">';
        if (empty($evbg_usecatcolor) || count($catcolor) !== 1) {
            $content .= $color;
        }

        $content .= '<div class="eventcontenttextbody">' . $editicon;
        $content .= JemHelper::caltooltip($catname.$eventname.$timehtml.$venue.$contact.$eventstate, $eventdate, $row->title . $statusicon, $detaillink, 'editlinktip hasTip', $timetp, $category->color);
        $content .= $eventaccess . '</div></div></div>' . $contentend;

        $this->cal->setEventContent($year, $month, $day, $content);
    endforeach;

    // enable little icon right beside day number to allow event creation
    if (!$this->print && $this->params->get('show_addevent_icon', 0) && !empty($this->permissions->canAddEvent)) {
        $html = JemOutput::prepareAddEventButton();
        $this->cal->enableNewEventLinks($html);
    }

    $displayLegend = (int) $this->params->get('displayLegend', 1);
    $weekLegend = JemCalendarFilterHelper::render((array) $this->rows, $displayLegend, $firstDate, $weekLegendEndDate);

    if (in_array($displayLegend, array(2, 4, 6), true)) {
        echo $weekLegend . $specialDaysLegendHtml;
    }

    echo $this->cal->showWeeksByID($currentWeek, $nrweeks);

    if (in_array($displayLegend, array(0, 1, 3, 5), true)) {
        echo $weekLegend . $specialDaysLegendHtml;
    }
    ?>
    <div class="clr"></div>
    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
