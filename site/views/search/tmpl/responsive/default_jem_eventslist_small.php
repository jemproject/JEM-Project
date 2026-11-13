<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$uri = Uri::getInstance();
$flagBase = Uri::root(true) . '/media/com_jem/images/flags/w80-webp/';
?>

<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value    = dir;
        form.submit(view);
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
<div id="jem_filter" class="jem-search-filter mb-3" data-default-limit="<?php echo (int) $this->jemsettings->display_num; ?>">
    <h5 class="jem-search-filter-heading"><?php echo Text::_('COM_JEM_SEARCH_EVENTS_HEADING'); ?></h5>

    <!-- KEYWORDS + DATE RANGE -->
    <div class="jem-search-filter-grid jem-search-filter-primary">
        <div>
            <label class="jem-filter-label" for="filter_search"><?php echo Text::_('COM_JEM_SEARCH_KEYWORDS'); ?></label>
            <div class="input-group">
                <?php echo $this->lists['filter_types']; ?>
                <input type="text" name="filter_search" id="filter_search"
                       value="<?php echo htmlspecialchars($this->lists['filter'], ENT_QUOTES, 'UTF-8'); ?>"
                       class="form-control"
                       placeholder="<?php echo Text::_('COM_JEM_SEARCH_TYPE_TO_SEARCH'); ?>"
                       onchange="jem_search_submit(this.form);" />
            </div>
        </div>
        <?php if ($this->filter_show_dates): ?>
        <fieldset class="jem-search-date-range">
            <legend class="jem-filter-label"><?php echo Text::_('COM_JEM_SEARCH_DATE_RANGE'); ?></legend>
            <div class="jem-search-filter-grid jem-search-filter-grid-two">
                <?php if ($this->filter_show_date_from): ?>
                <input type="date" name="filter_date_from" id="filter_date_from"
                       class="form-control" aria-label="<?php echo Text::_('COM_JEM_SEARCH_FROM'); ?>"
                       value="<?php echo htmlspecialchars($this->filter_date_from, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <?php if ($this->filter_show_date_to): ?>
                <input type="date" name="filter_date_to" id="filter_date_to"
                       class="form-control" aria-label="<?php echo Text::_('COM_JEM_SEARCH_TO'); ?>"
                       value="<?php echo htmlspecialchars($this->filter_date_to, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
            </div>
        </fieldset>
        <?php endif; ?>
    </div>

    <!-- CATEGORY / VENUES / EVENT TYPE -->
    <?php if ($this->filter_show_category || $this->filter_show_venue || $this->filter_show_eventtype): ?>
    <div class="jem-search-filter-grid jem-search-filter-selects">
        <?php if ($this->filter_show_category): ?>
        <div>
            <label class="jem-filter-label" for="filter_category"><?php echo Text::_('COM_JEM_CATEGORY'); ?></label>
            <?php echo $this->lists['categories']; ?>
        </div>
        <?php endif; ?>
        <?php if ($this->filter_show_venue): ?>
        <div>
            <label class="jem-filter-label" for="filter_venue_id"><?php echo Text::_('COM_JEM_SEARCH_VENUES'); ?></label>
            <?php echo $this->lists['venues']; ?>
        </div>
        <?php endif; ?>
        <?php if ($this->filter_show_eventtype): ?>
        <div>
            <label class="jem-filter-label" for="filter_type_id"><?php echo Text::_('COM_JEM_SEARCH_EVENT_TYPE'); ?></label>
            <?php echo $this->lists['event_types']; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- LOCATION + RESPONSIVE ACTION ROW -->
    <div class="jem-search-filter-grid jem-search-bottom<?php echo $this->filter_show_continent && $this->filter_show_country && $this->filter_show_state && $this->filter_show_city ? ' jem-search-bottom-four' : ''; ?>"
         style="--jem-location-count: <?php echo max(1, (int) $this->filter_show_continent + (int) $this->filter_show_country + (int) $this->filter_show_state + (int) $this->filter_show_city); ?>">
        <?php if ($this->filter_show_continent): ?>
        <div>
            <label class="jem-filter-label" for="filter_continent"><?php echo Text::_('COM_JEM_CONTINENT'); ?></label>
            <?php echo $this->lists['continents']; ?>
        </div>
        <?php endif; ?>
        <?php if ($this->filter_show_country): ?>
        <?php $locationHint = $this->location_filter_hints['filter_country'] ?? ''; ?>
        <div class="jem-search-location-field<?php echo $locationHint !== '' ? ' is-disabled' : ''; ?>">
            <div class="jem-search-location-label">
                <label class="jem-filter-label" for="filter_country"><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
                <?php if ($locationHint !== ''): ?>
                <span id="filter_country_hint" class="jem-search-location-hint" tabindex="0" role="img"
                      title="<?php echo htmlspecialchars($locationHint, ENT_QUOTES, 'UTF-8'); ?>"
                      aria-label="<?php echo htmlspecialchars($locationHint, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa fa-ban" aria-hidden="true"></i>
                </span>
                <?php endif; ?>
            </div>
            <?php echo $this->lists['countries']; ?>
        </div>
        <?php endif; ?>
        <?php if ($this->filter_show_state): ?>
        <?php $locationHint = $this->location_filter_hints['filter_state'] ?? ''; ?>
        <div class="jem-search-location-field<?php echo $locationHint !== '' ? ' is-disabled' : ''; ?>">
            <div class="jem-search-location-label">
                <label class="jem-filter-label" for="filter_state"><?php echo Text::_('COM_JEM_TABLE_STATE'); ?></label>
                <?php if ($locationHint !== ''): ?>
                <span id="filter_state_hint" class="jem-search-location-hint" tabindex="0" role="img"
                      title="<?php echo htmlspecialchars($locationHint, ENT_QUOTES, 'UTF-8'); ?>"
                      aria-label="<?php echo htmlspecialchars($locationHint, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa fa-ban" aria-hidden="true"></i>
                </span>
                <?php endif; ?>
            </div>
            <?php echo $this->lists['states']; ?>
        </div>
        <?php endif; ?>
        <?php if ($this->filter_show_city): ?>
        <?php $locationHint = $this->location_filter_hints['filter_city'] ?? ''; ?>
        <div class="jem-search-location-field<?php echo $locationHint !== '' ? ' is-disabled' : ''; ?>">
            <div class="jem-search-location-label">
                <label class="jem-filter-label" for="filter_city"><?php echo Text::_('COM_JEM_CITY'); ?></label>
                <?php if ($locationHint !== ''): ?>
                <span id="filter_city_hint" class="jem-search-location-hint" tabindex="0" role="img"
                      title="<?php echo htmlspecialchars($locationHint, ENT_QUOTES, 'UTF-8'); ?>"
                      aria-label="<?php echo htmlspecialchars($locationHint, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa fa-ban" aria-hidden="true"></i>
                </span>
                <?php endif; ?>
            </div>
            <?php echo $this->lists['cities']; ?>
        </div>
        <?php endif; ?>
    <!-- LIMIT + buttons -->
    <div class="jem-search-filter-footer">
        <?php if ($this->filter_show_limit): ?>
        <div class="jem-search-filter-limit">
            <span class="jem-filter-label"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?>:</span>
            <?php echo $this->pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
        <div class="jem-search-filter-actions">
            <button class="btn btn-secondary" type="button" onclick="jem_search_clear();">
                <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
            </button>
            <button class="btn btn-primary" type="submit">
                <?php echo Text::_('COM_JEM_SEARCH_SUBMIT'); ?>
            </button>
        </div>
    </div>
    </div>
</div>
<!-- /SEARCH FILTERS -->
<div class="jem-sort jem-sort-small">
  <div class="jem-list-row jem-small-list">
    <div id="jem_date" class="sectiontableheader"><i class="far fa-clock" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php if ($this->jemsettings->showtitle == 1) : ?>
      <div id="jem_title" class="sectiontableheader"><i class="fa fa-comment" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showlocate == 1) : ?>
      <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showcity == 1) : ?>
      <div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showstate == 1) : ?>
      <div id="jem_state" class="sectiontableheader"><i class="fa fa-flag" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'c.name', $this->lists['order_Dir'], $this->lists['order']); ?></div>
    <?php endif; ?>
    <?php if ($this->jemsettings->showcat == 1) : ?>
      <div id="jem_category" class="sectiontableheader"><i class="fa fa-tag" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></div>
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
      $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
      $isSafari  = (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false);
      ?>
            <?php $this->rows = $this->getRows(); ?>
            <?php foreach ($this->rows as $row) : ?>
      <?php if (!empty($row->featured)) :   ?>
        <li class="jem-event jem-list-row jem-small-list jem-featured event_id<?php echo $row->id . ' ' . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
      <?php else : ?>
        <li class="jem-event jem-list-row jem-small-list event_id<?php echo $row->id . ' jem-odd' . ($row->odd +1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
      <?php endif; ?>

            <div class="jem-event-info-small jem-event-date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari)) : echo 'onclick="location.href=\''.Route::_(JemHelperRoute::getEventRoute($row->slug)).'\'"'; endif; ?>>
              <i class="far fa-clock" aria-hidden="true"></i>
              <?php
                echo JemOutput::formatShortDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row);
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
                <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->title) . JemOutput::moduleEventStatusBadge($row); ?></span></a>
                <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured)) :?>
                  <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showlocate == 1) : ?>
              <?php if (!empty($row->venue)) : ?>
                <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>" itemprop="location" itemscope itemtype="https://schema.org/Place">
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
              <?php if (!empty($row->country)) : ?>
                <?php $countryName = $this->escape($row->country_name ?: $row->country); ?>
                <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$countryName; ?>">
                  <i class="fa fa-flag" aria-hidden="true"></i>
                  <img src="<?php echo $flagBase . strtolower($row->country); ?>.webp" style="height:1em;vertical-align:middle;margin-right:4px;" alt="<?php echo $countryName; ?>">
                  <?php echo $countryName; ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-state"><i class="fa fa-flag" aria-hidden="true"></i> -</div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showcat == 1) : ?>
              <div class="jem-event-info-small jem-event-category" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))); ?>">
                <i class="fa fa-tag" aria-hidden="true"></i>
                <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
              </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showtitle == 0) : ?>
              <meta itemprop="name" content="<?php echo $this->escape($row->title); ?>" />
            <?php endif; ?>
            <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
            <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />

      </li>
    <?php endforeach; ?>
  <?php endif; ?>
</ul>


