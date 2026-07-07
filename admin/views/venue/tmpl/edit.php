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

require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

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
Text::script('COM_JEM_GEOCODE_SELECT_RESULT');
Text::script('COM_JEM_GEOCODE_USE_RESULT');
Text::script('COM_JEM_GEOCODE_NO_CONFIDENT_RESULT');
Text::script('COM_JEM_GEOCODE_INVALID_RESULT_SELECTION');
Text::script('COM_JEM_STREET');
Text::script('COM_JEM_ZIP');
Text::script('COM_JEM_CITY');
Text::script('COM_JEM_STATE');
Text::script('COM_JEM_COUNTRY');
Text::script('JCANCEL');
?>
<style>
    #image-event .jem-venue-image-fields {
        margin: 0;
        padding: 0;
    }

    #image-event .jem-venue-image-control .control-group,
    #image-event .jem-venue-image-control .controls {
        margin: 0;
    }

    #image-event .jem-venue-image-control .controls {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 0.45rem;
    }

    #image-event .jem-venue-image-control .control-label,
    #image-event .jem-venue-image-control label {
        display: none;
    }

    #image-event .jem-venue-image-control .fltlft,
    #image-event .jem-venue-image-control .button2-left,
    #image-event .jem-venue-image-control .button2-left .blank {
        float: none;
        margin: 0;
    }

    #image-event .jem-venue-image-control input[type="text"] {
        width: 13.5rem;
    }

    #image-event .jem-venue-image-control .btn-margin {
        margin: 0;
        white-space: nowrap;
    }

    #image-event .jem-venue-image-control .controls::after {
        content: "";
        flex: 0 0 100%;
        order: 1;
    }

    #image-event .jem-venue-image-control img.venue-image {
        flex: 0 0 auto;
        order: 2;
        max-width: 100%;
        object-fit: contain;
        margin: 0.1rem 0 0;
    }

    @media (max-width: 640px) {
        #image-event .jem-venue-image-control .controls {
            display: grid;
            grid-template-columns: 1fr;
        }

        #image-event .jem-venue-image-control input[type="text"],
        #image-event .jem-venue-image-control .btn-margin {
            width: 100%;
        }

        #image-event .jem-venue-image-control .controls::after {
            display: none;
        }

        #image-event .jem-venue-image-control img.venue-image {
            justify-self: start;
        }
    }
</style>

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

        function updateCountryFieldDisplay(field, option) {
            var wrapper = field.closest('joomla-field-fancy-select') || field.parentElement;
            var choicesContainer = wrapper ? wrapper.querySelector('.choices') : null;
            var selectedItems = choicesContainer
                ? choicesContainer.querySelectorAll('.choices__list--single .choices__item, .choices__list--multiple .choices__item')
                : [];

            selectedItems.forEach(function (item) {
                item.textContent = option.text;
                item.dataset.value = option.value;
                item.classList.remove('choices__placeholder');
            });
        }

        function setCountryFieldValue(value, retryCount) {
            var field = document.getElementById('jform_country');
            var countryCode = String(value || '').trim().toUpperCase();
            var attempts = retryCount || 0;
            var fancySelect = field ? field.closest('joomla-field-fancy-select') : null;

            if (!field || !countryCode) {
                return;
            }

            var matchedOption = Array.prototype.slice.call(field.options || []).find(function (option) {
                return normalizeGeocodeValue(option.value) === normalizeGeocodeValue(countryCode)
                    || normalizeGeocodeValue(option.text) === normalizeGeocodeValue(countryCode);
            });

            if (!matchedOption && fancySelect) {
                var matchedChoice = Array.prototype.slice.call(fancySelect.querySelectorAll('.choices__item--choice[data-value]')).find(function (choice) {
                    return normalizeGeocodeValue(choice.dataset.value) === normalizeGeocodeValue(countryCode)
                        || normalizeGeocodeValue(choice.textContent) === normalizeGeocodeValue(countryCode);
                });

                if (matchedChoice) {
                    matchedOption = new Option(matchedChoice.textContent.trim(), matchedChoice.dataset.value, true, true);
                    field.appendChild(matchedOption);
                }
            }

            if (!matchedOption) {
                return;
            }

            field.value = matchedOption.value;
            matchedOption.selected = true;
            matchedOption.setAttribute('selected', 'selected');
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.dispatchEvent(new Event('change', {bubbles: true}));

            if (fancySelect) {
                var choices = fancySelect.choicesInstance || fancySelect.choices || field.choicesInstance || field.choices;

                if (choices) {
                    try {
                        if (typeof choices.removeActiveItems === 'function') {
                            choices.removeActiveItems();
                        }

                        if (typeof choices.setChoiceByValue === 'function') {
                            choices.setChoiceByValue(matchedOption.value);
                        }

                        if (typeof choices.setValue === 'function') {
                            choices.setValue([{
                                value: matchedOption.value,
                                label: matchedOption.text
                            }]);
                        }

                        if (typeof choices.getValue === 'function' && choices.getValue(true) !== matchedOption.value) {
                            choices.setChoices([{
                                value: matchedOption.value,
                                label: matchedOption.text,
                                selected: true
                            }], 'value', 'label', false);
                            choices.setChoiceByValue(matchedOption.value);
                        }
                    } catch (ignore) {
                        // Fall back to the visible Choices markup below.
                    }
                } else {
                    fancySelect.value = matchedOption.value;
                }

                updateCountryFieldDisplay(field, matchedOption);
                fancySelect.dispatchEvent(new Event('input', {bubbles: true}));
                fancySelect.dispatchEvent(new Event('change', {bubbles: true}));
            }

            if (attempts < 3 && getFieldValue('jform_country') !== matchedOption.value) {
                window.setTimeout(function () {
                    setCountryFieldValue(matchedOption.value, attempts + 1);
                }, 150 * (attempts + 1));
            }
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
            var countryCode = getFieldValue('jform_country');

            return {
                venue: getFieldValue('jform_venue'),
                street: getFieldValue('jform_street'),
                postalcode: getFieldValue('jform_postalCode'),
                city: getFieldValue('jform_city'),
                state: getFieldValue('jform_state'),
                country: countryCode && countryCode !== '0' ? getFieldText('jform_country') : '',
                countryCode: countryCode
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
            return getFieldValue('jform_venue')
                || (getFieldValue('jform_street') && getFieldValue('jform_city') && getFieldValue('jform_country'));
        }

        function hasStructuredAddressData(address) {
            return (address.street || address.postalcode)
                && address.city
                && address.countryCode
                && address.countryCode !== '0';
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
            setCountryFieldValue(osmAddress.countryCode);
        }

        function getAddressSuggestions(address, onlyDifferences) {
            var suggestions = [];
            var osmAddress = buildReverseAddress(address || {});
            var checks = [
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_STREET') : 'Street', current: getFieldValue('jform_street'), suggested: osmAddress.street, relaxed: true},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_ZIP') : 'Post code', current: getFieldValue('jform_postalCode'), suggested: osmAddress.postalCode, relaxed: false},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_CITY') : 'City', current: getFieldValue('jform_city'), suggested: osmAddress.city, relaxed: false},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_STATE') : 'County', current: getFieldValue('jform_state'), suggested: osmAddress.state, relaxed: true},
                {label: Joomla.Text ? Joomla.Text._('COM_JEM_COUNTRY') : 'Country', current: getFieldValue('jform_country'), suggested: osmAddress.countryCode, relaxed: false}
            ];

            checks.forEach(function (check) {
                if (!check.suggested) {
                    return;
                }

                if (onlyDifferences && (!check.current || geocodeValuesMatch(check.current, check.suggested, check.relaxed))) {
                    return;
                }

                suggestions.push(check.label + ': ' + check.suggested);
            });

            return suggestions;
        }

        function geocodeValuesMatch(current, suggested, relaxed) {
            var currentValue = normalizeGeocodeValue(current);
            var suggestedValue = normalizeGeocodeValue(suggested);

            if (!currentValue || !suggestedValue) {
                return false;
            }

            return currentValue === suggestedValue
                || (relaxed && (currentValue.indexOf(suggestedValue) !== -1 || suggestedValue.indexOf(currentValue) !== -1));
        }

        function scoreGeocodeResult(result, expectedAddress) {
            var resultAddress = buildReverseAddress((result && result.address) || {});
            var score = 0;
            var hardMismatch = false;
            var matches = {
                street: geocodeValuesMatch(expectedAddress.street, resultAddress.street, true),
                postalCode: geocodeValuesMatch(expectedAddress.postalcode, resultAddress.postalCode, false),
                city: geocodeValuesMatch(expectedAddress.city, resultAddress.city, false),
                state: geocodeValuesMatch(expectedAddress.state, resultAddress.state, true),
                country: geocodeValuesMatch(expectedAddress.countryCode, resultAddress.countryCode, false)
            };

            if (expectedAddress.countryCode && resultAddress.countryCode && !matches.country) {
                hardMismatch = true;
            }

            if (expectedAddress.postalcode && resultAddress.postalCode && !matches.postalCode) {
                hardMismatch = true;
            }

            if (expectedAddress.city && resultAddress.city && !matches.city) {
                hardMismatch = true;
            }

            if (matches.country) {
                score += 3;
            }

            if (matches.postalCode) {
                score += 3;
            }

            if (matches.city) {
                score += 4;
            }

            if (matches.street) {
                score += 2;
            }

            if (matches.state) {
                score += 1;
            }

            return {
                result: result,
                address: resultAddress,
                score: score,
                hardMismatch: hardMismatch,
                confident: !hardMismatch
                    && matches.city
                    && matches.country
                    && (!expectedAddress.postalcode || !resultAddress.postalCode || matches.postalCode)
                    && (!expectedAddress.street || !resultAddress.street || matches.street)
                    && score >= 8
            };
        }

        function findBestGeocodeResult(results, expectedAddress) {
            var scored = (results || []).map(function (result) {
                return scoreGeocodeResult(result, expectedAddress);
            }).sort(function (a, b) {
                return b.score - a.score;
            }).filter(function (scoredResult, index, scoredResults) {
                var result = scoredResult.result || {};
                var resultKey = [result.lat, result.lon, result.osm_type, result.osm_id].join(':');

                return scoredResults.findIndex(function (otherResult) {
                    var other = otherResult.result || {};
                    return [other.lat, other.lon, other.osm_type, other.osm_id].join(':') === resultKey;
                }) === index;
            }).slice(0, 5);

            return {
                scored: scored,
                best: scored.length && scored[0].confident ? scored[0] : null
            };
        }

        function getSingleSafeGeocodeResult(scoredResults) {
            return scoredResults.length === 1 && !scoredResults[0].hardMismatch
                ? scoredResults[0]
                : null;
        }

        function formatGeocodeChoice(scored, index) {
            var address = scored.address || {};
            var parts = [
                address.street,
                address.postalCode,
                address.city,
                address.state,
                address.countryCode
            ].filter(Boolean);

            if (!parts.length && scored.result && scored.result.display_name) {
                parts.push(scored.result.display_name);
            }

            return (index + 1) + '. ' + parts.join(', ');
        }

        function chooseGeocodeResult(scoredResults) {
            if (!scoredResults.length) {
                return Promise.resolve(null);
            }

            return new Promise(function (resolve) {
                var message = Joomla.Text
                    ? Joomla.Text._('COM_JEM_GEOCODE_SELECT_RESULT')
                    : 'OpenStreetMap returned several possible matches. Select one to use, or cancel to keep current coordinates.';
                var useLabel = Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_USE_RESULT') : 'Use this location';
                var cancelLabel = Joomla.Text ? Joomla.Text._('JCANCEL') : 'Cancel';
                var overlay = document.createElement('div');
                var dialog = document.createElement('div');
                var title = document.createElement('h3');
                var list = document.createElement('div');
                var footer = document.createElement('div');
                var cancelButton = document.createElement('button');

                function close(selectedResult) {
                    document.removeEventListener('keydown', handleKeydown);
                    overlay.remove();
                    resolve(selectedResult || null);
                }

                function handleKeydown(event) {
                    if (event.key === 'Escape') {
                        close(null);
                    }
                }

                overlay.className = 'jem-geocode-choice-overlay';
                overlay.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:1rem;';
                dialog.className = 'jem-geocode-choice-dialog';
                dialog.style.cssText = 'width:min(640px,100%);max-height:90vh;overflow:auto;background:#fff;color:#1f2933;border-radius:.5rem;box-shadow:0 1rem 3rem rgba(0,0,0,.25);padding:1rem;';
                title.style.cssText = 'font-size:1.1rem;line-height:1.4;margin:0 0 1rem;';
                title.textContent = message;
                list.style.cssText = 'display:grid;gap:.5rem;';

                scoredResults.forEach(function (scoredResult, index) {
                    var option = document.createElement('button');
                    var optionTitle = document.createElement('span');
                    var optionAction = document.createElement('span');

                    option.type = 'button';
                    option.className = 'btn btn-light jem-geocode-choice-option';
                    option.style.cssText = 'display:flex;gap:1rem;align-items:center;justify-content:space-between;width:100%;padding:.75rem 1rem;text-align:left;border:1px solid #ccd3dc;border-radius:.375rem;background:#fff;color:inherit;cursor:pointer;';
                    optionTitle.textContent = formatGeocodeChoice(scoredResult, index);
                    optionAction.textContent = useLabel;
                    optionAction.style.cssText = 'white-space:nowrap;font-weight:600;color:#1d4ed8;';
                    option.appendChild(optionTitle);
                    option.appendChild(optionAction);
                    option.addEventListener('click', function () {
                        close(scoredResult);
                    });
                    list.appendChild(option);
                });

                footer.style.cssText = 'display:flex;justify-content:flex-end;margin-top:1rem;';
                cancelButton.type = 'button';
                cancelButton.className = 'btn btn-secondary';
                cancelButton.textContent = cancelLabel;
                cancelButton.addEventListener('click', function () {
                    close(null);
                });
                footer.appendChild(cancelButton);
                dialog.appendChild(title);
                dialog.appendChild(list);
                dialog.appendChild(footer);
                overlay.appendChild(dialog);
                overlay.addEventListener('click', function (event) {
                    if (event.target === overlay) {
                        close(null);
                    }
                });
                document.addEventListener('keydown', handleKeydown);
                document.body.appendChild(overlay);
                var firstOption = list.querySelector('button');
                if (firstOption) {
                    firstOption.focus();
                }
            });
        }

        async function applyGeocodeResult(scoredResult, matchedBy, expectedAddress, selectedManually) {
            var result = scoredResult.result;

            setFieldValue('jform_latitude', Number(result.lat).toFixed(6));
            setFieldValue('jform_longitude', Number(result.lon).toFixed(6));
            setMapEnabled();
            test();

            var verifiedAddress = (result && result.address) || {};
            try {
                var reverseResult = await requestNominatim('/reverse', {
                    format: 'jsonv2',
                    lat: result.lat,
                    lon: result.lon,
                    addressdetails: '1',
                    'accept-language': document.documentElement.lang || ''
                });
                verifiedAddress = reverseResult.address || verifiedAddress;
            } catch (reverseError) {
                // The coordinates are still useful even if reverse verification fails.
            }

            var reverseAddress = buildReverseAddress(verifiedAddress);
            if (selectedManually || !hasStructuredAddressData(expectedAddress)) {
                applyOsmAddress(reverseAddress);
                setSuggestedAddress(null);
            } else {
                var suggestions = getAddressSuggestions(verifiedAddress, true);
                setSuggestedAddress(suggestions.length ? reverseAddress : null, suggestions);
            }

            if (matchedBy === 'name') {
                return Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_COORDINATES_UPDATED_BY_NAME') : 'Coordinates updated. Found by venue name and address.';
            }

            return Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_COORDINATES_UPDATED_BY_ADDRESS') : 'Coordinates updated. Found by address only.';
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
                            limit: '5',
                            q: namedQuery,
                            addressdetails: '1',
                            countrycodes: address.countryCode && address.countryCode !== '0' ? address.countryCode.toLowerCase() : '',
                            'accept-language': document.documentElement.lang || ''
                        });
                        if (results.length) {
                            matchedBy = 'name';
                        }
                    }

                    if (!findBestGeocodeResult(results, address).best && hasStructuredAddressData(address)) {
                        var addressResults = await requestNominatim('/search', {
                            format: 'jsonv2',
                            limit: '5',
                            street: address.street,
                            postalcode: address.postalcode,
                            city: address.city,
                            state: address.state,
                            country: address.country,
                            addressdetails: '1',
                            countrycodes: address.countryCode && address.countryCode !== '0' ? address.countryCode.toLowerCase() : '',
                            'accept-language': document.documentElement.lang || ''
                        });
                        if (addressResults.length) {
                            results = results.concat(addressResults);
                            matchedBy = 'address';
                        }
                    }

                    if (!results.length) {
                        showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_NO_RESULTS') : 'No matching place was found in OpenStreetMap.', 'warning');
                        return;
                    }

                    var geocodeSelection = findBestGeocodeResult(results, address);
                    var selectedManually = false;
                    var selectedResult = geocodeSelection.best || getSingleSafeGeocodeResult(geocodeSelection.scored);

                    if (!selectedResult) {
                        selectedResult = await chooseGeocodeResult(geocodeSelection.scored);
                        selectedManually = !!selectedResult;
                    }

                    if (!selectedResult) {
                        showGeocodeMessage(Joomla.Text ? Joomla.Text._('COM_JEM_GEOCODE_NO_CONFIDENT_RESULT') : 'OpenStreetMap did not return a confident match. No coordinates were changed.', 'warning');
                        return;
                    }

                    var successMessage = await applyGeocodeResult(selectedResult, matchedBy, address, selectedManually);
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
            setCountryFieldValue(document.getElementById("tmp_form_country").value);
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
                                        <?php
                                        $customFields = array();
                                        foreach ($this->form->getFieldset('custom') as $field) {
                                            $customFields[$field->fieldname] = $field;
                                        }
                                        ?>
                                        <?php foreach(JemCustomFields::getOrderedFields('venue', 'backend') as $fieldName): ?>
                                            <?php if (empty($customFields[$fieldName])) continue; ?>
                                            <?php $field = $customFields[$fieldName]; ?>
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
                                    <ul class="adminformlist jem-venue-image-fields">
                                        <li class="jem-venue-image-control"><div class="label-form"><?php echo $this->form->renderfield('locimage'); ?></div></li>
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
                                                <input type="text" class="form-control valid" id="tmp_form_street" />
                                                <input type="hidden" class="readonly" id="tmp_form_streetnumber" readonly="readonly" />
                                                <input type="hidden" class="readonly form-control valid" id="tmp_form_route" readonly="readonly" />
                                            </div>
                                                </li>
                                            <li><label><?php echo Text::_('COM_JEM_ZIP'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_postalCode" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_CITY'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_city"/></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_STATE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_state" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_VENUE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_venue" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_country" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_LATITUDE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_latitude" /></div>
                                            </li>
                                            <li><label><?php echo Text::_('COM_JEM_LONGITUDE'); ?></label>
                                            <div class="geodata-info">
                                                <input type="text" class="form-control valid" id="tmp_form_longitude" />
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
