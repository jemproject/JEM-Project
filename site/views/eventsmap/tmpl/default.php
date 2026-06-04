<?php
/**
 * @package    JEM
 * @subpackage JEM Event Map View
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright https://leafletjs.com/
 * @copyright https://github.com/brunob/leaflet.fullscreen
 * @copyright https://github.com/Leaflet/Leaflet.heat
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$app         = Factory::getApplication();
$document    = $app->getDocument();
$wa          = $document->getWebAssetManager();

$wa->registerAndUseScript('leaflet', 'media/com_jem/js/leaflet.js');
$wa->registerAndUseStyle('mod_jem.leaflet', 'media/com_jem/css/leaflet.css');

$wa->registerAndUseScript('leaflet.fullscreen', 'media/com_jem/js/leaflet-fullscreen.js');
$wa->registerAndUseStyle('leaflet.fullscreen', 'media/com_jem/css/leaflet-fullscreen.css');
$wa->registerAndUseScript('leaflet.heat', 'media/com_jem/js/leaflet-heat.js');

JemHelper::loadModuleStyleSheet('mod_jem_map', 'mod_jem_map');

$jemsettings  = JemHelper::config();
$map_id        = 'leafletmap-' . uniqid();
$showDateFilter = (int) $this->showDateFilter;
$showCategoryFilter = (int) ($this->showCategoryFilter ?? 0);
$showCountryFilter = (int) ($this->showCountryFilter ?? 0);
$categories    = $this->categories ?? [];
$countries     = $this->countries ?? [];
$selectedCategoryId = (int) ($this->selectedCategoryId ?? 0);
$selectedCountry = (string) ($this->selectedCountry ?? '');
$filterMode    = $this->filterMode;
$filterDate    = $this->filterDate;
$isDateMode    = $filterDate !== null;
$currentDate   = $isDateMode ? $filterDate : '';
$youAreHere    = Text::_('MOD_JEM_MAP_YOU_ARE_HERE');
$height        = $this->height;
$centerLat     = (float) $this->centerLat;
$centerLng     = (float) $this->centerLng;
$venueMarker   = $this->venueMarker;
$mylocMarker   = $this->mylocMarker;
$jemItemid     = (int) $this->jemItemid;
$events        = $this->eventslist ?? [];

$startLat      = (float) $this->params->get('map_center_lat', '0');
$startLng      = (float) $this->params->get('map_center_lng', '0');
$startZoom     = (int)   $this->params->get('map_zoom', '10');
$autoCenter    = (int)   $this->params->get('map_auto_center', '1');
$heatMapLayer  = (int)  $this->params->get('heat_layer', '1');
$fullScreenMap = (int)  $this->params->get('full_screen_map', '0');
$showControls  = !empty($showDateFilter) || !empty($showCategoryFilter) || !empty($showCountryFilter) || (int) $this->params->get('show_my_location', '0');
$mapType       = (string) $this->params->get('map_type', 'political');
$tileLayers    = [
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
$eventMarkers = [];

foreach ((array) $events as $event) {
    $lat = (float) $event->latitude;
    $lng = (float) $event->longitude;

    $venueId = (int) ($event->venue_id ?? 0);
    $key = $venueId ? 'venue-' . $venueId : 'coords-' . $lat . '-' . $lng;

    if (!isset($eventMarkers[$key])) {
        $country = htmlspecialchars((string) $event->country, ENT_QUOTES, 'UTF-8');
        $countryFlagPath = rtrim((string) $jemsettings->flagicons_path, '/');
        $countryFlagExtension = substr(strrchr($countryFlagPath, '-'), 1);
        $countryFlagFile = $country && $countryFlagExtension
            ? rtrim(Uri::root(), '/') . '/' . $countryFlagPath . '/' . strtolower($country) . '.' . $countryFlagExtension
            : '';

        $eventMarkers[$key] = [
            'lat' => $lat,
            'lng' => $lng,
            'venue' => htmlspecialchars((string) $event->venue, ENT_QUOTES, 'UTF-8'),
            'city' => htmlspecialchars((string) $event->city, ENT_QUOTES, 'UTF-8'),
            'country' => $country,
            'countryFlag' => $countryFlagFile,
            'events' => [],
        ];
    }

    $slug = !empty($event->slug)
        ? (string) $event->slug
        : (int) $event->id . (!empty($event->alias) ? ':' . $event->alias : '');
    $link = Route::_(JemHelperRoute::getEventRoute($slug), false);
    $dateText = !empty($event->dates) ? JemOutput::formatdate($event->dates) : '';
    $timeText = !empty($event->times) ? JemOutput::formattime($event->times) : '';

    $eventMarkers[$key]['events'][] = [
        'title' => htmlspecialchars((string) $event->title, ENT_QUOTES, 'UTF-8'),
        'link' => htmlspecialchars($link, ENT_QUOTES, 'UTF-8'),
        'date' => htmlspecialchars(trim($dateText . ' ' . $timeText), ENT_QUOTES, 'UTF-8'),
    ];
}
?>


<div id="jem" class="jem_eventsmap<?php echo $this->pageclass_sfx; ?>">
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
                        <input type="radio" class="btn-check"  name="jem_map_filter_mode" value="<?= $value ?>"
                               id="filter-<?= $value ?>" <?= $isActive ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary btn-sm" style="padding-top: 6px;" for="filter-<?= $value ?>">
                            <?= Text::_($label) ?>
                        </label>
                        <input type="date" name="jem_map_filter_date" value="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>"
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
                        <option value="<?= (int) $category->id ?>" <?= ((int) $category->id === $selectedCategoryId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category->catname, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if($this->params->get('show_my_location','0')) { ?>
                <!-- Location button -->
                <button type="button" class="btn btn-info btn-sm" id="locate-me-btn">
                    <i class="icon-location"></i> <?= Text::_('MOD_JEM_MAP_SHOW_MY_LOCATION') ?>
                </button>
            <?php } ?>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <!-- Location help text -->
        <?php if($this->params->get('show_my_location','0')) { ?>
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
</div>

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
    document.addEventListener('DOMContentLoaded', function () {
        <?php if (!empty($showDateFilter)): ?>
        (function () {
            var form = document.querySelector('.jem-date-filter');
            if (!form) return;
            var rAll = form.querySelector('input[name="jem_map_filter_mode"][value="all"]');
            var rDate = form.querySelector('input[name="jem_map_filter_mode"][value="date"]');
            var dateInput = form.querySelector('input[name="jem_map_filter_date"]');
            function sync() {
                var useDate = rDate && rDate.checked;
                if (dateInput) {
                    dateInput.disabled = !useDate;
                    dateInput.required = useDate;
                }
            }
            if (rAll) {
                rAll.addEventListener('change', sync);
            }
            if (rDate) {
                rDate.addEventListener('change', sync);
            }
            sync();
        })();
        <?php endif; ?>

        var map = L.map('<?= $map_id ?>').setView([<?php echo (float) ($centerLat? $centerLat : $startLat); ?>, <?php echo (float)($centerLng? $centerLng : $startLng); ?>], <?php echo (int) $startZoom; ?>);
        L.tileLayer(<?= json_encode($tileLayer['url']) ?>, {
            maxZoom: <?= (int) $tileLayer['maxZoom'] ?>,
            attribution: <?= json_encode($tileLayer['attribution']) ?>
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
                if (!checkGeolocationSupport()) {
                    return;
                }

            // Show loading state
            var locateBtn = document.getElementById('locate-me-btn');
            if (!locateBtn) {
                return;
            }
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
                                shadowUrl: 'media/com_jem/images/marker-shadow.webp',
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
        $mapBounds = [];
        foreach ($eventMarkers as $marker):
        $visibleEvents = array_slice($marker['events'], 0, 5);
        $hiddenEvents = max(0, count($marker['events']) - count($visibleEvents));
        $popupEvents = [];

        foreach ($visibleEvents as $event) {
            $eventLine = '<li><a href="' . $event['link'] . '"><strong>' . $event['title'] . '</strong></a>';

            if ($event['date'] !== '') {
                $eventLine .= '<br><small>' . $event['date'] . '</small>';
            }

            $popupEvents[] = $eventLine . '</li>';
        }

        if ($hiddenEvents > 0) {
            $popupEvents[] = '<li><em>' . Text::sprintf('COM_JEM_EVENTS_MAP_MORE_EVENTS', $hiddenEvents) . '</em></li>';
        }

        $popupHtml = '<strong>' . $marker['venue'] . '</strong>'
            . ($marker['city'] ? ', ' . $marker['city'] : '') . '<br>'
            . ($marker['countryFlag'] ? '<img src="' . $marker['countryFlag'] . '" style="width:40px" alt="' . $marker['country'] . '"/><br>' : '')
            . '<ul class="jem-eventsmap-popup-events">' . implode('', $popupEvents) . '</ul>'
            . '<a href="https://maps.google.com/?daddr=' . (float) $marker['lat'] . ',' . (float) $marker['lng'] . '">'
            . Text::_('MOD_JEM_MAP_NAVIGATE') . '</a>';
        ?>
        L.marker([<?= (float) $marker['lat'] ?>, <?= (float) $marker['lng'] ?>], {
            icon: L.icon({
                iconUrl: "<?= addslashes($venueMarker) ?>",
                iconSize: [32,32], iconAnchor:[16,32], popupAnchor:[0,-32]
            })
        }).addTo(map).bindPopup(<?= json_encode($popupHtml) ?>);

        <?php $heatPoints[] = ['lat' => (float) $marker['lat'], 'lng' => (float) $marker['lng']]; ?>
        <?php $mapBounds[] = [(float) $marker['lat'], (float) $marker['lng']]; ?>
        <?php endforeach; ?>

        <?php if($heatMapLayer) { ?>
        var coordinates = <?php echo json_encode($heatPoints); ?>;
        var heatPoints = coordinates.map(function(p) {
            return [p.lat, p.lng, 1]; // 1 = intensity
        });

        if (typeof L.heatLayer === 'function') {
            L.heatLayer(heatPoints, {
                radius: 25,
                blur: 10,
                maxZoom: 17,
            }).addTo(map);
        }
        <?php } ?>

        <?php if ($autoCenter && !empty($mapBounds)) : ?>
        var eventBounds = <?php echo json_encode($mapBounds); ?>;
        if (eventBounds.length > 1) {
            map.fitBounds(eventBounds, {
                padding: [30, 30],
                maxZoom: 12
            });
        } else if (eventBounds.length === 1) {
            map.setView(eventBounds[0], Math.max(map.getZoom(), 10));
        }
        <?php endif; ?>
    });
</script>
