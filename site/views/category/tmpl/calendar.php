<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
?>

<div id="jem" class="jlcalendar jem_calendar<?php echo $this->pageclass_sfx;?>">
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

	foreach ($this->rows as $row) :
		if (!JemHelper::isValidDate($row->dates)) {
			continue; // skip, open date !
		}

		//get event date
		$year = strftime('%Y', strtotime($row->dates));
		$month = strftime('%m', strtotime($row->dates));
		$day = strftime('%d', strtotime($row->dates));

		@$countperday[$year.$month.$day]++;
		if ($countperday[$year.$month.$day] == $limit+1) {
			$var1a = JRoute::_( 'index.php?view=day&id='.$year.$month.$day.'&catid='.$this->catid);
			$var1b = JText::_('COM_JEM_AND_MORE');
			$var1c = "<a href=\"".$var1a."\">".$var1b."</a>";
			$id = 'eventandmore';

			/**
			 * $cal->setEventContent($year,$month,$day,$content,[$contentUrl,$id])
			 *
			 * Info from: http://www.micronetwork.de/activecalendar/demo/doc/doc_en.html
			 *
			 * Call this method, if you want the class to create a new HTML table within the date specified by the parameters $year, $month, $day.
			 * The parameter $content can be a string or an array.
			 * If $content is a string, then the new generated table will contain one row with the value of $content.
			 * If it is an array, the generated table will contain as many rows as the array length and each row will contain the value of each array item.
			 * The parameter $contentUrl is optional: If you set a $contentUrl, an event content specific link (..href='$contentUrl'..) will be generated
			 * in the 'event content' table row(s), even if the method $cal->enableDayLinks($link) was not called.
			 * The parameter $id is optional as well: if you set an $id, a HTML class='$id' will be generated for each event content (default: 'eventcontent').
			 */

			$this->cal->setEventContent($year, $month, $day, $var1c, null, $id);
			continue;
		} elseif ($countperday[$year.$month.$day] > $limit+1) {
			continue;
		}

		//for time in tooltip
		$timehtml = '';

		if ($this->jemsettings->showtime == 1) {
			$start = JemOutput::formattime($row->times);
			$end = JemOutput::formattime($row->endtimes);

			if ($start != '') {
				$timehtml = '<div class="time"><span class="text-label">'.JText::_('COM_JEM_TIME_SHORT').': </span>';
				$timehtml .= $start;
				if ($end != '') {
					$timehtml .= ' - '.$end;
				}
				$timehtml .= '</div>';
			}
		}

		$eventname  = '<div class="eventName">'.JText::_('COM_JEM_TITLE_SHORT').': '.$this->escape($row->title).'</div>';
		$detaillink = JRoute::_(JemHelperRoute::getEventRoute($row->slug));

		//initialize variables
		$multicatname = '';
		$colorpic = '';
		$nr = count($row->categories);
		$ix = 0;
		$content = '';
		$contentend = '';

		//walk through categories assigned to an event
		foreach($row->categories AS $category) {
			//Currently only one id possible...so simply just pick one up...
			$detaillink = JRoute::_(JemHelperRoute::getEventRoute($row->slug));

			//wrap a div for each category around the event for show hide toggler
			$content    .= '<div id="scat" class="cat'.$category->id.'">';
			$contentend .= '</div>';

			//attach category color if any in front of the catname
			if ($category->color) {
				$multicatname .= '<span class="colorpic" style="background-color: '.$category->color.';"></span>&nbsp;'.$category->catname;
			} else {
				$multicatname .= $category->catname;
			}

			$ix++;
			if ($ix != $nr) {
				$multicatname .= ', ';
			}

			//attach category color if any in front of the event title in the calendar overview
			if (isset($category->color) && $category->color) {
				$colorpic .= '<span class="colorpic" style="background-color: '.$category->color.';"></span>';
			}

			//count occurence of the category
			if (!array_key_exists($category->id, $countcatevents)) {
				$countcatevents[$category->id] = 1;
			} else {
				$countcatevents[$category->id]++;
			}
		}

		//for time in calendar
		$timetp = '';

		if ($this->jemsettings->showtime == 1) {
			$start = JemOutput::formattime($row->times,'',false);
			$end   = JemOutput::formattime($row->endtimes,'',false);

			$multi = new stdClass();
			$multi->row = (isset($row->multi) ? $row->multi : 'na');

			if ($multi->row) {
				if ($multi->row == 'first') {
					$timetp .= $image = JHtml::_("image","com_jem/arrow-left.png",'', NULL, true).' '.$start;
					$timetp .= '<br />';
				} elseif ($multi->row == 'middle') {
					$timetp .= JHtml::_("image","com_jem/arrow-middle.png",'', NULL, true);
					$timetp .= '<br />';
				} elseif ($multi->row == 'zlast') {
					$timetp .= JHtml::_("image","com_jem/arrow-right.png",'', NULL, true).' '.$end;
					$timetp .= '<br />';
				} elseif ($multi->row == 'na') {
					if ($start != '') {
						$timetp .= $start;
						if ($end != '') {
							$timetp .= ' - '.$end;
						}
						$timetp .= '<br />';
					}
				}
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
			$venue  = '<div class="location"><span class="text-label">'.JText::_('COM_JEM_VENUE_SHORT').': </span>';
			$venue .=     $row->locid ? $this->escape($row->venue) : '-';
			$venue .= '</div>';
		} else {
			$venue = '';
		}

		//date in tooltip
		$multidaydate = '<div class="time"><span class="text-label">'.JText::_('COM_JEM_DATE').': </span>';
		if ($multi->row == 'first') {
			$multidaydate .= JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
			$multidaydate .= JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
		} elseif ($multi->row == 'middle') {
			$multidaydate .= JemOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
			$multidaydate .= JemOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
		} elseif ($multi->row == 'zlast') {
			$multidaydate .= JemOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
			$multidaydate .= JemOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
		} else {
			$multidaydate .= JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
			$multidaydate .= JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
		}
		$multidaydate .= '</div>';

		//generate the output
		//$content .= $colorpic;
		$content .= JemHelper::caltooltip($catname.$eventname.$timehtml.$venue, $eventdate, $row->title, $detaillink, 'editlinktip hasTip', $timetp, $category->color);
		$content .= $contentend;

		$this->cal->setEventContent($year, $month, $day, $content);
	endforeach;

	// print the calendar
	echo $this->cal->showMonth();
	?>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>