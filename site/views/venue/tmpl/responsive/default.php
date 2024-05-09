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

?>
<div id="jem" class="jem_venue<?php echo $this->pageclass_sfx;?>" itemscope="itemscope" itemtype="https://schema.org/Place">
	<div class="buttons">
		<?php
		$btn_params = array('id' => $this->venue->slug, 'slug' => $this->venue->slug, 'task' => $this->task, 'print_link' => $this->print_link, 'archive_link' => $this->archive_link);
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
		?>
	</div>

	<?php if ($this->escape($this->params->get('show_page_heading', 1))) : ?>
	<h1 class="componentheading">
		<span itemprop="name"><?php echo $this->escape($this->params->get('page_heading')); ?></span>
		<?php echo JemOutput::editbutton($this->venue, $this->params, NULL, $this->permissions->canEditVenue, 'venue'); ?>
	</h1>
	<?php endif; ?>
  
  <?php if ($this->escape($this->params->get('page_heading')) != $this->escape($this->venue->title)) : ?>
    <?php if ($this->escape($this->params->get('show_page_heading', 1))) : ?>
      <h2 class="jem-venue-title">
        <?php echo $this->escape($this->venue->title);?>
      </h2>
    <?php else : ?>
      <h1 class="jem-venue-title">
        <?php echo $this->escape($this->venue->title);?>
      </h1>
    <?php endif; ?>
	<?php endif; ?>

	<!--Venue-->
	<h2 class="jem">
		<?php /*
		echo Text::_('COM_JEM_VENUE');
		echo JemOutput::editbutton($this->venue, $this->params, NULL, $this->permissions->canEditVenue, 'venue');
		echo JemOutput::copybutton($this->venue, $this->params, NULL, $this->permissions->canAddVenue, 'venue');
		*/?>
	</h2>
  <div class="jem-row">
    <div class="jem-info">
      <?php if ($this->settings->get('global_show_detailsadress',1)) : ?>
        <dl class="jem-dl" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
          <dt class="title hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_TITLE'); ?>"><?php echo Text::_('COM_JEM_TITLE').':'; ?></dt>
          <dd class="title">
            <?php echo $this->venue->title;?>
          </dd>
        
          <?php if (($this->settings->get('global_show_detlinkvenue', 1)) && (!empty($this->venue->url))) : ?>
          <dt class="venue hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_WEBSITE'); ?>"><?php echo Text::_('COM_JEM_WEBSITE'); ?>:</dt>
          <dd class="venue">
            <a href="<?php echo $this->venue->url; ?>" target="_blank"><?php echo $this->venue->urlclean; ?></a>
          </dd>
          <?php endif; ?>
        
          <?php if ($this->venue->street) : ?>
          <dt class="venue_street hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_STREET'); ?>"><?php echo Text::_('COM_JEM_STREET'); ?>:</dt>
          <dd class="venue_street" itemprop="streetAddress">
            <?php echo $this->escape($this->venue->street); ?>
          </dd>
          <?php endif; ?>
    
          <?php if ($this->venue->postalCode) : ?>
          <dt class="venue_postalCode hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_ZIP'); ?>"><?php echo Text::_('COM_JEM_ZIP'); ?>:</dt>
          <dd class="venue_postalCode" itemprop="postalCode">
            <?php echo $this->escape($this->venue->postalCode); ?>
          </dd>
          <?php endif; ?>
    
          <?php if ($this->venue->city) : ?>
          <dt class="venue_city hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_CITY'); ?>"><?php echo Text::_('COM_JEM_CITY'); ?>:</dt>
          <dd class="venue_city" itemprop="addressLocality">
            <?php echo $this->escape($this->venue->city); ?>
          </dd>
          <?php endif; ?>
    
          <?php if ($this->venue->state) : ?>
          <dt class="venue_state hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_STATE'); ?>"><?php echo Text::_('COM_JEM_STATE'); ?>:</dt>
          <dd class="venue_state" itemprop="addressRegion">
            <?php echo $this->escape($this->venue->state); ?>
          </dd>
          <?php endif; ?>
    
          <?php if ($this->venue->country) : ?>
          <dt class="venue_country hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_COUNTRY'); ?>"><?php echo Text::_('COM_JEM_COUNTRY'); ?>:</dt>
          <dd class="venue_country">
            <?php echo $this->venue->countryimg ? $this->venue->countryimg : $this->venue->country; ?>
            <meta itemprop="addressCountry" content="<?php echo $this->venue->country; ?>" />
          </dd>
          <?php endif; ?>
    
          <!-- PUBLISHING STATE -->
          <?php if (isset($this->venue->published) && !empty($this->show_status)) : ?>
          <dt class="published hasTooltip" data-original-title="<?php echo Text::_('JSTATUS'); ?>"><?php echo Text::_('JSTATUS'); ?>:</dt>
          <dd class="published">
            <?php switch ($this->venue->published) {
            case  1: echo Text::_('JPUBLISHED');   break;
            case  0: echo Text::_('JUNPUBLISHED'); break;
            case  2: echo Text::_('JARCHIVED');    break;
            case -2: echo Text::_('JTRASHED');     break;
            } ?>
          </dd>
          <?php endif; ?>
    
          <?php
          for ($cr = 1; $cr <= 10; $cr++) {
            $currentRow = $this->venue->{'custom'.$cr};
            if (preg_match('%^http(s)?://%', $currentRow)) {
              $currentRow = '<a href="' . $this->escape($currentRow) . '" target="_blank">' . $this->escape($currentRow) . '</a>';
            }
            if ($currentRow) {
            ?>
            <dt class="custom<?php echo $cr; ?> hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr); ?>"><?php echo Text::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr); ?>:</dt>
            <dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
            <?php
            }
          }
          ?>
    
          <?php
          if ($this->settings->get('global_show_mapserv') == 1 || $this->settings->get('global_show_mapserv') == 4) {
            echo JemOutput::mapicon($this->venue, null, $this->settings);
          }
          ?>
        </dl>
        <?php
      ?>
    </div>
    
    
    <style>
      .jem-img {
        flex-basis: <?php echo $this->jemsettings->imagewidth; ?>px;
      }
    </style>
    <div class="jem-img">
      <?php echo JemOutput::flyer($this->venue, $this->limage, 'venue'); ?>
    </div>  
  </div>
	<?php elseif (isset($this->venue->published) && !empty($this->show_status)) : ?>
	<!-- PUBLISHING STATE -->
		<dl>
			<dt class="published hasTooltip" data-original-title="<?php echo Text::_('JSTATUS'); ?>"><?php echo Text::_('JSTATUS'); ?>:</dt>
			<dd class="published">
				<?php switch ($this->venue->published) {
				case  1: echo Text::_('JPUBLISHED');   break;
				case  0: echo Text::_('JUNPUBLISHED'); break;
				case  2: echo Text::_('JARCHIVED');    break;
				case -2: echo Text::_('JTRASHED');     break;
				} ?>
			</dd>
		</dl>
	<?php endif; ?>

	<?php
	$global_show_mapserv = $this->settings->get('global_show_mapserv');
	if ($global_show_mapserv == 2 || $global_show_mapserv == 3 || $global_show_mapserv == 5) : ?>
		<div class="jem-map">
			<?php if ($global_show_mapserv == 2 || $global_show_mapserv == 5) : ?>
			<div class="jem-map">
				<?php echo JemOutput::mapicon($this->venue, null, $this->settings); ?>
			</div>
			<?php endif; ?>

			<?php if ($global_show_mapserv == 3) : ?>
				<input type="hidden" id="latitude" value="<?php echo $this->venue->latitude; ?>">
				<input type="hidden" id="longitude" value="<?php echo $this->venue->longitude; ?>">

				<input type="hidden" id="venue" value="<?php echo $this->venue->venue; ?>">
				<input type="hidden" id="street" value="<?php echo $this->venue->street; ?>">
				<input type="hidden" id="city" value="<?php echo $this->venue->city; ?>">
				<input type="hidden" id="state" value="<?php echo $this->venue->state; ?>">
				<input type="hidden" id="postalCode" value="<?php echo $this->venue->postalCode; ?>">
				<?php echo JemOutput::mapicon($this->venue, null, $this->settings); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ($this->settings->get('global_show_locdescription', 1) && $this->venuedescription != '' &&
	          $this->venuedescription != '<br />') : ?>

		<h2 class="description"><?php echo Text::_('COM_JEM_VENUE_DESCRIPTION'); ?></h2>
		<div class="description no_space floattext" itemprop="description">
			<?php echo $this->venuedescription; ?>
		</div>
	<?php endif; ?>

	<?php $this->attachments = $this->venue->attachments; ?>
	<?php echo $this->loadTemplate('attachments'); ?>

	<!--table-->
  <h2 class="jem">
		<?php echo Text::_('COM_JEM_EVENTS'); ?>
	</h2>
	<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm">
		<?php echo $this->loadTemplate('events_table'); ?>

		<p>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="view" value="venue" />
		<input type="hidden" name="id" value="<?php echo $this->venue->id; ?>" />
		</p>
	</form>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<?php echo JemOutput::icalbutton($this->venue->id, 'venue'); ?>

	<!--copyright-->
	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>

<?php echo JemOutput::lightbox(); ?>
