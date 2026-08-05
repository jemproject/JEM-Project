/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

(function (document, L) {
    'use strict';

    if (!L) {
        return;
    }

    function isVisible(element) {
        return Boolean(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    function initialiseMap(element) {
        if (element.dataset.jemMapInitialised === 'true') {
            element.jemLeafletMap.invalidateSize();
            return;
        }

        var latitude = Number(element.dataset.latitude);
        var longitude = Number(element.dataset.longitude);
        var zoom = Number.parseInt(element.dataset.zoom, 10);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        var map = L.map(element).setView([latitude, longitude], Number.isFinite(zoom) ? zoom : 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        L.circleMarker([latitude, longitude], {
            radius: 7,
            color: '#ffffff',
            weight: 2,
            fillColor: '#3388ff',
            fillOpacity: 1
        }).addTo(map);
        element.jemLeafletMap = map;
        element.dataset.jemMapInitialised = 'true';
    }

    function initialiseVisibleMaps(container) {
        container.querySelectorAll('.jem-osm-map').forEach(function (element) {
            if (isVisible(element)) {
                initialiseMap(element);
            }
        });
    }

    function initialiseModalMaps(modal) {
        window.setTimeout(function () {
            initialiseVisibleMaps(modal);
        }, 0);
    }

    function start() {
        initialiseVisibleMaps(document);

        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                initialiseModalMaps(modal);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}(document, window.L));
