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
use Joomla\CMS\Uri\Uri;

if (!function_exists('jem_frontend_status_label')) {
    function jem_frontend_status_label($state)
    {
        switch ((int) $state) {
            case 1:
                return Text::_('JPUBLISHED');
            case 0:
                return Text::_('JUNPUBLISHED');
            case 2:
                return Text::_('JARCHIVED');
            case -2:
                return Text::_('JTRASHED');
        }

        return Text::_('JSTATUS');
    }
}

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

  #jem .jem-list-row.jem-small-list {
    column-gap: 0;
    row-gap: 0.35rem;
  }

  #jem .jem-list-row.jem-small-list > * {
    box-sizing: border-box;
    min-width: 0;
    padding-right: 0.75rem;
  }

  #jem .jem-list-row.jem-small-list > *:last-child {
    padding-right: 0;
  }

  #jem .jem-status-published .btn-micro span.icon-publish:before,
  #jem .jem-status-published .btn-micro i.icon-publish,
  #jem .jem-status-published .jgrid span.publish {
    background-image: url(<?php echo Uri::root(true); ?>/media/com_jem/images/tick.webp) !important;
    content: url(<?php echo Uri::root(true); ?>/media/com_jem/images/tick.webp) !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .btn-micro span.icon-unpublish:before,
  #jem .jem-status-unpublished .btn-micro i.icon-unpublish:before,
  #jem .jem-status-unpublished .jgrid span.unpublish:before,
  #jem .jem-status-unpublished .icon-unpublish:before {
    background-image: none !important;
    color: #b71c1c !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .btn-micro i.icon-unpublish,
  #jem .jem-status-unpublished .jgrid span.unpublish,
  #jem .jem-status-unpublished .btn-micro span.icon-unpublish {
    background-image: none !important;
    color: #b71c1c !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .btn-micro,
  #jem .jem-status-unpublished .btn-micro *,
  #jem .jem-status-unpublished .jgrid,
  #jem .jem-status-unpublished .jgrid * {
    color: #b71c1c !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .jem-publishstateicon-unpublished,
  #jem .jem-status-unpublished .jem-status-link {
    color: #b71c1c !important;
    opacity: 1 !important;
    text-shadow: none !important;
  }

  #jem .jem-status-trashed .btn-micro span.icon-trash:before,
  #jem .jem-status-trashed .btn-micro i.icon-trash,
  #jem .jem-status-trashed .jgrid span.trash {
    background-image: url(<?php echo Uri::root(true); ?>/media/com_jem/images/icon-16-trash.webp) !important;
    content: url(<?php echo Uri::root(true); ?>/media/com_jem/images/icon-16-trash.webp) !important;
    opacity: 0.55 !important;
  }

  #jem .jem-row-unpublished,
  #jem .jem-row-unpublished a {
    color: #6c757d !important;
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

  .jem-sort #jem_country,
  #jem .jem-event .jem-event-country {
    <?php if (($this->jemsettings->showstate == 1) && (!empty($this->jemsettings->statewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort .jem-myvenues-check,
  #jem.jem_myvenues .jem-myvenues-check {
    display: flex;
    flex: 0 0 auto;
    flex-basis: auto !important;
    width: max-content;
    min-width: 1.5rem;
    max-width: max-content;
    align-items: center;
    justify-content: center;
    overflow: visible;
  }

  .jem-sort .jem-myvenues-status,
  #jem.jem_myvenues .jem-myvenues-status {
    display: flex;
    flex: 0 0 auto;
    flex-basis: auto !important;
    width: max-content;
    min-width: max-content;
    max-width: max-content;
    margin-left: auto;
    align-items: center;
    text-align: center;
    justify-content: center;
    overflow: visible;
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
          <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
        </div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showlocate == 1) : ?>
        <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcity == 1) : ?>
        <div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showstate == 1) : ?>
        <div id="jem_country" class="sectiontableheader"><i class="fa fa-globe" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'l.country', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <div class="jem-myvenues-status" title="<?php echo Text::_('JSTATUS'); ?>" aria-label="<?php echo Text::_('JSTATUS'); ?>">
        <span class="fa fa-check-circle" aria-hidden="true"></span>&nbsp;<?php echo Text::_('JSTATUS'); ?>
      </div>
    </div>
  </div>

    <ul class="eventlist">
        <?php if (count((array)$this->venues) == 0) : ?>
            <li class="jem-event"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></li>
        <?php else :?>
            <?php foreach ($this->venues as $i => $row) : ?>
        <?php if (!empty($row->featured)) :   ?>
          <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx') . ' venue_id' . $this->escape($row->id); ?><?php echo ((int) $row->published === 0) ? ' jem-row-unpublished' : ''; ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
                <?php else : ?>
          <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($i % 2) . $this->params->get('pageclass_sfx') . ' venue_id' . $this->escape($row->id); ?><?php echo ((int) $row->published === 0) ? ' jem-row-unpublished' : ''; ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
                <?php endif; ?>

            <?php if (empty($this->print) && $this->permissions->canPublishVenue) : ?>
            <div class="jem-event-info-small jem-myvenues-check" >
              <?php
              if (!empty($row->params) && $row->params->get('access-change', false)) :
                echo HTMLHelper::_('grid.id', $i, $row->id) . '&nbsp;';
              endif;
              ?>
            </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showlocate == 1) : ?>
                <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
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
                </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showcity == 1) : ?>
              <?php if (!empty($row->city)) : ?>
                <div class="jem-event-info-small jem-event-city" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                  <?php echo $this->escape($row->city); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-city">-</div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showstate == 1) : ?>
              <?php $countryName = jem_myvenues_country_name($row->country ?? ''); ?>
              <?php if ($countryName !== '') : ?>
                <div class="jem-event-info-small jem-event-country" title="<?php echo Text::_('COM_JEM_COUNTRY').': '.$this->escape($countryName); ?>">
                  <?php echo $this->escape($countryName); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-country">-</div>
              <?php endif; ?>
            <?php endif; ?>

                    <div class="jem-event-info-small jem-myvenues-status jem-status-<?php echo ((int) $row->published === 1) ? 'published' : (((int) $row->published === -2) ? 'trashed' : 'unpublished'); ?>" title="<?php echo Text::_('JSTATUS') . ': ' . jem_frontend_status_label($row->published); ?>" aria-label="<?php echo Text::_('JSTATUS') . ': ' . jem_frontend_status_label($row->published); ?>">
                        <?php
                        $enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
                        $statusIcon = JemOutput::publishstateicon($row, array(), false, false);
                        if ($enabled && ((int) $row->published >= 0)) :
                            $statusTask = ((int) $row->published === 1) ? 'unpublish' : 'publish';
                            ?>
                            <a href="javascript:void(0);" onclick="return listItemTask('cb<?php echo $i; ?>','myvenues.<?php echo $statusTask; ?>')" class="jem-status-link">
                                <?php echo $statusIcon; ?>
                            </a>
                        <?php else :
                            echo $statusIcon;
                        endif;
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
