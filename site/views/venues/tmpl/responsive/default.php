<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<div id="jem" class="jem_venues<?php echo $this->pageclass_sfx; ?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link, 'pdf_link' => $this->pdf_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
    <h1 class='componentheading'>
        <?php echo $this->escape($this->params->get('page_heading')); ?>
    </h1>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <?php if ($this->show_venue_event_filter) : ?>
        <form action="<?php echo Route::_('index.php'); ?>" method="get" class="jem-form mb-3">
            <label class="jem-row jem-align-center jem-nowrap">
                <input type="hidden" name="show_all_venues" value="0">
                <input type="checkbox" name="show_all_venues" value="1"<?php echo $this->show_all_venues ? ' checked' : ''; ?> onchange="this.form.submit();">
                <?php echo Text::_('COM_JEM_SHOW_ALL_VENUES'); ?>
            </label>
            <input type="hidden" name="option" value="com_jem">
            <input type="hidden" name="view" value="venues">
            <?php if ($this->task) : ?><input type="hidden" name="task" value="<?php echo $this->escape($this->task); ?>"><?php endif; ?>
            <?php if ($this->item) : ?><input type="hidden" name="Itemid" value="<?php echo (int) $this->item->id; ?>"><?php endif; ?>
        </form>
    <?php endif; ?>

  <style>
    .jem-img {
      flex-basis: <?php echo $this->jemsettings->imagewidth; ?>px;
    }
  </style>

    <!--Venue-->
    <?php foreach($this->rows as $row) : ?>
            <?php

            // has user access
            $venueaccess = '';
            if (!$row->user_has_access_venue) {
                // show a closed lock icon
                $venueaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
            } ?>
            <div itemscope itemtype="https://schema.org/Place" class="venue_id<?php echo $this->escape($row->locid); ?>">
                <h2 class="jem">
                    <?php if (!empty($row->parent_venue_id)) : ?><span class="gi" aria-hidden="true">|&mdash;</span><?php endif; ?>
                    <a href="<?php echo $row->linkEventsPublished; ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->venue); ?></span></a>
                    <?php echo JemOutput::publishstateicon($row); ?>
                    <?php echo $venueaccess;?>
                </h2>

                <?php if ($row->user_has_access_venue) : ?>
                    <div class="jem-row">
        <div class="jem-info">
          <dl class="jem-dl" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
            <?php if (!empty($row->parent_venue_id)) : ?>
            <dt class="venue_parent_id hasTooltip" data-placement="bottom" data-original-title="<?php echo Text::_('COM_JEM_PARENT_VENUE_ID'); ?>">
              <?php echo Text::_('COM_JEM_PARENT_VENUE_ID').':'; ?>
            </dt>
            <dd class="venue_parent_id">
              <?php echo (int) $row->parent_venue_id; ?>
              <?php if (!empty($row->parent_venue_name)) : ?>
                <span class="text-muted">(<?php echo $this->escape($row->parent_venue_name); ?>)</span>
              <?php endif; ?>
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
                echo $countryimg ? $countryimg : $this->escape($row->country);
              endif; ?>
              <meta itemprop="addressCountry" content="<?php echo $this->escape($row->country); ?>" />
            </dd>
            <?php endif; ?>
          </dl>
        </div>

        <!-- FLYER -->
        <div class="jem-img">
          <?php echo JemOutput::flyer( $row, $row->limage, 'venue' ); ?>
        </div>
      </div>

            <?php /* if ($this->settings->get('global_show_locdescription',1) && $row->locdescription != '' && $row->locdescription != '<br>') : ?>
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
                <?php endif; ?>
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
        <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php echo JemOutput::footer( ); ?>
    </div>
</div>
<?php echo JemOutput::lightbox(); ?>
