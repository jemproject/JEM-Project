<?php
/**
 * @package    JEM
 * @subpackage JEM Map Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright https://leafletjs.com/
 * @copyright https://github.com/brunob/leaflet.fullscreen
 * @copyright https://github.com/Leaflet/Leaflet.heat
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

//require needed component classes
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');

$app         = Factory::getApplication();
$document    = $app->getDocument();
$wa          = $document->getWebAssetManager();

$jemsettings = JemHelper::config();
JemHelper::loadIconFont();

if (!function_exists('jem_map_normalise_marker_color')) {
    function jem_map_normalise_marker_color($color, $fallback = '#d9ddb5')
    {
        $color = trim((string) $color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $fallback;
    }
}

if (!function_exists('jem_map_normalise_icon_class')) {
    function jem_map_normalise_icon_class($icon)
    {
        $icon = trim((string) $icon);

        return $icon !== '' && preg_match('/^[a-zA-Z0-9_-]+(?:\s+[a-zA-Z0-9_-]+)*$/', $icon)
            ? $icon
            : '';
    }
}

$map_id       = 'leafletmap-' . uniqid();
$isDateMode  = isset($filterDate) && $filterDate !== null;
$currentDate = $isDateMode ? $filterDate : '';
$youAreHere  = Text::_('MOD_JEM_MAP_YOU_ARE_HERE');

$startLat    = (float) $params->get('map_center_lat', '0');
$startLng    = (float) $params->get('map_center_lng', '0');
$startZoom   = (int)   $params->get('map_zoom', '10');
$mapProvider = (string) $params->get('map_provider', 'osm');
$mapProvider = $mapProvider === 'google' ? 'google' : 'osm';
$heatMapLayer = (int)  $params->get('heat_layer', '1');
$fullScreenMap = (int)  $params->get('full_screen_map', '0');
$showDirectionsLink = (int) $params->get('show_directions_link', '1');
$showFullMapLink = (int) $params->get('show_full_map_link', '1');
$showControls = !empty($showDateFilter) || !empty($showCategoryFilter) || !empty($showCountryFilter) || (int) $params->get('show_my_location', '0');
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
        $links[] = '<a href="' . htmlspecialchars($buildFullMapLink($lat, $lng), ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_('MOD_JEM_MAP_OPEN_FULL_MAP') . '</a>';
    }

    return $links ? implode(' | ', $links) : '';
};
?>

<?php if ($showControls): ?>
    <form method="get" class="jem-date-filter d-flex flex-wrap align-items-center gap-2 mb-3">
        <?php if (!empty($showCountryFilter)): ?>
            <label for="jem-map-filter-country-<?= $map_id ?>" class="visually-hidden">
                <?= Text::_('MOD_JEM_MAP_COUNTRY_FILTER') ?>
            </label>
            <select name="jem_map_filter_country"
                    id="jem-map-filter-country-<?= $map_id ?>"
                    class="form-select form-select-sm auto-submit"
                    style="width: auto;">
                <option value=""><?= Text::_('MOD_JEM_MAP_ALL_COUNTRIES') ?></option>
                <?php foreach ($countries as $country): ?>
                    <?php
                    $countryCode = (string) $country->country;
                    $countryName = !empty($country->country_name) ? (string) $country->country_name : $countryCode;
                    ?>
                    <option value="<?= htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8') ?>" <?= ($countryCode === (string) $selectedCountry) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if (!empty($showDateFilter)): ?>
        <?php
        $options = [
            'all'      => 'MOD_JEM_MAP_ALL',
            'today'    => 'MOD_JEM_MAP_TODAY',
            'tomorrow' => 'MOD_JEM_MAP_TOMORROW',
            'week'     => 'MOD_JEM_MAP_WEEK',
            'month'    => 'MOD_JEM_MAP_MONTH',
            'year'     => 'MOD_JEM_MAP_YEAR',
            'date'     => 'MOD_JEM_MAP_DATE'
        ];
        foreach ($options as $value => $label) {
            $isActive = ($filterMode === 'date' ) ? $isDateMode : !$isDateMode;
            $isDateField = $value === 'date';
            ?>
            <?php if ($isDateField) { ?>
                <div class="btn-group btn-group-sm" style="margin:0px;" role="group">
                    <input type="radio" class="btn-check" name="jem_map_filter_mode" value="<?= $value ?>"
                           id="filter-<?= $value ?>" <?= $isActive ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary btn-sm" style="padding-top: 4px;" for="filter-<?= $value ?>">
                        <?= Text::_($label) ?>
                    </label>
                    <input type="date" name="jem_map_filter_date" id="jem_map_filter_date_selected" value="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>"
                           class="form-control form-control-sm" style="width: auto;">
                </div>
            <?php } else { ?>
                <input type="radio" class="btn-check auto-submit" name="jem_map_filter_mode" value="<?= $value ?>"
                       id="filter-<?= $value ?>" <?= ($value == $filterMode) ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary btn-sm"  for="filter-<?= $value ?>">
                    <?= Text::_($label) ?>
                </label>
            <?php } ?>
        <?php } ?>

            <button type="submit" class="btn btn-primary btn-sm">
                <?= Text::_('MOD_JEM_MAP_APPLY') ?>
            </button>
        <?php endif; ?>

        <?php if (!empty($showCategoryFilter)): ?>
            <label for="jem-map-filter-category-<?= $map_id ?>" class="visually-hidden">
                <?= Text::_('MOD_JEM_MAP_CATEGORY_FILTER') ?>
            </label>
            <select name="jem_map_filter_catid"
                    id="jem-map-filter-category-<?= $map_id ?>"
                    class="form-select form-select-sm auto-submit"
                    style="width: auto;">
                <option value="0"><?= Text::_('MOD_JEM_MAP_ALL_CATEGORIES') ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category->id ?>" <?= ((int) $category->id === (int) $selectedCategoryId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category->catname, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if($params->get('show_my_location','0')) { ?>
            <!-- Location button -->
            <button type="button" class="btn btn-info btn-sm" id="locate-me-btn">
                <i class="icon-location"></i> <?= Text::_('MOD_JEM_MAP_SHOW_MY_LOCATION') ?>
            </button>
        <?php } ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <!-- Location help text -->
    <?php if($params->get('show_my_location','0')) { ?>
        <div class="alert alert-info small mt-2" id="location-help">
            <i class="icon-info"></i>
            <?= Text::_('MOD_JEM_MAP_LOCATION_HELP') ?>
        </div>

        <!-- Permission instructions -->
        <div class="alert alert-warning small mt-1" id="permission-instructions" style="display: none;">
            <i class="icon-warning"></i>
            <?= Text::_('MOD_JEM_MAP_PERMISSION_INSTRUCTIONS') ?>
        </div>
    <?php } ?>
<?php endif; ?>

<div id="<?= $map_id ?>" style="width:100%; height:<?= htmlspecialchars($height, ENT_QUOTES) ?>;"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.auto-submit').forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.matches('select') || this.checked) {
                    this.closest('form').submit();
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($showDateFilter)): ?>
        (function(){
            var form      = document.querySelector('.jem-date-filter');
            if (!form) return;
            var rAll      = form.querySelector('input[name="jemfilter"][value="all"]');
            var rDate     = form.querySelector('input[name="jemfilter"][value="date"]');
            var dateInput = form.querySelector('input[name="jemdate"]');
            function sync() {
                var useDate = rDate && rDate.checked;
                if (dateInput) {
                    dateInput.disabled = !useDate;
                    dateInput.required = useDate;
                }
            }
            if (rAll)  rAll.addEventListener('change', sync);
            if (rDate) rDate.addEventListener('change', sync);
            sync();
        })();
        <?php endif; ?>

        var mapElement = document.getElementById('<?= $map_id ?>');
        if (!mapElement) {
            return;
        }

        function getVenueTypeIconDetails(iconClass) {
            if (!iconClass) {
                return null;
            }

            var probe = document.createElement('span');
            probe.className = iconClass;
            probe.style.position = 'absolute';
            probe.style.visibility = 'hidden';
            document.body.appendChild(probe);
            var pseudoStyle = window.getComputedStyle(probe, '::before');
            var content = pseudoStyle.content || '';
            var details = {
                glyph: content.replace(/^['"]|['"]$/g, ''),
                fontFamily: pseudoStyle.fontFamily,
                fontWeight: pseudoStyle.fontWeight
            };
            probe.remove();

            return details.glyph && details.glyph !== 'none' && details.glyph !== 'normal' ? details : null;
        }

        function getGoogleVenueTypeMarker(iconClass, color, iconColor) {
            var iconDetails = getVenueTypeIconDetails(iconClass);

            return {
                icon: {
                    path: 'M16 0C7.16 0 0 7.16 0 16c0 12 16 28 16 28s16-16 16-28C32 7.16 24.84 0 16 0z',
                    fillColor: color,
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeOpacity: 1,
                    strokeWeight: 2,
                    anchor: new google.maps.Point(16, 44),
                    labelOrigin: new google.maps.Point(16, 16)
                },
                label: iconDetails ? {
                    text: iconDetails.glyph,
                    color: iconColor,
                    fontFamily: iconDetails.fontFamily,
                    fontSize: '15px',
                    fontWeight: iconDetails.fontWeight
                } : null
            };
        }

        function getLeafletVenueTypeMarker(iconClass, color, iconColor) {
            return L.divIcon({
                className: '',
                html: '<div class="jem-map-type-marker" style="--jem-marker-color:' + color +
                    ';--jem-marker-icon-color:' + iconColor + '">' +
                    '<span class="jem-map-type-marker__icon ' + iconClass + '" aria-hidden="true"></span></div>',
                iconSize: [34, 44],
                iconAnchor: [17, 44],
                popupAnchor: [0, -44]
            });
        }

        <?php if ($mapProvider === 'google' && $googleApiKey !== '') : ?>
        if (typeof google === 'undefined' || !google.maps) {
            return;
        }

        var map = new google.maps.Map(mapElement, {
            center: {lat: <?php echo (float) ($centerLat ? $centerLat : $startLat); ?>, lng: <?php echo (float) ($centerLng ? $centerLng : $startLng); ?>},
            zoom: <?php echo (int) $startZoom; ?>,
            mapTypeId: 'roadmap',
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

        function checkGeolocationSupport() {
            if (!navigator.geolocation) {
                showError('<?= Text::_("MOD_JEM_MAP_GEOLOCATION_NOT_SUPPORTED") ?>');
                return false;
            }
            return true;
        }

        function showError(message) {
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
            if (!checkGeolocationSupport()) return;

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

                    showError(errorMessage);
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
        foreach ($venues as $v):
        $route = 'index.php?option=com_jem&view=venue&id=' . (int)$v->id . ':' . $v->alias;
        if (!empty($jemItemid)) { $route .= '&Itemid=' . (int)$jemItemid; }
        $sef  = Route::_($route, false);
        $base = Uri::base(true);
        if (!empty($base) && strpos($sef, $base) === 0) {
            $sef = substr($sef, strlen($base));
        }
        $link = Uri::root() . ltrim($sef, '/');

        $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
        $city      = htmlspecialchars($v->city, ENT_QUOTES);
        $country   = htmlspecialchars($v->country, ENT_QUOTES);

        $countryFlagPath = rtrim($jemsettings->flagicons_path, '/');
        $countryFlagExtension = substr(strrchr($countryFlagPath, '-'), 1);
        $countryFlagFile = rtrim(Uri::root(), '/') . '/' . $countryFlagPath . '/' . strtolower($country) . '.' . $countryFlagExtension;

        $mapActionsHtml = $buildMapActionsHtml($v->latitude, $v->longitude);
        $venueTypeIcon = jem_map_normalise_icon_class($v->venue_type_icon ?? '');
        $venueTypeColor = jem_map_normalise_marker_color($v->venue_type_color ?? '');
        $venueMarkerColor = jem_map_normalise_marker_color($v->color ?? '', $venueTypeColor);
        $venueMarkerIconColor = JemHelper::getContrastTextColor($venueMarkerColor) ?: '#ffffff';
        $popupHtml = '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
            . $city . '<br>'
            . '<img src="' . $countryFlagFile . '" style="width:40px" alt="' . $country . '"/><br>'
            . $mapActionsHtml;
        ?>
        (function() {
            var position = {lat: <?= (float) $v->latitude ?>, lng: <?= (float) $v->longitude ?>};
            var typeMarker = <?= json_encode($venueTypeIcon) ?>
                ? getGoogleVenueTypeMarker(<?= json_encode($venueTypeIcon) ?>, <?= json_encode($venueMarkerColor) ?>, <?= json_encode($venueMarkerIconColor) ?>)
                : null;
            var marker = new google.maps.Marker({
                position: position,
                map: map,
                icon: typeMarker ? typeMarker.icon : venueIcon,
                label: typeMarker ? typeMarker.label : null
            });
            marker.addListener('click', function() {
                infoWindow.setContent(<?= json_encode($popupHtml) ?>);
                infoWindow.open(map, marker);
            });
            bounds.extend(position);
            hasBounds = true;
        })();
        <?php $heatPoints[] = ["lat" => (float)$v->latitude, "lng" => (float)$v->longitude]; ?>
        <?php endforeach; ?>

        <?php if($heatMapLayer) { ?>
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
        <?php } ?>

        if (hasBounds && <?= (int) $params->get('map_auto_center', '1') ? 'true' : 'false' ?>) {
            map.fitBounds(bounds);
            if (<?= count($heatPoints) ?> === 1) {
                google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
                    map.setZoom(Math.max(map.getZoom(), 10));
                });
            }
        }
        <?php else : ?>
        var map = L.map('<?= $map_id ?>').setView([<?php echo (float) ($centerLat? $centerLat : $startLat); ?>, <?php echo (float)($centerLng? $centerLng : $startLng); ?>], <?php echo (int) $startZoom; ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        <?php if($fullScreenMap) { ?>
        L.control
            .fullscreen({
                position: 'topleft', // change the position: topleft, topright, bottomright or bottomleft, default topleft
                title: '<?= Text::_("MOD_JEM_MAP_FULLSCREEN_TITLE") ?>',
                titleCancel: '<?= Text::_("MOD_JEM_MAP_FULLSCREEN_EXIT") ?>',
                content: null,
                forceSeparateButton: true
            })
            .addTo(map);
        <?php } ?>

        // Variables for location marker
        var locationMarker = null;
        var locationCircle = null;
        var locationRequested = false;

        // Check geolocation support and permissions
        function checkGeolocationSupport() {
            if (!navigator.geolocation) {
                showError('<?= Text::_("MOD_JEM_MAP_GEOLOCATION_NOT_SUPPORTED") ?>');
                return false;
            }
            return true;
        }

        // Show error message
        function showError(message) {
            alert(message);
            var locateBtn = document.getElementById('locate-me-btn');
            if (locateBtn) {
                locateBtn.innerHTML = '<i class="icon-location"></i> <?= Text::_("MOD_JEM_MAP_SHOW_MY_LOCATION") ?>';
                locateBtn.disabled = false;
            }
        }

        // Show permission instructions
        function showPermissionInstructions() {
            var instructions = document.getElementById('permission-instructions');
            var help = document.getElementById('location-help');
            if (instructions && help) {
                help.style.display = 'none';
                instructions.style.display = 'block';
            }
        }

        // Hide permission instructions
        function hidePermissionInstructions() {
            var instructions = document.getElementById('permission-instructions');
            var help = document.getElementById('location-help');
            if (instructions && help) {
                instructions.style.display = 'none';
                help.style.display = 'block';
            }
        }

        // Improved function to center on user location
        function locateUser() {
            if (!checkGeolocationSupport()) return;

            // Show loading state
            var locateBtn = document.getElementById('locate-me-btn');
            var originalText = locateBtn.innerHTML;
            locateBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> <?= Text::_("MOD_JEM_MAP_LOCATING") ?>';
            locateBtn.disabled = true;
            locationRequested = true;

            // Show permission instructions after a short delay
            setTimeout(showPermissionInstructions, 1000);

            // Use browser's native geolocation API
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Success callback - user granted permission
                    hidePermissionInstructions();
                    var latlng = L.latLng(position.coords.latitude, position.coords.longitude);

                    // Remove previous markers if they exist
                    if (locationMarker) {
                        map.removeLayer(locationMarker);
                    }
                    if (locationCircle) {
                        map.removeLayer(locationCircle);
                    }

                    // Create accuracy circle
                    locationCircle = L.circle(latlng, {
                        radius: position.coords.accuracy,
                        color: 'red',
                        fillColor: '#3399ff',
                        fillOpacity: 0.2
                    }).addTo(map);

                    // Create location marker
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

                    // Center map on location with appropriate zoom
                    map.setView(latlng, Math.max(map.getZoom(), 15));

                    // Reset button
                    locateBtn.innerHTML = originalText;
                    locateBtn.disabled = false;
                    locationRequested = false;
                },
                function(error) {
                    // Error callback
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

                    showError(errorMessage);
                    locationRequested = false;
                },
                {
                    enableHighAccuracy: false, // Better for most use cases
                    timeout: 20000, // 20 seconds
                    maximumAge: 300000 // Cache location for 5 minutes
                }
            );
        }

        // Location button event - only add if button exists
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
        foreach ($venues as $v):
        $route = 'index.php?option=com_jem&view=venue&id=' . (int)$v->id . ':' . $v->alias;
        if (!empty($jemItemid)) { $route .= '&Itemid=' . (int)$jemItemid; }
        $sef  = Route::_($route, false);
        $base = Uri::base(true);
        if (!empty($base) && strpos($sef, $base) === 0) {
            $sef = substr($sef, strlen($base));
        }
        $link = Uri::root() . ltrim($sef, '/');

        $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
        $city      = htmlspecialchars($v->city, ENT_QUOTES);
        $country   = htmlspecialchars($v->country, ENT_QUOTES);

        $countryFlagPath = rtrim($jemsettings->flagicons_path, '/');
        $countryFlagExtension = substr(strrchr($countryFlagPath, '-'), 1);
        $countryFlagFile = rtrim(Uri::root(), '/') . '/' . $countryFlagPath . '/' . strtolower($country) . '.' . $countryFlagExtension;

        $mapActionsHtml = $buildMapActionsHtml($v->latitude, $v->longitude);
        $venueTypeIcon = jem_map_normalise_icon_class($v->venue_type_icon ?? '');
        $venueTypeColor = jem_map_normalise_marker_color($v->venue_type_color ?? '');
        $venueMarkerColor = jem_map_normalise_marker_color($v->color ?? '', $venueTypeColor);
        $venueMarkerIconColor = JemHelper::getContrastTextColor($venueMarkerColor) ?: '#ffffff';
        $popupHtml = '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
            . $city . '<br>'
            . '<img src="' . $countryFlagFile . '" style="width:40px" alt="' . $country . '"/><br>'
            . $mapActionsHtml;
        ?>
        L.marker([<?= (float) $v->latitude ?>, <?= (float) $v->longitude ?>], {
            icon: <?= json_encode($venueTypeIcon) ?>
                ? getLeafletVenueTypeMarker(<?= json_encode($venueTypeIcon) ?>, <?= json_encode($venueMarkerColor) ?>, <?= json_encode($venueMarkerIconColor) ?>)
                : L.icon({
                iconUrl: "<?= addslashes($venueMarker) ?>",
                iconSize: [32,32], iconAnchor:[16,32], popupAnchor:[0,-32]
            })
        }).addTo(map).bindPopup(<?= json_encode($popupHtml) ?>);

        <?php   $heatPoints[] = ["lat" => (float)$v->latitude, "lng" => (float)$v->longitude]; ?>
        <?php endforeach; ?>

        <?php if($heatMapLayer) { ?>
        var coordinates = <?php echo json_encode($heatPoints); ?>;
        var heatPoints = coordinates.map(function(p) {
            return [p.lat, p.lng, 1]; // 1 = intensity
        });

        L.heatLayer(heatPoints, {
            radius: 25,
            blur: 10,
            maxZoom: 17,
        }).addTo(map);
        <?php } ?>

        <?php endif; ?>
    });
</script>
