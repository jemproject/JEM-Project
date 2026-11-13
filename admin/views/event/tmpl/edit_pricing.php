<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

require_once JPATH_SITE . '/components/com_jem/classes/money.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/pricingcountry.class.php';

$requirements = (array) ($this->item->pricing_requirements ?? array());
$venueExists = !empty($requirements['venue_exists']);
$physicalActive = ($this->item->venue_allocation_mode ?? 'none') !== 'none';
$countryCode = JemPricingCountry::forVenue($requirements['country_code'] ?? '');
$capacityReady = !empty($requirements['capacity_ready']);
$pricedReady = $countryCode !== '' && (!$physicalActive || ($venueExists && $capacityReady));
$isSaved = !empty($this->item->id);
$effectiveDate = trim((string) ($this->item->dates ?? ''));
$parsedEffectiveDate = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate, new DateTimeZone('UTC'));
if (!$parsedEffectiveDate || $parsedEffectiveDate->format('Y-m-d') !== $effectiveDate) {
    $effectiveDate = gmdate('Y-m-d');
}
$poolCandidates = array_values((array) ($requirements['pool_candidates'] ?? array()));
$configurationOptions = array_values((array) ($requirements['configuration_options'] ?? array()));
$configurationAssignments = array_values((array) ($requirements['configuration_assignments'] ?? array()));
$configurationProfiles = array_values((array) ($requirements['configuration_profiles'] ?? array()));
$configurationCustomRequired = !empty($requirements['configuration_custom_required']);
$eventCurrency = strtoupper(trim((string) ($this->item->currency ?? '')));
if (preg_match('/^[A-Z]{3}$/D', $eventCurrency) !== 1) {
    $eventCurrency = JemEventPricingCapacityService::defaultCurrency();
}
$currencyLocale = Factory::getApplication()->getLanguage()->getTag();
$currencyLabel = JemMoney::currencyLabel($eventCurrency, $currencyLocale);
$selectedConfigurationKey = (string) ($this->item->venue_configuration_key ?? '');
$selectedAssignmentIds = json_decode((string) ($this->item->venue_assignment_ids ?? '[]'), true);
if (!is_array($selectedAssignmentIds)) {
    $selectedAssignmentIds = array();
}
?>

<div class="jem-event-pricing"
     data-country-code="<?php echo htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8'); ?>"
     data-effective-date="<?php echo htmlspecialchars($effectiveDate, ENT_QUOTES, 'UTF-8'); ?>">
    <fieldset class="adminform">
        <legend><?php echo Text::_('COM_JEM_EVENT_PRICING_CAPACITY_TAB'); ?></legend>
        <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_PRICING_INTRO'); ?></p>
        <?php echo $this->form->renderField('pricing_mode'); ?>
    </fieldset>

    <div class="jem-event-pricing-details">
        <div class="jem-event-pricing-readiness" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_EVENT_PRICING_REQUIREMENTS'), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="jem-event-pricing-check <?php echo $venueExists ? 'is-ready' : 'is-missing'; ?>" data-requirement="venue">
                <strong><?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENT_VENUE'); ?></strong>
                <span><?php echo $venueExists ? Text::_('JYES') : Text::_('JNO'); ?></span>
            </div>
            <div class="jem-event-pricing-check <?php echo $countryCode !== '' ? 'is-ready' : 'is-missing'; ?>" data-requirement="country">
                <strong><?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENT_COUNTRY'); ?></strong>
                <span><?php echo $countryCode !== '' ? htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8') : Text::_('JNO'); ?></span>
            </div>
            <div class="jem-event-pricing-check <?php echo $capacityReady ? 'is-ready' : 'is-missing'; ?>" data-requirement="configuration">
                <strong><?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENT_CONFIGURATION'); ?></strong>
                <span><?php echo $capacityReady
                    ? Text::sprintf(
                        'COM_JEM_EVENT_PRICING_CONFIGURATION_SUMMARY',
                        (int) ($requirements['space_count'] ?? 0),
                        (int) ($requirements['configured_capacity'] ?? 0),
                        (int) ($requirements['profile_revision'] ?? 0)
                    )
                    : Text::_('JNO'); ?></span>
            </div>
        </div>

        <fieldset class="adminform jem-event-venue-configuration" <?php echo $capacityReady ? '' : 'hidden'; ?>>
            <legend><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION'); ?></legend>
            <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_DESC'); ?></p>
            <label for="jem-event-price-venue-configuration-select" class="form-label">
                <?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_SELECT'); ?>
            </label>
            <select id="jem-event-price-venue-configuration-select" class="form-select">
                <?php if ($selectedConfigurationKey === 'saved') : ?>
                    <option value="saved" selected><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_SAVED'); ?></option>
                <?php endif; ?>
                <?php foreach ($configurationOptions as $option) : ?>
                    <option value="<?php echo htmlspecialchars((string) $option['key'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $selectedConfigurationKey === (string) $option['key'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($configurationCustomRequired || $selectedConfigurationKey === 'custom') : ?>
                    <option value="custom" <?php echo $selectedConfigurationKey === 'custom' ? 'selected' : ''; ?>>
                        <?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_CUSTOM'); ?>
                    </option>
                <?php endif; ?>
            </select>
            <div class="jem-event-venue-custom mt-3" <?php echo $selectedConfigurationKey === 'custom' ? '' : 'hidden'; ?>>
                <p class="form-text"><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_CUSTOM_DESC'); ?></p>
                <label class="form-label" for="jem-event-price-venue-custom-profile"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE'); ?></label>
                <select class="form-select mb-3" id="jem-event-price-venue-custom-profile">
                    <?php foreach ($configurationProfiles as $profile) : ?>
                        <option value="<?php echo (int) $profile['profile_id']; ?>"><?php echo htmlspecialchars((string) $profile['profile_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="jem-event-venue-custom-grid">
                    <?php foreach ($configurationAssignments as $assignment) : ?>
                        <?php $assignmentId = (int) $assignment['id']; ?>
                        <label class="jem-event-venue-custom-option" data-profile-id="<?php echo (int) ($assignment['profile_id'] ?? 0); ?>">
                            <input type="checkbox" value="<?php echo $assignmentId; ?>" data-profile-id="<?php echo (int) ($assignment['profile_id'] ?? 0); ?>"
                                <?php echo in_array($assignmentId, array_map('intval', $selectedAssignmentIds), true) ? 'checked' : ''; ?>>
                            <span>
                                <strong><?php echo htmlspecialchars((string) $assignment['space_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php echo htmlspecialchars((string) $assignment['layout_name'], ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo (int) $assignment['capacity']; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="jem-event-venue-configuration-preview mt-3" aria-live="polite"></div>
        </fieldset>

        <div class="alert alert-info" data-pricing-notice="general" <?php echo $physicalActive ? 'hidden' : ''; ?>>
            <?php echo Text::_('COM_JEM_EVENT_PRICING_GENERAL_CAPACITY_HELP'); ?>
        </div>
        <div class="alert alert-warning" data-pricing-notice="missing" <?php echo $pricedReady ? 'hidden' : ''; ?>>
            <?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENTS_MISSING'); ?>
        </div>
        <div class="alert alert-info" data-pricing-notice="first-save" <?php echo $pricedReady && !$isSaved ? '' : 'hidden'; ?>>
            <?php echo Text::_('COM_JEM_EVENT_PRICING_FIRST_SAVE_NOTICE'); ?>
        </div>
        <div class="alert alert-info" data-pricing-notice="snapshot" <?php echo $pricedReady && $isSaved ? '' : 'hidden'; ?>>
            <?php echo Text::_('COM_JEM_EVENT_PRICING_SNAPSHOT_NOTICE'); ?>
        </div>

        <?php echo $this->form->getInput('management_fee_mode'); ?>
        <?php echo $this->form->getInput('management_fee_basis'); ?>

        <fieldset class="adminform jem-event-pricing-policy">
            <legend><?php echo Text::_('COM_JEM_EVENT_PRICING_POLICY'); ?></legend>
            <div class="jem-event-pricing-grid">
                <div class="control-group">
                    <div class="control-label"><label for="jform_currency_display"><?php echo Text::_('COM_JEM_EVENT_CURRENCY'); ?></label></div>
                    <div class="controls">
                        <output id="jform_currency_display" class="form-control-plaintext fw-semibold"><?php echo htmlspecialchars($currencyLabel, ENT_QUOTES, 'UTF-8'); ?></output>
                        <?php echo $this->form->getInput('currency'); ?>
                        <div id="jform_currency-desc" class="form-text hide-aware-inline-help d-none"><?php echo Text::_('COM_JEM_EVENT_CURRENCY_DESC'); ?></div>
                    </div>
                </div>
                <?php echo $this->form->renderField('prices_include_tax'); ?>
                <?php echo $this->form->renderField('default_tax_rate_id'); ?>
                <?php echo $this->form->renderField('management_fee_value'); ?>
                <?php echo $this->form->renderField('management_fee_tax_rate_id'); ?>
                <?php echo $this->form->renderField('management_fee_refundable'); ?>
            </div>
        </fieldset>

        <?php if ($isSaved) : ?>
            <fieldset class="adminform jem-event-capacity-pools">
                <legend><?php echo Text::_('COM_JEM_EVENT_CAPACITY_AREAS'); ?></legend>
                <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_CAPACITY_AREAS_DESC'); ?></p>
                <?php echo $this->form->getInput('capacity_pools'); ?>
            </fieldset>
        <?php endif; ?>

        <fieldset class="adminform jem-event-price-options">
            <legend><?php echo Text::_('COM_JEM_EVENT_PRICE_OPTIONS'); ?></legend>
            <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_PRICE_OPTIONS_DESC'); ?></p>
            <div class="jem-event-price-toolbar">
                <button type="button" class="btn btn-outline-secondary btn-sm jem-price-advanced-toggle" aria-expanded="false">
                    <?php echo Text::_('COM_JEM_EVENT_PRICE_SHOW_ADVANCED'); ?>
                </button>
            </div>
            <?php echo $this->form->getInput('event_prices'); ?>
        </fieldset>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.jem-event-pricing');
    const mode = document.getElementById('jform_pricing_mode');
    if (!root || !mode) {
        return;
    }

    const details = root.querySelector('.jem-event-pricing-details');
    let countryCode = (root.dataset.countryCode || '').toUpperCase();
    const allocationMode = document.getElementById('jform_venue_allocation_mode');
    const defaultTaxCountry = <?php echo json_encode(JemPricingCountry::defaultCountry()); ?>;
    let venueRequirements = <?php echo json_encode($requirements, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const effectiveDate = root.dataset.effectiveDate || '';
    const isSaved = <?php echo $isSaved ? 'true' : 'false'; ?>;
    let poolCandidates = <?php echo json_encode(
        $poolCandidates,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;
    let configurationState = {
        options: <?php echo json_encode($configurationOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        assignments: <?php echo json_encode($configurationAssignments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        customRequired: <?php echo $configurationCustomRequired ? 'true' : 'false'; ?>
    };
    const configurationFieldset = root.querySelector('.jem-event-venue-configuration');
    const configurationSelect = root.querySelector('#jem-event-price-venue-configuration-select');
    const configurationCustom = root.querySelector('.jem-event-venue-custom');
    const configurationCustomGrid = root.querySelector('.jem-event-venue-custom-grid');
    const configurationCustomProfile = root.querySelector('#jem-event-price-venue-custom-profile');
    const configurationPreview = root.querySelector('.jem-event-venue-configuration-preview');
    const capacityRoot = document.querySelector('.jem-event-capacity');
    const configurationController = capacityRoot ? capacityRoot.jemVenueConfiguration : null;
    let configurationDirty = false;
    const advancedToggle = root.querySelector('.jem-price-advanced-toggle');
    const copyText = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_COPY')); ?>;
    const copySuffix = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_COPY_SUFFIX')); ?>;
    const previewNet = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_NET')); ?>;
    const previewTax = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_TAX')); ?>;
    const previewGross = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_GROSS')); ?>;
    const previewIncomplete = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_INCOMPLETE')); ?>;

    const priceSubform = function () {
        return Array.from(root.querySelectorAll('joomla-field-subform')).find(function (field) {
            return (field.getAttribute('name') || '').indexOf('[event_prices]') !== -1;
        }) || null;
    };

    const priceRows = function () {
        const subform = priceSubform();
        return subform && typeof subform.getRows === 'function'
            ? subform.getRows()
            : Array.from(root.querySelectorAll('.jem-event-price-options .subform-repeatable-group'));
    };

    /**
     * Match the server's ASCII normalisation and 64-character admission-code limit.
     * @param {string} value Submitted admission code.
     * @returns {string} Normalised code.
     */
    const normalisePriceCode = function (value) {
        return String(value).replace(/^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g, '')
            .replace(/[A-Z]/g, function (letter) { return letter.toLowerCase(); })
            .replace(/[^a-z0-9_-]+/g, '-').slice(0, 64).replace(/^[-_]+|[-_]+$/g, '');
    };

    // Keep stored codes reserved even when their rows are removed from the form.
    const storedPriceCodes = new Set(<?php echo json_encode(array_column((array) ($this->item->event_prices ?? array()), 'code'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.map(normalisePriceCode));

    /**
     * Allocate a code for Add or Copy, preserving space for its suffix.
     * @param {string} value Base code, before any copy suffix.
     * @param {HTMLElement} target New row excluded from the current-code check.
     * @param {boolean} copy Whether to append the copy suffix.
     * @returns {string} Code unused by stored prices and other form rows.
     */
    const uniquePriceCode = function (value, target, copy = false) {
        const base = normalisePriceCode(value) || 'admission';
        const usedCodes = new Set(storedPriceCodes);
        priceRows().forEach(function (row) {
            const code = row.querySelector('[name$="[code]"]');
            if (row !== target && code) {
                usedCodes.add(normalisePriceCode(code.value));
            }
        });
        let tail = copy ? '-copy' : '';
        let candidate = base.slice(0, 64 - tail.length) + tail;
        let suffix = 2;
        while (usedCodes.has(candidate)) {
            tail = (copy ? '-copy-' : '-') + suffix++;
            candidate = base.slice(0, 64 - tail.length) + tail;
        }
        return candidate;
    };

    const updateMode = function () {
        const classic = mode.value === 'classic';
        details.hidden = classic;

        priceRows().forEach(function (row) {
            const taxRate = row.querySelector('[name$="[tax_rate_id]"]');
            if (!taxRate) {
                return;
            }

            taxRate.toggleAttribute('required', !classic);
            taxRate.classList.toggle('required', !classic);
        });

        details.querySelectorAll('input, select, textarea, fieldset').forEach(function (control) {
            if (classic) {
                if (control.hasAttribute('required') && !control.hasAttribute('data-jem-pricing-required')) {
                    control.setAttribute('data-jem-pricing-required', 'attribute');
                    control.removeAttribute('required');
                }
                if (control.classList.contains('required') && !control.hasAttribute('data-jem-pricing-required-class')) {
                    control.setAttribute('data-jem-pricing-required-class', '1');
                    control.classList.remove('required');
                }
                return;
            }

            if (control.getAttribute('data-jem-pricing-required') === 'attribute') {
                control.setAttribute('required', 'required');
                control.removeAttribute('data-jem-pricing-required');
            }
            if (control.getAttribute('data-jem-pricing-required-class') === '1') {
                control.classList.add('required');
                control.removeAttribute('data-jem-pricing-required-class');
            }
        });
    };

    const sortedAssignmentIds = function (ids) {
        return Array.from(new Set((ids || []).map(Number).filter(function (id) { return id > 0; })))
            .sort(function (left, right) { return left - right; });
    };

    const selectedConfigurationOption = function () {
        return configurationState.options.find(function (option) {
            return option.key === configurationSelect.value;
        }) || null;
    };

    const selectedCustomIds = function () {
        return sortedAssignmentIds(Array.from(configurationCustomGrid.querySelectorAll('input:checked')).map(function (input) {
            return input.dataset.profileId === configurationCustomProfile.value ? input.value : 0;
        }));
    };

    const renderConfigurationPreview = function (ids, option) {
        configurationPreview.replaceChildren();
        if (!ids.length) {
            configurationPreview.className = 'jem-event-venue-configuration-preview mt-3 alert alert-warning';
            configurationPreview.textContent = <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_REQUIRED')); ?>;
            return;
        }
        const selected = configurationState.assignments.filter(function (assignment) {
            return ids.indexOf(Number(assignment.id)) !== -1;
        });
        if (!selected.length && configurationSelect.value === 'saved') {
            configurationPreview.className = 'jem-event-venue-configuration-preview mt-3 alert alert-info';
            configurationPreview.textContent = <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_SAVED_DESC')); ?>;
            return;
        }
        const capacity = selected.reduce(function (total, assignment) { return total + Number(assignment.capacity || 0); }, 0);
        configurationPreview.className = 'jem-event-venue-configuration-preview mt-3';
        const heading = document.createElement('strong');
        heading.textContent = (option && option.label ? option.label : <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_CUSTOM')); ?>);
        const list = document.createElement('ul');
        selected.forEach(function (assignment) {
            const item = document.createElement('li');
            item.textContent = assignment.space_name + ' · ' + assignment.layout_name + ' · ' + assignment.capacity;
            list.appendChild(item);
        });
        const summary = document.createElement('span');
        summary.className = 'badge bg-primary';
        summary.textContent = capacity + ' ' + <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_PLACES')); ?>;
        configurationPreview.append(heading, list, summary);
    };

    /** Send a Pricing choice to the shared Capacity controller. @returns {void} */
    const syncConfigurationSelection = function () {
        if (configurationController) {
            configurationController.select(configurationSelect.value, selectedCustomIds(), configurationCustomProfile.value);
        }
    };

    const filterTaxRates = function () {
        root.querySelectorAll('select[id$="tax_rate_id"]').forEach(function (select) {
            Array.from(select.options).forEach(function (option) {
                const optionCountry = (option.dataset.countryCode || '').toUpperCase();
                const validFrom = option.dataset.validFrom || '';
                const validUntil = option.dataset.validUntil || '';
                const countryApplies = !option.value || !optionCountry || !countryCode || optionCountry === countryCode;
                const dateApplies = !option.value || !effectiveDate
                    || ((!validFrom || validFrom === '0000-00-00' || validFrom <= effectiveDate)
                        && (!validUntil || validUntil === '0000-00-00' || validUntil >= effectiveDate));
                const applicable = countryApplies && dateApplies;
                option.hidden = !applicable;
                option.disabled = !applicable && !option.selected;
            });
        });
    };

    const populateCapacityOptions = function () {
        if ((isSaved && !configurationDirty) || !poolCandidates.length || !allocationMode || allocationMode.value === 'none') {
            return;
        }
        priceRows().forEach(function (row) {
            const select = row.querySelector('[name$="[capacity_pool_id]"]');
            if (!select) {
                return;
            }
            const selectedOption = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
            const selectedCodeMatch = selectedOption ? selectedOption.textContent.match(/\[([^\]]+)\]/) : null;
            const selectedCode = selectedCodeMatch ? selectedCodeMatch[1] : '';
            Array.from(select.options).forEach(function (option) {
                if (option.value.indexOf('source:') === 0 && !poolCandidates.some(function (pool) {
                    return option.value === 'source:' + pool.code;
                })) {
                    option.remove();
                }
            });
            poolCandidates.forEach(function (pool) {
                const value = 'source:' + pool.code;
                const label = pool.name + ' [' + pool.code + '] - ' + pool.capacity;
                const existing = Array.from(select.options).find(function (option) { return option.value === value; });
                if (existing) {
                    if (existing.textContent !== label) {
                        existing.textContent = label;
                    }
                    return;
                }
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            });
            if (selectedCode && poolCandidates.some(function (pool) { return pool.code === selectedCode; })) {
                select.value = 'source:' + selectedCode;
            } else if (poolCandidates.length === 1 && (!select.value || configurationDirty)) {
                select.value = 'source:' + poolCandidates[0].code;
            }
        });
    };

    const renderCustomAssignments = function (checkedIds) {
        configurationCustomGrid.replaceChildren();
        configurationState.assignments.forEach(function (assignment) {
            const label = document.createElement('label');
            const input = document.createElement('input');
            const text = document.createElement('span');
            label.className = 'jem-event-venue-custom-option';
            label.dataset.profileId = assignment.profile_id;
            input.type = 'checkbox';
            input.value = assignment.id;
            input.dataset.profileId = assignment.profile_id;
            input.checked = checkedIds.indexOf(Number(assignment.id)) !== -1;
            text.textContent = assignment.space_name + ' · ' + assignment.layout_name + ' · ' + assignment.capacity;
            label.append(input, text);
            configurationCustomGrid.appendChild(label);
        });
    };

    const updateRequirement = function (name, ready, value) {
        const check = root.querySelector('[data-requirement="' + name + '"]');
        if (!check) {
            return;
        }
        check.classList.toggle('is-ready', ready);
        check.classList.toggle('is-missing', !ready);
        const result = check.querySelector('span');
        if (result) {
            result.textContent = ready ? value : <?php echo json_encode(Text::_('JNO')); ?>;
        }
    };

    /** Refresh optional physical controls and the effective country for tax choices. */
    const updatePricingReadiness = function () {
        const physical = Boolean(allocationMode && allocationMode.value !== 'none');
        countryCode = String(venueRequirements.country_code || defaultTaxCountry || '').toUpperCase();
        root.dataset.countryCode = countryCode;
        ['venue', 'configuration'].forEach(function (name) {
            root.querySelector('[data-requirement="' + name + '"]').hidden = !physical;
        });
        updateRequirement('country', Boolean(countryCode), countryCode);
        const ready = Boolean(countryCode && (!physical || (venueRequirements.venue_exists && venueRequirements.capacity_ready)));
        root.querySelector('[data-pricing-notice="general"]').hidden = physical;
        root.querySelector('[data-pricing-notice="missing"]').hidden = !physical || ready;
        root.querySelector('[data-pricing-notice="first-save"]').hidden = !physical || !ready || isSaved;
        root.querySelector('[data-pricing-notice="snapshot"]').hidden = !physical || !ready || !isSaved;
        configurationFieldset.hidden = !physical || !venueRequirements.capacity_ready;
        const pools = root.querySelector('.jem-event-capacity-pools');
        if (pools) {
            pools.hidden = !physical;
        }
        filterTaxRates();
    };

    /**
     * Reflect the sole Capacity state without writing a second set of submitted fields.
     * @param {object} state Shared configuration and venue requirements.
     * @returns {void}
     */
    const applySharedConfiguration = function (state) {
        const requirementsChanged = venueRequirements !== state.requirements;
        venueRequirements = state.requirements;
        configurationState = {
            options: venueRequirements.configuration_options || [],
            assignments: venueRequirements.configuration_assignments || [],
            customRequired: Boolean(venueRequirements.configuration_custom_required)
        };
        if (requirementsChanged) {
            configurationSelect.replaceChildren();
            state.options.forEach(function (configuration) {
                const option = document.createElement('option');
                option.value = configuration.key;
                option.textContent = configuration.label;
                configurationSelect.appendChild(option);
            });
            configurationCustomProfile.replaceChildren();
            (venueRequirements.configuration_profiles || []).forEach(function (profile) {
                const option = document.createElement('option');
                option.value = profile.profile_id;
                option.textContent = profile.profile_name;
                configurationCustomProfile.appendChild(option);
            });
            renderCustomAssignments(state.assignmentIds);
        }
        configurationSelect.value = state.key;
        configurationCustomProfile.value = state.profileId;
        configurationCustom.hidden = state.key !== 'custom';
        configurationCustomGrid.querySelectorAll('input').forEach(function (input) {
            input.checked = state.assignmentIds.indexOf(Number(input.value)) !== -1;
            input.closest('label').hidden = input.dataset.profileId !== state.profileId;
        });
        configurationDirty = configurationDirty || state.changed;
        const option = selectedConfigurationOption();
        poolCandidates = option && Array.isArray(option.pool_candidates) ? option.pool_candidates : [];
        if (state.key === 'custom') {
            configurationState.options.forEach(function (configuration) {
                (configuration.pool_candidates || []).forEach(function (pool) {
                    const selected = configurationState.assignments.some(function (assignment) {
                        return state.assignmentIds.indexOf(Number(assignment.id)) !== -1
                            && Number(pool.venue_layout_id) === Number(assignment.layout_id);
                    });
                    if (selected && !poolCandidates.some(function (candidate) { return candidate.code === pool.code; })) {
                        poolCandidates.push(pool);
                    }
                });
            });
        }
        renderConfigurationPreview(state.assignmentIds, option);
        updateRequirement('venue', Boolean(venueRequirements.venue_exists), <?php echo json_encode(Text::_('JYES')); ?>);
        updateRequirement('configuration', Boolean(venueRequirements.capacity_ready), venueRequirements.configuration_summary || <?php echo json_encode(Text::_('JYES')); ?>);
        updatePricingReadiness();
        populateCapacityOptions();
        updatePreviews();
    };

    const fieldKey = function (control) {
        const match = (control.name || '').match(/\[([^\]]+)\](?:\[\])?$/);
        return match ? match[1] : '';
    };

    const rowControls = function (row) {
        return Array.from(row.querySelectorAll('input[name], select[name], textarea[name]')).filter(function (control) {
            return control.closest('.subform-repeatable-group') === row;
        });
    };

    const decorateRows = function () {
        const advancedKeys = [
            'code', 'description', 'quota', 'min_quantity', 'max_quantity',
            'available_from', 'available_until', 'min_age', 'max_age',
            'access_level_id', 'user_group_id', 'verification_mode'
        ];
        const inlineHelpReference = document.querySelector('#jform_pricing_mode-desc');
        const showInlineHelp = inlineHelpReference
            && !inlineHelpReference.classList.contains('d-none')
            && window.getComputedStyle(inlineHelpReference).display !== 'none';
        priceRows().forEach(function (row) {
            const rowId = row.querySelector('[name$="[id]"]');
            const rowCode = row.querySelector('[name$="[code]"]');
            if (rowCode) {
                const stored = rowId && Number.parseInt(rowId.value || '0', 10) > 0;
                rowCode.readOnly = stored;
                rowCode.classList.toggle('readonly', stored);
            }
            rowControls(row).forEach(function (control) {
                if (advancedKeys.indexOf(fieldKey(control)) !== -1) {
                    const group = control.closest('.control-group');
                    if (group) {
                        group.classList.add('jem-price-advanced-field');
                    }
                }
            });

            row.querySelectorAll('[id$="-desc"]').forEach(function (description) {
                description.classList.add('hide-aware-inline-help');
                description.classList.toggle('d-none', !showInlineHelp);

                const text = description.querySelector('.form-text');
                if (text) {
                    text.classList.remove('hide-aware-inline-help', 'd-none');
                }
            });

            const buttonGroup = row.querySelector(':scope > .btn-toolbar .btn-group');
            if (buttonGroup && !buttonGroup.querySelector('.jem-price-copy')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-secondary jem-price-copy';
                button.textContent = copyText;
                button.setAttribute('aria-label', copyText);
                buttonGroup.prepend(button);
            }

            if (!row.querySelector(':scope > .jem-price-preview')) {
                const preview = document.createElement('div');
                preview.className = 'jem-price-preview alert alert-light';
                preview.setAttribute('aria-live', 'polite');
                row.appendChild(preview);
            }
        });
    };

    const decimalMinorUnits = function (value) {
        const match = String(value || '').trim().match(/^(\d+)(?:\.(\d{1,2}))?$/);
        if (!match) {
            return null;
        }
        return (BigInt(match[1]) * 100n) + BigInt((match[2] || '').padEnd(2, '0'));
    };

    const rateBasisPoints = function (value) {
        const match = String(value || '').trim().match(/^(\d{1,3})(?:\.(\d{1,2}))?$/);
        return match ? (BigInt(match[1]) * 100n) + BigInt((match[2] || '').padEnd(2, '0')) : 0n;
    };

    const roundedRatio = function (value, multiplier, divisor) {
        return ((value * multiplier) + (divisor / 2n)) / divisor;
    };

    const formatMoney = function (minor, currency) {
        const whole = minor / 100n;
        const decimals = String(minor % 100n).padStart(2, '0');
        const decimal = Number(String(whole) + '.' + decimals);
        if (currency && window.Intl && Intl.NumberFormat) {
            try {
                return new Intl.NumberFormat(<?php echo json_encode($currencyLocale); ?>, {
                    style: 'currency',
                    currency: currency
                }).format(decimal);
            } catch (ignore) {
                // Fall through to the exact ISO display.
            }
        }
        return String(whole) + '.' + decimals + (currency ? ' ' + currency : '');
    };

    const updatePreviews = function () {
        const includesTax = String((root.querySelector('[name="jform[prices_include_tax]"]:checked') || {}).value || '1') === '1';
        const currency = (root.querySelector('#jform_currency') || {}).value || '';
        priceRows().forEach(function (row) {
            const amount = row.querySelector('[name$="[amount]"]');
            const tax = row.querySelector('[name$="[tax_rate_id]"]');
            const preview = row.querySelector(':scope > .jem-price-preview');
            const entered = amount ? decimalMinorUnits(amount.value) : null;
            const option = tax && tax.selectedIndex >= 0 ? tax.options[tax.selectedIndex] : null;
            if (!preview || entered === null || !option || !option.value) {
                if (preview) {
                    preview.textContent = previewIncomplete;
                }
                return;
            }

            const rate = rateBasisPoints(option.dataset.taxRate || '0');
            let net = entered;
            let taxAmount = 0n;
            let gross = entered;
            if (rate > 0n && includesTax) {
                net = roundedRatio(entered, 10000n, 10000n + rate);
                taxAmount = entered - net;
            } else if (rate > 0n) {
                taxAmount = roundedRatio(entered, rate, 10000n);
                gross = entered + taxAmount;
            }
            preview.textContent = previewNet + ': ' + formatMoney(net, currency)
                + ' · ' + previewTax + ': ' + formatMoney(taxAmount, currency)
                + ' · ' + previewGross + ': ' + formatMoney(gross, currency);
        });
    };

    const copyRow = function (source) {
        const subform = priceSubform();
        if (!subform || typeof subform.addRow !== 'function') {
            return;
        }
        const target = subform.addRow(source);
        if (!target) {
            return;
        }
        const sourceByKey = {};
        rowControls(source).forEach(function (control) {
            const key = fieldKey(control);
            if (!sourceByKey[key]) {
                sourceByKey[key] = [];
            }
            sourceByKey[key].push(control);
        });
        rowControls(target).forEach(function (control, index) {
            const key = fieldKey(control);
            const candidates = sourceByKey[key] || [];
            const sourceControl = candidates.find(function (candidate) {
                return candidate.type !== 'radio' || candidate.value === control.value;
            }) || candidates[index] || candidates[0];
            if (!sourceControl) {
                return;
            }
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = sourceControl.checked;
            } else {
                control.value = sourceControl.value;
                if (sourceControl.dataset.altValue) {
                    control.dataset.altValue = sourceControl.dataset.altValue;
                }
            }
            if (key === 'id') {
                control.value = '0';
            } else if (key === 'code') {
                const baseCode = normalisePriceCode(sourceControl.value).replace(/(?:-copy(?:-\d+)?)$/, '');
                control.value = uniquePriceCode(baseCode, target, true);
            } else if (key === 'name') {
                control.value = (sourceControl.value || '') + copySuffix;
            }
        });
        mode.value = 'multiple';
        mode.dispatchEvent(new Event('change', {bubbles: true}));
        decorateRows();
        filterTaxRates();
        populateCapacityOptions();
        updatePreviews();
    };

    root.addEventListener('click', function (event) {
        const copy = event.target.closest('.jem-price-copy');
        if (copy) {
            copyRow(copy.closest('.subform-repeatable-group'));
            return;
        }
        if (event.target.closest('.jem-price-advanced-toggle')) {
            const show = !root.classList.contains('show-price-advanced');
            root.classList.toggle('show-price-advanced', show);
            advancedToggle.setAttribute('aria-expanded', show ? 'true' : 'false');
            advancedToggle.textContent = show
                ? <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_HIDE_ADVANCED')); ?>
                : <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_SHOW_ADVANCED')); ?>;
        }
    });

    root.addEventListener('subform-row-add', function (event) {
        if (!event.detail || !event.detail.row || !event.detail.row.closest('.jem-event-price-options')) {
            return;
        }
        const addedCode = event.detail.row.querySelector('[name$="[code]"]');
        if (addedCode && !addedCode.value.trim()) {
            addedCode.value = uniquePriceCode('admission', event.detail.row);
            addedCode.dispatchEvent(new Event('input', {bubbles: true}));
        }
        const defaultTax = document.getElementById('jform_default_tax_rate_id');
        const rowTax = event.detail.row.querySelector('[name$="[tax_rate_id]"]');
        if (defaultTax && rowTax && defaultTax.value) {
            rowTax.value = defaultTax.value;
        }
        if (priceRows().length > 1) {
            mode.value = 'multiple';
            mode.dispatchEvent(new Event('change', {bubbles: true}));
        }
        decorateRows();
        filterTaxRates();
        populateCapacityOptions();
        updatePreviews();
    });

    root.addEventListener('subform-row-remove', function () {
        window.setTimeout(function () {
            if (priceRows().length === 1 && mode.value === 'multiple') {
                mode.value = 'single';
                mode.dispatchEvent(new Event('change', {bubbles: true}));
            }
            updatePreviews();
        }, 0);
    });

    root.addEventListener('input', updatePreviews);
    root.addEventListener('change', updatePreviews);

    const defaultTax = document.getElementById('jform_default_tax_rate_id');
    if (defaultTax) {
        defaultTax.addEventListener('change', function () {
            priceRows().forEach(function (row) {
                const rowTax = row.querySelector('[name$="[tax_rate_id]"]');
                if (rowTax && !rowTax.value && defaultTax.value) {
                    rowTax.value = defaultTax.value;
                    rowTax.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
        });
    }

    if (configurationController) {
        configurationSelect.addEventListener('change', syncConfigurationSelection);
        configurationCustomGrid.addEventListener('change', syncConfigurationSelection);
        configurationCustomProfile.addEventListener('change', syncConfigurationSelection);
        capacityRoot.addEventListener('jem:venue-configuration-change', function (event) {
            applySharedConfiguration(event.detail);
        });
    } else {
        configurationFieldset.disabled = true;
    }

    if (allocationMode) {
        allocationMode.addEventListener('change', updatePricingReadiness);
    }
    mode.addEventListener('change', updateMode);
    new MutationObserver(function () {
        updateMode();
        decorateRows();
        filterTaxRates();
        populateCapacityOptions();
    }).observe(root, {childList: true, subtree: true});
    updateMode();
    decorateRows();
    filterTaxRates();
    if (configurationController) {
        applySharedConfiguration(configurationController.read());
    }
    updatePricingReadiness();
    populateCapacityOptions();
    updatePreviews();
});
</script>
