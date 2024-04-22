<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;

$uri = Uri::getInstance();
if (empty($this->catrow->events)) { return; }
?>

<style>
 <?php
 $imagewidth = 'inherit';
 if ($this->jemsettings->imagewidth != 0) {
  $imagewidth = $this->jemsettings->imagewidth / 2; 
  $imagewidth = $imagewidth.'px';
 }
 $imagewidthstring = 'jem-imagewidth';
 if (JemHelper::jemStringContains($this->params->get('pageclass_sfx'), $imagewidthstring)) {
   $pageclass_sfx = $this->params->get('pageclass_sfx');
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
 if (JemHelper::jemStringContains($this->params->get('pageclass_sfx'), $imageheigthstring)) {
   $pageclass_sfx = $this->params->get('pageclass_sfx');
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

  #jem .jem-list-img {
    width: <?php echo $imagewidth; ?>;
  }
  
  #jem .jem-list-img img {
    width: <?php echo $imagewidth; ?>;
    height: <?php echo $imageheight; ?>;
  }
  
  @media not print {
    @media only all and (max-width: 47.938rem) {    
      #jem .jem-list-img {
        width: 100%;
      }
      
      #jem .jem-list-img img {
        width: <?php echo $imagewidth; ?>;
        height: <?php echo $imageheight; ?>;
      }
    }
  }
</style>

<ul class="eventlist">
  <?php if (empty($this->catrow->events)) : ?>
    <li class="jem-event"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></li>
  <?php else : ?>
      <?php
      // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
      // This detects the browser and just writes the onclick attribute if the broswer is not Safari.
      $isSafari = false;
      if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
        $isSafari = true;
      }
      ?>
			<?php foreach ($this->catrow->events as $row) : ?>
        <?php if (!empty($row->featured)) :   ?>
          <li class="jem-event jem-row jem-justify-start jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari) && ($this->jemsettings->gddisabled == 0)) : echo 'onclick=location.href="'.Route::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
				<?php else : ?>
              <?php $odd = 0; ?>
          <li class="jem-event jem-row jem-justify-start jem-odd<?php echo ($odd +1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari) && ($this->jemsettings->gddisabled == 0)) : echo 'onclick=location.href="'.Route::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
				<?php endif; ?>
          
          <?php if (($this->jemsettings->showeventimage == 1) && (!empty($row->datimage))): ?>
            <div headers="jem_eventimage" class="jem-list-img" >
              <?php
              $dimage = JemImage::flyercreator($row->datimage, 'event');
              echo JemOutput::flyer($row, $dimage, 'event');
              ?>
            </div>
          <?php endif; ?>
          <div class="jem-event-details" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari) && ($this->jemsettings->gddisabled == 1)) : echo 'onclick=location.href="'.Route::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
            <?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : // Display title as title of jem-event with link ?>
            <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
              <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo $this->escape($row->title); ?></a>
              <?php echo JemOutput::recurrenceicon($row); ?>
              <?php echo JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4>
            
            <?php elseif (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : // Display title as title of jem-event without link ?>
            <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
              <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4> 
            
            <?php elseif (($this->jemsettings->showtitle == 0) && ($this->jemsettings->showdetails == 1)) : // Display date as title of jem-event with link ?>
            <h4>
              <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>">
              <?php
                echo JemOutput::formatShortDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes);
              ?>
              </a>
              <?php echo JemOutput::recurrenceicon($row); ?>
              <?php echo JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4>  
              
            <?php else : // Display date as title of jem-event without link ?>
            <h4>
              <?php
                echo JemOutput::formatShortDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes);
              ?>
              <?php echo JemOutput::recurrenceicon($row); ?>
              <?php echo JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4>  
            <?php endif; ?>
            
            <?php // Display other information below in a row ?>
            <div class="jem-list-row">  
              <?php if ($this->jemsettings->showtitle == 1) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>" >
                  <i class="far fa-clock" aria-hidden="true"></i>
                  <?php
                    echo JemOutput::formatShortDateTime($row->dates, $row->times,
                      $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                    echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times,
                      $row->enddates, $row->endtimes);
                  ?>
                </div>
              <?php endif; ?>
              
              <?php if ($this->jemsettings->showtitle == 0) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
                  <i class="fa fa-comment" aria-hidden="true"></i>
                  <?php echo $this->escape($row->title); ?>
                </div>
              <?php endif; ?>
              
              <?php if (($this->jemsettings->showlocate == 1) && (!empty($row->locid))) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                  <i class="fa fa-map-marker" aria-hidden="true"></i>
                  <?php if ($this->jemsettings->showlinkvenue == 1) : ?>
                    <?php echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>"; ?>
                  <?php else : ?>
                    <?php echo $this->escape($row->venue); ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (($this->jemsettings->showcity == 1) && (!empty($row->city))) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                  <i class="fa fa-building" aria-hidden="true"></i>
                  <?php echo $this->escape($row->city); ?>
                </div>
              <?php endif; ?>
              
              <?php if (($this->jemsettings->showstate == 1) && (!empty($row->state))): ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                  <i class="fa fa-map" aria-hidden="true"></i>
                  <?php echo $this->escape($row->state); ?>
                </div>
              <?php endif; ?>
              
              <?php if ($this->jemsettings->showcat == 1) : ?>
                <div class="jem-event-info" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))); ?>">
                  <i class="fa fa-tag" aria-hidden="true"></i>
                  <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
                </div>
              <?php endif; ?>
              
              <?php if (($this->jemsettings->showatte == 1) && (!empty($row->regCount))) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_ATTENDEES').': '.$this->escape($row->regCount); ?>">
                  <i class="fa fa-user" aria-hidden="true"></i>
                  <?php echo $this->escape($row->regCount); ?>
                </div>
              <?php endif; ?>
            </div>            
          </div>
          
          <meta itemprop="name" content="<?php echo $this->escape($row->title); ?>" />
          <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
          <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
          <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;" >
            <?php if (!empty($row->locid)) : ?>
              <meta itemprop="name" content="<?php echo $this->escape($row->venue); ?>" />
            <?php else : ?>
              <meta itemprop="name" content="None" />
            <?php endif; ?>
            <?php
            $microadress = '';
            if (!empty($row->city)) {
              $microadress .= $this->escape($row->city);
            }
            if (!empty($microadress)) {
              $microadress .= ', ';
            }                
            if (!empty($row->state)) {
              $microadress .= $this->escape($row->state);
            }
            if (empty($microadress)) {
              $microadress .= '-';
            }
            ?>
            <meta itemprop="address" content="<?php echo $microadress; ?>" />
          </div>
        
        </li>
			<?php endforeach; ?>
  <?php endif; ?>
</ul>
<?php echo JemOutput::lightbox(); ?>