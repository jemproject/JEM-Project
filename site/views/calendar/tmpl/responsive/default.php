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

// Initialize the database driver for use within the loop.
$db = Factory::getContainer()->get('DatabaseDriver');

// Configuration constants
$limit = $this->params->get('daylimit', 10);
$evbg_usecatcolor = $this->params->get('eventbg_usecatcolor', 0);
$eventbackgroundcolor = $this->params->get('eventbackgroundcolor', 0);
$recurrenceIconRender = $this->params->get('recurrenceIconRender', 0);
$showtime = $this->settings->get('global_show_timedetails', 1);
$categoryColorMarker = $this->params->get('categoryColorMarker', 0);
$displayLegend = (int)$this->params->get('displayLegend', 1);

// Global counters
$countperday = [];
$countcatevents = [];
$countvenueevents = [];
$counter_cats = [];
$counter_venues = [];
$num_catzid = 1;
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
        $btn_params = ['print_link' => $this->print_link, 'ical_link' => $this->ical_link];
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

    <?php foreach ($this->rows as $row) :
        // Skip if open date
        if (!JemHelper::isValidDate($row->dates)) {
            continue;
        }

        $year = date('Y', strtotime($row->dates));
        $month = date('m', strtotime($row->dates));
        $day = date('d', strtotime($row->dates));
        $keyday = $year . $month . $day;

        // Event Limit Logic per day
        $countperday[$keyday] = ($countperday[$keyday] ?? 0) + 1;
        if ($countperday[$keyday] == $limit + 1) {
            $moreLink  = Route::_('index.php?option=com_jem&view=day&id=' . $keyday . $this->param_topcat);
            $moreHtml  = '<div id="catzplus'.$num_catzid.'" class="cat0">';
            $moreHtml .= "<a href=\"".$moreLink."\">".Text::_('COM_JEM_CALENDAR_ANDMORE')."</a>";
            $moreHtml .= '</div>';
            $this->cal->setEventContent($year, $month, $day, $moreHtml, null, 'eventcontent eventandmore');
            $num_catzid++;
            continue;
        } elseif ($countperday[$keyday] > $limit + 1) {
            continue;
        }

        // --- EVENT DATA PREPARATION ---

        // Access lock icon
        $eventaccess = '';
        if (!$row->user_has_access_event) {
            $eventaccess = ' <span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
        }

        // Time for tooltip
        $timehtml = '';
        if ($showtime) {
            $start = JemOutput::formattime($row->times);
            $end = JemOutput::formattime($row->endtimes);

            if ($start != '') {
                $timehtml = '<div class="time"><span class="text-label">'.Text::_('COM_JEM_TIME_SHORT').': </span>' . $start;
                if ($end != '') {
                    $timehtml .= ' - '.$end;
                }
                $timehtml .= '</div>';
            }
        }

        $detaillink = Route::_(JemHelperRoute::getEventRoute($row->slug));
        $eventname  = '<div class="eventName">'.Text::_('COM_JEM_TITLE_SHORT').': '.$this->escape($row->title).'</div>';
        $eventid = $this->escape($row->id);

        // Contact details
        $contact = '';
        if ($row->contactid) {
            $query = $db->getQuery(true)
                ->select('name')
                ->from('#__contact_details')
                ->where('id = ' . (int)$row->contactid);
            $db->setQuery($query);
            $contactname = $db->loadResult();

            if ($contactname) {
                $contact  = '<div class="contact"><span class="text-label">'.Text::_('COM_JEM_CONTACT').': </span>';
                $contact .= $this->escape($contactname) . '</div>';
            }
        }

        //initialize variables
        $multicatname = '';
        $colorpic = '';
        $color = '';
        $nr = is_array($row->categories) ? count($row->categories) : 0;
        $ix = 0;
        $catcolor = array();

        $content = '<div id="catz'.$row->id.'" class="';
        $classcontent ='';

        foreach((array)$row->categories AS $category) {
            // Currently only one id possible...so simply just pick one up...
            $detaillink = Route::_(JemHelperRoute::getEventRoute($row->slug));

            // Wrap a div for each category around the event for show/hide toggler
            $classcontent .= ($classcontent? ' ':'') . 'cat'.$category->id;

            // Attach category color in front of the catname
            if ($category->color) {
                $multicatname .= '<span class="colorpicblock" style="background-color: '.$category->color.';"></span>&nbsp;'.$category->catname;
				// Collect all category colors (needed for the bar or blocks)
                $catcolor[$category->color] = $category->color;
            } else {
                $multicatname .= $category->catname;
            }

            $ix++;
            if ($ix != $nr) {
                $multicatname .= ', ';
            }

            // Count events per category (for the legend)
            if (!isset($row->multi) || ($row->multi == 'first')) {
                if (!array_key_exists($category->id, $countcatevents)) {
                    $countcatevents[$category->id] = 1;
                } else {
                    $countcatevents[$category->id]++;
                }
            }
        }
        $content .= $classcontent . '">';

        // Count events per venue (for the legend)
        if (!array_key_exists($row->locid, $countvenueevents)) {
            $countvenueevents[$row->locid] = 1;
        } else {
            $countvenueevents[$row->locid]++;
        }

        // Build color output depending on $categoryColorMarker
        if (!empty($catcolor)) {
            if ($categoryColorMarker) {
                // Build a single multicolor TOP BAR
                $numColors = count($catcolor);
                $step = 100 / $numColors;
                $gradientParts = [];
                $i = 0;

                foreach ($catcolor as $c) {
                    $start = $i * $step;
                    $end = ($i + 1) * $step;
                    $gradientParts[] = "$c $start% $end%";
                    $i++;
                }

                $gradientCss = "linear-gradient(to right, " . implode(", ", $gradientParts) . ")";
                $color  = '<div id="eventcontenttop" class="eventcontenttop ">';
                $color .= '<div class="colorpicbar" style="background: '.$gradientCss.';"></div>';
                $color .= '</div>';
            } else {
                // Color BLOCKS logic
                $colorpic = '';
                foreach ($catcolor as $c) {
                    $colorpic .= '<span class="colorpicblock" style="background-color: '.$c.';"></span>';
                }
                $color = $colorpic;
            }
        }

        // Multi-day Icons
        $multi_mode = 0;
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

        // Time for Calendar Cell (Timetp)
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

        $eventdate = !empty($row->multistartdate ?? 0) ? JemOutput::formatdate($row->multistartdate ?? 0) : JemOutput::formatdate($row->dates);
        $eventdate .= !empty($row->multienddate) ? ' - ' . JemOutput::formatdate($row->multienddate ?? '') : '';
        $eventdate .= (!($row->multienddate ?? 0 )&& $row->enddates && $row->dates < $row->enddates) ? ' - ' . JemOutput::formatdate($row->enddates) : '';

        // Venue
        $venue = '';
        if ($this->jemsettings->showlocate == 1) {
            $venue  = '<div class="location"><span class="text-label">'.Text::_('COM_JEM_VENUE_SHORT').': </span>';
            $venue .= !empty($row->venue) ? $this->escape($row->venue) : '-';
            $venue .= '</div>';
        }

        // Publishing Status and Access Icons
        $statusicon = '';
        $eventstate = '';
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
        }

        // has user access
        $eventaccess = "";
        if(!$row->user_has_access_event){
            // show a closed lock icon
            $statusicon  = JemOutput::publishstateicon($row);
            $eventaccess  = '<span class="icon-lock" style="margin-left:5px;" aria-hidden="true"></span>';
        }

        // Multiday date (Tooltip)
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
            $btns = [];
            if ($this->params->get('show_editevent_icon', 0) && $row->params->get('access-edit', false)) {
                $btns[] = JemOutput::editbutton($row, null, null, true, 'editevent');
            }
            if ($this->params->get('show_copyevent_icon', 0) && $this->permissions->canAddEvent) {
                $btns[] = JemOutput::copybutton($row, null, null, true, 'editevent');
            }
            if (!empty($btns)) {
                $editicon .= '<div class="inline-button-right">' . join(' ', $btns) . '</div>';
            }
        }

        // Featured Event Border
        $featuredclass = '';
        $featuredstyle ='';
        if($this->params->get('usefeaturedborder', 0) && $row->featured){
            $featuredclass="borderfeatured";
            $featuredstyle="border-color:" . $this->params->get('featuredbordercolor', 0);
        }


        // Build color bottom bar output venue color
        if (!empty($row->l_color)) {
            $colorbottom  = '<div <div id="eventcontentbottom" class="eventcontentbottom pt-0">';

            $colorbottom .= '<div class="colorpicbarbottom" style="background: '.$row->l_color.';"></div>';
            $colorbottom .= '</div>';
        }

        //generate the output
        // if we have exact one color from categories we can use this as background color of event
        $content .= '<div class="eventcontentinner event_id' . $eventid . ' cat_id' . $category->id . ' ' . $featuredclass . ($categoryColorMarker ? ' pt-0 pb-2' : '') . '" style="' . $featuredstyle;
        $content .= '" onclick="location.href=\'' . $detaillink . '\'">';
        $divClass = $categoryColorMarker ? 'eventcontenttextbar' : 'eventcontenttextblock';

        $style = '';
        if (!empty($evbg_usecatcolor) && count($catcolor) === 1) {
            $style = 'background-color:' . array_pop($catcolor);
        }else{
            $style = 'background-color:' . $eventbackgroundcolor;
        }
        $content .= '<div class="' . $divClass . '" style="' . $style . '">';

        if (empty($evbg_usecatcolor) || count($catcolor) !== 1) {
            $content .= $color;
        }

        $content .= $editicon;
        $content .= JemHelper::caltooltip($catname.$eventname.$timehtml.$venue.$contact.$eventstate, $eventdate, $row->title . $statusicon, $detaillink, 'editlinktip hasTip', $timetp, $category->color);
        $content .= $eventaccess . '';

        if (!empty($row->l_color)) {
            $content .= '<div class="eventcontentbottom">';
            if (empty($evbg_usecatcolor) || count($catcolor) !== 1) {
                $content .= $colorbottom;
            }
        }
        $content .= '</div></div>';

        $this->cal->setEventContent($year, $month, $day, $content);

    endforeach;

    // Enable little icon right beside day number to allow event creation
    if (!$this->print && $this->params->get('show_addevent_icon', 0) && !empty($this->permissions->canAddEvent)) {
        $html = JemOutput::prepareAddEventButton();
        $this->cal->enableNewEventLinks($html);
    }

    if ($displayLegend == 2 || $displayLegend == 4 || $displayLegend == 6) { ?>
        <!-- Calendar legend below -->
        <div id="jlcalendarlegend" class="mt-5">

            <!-- Calendar buttons -->
            <div class="calendarButtons jem-row jem-justify-start row mb-4">
                <div class='col-md-2 '><?php echo Text::_('COM_JEM_EVENTS')?></div>
                <div class='col-md-10'>
                    <button id="buttonshowall" class="btn btn-outline-dark">
                        <?php echo Text::_('COM_JEM_SHOWALL'); ?>
                    </button>
                    <button id="buttonhideall" class="btn btn-outline-dark">
                        <?php echo Text::_('COM_JEM_HIDEALL'); ?>
                    </button>
                </div>
            </div>

            <?php if ($displayLegend == 2 || $displayLegend == 4) { ?>
                <!-- Calendar Legend : Categories -->
                <div class="calendarLegends jem-row jem-justify-start row mb-4">
                    <?php
                    # walk through events for categories
                    $counter_cats = array();
                    ?>
                    <div class='col-md-2 '><?php echo Text::_('COM_JEM_CATEGORIES')?></div>
                    <div class='col-md-10'>
                        <?php
                        foreach ($this->rows as $row) {
                            foreach ($row->categories as $cat) {

                                # sort out dupes for the counter (catid-legend)
                                if (!in_array($cat->id, $counter_cats)) {
                                    # add cat id to cat counter
                                    $counter_cats[] = $cat->id;

                                    # build legend
                                    if (array_key_exists($cat->id, $countcatevents)) {
                                        ?>
                                        <button class="eventCat btn btn-outline-dark me-2" id="cat<?php echo $cat->id; ?>">
                                            <?php
                                            if (!empty($cat->color)) {
                                                $class = $categoryColorMarker ? 'colorpicbar' : 'colorpicblock ms-2';
                                                echo '<span class="' . $class . '" style="background-color:' . $cat->color . ';"></span>';
                                            }

                                            $text = $cat->catname . ' (' . $countcatevents[$cat->id] . ')';
                                            $textClass = $categoryColorMarker ? 'colorpicbartext' : 'colorpicblocktext pe-2';
                                            echo '<span class="' . $textClass . '">' . $text . '</span>';
                                            ?>
                                        </button>
                                        <?php
                                    }
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($displayLegend == 4 || $displayLegend == 6) { ?>
                <!-- Calendar Legend : Venues -->
                <div class="calendarLegends jem-row jem-justify-start row mb-4">
                    <?php
                    # walk through events for venues
                    $counter_venues = array();
                    ?>
                    <div class='col-md-2 '><?php echo Text::_('COM_JEM_VENUES')?></div>
                    <div class='col-md-10'>
                        <?php
                        foreach ($this->rows as $row) {
                            if (!in_array($row->locid, $counter_venues)) {
                                # add venue id to cat counter
                                $counter_venues[] = $row->locid;

                                # build legend
                                if (array_key_exists($row->locid, $countvenueevents)) {
                                    ?>
                                    <button class="eventVenues btn btn-outline-dark me-2" id="cat<?php echo $row->locid; ?>">
                                        <?php
                                        if (!empty($row->l_color)) {
                                            $class = $categoryColorMarker ? 'colorpicbarbottom-leyend' : 'colorpicblock ms-2';
                                            echo '<span class="' . $class . '" style="background-color:' . $row->l_color . ';"></span>';
                                        }

                                        $text = $row->venue . ' (' . $countvenueevents[$row->locid] . ')';
                                        $textClass = $categoryColorMarker ? 'colorpicbartext' : 'colorpicblocktext pe-2';
                                        echo '<span class="' . $textClass . '">' . $text . '</span>';
                                        ?>
                                    </button>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    <?php } ?>

    <?php
    // print the calendar
    echo $this->cal->showMonth();
    ?>

    <?php
    if ($displayLegend == 0 || $displayLegend == 1 || $displayLegend == 3 || $displayLegend == 5) { ?>
        <!-- Calendar legend below -->
        <div id="jlcalendarlegend" class="mt-5">

            <!-- Calendar buttons -->
            <div class="calendarButtons jem-row jem-justify-start row mb-4">
                <div class='col-md-2 '><?php echo Text::_('COM_JEM_EVENTS')?></div>
                <div class='col-md-10'>
                    <button id="buttonshowall" class="btn btn-outline-dark">
                        <?php echo Text::_('COM_JEM_SHOWALL'); ?>
                    </button>
                    <button id="buttonhideall" class="btn btn-outline-dark">
                        <?php echo Text::_('COM_JEM_HIDEALL'); ?>
                    </button>
                </div>
            </div>

            <?php if ($displayLegend == 1 || $displayLegend == 3) { ?>
                <!-- Calendar Legend : Categories -->
                <div class="calendarLegends jem-row jem-justify-start row mb-4">
                    <?php
                    # walk through events for categories
					$counter_cats = array();
                    ?>
                    <div class='col-md-2 '><?php echo Text::_('COM_JEM_CATEGORIES')?></div>
                    <div class='col-md-10'>
                        <?php
                        foreach ($this->rows as $row) {
                            foreach ($row->categories as $cat) {

                                # sort out dupes for the counter (catid-legend)
                                if (!in_array($cat->id, $counter_cats)) {
                                    # add cat id to cat counter
                                    $counter_cats[] = $cat->id;

                                    # build legend
                                    if (array_key_exists($cat->id, $countcatevents)) {
                                        ?>
                                        <button class="eventCat btn btn-outline-dark me-2" id="cat<?php echo $cat->id; ?>">
                                            <?php
                                            if (!empty($cat->color)) {
                                                $class = $categoryColorMarker ? 'colorpicbar' : 'colorpicblock ms-2';
                                                echo '<span class="' . $class . '" style="background-color:' . $cat->color . ';"></span>';
                                            }

                                            $text = $cat->catname . ' (' . $countcatevents[$cat->id] . ')';
                                            $textClass = $categoryColorMarker ? 'colorpicbartext' : 'colorpicblocktext pe-2';
                                            echo '<span class="' . $textClass . '">' . $text . '</span>';
                                            ?>
                                        </button>
                                        <?php
                                    }
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($displayLegend == 3 || $displayLegend == 5) { ?>
                <!-- Calendar Legend : Venues -->
                <div class="calendarLegends jem-row jem-justify-start row mb-4">
                    <?php
                    # walk through events for venues
					$counter_venues = array();
                    ?>
                    <div class='col-md-2 '><?php echo Text::_('COM_JEM_VENUES')?></div>
                    <div class='col-md-10'>
                        <?php
                        foreach ($this->rows as $row) {
                            if (!in_array($row->locid, $counter_venues)) {
                                # add venue id to cat counter
                                $counter_venues[] = $row->locid;

                                # build legend
                                if (array_key_exists($row->locid, $countvenueevents)) {
                                    ?>
                                    <button class="eventVenues btn btn-outline-dark me-2" id="cat<?php echo $row->locid; ?>">
                                        <?php
                                        if (!empty($row->l_color)) {
                                            $class = $categoryColorMarker ? 'colorpicbarbottom-leyend' : 'colorpicblock ms-2';
                                            echo '<span class="' . $class . '" style="background-color:' . $row->l_color . ';"></span>';
                                        }

                                        $text = $row->venue . ' (' . $countvenueevents[$row->locid] . ')';
                                        $textClass = $categoryColorMarker ? 'colorpicbartext' : 'colorpicblocktext pe-2';
                                        echo '<span class="' . $textClass . '">' . $text . '</span>';
                                        ?>
                                    </button>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    <?php } ?>

    <div class="clr"></div>

    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>