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

  .jem-sort #jem_country,
  #jem .jem-event .jem-event-country {
      flex: 1 20%;
  }

  .jem-sort #jem_calendar,
  #jem .jem-event .jem-event-calendar-action {
      flex: 0 0 6rem;
      justify-content: center;
      text-align: center;
  }

  .jem-sort #jem_edit,
  #jem .jem-event .jem-event-edit-action {
      flex: 0 0 7rem;
      justify-content: center;
      text-align: center;
  }

  #jem .jem-event .jem-event-calendar-action,
  #jem .jem-event .jem-event-edit-action {
      display: flex;
      align-items: center;
  }

  #jem .jem-event .jem-event-calendar-action a,
  #jem .jem-event .jem-event-edit-action a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
  }

</style>


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

if (!function_exists('jem_venueslist_responsive_display_order')) {
    function jem_venueslist_responsive_display_order($params)
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

$displayOrder = jem_venueslist_responsive_display_order($this->params);
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
    <div class="jem-row jem-justify-start jem-nowrap jem-venueslist-filter-actions">
      <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
      <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
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
            <?php if ($field === 'venue') : ?>
                <div id="jem_location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'a.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
            <?php elseif ($field === 'city') : ?>
                <div id="jem_city" class="sectiontableheader"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'a.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>

                <?php if ($this->params->get('showstate')) : ?>
                    <div id="jem_state" class="sectiontableheader"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'a.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
                <?php endif; ?>
            <?php elseif ($field === 'country') : ?>
                <div id="jem_country" class="sectiontableheader"><i class="fa fa-globe" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'a.country', $this->lists['order_Dir'], $this->lists['order']); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($showCalendarColumn) : ?>
            <div id="jem_calendar" class="sectiontableheader jem-event-action jem-event-calendar-action"><?php echo Text::_('COM_JEM_CALENDAR'); ?></div>
        <?php endif; ?>
        <?php if ($showEditColumn) : ?>
            <div id="jem_edit" class="sectiontableheader jem-event-action jem-event-edit-action"><?php echo Text::_('COM_JEM_EDIT_VENUE'); ?></div>
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
                    <?php if ($field === 'venue') : ?>
                        <?php if (!empty($row->locid)) : ?>
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
                    <?php elseif ($field === 'city') : ?>
                        <?php if (!empty($row->city)) : ?>
                          <div class="jem-event-info-small jem-event-city venue-big" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                            <?php echo $this->escape($row->city); ?>
                          </div>
                        <?php else : ?>
                          <div class="jem-event-info-small jem-event-city">-</div>
                        <?php endif; ?>

                        <?php if ($this->params->get('showstate')) : ?>
                            <?php if (!empty($row->state)) : ?>
                            <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                                <?php echo $this->escape($row->state); ?>
                            </div>
                            <?php else : ?>
                            <div class="jem-event-info-small jem-event-state">-</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php elseif ($field === 'country') : ?>
                        <div class="jem-event-info-small jem-event-country" title="<?php echo Text::_('COM_JEM_COUNTRY') . ': ' . $this->escape(jem_venueslist_country_name($row->country ?? '')); ?>">
                            <?php $countryName = jem_venueslist_country_name($row->country ?? ''); ?>
                            <?php echo $countryName !== '' ? jem_venueslist_country_flag($row->country ?? '', $countryName) . $this->escape($countryName) : '-'; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($showCalendarColumn) : ?>
                    <div class="jem-event-info-small jem-event-action jem-event-calendar-action" title="<?php echo Text::_('COM_JEM_CALENDAR'); ?>">
                        <a href="<?php echo htmlspecialchars(jem_venueslist_responsive_venue_calendar_link($row), ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?php echo htmlspecialchars($calendarIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_CALENDAR'); ?>" class="jem-venuesmap-action-icon" />
                        </a>
                    </div>
                <?php endif; ?>

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
                <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
                <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
                <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;" >
                <?php if (!empty($row->locid)) : ?>
                    <meta itemprop="name" content="<?php echo $this->escape($row->venue); ?>" />
                <?php else : ?>
                    <meta itemprop="name" content="None" />
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
