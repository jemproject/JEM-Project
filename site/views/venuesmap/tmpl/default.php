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

$map_id = 'leafletmap-' . uniqid();
$isDateMode = isset($filterDate) && $filterDate !== null;
$currentDate = $isDateMode ? $filterDate : '';
$youAreHere = Text::_('COM_JEM_VENUESAMP_VENUESMAP_YOU_ARE_HERE');

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('stylesheet', 'com_jem/css/leaflet.css', ['relative' => true]);
HTMLHelper::_('script', 'com_jem/js/leaflet.js', ['relative' => true]);

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


    <?php
    if (!empty($showDateFilter)): ?>
        <form method="get" class="jem-date-filter"
              style="margin:0 0 12px 0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <label style="display:flex; gap:6px; align-items:center;">
                <input type="radio" name="jemfilter" value="all" <?= $isDateMode ? '' : 'checked' ?>>
                <span>Alles</span>
            </label>

            <label style="display:flex; gap:6px; align-items:center;">
                <input type="radio" name="jemfilter" value="date" <?= $isDateMode ? 'checked' : '' ?>>
                <span>Datum</span>
                <input type="date" name="jemdate" value="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>">
            </label>

            <button type="submit">Toepassen</button>
        </form>
    <?php
    endif; ?>



    <div id="<?= $map_id ?>" style="width:100%; height:500px;<?= htmlspecialchars($this->height, ENT_QUOTES) ?>;"></div>



    <?php
    if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php
            echo $this->params->get('introtext'); ?>
        </div>
    <?php
    endif; ?>


    <!--footer-->

    <div class="pagination">
        <?php
        echo $this->pagination->getPagesLinks(); ?>
    </div>
    <div class="copyright">
        <?php
        echo JemOutput::footer(); ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if (!empty($showDateFilter)): ?>
        (function () {
            var form = document.querySelector('.jem-date-filter');
            if (!form) return;
            var rAll = form.querySelector('input[name="jemfilter"][value="all"]');
            var rDate = form.querySelector('input[name="jemfilter"][value="date"]');
            var dateInput = form.querySelector('input[name="jemdate"]');

            function sync() {
                var useDate = rDate && rDate.checked;
                if (dateInput) {
                    dateInput.disabled = !useDate;
                    dateInput.required = useDate;
                }
            }

            if (rAll) rAll.addEventListener('change', sync);
            if (rDate) rDate.addEventListener('change', sync);
            sync();
        })();
        <?php endif; ?>

        var map = L.map('<?= $map_id ?>').setView([52.1, 5.1], <?= (int)$zoom ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // "Je bent hier" – backend-zoom + instelbare marker
        map.locate({setView: false});
        map.on('locationfound', function (e) {
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
                    shadowUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
                    shadowSize: [41, 41],
                    shadowAnchor: [12, 41]
                })
            }).addTo(map).bindPopup(<?= json_encode($youAreHere) ?>).openPopup();
        });
        map.on('locationerror', function (e) {
            console.warn('Geolocatie mislukt:', e.message);
        });

        <?php foreach ($this->venueslist as $v):
        $route = 'index.php?option=com_jem&view=venue&id=' . (int)$v->id . ':' . $v->alias;
        if (!empty($jemItemid)) {
            $route .= '&Itemid=' . (int)$jemItemid;
        }
        $sef = Route::_($route, false);
        $link = Uri::root() . ltrim($sef, '/');

        $venueName = htmlspecialchars($v->venue, ENT_QUOTES);
        $city = htmlspecialchars($v->city, ENT_QUOTES);
        $country = htmlspecialchars($v->country, ENT_QUOTES);

        $popupHtml =
            '<a href="' . $link . '"><strong>' . $venueName . '</strong></a><br>'
            . $city . '<br>'
            . '<img src="/media/com_jem/images/flags/w20-png/' . strtolower(
                $country
            ) . '.png" alt="' . $country . '"/><br>'
            . '<a href="https://maps.google.com/?daddr=' . (float)$v->latitude . ',' . (float)$v->longitude . '">' . Text::_('MOD_JEM_MAP_NAVIGATE') . '</a>';
        ?>
        L.marker([<?= (float)$v->latitude ?>, <?= (float)$v->longitude ?>], {
            icon: L.icon({
                iconUrl: "<?= addslashes($venueMarker) ?>",
                iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32]
            })
        }).addTo(map).bindPopup(<?= json_encode($popupHtml) ?>);
        <?php endforeach; ?>
    });
</script>