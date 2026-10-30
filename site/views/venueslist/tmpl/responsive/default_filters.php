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
<div id="jem_filter" class="floattext jem-form jem-row jem-justify-start jem-venueslist-filter">
    <?php if ($showSearch) : ?>
        <div class="jem-venueslist-filter-label">
            <label for="filter_type"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
        </div>
        <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-search">
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox form-control" onchange="this.form.submit();" />
        </div>
    <?php endif; ?>

    <?php if ($showCountry) : ?>
        <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-country">
            <label for="filter_country"><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
            <?php echo $this->lists['country_filter']; ?>
        </div>
    <?php endif; ?>

    <?php if ($showAll) : ?>
        <label class="jem-row jem-align-center jem-nowrap">
            <input type="hidden" name="show_all_venues" value="0">
            <input type="checkbox" name="show_all_venues" value="1"<?php echo !empty($this->lists['show_all_venues']) ? ' checked' : ''; ?> onchange="this.form.submit();">
            <?php echo Text::_('COM_JEM_SHOW_ALL_VENUES'); ?>
        </label>
    <?php endif; ?>

    <?php if ($showSearch || $showCountry) : ?>
        <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-actions">
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="var s=this.form.querySelector('#filter_search');if(s){s.value='';}var c=this.form.querySelector('#filter_country');if(c){c.selectedIndex=0;}this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
    <?php endif; ?>

    <?php if ($showLimit) : ?>
        <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-limit">
            <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
            <?php echo $this->pagination->getLimitBox(); ?>
        </div>
    <?php endif; ?>
</div>
