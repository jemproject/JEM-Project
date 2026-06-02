<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/countries.php';
require_once JPATH_SITE . '/components/com_jem/classes/image.class.php';

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
?>

<style>
    #jem_filter .jem-venueslist-filter-country {
        flex: 0 0 11.5rem;
        min-width: 0;
        max-width: 11.5rem;
    }

    #jem_filter .jem-venueslist-filter-country label {
        margin-bottom: 0;
        white-space: nowrap;
    }

    #jem_filter .jem-venueslist-filter-country #filter_country,
    #jem_filter .jem-venueslist-filter-country joomla-field-fancy-select,
    #jem_filter .jem-venueslist-filter-country .choices {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        margin-bottom: 0;
    }

    #jem_filter .jem-venueslist-filter-country .choices__inner {
        min-height: 2.25rem;
        box-sizing: border-box;
    }

    #jem_filter .jem-venueslist-filter-country .choices__placeholder {
        display: none;
    }

    #jem_filter .jem-venueslist-filter-country .choices__input--cloned {
        min-width: 1ch !important;
        width: 1ch !important;
    }

    #jem_filter .jem-venueslist-filter-country .choices__input--cloned::placeholder {
        color: transparent;
    }

    #jem_filter .jem-venueslist-filter-search {
        flex: 1 1 auto;
        min-width: 18rem;
    }

    #jem_filter .jem-venueslist-filter-search #filter_search {
        flex: 0 1 12rem !important;
        max-width: 12rem !important;
    }

    .jem-venueslist-map-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        white-space: nowrap;
    }
</style>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">

<?php
function jem_common_show_filter(&$obj) {
  if (JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-hidefilter')) {
    return false;
  }
  if ((int) $obj->params->get('showcountryfilter', 1)) {
    return true;
  }
  if ($obj->settings->get('global_show_filter',1)) {
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

if (!function_exists('jem_venueslist_default_venue_image')) {
    function jem_venueslist_default_venue_image($row)
    {
        $image = JemImage::flyercreator($row->locimage ?? '', 'venue');

        if (empty($image)) {
            return '';
        }

        $src = !empty($image['thumb']) && is_file(JPATH_SITE . '/' . $image['thumb']) ? $image['thumb'] : $image['original'];
        $alt = htmlspecialchars((string) ($row->venue ?? ''), ENT_QUOTES, 'UTF-8');

        return '<img src="' . htmlspecialchars(Uri::root(true) . '/' . ltrim($src, '/'), ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="jem-venueslist-venue-image" loading="lazy" style="width:64px;height:48px;object-fit:contain;" />';
    }
}

if (!function_exists('jem_venueslist_default_type_badge')) {
    function jem_venueslist_default_type_badge($row)
    {
        JemOutput::translateType($row, 'type_');

        if (empty($row->type_name)) {
            return '';
        }

        $name = htmlspecialchars((string) $row->type_name, ENT_QUOTES, 'UTF-8');
        $style = '';

        if (!empty($row->type_color) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $row->type_color)) {
            $style = ' style="background-color:' . htmlspecialchars((string) $row->type_color, ENT_QUOTES, 'UTF-8') . ';"';
        }

        $inner = '';
        if (!empty($row->type_icon)) {
            $inner .= '<span class="' . htmlspecialchars((string) $row->type_icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span> ';
        }
        $inner .= $name;

        $link = htmlspecialchars(Route::_(JemHelperRoute::getTypevenuesRoute((int) $row->type_id)), ENT_QUOTES, 'UTF-8');

        return '<a href="' . $link . '" class="jem-type-badge"' . $style . '>' . $inner . '</a>';
    }
}

if (!function_exists('jem_venueslist_default_venue_map')) {
    function jem_venueslist_default_venue_map($row)
    {
        if (!is_numeric($row->latitude ?? null) || !is_numeric($row->longitude ?? null)) {
            return '';
        }

        $lat = (float) $row->latitude;
        $lon = (float) $row->longitude;
        $bbox = ($lon - 0.005) . ',' . ($lat - 0.003) . ',' . ($lon + 0.005) . ',' . ($lat + 0.003);
        $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);
        $external = 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $lat) . '&mlon=' . rawurlencode((string) $lon) . '#map=16/' . rawurlencode((string) $lat) . '/' . rawurlencode((string) $lon);
        $title = htmlspecialchars((string) ($row->venue ?? Text::_('COM_JEM_MAP')), ENT_QUOTES, 'UTF-8');
        $modalId = 'jem-venueslist-map-' . (int) ($row->id ?? 0);

        $output = HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            array(
                'url'    => $src,
                'title'  => Text::_('COM_JEM_MAP') . ': ' . $title,
                'width'  => '900px',
                'height' => '520px',
                'footer' => '<a class="btn btn-primary" href="' . htmlspecialchars($external, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_('COM_JEM_OPEN_MAP') . '</a>'
                    . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>',
            )
        );

        $output .= '<button type="button" class="btn btn-sm btn-outline-primary jem-venueslist-map-button" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '" title="' . $title . '">'
            . '<i class="fa fa-map-marker" aria-hidden="true"></i> ' . Text::_('COM_JEM_VIEW_MAP')
            . '</button>';

        return $output;
    }
}

if (!function_exists('jem_venueslist_default_display_order')) {
    function jem_venueslist_default_display_order($params)
    {
        $defaultOrder = array('image', 'venue', 'type', 'city', 'state', 'country', 'map', 'calendar');
        $orders = array(
            'venue_city_country' => array('venue', 'city', 'country'),
            'venue_country_city' => array('venue', 'country', 'city'),
            'city_venue_country' => array('city', 'venue', 'country'),
            'city_country_venue' => array('city', 'country', 'venue'),
            'country_venue_city' => array('country', 'venue', 'city'),
            'country_city_venue' => array('country', 'city', 'venue'),
        );
        $order = strtolower((string) $params->get('display_order', implode(',', $defaultOrder)));
        $aliases = array(
            'types' => 'type',
            'county' => 'state',
            'region' => 'state',
            'venueimage' => 'image',
            'venue_image' => 'image',
        );

        if (isset($orders[$order])) {
            $displayOrder = $orders[$order];
        } else {
            $displayOrder = preg_split('/[\s,;|]+/', $order, -1, PREG_SPLIT_NO_EMPTY);
            $displayOrder = array_map(static function ($field) use ($aliases) {
                $field = preg_replace('/[^a-z_]/', '', (string) $field);

                return $aliases[$field] ?? $field;
            }, $displayOrder);
            $displayOrder = array_values(array_intersect(array_unique($displayOrder), $defaultOrder));
        }

        foreach ($defaultOrder as $field) {
            if (!in_array($field, $displayOrder, true)) {
                $displayOrder[] = $field;
            }
        }

        if (!(int) $params->get('showvenueimage', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('image')));
        }

        if (!(int) $params->get('showtypes', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('type')));
        }

        if (!(int) $params->get('showcity', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('city')));
            $displayOrder = array_values(array_diff($displayOrder, array('state')));
        }

        if (!(int) $params->get('showcountry', 0)) {
            $displayOrder = array_values(array_diff($displayOrder, array('country')));
        }

        if (!(int) $params->get('showstate', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('state')));
        }

        if (!(int) $params->get('showvenuemap', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('map')));
        }

        if (!(int) $params->get('showvenuecalendar', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('calendar')));
        }

        return $displayOrder;
    }
}

$displayOrder = jem_venueslist_default_display_order($this->params);
$uri = Uri::getInstance();
$showVenueImage = (bool) $this->params->get('showvenueimage', 1);
$showTypes = (bool) $this->params->get('showtypes', 1);
$showVenueMap = (bool) $this->params->get('showvenuemap', 1);
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
    <div id="jem_filter" class="jem-venueslist-filter d-flex flex-wrap align-items-center gap-2 mb-2">
        <?php if ($this->settings->get('global_show_filter',1)) : ?>
        <div class="jem-venueslist-filter-search d-flex flex-wrap align-items-center gap-2">
            <label for="filter" class="mb-0"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="form-control form-control-sm" style="flex:1 1 8rem;min-width:6rem;max-width:20rem;" onchange="document.adminForm.submit();" />
            <button class="btn btn-primary btn-sm" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary btn-sm" type="button" onclick="document.getElementById('filter_search').value='';var c=document.getElementById('filter_country');if(c){c.selectedIndex=0;}this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($this->lists['country_filter'])) : ?>
        <div class="jem-venueslist-filter-country d-flex align-items-center gap-2">
            <label for="filter_country" class="mb-0"><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
            <?php echo $this->lists['country_filter']; ?>
        </div>
        <?php endif; ?>

        <?php if ($this->settings->get('global_display',1)) : ?>
        <div class="jem-venueslist-filter-limit d-flex align-items-center gap-2 ms-auto">
            <label for="limit" class="mb-0"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
            <?php echo $this->pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <div class="table table-responsive table-striped table-hover table-sm">
    <table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="Venues">
        <colgroup>
            <?php foreach ($displayOrder as $field) : ?>
                <?php if ($field === 'image') : ?>
                    <col style="width: 1%" class="jem_col_venue_image" />
                <?php elseif ($field === 'venue') : ?>
                    <col style="width: <?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
                <?php elseif ($field === 'type') : ?>
                    <col style="width: 12%" class="jem_col_type" />
                <?php elseif ($field === 'city') : ?>
                    <col style="width: 20%" class="jem_col_city" />
                <?php elseif ($field === 'state') : ?>
                    <col style="width: 20%" class="jem_col_state" />
                <?php elseif ($field === 'country') : ?>
                    <col style="width: 20%" class="jem_col_country" />
                <?php elseif ($field === 'map') : ?>
                    <col style="width: 1%" class="jem_col_map" />
                <?php elseif ($field === 'calendar') : ?>
                    <col style="width: 1%" class="jem_col_calendar" />
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($showEditColumn) : ?>
                <col style="width: 1%" class="jem_col_edit" />
            <?php endif; ?>
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($displayOrder as $field) : ?>
                    <?php if ($field === 'image') : ?>
                        <th id="jem_venue_image" class="sectiontableheader" style="text-align: center;"><i class="fa fa-image" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_IMAGE'); ?></th>
                    <?php elseif ($field === 'venue') : ?>
                        <th id="jem_location" class="sectiontableheader" style="text-align: left;"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'a.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php elseif ($field === 'type') : ?>
                        <th id="jem_type" class="sectiontableheader" style="text-align: left;"><i class="fa fa-tags" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TYPE'); ?></th>
                    <?php elseif ($field === 'city') : ?>
                        <th id="jem_city" class="sectiontableheader" style="text-align: left;"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'a.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php elseif ($field === 'state') : ?>
                        <th id="jem_state" class="sectiontableheader" style="text-align: left;"><i class="fa fa-map-signs" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUESLIST_TABLE_STATE', 'a.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php elseif ($field === 'country') : ?>
                        <th id="jem_country" class="sectiontableheader" style="text-align: left;"><i class="fa fa-globe" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'a.country', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php elseif ($field === 'map') : ?>
                        <th id="jem_map" class="sectiontableheader center" style="text-align: center;"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_MAP'); ?></th>
                    <?php elseif ($field === 'calendar') : ?>
                        <th id="jem_calendar" class="sectiontableheader center" style="text-align: center;"><i class="fa fa-calendar" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_CALENDAR'); ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($showEditColumn) : ?>
                    <th id="jem_edit" class="sectiontableheader center" style="text-align: center;"><i class="fa fa-edit" aria-hidden="true"></i>&nbsp;<?php echo Text::_('JGLOBAL_EDIT'); ?></th>
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
                    <tr class="venue_id<?php echo $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
                    <?php $odd = 1 - $odd; ?>
                    <?php foreach ($displayOrder as $field) : ?>
                        <?php if ($field === 'image') : ?>
                            <td headers="jem_venue_image" style="text-align: center; vertical-align: middle;">
                                <?php echo jem_venueslist_default_venue_image($row) ?: '-'; ?>
                            </td>
                        <?php elseif ($field === 'venue') : ?>
                            <td headers="jem_location" style="text-align: left; vertical-align: middle;">
                                <?php
                                if ($this->jemsettings->showlinkvenue == 1) :
                                    echo $row->id != 0 ? "<a href='".jem_venueslist_default_venue_page_link($row)."' itemprop='url'><span itemprop='name'>".$this->escape($row->venue)."</span></a>" : '-';
                                else :
                                    echo $row->id ? "<span itemprop='name'>".$this->escape($row->venue)."</span>" : '-';
                                endif;
                                echo JemOutput::publishstateicon($row);
                                echo $venueaccess;
                                ?>
                                <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getVenueRoute($row->venueslug)); ?>" />
                                <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" hidden>
                                    <?php if (!empty($row->street)) : ?><meta itemprop="streetAddress" content="<?php echo $this->escape($row->street); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->postalCode)) : ?><meta itemprop="postalCode" content="<?php echo $this->escape($row->postalCode); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->city)) : ?><meta itemprop="addressLocality" content="<?php echo $this->escape($row->city); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->state)) : ?><meta itemprop="addressRegion" content="<?php echo $this->escape($row->state); ?>" /><?php endif; ?>
                                    <?php if (!empty($row->country)) : ?><meta itemprop="addressCountry" content="<?php echo $this->escape($row->country); ?>" /><?php endif; ?>
                                </div>
                            </td>
                        <?php elseif ($field === 'type') : ?>
                            <td headers="jem_type" style="text-align: left; vertical-align: middle;">
                                <?php echo jem_venueslist_default_type_badge($row) ?: '-'; ?>
                            </td>
                        <?php elseif ($field === 'city') : ?>
                            <td headers="jem_city" style="text-align: left; vertical-align: middle;"><?php echo $row->city ? $this->escape($row->city) : '-'; ?></td>
                        <?php elseif ($field === 'state') : ?>
                            <td headers="jem_state" style="text-align: left; vertical-align: middle;">
                                <?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
                            </td>
                        <?php elseif ($field === 'country') : ?>
                            <td headers="jem_country" style="text-align: left; vertical-align: middle;">
                                <?php $countryName = jem_venueslist_country_name($row->country ?? ''); ?>
                                <?php echo $countryName !== '' ? jem_venueslist_country_flag($row->country ?? '', $countryName) . $this->escape($countryName) : '-'; ?>
                            </td>
                        <?php elseif ($field === 'map') : ?>
                            <td headers="jem_map" class="center" style="text-align: center; vertical-align: middle;">
                                <?php echo jem_venueslist_default_venue_map($row) ?: '-'; ?>
                            </td>
                        <?php elseif ($field === 'calendar') : ?>
                            <td headers="jem_calendar" class="center" style="text-align: center; vertical-align: middle;">
                                <a href="<?php echo htmlspecialchars(jem_venueslist_default_venue_calendar_link($row), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_JEM_CALENDAR'); ?>">
                                    <img src="<?php echo htmlspecialchars($calendarIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_CALENDAR'); ?>" class="jem-venuesmap-action-icon" />
                                </a>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                        <?php if ($showEditColumn) : ?>
                            <td headers="jem_edit" class="center" style="text-align: center; vertical-align: middle;">
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

