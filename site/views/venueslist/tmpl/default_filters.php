<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Shared top/bottom venue filters. Placement never changes available controls.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$pageClassSuffix = (string) $this->params->get('pageclass_sfx', '');

if (JemHelper::jemStringContains($pageClassSuffix, 'jem-hidefilter')) {
    return;
}

$showSearch = (bool) $this->settings->get('global_show_filter', 1)
    || JemHelper::jemStringContains($pageClassSuffix, 'jem-showfilter');
$showCountry = !empty($this->lists['country_filter']);
$showAll = !empty($this->lists['show_venue_event_filter']);
$showLimit = (bool) $this->settings->get('global_display', 1);

if (!$showSearch && !$showCountry && !$showAll && !$showLimit) {
    return;
}
?>
<div id="jem_filter" class="jem-venueslist-filter d-flex flex-wrap align-items-center gap-2 mb-2">
    <?php if ($showSearch) : ?>
        <div class="jem-venueslist-filter-search d-flex flex-wrap align-items-center gap-2">
            <label for="filter_type" class="mb-0"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm" style="flex:1 1 8rem;min-width:6rem;max-width:20rem;" onchange="this.form.submit();" />
        </div>
    <?php endif; ?>

    <?php if ($showCountry) : ?>
        <div class="jem-venueslist-filter-country d-flex align-items-center gap-2">
            <label for="filter_country" class="mb-0"><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
            <?php echo $this->lists['country_filter']; ?>
        </div>
    <?php endif; ?>

    <?php if ($showSearch || $showCountry) : ?>
        <div class="jem-venueslist-filter-actions d-flex align-items-center gap-2">
            <button class="btn btn-primary btn-sm" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary btn-sm" type="button" onclick="var s=this.form.querySelector('#filter_search');if(s){s.value='';}var c=this.form.querySelector('#filter_country');if(c){c.selectedIndex=0;}this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
    <?php endif; ?>

    <?php if ($showAll) : ?>
        <label class="d-flex align-items-center gap-1 mb-0">
            <input type="hidden" name="show_all_venues" value="0">
            <input type="checkbox" name="show_all_venues" value="1"<?php echo !empty($this->lists['show_all_venues']) ? ' checked' : ''; ?> onchange="this.form.submit();">
            <?php echo Text::_('COM_JEM_SHOW_ALL_VENUES'); ?>
        </label>
    <?php endif; ?>

    <?php if ($showLimit) : ?>
        <div class="jem-venueslist-filter-limit d-flex align-items-center gap-2 ms-auto">
            <label for="limit" class="mb-0"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
            <?php echo $this->pagination->getLimitBox(); ?>
        </div>
    <?php endif; ?>
</div>
