<?php
/**
 * @package    JEM
 * @subpackage JEM Map Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
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

$wa->registerAndUseScript('leaflet', 'media/com_jem/js/leaflet.js');
$wa->registerAndUseStyle('mod_jem.leaflet', 'media/com_jem/css/leaflet.css');
$wa->registerAndUseScript('leaflet.fullscreen', 'media/com_jem/js/leaflet-fullscreen.js');
$wa->registerAndUseStyle('leaflet.fullscreen', 'media/com_jem/css/leaflet-fullscreen.css');
$wa->registerAndUseScript('leaflet.heat', 'media/com_jem/js/leaflet-heat.js');

JemHelper::loadModuleStyleSheet('mod_jem_map', 'mod_jem_map');
$jemsettings = JemHelper::config();

$map_id       = 'leafletmap-' . uniqid();
$isDateMode  = isset($filterDate) && $filterDate !== null;
$currentDate = $isDateMode ? $filterDate : '';
$youAreHere  = Text::_('MOD_JEM_MAP_YOU_ARE_HERE');

$startLat    = (float) $params->get('map_center_lat', '0');
$startLng    = (float) $params->get('map_center_lng', '0');
$startZoom   = (int)   $params->get('map_zoom', '10');
$heatMapLayer = (int)  $params->get('heat_layer', '0');
$fullScreenMap = (int)  $params->get('full_screen_map', '0');
?>

<?php if (!empty($showDateFilter)): ?>
    <form method="get" class="jem-date-filter d-flex flex-wrap align-items-center gap-2 mb-3">
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
                    <input type="radio" class="btn-check" id="jem_map_filter_date" name="jem_map_filter_mode" value="<?= $value ?>"
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
                if (this.checked) {
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
            $link = Uri::root() . ltrim($sef, '/');

            $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
            $city      = htmlspecialchars($v->city, ENT_QUOTES);
            $country   = htmlspecialchars($v->country, ENT_QUOTES);

            $countryFlagPath = rtrim($jemsettings->flagicons_path, '/');
            $countryFlagExtension = substr(strrchr($countryFlagPath, '-'), 1);
            $countryFlagFile = rtrim(Uri::root(), '/') . '/' . $countryFlagPath . '/' . strtolower($country) . '.' . $countryFlagExtension;

            $popupHtml = '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
                    . $city . '<br>'
                    . '<img src="' . $countryFlagFile . '" style="width:40px" alt="' . $country . '"/><br>'
                    . '<a href="https://maps.google.com/?daddr=' . (float) $v->latitude . ',' . (float) $v->longitude . '">'
                    . Text::_('MOD_JEM_MAP_NAVIGATE') . '</a>';
            ?>
            L.marker([<?= (float) $v->latitude ?>, <?= (float) $v->longitude ?>], {
                icon: L.icon({
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

    });
</script>