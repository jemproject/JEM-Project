<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @subpackage JEM Banner Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

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

/*
$uri = Uri::getInstance();
$module_name = 'mod_jem_banner';
$css_path = JPATH_THEMES. '/'.$document->template.'/css/'.$module_name;
if(file_exists($css_path.'/'.$module_name.'.css')) {
  unset($document->_styleSheets[$uri->base(true).'/modules/mod_jem_banner/tmpl/mod_jem_banner.css']);
  $document->addStylesheet($uri->base(true) . '/templates/'.$document->template.'/css/'. $module_name.'/'.$module_name.'.css');
}*/

$banneralignment = "jem-vertical-banner";
if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), "jem-horizontal")){
  $banneralignment = "jem-horizontal-banner";
}
?>

<style>
 <?php
 $imagewidth = '100%';
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

  #jemmodulebanner .jem-eventimg-banner {
    width: <?php echo $imagewidth; ?>;
    <?php
    if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), "jem-imagetop")) {
      echo "order: -1;";
    }
    ?>
  }
  
  #jemmodulebanner .jem-eventimg-banner img {
    <?php echo ($imagewidthmax? 'width:' . $imagewidthmax .'px': 'max-width:'. $imagewidth); ?>;
    height: <?php echo ($imagewidthmax? 'auto' : $imageheight); ?>;
  }
  
  @media not print {
    @media only all and (max-width: 47.938rem) {  
      #jemmodulebanner .jem-eventimg-banner {
        
      }
      
      #jemmodulebanner .jem-eventimg-banner img {
        width: <?php echo $imagewidth; ?>;
        height: <?php echo $imageheight; ?>;
      }
    }
  }
</style>

<div class="jemmodulebanner<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebanner">
<?php ?>
	<div class="eventset" summary="mod_jem_banner">
	<?php $i = count($list); ?>
	<?php if ($i > 0) : ?>
		<?php foreach ($list as $item) : ?>

			<h2 class="event-title">
			<?php if ($item->eventlink) : ?>
				<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>"><?php echo $item->title; ?></a>
			<?php else : ?>
				<?php echo $item->title; ?>
			<?php endif; ?>
			</h2>

      <div class="jem-row-banner <?php echo $banneralignment; ?>">
        <?php if ($showcalendar == 1) :?>
          <div class="calendar<?php echo '-'.$item->colorclass; ?> jem-banner-calendar"
               title="<?php echo strip_tags($item->dateinfo); ?>"
            <?php if (!empty($item->color)) : ?>
               style="background-color: <?php echo $item->color; ?>"
            <?php endif; ?>
          >
            <div class="monthbanner">
              <?php echo $item->startdate['month']; ?>
            </div>
            <div class="daybanner">
              <?php echo $item->startdate['weekday']; ?>
            </div>
            <div class="daynumbanner">
              <?php echo $item->startdate['day']; ?>
            </div>
          </div>
        <?php endif; ?>
        <div class="jem-event-details-banner jem-row-banner">
        <div class="jem-row-banner <?php echo $banneralignment; ?> jem-banner-datecat">
          <?php /* Datum und Zeitangabe:
                 *  showcalendar 1, datemethod 1 : date inside calendar image + time
                 *  showcalendar 1, datemethod 2 : date inside calendar image + relative date + time
                 *  showcalendar 0, datemethod 1 : no calendar image, date + time
                 *  showcalendar 0, datemethod 2 : no calendar image, relative date + time
                 */
          ?>
          <?php /* when no calendar sheet is displayed */ ?>
          <?php if ($showcalendar == 0) : ?>
            <?php if ($item->date && $datemethod == 2) :?>
              <div class="date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-calendar" aria-hidden="true"></i> -->
                <?php echo $item->date; ?>
              </div>
            <?php endif; ?>

            <?php if ($item->date && $datemethod == 1) :?>
              <div class="date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-calendar" aria-hidden="true"></i> -->
                <?php echo $item->date; ?>
              </div>
            <?php if ($item->time && $datemethod == 1) :?>
              <div class="time" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-clock" aria-hidden="true"></i> -->
                <?php echo $item->time; ?>
              </div>
            <?php endif; ?>
            <?php endif; ?>
          <?php /* when calendar sheet is displayed */ ?>
          <?php else : ?>
            <?php /* if time difference should be displayed */ ?>
            <?php if ($item->date && $datemethod == 2) : ?>
              <div class="date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-calendar" aria-hidden="true"></i> -->
                <?php echo $item->date; ?>
              </div>
            <?php endif; ?>

            <?php /* if date is to be displayed */ ?>
            <?php if ($item->time && $datemethod == 1) :?>
            <?php /* es muss nur noch die Zeit angezeigt werden (da Datum auf Kalenderblatt schon angezeigt) */ ?>
              <div class="time" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <!-- <i class="fa fa-clock" aria-hidden="true"></i> -->
                <?php echo $item->time; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          
          <?php /*venue*/ ?>
          <?php if (($params->get('showvenue', 1) == 1) && (!empty($item->venue))) :?>
            <div class="venue-title" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.strip_tags($item->venue); ?>">
              <!-- <i class="fa fa-map-marker" aria-hidden="true"></i> -->
              <?php if ($item->venuelink) : ?>
                <a href="<?php echo $item->venuelink; ?>"><?php echo $item->venue; ?></a>
              <?php else : ?>
                <?php echo $item->venue; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php /*category*/ ?>
          <?php if (($params->get('showcategory', 1) == 1) && !empty($item->catname)) :?>
            <div class="category" title="<?php echo Text::_('COM_JEM_TABLE_CATEGORY').': '.strip_tags($item->catname); ?>">
              <!-- <i class="fa fa-tag" aria-hidden="true"></i> -->
              <?php echo $item->catname; ?>
            </div>
          <?php endif; ?>
        </div>
    
        <?php if (($showflyer == 1) && !empty($item->eventimage)) : ?>
          <div class="jem-eventimg-banner">
            <?php $class = ($showcalendar == 1) ? 'image-preview' : 'image-preview2'; ?>
            <a href="<?php echo ($flyer_link_type == 2) ? $item->eventlink : $item->eventimageorig; ?>" class="flyermodal" rel="<?php echo $modal;?>"
               title="<?php echo ($flyer_link_type == 2) ? $item->fulltitle : Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?> " data-title="<?php echo $item->title; ?>">
              <img class="<?php echo $class; ?>" src="<?php echo $item->eventimageorig; ?>" alt="<?php echo $item->title; ?>" />
            </a>
          </div>
        <?php endif; ?>

        <?php if ($params->get('showdesc', 1) == 1) :?>
          <div class="desc">
            <?php echo $item->eventdescription; ?> 
          </div>    
          <?php if (isset($item->link)) : ?>
            <div class="jem-readmore-banner">   
              <a href="<?php echo $item->link ?>" title="<?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>">
                <!--<button class="jem-btn btn">-->
                <?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>
                <!--</button>-->
              </a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
        </div>
      </div>

			<?php if (--$i > 0) : /* no hr after last entry */ ?>
        <hr class="jem-hr">
			<?php endif; ?>
		<?php endforeach; ?>
	<?php else : ?>
		<?php echo Text::_('MOD_JEM_BANNER_NO_EVENTS'); ?>
	<?php endif; ?>
	</div>
</div>

<?php if ($showcalendar == 1) :?>
  <script>
    function parseColor(input) {
      return input.split("(")[1].split(")")[0].split(",");
    }
    
    function calculateBrightness(rgb) {
      var o = Math.round(((parseInt(rgb[0]) * 299) + (parseInt(rgb[1]) * 587) + (parseInt(rgb[2]) * 114)) /1000);    
      return o;
    }
    
    var calendars = document.getElementsByClassName('calendar-category');
    if (calendars != undefined) {
      var o = 0;
      var monthbanner = null;
      for (var i = 0; i < calendars.length; i++) {
        o = calculateBrightness(parseColor(calendars[i].style.backgroundColor));
        monthbanner = null;
        for (var j = 0; j < calendars[i].childNodes.length; j++) {
            if (calendars[i].childNodes[j].className == "monthbanner") {
              monthbanner = calendars[i].childNodes[j];
              break;
            }        
        }
        if (monthbanner != null) {
          if (o > 125) {
              monthbanner.style.color = 'rgb(0, 0, 0)';
          } else { 
              monthbanner.style.color = 'rgb(255, 255, 255)';
          }        
        }
      }
    }
  </script>
<?php endif; ?>