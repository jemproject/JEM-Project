<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<div id="jem" class="jem_venues<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		$btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
		?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->params->get('page_heading')); ?>
	</h1>
	<?php endif; ?>

  <style>
    .jem-img {
      flex-basis: <?php echo $this->jemsettings->imagewidth; ?>px;
    }
  </style>
  
	<!--Venue-->
	<?php foreach($this->rows as $row) : ?>
		<div itemscope itemtype="https://schema.org/Place">
			<h2 class="jem">
				<a href="<?php echo $row->linkEventsPublished; ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->venue); ?></span></a>
			</h2>
    
      <div class="jem-row">
        <div class="jem-info">
          <dl class="jem-dl" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
            <?php if (($this->settings->get('global_show_detlinkvenue',1)) && (!empty($row->url))) : ?>
            <dt class="venue_website hasTooltip" data-placement="bottom" data-original-title="<?php echo Text::_('COM_JEM_WEBSITE'); ?>" >
              <?php echo Text::_('COM_JEM_WEBSITE').':'; ?>
            </dt>
            <dd class="venue_website">
              <a href="<?php echo $this->escape($row->url); ?>" target="_blank">
              <?php 
                if (\Joomla\String\StringHelper::strlen($row->url) > 35) {
                  $urlclean = htmlspecialchars(\Joomla\String\StringHelper::substr($row->url, 0 , 35)) . '...';
                } else {
                  $urlclean = htmlspecialchars($row->url);
                }
                echo $urlclean;
              ?>
              </a>
            </dd>
            <?php endif; ?>

            <?php if ($row->city) : ?>
            <dt class="venue_city hasTooltip" data-placement="bottom" data-original-title="<?php echo Text::_('COM_JEM_CITY'); ?>">
              <?php echo Text::_('COM_JEM_CITY').':'; ?>
            </dt>
            <dd class="venue_city" itemprop="addressLocality">
              <?php echo $this->escape($row->city); ?>
            </dd>
            <?php endif; ?>

            <?php if ($row->state) : ?>
            <dt class="venue_state hasTooltip" data-placement="bottom" data-original-title="<?php echo Text::_('COM_JEM_STATE'); ?>">
              <?php echo Text::_('COM_JEM_STATE').':'; ?>
            </dt>
            <dd class="venue_state" itemprop="addressRegion">
              <?php echo $this->escape($row->state); ?>
            </dd>
            <?php endif; ?>

            <?php if ($row->country) : ?>
            <dt class="venue_country hasTooltip" data-placement="bottom" data-original-title="<?php echo Text::_('COM_JEM_COUNTRY'); ?>">
              <?php echo Text::_('COM_JEM_COUNTRY').':'; ?>
            </dt>
            <dd class="venue_country">
              <?php if ($row->country) :
                $countryimg = JemHelperCountries::getCountryFlag($row->country);
                echo $countryimg ? $countryimg : $row->country; 
              endif; ?>
              <meta itemprop="addressCountry" content="<?php echo $row->country; ?>" />
            </dd>
            <?php endif; ?>
          </dl>
        </div>
        
        <!-- FLYER -->
        <div class="jem-img">
          <?php echo JemOutput::flyer( $row, $row->limage, 'venue' ); ?>
        </div> 
      </div>

			<?php /* if ($this->settings->get('global_show_locdescription',1) && $row->locdescription != '' && $row->locdescription != '<br />') : ?>
			<h3 class="description">
				<?php echo Text::_('COM_JEM_VENUE_DESCRIPTION').':'; ?>
			</h3>
			<div class="description" itemprop="description">
				<?php echo $row->locdescription; ?>
			</div>
			<?php else : ?>
			<div class="clr"> </div>
			<?php endif; */?>
      
      <div class="jem-readmore">
        <a href="<?php echo $row->linkEventsPublished; ?>" title="<?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>">
          <button class="buttonfilter btn">
            <?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?>
          </button>
        </a>
      </div>
      
		</div>
    <?php 
    if ($row !== end($this->rows)) :
        echo '<hr class="jem-hr">';
    endif;
    ?>
	<?php endforeach; ?>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<!--copyright-->
	<div class="copyright">
		<?php echo JemOutput::footer( ); ?>
	</div>
</div>
<?php echo JemOutput::lightbox(); ?>