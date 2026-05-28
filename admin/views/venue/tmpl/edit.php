<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
        $wa->useStyle('jem.geostyle')
            ->useScript('keepalive')
            ->useScript('form.validate')
            ->useScript('jem.attachments')
            ->useScript('inlinehelp')
            ->useScript('jem.geocomplete');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

$typeField = $this->form->getField('type_id');

# defining values for centering default-map
$location = JemHelper::defineCenterMap($this->form);

Text::script('COM_JEM_GEOCODE_SEARCHING');
Text::script('COM_JEM_GEOCODE_ADDRESS_REQUIRED');
Text::script('COM_JEM_GEOCODE_COORDINATES_REQUIRED');
Text::script('COM_JEM_GEOCODE_NO_RESULTS');
Text::script('COM_JEM_GEOCODE_REQUEST_FAILED');
Text::script('COM_JEM_GEOCODE_COORDINATES_UPDATED');
Text::script('COM_JEM_GEOCODE_COORDINATES_UPDATED_BY_NAME');
Text::script('COM_JEM_GEOCODE_COORDINATES_UPDATED_BY_ADDRESS');
Text::script('COM_JEM_GEOCODE_ADDRESS_UPDATED');
Text::script('COM_JEM_GEOCODE_CHECK_SUGGESTIONS');
Text::script('COM_JEM_GEOCODE_APPLY_ADDRESS_HINT');
Text::script('COM_JEM_GEOCODE_APPLY_SUGGESTED_ADDRESS');
Text::script('COM_JEM_GEOCODE_NO_SUGGESTED_ADDRESS');
Text::script('COM_JEM_STREET');
Text::script('COM_JEM_ZIP');
Text::script('COM_JEM_CITY');
Text::script('COM_JEM_STATE');
Text::script('COM_JEM_COUNTRY');
?>

<script>
    Joomla.submitbutton = function(task)
    {
        if (task == 'venue.cancel' || document.formvalidator.isValid(document.getElementById('venue-form'))) {
            Joomla.submitform(task, document.getElementById('venue-form'));
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var requestedTab = new URLSearchParams(window.location.search).get('active_tab') || window.location.hash.replace('#', '');
        if (requestedTab === 'attachments' && window.bootstrap && bootstrap.Tab) {
            var tabTrigger = document.querySelector('[data-bs-target="#attachments"], [href="#attachments"], [aria-controls="attachments"]');
            if (tabTrigger) {
                bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
            }
        }

        var addressToCoordsButton = document.getElementById('jem-geocode-address');
        var coordsToAddressButton = document.getElementById('jem-reverse-geocode');
        var applySuggestedAddressButton = document.getElementById('jem-apply-suggested-address');
        var suggestedAddressNotice = document.getElementById('jem-suggested-address');
        var suggestedAddressText = document.getElementById('jem-suggested-address-text');
        var nominatimBaseUrl = 'https://nominatim.openstreetmap.org';
        var lastSuggestedAddress = null;

        function getFieldValue(id) {
            var field = document.getElementById(id);
            return field ? field.value.trim() : '';
        }

        function getFieldText(id) {
            var field = document.getElementById(id);
            if (!field) {
                return '';
            }

            if (field.options && field.selectedIndex >= 0) {
                return field.options[field.selectedIndex].text.trim();
            }

            return field.value.trim();
        }

        function setFieldValue(id, value) {
            var field = document.getElementById(id);
            if (!field || value === undefined || value === null || value === '') {
                return;
            }

            field.value = value;
            field.dispatchEvent(new Event('change', {bubbles: true}));
        }

        function setSuggestedAddress(address, suggestions) {
            lastSuggestedAddress = address || null;

            if (applySuggestedAddressButton) {
                applySuggestedAddressButton.disabled = !lastSuggestedAddress;
            }

            if (suggestedAddressNotice) {
                suggestedAddressNotice.classList.toggle('d-none', !lastSuggestedAddress);
            }

            if (suggestedAddressText) {
                suggestedAddressText.textContent = lastSuggestedAddress && suggestions && suggestions.length
                    ? (Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_CHECK_SUGGESTIONS') : 'OpenStreetMap suggests checking:') + ' ' + suggestions.join('; ')
                    : '';
            }
        }

        function showGeocodeMessage(message, type) {
            if (window.Joomla && Joomla.removeMessages) {
                Joomla.removeMessages();
            } else {
                document.querySelectorAll('#system-message-container .alert').forEach(function (alert) {
                    alert.remove();
                });
            }

            if (window.Joomla && Joomla.renderMessages) {
                Joomla.renderMessages({[type || 'message']: [message]});
            } else {
                alert(message);
            }
        }

        function buildVenueAddressParts() {
            return {
                venue: getFieldValue('jform_venue'),
                street: getFieldValue('jform_street'),
                postalcode: getFieldValue('jform_postalCode'),
                city: getFieldValue('jform_city'),
                state: getFieldValue('jform_state'),
                country: getFieldText('jform_country'),
                countryCode: getFieldValue('jform_country')
            };
        }

        function buildVenueAddress() {
            var address = buildVenueAddressParts();

            return [
                address.street,
                address.postalcode,
                address.city,
                address.state,
                address.country
            ].filter(Boolean).join(', ');
        }

        function buildVenueNamedAddress() {
            var address = buildVenueAddressParts();

            return [
                address.venue,
                address.street,
                address.postalcode,
                address.city,
                address.state,
                address.country
            ].filter(Boolean).join(', ');
        }

        function hasEnoughAddressData() {
            return (getFieldValue('jform_venue') || getFieldValue('jform_street'))
                && getFieldValue('jform_city')
                && getFieldValue('jform_country');
        }

        function hasValidCoordinates(latitude, longitude) {
            var lat = Number(latitude);
            var lon = Number(longitude);

            return Number.isFinite(lat)
                && Number.isFinite(lon)
                && lat >= -90
                && lat <= 90
                && lon >= -180
                && lon <= 180;
        }

        function setMapEnabled() {
            var map = document.getElementById('jform_map');
            if (map && !map.checked) {
                map.checked = true;
                map.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }

        function setButtonBusy(button, busy) {
            if (!button) {
                return;
            }

            button.disabled = busy;
            if (busy) {
                button.dataset.originalText = button.textContent;
                button.textContent = Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_SEARCHING') : 'Searching...';
            } else if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }

        function getAddressPart(address, keys) {
            for (var i = 0; i < keys.length; i++) {
                if (address[keys[i]]) {
                    return address[keys[i]];
                }
            }

            return '';
        }

        function normalizeGeocodeValue(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ');
        }

        function buildReverseAddress(address) {
            var road = getAddressPart(address, ['road', 'pedestrian', 'footway', 'path', 'residential']);
            var houseNumber = getAddressPart(address, ['house_number']);
            var city = getAddressPart(address, ['city', 'town', 'village', 'municipality', 'hamlet', 'county']);

            return {
                street: [road, houseNumber].filter(Boolean).join(' '),
                postalCode: getAddressPart(address, ['postcode']),
                city: city,
                state: getAddressPart(address, ['state', 'region']),
                countryCode: address.country_code ? address.country_code.toUpperCase() : ''
            };
        }

        function applyOsmAddress(osmAddress) {
            setFieldValue('jform_street', osmAddress.street);
            setFieldValue('jform_postalCode', osmAddress.postalCode);
            setFieldValue('jform_city', osmAddress.city);
            setFieldValue('jform_state', osmAddress.state);
            setFieldValue('jform_country', osmAddress.countryCode);
        }

        function getAddressSuggestions(address) {
            var suggestions = [];
            var osmAddress = buildReverseAddress(address || {});
            var checks = [
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_STREET') : 'Street', current: getFieldValue('jform_street'), suggested: osmAddress.street},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_ZIP') : 'Post code', current: getFieldValue('jform_postalCode'), suggested: osmAddress.postalCode},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_CITY') : 'City', current: getFieldValue('jform_city'), suggested: osmAddress.city},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_STATE') : 'County', current: getFieldValue('jform_state'), suggested: osmAddress.state},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_COUNTRY') : 'Country', current: getFieldValue('jform_country'), suggested: osmAddress.countryCode}
            ];

            checks.forEach(function (check) {
                if (!check.suggested) {
                    return;
                }

                suggestions.push(check.label + ': ' + check.suggested);
            });

            return suggestions;
        }

        async function requestNominatim(path, params) {
            var url = new URL(nominatimBaseUrl + path);
            Object.keys(params).forEach(function (key) {
                if (params[key] !== '') {
                    url.searchParams.set(key, params[key]);
                }
            });

            var response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Nominatim request failed (' + response.status + ')');
            }

            return response.json();
        }

        if (addressToCoordsButton) {
            addressToCoordsButton.addEventListener('click', async function () {
                setSuggestedAddress(null);

                if (!hasEnoughAddressData()) {
                    showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_ADDRESS_REQUIRED') : 'Enter venue or street, city and country before searching for coordinates.', 'warning');
                    return;
                }

                var address = buildVenueAddressParts();
                var query = buildVenueAddress();
                var namedQuery = buildVenueNamedAddress();
                var matchedBy = '';
                setButtonBusy(addressToCoordsButton, true);
                try {
                    var results = [];

                    if (namedQuery) {
                        results = await requestNominatim('/search', {
                            format: 'jsonv2',
                            limit: '1',
                            q: namedQuery,
                            addressdetails: '1',
                            countrycodes: address.countryCode && address.countryCode !== '0' ? address.countryCode.toLowerCase() : '',
                            'accept-language': document.documentElement.lang || ''
                        });
                        if (results.length) {
                            matchedBy = 'name';
                        }
                    }

                    if (!results.length) {
                        results = await requestNominatim('/search', {
                            format: 'jsonv2',
                            limit: '1',
                            street: address.street,
                            postalcode: address.postalcode,
                            city: address.city,
                            state: address.state,
                            country: address.country,
                            addressdetails: '1',
                            countrycodes: address.countryCode && address.countryCode !== '0' ? address.countryCode.toLowerCase() : '',
                            'accept-language': document.documentElement.lang || ''
                        });
                        if (results.length) {
                            matchedBy = 'address';
                        }
                    }

                    if (!results.length) {
                        showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_NO_RESULTS') : 'No matching place was found in OpenStreetMap.', 'warning');
                        return;
                    }

                    setFieldValue('jform_latitude', Number(results[0].lat).toFixed(6));
                    setFieldValue('jform_longitude', Number(results[0].lon).toFixed(6));
                    setMapEnabled();
                    test();
                    var successMessage = Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_COORDINATES_UPDATED') : 'Coordinates updated.';
                    if (matchedBy === 'name') {
                        successMessage = Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_COORDINATES_UPDATED_BY_NAME') : 'Coordinates updated. Found by venue name and address.';
                    } else if (matchedBy === 'address') {
                        successMessage = Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_COORDINATES_UPDATED_BY_ADDRESS') : 'Coordinates updated. Found by address only.';
                    }

                    var verifiedAddress = results[0].address || {};
                    try {
                        var reverseResult = await requestNominatim('/reverse', {
                            format: 'jsonv2',
                            lat: results[0].lat,
                            lon: results[0].lon,
                            addressdetails: '1',
                            'accept-language': document.documentElement.lang || ''
                        });
                        verifiedAddress = reverseResult.address || verifiedAddress;
                    } catch (reverseError) {
                        // The coordinates are still useful even if reverse verification fails.
                    }

                    var suggestions = getAddressSuggestions(verifiedAddress);
                    if (suggestions.length) {
                        setSuggestedAddress(buildReverseAddress(verifiedAddress), suggestions);
                    } else {
                        setSuggestedAddress(null);
                    }
                    showGeocodeMessage(successMessage, 'message');
                } catch (error) {
                    showGeocodeMessage((Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_REQUEST_FAILED') : 'Could not contact OpenStreetMap.') + ' ' + error.message, 'error');
                } finally {
                    setButtonBusy(addressToCoordsButton, false);
                }
            });
        }

        if (coordsToAddressButton) {
            coordsToAddressButton.addEventListener('click', async function () {
                setSuggestedAddress(null);

                var latitude = getFieldValue('jform_latitude');
                var longitude = getFieldValue('jform_longitude');
                if (!hasValidCoordinates(latitude, longitude)) {
                    showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_COORDINATES_REQUIRED') : 'Enter valid latitude and longitude before searching for an address.', 'warning');
                    return;
                }

                setButtonBusy(coordsToAddressButton, true);
                try {
                    var result = await requestNominatim('/reverse', {
                        format: 'jsonv2',
                        lat: latitude,
                        lon: longitude,
                        addressdetails: '1',
                        'accept-language': document.documentElement.lang || ''
                    });
                    var address = result.address || {};
                    var osmAddress = buildReverseAddress(address);

                    applyOsmAddress(osmAddress);
                    setSuggestedAddress(null);
                    showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_ADDRESS_UPDATED') : 'Address updated.', 'message');
                } catch (error) {
                    showGeocodeMessage((Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_REQUEST_FAILED') : 'Could not contact OpenStreetMap.') + ' ' + error.message, 'error');
                } finally {
                    setButtonBusy(coordsToAddressButton, false);
                }
            });
        }

        if (applySuggestedAddressButton) {
            applySuggestedAddressButton.addEventListener('click', function () {
                if (!lastSuggestedAddress) {
                    showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_NO_SUGGESTED_ADDRESS') : 'There is no suggested address to apply.', 'warning');
                    return;
                }

                applyOsmAddress(lastSuggestedAddress);
                setSuggestedAddress(null);
                showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_ADDRESS_UPDATED') : 'Address updated.', 'message');
            });
        }
    });

    // window.addEvent('domready', function() {
    window.onload = function() {
        setAttribute();
        test();
    }

    function setAttribute(){
        document.getElementById("tmp_form_postalCode").setAttribute("geo-data", "postal_code");
        document.getElementById("tmp_form_city").setAttribute("geo-data", "locality");
        document.getElementById("tmp_form_state").setAttribute("geo-data", "administrative_area_level_1");
        document.getElementById("tmp_form_street").setAttribute("geo-data", "street_address");
        document.getElementById("tmp_form_route").setAttribute("geo-data", "route");
        document.getElementById("tmp_form_streetnumber").setAttribute("geo-data", "street_number");
        document.getElementById("tmp_form_country").setAttribute("geo-data", "country_short");
        document.getElementById("tmp_form_latitude").setAttribute("geo-data", "lat");
        document.getElementById("tmp_form_longitude").setAttribute("geo-data", "lng");
        document.getElementById("tmp_form_venue").setAttribute("geo-data", "name");
    }

    function meta(){
        var f = document.getElementById('venue-form');
        if(f.jform_meta_keywords.value != "") f.jform_meta_keywords.value += ", ";
        f.jform_meta_keywords.value += f.jform_venue.value+', ' + f.jform_city.value;
    }

    function test() {
        var form = document.getElementById('venue-form');
        var map = $('#jform_map');
        var streetcheck = $(form.jform_street).hasClass('required');
        // if (map && map.checked == true) {
        if (map && map.is(":checked")) {
            var lat = $('#jform_latitude');
            var lon = $('#jform_longitude');

            if (lat.val() == ('' || 0.000000) || lon.val() == ('' || 0.000000)) {
                if (!streetcheck) {
                    addrequired();
                }
            } else {
                if (lat.val() != ('' || 0.000000) && lon.val() != ('' || 0.000000)) {
                    removerequired();
                }
            }
            $('#mapdiv').show();
        }

        // if (map && map.checked == false) {
        if (map && !map.is(":checked")) {
            removerequired();
            $('#mapdiv').hide();
        }
    }

    function addrequired() {
        var form = document.getElementById('venue-form');

        $(form.jform_street).addClass('required');
        $(form.jform_postalCode).addClass('required');
        $(form.jform_city).addClass('required');
        $(form.jform_country).addClass('required');
    }

    function removerequired() {
        var form = document.getElementById('venue-form');

        $(form.jform_street).removeClass('required');
        $(form.jform_postalCode).removeClass('required');
        $(form.jform_city).removeClass('required');
        $(form.jform_country).removeClass('required');
    }


    // jQuery(function() {
    jQuery(document).ready(function() {


        jQuery("#geocomplete").geocomplete({
            map: ".map_canvas",
            <?php echo $location; ?>
            details: "form ",
            detailsAttribute: "geo-data",
            types: ['establishment', 'geocode'],
            mapOptions: {
                  zoom: 16,
                  mapTypeId: "hybrid"
                },
            markerOptions: {
                draggable: true
            }

        });

        jQuery("#geocomplete").bind('geocode:result', function(){
                var street = jQuery("#tmp_form_street").val();
                var route  = jQuery("#tmp_form_route").val();

                if (route) {
                    /* something to add */
                } else {
                    jQuery("#tmp_form_street").val('');
                }
        });

        jQuery("#geocomplete").bind("geocode:dragged", function(event, latLng){
            jQuery("#tmp_form_latitude").val(latLng.lat());
            jQuery("#tmp_form_longitude").val(latLng.lng());
        });

        /* option to attach a reset function to the reset-link
            jQuery("#reset").click(function(){
            jQuery("#geocomplete").geocomplete("resetMarker");
            jQuery("#reset").hide();
            return false;
        });
        */

        jQuery("#find-left").click(function() {
            jQuery("#geocomplete").val(jQuery("#jform_street").val() + ", " + jQuery("#jform_postalCode").val() + " " + jQuery("#jform_city").val());
            jQuery("#geocomplete").trigger("geocode");
        });

        jQuery("#cp-latlong").click(function() {
            document.getElementById("jform_latitude").value = document.getElementById("tmp_form_latitude").value;
            document.getElementById("jform_longitude").value = document.getElementById("tmp_form_longitude").value;
            test();
        });

        jQuery("#cp-address").click(function() {
            document.getElementById("jform_street").value = document.getElementById("tmp_form_street").value;
            document.getElementById("jform_postalCode").value = document.getElementById("tmp_form_postalCode").value;
            document.getElementById("jform_city").value = document.getElementById("tmp_form_city").value;
            document.getElementById("jform_state").value = document.getElementById("tmp_form_state").value;
            document.getElementById("jform_country").value = document.getElementById("tmp_form_country").value;
        });

        jQuery("#cp-venue").click(function() {
            var venue = document.getElementById("tmp_form_venue").value;
            if (venue) {
                document.getElementById("jform_venue").value = venue;
            }
        });

        jQuery("#cp-all").click(function() {
            jQuery("#cp-address").click();
            jQuery("#cp-latlong").click();
            jQuery("#cp-venue").click();
        });

        jQuery('#jform_map').on('keyup keypress blur change', function() {
            test();
        });

        jQuery('#jform_latitude').on('keyup keypress blur change', function() {
            test();
        });

        jQuery('#jform_longitude').on('keyup keypress blur change', function() {
            test();
        });
    });

    jQuery(document).ready(function() {
        jQuery("#venue-geodata").on("click", function() {
            if (jQuery("#venue-geodata").hasClass("pane-toggler-down")) {
                var map = jQuery("#geocomplete").geocomplete("map");
                zoom = map.getZoom();
                center = map.getCenter();
                google.maps.event.trigger(map, 'resize');
                map.setZoom(zoom);
                map.setCenter(center);
            }
        });
    });
</script>

<form
    action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
    class="form-validate" method="post" name="adminForm" id="venue-form" enctype="multipart/form-data">

    <div class="row">
        <div class="col-md-7">
            <!-- START OF LEFT DIV -->
            <!-- <div class="width-55 fltlft"> -->

                <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'info', 'recall' => !empty($this->item->id), 'breakpoint' => 768]); ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'info', Text::_('COM_JEM_VENUE_INFO_TAB')); ?>
                    <fieldset class="adminform">
                        <legend>
                            <?php echo empty($this->item->id) ? Text::_('COM_JEM_NEW_VENUE') : Text::sprintf('COM_JEM_VENUE_DETAILS', $this->item->id); ?>
                        </legend>

                        <ul class="adminformlist">
                            <li><div class="label-form"><?php echo $this->form->renderfield('venue'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('alias'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('street'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('postalCode'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('city'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('state'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('country'); ?></div></li>
                            <li>
                                <div class="label-form">
                                    <div class="control-group">
                                        <div class="control-label"><span aria-hidden="true">&nbsp;</span></div>
                                        <div class="controls">
                                            <div class="btn-toolbar gap-2 mb-2">
                                                <button type="button" class="btn btn-secondary" id="jem-geocode-address"><span class="icon-arrow-down-4" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_GEOCODE_GET_COORDINATES'); ?></button>
                                                <button type="button" class="btn btn-secondary" id="jem-reverse-geocode"><span class="icon-arrow-up-4" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_GEOCODE_GET_ADDRESS'); ?></button>
                                            </div>
                                            <div class="alert alert-info d-none align-items-center gap-2 mt-2 mb-0" id="jem-suggested-address">
                                                <span id="jem-suggested-address-text"></span>
                                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="jem-apply-suggested-address" disabled="disabled"><?php echo Text::_('COM_JEM_GEOCODE_APPLY_SUGGESTED_ADDRESS'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('latitude'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('longitude'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('url'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('color'); ?></div></li>
                            <?php if ($typeField) : ?>
                                <li><div class="label-form"><?php echo $this->form->renderfield('type_id'); ?></div></li>
                            <?php else : ?>
                                <?php echo $this->form->getInput('type_id'); ?>
                            <?php endif; ?>
                            <li><div class="label-form"><?php echo $this->form->renderfield('access'); ?></div></li>
                        </ul>
                        <div class="clr"></div>
                        <div>
                            <?php echo $this->form->getLabel('locdescription'); ?>
                            <div class="clr"></div>
                            <?php echo $this->form->getInput('locdescription'); ?>
                        </div>
                    </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'attachments', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB')); ?>

                    <?php echo $this->loadTemplate('attachments'); ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>

                <!-- END OF LEFT DIV -->
            <!-- </div> -->
        </div>
        <div class="col-md-5">
            <!-- START RIGHT DIV -->
            <!-- <div class="width-40 fltrt"> -->

                <?php //echo HTMLHelper::_('sliders.start', 'venue-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
                <?php //echo HTMLHelper::_('sliders.panel', Text::_('COM_JEM_FIELDSET_PUBLISHING'), 'publishing-details'); ?>
                <div class="accordion" id="accordionVenueForm">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="publishing-details-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#publishing-details" aria-expanded="true" aria-controls="publishing-details">
                            <?php echo Text::_('COM_JEM_FIELDSET_PUBLISHING'); ?>
                        </button>
                        </h2>
                        <div id="publishing-details" class="accordion-collapse collapse show" aria-labelledby="publishing-details-header" data-bs-parent="#accordionVenueForm">
                            <div class="accordion-body">
                                <fieldset class="panelform">
                                    <ul class="adminformlist">
                                        <li><?php echo $this->form->getLabel('id'); ?>
                                            <?php echo $this->form->getInput('id'); ?></li>

                                        <li><?php echo $this->form->getLabel('published'); ?>
                                            <?php echo $this->form->getInput('published'); ?></li>

                                        <?php foreach($this->form->getFieldset('publish') as $field): ?>
                                            <li><?php echo $field->label; ?>
                                                <?php echo $field->input; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="venue-custom-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#venue-custom" aria-expanded="true" aria-controls="venue-custom">
                            <?php echo Text::_('COM_JEM_CUSTOMFIELDS'); ?>
                        </button>
                        </h2>
                        <div id="venue-custom" class="accordion-collapse collapse" aria-labelledby="venue-custom-header" data-bs-parent="#accordionVenueForm">
                            <div class="accordion-body">
                                <fieldset class="panelform">
                                    <ul class="adminformlist">
                                        <?php foreach($this->form->getFieldset('custom') as $field): ?>
                                            <li><?php echo $field->label; ?>
                                                <?php echo $field->input; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="image-event-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#image-event" aria-expanded="true" aria-controls="image-event">
                            <?php echo Text::_('COM_JEM_IMAGE'); ?>
                        </button>
                        </h2>
                        <div id="image-event" class="accordion-collapse collapse" aria-labelledby="image-event-header" data-bs-parent="#accordionVenueForm">
                            <div class="accordion-body">
                                <fieldset class="panelform">
                                    <ul class="adminformlist">
                                        <li><?php echo $this->form->getLabel('locimage'); ?>
                                            <?php echo $this->form->getInput('locimage'); ?>
                                        </li>
                                    </ul>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="meta-event-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#meta-event" aria-expanded="true" aria-controls="meta-event">
                            <?php echo Text::_('COM_JEM_METADATA_INFORMATION'); ?>
                        </button>
                        </h2>
                        <div id="meta-event" class="accordion-collapse collapse" aria-labelledby="meta-event-header" data-bs-parent="#accordionVenueForm">
                            <div class="accordion-body">
                                <fieldset class="panelform">
                                    <input type="button" class="btn btn-primary" value="<?php echo Text::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="meta()" />
                                    <ul class="adminformlist">
                                        <?php foreach($this->form->getFieldset('meta') as $field): ?>
                                            <li><?php echo $field->label; ?>
                                                <?php echo $field->input; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="venue-geodata-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#venue-geodata" aria-expanded="true" aria-controls="venue-geodata">
                            <?php echo Text::_('COM_JEM_FIELDSET_GEODATA'); ?>
                        </button>
                        </h2>
                        <div id="venue-geodata" class="accordion-collapse collapse" aria-labelledby="venue-geodata-header" data-bs-parent="#accordionVenueForm">
                            <div class="accordion-body">
                                <fieldset class="adminform" id="geodata">
                                    <ul class="adminformlist">
                                        <li><?php echo $this->form->getLabel('map'); ?>
                                            <?php echo $this->form->getInput('map'); ?></li>
                                    </ul>
                                    <?php echo Text::_('COM_JEM_ADDRESS_NOTICE'); ?>
                                    <div class="clr"></div>
                                    <div id="mapdiv">
                                        <input id="geocomplete" class="readonly form-control valid" type="text" size="55" placeholder="<?php echo Text::_( 'COM_JEM_VENUE_ADDRPLACEHOLDER' ); ?>" value="" />
                                        <input id="find-left" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_ADDR_FINDVENUEDATA');?>" />
                                        <div class="clr"></div>
                                        <div class="map_canvas"></div>
                                        <ul class="adminformlist label-button-line">
                                            <li><label><?php echo Text::_('COM_JEM_STREET'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_street" />
                                                <input type="hidden" class="readonly" id="tmp_form_streetnumber" readonly="readonly" />
                                                <input type="hidden" class="readonly form-control valid" id="tmp_form_route" readonly="readonly" />
                                            </div>
                                                </li>
                                            <li><label><?php echo Text::_('COM_JEM_ZIP'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_postalCode" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_CITY'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_city"/></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_STATE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_state" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_VENUE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_venue" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_country" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_LATITUDE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_latitude" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_LONGITUDE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_longitude" />
                                             </div>
                                            </li>
                                        </ul>
                                        <div class="clr"></div>
                                        <input id="cp-all" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_DATA'); ?>" style="margin-right: 3em;" />
                                        <input id="cp-address" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_ADDRESS'); ?>" />
                                        <input id="cp-venue" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_VENUE'); ?>" />
                                        <input id="cp-latlong" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_COORDINATES'); ?>" />
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="task" value="" />
                <input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />

                <!-- END RIGHT DIV -->
                <?php echo HTMLHelper::_( 'form.token' ); ?>
            <!-- </div> -->
        </div>
    </div>
    <div class="clr"></div>
</form>
