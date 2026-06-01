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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$uri = Uri::getInstance();
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

  .jem-sort #jem_city,
  #jem .jem-event .jem-event-city {
      flex: 1 <?php echo ($this->jemsettings->citywidth); ?>;
  }

  .jem-sort #jem_state,
  #jem .jem-event .jem-event-state {
    <?php if ($this->params->get('showstate') && (!empty($this->jemsettings->statewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
  }

  .jem-sort #jem_location,
  #jem .jem-event .jem-event-venue {
      flex: 1 <?php echo ($this->jemsettings->locationwidth); ?>;
  }

  .jem-sort #jem_venue_image,
  #jem .jem-event .jem-event-venue-image {
      flex: 0 0 5rem;
      justify-content: center;
      text-align: center;
  }

  .jem-sort #jem_type,
  #jem .jem-event .jem-event-type {
      flex: 0 0 9rem;
  }

  .jem-sort #jem_country,
  #jem .jem-event .jem-event-country {
      flex: 1 20%;
  }

  .jem-sort #jem_map,
  #jem .jem-event .jem-event-map-action {
      flex: 0 0 5rem;
      justify-content: center;
      text-align: center;
  }

  .jem-sort #jem_calendar,
  #jem .jem-event .jem-event-calendar-action {
      flex: 0 0 6rem;
      justify-content: center;
      text-align: center;
  }

  .jem-sort #jem_edit,
  #jem .jem-event .jem-event-edit-action {
      flex: 0 0 4rem;
      justify-content: center;
      text-align: center;
  }

  #jem_filter .jem-venueslist-filter-country {
      flex: 0 0 11.5rem;
      min-width: 0;
      max-width: 11.5rem;
      align-items: center;
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

  .jem-venueslist-map-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
      white-space: nowrap;
  }

  #jem .jem-event .jem-event-calendar-action,
  #jem .jem-event .jem-event-map-action,
  #jem .jem-event .jem-event-edit-action {
      display: flex;
      align-items: center;
  }

  .jem-sort .sectiontableheader,
  #jem .jem-event .jem-event-info-small {
      box-sizing: border-box;
      padding-right: 0.75rem;
  }

  .jem-sort .jem-small-list,
  #jem .eventlist .jem-small-list {
      align-items: center;
  }

  .jem-sort .jem-event-action,
  #jem .jem-event .jem-event-action {
      padding-right: 0;
  }

  @media (min-width: 768px) {
      .jem-sort .jem-small-list,
      #jem .eventlist .jem-small-list {
          flex-wrap: nowrap;
      }
  }

  #jem .jem-event .jem-event-calendar-action a,
  #jem .jem-event .jem-event-edit-action a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
  }

  #jem .jem-event .jem-venueslist-venue-image {
      width: 64px;
      height: 48px;
      object-fit: contain;
  }

</style>


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

if (!function_exists('jem_venueslist_responsive_venue_page_link')) {
    function jem_venueslist_responsive_venue_page_link($row)
    {
        $slug = (int) $row->id . ':' . ($row->alias ?? '');

        return Route::_('index.php?option=com_jem&view=venue&layout=default&id=' . $slug);
    }
}

if (!function_exists('jem_venueslist_responsive_venue_calendar_link')) {
    function jem_venueslist_responsive_venue_calendar_link($row)
    {
        $slug = (int) $row->id . ':' . ($row->alias ?? '');

        return Route::_('index.php?option=com_jem&view=venue&layout=calendar&id=' . $slug);
    }
}

if (!function_exists('jem_venueslist_responsive_venue_edit_link')) {
    function jem_venueslist_responsive_venue_edit_link($row)
    {
        return Route::_('index.php?option=com_jem&task=venue.edit&a_id=' . (int) $row->id . '&return=' . base64_encode(Uri::getInstance()->toString()));
    }
}

if (!function_exists('jem_venueslist_responsive_venue_image')) {
    function jem_venueslist_responsive_venue_image($row)
    {
        $image = JemImage::flyercreator($row->locimage ?? '', 'venue');

        if (empty($image)) {
            return '';
        }

        $src = !empty($image['thumb']) && is_file(JPATH_SITE . '/' . $image['thumb']) ? $image['thumb'] : $image['original'];
        $alt = htmlspecialchars((string) ($row->venue ?? ''), ENT_QUOTES, 'UTF-8');

        return '<img src="' . htmlspecialchars(Uri::root(true) . '/' . ltrim($src, '/'), ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="jem-venueslist-venue-image" loading="lazy" />';
    }
}

if (!function_exists('jem_venueslist_responsive_type_badge')) {
    function jem_venueslist_responsive_type_badge($row)
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

if (!function_exists('jem_venueslist_responsive_venue_map')) {
    function jem_venueslist_responsive_venue_map($row)
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
        $modalId = 'jem-venueslist-map-responsive-' . (int) ($row->id ?? 0);

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

if (!function_exists('jem_venueslist_responsive_display_order')) {
    function jem_venueslist_responsive_display_order($params)
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

        if (!(int) $params->get('showcountry', 1)) {
            $displayOrder = array_values(array_diff($displayOrder, array('country')));
        }

        if (!(int) $params->get('showstate', 0)) {
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

$displayOrder = jem_venueslist_responsive_display_order($this->params);
$showVenueImage = (bool) $this->params->get('showvenueimage', 1);
$showTypes = (bool) $this->params->get('showtypes', 1);
$showVenueMap = (bool) $this->params->get('showvenuemap', 1);
$showCalendarColumn = (bool) $this->params->get('showvenuecalendar', 1);
$mediaRoot = rtrim(Uri::root(true), '/');
$calendarIcon = $mediaRoot . '/media/com_jem/images/el.webp';
$editIcon = $mediaRoot . '/media/com_jem/images/calendar_edit.webp';
$user = JemFactory::getUser();
$this->rows = $this->getRows();
$showEditColumn = false;

foreach ((array) $this->rows as $venueRow) {
    if ($user->can('edit', 'venue', (int) $venueRow->id, (int) ($venueRow->created_by ?? 0))) {
        $showEditColumn = true;
        break;
    }
}
?>
<?php if (jem_common_show_filter($this) && !JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')): ?>
  <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start jem-venueslist-filter">
    <div class="jem-venueslist-filter-label">
      <?php echo '<label for="filter">'.Text::_('COM_JEM_FILTER').'</label>'; ?>
    </div>
    <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-search">
      <?php echo $this->lists['filter']; ?>
      <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8');?>" class="inputbox form-control" onchange="document.adminForm.submit();" />
    </div>
    <?php if (!empty($this->lists['country_filter'])) : ?>
    <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-country">
      <label for="filter_country"><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
      <?php echo $this->lists['country_filter']; ?>
    </div>
    <?php endif; ?>
    <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-actions">
      <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
      <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';var c=document.getElementById('filter_country');if(c){c.selectedIndex=0;}this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
    </div>
          <?php if ($this->settings->get('global_display',1)) : ?>
    <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-limit">
        <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
        <?php echo $this->pagination->getLimitBox(); ?>
    </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<div class="jem-sort jem-sort-small">
    <div class="jem-list-row jem-small-list">
        <?php foreach ($displayOrder as $field) : ?>
            <?php if ($field === 'image') : ?>
                <div id="jem_venue_image" class="sectiontableheader"><?php echo Text::_('COM_JEM_IMAGE'); ?></div>
            <?php elseif ($field === 'venue') : ?>
                <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'a.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
            <?php elseif ($field === 'type') : ?>
                <div id="jem_type" class="sectiontableheader"><?php echo Text::_('COM_JEM_TYPE'); ?></div>
            <?php elseif ($field === 'city') : ?>
                <div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'a.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
            <?php elseif ($field === 'state') : ?>
                <div id="jem_state" class="sectiontableheader"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'a.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
            <?php elseif ($field === 'country') : ?>
                <div id="jem_country" class="sectiontableheader"><i class="fa fa-globe" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'a.country', $this->lists['order_Dir'], $this->lists['order']); ?></div>
            <?php elseif ($field === 'map') : ?>
                <div id="jem_map" class="sectiontableheader jem-event-action jem-event-map-action"><?php echo Text::_('COM_JEM_MAP'); ?></div>
            <?php elseif ($field === 'calendar') : ?>
                <div id="jem_calendar" class="sectiontableheader jem-event-action jem-event-calendar-action"><?php echo Text::_('COM_JEM_CALENDAR'); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($showEditColumn) : ?>
            <div id="jem_edit" class="sectiontableheader jem-event-action jem-event-edit-action"><?php echo Text::_('JGLOBAL_EDIT'); ?></div>
        <?php endif; ?>
    </div>
</div>

<ul class="eventlist">
  <?php if ($this->novenues == 1) : ?>
    <li class="jem-event"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></li>
  <?php else : ?>
      <?php
      // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
        // This detects the browser and just writes the onclick attribute if the browser is not Safari.
      $isSafari = false;
      if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
        $isSafari = true;
      }
      ?>
            <?php foreach ($this->rows as $row) : ?>
            <?php
            // has user access
            $venueaccess = '';
            if (!$row->user_has_access_venue) {
                // show a closed lock icon
                $venueaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
            }
            $canEditVenue = $user->can('edit', 'venue', (int) $row->id, (int) ($row->created_by ?? 0));
            ?>
                <?php if (!empty($row->featured)) :   ?>
                  <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx') . ' venue_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Event"  >
                <?php else : ?>
                    <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($row->odd +1) . $this->params->get('pageclass_sfx') . ' venue_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Event"  >
                <?php endif; ?>

                <?php foreach ($displayOrder as $field) : ?>
                    <?php if ($field === 'image') : ?>
                        <div class="jem-event-info-small jem-event-venue-image">
                            <?php echo jem_venueslist_responsive_venue_image($row) ?: '-'; ?>
                        </div>
                    <?php elseif ($field === 'venue') : ?>
                        <?php if (!empty($row->id)) : ?>
                          <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                            <i class="fa fa-map-marker" aria-hidden="true"></i>
                                <?php
                                if ($this->jemsettings->showlinkvenue == 1) :
                                    echo $row->id != 0 ? "<a href='".jem_venueslist_responsive_venue_page_link($row)."'>".$this->escape($row->venue)."</a>" : '-';
                                else :
                                    echo $row->id ? $this->escape($row->venue) : '-';
                                 endif; ?>
                                <?php echo JemOutput::publishstateicon($row); ?>
                            <?php echo $venueaccess;?>
                            </div>
                        <?php else : ?>
                          <div class="jem-event-info-small jem-event-venue">
                            <i class="fa fa-map-marker" aria-hidden="true"></i>
                                <?php
                                if ($this->jemsettings->showlinkvenue == 1) :
                                    echo $row->id != 0 ? "<a href='".jem_venueslist_responsive_venue_page_link($row)."'>".$this->escape($row->venue)."</a>" : '-';
                                else :
                                    echo $row->id ? $this->escape($row->venue) : '-';
                                 endif; ?>
                                <?php echo JemOutput::publishstateicon($row); ?>
                            <?php echo $venueaccess;?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($field === 'type') : ?>
                        <div class="jem-event-info-small jem-event-type">
                            <?php echo jem_venueslist_responsive_type_badge($row) ?: '-'; ?>
                        </div>
                    <?php elseif ($field === 'city') : ?>
                        <?php if (!empty($row->city)) : ?>
                          <div class="jem-event-info-small jem-event-city venue-big" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                            <?php echo $this->escape($row->city); ?>
                          </div>
                        <?php else : ?>
                          <div class="jem-event-info-small jem-event-city">-</div>
                        <?php endif; ?>
                    <?php elseif ($field === 'state') : ?>
                        <?php if (!empty($row->state)) : ?>
                        <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                            <?php echo $this->escape($row->state); ?>
                        </div>
                        <?php else : ?>
                        <div class="jem-event-info-small jem-event-state">-</div>
                        <?php endif; ?>
                    <?php elseif ($field === 'country') : ?>
                        <div class="jem-event-info-small jem-event-country" title="<?php echo Text::_('COM_JEM_COUNTRY') . ': ' . $this->escape(jem_venueslist_country_name($row->country ?? '')); ?>">
                            <?php $countryName = jem_venueslist_country_name($row->country ?? ''); ?>
                            <?php echo $countryName !== '' ? jem_venueslist_country_flag($row->country ?? '', $countryName) . $this->escape($countryName) : '-'; ?>
                        </div>
                    <?php elseif ($field === 'map') : ?>
                        <div class="jem-event-info-small jem-event-action jem-event-map-action" title="<?php echo Text::_('COM_JEM_MAP'); ?>">
                            <?php echo jem_venueslist_responsive_venue_map($row) ?: '-'; ?>
                        </div>
                    <?php elseif ($field === 'calendar') : ?>
                        <div class="jem-event-info-small jem-event-action jem-event-calendar-action" title="<?php echo Text::_('COM_JEM_CALENDAR'); ?>">
                            <a href="<?php echo htmlspecialchars(jem_venueslist_responsive_venue_calendar_link($row), ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars($calendarIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_CALENDAR'); ?>" class="jem-venuesmap-action-icon" />
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($showEditColumn) : ?>
                    <div class="jem-event-info-small jem-event-action jem-event-edit-action" title="<?php echo Text::_('COM_JEM_EDIT_VENUE'); ?>">
                        <?php if ($canEditVenue) : ?>
                            <a href="<?php echo htmlspecialchars(jem_venueslist_responsive_venue_edit_link($row), ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars($editIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_EDIT_VENUE'); ?>" class="jem-venuesmap-action-icon" />
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <meta itemprop="name" content="<?php echo $this->escape($row->venue); ?>" />
                <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getVenueRoute($row->venueslug)); ?>" />
                <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getVenueRoute($row->venueslug)); ?>" />
                <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;" >
                <?php if (!empty($row->venue)) : ?>
                    <meta itemprop="name" content="<?php echo $this->escape($row->venue); ?>" />
                <?php endif;
                
                $microadress = '';
                if (!empty($row->city)) {
                    $microadress .= $this->escape($row->city);
                }
                if (!empty($microadress)) {
                    $microadress .= ', ';
                }
                if (!empty($row->state)) {
                    $microadress .= $this->escape($row->state);
                }
                if (!empty($row->country)) {
                    if (!empty($microadress)) {
                        $microadress .= ', ';
                    }
                    $microadress .= $this->escape(jem_venueslist_country_name($row->country));
                }
                if (empty($microadress)) {
                    $microadress .= '-';
                }
                ?>
                <meta itemprop="address" content="<?php echo $microadress; ?>" />
              </div>
        </li>
            <?php endforeach; ?>
  <?php endif; ?>
</ul>

<div class="pagination">
    <?php echo $this->pagination->getPagesLinks(); ?>
</div>
