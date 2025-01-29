<?php
/**
 * @package    JEM
 * @subpackage JEM Jubilee Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$datemethod      = (int)$params->get('datemethod', 0);
$showtime        = (int)$params->get('showtime', 0);
$showcalendar    = (int)$params->get('showcalendar', 1);
$introtext       = $params->get('introtext', '');
$showflyer       = (int)$params->get('showflyer', 1);
$flyer_link_type = (int)$params->get('flyer_link_type', 0);

$colorclass      = $params->get('color');
$user_color      = $params->get('usercolor', '#EEEEEE');
$user_color_is_dark = $params->get('usercolor_is_dark', false);
$date            = (array)$params->get('date');

if ($flyer_link_type == 1) {
	echo JemOutput::lightbox();
	$modal = 'lightbox';
} elseif ($flyer_link_type == 0) {
	$modal = 'notmodal';
} else {
	$modal = '';
}
?>

<div class="jemmodulejubilee<?php echo $params->get('moduleclass_sfx'); ?>" id="jemmodulejubilee">
<?php ?>
	<div class="eventset">
		<?php if ($showcalendar == 1) :?>
		<?php if ($colorclass === "alpha"): ?>
			<div class="calendar<?php echo '-' . $colorclass; ?> jem-jubilee-calendar">
               <div class="color-bar" style="background-color:<?php echo !empty($user_color) ? $user_color : 'rgb(128,128,128)'; ?>"></div>
            <div class="lower-background"></div>
               <div class="background-image"></div>
    	<?php else: ?>
        	<div class="calendar<?php echo '-' . $colorclass; ?> jem-jubilee-calendar">
    <?php endif; ?>
          <?php if (isset($user_color_is_dark)) : ?>
        <div class="monthjubilee monthcolor-<?php echo !empty($user_color_is_dark) ? 'light' : 'dark'; ?>">
          	<?php else : ?>
				<div class="monthjubilee">
			<?php endif;
				echo $date['month']; ?>
				</div>
				<div class="dayjubilee">
					<?php /*echo $date['weekday'];*/ ?>
				</div>
				<div class="daynumjubilee">
					<?php echo $date['day']; ?>
				</div>
			</div>
		<?php endif; ?>		
		<?php if (!empty($introtext)) :?>
		<div class="intro">
			<?php echo $introtext; ?>
		</div>
		<?php endif; ?>

	<?php $i = count($list); ?>
	<?php if ($i == 0) : ?>
		<div class="clr"></div>
		<div class="hr"><hr /></div>
		<p><?php echo Text::_('MOD_JEM_JUBILEE_NO_EVENTS'); ?></p>
	<?php else : ?>
		<?php foreach ($list as $item) : ?>
			<div class="clr"></div>
			<div class="hr"><hr /></div>
			<div class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event">
			<h2 class="event-title" itemprop="name" content="<?php echo $item->title; ?>">
				<?php echo $item->startdate['year'] . ': '; ?>
			<?php if ($item->eventlink) : ?>
				<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>" itemprop="url"><?php echo $item->title; ?></a>
			<?php else : ?>
				<?php echo $item->title; ?>
			<?php endif; ?>
			</h2>

			<div>
				<?php if (($showflyer == 1) && !empty($item->eventimage)) : ?>
				<div>
					<div class="banner-jem">
					<?php if ($flyer_link_type != 3) : ?>
						<a href="<?php echo ($flyer_link_type == 2) ? $item->eventlink : $item->eventimageorig; ?>" rel="<?php echo $modal;?>" class="jubilee-flyerimage" title="<?php echo ($flyer_link_type == 2) ? $item->fulltitle : Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" data-title="<?php echo $item->title; ?>"><?php endif; ?>
								<img class="float_right <?php echo 'image-preview2'; ?>" src="<?php echo $item->eventimageorig; ?>" alt="<?php echo $item->title; ?>" />
							<?php if ($flyer_link_type != 3) { echo '</a>'; } ?>
						</div>
					</div>
				<div class="clr"></div>
				<?php else /* showflyer == 0 or no image */ : ?>
				<div>
					<div class="banner-jem">
					</div>
				</div>
				<?php endif; ?>

				<?php if ($params->get('showdesc', 1) == 1) :?>
				<div class="desc" itemprop="description">
					<?php echo $item->eventdescription; ?>
					<?php if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')) :
						echo '</br><a class="readmore" href="'.$item->link.'">'.$item->linkText.'</a>';
					endif;?>
				</div>
				<?php endif;
				 echo $item->dateschema; ?>
      			<div itemprop="location" itemscope itemtype="https://schema.org/Place" style="display:none;">
      				<meta itemprop="name" content="<?php echo $item->venue; ?>" />
      				<div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" style="display:none;">
      					<meta itemprop="streetAddress" content="<?php echo $item->street; ?>" />
      					<meta itemprop="addressLocality" content="<?php echo $item->city; ?>" />
      					<meta itemprop="addressRegion" content="<?php echo $item->state; ?>" />
      					<meta itemprop="postalCode" content="<?php echo $item->postalCode; ?>" />
      				</div>
          		</div>
			</div>

			<div class="clr"></div>

			<?php /* Datum und Zeitangabe:
				   *  showcalendar 1, datemethod 1 : date inside calendar image + time
				   *  showcalendar 1, datemethod 2 : date inside calendar image + relative date + time
				   *  showcalendar 0, datemethod 1 : no calendar image, date + time
				   *  showcalendar 0, datemethod 2 : no calendar image, relative date + time
				   */
			 ?>
			<?php /* wenn kein Kalenderblatt angezeigt wird */ ?>
			<?php if (1/*$showcalendar == 0*/) : ?>
				<?php if ($item->date && $datemethod == 2) :?>
				<div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
					<?php echo $item->date; ?>
				</div>
				<?php endif; ?>

				<?php if ($item->date && $datemethod == 1) :?>
				<div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
					<?php echo $item->date; ?>
				</div>
					<?php if ($showtime == 1 && $item->time && $datemethod == 1) :?>
					<div class="time" title="<?php echo strip_tags($item->dateinfo); ?>">
						<?php echo $item->time; ?>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php /* wenn Kalenderblatt angezeigt wird */ ?>
			<?php else : ?>
				<?php /* wenn Zeitdifferenz angezeigt werden soll */ ?>
				<?php if ($item->date && $datemethod == 2) : ?>
				<div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
					<?php echo $item->date; ?>
				</div>
				<?php endif; ?>

				<?php /* wenn Datum angezeigt werden soll */ ?>
				<?php if ($showtime == 1 && $item->time && $datemethod == 1) :?>
				<?php /* es muss nur noch die Zeit angezeigt werden (da Datum auf Kalenderblatt schon angezeigt) */ ?>
				<div class="time" title="<?php echo strip_tags($item->dateinfo); ?>">
					<?php echo $item->time; ?>
				</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="clr"></div>

			<?php /*venue*/ ?>
			<?php if (($params->get('showvenue', 1) == 1) && !empty($item->venue)) :?>
				<div class="venue-title">
				<?php if ($item->venuelink) : ?>
					<a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>"><?php echo $item->venue; ?></a>
				<?php else : ?>
					<?php echo $item->venue; ?>
				<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php /*category*/ ?>
			<?php if (($params->get('showcategory', 1) == 1) && !empty($item->catname)) :?>
				<div class="category">
					<?php echo $item->catname; ?>
				</div>
			<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="clr"></div>
	<?php endif; ?>
	</div>
</div>