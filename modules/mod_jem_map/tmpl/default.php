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
$map_id      = 'leafletmap-' . uniqid();
$isDateMode  = isset($filterDate) && $filterDate !== null;
$currentDate = $isDateMode ? $filterDate : '';
$youAreHere  = Text::_('MOD_JEM_MAP_YOU_ARE_HERE');

$document->addStyleSheet(Uri::base() .'media/com_jem/css/leaflet.css');
$document->addScript(Uri::base() . 'media/com_jem/js/leaflet.js');

JemHelper::loadModuleStyleSheet('mod_jem_map', 'mod_jem_map');
?>
<?php if (!empty($showDateFilter)): ?>
<form method="get" class="jem-date-filter" style="margin:0 0 12px 0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
  <label style="display:flex; gap:6px; align-items:center;">
    <input type="radio" name="jemfilter" value="all" <?= $isDateMode ? '' : 'checked' ?>>
    <span><?php echo Text::_('MOD_JEM_MAP_ALL');?></span>
  </label>

  <label style="display:flex; gap:6px; align-items:center;">
    <input type="radio" name="jemfilter" value="date" <?= $isDateMode ? 'checked' : '' ?>>
    <span><?php echo Text::_('MOD_JEM_MAP_DATE');?></span>
    <input type="date" name="jemdate" value="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>">
  </label>

  <button type="submit"><?php echo Text::_('MOD_JEM_MAP_APPLY');?></button>
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

  var map = L.map('<?= $map_id ?>').setView([46.20739775977303, 6.155887437523999], <?= (int)$zoom ?>);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // "Je bent hier" – backend-zoom + instelbare marker
  map.locate({ setView: false });
  map.on('locationfound', function(e) {
    map.setView(e.latlng, <?= (int)$zoom ?>);

    L.circle(e.latlng, {
      radius: e.accuracy,
      color: 'red',
      fillColor: '#3399ff',
      fillOpacity: 0.2
    }).addTo(map);

    L.marker(e.latlng, {
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
  });
  map.on('locationerror', function(e){ console.warn('Geolocatie mislukt:', e.message); });

  <?php foreach ($venues as $v):
    $route = 'index.php?option=com_jem&view=venue&id=' . (int)$v->id . ':' . $v->alias;
    if (!empty($jemItemid)) { $route .= '&Itemid=' . (int)$jemItemid; }
    $sef  = Route::_($route, false);
    $link = Uri::root() . ltrim($sef, '/');

    $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
    $city      = htmlspecialchars($v->city, ENT_QUOTES);
    $country   = htmlspecialchars($v->country, ENT_QUOTES);

    $popupHtml =
      '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
      . $city . '<br>'
      . '<img src="/media/com_jem/images/flags/w20-png/' . strtolower($country) . '.png" alt="' . $country . '"/><br>'
      . '<a href="https://maps.google.com/?daddr=' . (float)$v->latitude . ',' . (float)$v->longitude . '">Navigeren</a>';
  ?>
  L.marker([<?= (float)$v->latitude ?>, <?= (float)$v->longitude ?>], {
    icon: L.icon({
      iconUrl: "<?= addslashes($venueMarker) ?>",
      iconSize: [32,32], iconAnchor:[16,32], popupAnchor:[0,-32]
    })
  }).addTo(map).bindPopup(<?= json_encode($popupHtml) ?>);
  <?php endforeach; ?>
});
</script>
