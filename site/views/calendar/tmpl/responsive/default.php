<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
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
        $btn_params = array('print_link' => $this->print_link, 'ical_link' => $this->ical_link);
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
	$countperday = array();
	$limit = $this->params->get('daylimit', 10);
	$evbg_usecatcolor = $this->params->get('eventbg_usecatcolor', 0);
	$showtime = $this->settings->get('global_show_timedetails', 1);

	foreach ($this->rows as $row) :
		if (!JemHelper::isValidDate($row->dates)) {
			continue; // skip, open date !
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

		//initialize variables
		$multicatname = '';
		$colorpic = '';
		$nr = is_array($row->categories) ? count($row->categories) : 0;
		$ix = 0;
		$content = '';
		$contentend = '';
		$catcolor = array();

		//walk through categories assigned to an event
		foreach((array)$row->categories AS $category) {
			//Currently only one id possible...so simply just pick one up...
			$detaillink = Route::_(JemHelperRoute::getEventRoute($row->slug));

			//wrap a div for each category around the event for show hide toggler
			$content    .= '<div id="catz" class="cat'.$category->id.'">';
			$contentend .= '</div>';

			//attach category color if any in front of the catname
			if ($category->color) {
				$multicatname .= '<span class="colorpic" style="width:6px; background-color: '.$category->color.';"></span>&nbsp;'.$category->catname;
			} else {
				$multicatname .= $category->catname;
			}

			$ix++;
			if ($ix != $nr) {
				$multicatname .= ', ';
			}

			//attach category color if any in front of the event title in the calendar overview
			if (isset($category->color) && $category->color) {
				$colorpic .= '<span class="colorpic" style="width:6px; background-color: '.$category->color.';"></span>';
				$catcolor[$category->color] = $category->color; // we need to list all different colors of this event
			}

			//count occurence of the category
			if (!isset($row->multi) || ($row->multi == 'first')) {
				if (!array_key_exists($category->id, $countcatevents)) {
					$countcatevents[$category->id] = 1;
				} else {
					$countcatevents[$category->id]++;
				}
			}
		}

		$color  = '<div id="eventcontenttop" class="eventcontenttop">';
		$color .= $colorpic;
		$color .= '</div>';

		// multiday
		$multi_mode = 0; // single day
		$multi_icon = '';
		if (isset($row->multi)) {
			switch ($row->multi) {
			case 'first': // first day
				$multi_mode = 1;
				$multi_icon = '<i class="fa fa-step-backward" aria-hidden="true"></i>';
				break;
			case 'middle': // middle day
				$multi_mode = 2;
				$multi_icon = '<i class="fas fa-arrows-alt-h"></i>';
				break;
			case 'zlast': // last day
				$multi_mode = 3;
				$multi_icon = '<i class="fa fa-step-forward" aria-hidden="true"></i>';
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
				$timetp .= $multi_icon . ' ' . $start . '<br />';
				break;
			case 2:
				$timetp .= $multi_icon . '<br />';
				break;
			case 3:
				$timetp .= $multi_icon . ' ' . $end . '<br />';
				break;
			default:
				if ($start != '') {
					$timetp .= $start;
					if ($end != '') {
						$timetp .= ' - '.$end;
					}
					$timetp .= '<br />';
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

        //generate the output
        // if we have exact one color from categories we can use this as background color of event
        $content .= '<div class="eventcontentinner ' . $featuredclass . '" style="' . $featuredstyle;
        if (!empty($evbg_usecatcolor) && (count($catcolor) == 1)) {
            $content .= '; background-color:'.array_pop($catcolor);
            $content .= '" onclick=location.href="'.$detaillink.'">';
        } else {
            $content .= '" onclick=location.href="'.$detaillink.'">' . $colorpic;
        }
        $content .= $editicon;
        $content .= JemHelper::caltooltip($catname.$eventname.$timehtml.$venue.$eventstate, $eventdate, $row->title . $statusicon, $detaillink, 'editlinktip hasTip', $timetp, $category->color);
        $content .= $contentend . '</div>';

		$this->cal->setEventContent($year, $month, $day, $content);
	endforeach;

	// enable little icon right beside day number to allow event creation
	if (!$this->print && $this->params->get('show_addevent_icon', 0) && !empty($this->permissions->canAddEvent)) {
		$html = JemOutput::prepareAddEventButton();
		$this->cal->enableNewEventLinks($html);
	}

	$displayLegend = (int)$this->params->get('displayLegend', 1);
	if ($displayLegend == 2) : ?>
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

	<!-- Calendar Legend -->
		<div class="calendarLegends jem-row jem-justify-start">
			<?php
			if ($this->params->get('displayLegend')) {

				##############
				## FOR EACH ##
				##############

				$counter = array();

				# walk through events
				foreach ($this->rows as $row) {
					foreach ($row->categories as $cat) {

						# sort out dupes for the counter (catid-legend)
						if (!in_array($cat->id, $counter)) {
							# add cat id to cat counter
							$counter[] = $cat->id;

							# build legend
							if (array_key_exists($cat->id, $countcatevents)) {
							?>
								<button class="eventCat btn btn-outline-dark" id="cat<?php echo $cat->id; ?>">
									<?php
									if (isset($cat->color) && $cat->color) {
										echo '<span class="colorpic" style="background-color: '.$cat->color.';"></span>';
									}
									echo $cat->catname.' ('.$countcatevents[$cat->id].')';
									?>
								</button>
							<?php
							}
						}
					}
				}
			}
			?>
		</div>
	</div>
	<?php endif; ?>

	<?php
	// print the calendar
	echo $this->cal->showMonth();
	?>

	<?php if (($displayLegend == 1) || ($displayLegend == 0)) : ?>
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
    
	<!-- Calendar Legend -->
		<div class="calendarLegends jem-row jem-justify-start">
			<?php
			if ($displayLegend == 1) {

				##############
				## FOR EACH ##
				##############

				$counter = array();

				# walk through events
				foreach ($this->rows as $row) {
					foreach ($row->categories as $cat) {

						# sort out dupes for the counter (catid-legend)
						if (!in_array($cat->id, $counter)) {
							# add cat id to cat counter
							$counter[] = $cat->id;

							# build legend
							if (array_key_exists($cat->id, $countcatevents)) {
							?>
								<button class="eventCat btn btn-outline-dark" id="cat<?php echo $cat->id; ?>">
									<?php
									if (isset($cat->color) && $cat->color) {
										echo '<span class="colorpic" style="background-color: '.$cat->color.';"></span>';
									}
									echo $cat->catname.' ('.$countcatevents[$cat->id].')';
									?>
								</button>
							<?php
							}
						}
					}
				}
			}
			?>
		</div>
	</div>
	<?php endif; ?>

	<div class="clr"></div>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>