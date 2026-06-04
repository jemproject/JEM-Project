<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

if (!function_exists('jem_myvenues_country_name')) {
    function jem_myvenues_country_name($country)
    {
        $country = trim((string) $country);

        if ($country === '') {
            return '';
        }

        return JemHelperCountries::getCountryName($country) ?: $country;
    }
}

if (!function_exists('jem_myvenues_country_flag')) {
    function jem_myvenues_country_flag($country, $countryName)
    {
        $flagSrc = JemHelperCountries::getIsoFlag((string) $country);

        if (!$flagSrc) {
            return '';
        }

        $alt = htmlspecialchars((string) $countryName, ENT_QUOTES, 'UTF-8');
        $src = htmlspecialchars($flagSrc, ENT_QUOTES, 'UTF-8');

        return '<img src="' . $src . '" alt="' . $alt . '" title="' . $alt . '" class="venue_country_flag jem-myvenues-country-flag" style="width:20px;height:auto;margin-right:6px;vertical-align:middle;" />';
    }
}
?>

<?php if (!$this->params->get('show_page_heading', 1)) :
           /* hide this if page heading is shown */     ?>
<h1 class="componentheading"><?php echo Text::_('COM_JEM_MY_VENUES'); ?></h1>
<?php endif; ?>

<style>
  <?php if (!empty($this->jemsettings->tablewidth)) : ?>
    #jem #adminForm {
      width: <?php echo ($this->jemsettings->tablewidth); ?>;
    }
  <?php endif; ?>

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

  .jem-sort #jem_country,
  #jem .jem-event .jem-event-country {
    <?php if (($this->jemsettings->showstate == 1) && (!empty($this->jemsettings->statewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  #jem.jem_myvenues .jem-myvenues-check {
    flex: 0 1%;
  }

  #jem.jem_myvenues .jem-myvenues-status {
    flex: 0 0 4rem;
    min-width: 4rem;
    text-align: center;
    justify-content: center;
  }
</style>

<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value = dir;
        form.submit(view);
    }
</script>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
  <?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
        <?php if ($this->settings->get('global_show_filter',1)) : ?>
      <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start">
        <div>
          <?php echo '<label for="filter">'.Text::_('COM_JEM_FILTER').'</label>'; ?>
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <?php echo $this->lists['filter']; ?>
          <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8');?>" class="inputbox form-control" onchange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
          <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php if ($this->settings->get('global_display',1)) : ?>
        <div class="jem-row jem-justify-start jem-nowrap">
        <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>&nbsp;
        <?php echo $this->pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
        </div>
        <?php endif; ?>
  <?php endif; ?>

    <div class="jem-sort jem-sort-small">
    <div class="jem-list-row jem-small-list">
      <?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
                <div class="sectiontableheader jem-myvenues-check">
          <input type="checkbox" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
        </div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showlocate == 1) : ?>
        <div id="jem_location" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcity == 1) : ?>
        <div id="jem_city" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showstate == 1) : ?>
        <div id="jem_country" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'l.country', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <div class="jem-myvenues-status" ><?php echo Text::_('JSTATUS'); ?></div>
    </div>
  </div>

    <ul class="eventlist">
        <?php if (count((array)$this->venues) == 0) : ?>
            <li class="jem-event"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></li>
        <?php else :?>
            <?php foreach ($this->venues as $i => $row) : ?>
        <?php if (!empty($row->featured)) :   ?>
          <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx') . ' venue_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
                <?php else : ?>
          <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($i % 2) . $this->params->get('pageclass_sfx') . ' venue_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
                <?php endif; ?>

            <?php if (empty($this->print) && $this->permissions->canPublishVenue) : ?>
            <div class="jem-event-info-small jem-myevents-check" >
              <?php
              if (!empty($row->params) && $row->params->get('access-change', false)) :
                echo HTMLHelper::_('grid.id', $i, $row->id) . '&nbsp;';
              endif;
              ?>
            </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showlocate == 1) : ?>
                <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                  <i class="fa fa-map-marker" aria-hidden="true"></i>
                  <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
                    <?php echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."' itemprop='url'><span itemprop='name'>".$this->escape($row->venue)."</span></a>"; ?>
                  <?php else : ?>
                    <span itemprop="name"><?php echo $this->escape($row->venue); ?></span>
                  <?php endif; ?>
                    <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" hidden>
                      <?php if (!empty($row->street)) : ?><meta itemprop="streetAddress" content="<?php echo $this->escape($row->street); ?>" /><?php endif; ?>
                      <?php if (!empty($row->postalCode)) : ?><meta itemprop="postalCode" content="<?php echo $this->escape($row->postalCode); ?>" /><?php endif; ?>
                      <?php if (!empty($row->city)) : ?><meta itemprop="addressLocality" content="<?php echo $this->escape($row->city); ?>" /><?php endif; ?>
                      <?php if (!empty($row->state)) : ?><meta itemprop="addressRegion" content="<?php echo $this->escape($row->state); ?>" /><?php endif; ?>
                      <?php if (!empty($row->country)) : ?><meta itemprop="addressCountry" content="<?php echo $this->escape($row->country); ?>" /><?php endif; ?>
                    </div>
                    <?php echo JemOutput::publishstateicon($row); ?>
                </div>
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
              <?php $countryName = jem_myvenues_country_name($row->country ?? ''); ?>
              <?php if ($countryName !== '') : ?>
                <div class="jem-event-info-small jem-event-country" title="<?php echo Text::_('COM_JEM_COUNTRY').': '.$this->escape($countryName); ?>">
                  <?php echo jem_myvenues_country_flag($row->country ?? '', $countryName); ?>
                  <?php echo $this->escape($countryName); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-country">-</div>
              <?php endif; ?>
            <?php endif; ?>

                    <div class="jem-event-info-small jem-myvenues-status">
                        <?php // Ensure icon is not clickable if user isn't allowed to change state!
                        $enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
                        echo HTMLHelper::_('jgrid.published', $row->published, $i, 'myvenues.', $enabled);
                        ?>
                    </div>
            </li>

                <?php $i = 1 - $i; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>



    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="task" value="<?php echo $this->task; ?>" />
    <input type="hidden" name="option" value="com_jem" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>



<div class="pagination">
    <?php echo $this->pagination->getPagesLinks(); ?>
</div>
