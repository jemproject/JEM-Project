<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

$app      = Factory::getApplication();
$document = $app->getDocument();
$wa       = $document->getWebAssetManager();

JemHelper::loadModuleStyleSheet('mod_jem_map', 'mod_jem_map');

$map_id = 'leafletmap-' . uniqid();
$youAreHere = Text::_('MOD_JEM_MAP_YOU_ARE_HERE');
$height = $this->height;
$zoom = (int) $this->zoom;
$heatMapLayer = (int) $this->heatMapLayer;
$venueMarker = $this->venueMarker;
$mylocMarker = $this->mylocMarker;
$jemItemid = (int) $this->jemItemid;
$centerLat = (float) $this->centerLat;
$centerLng = (float) $this->centerLng;
$countries = $this->countries ?? [];
$cities = $this->cities ?? [];
$categories = $this->categories ?? [];
$showCountryFilter = (int) ($this->showCountryFilter ?? 1);
$showCategoryFilter = (int) ($this->showCategoryFilter ?? 1);
$selectedCountry = (string) ($this->selectedCountry ?? '');
$selectedCity = (string) ($this->selectedCity ?? '');
$selectedCategoryId = (int) ($this->selectedCategoryId ?? 0);
$startLat = (float) $this->params->get('map_center_lat', '54.526');
$startLng = (float) $this->params->get('map_center_lng', '15.255');
$startZoom = (int) $this->params->get('map_zoom', '4');
$fullScreenMap = (int) $this->params->get('full_screen_map', '0');
$showMyLocation = (int) $this->params->get('show_my_location', '0');
$showDirectionsLink = (int) $this->params->get('show_directions_link', '1');
$showFullMapLink = (int) $this->params->get('show_full_map_link', '1');
$mapProvider = (string) $this->params->get('map_provider', 'osm');
$mapProvider = $mapProvider === 'google' ? 'google' : 'osm';
$mapType = (string) $this->params->get('map_type', 'political');
$settings = JemHelper::globalattribs();
$googleApiKey = trim((string) $settings->get('global_googleapi', ''));

if ($mapProvider === 'google' && $googleApiKey !== '') {
    $wa->registerAndUseScript('jem.googlemaps.api', 'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($googleApiKey) . '&libraries=visualization');
} else {
    $wa->registerAndUseScript('leaflet', 'media/com_jem/js/leaflet.js');
    $wa->registerAndUseStyle('mod_jem.leaflet', 'media/com_jem/css/leaflet.css');
    $wa->registerAndUseScript('leaflet.fullscreen', 'media/com_jem/js/leaflet-fullscreen.js');
    $wa->registerAndUseStyle('leaflet.fullscreen', 'media/com_jem/css/leaflet-fullscreen.css');
    $wa->registerAndUseScript('leaflet.heat', 'media/com_jem/js/leaflet-heat.js');
}

$tileLayers = [
    'political' => [
        'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'maxZoom' => 19,
    ],
    'physical' => [
        'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        'attribution' => 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, SRTM | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
        'maxZoom' => 17,
    ],
];
$tileLayer = $tileLayers[$mapType] ?? $tileLayers['political'];
$mapLanguage = substr((string) Factory::getLanguage()->getTag(), 0, 2) ?: 'en';
$buildDirectionsLink = static function ($lat, $lng) use ($mapProvider, $mapLanguage) {
    $lat = (float) $lat;
    $lng = (float) $lng;

    if ($mapProvider === 'google') {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($lat . ',' . $lng);
    }

    return 'https://routing.openstreetmap.de/?z=17&center=' . rawurlencode($lat . ',' . $lng)
        . '&loc=' . rawurlencode($lat . ',' . $lng)
        . '&hl=' . rawurlencode($mapLanguage)
        . '&alt=0&srv=1';
};
$buildFullMapLink = static function ($lat, $lng) use ($mapProvider) {
    $lat = (float) $lat;
    $lng = (float) $lng;

    if ($mapProvider === 'google') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
    }

    return 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $lat)
        . '&mlon=' . rawurlencode((string) $lng)
        . '&zoom=15#map=15/' . rawurlencode((string) $lat) . '/' . rawurlencode((string) $lng);
};
$buildMapActionsHtml = static function ($lat, $lng) use ($showDirectionsLink, $showFullMapLink, $buildDirectionsLink, $buildFullMapLink) {
    $links = [];

    if ($showDirectionsLink) {
        $links[] = '<a href="' . htmlspecialchars($buildDirectionsLink($lat, $lng), ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_('MOD_JEM_MAP_NAVIGATE') . '</a>';
    }

    if ($showFullMapLink) {
        $links[] = '<a href="' . htmlspecialchars($buildFullMapLink($lat, $lng), ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_('COM_JEM_OPEN_FULL_MAP') . '</a>';
    }

    return $links ? implode(' | ', $links) : '';
};
$currentUri = Uri::getInstance()->toString();
$user = JemFactory::getUser();
$mediaRoot = rtrim(Uri::root(true), '/');
$calendarIcon = $mediaRoot . '/media/com_jem/images/el.webp';
$editIcon = $mediaRoot . '/media/com_jem/images/calendar_edit.webp';

$buildVenuePageLink = static function ($venue) use ($jemItemid) {
    $slug = (int) $venue->id . ':' . $venue->alias;
    $route = 'index.php?option=com_jem&view=venue&layout=default&id=' . $slug;

    if (!empty($jemItemid)) {
        $route .= '&Itemid=' . (int) $jemItemid;
    }

    return Route::_($route);
};

$buildVenueCalendarLink = static function ($venue) use ($jemItemid) {
    $slug = (int) $venue->id . ':' . $venue->alias;
    $route = 'index.php?option=com_jem&view=venue&layout=calendar&id=' . $slug;

    if (!empty($jemItemid)) {
        $route .= '&Itemid=' . (int) $jemItemid;
    }

    return Route::_($route);
};

$buildVenueEditLink = static function ($venue) use ($currentUri) {
    return Route::_('index.php?option=com_jem&task=venue.edit&a_id=' . (int) $venue->id . '&return=' . base64_encode($currentUri));
};

$editableVenues = [];
$showEditColumn = false;

foreach (($this->venueslist ?? []) as $venue) {
    $venueId = (int) $venue->id;
    $editableVenues[$venueId] = $user->can('edit', 'venue', $venueId, (int) ($venue->created_by ?? 0));
    $showEditColumn = $showEditColumn || $editableVenues[$venueId];
}

?>


<div id="jem" class="jem_venuesmap<?php echo $this->pageclass_sfx; ?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>

    <?php
    if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading">
            <?php
            echo $this->escape($this->params->get('page_heading')); ?>
        </h1>
    <?php
    endif; ?>

    <div class="clr"></div>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <form method="get" class="jem-date-filter d-flex flex-wrap align-items-center gap-2 mb-3">
        <?php if ($showCountryFilter) : ?>
            <label for="jem-map-filter-country-<?= $map_id ?>" class="form-label mb-0">
                <?= Text::_('COM_JEM_COUNTRY') ?>
            </label>
            <select name="jem_map_filter_country"
                    id="jem-map-filter-country-<?= $map_id ?>"
                    class="form-select form-select-sm auto-submit"
                    style="width: auto;">
                <option value=""><?= Text::_('COM_JEM_SELECT_COUNTRY') ?></option>
                <?php foreach ($countries as $country): ?>
                    <?php
                    $countryValue = (string) $country->country;
                    $countryName = !empty($country->country_name) ? (string) $country->country_name : $countryValue;
                    ?>
                    <option value="<?= htmlspecialchars($countryValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($countryValue === $selectedCountry) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="jem-map-filter-city-<?= $map_id ?>" class="form-label mb-0">
                <?= Text::_('COM_JEM_CITY') ?>
            </label>
            <select name="jem_map_filter_city"
                    id="jem-map-filter-city-<?= $map_id ?>"
                    class="form-select form-select-sm auto-submit"
                    style="width: auto;"
                    <?= ($selectedCountry === '') ? 'disabled' : '' ?>>
                <option value=""><?= Text::_('COM_JEM_SELECT_CITY') ?></option>
                <?php foreach ($cities as $city): ?>
                    <?php $cityValue = (string) $city->city; ?>
                    <option value="<?= htmlspecialchars($cityValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($cityValue === $selectedCity) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cityValue, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if ($showCategoryFilter) : ?>
            <label for="jem-map-filter-category-<?= $map_id ?>" class="form-label mb-0">
                <?= Text::_('COM_JEM_CATEGORY') ?>
            </label>
            <select name="jem_map_filter_catid"
                    id="jem-map-filter-category-<?= $map_id ?>"
                    class="form-select form-select-sm auto-submit"
                    style="width: auto;">
                <option value="0"><?= Text::_('MOD_JEM_MAP_ALL_CATEGORIES') ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category->id ?>" <?= ((int) $category->id === $selectedCategoryId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category->catname, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>

    <?php if (!empty($showMyLocation)): ?>
        <div class="jem-date-filter d-flex flex-wrap align-items-center gap-2 mb-3">
            <button type="button" class="btn btn-info btn-sm" id="locate-me-btn">
                <i class="icon-location"></i> <?= Text::_('MOD_JEM_MAP_SHOW_MY_LOCATION') ?>
            </button>
        </div>

        <!-- Location help text -->
        <div class="alert alert-info small mt-2" id="location-help">
            <i class="icon-info"></i>
            <?= Text::_('MOD_JEM_MAP_LOCATION_HELP') ?>
        </div>

        <!-- Permission instructions -->
        <div class="alert alert-warning small mt-1" id="permission-instructions" style="display: none;">
            <i class="icon-warning"></i>
            <?= Text::_('MOD_JEM_MAP_PERMISSION_INSTRUCTIONS') ?>
        </div>
    <?php endif; ?>



    <div id="<?= $map_id ?>" class="jem-venuesmap-canvas" style="width:100%; height:<?= htmlspecialchars($height, ENT_QUOTES) ?>; min-height:300px;"></div>

    <?php if (empty($this->venueslist)) : ?>
        <div class="alert alert-info small mt-2">
            <?php echo Text::_('COM_JEM_NOVENUES'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($this->venueslist)) : ?>
        <div class="table table-responsive table-striped table-hover table-sm jem-venuesmap-list">
            <table class="eventtable table table-striped" style="width:100%;" summary="<?php echo Text::_('COM_JEM_VENUESMAP_PAGETITLE'); ?>">
                <thead>
                    <tr>
                        <th style="text-align:left;"><?php echo Text::_('COM_JEM_VENUE'); ?></th>
                        <th style="text-align:left;"><?php echo Text::_('COM_JEM_CITY'); ?></th>
                        <th style="text-align:left;"><?php echo Text::_('COM_JEM_COUNTRY'); ?></th>
                        <th class="center" style="width:1%;"><?php echo Text::_('COM_JEM_CALENDAR'); ?></th>
                        <?php if ($showEditColumn) : ?>
                            <th class="center" style="width:1%;"><?php echo Text::_('COM_JEM_EDIT_VENUE'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->venueslist as $venue) : ?>
                        <?php
                        $venueName = $this->escape($venue->venue);
                        $countryName = JemHelperCountries::getCountryName($venue->country) ?: $venue->country;
                        $canEditVenue = $editableVenues[(int) $venue->id] ?? false;
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo htmlspecialchars($buildVenuePageLink($venue), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $venueName; ?>
                                </a>
                            </td>
                            <td><?php echo $venue->city !== '' ? $this->escape($venue->city) : '-'; ?></td>
                            <td><?php echo $countryName !== '' ? $this->escape($countryName) : '-'; ?></td>
                            <td class="center">
                                <a href="<?php echo htmlspecialchars($buildVenueCalendarLink($venue), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_JEM_CALENDAR'); ?>">
                                    <img src="<?php echo htmlspecialchars($calendarIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_CALENDAR'); ?>" class="jem-venuesmap-action-icon" />
                                    <span class="visually-hidden"><?php echo Text::_('COM_JEM_CALENDAR'); ?></span>
                                </a>
                            </td>
                            <?php if ($showEditColumn) : ?>
                                <td class="center">
                                    <?php if ($canEditVenue) : ?>
                                        <a href="<?php echo htmlspecialchars($buildVenueEditLink($venue), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_JEM_EDIT_VENUE'); ?>">
                                            <img src="<?php echo htmlspecialchars($editIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo Text::_('COM_JEM_EDIT_VENUE'); ?>" class="jem-venuesmap-action-icon" />
                                            <span class="visually-hidden"><?php echo Text::_('COM_JEM_EDIT_VENUE'); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!--footer-->
        <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php
        echo JemOutput::footer(); ?>
    </div>

    <div class="pagination">
        <?php
        echo $this->pagination->getPagesLinks(); ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.auto-submit').forEach(function(control) {
            control.addEventListener('change', function() {
                if (control.name === 'jem_map_filter_country') {
                    var city = document.getElementById('jem-map-filter-city-<?= $map_id ?>');
                    if (city) {
                        city.value = '';
                        city.disabled = control.value === '';
                    }
                }

                control.closest('form').submit();
            });
        });

        var mapElement = document.getElementById('<?= $map_id ?>');
        if (!mapElement) {
            return;
        }

        <?php if ($mapProvider === 'google' && $googleApiKey !== '') : ?>
        if (typeof google === 'undefined' || !google.maps) {
            return;
        }

        var mapTypeId = <?= json_encode($mapType === 'physical' ? 'terrain' : 'roadmap') ?>;
        var map = new google.maps.Map(mapElement, {
            center: {lat: <?php echo (float) ($centerLat ? $centerLat : $startLat); ?>, lng: <?php echo (float) ($centerLng ? $centerLng : $startLng); ?>},
            zoom: <?php echo (int) $startZoom; ?>,
            mapTypeId: mapTypeId,
            fullscreenControl: <?= $fullScreenMap ? 'true' : 'false' ?>
        });
        var infoWindow = new google.maps.InfoWindow();
        var bounds = new google.maps.LatLngBounds();
        var hasBounds = false;
        var venueIcon = {
            url: <?= json_encode($venueMarker) ?>,
            scaledSize: new google.maps.Size(32, 32),
            anchor: new google.maps.Point(16, 32)
        };
        var mylocIcon = {
            url: <?= json_encode($mylocMarker) ?>,
            scaledSize: new google.maps.Size(32, 32),
            anchor: new google.maps.Point(16, 32)
        };

        var locationMarker = null;
        var locationCircle = null;
        var locationRequested = false;

        function showLocationError(message) {
            alert(message);
            var locateBtn = document.getElementById('locate-me-btn');
            if (locateBtn) {
                locateBtn.innerHTML = '<i class="icon-location"></i> <?= Text::_("MOD_JEM_MAP_SHOW_MY_LOCATION") ?>';
                locateBtn.disabled = false;
            }
        }

        function showPermissionInstructions() {
            var instructions = document.getElementById('permission-instructions');
            var help = document.getElementById('location-help');
            if (instructions && help) {
                help.style.display = 'none';
                instructions.style.display = 'block';
            }
        }

        function hidePermissionInstructions() {
            var instructions = document.getElementById('permission-instructions');
            var help = document.getElementById('location-help');
            if (instructions && help) {
                instructions.style.display = 'none';
                help.style.display = 'block';
            }
        }

        function locateUser() {
            if (!navigator.geolocation) {
                showLocationError('<?= Text::_("MOD_JEM_MAP_GEOLOCATION_NOT_SUPPORTED") ?>');
                return;
            }

            var locateBtn = document.getElementById('locate-me-btn');
            if (!locateBtn) {
                return;
            }

            var originalText = locateBtn.innerHTML;
            locateBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> <?= Text::_("MOD_JEM_MAP_LOCATING") ?>';
            locateBtn.disabled = true;
            locationRequested = true;
            setTimeout(showPermissionInstructions, 1000);

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    hidePermissionInstructions();
                    var latLng = {lat: position.coords.latitude, lng: position.coords.longitude};

                    if (locationMarker) {
                        locationMarker.setMap(null);
                    }
                    if (locationCircle) {
                        locationCircle.setMap(null);
                    }

                    locationCircle = new google.maps.Circle({
                        map: map,
                        center: latLng,
                        radius: position.coords.accuracy,
                        strokeColor: 'red',
                        fillColor: '#3399ff',
                        fillOpacity: 0.2
                    });

                    locationMarker = new google.maps.Marker({
                        position: latLng,
                        map: map,
                        icon: mylocIcon,
                        title: <?= json_encode($youAreHere) ?>
                    });
                    infoWindow.setContent(<?= json_encode($youAreHere) ?>);
                    infoWindow.open(map, locationMarker);
                    map.setCenter(latLng);
                    map.setZoom(Math.max(map.getZoom(), 15));
                    locateBtn.innerHTML = originalText;
                    locateBtn.disabled = false;
                    locationRequested = false;
                },
                function(error) {
                    hidePermissionInstructions();
                    var errorMessage = '';

                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_PERMISSION_DENIED") ?>';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_POSITION_UNAVAILABLE") ?>';
                            break;
                        case error.TIMEOUT:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_TIMEOUT") ?>';
                            break;
                        default:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_LOCATION_ERROR") ?>';
                    }

                    showLocationError(errorMessage);
                    locationRequested = false;
                },
                {
                    enableHighAccuracy: false,
                    timeout: 20000,
                    maximumAge: 300000
                }
            );
        }

        var locateBtn = document.getElementById('locate-me-btn');
        if (locateBtn) {
            locateBtn.addEventListener('click', function() {
                if (!locationRequested) {
                    locateUser();
                }
            });
        }

        <?php
        $heatPoints = [];
        $mapBounds = [];
        foreach ($this->venueslist as $v):
        $link = htmlspecialchars($buildVenuePageLink($v), ENT_QUOTES, 'UTF-8');
        $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
        $city = htmlspecialchars($v->city, ENT_QUOTES);
        $country = htmlspecialchars($v->country, ENT_QUOTES);
        $mapActionsHtml = $buildMapActionsHtml($v->latitude, $v->longitude);
        $popupHtml =
            '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
            . $city . '<br>'
            . '<img src="/media/com_jem/images/flags/w20-png/' . strtolower($country) . '.png" alt="' . $country . '"/><br>'
            . $mapActionsHtml;
        ?>
        (function() {
            var position = {lat: <?= (float)$v->latitude ?>, lng: <?= (float)$v->longitude ?>};
            var marker = new google.maps.Marker({
                position: position,
                map: map,
                icon: venueIcon
            });
            marker.addListener('click', function() {
                infoWindow.setContent(<?= json_encode($popupHtml) ?>);
                infoWindow.open(map, marker);
            });
            bounds.extend(position);
            hasBounds = true;
        })();
        <?php $heatPoints[] = ['lat' => (float) $v->latitude, 'lng' => (float) $v->longitude]; ?>
        <?php $mapBounds[] = [(float) $v->latitude, (float) $v->longitude]; ?>
        <?php endforeach; ?>

        <?php if ($heatMapLayer) : ?>
        if (google.maps.visualization && hasBounds) {
            var heatPoints = <?php echo json_encode($heatPoints); ?>.map(function(point) {
                return new google.maps.LatLng(point.lat, point.lng);
            });
            new google.maps.visualization.HeatmapLayer({
                data: heatPoints,
                radius: 25,
                map: map
            });
        }
        <?php endif; ?>

        if (hasBounds && <?= ($selectedCountry !== '' || (int) $this->params->get('map_auto_center', 1)) ? 'true' : 'false' ?>) {
            map.fitBounds(bounds);
            if (<?= count($mapBounds) ?> === 1) {
                google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
                    map.setZoom(Math.max(map.getZoom(), 10));
                });
            }
        }
        <?php else : ?>
        if (typeof L === 'undefined') {
            mapElement.innerHTML = '<div class="alert alert-warning"><?= Text::_('COM_JEM_VENUESMAP_MAP_UNAVAILABLE') ?></div>';
            return;
        }

        var map = L.map('<?= $map_id ?>').setView([<?php echo (float) ($centerLat? $centerLat : $startLat); ?>, <?php echo (float)($centerLng? $centerLng : $startLng); ?>], <?php echo (int) $startZoom; ?>);
        L.tileLayer(<?= json_encode($tileLayer['url']) ?>, {
            maxZoom: <?= (int) $tileLayer['maxZoom'] ?>,
            attribution: <?= json_encode($tileLayer['attribution']) ?>
        }).addTo(map);

        setTimeout(function() {
            map.invalidateSize();
        }, 0);
        window.addEventListener('load', function() {
            map.invalidateSize();
        });

        <?php if ($fullScreenMap) : ?>
        if (L.control && typeof L.control.fullscreen === 'function') {
            L.control.fullscreen({
                position: 'topleft',
                title: '<?= Text::_("MOD_JEM_MAP_FULLSCREEN_TITLE") ?>',
                titleCancel: '<?= Text::_("MOD_JEM_MAP_FULLSCREEN_EXIT") ?>',
                content: null,
                forceSeparateButton: true
            }).addTo(map);
        }
        <?php endif; ?>

        var locationMarker = null;
        var locationCircle = null;
        var locationRequested = false;

        function showLocationError(message) {
            alert(message);
            var locateBtn = document.getElementById('locate-me-btn');
            if (locateBtn) {
                locateBtn.innerHTML = '<i class="icon-location"></i> <?= Text::_("MOD_JEM_MAP_SHOW_MY_LOCATION") ?>';
                locateBtn.disabled = false;
            }
        }

        function showPermissionInstructions() {
            var instructions = document.getElementById('permission-instructions');
            var help = document.getElementById('location-help');
            if (instructions && help) {
                help.style.display = 'none';
                instructions.style.display = 'block';
            }
        }

        function hidePermissionInstructions() {
            var instructions = document.getElementById('permission-instructions');
            var help = document.getElementById('location-help');
            if (instructions && help) {
                instructions.style.display = 'none';
                help.style.display = 'block';
            }
        }

        function locateUser() {
            if (!navigator.geolocation) {
                showLocationError('<?= Text::_("MOD_JEM_MAP_GEOLOCATION_NOT_SUPPORTED") ?>');
                return;
            }

            var locateBtn = document.getElementById('locate-me-btn');
            if (!locateBtn) {
                return;
            }

            var originalText = locateBtn.innerHTML;
            locateBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> <?= Text::_("MOD_JEM_MAP_LOCATING") ?>';
            locateBtn.disabled = true;
            locationRequested = true;
            setTimeout(showPermissionInstructions, 1000);

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    hidePermissionInstructions();
                    var latlng = L.latLng(position.coords.latitude, position.coords.longitude);

                    if (locationMarker) {
                        map.removeLayer(locationMarker);
                    }
                    if (locationCircle) {
                        map.removeLayer(locationCircle);
                    }

                    locationCircle = L.circle(latlng, {
                        radius: position.coords.accuracy,
                        color: 'red',
                        fillColor: '#3399ff',
                        fillOpacity: 0.2
                    }).addTo(map);

                    locationMarker = L.marker(latlng, {
                        icon: L.icon({
                            iconUrl: "<?= addslashes($mylocMarker) ?>",
                            iconSize: [32, 32],
                            iconAnchor: [16, 32],
                            popupAnchor: [0, -32],
                            shadowUrl: "media/com_jem/images/marker-shadow.webp",
                            shadowSize: [32, 32],
                            shadowAnchor: [16, 32]
                        })
                    }).addTo(map).bindPopup(<?= json_encode($youAreHere) ?>).openPopup();

                    map.setView(latlng, Math.max(map.getZoom(), 15));
                    locateBtn.innerHTML = originalText;
                    locateBtn.disabled = false;
                    locationRequested = false;
                },
                function(error) {
                    hidePermissionInstructions();
                    var errorMessage = '';

                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_PERMISSION_DENIED") ?>';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_POSITION_UNAVAILABLE") ?>';
                            break;
                        case error.TIMEOUT:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_TIMEOUT") ?>';
                            break;
                        default:
                            errorMessage = '<?= Text::_("MOD_JEM_MAP_LOCATION_ERROR") ?>';
                    }

                    showLocationError(errorMessage);
                    locationRequested = false;
                },
                {
                    enableHighAccuracy: false,
                    timeout: 20000,
                    maximumAge: 300000
                }
            );
        }

        var locateBtn = document.getElementById('locate-me-btn');
        if (locateBtn) {
            locateBtn.addEventListener('click', function() {
                if (!locationRequested) {
                    locateUser();
                }
            });
        }

        <?php
        $heatPoints = [];
        $mapBounds = [];
        foreach ($this->venueslist as $v):
        $link = htmlspecialchars($buildVenuePageLink($v), ENT_QUOTES, 'UTF-8');

        $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
        $city = htmlspecialchars($v->city, ENT_QUOTES);
        $country = htmlspecialchars($v->country, ENT_QUOTES);

        $mapActionsHtml = $buildMapActionsHtml($v->latitude, $v->longitude);
        $popupHtml =
            '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
            . $city . '<br>'
            . '<img src="/media/com_jem/images/flags/w20-png/' . strtolower(
                $country
            ) . '.png" alt="' . $country . '"/><br>'
            . $mapActionsHtml;
        ?>
        L.marker([<?= (float)$v->latitude ?>, <?= (float)$v->longitude ?>], {
            icon: L.icon({
                iconUrl: "<?= addslashes($venueMarker) ?>",
                iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32]
            })
        }).addTo(map).bindPopup(<?= json_encode($popupHtml) ?>);
        <?php $heatPoints[] = ['lat' => (float) $v->latitude, 'lng' => (float) $v->longitude]; ?>
        <?php $mapBounds[] = [(float) $v->latitude, (float) $v->longitude]; ?>
        <?php endforeach; ?>

        <?php if ($heatMapLayer) : ?>
        var coordinates = <?php echo json_encode($heatPoints); ?>;
        var heatPoints = coordinates.map(function(p) {
            return [p.lat, p.lng, 1];
        });

        if (typeof L.heatLayer === 'function' && heatPoints.length) {
            L.heatLayer(heatPoints, {
                radius: 25,
                blur: 10,
                maxZoom: 17
            }).addTo(map);
        }
        <?php endif; ?>

        <?php if ($selectedCountry !== '' && !empty($mapBounds)) : ?>
        var venueBounds = <?php echo json_encode($mapBounds); ?>;
        if (venueBounds.length > 1) {
            map.fitBounds(venueBounds, {
                padding: [30, 30],
                maxZoom: 12
            });
        } else if (venueBounds.length === 1) {
            map.setView(venueBounds[0], Math.max(map.getZoom(), 10));
        }
        <?php endif; ?>
        <?php endif; ?>
    });
</script>
