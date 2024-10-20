<?php
/**
 * @package    JEM
 * @subpackage JEM Banner Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$datemethod      = (int)$params->get('datemethod', 1);
$showcalendar    = (int)$params->get('showcalendar', 1);
$showflyer       = (int)$params->get('showflyer', 1);
$flyer_link_type = (int)$params->get('flyer_link_type', 0);
$imagewidthmax   = (int)$params->get('imagewidthmax', 0);

if ($flyer_link_type == 1) {
    echo JemOutput::lightbox();
    $modal = 'lightbox';
} elseif ($flyer_link_type == 0) {
    $modal = 'notmodal';
} else {
    $modal = '';
}
?>
<style>
    .banner-jem img {
    <?php echo ($imagewidthmax? "width:" . $imagewidthmax ."px": "max-width:100%"); ?>;
    }
</style>

<div class="jemmodulebanner<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebanner">
    <div class="eventset">
        <?php $i = count($list); ?>
        <?php if ($i > 0) : ?>
        <?php foreach ($list as $item) : ?>
        <div class="event_id<?php echo $item->eventid; ?>">
            <h2 class="event-title">
                <?php if ($item->eventlink) : ?>
                    <a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>"><?php echo $item->title; ?></a>
                <?php else : ?>
                    <?php echo $item->title; ?>
                <?php endif; ?>
            </h2>
				<div class="jem-row-banner <?php echo $banneralignment; ?>">
				      
                <?php if ($showcalendar == 1) :?>
		<?php if ($item->colorclass === "category" || $item->colorclass === "alpha"): ?>
			<div class="calendar<?php echo '-' . $item->colorclass; ?> jem-banner-calendar" title="<?php echo strip_tags($item->dateinfo); ?>">
               <div class="color-bar" style="background-color:<?php echo !empty($item->color) ? $item->color : 'rgb(128,128,128)'; ?>"></div>
            <div class="lower-background"></div>
               <div class="background-image"></div>
    <?php else: ?>
        <div class="calendar<?php echo '-' . $item->colorclass; ?> jem-banner-calendar"
                         title="<?php echo strip_tags($item->dateinfo); ?>"
                        <?php if (!empty($item->color)) : ?>
                            style="background-color: <?php echo $item->color; ?>"
            <?php endif; ?>>
                        <?php endif; ?>
    
                        <?php if (isset($item->color_is_dark)) : ?>
        <div class="monthbanner monthcolor-<?php echo !empty($item->color_is_dark) ? 'light' : 'dark'; ?>">
                            <?php else : ?>
                            <div class="monthbanner">
                                <?php endif;
                                echo $item->startdate['month']; ?>
                            </div>
                            <div class="daybanner">
                                <?php echo $item->startdate['weekday']; ?>
                            </div>
                            <div class="daynumbanner">
                                <?php echo $item->startdate['day']; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (($showflyer == 1) && !empty($item->eventimage)) : ?>
                        <div>
                            <div class="banner-jem">
                                <div>
                                    <?php $class = ($showcalendar == 1) ? 'image-preview' : 'image-preview2'; ?>
                                    <?php if ($flyer_link_type != 3) : ?>
                                    <a href="<?php echo ($flyer_link_type == 2) ? $item->eventlink : $item->eventimageorig; ?>" rel="<?php echo $modal;?>" class="jubilee-flyerimage" title="<?php echo ($flyer_link_type == 2) ? $item->fulltitle : Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" data-title="<?php echo $item->title; ?>"><?php endif; ?>
                                        <img class="float_right <?php echo 'image-preview2'; ?>" src="<?php echo $item->eventimageorig; ?>" alt="<?php echo $item->title; ?>" />
                                        <?php if ($flyer_link_type != 3) { echo '</a>'; } ?>
                                </div>
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
                        <div class="desc">
                            <?php echo $item->eventdescription; ?>
                            <?php if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')) :
                                echo '</br><a class="readmore" href="'.$item->link.'">'.$item->linkText.'</a>';
                            endif;?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="clr"></div>

                <?php /* Datum und Zeitangabe:
				       *  showcalendar 1, datemethod 1 : date inside calendar image + time
				       *  showcalendar 1, datemethod 2 : date inside calendar image + relative date + time
				       *  showcalendar 0, datemethod 1 : no calendar image, date + time
				       *  showcalendar 0, datemethod 2 : no calendar image, relative date + time
			    	   */
                ?>

                <?php /* if no calendar sheet is displayed */ ?>
                <?php if ($showcalendar == 0) : ?>
                    <?php if ($item->date && $datemethod == 2) :?>
                        <div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
                            <?php echo $item->date; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($item->date && $datemethod == 1) :?>
                        <div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
                            <?php echo $item->date; ?>
                        </div>
                        <?php if ($item->time && $datemethod == 1) :?>
                            <div class="time" title="<?php echo strip_tags($item->dateinfo); ?>">
                                <?php echo $item->time; ?>
                            </div>
                        <?php endif;
                    endif;

                // if calendar page is displayed
                else :
                    // if time difference is to be displayed
                    if ($item->date && $datemethod == 2) : ?>
                        <div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
                            <?php echo $item->date; ?>
                        </div>
                    <?php endif;

                    // if date is to be displayed
                    if ($item->time && $datemethod == 1) :
                        // only the time needs to be displayed (as the date is already displayed on the calendar page) ?>
                        <div class="time" title="<?php echo strip_tags($item->dateinfo); ?>">
                            <?php echo $item->time; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="clr"></div>

                <?php
                // venue
                if (($params->get('showvenue', 1) == 1) && (!empty($item->venue))) :?>
                    <div class="venue-title">
                        <?php if ($item->venuelink) : ?>
                            <a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>"><?php echo $item->venue; ?></a>
                        <?php else : ?>
                            <?php echo $item->venue; ?>
                        <?php endif; ?>
                    </div>
                <?php endif;

                // category
                if (($params->get('showcategory', 1) == 1) && !empty($item->catname)) :?>
                    <div class="category">
                        <?php echo $item->catname; ?>
                    </div>
                <?php endif; ?>

                <div class="clr"></div>

                <?php if (--$i > 0) : /* no hr after last entry */ ?>
                    <div class="hr"><hr /></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php else : ?>
                <?php echo Text::_('MOD_JEM_BANNER_NO_EVENTS'); ?>
            <?php endif; ?>
        </div>
    </div>
