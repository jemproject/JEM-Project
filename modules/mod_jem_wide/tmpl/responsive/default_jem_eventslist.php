<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$jemsettings = JemHelper::config();

?>

<style>
 <?php
 $imagewidth = 'inherit';
 if ($jemsettings->imagewidth != 0) {
  $imagewidth = $jemsettings->imagewidth / 2; 
  $imagewidth = $imagewidth.'px';
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

  #jemmodulewide .jem-list-img {
    width: <?php echo $imagewidth; ?>;
  }
  
  #jemmodulewide .jem-list-img img {
    width: <?php echo $imagewidth; ?>;
    height: <?php echo $imageheight; ?>;
  }
  
  @media not print {
    @media only all and (max-width: 47.938rem) {  
      #jemmodulewide .jem-event-details {
        flex-basis: 100%;
      }
      
      #jemmodulewide .jem-list-img img {
        width: <?php echo $imagewidth; ?>;
        height: <?php echo $imageheight; ?>;
      }
    }
  }
</style>

<ul class="eventlist">
      <?php
      // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
      // This detects the browser and just writes the onclick attribute if the broswer is not Safari.
      $isSafari = false;
      if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
        $isSafari = true;
      }
      ?>
			<?php foreach ($list as $item) : ?>
        <?php if (!empty($item->featured)) :   ?>
          <li class="jem-event jem-row jem-justify-start jem-featured" <?php if ($params->get('linkevent') == 1 && (!$isSafari)) : echo 'onclick=location.href="'.$item->eventlink.'"'; endif; ?> >
				<?php else : ?>
          <li class="jem-event jem-row jem-justify-start">
				<?php endif; ?>       
          <div class="jem-event-details" <?php if ($params->get('linkevent') == 1 && (!$isSafari)) : echo 'onclick=location.href="'.$item->eventlink.'"'; endif; ?>>
            <?php if ($params->get('linkevent') == 1) : // Display title as title of jem-event with link ?>
            <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$item->fulltitle; ?>">
              <a href="<?php echo $item->eventlink; ?>" ><?php echo $item->title; ?></a>
              <?php echo JemOutput::recurrenceicon($item); ?>
              <?php echo JemOutput::publishstateicon($item); ?>
              <?php if (!empty($item->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4>
            
            <?php elseif ($params->get('linkevent') == 0) : //Display title as title of jem-event without link ?>
            <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$item->fulltitle; ?>">
              <?php echo $item->title . JemOutput::recurrenceicon($item) . JemOutput::publishstateicon($item); ?>
              <?php if (!empty($item->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4> 
            <?php endif; ?>
            
            <?php // Display other information below in a row ?>
            <div class="jem-list-row"> 
              
              <?php if ($item->date && $params->get('datemethod', 1) == 2) :?>
                <div class="jem-event-info date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                  <!-- <i class="fa fa-clock" aria-hidden="true"></i> -->
                  <?php echo $item->date; ?>
                </div>
              <?php elseif ($item->date && $params->get('datemethod', 1) == 1) : ?>
                <div class="jem-event-info time" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                  <!-- <i class="fa fa-clock" aria-hidden="true"></i> -->
                  <?php echo $item->dateinfo; ?>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($item->venue) && (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-novenue'))) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$item->venue; ?>">
                  <i class="fa fa-map-marker" aria-hidden="true"></i>
                  <?php if ($params->get('linkvenue') == 1) : ?>
                    <?php echo "<a href='".$item->venuelink."'>".$item->venue."</a>"; ?>
                  <?php else : ?>
                    <?php echo $item->venue; ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if ((!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocity')) && (!empty($item->city))) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$item->city; ?>">
                  <i class="fa fa-building" aria-hidden="true"></i>
                  <?php echo $item->city; ?>
                </div>
              <?php endif; ?>
              
              <?php if ((!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nostate')) && (!empty($item->state))): ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$item->state; ?>">
                  <i class="fa fa-map" aria-hidden="true"></i>
                  <?php echo $item->state; ?>
                </div>
              <?php endif;?> 
              
              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocats')) : ?>
                <div class="jem-event-info" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.$item->catname); ?>">
                  <i class="fa fa-tag" aria-hidden="true"></i>
                  <?php echo $item->catname; ?>
                </div>
              <?php endif; ?>  
            </div>         
          </div>   

          <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-noimageevent') && (strpos($item->eventimage, 'blank.png') === false)) : ?>
            <div class="jem-list-img" >
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
				
              <a href="<?php echo $image; ?>" class="flyermodal" rel="lightbox" data-lightbox="wide-flyerimage-<?php echo $item->eventid ?>"  data-title="<?php echo Text::_('COM_JEM_EVENT') .': ' . $item->title; ?>">
              <?php endif; ?>
                <img src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->fulltitle; ?>" class="image-preview" title="<?php echo Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" />
              <?php if ($params->get('use_modal')) : ?>
              </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          
          <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-noimagevenue') && (strpos($item->venueimage, 'blank.png') === false)) : ?>
            <div class="jem-list-img" >
              <?php if ($params->get('use_modal')) : ?>
                <a href="<?php echo $item->venueimageorig; ?>" class="flyermodal" rel="lightbox" data-lightbox="wide-flyerimage-<?php echo $item->eventid ?>" title="<?php echo $item->venue; ?>" data-title="<?php echo Text::_('COM_JEM_VENUE') .': ' . $item->venue; ?>">
                <?php endif; ?>
                  <img src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" class="image-preview" title="<?php echo Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" />
                <?php if ($item->venuelink) : ?>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          
        </li>
			<?php endforeach; ?>
</ul>