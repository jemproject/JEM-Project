<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
?>

<style>
    td.today div.daynum::before,
    td.today div.daynum::after {
        background-color: <?php echo $this->params->get('currentdaycolor'); ?>;
    }
</style>

<div id="jem" class="jlcalendar jem_calendar<?php echo $this->pageclass_sfx;?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link, 'ical_link' => $this->ical_link, 'archive_link' => $this->archive_link);
        if (!$this->params->get('show_archived_events', 0)) {
            $btn_params['show'] = array('archive');
        }
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
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
    $countcatevents = array ();
    $countvenueevents = array();
    $countperday = array();
    $limit = $this->params->get('daylimit', 10);
    $evbg_usecatcolor = $this->params->get('eventbg_usecatcolor', 0);
    $eventbackgroundcolor = $this->params->get('eventbackgroundcolor', '#FFF8E1');
    $recurrenceIconRender = $this->params->get('recurrenceIconRender', 0);
    $showtime = $this->settings->get('global_show_timedetails', 1);
    $categoryColorMarker = $this->params->get('categoryColorMarker', 0);
    $displayLegend = (int)$this->params->get('displayLegend', 1);

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
        $timestamp = strtotime($row->dates);
        $year    = date('Y', $timestamp);
        $month   = date('m', $timestamp);
        $day     = date('d', $timestamp);
        $dateKey = $year.$month.$day;
        $isLegendEvent = ((int) $year === (int) $this->calendarYear)
            && ((int) $month === (int) $this->calendarMonth)
            && !empty($row->user_has_access_event);

        if ($isLegendEvent && (!isset($row->multi) || ($row->multi == 'first'))) {
            foreach ((array) $row->categories as $category) {
                if (!array_key_exists($category->id, $countcatevents)) {
                    $countcatevents[$category->id] = 1;
                } else {
                    $countcatevents[$category->id]++;
                }
            }

            $venueId = (int) $row->locid;
            if ($venueId > 0) {
                if (!array_key_exists($venueId, $countvenueevents)) {
                    $countvenueevents[$venueId] = 1;
                } else {
                    $countvenueevents[$venueId]++;
                }
            }
        }

        $countperday[$dateKey] = ($countperday[$dateKey] ?? 0) + 1;

        if ($countperday[$dateKey] === $limit + 1) {
             $url  = Route::_('index.php?option=com_jem&view=day&id=' . $year.$month.$day . $this->param_topcat);
             $text = Text::_('COM_JEM_AND_MORE');
             $link = '<a href="' . $url . '">' . $text . '</a>';

             $this->cal->setEventContent($year, $month, $day, $link, null, 'eventandmore');
             continue;

        } elseif ($countperday[$dateKey] > $limit + 1) {
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
        $catcolor = array();
        $eventFilterClasses = array();
        $venueId = (int) $row->locid;

        foreach((array)$row->categories AS $category) {
            // Currently only one id possible...so simply just pick one up...
            $detaillink = Route::_(JemHelperRoute::getEventRoute($row->slug));

            // Collect category classes for the show/hide toggler.
            $eventFilterClasses['cat' . (int) $category->id] = 'cat' . (int) $category->id;

            // Attach category color in front of the catname
            if ($category->color) {
                $multicatname .= '<span class="colorpicblock" style="background-color: '.$category->color.';"></span>&nbsp;'.$category->catname;
            } else {
                $multicatname .= $category->catname;
            }

            $ix++;
            if ($ix != $nr) {
                $multicatname .= ', ';
            }

            // Collect all category colors (needed for the bar or blocks)
            if (isset($category->color) && $category->color) {
                $catcolor[$category->color] = $category->color;
            }

        }

        if ($venueId > 0) {
            $eventFilterClasses['venue' . $venueId] = 'venue' . $venueId;

        }

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
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
                break;
            case 2:  // middle day
                $multidaydate .= JemOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
                break;
            case 3:  // last day
                $multidaydate .= JemOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
                break;
            default: // single day
                $multidaydate .= JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $showtime);
                $multidaydate .= JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
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

        $venueColor = !empty($row->l_color) ? $row->l_color : (!empty($row->venuecolor) ? $row->venuecolor : '');
        $venueColorBar = '';
        if ($categoryColorMarker && $venueColor) {
            $venueColorBar  = '<div class="eventcontentbottom">';
            $venueColorBar .= '<div class="colorpicbarbottom" style="background:' . $this->escape($venueColor) . ';"></div>';
            $venueColorBar .= '</div>';
        }

        //generate the output
        // if we have exact one color from categories we can use this as background color of event
        $content .= '<div class="event-filter ' . implode(' ', $eventFilterClasses) . '" data-categories="' . $this->escape(implode(' ', array_filter($eventFilterClasses, static function ($class) { return strpos($class, 'cat') === 0; }))) . '" data-venue="' . ($venueId > 0 ? 'venue' . $venueId : '') . '">';
        $content .= '<div class="eventcontentinner event_id' . $eventid . ' cat_id' . $category->id . ' ' . $featuredclass . ($categoryColorMarker ? ' pt-0 pb-2' : '') . '" style="' . $featuredstyle;
        $style = '';
        $eventBackgroundColor = '';
        if (!empty($evbg_usecatcolor) && count($catcolor) === 1) {
            $eventBackgroundColor = reset($catcolor);
        } elseif ($eventbackgroundcolor) {
            $eventBackgroundColor = $eventbackgroundcolor;
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

        $content .= $editicon;
        $content .= JemHelper::caltooltip($catname.$eventname.$timehtml.$venue.$contact.$eventstate, $eventdate, $row->title . $statusicon, $detaillink, 'editlinktip hasTip', $timetp, $category->color);
        $content .= $eventaccess . $venueColorBar . '</div></div></div>';

        $this->cal->setEventContent($year, $month, $day, $content);
    endforeach;

    // enable little icon right beside day number to allow event creation
    if (!$this->print && $this->params->get('show_addevent_icon', 0) && !empty($this->permissions->canAddEvent)) {
        $html = JemOutput::prepareAddEventButton();
        $this->cal->enableNewEventLinks($html);
    }

    $this->calendarLegendDisplayLegend = $displayLegend;
    $this->calendarLegendCountCatEvents = $countcatevents;
    $this->calendarLegendCountVenueEvents = $countvenueevents;
    $this->calendarLegendCategoryColorMarker = $categoryColorMarker;

    if (in_array($displayLegend, array(2, 4, 6), true)) : ?>
        <!-- Calendar legend above -->
        <div id="jlcalendarlegend">

            <!-- Calendar buttons -->
            <div class="calendarButtons jem-row jem-justify-start">
                <button id="buttonshowall" class="calendarButton btn btn-outline-dark">
                    <?php echo Text::_('COM_JEM_SHOWALL'); ?>
                </button>
                <button id="buttonhideall" class="calendarButton btn btn-outline-dark">
                    <?php echo Text::_('COM_JEM_HIDEALL'); ?>
                </button>
            </div>

            <?php include __DIR__ . '/default_legend.php'; ?>
        </div>
    <?php endif; ?>

    <?php
    // print the calendar
    echo $this->cal->showMonth();
    ?>

    <?php if (in_array($displayLegend, array(0, 1, 3, 5), true)) : ?>
        <!-- Calendar legend below -->
        <div id="jlcalendarlegend">

            <!-- Calendar buttons -->
            <div class="calendarButtons jem-row jem-justify-start">
                <button id="buttonshowall" class="btn btn-outline-dark">
                    <?php echo Text::_('COM_JEM_SHOWALL'); ?>
                </button>
                <button id="buttonhideall" class="btn btn-outline-dark">
                    <?php echo Text::_('COM_JEM_HIDEALL'); ?>
                </button>
            </div>

            <?php include __DIR__ . '/default_legend.php'; ?>
        </div>
    <?php endif; ?>

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
