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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$uri = Uri::getInstance();
?>

<script type="text/javascript">
	function tableOrdering(order, dir, view)
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit(view);
	}
  
  function clearForm() {
    var node = null;
    node = document.getElementById('filter_type');
    if (node != null) {
      node.value='title';
    }
    node = null;
    node = document.getElementById('filter_search');
    if (node != null) {
      node.value='';
    }
    node = null;
    node = document.getElementById('filter_category');
    if (node != null) {
      node.value='1';
    }
    node = null;
    node = document.getElementById('filter_date_from');
    if (node != null) {
      node.value='';
    }
    node = null;
    node = document.getElementById('filter_date_to');
    if (node != null) {
      node.value='';
    }
    node = null;
    node = document.getElementById('filter_continent');
    if (node != null) {
      node.value='';
    }
    node = null;
    node = document.getElementById('filter_country');
    if (node != null) {
      node.value='';
    }
    node = null;
    node = document.getElementById('filter_city');
    if (node != null) {
      node.value='';
    }
    node = null;
    return;
  }
</script>
<div id="jem_filter" class="floattext">			
<dl class="jem-dl">
  <dt>
    <label for="filter_type"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
  </dt>
  <dd class="jem-row jem-justify-start">
    <?php echo  $this->lists['filter_types'].'&nbsp;'; ?>
    <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['filter'];?>" class="inputbox" onchange="document.getElementById('adminForm').submit();" />
  </dd>
  <dt>
    <?php echo '<label for="category">'.Text::_('COM_JEM_CATEGORY').'</label>'; ?>
  </dt>
  <dd>
    <?php echo $this->lists['categories']; ?>
  </dd>
  <dt>
    <?php echo '<label for="date">'.Text::_('COM_JEM_SEARCH_DATE').'</label>'; ?>
  </dt>
  <dd>
    <div class="jem-row jem-nowrap jem-justify-start jem-date"><?php echo Text::_('COM_JEM_SEARCH_FROM'); ?>&nbsp;<?php echo $this->lists['date_from'];?></div>
    <div class="jem-row jem-nowrap jem-justify-start jem-date"><?php echo text::_('COM_JEM_SEARCH_TO'); ?>&nbsp;<?php echo $this->lists['date_to'];?></div>
  </dd>
  <dt>
    <?php echo '<label for="continent">'.Text::_('COM_JEM_CONTINENT').'</label>'; ?>
  </dt>
  <dd>
    <?php echo $this->lists['continents'];?>
  </dd>
  <?php if ($this->filter_continent): ?>
    <dt>
      <?php echo '<label for="country">'.Text::_('COM_JEM_COUNTRY').'</label>'; ?>
    </dt>
    <dd>
      <?php echo $this->lists['countries'];?>
    </dd>
  <?php endif; ?>
  <?php if ($this->filter_continent && $this->filter_country): ?>
    <dt>
      <?php echo '<label for="city">'.Text::_('COM_JEM_CITY').'</label>';?>
    </dt>
    <dd>
      <?php echo $this->lists['cities'];?>
    </dd>
  <?php endif; ?>
  <dt></dt>
  <dd>
    <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
    <button class="btn btn-secondary" type="button" onclick="clearForm();this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
  </dd>
</dl>
  <?php if ($this->settings->get('global_display',1)) : ?>
    <div class="jem_limit jem-row">
      <?php
        echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
        echo $this->pagination->getLimitBox();
      ?>
    </div>
  <?php endif; ?>
</div>
<div class="jem-misc jem-row">
  <div class="jem-sort jem-row jem-justify-start jem-nowrap">
    <i class="fa fa-sort fa-lg jem-sort-icon" aria-hidden="true"></i>
    <div class="jem-row jem-justify-start jem-sort-parts">
      <div id="jem_date" class="sectiontableheader"><i class="far fa-clock" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <div id="jem_title" class="sectiontableheader"><i class="fa fa-comment" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php if ($this->jemsettings->showlocate == 1) : ?>
        <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcity == 1) : ?>
        <div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showstate == 1) : ?>
        <div id="jem_state" class="sectiontableheader"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcat == 1) : ?>
        <div id="jem_category" class="sectiontableheader"><i class="fa fa-tag" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?> 
      <?php if ($this->jemsettings->showatte == 1) : ?>
				<div id="jem_atte" class="sectiontableheader"><i class="fa fa-user" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_ATTENDEES'); ?></div>
      <?php endif; ?>
    </div>    
  </div>

</div>

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
  <?php if ($this->noevents == 1) : ?>
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
			<?php $this->rows = $this->getRows(); ?>
			<?php foreach ($this->rows as $row) : ?>
        <?php if (!empty($row->featured)) :   ?>
          <li class="jem-event jem-row jem-justify-start jem-nowrap jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari) && ($this->jemsettings->gddisabled == 0)) : echo 'onclick=location.href="'.Route::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
				<?php else : ?>
          <li class="jem-event jem-row jem-justify-start jem-nowrap jem-odd<?php echo ($row->odd +1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari) && ($this->jemsettings->gddisabled == 0)) : echo 'onclick=location.href="'.Route::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
				<?php endif; ?>
        
          <div class="jem-event-details" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari) && ($this->jemsettings->gddisabled == 1)) : echo 'onclick=location.href="'.Route::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
            <?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : // Display title as title of jem-event with link ?>
            <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
              <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" ><?php echo $this->escape($row->title); ?></a>
              <?php echo JemOutput::recurrenceicon($row); ?>
              <?php echo JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4>
            
            <?php elseif (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : //Display title as title of jem-event without link ?>
            <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
              <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </h4> 
            
            <?php elseif (($this->jemsettings->showtitle == 0) && ($this->jemsettings->showdetails == 1)) : // Display date as title of jem-event with link ?>
            <h4>
              <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" >
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
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>">
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
                  <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                </div>
              <?php endif; ?>
              
              <?php if (($this->jemsettings->showlocate == 1) && (!empty($row->venue))) : ?>
                <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                  <i class="fa fa-map-marker" aria-hidden="true"></i>
                  <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
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
