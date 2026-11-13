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
?>

<?php $flagBase = Uri::root(true) . '/media/com_jem/images/flags/w80-webp/'; ?>
<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value = dir;
        form.submit(view);
    }
</script>

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

<div class="table-responsive">
    <table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">
        <colgroup>
            <col style="width: <?php echo $this->jemsettings->datewidth; ?>" class="jem_col_date" />
            <?php if ($this->jemsettings->showtitle == 1) : ?>
            <col style="width: <?php echo $this->jemsettings->titlewidth; ?>" class="jem_col_title" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showlocate == 1) : ?>
            <col style="width: <?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showcity == 1) : ?>
            <col style="width: <?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showstate == 1) : ?>
            <col style="width: <?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showcat == 1) : ?>
            <col style="width: <?php echo $this->jemsettings->catfrowidth; ?>" class="jem_col_category" />
            <?php endif; ?>
        </colgroup>

        <thead>
            <tr>
                <th id="jem_date" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                <?php if ($this->jemsettings->showtitle == 1) : ?>
                <th id="jem_title" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                <?php endif; ?>
                <?php if ($this->jemsettings->showlocate == 1) : ?>
                <th id="jem_location" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                <?php endif; ?>
                <?php if ($this->jemsettings->showcity == 1) : ?>
                <th id="jem_city" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                <?php endif; ?>
                <?php if ($this->jemsettings->showstate == 1) : ?>
                <th id="jem_state" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'c.name', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                <?php endif; ?>
                <?php if ($this->jemsettings->showcat == 1) : ?>
                <th id="jem_category" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($this->rows)) : ?>
                <tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS_FOUND'); ?></td></tr>
            <?php else : ?>
                <?php $odd = 0; ?>
                <?php foreach ($this->rows as $row) : ?>
                    <?php $odd = 1 - $odd; ?>
                    <?php if (!empty($row->featured)) : ?>
                    <tr class="featured featured<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                    <?php else : ?>
                    <tr class="sectiontableentry<?php echo ($odd + 1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                    <?php endif; ?>

                        <td headers="jem_date" style="text-align: left;">
                            <?php
                            echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                            echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row);
                            ?>
                        </td>

                        <?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : ?>
                        <td headers="jem_title" style="text-align: left; vertical-align: top;">
                            <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url">
                                <span itemprop="name"><?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::moduleEventStatusBadge($row); ?></span>
                            </a><?php echo JemOutput::publishstateicon($row); ?>
                        </td>
                        <?php endif; ?>

                        <?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : ?>
                        <td headers="jem_title" style="text-align: left; vertical-align: top;" itemprop="name">
                            <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row) . JemOutput::moduleEventStatusBadge($row); ?>
                        </td>
                        <?php endif; ?>

                        <?php if ($this->jemsettings->showlocate == 1) : ?>
                        <td headers="jem_location" style="text-align: left; vertical-align: top;">
                            <?php
                            if (!empty($row->venue)) :
                                if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) :
                                    echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>";
                                else :
                                    echo $this->escape($row->venue);
                                endif;
                            else :
                                echo '-';
                            endif;
                            ?>
                        </td>
                        <?php endif; ?>

                        <?php if ($this->jemsettings->showcity == 1) : ?>
                        <td headers="jem_city" style="text-align: left; vertical-align: top;">
                            <?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
                        </td>
                        <?php endif; ?>

                        <?php if ($this->jemsettings->showstate == 1) : ?>
                        <td headers="jem_state" style="text-align: left; vertical-align: top;">
                            <?php
                            if (!empty($row->country)) {
                                $countryName = $this->escape($row->country_name ?: $row->country);
                                echo '<img src="' . $flagBase . strtolower($row->country) . '.webp" style="height:1em;vertical-align:middle;margin-right:4px;" alt="' . $countryName . '">';
                                echo $countryName;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <?php endif; ?>

                        <?php if ($this->jemsettings->showcat == 1) : ?>
                        <td headers="jem_category" style="text-align: left; vertical-align: top;">
                            <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
