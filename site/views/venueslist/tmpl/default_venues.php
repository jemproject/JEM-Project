<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/countries.php';

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
?>

<style>
div#jem_filter select {
    width: auto;
    margin-right:10px;
    border: 1px solid #808080;
    background-color: #C6CCBE;
    cursor: pointer;
}
</style>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">

<?php
function jem_common_show_filter(&$obj) {
  if ($obj->settings->get('global_show_filter',1) && !JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-hidefilter')) {
    return true;
  }
  if (JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-showfilter')) {
    return true;
  }
  return false;
}

if (!function_exists('jem_venueslist_country_name')) {
    function jem_venueslist_country_name($country)
    {
        $country = trim((string) $country);

        if ($country === '') {
            return '';
        }

        return JemHelperCountries::getCountryName($country) ?: $country;
    }
}

if (!function_exists('jem_venueslist_country_flag')) {
    function jem_venueslist_country_flag($country, $countryName)
    {
        $flagSrc = JemHelperCountries::getIsoFlag((string) $country);

        if (!$flagSrc) {
            return '';
        }

        $alt = htmlspecialchars((string) $countryName, ENT_QUOTES, 'UTF-8');
        $src = htmlspecialchars($flagSrc, ENT_QUOTES, 'UTF-8');

        return '<img src="' . $src . '" alt="' . $alt . '" title="' . $alt . '" class="venue_country_flag jem-venueslist-country-flag" style="width:20px;height:auto;margin-right:6px;vertical-align:middle;" />';
    }
}

if (!function_exists('jem_venueslist_default_venue_page_link')) {
    function jem_venueslist_default_venue_page_link($row)
    {
        $slug = (int) $row->id . ':' . ($row->alias ?? '');

        return Route::_('index.php?option=com_jem&view=venue&layout=default&id=' . $slug);
    }
}

if (!function_exists('jem_venueslist_default_venue_calendar_link')) {
    function jem_venueslist_default_venue_calendar_link($row)
    {
        $slug = (int) $row->id . ':' . ($row->alias ?? '');

        return Route::_('index.php?option=com_jem&view=venue&layout=calendar&id=' . $slug);
    }
}

if (!function_exists('jem_venueslist_default_venue_edit_link')) {
    function jem_venueslist_default_venue_edit_link($row)
    {
        return Route::_('index.php?option=com_jem&task=venue.edit&a_id=' . (int) $row->id . '&return=' . base64_encode(Uri::getInstance()->toString()));
    }
}

if (!function_exists('jem_venueslist_default_display_order')) {
    function jem_venueslist_default_display_order($params)
    {
        $orders = array(
            'venue_city_country' => array('venue', 'city', 'country'),
            'venue_country_city' => array('venue', 'country', 'city'),
            'city_venue_country' => array('city', 'venue', 'country'),
            'city_country_venue' => array('city', 'country', 'venue'),
            'country_venue_city' => array('country', 'venue', 'city'),
            'country_city_venue' => array('country', 'city', 'venue'),
        );
        $order = (string) $params->get('display_order', 'venue_city_country');

        return $orders[$order] ?? $orders['venue_city_country'];
    }
}

$displayOrder = jem_venueslist_default_display_order($this->params);
$showCalendarColumn = (bool) $this->params->get('showvenuecalendar', 1);
$mediaRoot = rtrim(Uri::root(true), '/');
$calendarIcon = $mediaRoot . '/media/com_jem/images/el.webp';
$editIcon = $mediaRoot . '/media/com_jem/images/calendar_edit.webp';
$user = JemFactory::getUser();
$showEditColumn = false;

foreach ((array) $this->rows as $venueRow) {
    if ($user->can('edit', 'venue', (int) $venueRow->id, (int) ($venueRow->created_by ?? 0))) {
        $showEditColumn = true;
        break;
    }
}
?>
<?php if (jem_common_show_filter($this) && !JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')): ?>
    <div id="jem_filter" class="floattext jem-venueslist-filter">
       <?php if ($this->settings->get('global_show_filter',1)) : ?>
        <div class="jem_fleft jem-venueslist-filter-search">
            <label for="filter"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
            <?php echo $this->lists['filter'].'&nbsp;'; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
    <?php endif; ?>

    <?php if ($this->settings->get('global_display',1)) : ?>
    <div class="jem_fright jem-venueslist-filter-limit">
        <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
        <?php echo $this->pagination->getLimitBox(); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

    <div class="table table-responsive table-striped table-hover table-sm">
    <table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="Venues">
        <colgroup>
            <?php foreach ($displayOrder as $field) : ?>
                <?php if ($field === 'venue') : ?>
                    <col style="width: <?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
                <?php elseif ($field === 'city') : ?>
                    <col style="width: 20%" class="jem_col_city" />
                    <?php if ($this->params->get('showstate')) : ?>
                        <col style="width: 20%" class="jem_col_state" />
                    <?php endif; ?>
                <?php elseif ($field === 'country') : ?>
                    <col style="width: 20%" class="jem_col_country" />
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($showCalendarColumn) : ?>
                <col style="width: 1%" class="jem_col_calendar" />
            <?php endif; ?>
            <?php if ($showEditColumn) : ?>
                <col style="width: 1%" class="jem_col_edit" />
            <?php endif; ?>
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($displayOrder as $field) : ?>
                    <?php if ($field === 'venue') : ?>
                        <th id="jem_location" class="sectiontableheader" style="text-align: left;"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'a.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php elseif ($field === 'city') : ?>
                        <th id="jem_city" class="sectiontableheader" style="text-align: left;"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'a.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                        <?php if ($this->params->get('showstate')) : ?>
                            <th id="jem_state" class="sectiontableheader" style="text-align: left;"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'a.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                        <?php endif; ?>
                    <?php elseif ($field === 'country') : ?>
                        <th id="jem_country" class="sectiontableheader" style="text-align: left;"><i class="fa fa-globe" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'a.country', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($showCalendarColumn) : ?>
                    <th id="jem_calendar" class="sectiontableheader center" style="text-align: center;"><?php echo Text::_('COM_JEM_CALENDAR'); ?></th>
                <?php endif; ?>
                <?php if ($showEditColumn) : ?>
                    <th id="jem_edit" class="sectiontableheader center" style="text-align: center;"><?php echo Text::_('COM_JEM_EDIT_VENUE'); ?></th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($this->rows)) : ?>
                <tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></td></tr>
            <?php else : ?>
                <?php $odd = 0; ?>
                <?php foreach ($this->rows as $row) : ?>
                    <?php
                    // has user access
                    $venueaccess = '';
                    if (!$row->user_has_access_venue) {
                        // show a closed lock icon
                        $venueaccess = ' <span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                    }
                    $canEditVenue = $user->can('edit', 'venue', (int) $row->id, (int) ($row->created_by ?? 0));
                    ?>
                    <tr class="venue_id<?php echo $this->escape($row->id); ?>">
                    <?php $odd = 1 - $odd; ?>
                    <?php foreach ($displayOrder as $field) : ?>
                        <?php if ($field === 'venue') : ?>
                            <td headers="jem_location" style="text-align: left; vertical-align: top;">
                                <?php
                                if ($this->jemsettings->showlinkvenue == 1) :
                                    echo $row->id != 0 ? "<a href='".jem_venueslist_default_venue_page_link($row)."'>".$this->escape($row->venue)."</a>" : '-';
                                else :
                                    echo $row->id ? $this->escape($row->venue) : '-';
                                endif;
                                echo JemOutput::publishstateicon($row);
                                echo $venueaccess;
                                ?>
                            </td>
                        <?php elseif ($field === 'city') : ?>
                            <td headers="jem_city" style="text-align: left; vertical-align: top;"><?php echo $row->city ? $this->escape($row->city) : '-'; ?></td>
                            <?php if ($this->params->get('showstate')) : ?>
                                <td headers="jem_state" style="text-align: left; vertical-align: top;">
                                    <?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
                                </td>
                            <?php endif; ?>
                        <?php elseif ($field === 'country') : ?>
                            <td headers="jem_country" style="text-align: left; vertical-align: top;">
                                <?php $countryName = jem_venueslist_country_name($row->country ?? ''); ?>
                                <?php echo $countryName !== '' ? jem_venueslist_country_flag($row->country ?? '', $countryName) . $this->escape($countryName) : '-'; ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                        <?php if ($showCalendarColumn) : ?>
                            <td headers="jem_calendar" class="center" style="text-align: center; vertical-align: top;">
                                <a href="<?php echo htmlspecialchars(jem_venueslist_default_venue_calendar_link($row), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_JEM_CALENDAR'); ?>">
                                    <img src="<?php echo htmlspecialchars($calendarIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_CALENDAR'); ?>" class="jem-venuesmap-action-icon" />
                                </a>
                            </td>
                        <?php endif; ?>
                        <?php if ($showEditColumn) : ?>
                            <td headers="jem_edit" class="center" style="text-align: center; vertical-align: top;">
                                <?php if ($canEditVenue) : ?>
                                    <a href="<?php echo htmlspecialchars(jem_venueslist_default_venue_edit_link($row), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_JEM_EDIT_VENUE'); ?>">
                                        <img src="<?php echo htmlspecialchars($editIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_EDIT_VENUE'); ?>" class="jem-venuesmap-action-icon" />
                                    </a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                </tr>

            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="option" value="com_jem" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<div class="pagination">
    <?php echo $this->pagination->getPagesLinks(); ?>
</div>
