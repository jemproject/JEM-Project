<?php
/**
 * @version 1.9.3
 * @package JEM
 * @subpackage JEM Teaser Module
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
JHTML::_('behavior.modal', 'a.flyermodal');
?>

<div id="jemmoduleteaser">
<?php ?>
<div class="eventset" summary="mod_jem_teaser">

<?php foreach ($list as $item) : ?>

	<h2 class="event-title">
	<?php if ($item->eventlink) : ?>
		<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->title; ?>">
	<?php endif; ?>

	<?php echo $item->title; ?>
	<?php if ($item->eventlink) : ?>
		</a>
	<?php endif; ?></h2>

<table>
	<tr>
		<td>
			<div class="calendar">
				<div class="year">
					<!--I don't need <?php echo $item->year; ?> -->
				</div>
				<div class="month">
					<?php echo $item->month; ?>
				</div>
				<div class="day">
					<?php echo $item->dayname; ?>
				</div>
				<div class="daynum">
					<?php echo $item->daynum; ?>
				</div>
			</div>
		</td>
		<td>
			<div class="teaser-jem">
				<div>
					<?php if(($item->eventimage)!=str_replace("jpg","",($item->eventimage)) OR
							 ($item->eventimage)!=str_replace("gif","",($item->eventimage)) OR
							 ($item->eventimage)!=str_replace("png","",($item->eventimage))) : ?>
						<a href="<?php echo $item->eventimageorig; ?>" class="modal-jem" title="<?php echo $item->title; ?> ">
						<img class="float_right image-preview" src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" /></a>
					<?php else : ?>
						<?php if(($item->venueimage)!=str_replace("jpg","",($item->venueimage)) OR
								 ($item->venueimage)!=str_replace("gif","",($item->venueimage)) OR
								 ($item->venueimage)!=str_replace("png","",($item->venueimage))) : ?>
							<a href="<?php echo $item->venueimageorig; ?>" class="modal-jem" title="<?php echo $item->venue; ?> ">
							<img src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" class="float_right image-preview" /></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<div>
					<?php echo $item->eventdescription; ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<?php if ($item->time && $params->get('datemethod', 1) == 1) :?>
				<div class="time">
					<small>
					<?php echo $item->time; ?>
					</small>
				</div>
			<?php endif; ?>
		</td>
		<td>
			<div class="venue-title">
				<?php if ($item->venuelink) : ?>
					<a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>">
				<?php endif; ?>
				<?php echo $item->venue; ?>
				<?php if ($item->venuelink) : ?>
				</a>
				<?php endif; ?>
			</div>
			<div class="category">
				<?php if ($item->categorylink) : ?>
					<a href="<?php echo $item->categorylink; ?>" title="<?php echo $item->catname; ?>">
				<?php endif; ?>
				<?php echo $item->catname; ?>
				<?php if ($item->categorylink) : ?>
				</a>
				<?php endif; ?>
			</div>
		</td>
	</tr>
</table>
<?php endforeach; ?>
</div>
</div>