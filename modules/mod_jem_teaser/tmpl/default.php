<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

if ($params->get('use_modal', 0)) {
	echo JemOutput::lightbox();
	$modal = 'lightbox';
} else {
	$modal = 'notmodal';
}
?>

<div class="jemmoduleteaser<?php echo $params->get('moduleclass_sfx')?>" id="jemmoduleteaser">
<?php ?>
	<div class="eventset" summary="mod_jem_teaser">
	<?php if (count($list)) : ?>
		<?php foreach ($list as $item) : ?>

			<h2 class="event-title">
				<?php if ($item->eventlink) : ?>
					<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>"><?php echo $item->title; ?></a>
				<?php else : ?>
					<?php echo $item->title; ?>
				<?php endif; ?>
			</h2>

			<table>
				<tr>
					<td class="event-calendar">
						<div class="calendar<?php echo '-'.$item->colorclass; ?>"
						     title="<?php echo strip_tags($item->dateinfo); ?>"
							<?php if (!empty($item->color)) : ?>
						     style="background-color: <?php echo $item->color; ?>"
							<?php endif; ?>
						>
							<div class="monthteaser">
								<?php echo $item->month; ?>
							</div>
							<div class="dayteaser">
								<?php echo empty($item->dayname) ? '<br/>' : $item->dayname; ?>
							</div>
							<div class="daynumteaser">
								<?php echo empty($item->daynum) ? '?' : $item->daynum; ?>
							</div>
						</div>
					</td>
					<td class="event-info">
						<div class="teaser-jem">
							<div>
              <?php if($item->showimageevent): ?>
                <?php if(strpos($item->eventimage,'/media/com_jem/images/blank.png') === false) : ?>
                  <a href="<?php echo $item->eventimageorig; ?>" class="teaser-flyerimage" rel="<?php echo $modal;?>" data-lightbox="wide-flyerimage-<?php echo $item->eventid ?>" title="<?php echo Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" data-title="<?php echo Text::_('COM_JEM_EVENT') .': ' . $item->fulltitle; ?>">
                    <img class="float_right image-preview" style="height:auto" src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" /></a>
                <?php endif; ?>
              <?php endif; ?>
              <?php if(strpos($item->venueimage,'/media/com_jem/images/blank.png') === false) : ?>
                <?php if(!empty($item->venueimage)) : ?>
                  <a href="<?php echo $item->venueimageorig; ?>" class="teaser-flyerimage" rel="<?php echo $modal;?>" data-lightbox="wide-flyerimage-<?php echo $item->eventid ?>" title="<?php echo Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" data-title="<?php echo Text::_('COM_JEM_VENUE') .': ' . $item->venue; ?>">
                    <img class="float_right image-preview" style="height:auto" src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" /></a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div>
              <?php if($item->showdescriptionevent):
                echo $item->eventdescription;
                if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')) :
                  echo '<a class="readmore" style="padding-left: 10px;" href="'.$item->link.'">'.$item->linkText.'</a>';
                endif;

                if ($item->eventlink) : ?>
                    <a href="<?php echo $item->eventlink ?>" title="<?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>">
                      <?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>
                    </a>
                <?php endif;
              endif; ?>
            </div>
						</div>
					</td>
				</tr>
				<tr>
					<td class="event-datetime">
						<?php if ($item->date && $params->get('datemethod', 1) == 2) :?>
							<div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
								<small><?php echo $item->date; ?></small>
							</div>
						<?php endif; ?>
						<?php if ($item->time && $params->get('datemethod', 1) == 1) :?>
							<div class="time" title="<?php echo strip_tags($item->dateinfo); ?>">
								<small><?php echo $item->time; ?></small>
							</div>
						<?php endif; ?>
					</td>
					<td class="event-vencat">
						<?php if (!empty($item->venue)) : ?>
							<div class="venue-title">
							<?php if ($item->venuelink) : ?>
								<a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>"><?php echo $item->venue; ?></a>
							<?php else : ?>
								<?php echo $item->venue; ?>
							<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if (!empty($item->catname)) : ?>
							<div class="category">
								<?php echo $item->catname; ?>
							</div>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		<?php endforeach; ?>
	<?php else : ?>
		<?php echo Text::_('MOD_JEM_TEASER_NO_EVENTS'); ?>
	<?php endif; ?>
	</div>
</div>
