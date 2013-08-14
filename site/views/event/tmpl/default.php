<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

JHTML::_('behavior.modal', 'a.flyermodal');

?>




<div id="jem" class="event_id<?php echo $this->row->did; ?> jem_event">
	<div class="buttons">
		<?php echo JEMOutput::mailbutton($this->row->slug, 'event', $this->params); ?>
		<?php echo JEMOutput::printbutton($this->print_link, $this->params); ?>
		<?php echo JEMOutput::icalbutton($this->row->slug, 'event'); ?>
	</div>

	<?php if ($this->params->def('show_page_title', 1)) : ?>
		<h1 class="componentheading">
			<?php echo $this->escape($this->row->title); ?>
		</h1>
	<?php endif; ?>

	<!-- Event -->
	<h2 class="jem">
		<?php
		echo JText::_('COM_JEM_EVENT');
		$itemid = $this->item ? $this->item->id : 0;
		echo '&nbsp;'.JEMOutput::editbutton($itemid, $this->row->did, $this->params, $this->allowedtoeditevent, 'editevent');
		?>
	</h2>

	<?php //flyer
	echo JEMOutput::flyer($this->row, $this->dimage, 'event');
	?>

	<dl class="event_info floattext">
		<?php if ($this->jemsettings->showdetailstitle == 1) : ?>
			<dt class="title"><?php echo JText::_('COM_JEM_TITLE').':'; ?></dt>
			<dd class="title"><?php echo $this->escape($this->row->title); ?></dd>
		<?php
		endif;
		?>
		<dt class="when"><?php echo JText::_('COM_JEM_WHEN').':'; ?></dt>
		<dd class="when">
			<?php echo JEMOutput::formatLongDateTime($this->row->dates, $this->row->times,
				$this->row->enddates, $this->row->endtimes); ?>
		</dd>
		<?php if ($this->row->locid != 0) : ?>
			<dt class="where"><?php echo JText::_('COM_JEM_WHERE').':'; ?></dt>
			<dd class="where">
				<?php if (($this->jemsettings->showdetlinkvenue == 1) && (!empty($this->row->url))) : ?>
					<a target="_blank" href="<?php echo $this->row->url; ?>"><?php echo $this->escape($this->row->venue); ?></a> -
				<?php elseif ($this->jemsettings->showdetlinkvenue == 2) : ?>
					<a href="<?php echo JRoute::_(JEMHelperRoute::getVenueRoute($this->row->venueslug)); ?>"><?php echo $this->row->venue; ?></a> -
				<?php elseif ($this->jemsettings->showdetlinkvenue == 0) :
					echo $this->escape($this->row->venue).' - ';
				endif;

				echo $this->escape(ucwords(strtolower($this->row->city))).', '.$this->escape(ucwords(strtolower($this->row->state))); ?>
			</dd>

		<?php endif;
		$n = count($this->categories);
		?>

		<dt class="category"><?php echo $n < 2 ? JText::_('COM_JEM_CATEGORY') : JText::_('COM_JEM_CATEGORIES'); ?>:</dt>
		<dd class="category">
			<?php
			$i = 0;
			foreach ($this->categories as $category) :
			?>
				<a href="<?php echo JRoute::_(JEMHelperRoute::getCategoryRoute($category->slug)); ?>">
					<?php echo $this->escape($category->catname); ?>
				</a>
			<?php
				$i++;
				if ($i != $n) :
					echo ', ';
				endif;
			endforeach;
			?>
		</dd>

		<?php
		for($cr = 1; $cr <= 10; $cr++) {
			$currentRow = $this->row->{'custom'.$cr};
			if(substr($currentRow, 0, 7) == "http://") {
				$currentRow = '<a href="'.$this->escape($currentRow).'" target="_blank">'.$this->escape($currentRow).'</a>';
 			}
			if($currentRow) {
		?>
				<dt class="custom<?php echo $cr; ?>"><?php echo JText::_('COM_JEM_CUSTOM_FIELD'.$cr).':'; ?></dt>
				<dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
		<?php
			}
		}
		?>

	</dl>

	<?php if ($this->jemsettings->showevdescription == 1 && $this->row->datdescription != ''
 		&& $this->row->datdescription != '<br />') : ?>

		<h2 class="description"><?php echo JText::_('COM_JEM_DESCRIPTION'); ?></h2>
		<div class="description event_desc">
			<?php echo $this->row->datdescription; ?>
		</div>

	<?php endif; ?>

	<?php if ($this->row->attachments && count($this->row->attachments)):?>
		<h2 class="description"><?php echo JText::_('COM_JEM_EVENT_FILES'); ?></h2>
		<div>
			<table class="event-file">
				<tbody>
					<?php foreach ($this->row->attachments as $file): ?>
					<tr>
						<td>
							<span class="event-file-dl-icon hasTip" title="<?php echo JText::_('COM_JEM_DOWNLOAD').' '.$this->escape($file->file).'::'.$this->escape($file->description);?>">
							<?php echo JHTML::link('index.php?option=com_jem&task=getfile&format=raw&file='.$file->id,
								   JHTML::image('media/com_jem/images/download_16.png', JText::_('COM_JEM_DOWNLOAD'))); ?></span>
						</td>
						<td class="event-file-name"><?php echo $this->escape($file->name ? $file->name : $file->file); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!--  	Contact  -->
	<?php if ($this->row->conid != 0) : ?>

		<h2 class="contact">
			<?php echo JText::_('COM_JEM_CONTACT') ; ?>
		</h2>

		<dl class="location floattext">
			<?php if ($this->row->conname) : ?>
				<dt class="con_name"><?php echo JText::_('COM_JEM_NAME').':'; ?></dt>
				<dd class="con_name">
					<?php
					echo $this->escape($this->row->conname);
					//echo "<a href='".JRoute::_('index.php?option=com_contact&view=contact&id='.$this->row->conid)."'>".$this->escape($this->row->conname)."</a>";
					?>


				</dd>
			<?php endif; ?>

			<?php if ($this->row->contelephone) : ?>
				<dt class="con_telephone"><?php echo JText::_('COM_JEM_TELEPHONE').':'; ?></dt>
				<dd class="con_telephone">
					<?php echo $this->escape($this->row->contelephone); ?>
				</dd>
			<?php endif; ?>
		</dl>
	<?php endif ?>

	<!--  	Venue  -->
	<?php if ($this->row->locid != 0) : ?>

		<h2 class="location">
			<?php
			echo JText::_('COM_JEM_VENUE') ;
			$itemid = $this->item ? $this->item->id : 0 ;
			echo JEMOutput::editbutton($itemid, $this->row->locid, $this->params, $this->allowedtoeditvenue, 'editvenue');
			?>
		</h2>

		<?php //flyer
		echo JEMOutput::flyer($this->row, $this->limage, 'venue');
		?>

		<dl class="location floattext">
			<dt class="venue"><?php echo JText::_('COM_JEM_LOCATION').':'; ?></dt>
			<dd class="venue">
			<?php echo "<a href='".JRoute::_(JEMHelperRoute::getVenueRoute($this->row->venueslug))."'>".$this->escape($this->row->venue)."</a>"; ?>

			<?php if (!empty($this->row->url)) : ?>
				&nbsp; - &nbsp;
				<a target="_blank" href="<?php echo $this->row->url; ?>"> <?php echo JText::_('COM_JEM_WEBSITE'); ?></a>
			<?php endif; ?>
			</dd>

			<?php
			if ($this->jemsettings->showdetailsadress == 1) :
			?>
				<?php if ($this->row->street) : ?>
				<dt class="venue_street"><?php echo JText::_('COM_JEM_STREET').':'; ?></dt>
				<dd class="venue_street">
					<?php echo $this->escape($this->row->street); ?>
				</dd>
				<?php endif; ?>

				<?php if ($this->row->postalCode) : ?>
				<dt class="venue_postalCode"><?php echo JText::_('COM_JEM_ZIP').':'; ?></dt>
				<dd class="venue_postalCode">
					<?php echo $this->escape($this->row->postalCode); ?>
				</dd>
				<?php endif; ?>

				<?php if ($this->row->city) : ?>
				<dt class="venue_city"><?php echo JText::_('COM_JEM_CITY').':'; ?></dt>
				<dd class="venue_city">
					<?php echo $this->escape((ucwords(strtolower($this->row->city))));?>
				</dd>
				<?php endif; ?>

				<?php if ($this->row->state) : ?>
				<dt class="venue_state"><?php echo JText::_('COM_JEM_STATE').':'; ?></dt>
				<dd class="venue_state">
					<?php echo $this->escape((ucwords(strtolower($this->row->state)))); ?>
				</dd>
				<?php endif; ?>

				<?php if ($this->row->country) : ?>
				<dt class="venue_country"><?php echo JText::_('COM_JEM_COUNTRY').':'; ?></dt>
				<dd class="venue_country">
					<?php echo $this->row->countryimg ? $this->row->countryimg : $this->row->country; ?>
				</dd>
				<?php endif; ?>
			<?php if ($this->jemsettings->showmapserv == 1) {
					echo JEMOutput::mapicon($this->row);
				 	}  ?>
			<?php endif; ?>


		</dl>
		<?php
		if ($this->jemsettings->showmapserv == 2){ ?>
		<p>
		<?php echo JEMOutput::mapicon($this->row);  ?>
		</p>
        <?php } ?>
		<?php if ($this->jemsettings->showlocdescription == 1 && $this->row->locdescription != ''
 			&& $this->row->locdescription != '<br />') : ?>

			<h2 class="location_desc"><?php echo JText::_('COM_JEM_DESCRIPTION'); ?></h2>
			<div class="description location_desc">
				<?php echo $this->row->locdescription;	?>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<!-- Registration -->
	<?php
if ($this->row->registra == 1) :
?>
		<?php echo $this->loadTemplate('attendees'); ?>
	<?php
	 endif;
	?>

	<?php echo $this->row->pluginevent->onEventEnd; ?>

	<div class="copyright">
		<?php echo JEMOutput::footer(); ?>
	</div>
</div>
