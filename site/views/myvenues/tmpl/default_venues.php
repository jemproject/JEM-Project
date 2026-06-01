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

<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value = dir;
        form.submit(view);
    }
</script>

<?php if (!$this->params->get('show_page_heading', 1)) : /* hide this if page heading is shown */ ?>
    <h1 class="componentheading"><?php echo Text::_('COM_JEM_MY_VENUES'); ?></h1>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
    <?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
    <div id="jem_filter" class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <?php if ($this->settings->get('global_show_filter',1)) : ?>
        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
            <label for="filter" class="mb-0"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm" style="flex:1 1 8rem;min-width:6rem;max-width:20rem;" onchange="document.adminForm.submit();" />
            <button class="btn btn-primary btn-sm" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary btn-sm" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php endif; ?>

        <?php if ($this->settings->get('global_display',1)) : ?>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <label for="limit" class="mb-0"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
            <?php echo $this->pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="eventtable jem-myvenues" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="Venues">
            <colgroup>
                <?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
                <col style="width: 1%" class="jem_col_checkall" />
                <?php endif; ?>
                <?php if (/*$this->jemsettings->showlocate ==*/ 1) : ?>
                <col style="width: <?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showcity == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showstate == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->statewidth; ?>" class="jem_col_country" />
                <?php endif; ?>
                <col style="width: 1%" class="jem_col_status" />
            </colgroup>

            <thead>
                <tr>
                    <?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
                    <th class="sectiontableheader center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
                    <?php endif; ?>
                    <?php if (/*$this->jemsettings->showlocate ==*/ 1) : ?>
                    <th id="jem_location" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showcity == 1) : ?>
                    <th id="jem_city" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showstate == 1) : ?>
                    <th id="jem_country" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'l.country', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <th id="jem_status" class="sectiontableheader center" nowrap="nowrap"><?php echo Text::_('JSTATUS'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($this->venues)) : ?>
                    <tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($this->venues as $i => $row) : ?>
                        <tr class="row<?php echo $i % 2 . ' venue_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Place">

                            <?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
                            <td class="center">
                                <?php
                                if (!empty($row->params) && $row->params->get('access-change', false)) :
                                    echo HTMLHelper::_('grid.id', $i, $row->id);
                                endif;
                                ?>
                            </td>
                            <?php endif; ?>

                            <?php if (/*$this->jemsettings->showlocate ==*/ 1) : ?>
                            <td headers="jem_location" style="text-align: left; vertical-align: top;">
                                <?php
                                if (!empty($row->venue)) :
                                    if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) :
                                        echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."' itemprop='url'><span itemprop='name'>".$this->escape($row->venue)."</span></a>";
                                    else :
                                        echo '<span itemprop="name">'.$this->escape($row->venue).'</span>';
                                    endif;
                                else :
                                    echo '-';
                                endif;
                                ?>
                                <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" hidden>
                                    <?php if (!empty($row->street)) : ?><meta itemprop="streetAddress" content="<?php echo $this->escape($row->street); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->postalCode)) : ?><meta itemprop="postalCode" content="<?php echo $this->escape($row->postalCode); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->city)) : ?><meta itemprop="addressLocality" content="<?php echo $this->escape($row->city); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->state)) : ?><meta itemprop="addressRegion" content="<?php echo $this->escape($row->state); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->country)) : ?><meta itemprop="addressCountry" content="<?php echo $this->escape($row->country); ?>" /><?php endif; ?>
                                </div>
                                <?php echo JemOutput::publishstateicon($row); ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showcity == 1) : ?>
                            <td headers="jem_city" style="text-align: left; vertical-align: top;">
                                <?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showstate == 1) : ?>
                            <td headers="jem_country" style="text-align: left; vertical-align: top;">
                                <?php $countryName = jem_myvenues_country_name($row->country ?? ''); ?>
                                <?php echo $countryName !== '' ? jem_myvenues_country_flag($row->country ?? '', $countryName) . $this->escape($countryName) : '-'; ?>
                            </td>
                            <?php endif; ?>

                            <td class="center">
                                <?php // Ensure icon is not clickable if user isn't allowed to change state!
                                $enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
                                echo HTMLHelper::_('jgrid.published', $row->published, $i, 'myvenues.', $enabled);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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
