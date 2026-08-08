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

        var typeIcon = (element.dataset.typeIcon || '').trim();
        var marker;

        if (typeIcon) {
            var typeColor = element.dataset.typeColor || '#d9ddb5';
            var typeIconColor = element.dataset.typeIconColor || '#ffffff';
            var iconHtml = '<span class="jem-map-type-marker" style="display:flex;width:32px;height:32px;align-items:center;justify-content:center;border:2px solid #fff;border-radius:50%;box-sizing:border-box;background:'
                + typeColor + ';color:' + typeIconColor + ';box-shadow:0 1px 4px rgba(0,0,0,.45)"><i class="'
                + typeIcon + '" aria-hidden="true"></i></span>';

            marker = L.marker([latitude, longitude], {
                icon: L.divIcon({
                    className: 'jem-map-type-div-icon',
                    html: iconHtml,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                })
            });
        } else {
            marker = L.marker([latitude, longitude], {
                icon: L.icon({
                    iconUrl: element.dataset.marker,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32]
                })
            });
        }

        marker.addTo(map);
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
