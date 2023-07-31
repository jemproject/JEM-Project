<?php
/**
 * @version 4.0.1-dev1
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

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

<style>
  <?php if (!empty($this->jemsettings->tablewidth)) : ?>
    #jem #adminForm {
      width: <?php echo ($this->jemsettings->tablewidth); ?>;
    }
  <?php endif; ?>

  .jem-sort #jem_date,
  #jem .jem-event .jem-event-date {
    <?php if (!empty($this->jemsettings->datewidth)) : ?>
      flex: 1 <?php echo ($this->jemsettings->datewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_title,
  #jem .jem-event .jem-event-title {
    <?php if (($this->jemsettings->showtitle == 1) && (!empty($this->jemsettings->titlewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->titlewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_location,
  #jem .jem-event .jem-event-venue {
    <?php if (($this->jemsettings->showlocate == 1) && (!empty($this->jemsettings->locationwidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->locationwidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_city,
  #jem .jem-event .jem-event-city {
    <?php if (($this->jemsettings->showcity == 1) && (!empty($this->jemsettings->citywidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->citywidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_state,
  #jem .jem-event .jem-event-state {
    <?php if (($this->jemsettings->showstate == 1) && (!empty($this->jemsettings->statewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_category,
  #jem .jem-event .jem-event-category {
    <?php if (($this->jemsettings->showcat == 1) && (!empty($this->jemsettings->catfrowidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->catfrowidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_atte,
  #jem .jem-event .jem-event-attendees {
    <?php if (($this->jemsettings->showatte == 1) && (!empty($this->jemsettings->attewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->attewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }
</style>
<div id="jem_filter" class="floattext">		
<dl class="jem-dl">
  <dt>
    <label for="filter_type"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
  </dt>
  <dd>
    <?php echo  $this->lists['filter_types']; ?>
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
    <div class="jem-row jem-nowrap jem-justify-start"><?php echo text::_('COM_JEM_SEARCH_FROM'); ?>&nbsp;<?php echo $this->lists['date_from'];?></div>
    <div class="jem-row jem-nowrap jem-justify-start"><?php echo text::_('COM_JEM_SEARCH_TO'); ?>&nbsp;<?php echo $this->lists['date_to'];?></div>
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
  <div class="jem-limit-smallist">
    <?php
      echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
      //echo '<span class="jem-limit-text">'.Text::_('COM_JEM_DISPLAY_NUM').'</span>&nbsp;';
      echo $this->pagination->getLimitBox();
    ?>
  </div>
<?php endif; ?>
  </div>
<div class="jem-sort jem-sort-small">
  <div class="jem-list-row jem-small-list">
    <div id="jem_date" class="sectiontableheader"><i class="far fa-clock" aria-hidden="true"></i>&nbsp;<?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php if ($this->jemsettings->showtitle == 1) : ?>              
      <div id="jem_title" class="sectiontableheader"><i class="fa fa-comment" aria-hidden="true"></i>&nbsp;<?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?> 
    <?php if ($this->jemsettings->showlocate == 1) : ?>
      <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showcity == 1) : ?>
      <div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showstate == 1) : ?>
      <div id="jem_state" class="sectiontableheader"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showcat == 1) : ?>
      <div id="jem_category" class="sectiontableheader"><i class="fa fa-tag" aria-hidden="true"></i>&nbsp;<?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?> 
  </div>    
</div>

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
        <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
      <?php else : ?>
        <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($row->odd +1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event"  >
      <?php endif; ?>
                    
            <div class="jem-event-info-small jem-event-date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari)) : echo 'onclick=location.href="'.JRoute::_(JemHelperRoute::getEventRoute($row->slug)).'"'; endif; ?>>
              <i class="far fa-clock" aria-hidden="true"></i>
              <?php
                echo JemOutput::formatShortDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes);
              ?>
               <?php if ($this->jemsettings->showtitle == 0) : ?>
                <?php echo JemOutput::recurrenceicon($row); ?>
                <?php echo JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured)) :?>
                  <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
               <?php endif; ?>
            </div>
            
            <?php if ($this->jemsettings->showtitle == 1) : ?>
              <div class="jem-event-info-small jem-event-title" title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
                <i class="fa fa-comment" aria-hidden="true"></i>
                <a href="<?php echo JRoute::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo $this->escape($row->title); ?></a>
                <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured)) :?>
                  <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            
            <?php if ($this->jemsettings->showlocate == 1) : ?>
              <?php if (!empty($row->venue)) : ?>
                <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                  <i class="fa fa-map-marker" aria-hidden="true"></i>
                  <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
                    <?php echo "<a href='".JRoute::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>"; ?>
                  <?php else : ?>
                    <?php echo $this->escape($row->venue); ?>
                  <?php endif; ?>                  
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-venue">
                  <i class="fa fa-map-marker" aria-hidden="true"></i> -
                </div>
              <?php endif; ?>                
            <?php endif; ?>

            <?php if ($this->jemsettings->showcity == 1) : ?>
              <?php if (!empty($row->city)) : ?>
                <div class="jem-event-info-small jem-event-city" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                  <i class="fa fa-building" aria-hidden="true"></i>
                  <?php echo $this->escape($row->city); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-city"><i class="fa fa-building" aria-hidden="true"></i> -</div>
              <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($this->jemsettings->showstate == 1) : ?>
              <?php if (!empty($row->state)) : ?>
                <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                  <i class="fa fa-map" aria-hidden="true"></i>
                  <?php echo $this->escape($row->state); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-state"><i class="fa fa-map" aria-hidden="true"></i> -</div>
              <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($this->jemsettings->showcat == 1) : ?>
              <div class="jem-event-info-small jem-event-category" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))); ?>">
                <i class="fa fa-tag" aria-hidden="true"></i>
                <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
              </div>
            <?php endif; ?>
            
            <meta itemprop="name" content="<?php echo $this->escape($row->title); ?>" />
            <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').JRoute::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
            <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').JRoute::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
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


