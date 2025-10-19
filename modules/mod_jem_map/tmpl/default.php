<?php
/**
 * @package    JEM
 * @subpackage JEM Calendar Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

$app         = Factory::getApplication();
$document    = $app->getDocument();
$wa          = $document->getWebAssetManager();

$wa->registerAndUseScript('leaflet', 'media/com_jem/js/leaflet.js');
$wa->registerAndUseScript('leaflet.heat', 'media/com_jem/js/leaflet-heat.js');
$wa->registerAndUseStyle('mod_jem.leaflet', 'media/com_jem/css/leaflet.css');

JemHelper::loadModuleStyleSheet('mod_jem_map', 'mod_jem_map');
$jemsettings = JemHelper::config();

$map_id      = 'leafletmap-' . uniqid();
$isDateMode  = isset($filterDate) && $filterDate !== null;
$currentDate = $isDateMode ? $filterDate : '';
$youAreHere  = Text::_('MOD_JEM_MAP_YOU_ARE_HERE');

$startLat    = (float) $params->get('map_center_lat', '52.1');
$startLng    = (float) $params->get('map_center_lng', '5.3');
$startZoom   = (int)   $params->get('map_zoom', '7');
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
            $isActive = ($value === 'date') ? $isDateMode : !$isDateMode;
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
                <input type="radio" class="btn-check" name="jem_map_filter_mode" value="<?= $value ?>"
                       id="filter-<?= $value ?>" <?= ($isActive && $value == $filterMode) ? 'checked' : '' ?>>
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
    </form>
<?php endif; ?>

<div id="<?= $map_id ?>" style="width:100%; height:<?= htmlspecialchars($height, ENT_QUOTES) ?>;"></div>

<script>
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

        var map = L.map('<?= $map_id ?>').setView([<?php echo (float) $startLat; ?>, <?php echo (float) $startLng; ?>], <?php echo (int) $startZoom; ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Variables for location marker
        var locationMarker = null;
        var locationCircle = null;

        // Function to center on user location
        function locateUser() {
            map.locate({setView: true, maxZoom: 15});
        }

        // Location button event
        document.getElementById('locate-me-btn').addEventListener('click', function() {
            locateUser();
        });

        // Handle location found
        map.on('locationfound', function(e) {
            // Remove previous markers if they exist
            if (locationMarker) {
                map.removeLayer(locationMarker);
            }
            if (locationCircle) {
                map.removeLayer(locationCircle);
            }

            // Create accuracy circle
            locationCircle = L.circle(e.latlng, {
                radius: e.accuracy,
                color: 'red',
                fillColor: '#3399ff',
                fillOpacity: 0.2
            }).addTo(map);

            // Create location marker
            locationMarker = L.marker(e.latlng, {
                icon: L.icon({
                    iconUrl: "<?= addslashes($mylocMarker) ?>",
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowUrl: "media/com_jem/images/marker-shadow.png",
                    shadowSize: [41, 41],
                    shadowAnchor: [12, 41]
                })
            }).addTo(map).bindPopup(<?= json_encode($youAreHere) ?>).openPopup();

            // Center map on location
            map.setView(e.latlng, Math.max(map.getZoom(), 15));
        });

        // Handle geolocation error
        map.on('locationerror', function(e){
            console.warn('Geolocation failed:', e.message);
            alert('<?= Text::_('MOD_JEM_MAP_LOCATION_ERROR') ?>');
        });

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
                . '<a href="https://maps.google.com/?daddr=' . (float)$v->latitude . ',' . (float)$v->longitude . '">'
                . Text::_('MOD_JEM_MAP_NAVIGATE') . '</a>';
        ?>
        L.marker([<?= (float)$v->latitude ?>, <?= (float)$v->longitude ?>], {
            icon: L.icon({
                iconUrl: "<?= addslashes($venueMarker) ?>",
                iconSize: [32,32], iconAnchor:[16,32], popupAnchor:[0,-32]
            })
        }).addTo(map).bindPopup(<?= json_encode($popupHtml) ?>);


        <?php   $heatPoints[] = ["lat" => (float)$v->latitude, "lng" => (float)$v->longitude]; ?>

        <?php endforeach; ?>

        var coordinates = <?php echo json_encode($heatPoints); ?>;
        var heatPoints = coordinates.map(function(p) {
            return [p.lat, p.lng, 1]; // 1 = intensity
        });

        L.heatLayer(heatPoints, {
            radius: 25,
            blur: 10,
            maxZoom: 17,
        }).addTo(map);

    });
</script>