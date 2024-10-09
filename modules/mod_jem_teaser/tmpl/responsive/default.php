<?php
/**
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

if ($params->get('use_modal', 0)) {
	echo JemOutput::lightbox();
	$modal = 'lightbox';
} else {
	$modal = 'notmodal';
}
?>

<style>
 <?php
 $imagewidth = 'inherit';
 if ($jemsettings->imagewidth != 0) {
  $imagewidth = $jemsettings->imagewidth .'px';
 }
 $imagewidthstring = 'jem-imagewidth';
 if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), $imagewidthstring)) {
   $pageclass_sfx = $params->get('moduleclass_sfx');
   $imagewidthpos = strpos($pageclass_sfx, $imagewidthstring);
   $spacepos = strpos($pageclass_sfx, ' ', $imagewidthpos);
   if ($spacepos === false) {
     $spacepos = strlen($pageclass_sfx);
   }
   $startpos = $imagewidthpos + strlen($imagewidthstring);
   $endpos = $spacepos - $startpos;
   $imagewidth = substr($pageclass_sfx, $startpos, $endpos);
 }
 $imageheight = 'auto';
 $imageheigthstring = 'jem-imageheight';
 if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), $imageheigthstring)) {
   $pageclass_sfx = $params->get('moduleclass_sfx');
   $imageheightpos = strpos($pageclass_sfx, $imageheigthstring);
   $spacepos = strpos($pageclass_sfx, ' ', $imageheightpos);
   if ($spacepos === false) {
     $spacepos = strlen($pageclass_sfx);
   }
   $startpos = $imageheightpos + strlen($imageheigthstring);
   $endpos = $spacepos - $startpos;
   $imageheight = substr($pageclass_sfx, $startpos, $endpos);
 }
 ?>

    #jemmoduleteaser .jem-eventimg-teaser img {
        width: 100%;
        height: <?php echo $imageheight; ?>;
    }

    @media not print {
        @media only all and (max-width: 47.938rem) {

      
      #jemmoduleteaser .jem-eventimg-teaser img {
        width: <?php echo $imagewidth; ?>;
        height: <?php echo $imageheight; ?>;
      }
    }
  }
</style>

<div class="jemmoduleteaser<?php echo $params->get('moduleclass_sfx')?>" id="jemmoduleteaser">
<?php ?>
	<div class="eventset">
	<?php if (count($list)) : ?>
    <?php
      $titletag = '<h2 class="event-title">';
      $titleendtag = '</h2>';
      if ($module->showtitle) {
        $titletag = '<h3 class="event-title">';
        $titleendtag = '</h3>';
      } 
    ?>
    <?php foreach ($list as $item) : ?>
    <div class="event_id<?php echo $item->eventid; ?>">
      <?php echo $titletag; ?>
        <?php if ($item->eventlink) : ?>
          <a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>"><?php echo $item->title; ?></a>
        <?php else : ?>
          <?php echo $item->title; ?>
        <?php endif; ?>
      <?php echo $titleendtag; ?>
      
      <div class="jem-row-teaser jem-teaser-event">

		<?php if ($item->colorclass === "category" || $item->colorclass === "alpha"): ?>
			<div class="calendar<?php echo '-' . $item->colorclass; ?> jem-teaser-calendar" title="<?php echo strip_tags($item->dateinfo); ?>">
               <div class="color-bar" style="background-color:<?php echo !empty($item->color) ? $item->color : 'rgb(128,128,128)'; ?>"></div>
            <div class="lower-background"></div>
               <div class="background-image"></div>
          	<?php else : ?>
    		<div class="calendar<?php echo '-' . $item->colorclass; ?> jem-teaser-calendar" title="<?php echo strip_tags($item->dateinfo); ?>"<?php if (!empty($item->color)): ?> style="background-color: <?php echo $item->color; ?>"<?php endif; ?>>
        <?php endif; ?>
    
         <div class="monthteaser<?php 
    echo isset($item->color_is_dark) 
        ? ($item->color_is_dark === 1 
            ? ' monthcolor-light">' 
            : ($item->color_is_dark === 0 
                ? ' monthcolor-dark">' 
                : '">'))
        : '">';
    	echo $item->startdate['month']; ?>
          </div>
          <div class="dayteaser">
            <?php echo empty($item->dayname) ? '<br/>' : $item->dayname; ?>
          </div>
          <div class="daynumteaser">
            <?php echo empty($item->daynum) ? '?' : $item->daynum; ?>
          </div>
        </div>
        <div class="jem-event-details-teaser">
          <div class="jem-row-teaser jem-teaser-datecat">
            <?php if ($item->date && $params->get('datemethod', 1) == 2) :?>
              <div class="date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-clock" aria-hidden="true"></i> -->
                <?php echo $item->date; ?>
              </div>
            <?php //endif; ?>
            <?php elseif ($item->date && $params->get('datemethod', 1) == 1) : ?>
              <div class="time" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-clock" aria-hidden="true"></i> -->
                <?php echo $item->dateinfo; ?>
              </div>
            <?php //endif; ?>
            <?php /* elseif ($item->time && $params->get('datemethod', 1) == 1) :?>
              <div class="time" title="<?php echo strip_tags($item->dateinfo); ?>">
                <i class="fa fa-clock-o" aria-hidden="true"></i>
                <?php echo $item->time; ?>
              </div>
            <?php */endif; ?>
            <?php if (!empty($item->venue)) : ?>
              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-novenue')) : ?>
                <div class="venue-title" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.strip_tags($item->venue); ?>">
                <!-- <i class="fa fa-map-marker" aria-hidden="true"></i> -->
                <?php if ($item->venuelink) : ?>
                  <a href="<?php echo $item->venuelink; ?>"><?php echo $item->venue; ?></a>
                <?php else : ?>
                  <?php echo $item->venue; ?>
                <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocats')) : ?>
              <div class="category" title="<?php echo Text::_('COM_JEM_TABLE_CATEGORY').': '.strip_tags($item->catname); ?>">
                <!-- <i class="fa fa-tag" aria-hidden="true"></i> -->
                <?php echo $item->catname; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="jem-event-image-teaser">
          <div class="jem-row-image-teaser">
            <?php if($item->showimageevent): ?>
                <?php if(strpos($item->eventimage,'/media/com_jem/images/blank.png') === false) : ?>
                  <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-noimageevent')) : ?>
                    <?php if(!empty($item->eventimage)) : ?>
                      <div class="jem-eventimg-teaser">
                       <?php if ($params->get('use_modal')) : ?>
                       	<?php if ($item->eventimageorig) {
						$image = $item->eventimageorig;
						$document = Factory::getDocument();
						$document->addStyleSheet(Uri::base() .'media/com_jem/css/lightbox.min.css');
						$document->addScript(Uri::base() . 'media/com_jem/js/lightbox.min.js');
						echo '<script>lightbox.option({
							\'showImageNumberLabel\': false,
							})
							</script>';
					} else {
						$image = '';
					} ?>
					
					<a href="<?php echo $image; ?>" class="teaser-flyerimage" data-lightbox="teaser-flyerimage-<?php echo $item->eventid; ?>" rel="<?php echo $modal;?>" title="<?php echo Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" data-title="<?php echo Text::_('COM_JEM_EVENT') .': ' . $item->fulltitle; ?>">
					<?php endif; ?>
                        <img class="float_right image-preview" src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" />
                    <?php if ($params->get('use_modal')) : ?>
                      </a>
                    <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($item->showimagevenue): ?>
              <?php if(strpos($item->venueimage,'/media/com_jem/images/blank.png') === false) : ?>
                  <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-noimagevenue')) : ?>
                      <?php if(!empty($item->venueimage)) : ?>
                          <div class="jem-eventimg-teaser">
                    
                     <?php if ($params->get('use_modal')) : ?>
					<?php if ($item->venueimageorig) {
						$image = $item->venueimageorig;
					} ?>
					<a href="<?php echo $image; ?>" class="teaser-flyerimage" data-lightbox="teaser-flyerimage-<?php echo $item->eventid; ?>" rel="<?php echo $modal;?>" title="<?php echo Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" data-title="<?php echo Text::_('COM_JEM_VENUE') .': ' . $item->venue; ?>">
					<?php endif; ?>
                            <img class="float_right image-preview" src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" />
						<?php if ($params->get('use_modal')) : ?>
                          </a>
                        <?php endif; ?>
                          </div>
                      <?php endif; ?>
                  <?php endif; ?>
              <?php endif; ?>
            <?php endif; ?>

            <?php if($item->showdescriptionevent): ?>
              <div class="jem-description-teaser">
                                            <?php
					echo $item->eventdescription;
					if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')) : ?>
                    <div class="jem-readmore">
                      <a href="<?php echo $item->eventlink ?>" title="<?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>">
                      <?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>
                    </a>
                    </div>
                <?php endif; ?>
              </div>
            <?php endif; ?> 
          </div>
        </div>
      </div>
      </div>
      <?php 
      if ($item !== end($list)) :
          echo '<hr class="jem-hr">';
      endif;
      ?>
    <?php endforeach; ?>
	<?php else : ?>
		<?php echo Text::_('MOD_JEM_TEASER_NO_EVENTS'); ?>
	<?php endif; ?>
	</div>
</div>