<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<div id="jem" class="jlcalendar">
	<?php if ($this->params->def('show_page_title', 1)): ?>
		<h1 class="componentheading">
			<?php echo $this->escape($this->params->get('page_title')); ?>
		</h1>
	<?php endif; ?>

	<?php if ($this->params->get('showintrotext')) : ?>
	<div class="description no_space floattext">
		<?php echo $this->params->get('introtext'); ?>
	</div>
	<p><p>
<?php endif; ?>

	<?php
	$countcatevents = array ();

	$countperday = array();
	$limit = $this->params->get('daylimit', 10);
	foreach ($this->rows as $row) :
		if (!JEMHelper::isValidDate($row->dates)) {
			continue; // skip, open date !
		}

		//get event date
		$year = strftime('%Y', strtotime($row->dates));
		$month = strftime('%m', strtotime($row->dates));
		$day = strftime('%d', strtotime($row->dates));


		@$countperday[$year.$month.$day]++;
		if ($countperday[$year.$month.$day] == $limit+1) {
			// $this->cal->setEventContent($year, $month, $day, JText::_('COM_JEM_AND_MORE'));

			$var1a = JRoute::_( 'index.php?view=day&id='.$year.$month.$day );
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
			 *
			 *
			 * */

			$this->cal->setEventContent($year, $month, $day, $var1c,null, $id);
			continue;
		} else if ($countperday[$year.$month.$day] > $limit+1) {
			continue;
		}

		//for time printing
		$timehtml = '';

		if ($this->jemsettings->showtime == 1) :

			$start = JEMOutput::formattime($row->times);
			$end = JEMOutput::formattime($row->endtimes);

			if ($start != '') :
				$timehtml = '<div class="time"><span class="label">'.JText::_('COM_JEM_TIME').': </span>';
				$timehtml .= $start;
				if ($end != '') :
					$timehtml .= ' - '.$end;
				endif;
				$timehtml .= '</div>';
			endif;
			$multi = new stdClass();
			$multi->row = (isset($row->multi) ? $row->multi : 'na');
		endif;

		$eventname = '<div class="eventName">'.JText::_('COM_JEM_TITLE').': '.$this->escape($row->title).'</div>';
		$detaillink 	= JRoute::_( JEMHelperRoute::getEventRoute($row->slug));
		//initialize variables
		$multicatname = '';
		$colorpic = '';
		$nr = count($row->categories);
		$ix = 0;
		$content = '';
		$contentend = '';

		//walk through categories assigned to an event
		foreach($row->categories AS $category) :

			//Currently only one id possible...so simply just pick one up...
			$detaillink 	= JRoute::_( JEMHelperRoute::getEventRoute($row->slug));

			//wrap a div for each category around the event for show hide toggler
			$content 		.= '<div id="catz" class="cat'.$category->id.'">';
			$contentend		.= '</div>';

			//attach category color if any in front of the catname
			if ($category->color):
				$multicatname .= '<span class="colorpic" style="width:6px; background: '.$category->color.';"></span>'.$category->catname;
			else:
				$multicatname 	.= $category->catname;
			endif;
			$ix++;
			if ($ix != $nr) :
				$multicatname .= ', ';
			endif;

			//attach category color if any in front of the event title in the calendar overview
			if ( isset ($category->color) && $category->color) :
				$colorpic .= '<span class="colorpic" style="width:6px; background: '.$category->color.';"></span>';
			endif;
			//count occurence of the category
			if (!array_key_exists($category->id, $countcatevents)) :
				$countcatevents[$category->id] = 1;
			else :
				$countcatevents[$category->id]++;
			endif;

		endforeach;

		$color = '<div id="eventcontenttop" class="eventcontenttop">';
		$color .= $colorpic;
		$color .= '</div>';
		//for time in calendar
		$timetp = '';

		if ($this->jemsettings->showtime == 1) {
			$start = JEMOutput::formattime($row->times,'',false);
			$end = JEMOutput::formattime($row->endtimes,'',false);

			$multi = new stdClass();
			$multi->row = (isset($row->multi) ? $row->multi : 'na');

			if ($multi->row) {
				if ($multi->row == 'first') {
					$timetp .= $image = JHtml::image("media/com_jem/images/arrow-left.png",'').' '.$start.' ';
					$timetp .= '<br>';
				} elseif ($multi->row == 'middle') {
					$timetp .= JHtml::image("media/com_jem/images/arrow-middle.png",'');
					$timetp .= '<br>';
				} elseif ($multi->row == 'zlast') {
					$timetp .= JHtml::image("media/com_jem/images/arrow-right.png",'').' '.$end.' ';
				} elseif ($multi->row == 'na') {
					if ($start != '') :

						$timetp .= $start;
						if ($end != '') :
							$timetp .= ' - '.$end.' ';
						endif;

						$timetp .= '<br>';
					endif;
				}
			}
		}

		$catname = '<div class="catname">'.$multicatname.'</div>';

		$eventdate = JEMOutput::formatdate($row->dates);

		//venue
		if ($this->jemsettings->showlocate == 1) :
			$venue = '<div class="location"><span class="label">'.JText::_('COM_JEM_VENUE').': </span>';

			if ($this->jemsettings->showlinkvenue == 1 && 0) :
				$venue .= $row->locid != 0 ? "<a href='".JRoute::_(JEMHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>" : '-';
			else :
				$venue .= $row->locid ? $this->escape($row->venue) : '-';
			endif;
				$venue .= '</div>';
		else:
			$venue = '';
		endif;
		//date in tooltip
		$multidaydate = '<div class="location"><span class="label">'.JText::_('COM_JEM_DATE').': </span>';
		if ($multi->row == 'first') {
			$multidaydate .= JEMOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
			$multidaydate .= JEMOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
		} elseif ($multi->row == 'middle') {
			$multidaydate .= JEMOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
			$multidaydate .= JEMOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
		} elseif ($multi->row == 'zlast') {
			$multidaydate .= JEMOutput::formatShortDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
			$multidaydate .= JEMOutput::formatSchemaOrgDateTime($row->multistartdate, $row->times, $row->multienddate, $row->endtimes);
		} else {
			$multidaydate .= JEMOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
			$multidaydate .= JEMOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
		}
		$multidaydate .= '</div>';

		//generate the output
		$content .= $colorpic;
		$content .= JEMHelper::caltooltip($catname.$eventname.$timehtml.$venue, $eventdate, $row->title, $detaillink, 'editlinktip hasTip', $timetp, $category->color);
		$content .= $contentend;

		$this->cal->setEventContent($year, $month, $day, $content);

	endforeach;

	// print the calendar
	print ($this->cal->showMonth());
?>
</div>

<div id="jlcalendarlegend">

	<div id="buttonshowall">
		<?php echo JText::_('COM_JEM_SHOWALL'); ?>
	</div>

	<div id="buttonhideall">
		<?php echo JText::_('COM_JEM_HIDEALL'); ?>
	</div>

	<?php
	//print the legend
	if($this->params->get('displayLegend')) :
		$counter = array();

		//walk through events
		foreach ($this->rows as $row):

			//walk through the event categories
			foreach ($row->categories as $cat) :

				//sort out dupes
				if(!in_array($cat->id, $counter)):

					//add cat id to cat counter
					$counter[] = $cat->id;

					//build legend
					if (array_key_exists($cat->id, $countcatevents)):
					?>
						<div class="eventCat" id="cat<?php echo $cat->id; ?>">
							<?php
							if ( isset ($cat->color) && $cat->color) :
								echo '<span class="colorpic" style="background-color: '.$cat->color.';"></span>';
							endif;
							echo $cat->catname.' ('.$countcatevents[$cat->id].')';
							?>
						</div>
					<?php
					endif;
				endif;
			endforeach;
		endforeach;
	endif;
	?>
</div>

<div class="clr"/></div>
