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
</script>
<style>

  .jem-sort #jem_city,
  #jem .jem-event .jem-event-city {
      flex: 1 <?php echo ($this->jemsettings->citywidth); ?>;
  }

  .jem-sort #jem_state,
  #jem .jem-event .jem-event-state {
    <?php if (($this->jemsettings->showstate == 1) && (!empty($this->jemsettings->statewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_location,
  #jem .jem-event .jem-event-venue {
      flex: 1 <?php echo ($this->jemsettings->locationwidth); ?>;
  }

</style>


<?php
function jem_common_show_filter(&$obj) {
  if ($obj->settings->get('global_show_filter',1) && !JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-hidefilter')) {
    return true;
  }
  if (JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-showfilter')) {
    return true;
  }
  return false;
}
?>
<?php if (jem_common_show_filter($this) && !JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')): ?>
  <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start">
    <div>
      <?php echo '<label for="filter">'.Text::_('COM_JEM_FILTER').'</label>'; ?>
    </div>
    <div class="jem-row jem-justify-start jem-nowrap">
      <?php echo $this->lists['filter']; ?>
      <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
    </div>
    <div class="jem-row jem-justify-start jem-nowrap">
      <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
      <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button> 
    </div>
	  	<?php if ($this->settings->get('global_display',1)) : ?>
	<div class="jem-limit-smallist">
		   
		<label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
		<?php echo $this->pagination->getLimitBox(); ?>
		
	</div>
	<?php endif; ?>
  </div>

<?php endif; ?>

<div class="jem-sort jem-sort-small">
    <div class="jem-list-row jem-small-list">
		<div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'a.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>

		<?php if ($this->params->get('showstate')) : ?>			
        <div id="jem_state" class="sectiontableheader"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'a.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
		<?php endif; ?>

        <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'a.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    </div>    
</div>

<ul class="eventlist">
  <?php if ($this->novenues == 1) : ?>
    <li class="jem-event"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></li>
  <?php else : ?>
      <?php
      // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
        // This detects the browser and just writes the onclick attribute if the browser is not Safari.
      $isSafari = false;
      if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
        $isSafari = true;
      }
      ?>
			<?php $this->rows = $this->getRows(); ?>
			<?php foreach ($this->rows as $row) : ?>
				<?php if (!empty($row->featured)) :   ?>
		          <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event"  >
				<?php else : ?>
					<li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($row->odd +1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event"  >
				<?php endif; ?>  
  																 
                <?php if (!empty($row->city)) : ?>
                  <div class="jem-event-info-small jem-event-city venue-big" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                    <?php echo $this->escape($row->city); ?>
                  </div>
                <?php else : ?>
                  <div class="jem-event-info-small jem-event-city">-</div>
                <?php endif; ?>
		
				<?php if ($this->params->get('showstate')) : ?>	
					<?php if (!empty($row->state)) : ?>
					<div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
						<?php echo $this->escape($row->state); ?>
					</div>
					<?php else : ?>
					<div class="jem-event-info-small jem-event-state">-</div>
					<?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($row->locid)) : ?>
                  <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                    <i class="fa fa-map-marker" aria-hidden="true"></i>
  						<?php
						if ($this->jemsettings->showlinkvenue == 1) :
							echo $row->id != 0 ? "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>" : '-';
						else :
							echo $row->id ? $this->escape($row->venue) : '-';
						 endif; ?> 
					</div>			
                <?php else : ?>
                  <div class="jem-event-info-small jem-event-venue">
                    <i class="fa fa-map-marker" aria-hidden="true"></i>
					  	<?php
						if ($this->jemsettings->showlinkvenue == 1) :
							echo $row->id != 0 ? "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>" : '-';
						else :
							echo $row->id ? $this->escape($row->venue) : '-';
						 endif; ?> 										 
                  </div>
                <?php endif; ?> 			  
				 

              <meta itemprop="name" content="<?php echo $this->escape($row->venue); ?>" />
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

<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>